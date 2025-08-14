<?php
// public_html/shapefit/add_food_to_diary.php - VERSÃO FINAL CORRIGIDA

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php'; // Adicionado para consistência, caso use no futuro
require_once APP_ROOT_PATH . '/includes/functions.php'; // Adicionado para consistência

// --- Lógica PHP para obter dados da página ---
$user_id = $_SESSION['user_id'];
// A criação da variável $csrf_token_for_html foi removida, pois agora usamos $_SESSION['csrf_token'] diretamente.

$target_date_str = $_GET['date'] ?? date('Y-m-d');
$target_meal_type_slug = $_GET['meal_type'] ?? 'breakfast';

$date_obj_target = DateTime::createFromFormat('Y-m-d', $target_date_str);
if (!$date_obj_target || $date_obj_target->format('Y-m-d') !== $target_date_str) {
    $target_date_str = date('Y-m-d');
}

$meal_type_options = [
    'breakfast' => 'Café da Manhã', 'morning_snack' => 'Lanche da Manhã', 'lunch' => 'Almoço',
    'afternoon_snack' => 'Lanche da Tarde', 'dinner' => 'Jantar', 'supper' => 'Ceia',
    'pre_workout' => 'Pré-Treino', 'post_workout' => 'Pós-Treino'
];

if (empty($target_meal_type_slug) || !isset($meal_type_options[$target_meal_type_slug])) {
    $current_hour_for_select = (int)date('G');
    if ($current_hour_for_select >= 5 && $current_hour_for_select < 10) { $target_meal_type_slug = 'breakfast'; }
    elseif ($current_hour_for_select >= 10 && $current_hour_for_select < 12) { $target_meal_type_slug = 'morning_snack'; }
    elseif ($current_hour_for_select >= 12 && $current_hour_for_select < 15) { $target_meal_type_slug = 'lunch'; }
    elseif ($current_hour_for_select >= 15 && $current_hour_for_select < 18) { $target_meal_type_slug = 'afternoon_snack'; }
    elseif ($current_hour_for_select >= 18 && $current_hour_for_select < 21) { $target_meal_type_slug = 'dinner'; }
    else { $target_meal_type_slug = 'supper'; }
}

// --- Variáveis para o Template ---
$page_title = "Cadastrar Refeição";
$extra_css = ['add-food-page.css', 'modal.css'];
$extra_js = ['add_food_logic.js'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<!-- Div principal para aplicar estilos específicos -->
<div class="add-food-interface">

    <div class="container add-food-container">
        <header class="header-nav">
            <a href="javascript:history.back()" class="back-button" aria-label="Voltar"><i class="fas fa-chevron-left"></i></a>
            <h1 class="page-title">Cadastrar Refeição</h1>
        </header>

        <main>
            <form id="log-entire-meal-form" action="<?php echo BASE_APP_URL; ?>/process_add_entire_meal.php" method="POST">
                
                <!-- CORREÇÃO: Pega o token diretamente da sessão, que é garantido de existir pelo layout_header -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <input type="hidden" name="log_date" id="log_date_hidden_for_meal" value="<?php echo htmlspecialchars($target_date_str); ?>">
                <input type="hidden" name="log_meal_type" id="log_meal_type_hidden_for_meal" value="<?php echo htmlspecialchars($target_meal_type_slug); ?>">
                
                <div class="card-shadow meal-setup-card">
                    <div class="meal-setup-row">
                        <div class="form-group">
                            <label for="log_date_display">Data</label>
                            <input type="date" id="log_date_display" class="form-control" value="<?php echo htmlspecialchars($target_date_str); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="log_meal_type_display">Refeição</label>
                            <select id="log_meal_type_display" class="form-control" required>
                                <?php foreach ($meal_type_options as $key => $value): ?>
                                    <option value="<?php echo $key; ?>" <?php if ($key === $target_meal_type_slug) echo 'selected'; ?>><?php echo htmlspecialchars($value); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </form>

            <section class="search-and-scan-section">
                <div class="form-group">
                    <div class="search-input-wrapper">
                        <input type="search" id="food-search-input" class="form-control" placeholder="Buscar um alimento..." autocomplete="off">
                        <button type="button" id="scan-barcode-btn" class="btn-icon-action" aria-label="Escanear"><i class="fas fa-barcode"></i></button>
                    </div>
                </div>
                <div id="search-results-container" style="display:none;"></div>
            </section>

            <div id="selected-food-details-container" class="card-shadow"></div>
            
            <section class="current-meal-summary card-shadow">
                <h4>Sua Refeição Atual</h4>
                <ul id="current-meal-items-list">
                    <li class="empty-meal-placeholder">Nenhum alimento adicionado ainda.</li>
                </ul>
                <div class="current-meal-totals">
                    <p>Total de Calorias: <span id="current-meal-total-kcal">0</span></p>
                </div>
            </section>
        </main>
    </div> 

    <!-- Modal do Scanner -->
    <div id="barcode-scanner-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <h3 class="modal-title">Escanear Código de Barras</h3>
            <button class="modal-close-btn" id="close-scanner-modal-btn">×</button>
            <div id="scanner-container"></div>
            <div id="scanner-status"></div>
            <div id="scanner-result-confirmation" style="display:none;">
                <h4>Produto Encontrado</h4>
                <div id="scanned-product-info"></div>
                <p>Deseja adicionar este alimento à sua base de dados?</p>
                <form id="save-scanned-food-form"></form>
                <div class="form-actions">
                    <button type="button" id="rescan-barcode-btn" class="btn btn-secondary">Escanear Outro</button>
                    <button type="button" id="save-scanned-food-btn" class="btn btn-primary">Sim, Adicionar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Novo Modal - Produto Não Encontrado -->
    <div id="product-not-found-modal" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <button class="modal-close-btn" data-close-modal="product-not-found-modal">×</button>
            <h3 class="modal-title">Não encontrei esse produto!</h3>
            <p class="modal-subtitle">Por favor, ajude nossa comunidade enviando uma foto da tabela nutricional.</p>
            <div class="modal-actions">
                <button type="button" id="take-nutrition-photo-btn" class="btn btn-primary"><i class="fas fa-camera"></i> Tirar foto</button>
                <button type="button" id="choose-nutrition-photo-btn" class="btn btn-primary"><i class="fas fa-images"></i> Escolher foto da galeria</button>
                <a href="create_custom_food.php" class="link-secondary">Digitar informação</a>
            </div>
        </div>
    </div>

    <footer class="form-actions-fixed">
        <button type="button" id="cancel-log-entire-meal-btn" class="btn btn-secondary">Cancelar</button>
        <button type="submit" form="log-entire-meal-form" id="save-entire-meal-btn" class="btn btn-primary" disabled>Adicionar ao Diário</button>
    </footer>

</div><!-- Fim do .add-food-interface -->

<?php
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>