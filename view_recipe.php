<?php
// public_html/shapefit/view_recipe.php
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
$extra_js = ['favorite_logic.js']; // <-- ADICIONE ESTA LINHA
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$user_id = $_SESSION['user_id'];

// CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token_for_html = $_SESSION['csrf_token'];

$recipe_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$recipe_id) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Receita inválida selecionada.'];
    header("Location: " . BASE_APP_URL . "/main_app.php");
    exit();
}

$recipe = null;
$ingredients = [];
$is_favorited_by_user = false;

// Buscar dados da receita
$stmt_recipe = $conn->prepare("SELECT * FROM sf_recipes WHERE id = ? AND is_public = TRUE");
if ($stmt_recipe) {
    $stmt_recipe->bind_param("i", $recipe_id);
    $stmt_recipe->execute();
    $result_recipe = $stmt_recipe->get_result();
    if ($result_recipe->num_rows > 0) {
        $recipe = $result_recipe->fetch_assoc();

        // Verificar se esta receita é favorita do usuário logado
        $stmt_check_fav = $conn->prepare("SELECT recipe_id FROM sf_user_favorite_recipes WHERE user_id = ? AND recipe_id = ?");
        if ($stmt_check_fav) {
            $stmt_check_fav->bind_param("ii", $user_id, $recipe['id']);
            $stmt_check_fav->execute();
            $result_check_fav = $stmt_check_fav->get_result();
            if ($result_check_fav->num_rows > 0) {
                $is_favorited_by_user = true;
            }
            $stmt_check_fav->close();
        } else {
            error_log("Erro ao preparar verificação de favorito para recipe_id {$recipe['id']}: " . $conn->error);
        }
    }
    $stmt_recipe->close();
} else {
    error_log("Erro ao preparar busca da receita ID {$recipe_id}: " . $conn->error);
    die("Ocorreu um erro ao buscar a receita. Tente novamente mais tarde.");
}

if (!$recipe) {
    $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Receita não encontrada ou não está disponível.'];
    header("Location: " . BASE_APP_URL . "/main_app.php");
    exit();
}


// =========================================================================
//      NOVA LÓGICA INTELIGENTE PARA INGREDIENTES E DESCRIÇÃO
// =========================================================================

// Primeiro, verifica se os ingredientes estão na coluna 'notes' (formato do robô)
if (!empty($recipe['notes']) && str_starts_with($recipe['notes'], 'Ingredientes:')) {
    
    // Remove o prefixo "Ingredientes: " para pegar só a lista
    $ingredients_text = substr($recipe['notes'], strlen('Ingredientes: '));
    
    // Separa a lista em um array, uma linha para cada ingrediente
    $ingredients_raw = explode("\n", trim($ingredients_text));

    // Limpa qualquer linha vazia ou espaço em branco extra
    $ingredients = array_filter(array_map('trim', $ingredients_raw));

    // Como já usamos as 'notes' para os ingredientes, limpamos a variável
    // para não aparecer de novo na seção "Observação" lá embaixo.
    $recipe['notes'] = null;

} else {
    // Se não for uma receita do robô, ele tenta o método antigo (para compatibilidade)
    $stmt_ingredients = $conn->prepare("SELECT ingredient_description FROM sf_recipe_ingredients WHERE recipe_id = ? ORDER BY display_order ASC, id ASC");
    if ($stmt_ingredients) {
        $stmt_ingredients->bind_param("i", $recipe_id);
        $stmt_ingredients->execute();
        $result_ingredients = $stmt_ingredients->get_result();
        while($row = $result_ingredients->fetch_assoc()) {
            $ingredients[] = $row['ingredient_description'];
        }
        $stmt_ingredients->close();
    }
}

// Limpa a descrição se ela for a mensagem padrão do robô
if (trim($recipe['description']) === 'Receita importada automaticamente do FitLab.') {
    $recipe['description'] = '';
}
// =========================================================================


// Pré-seleção do tipo de refeição
$current_hour_for_select = (int)date('G');
$default_meal_type_for_select = 'lunch';
if ($current_hour_for_select >= 5 && $current_hour_for_select < 10) { $default_meal_type_for_select = 'breakfast'; }
elseif ($current_hour_for_select >= 10 && $current_hour_for_select < 12) { $default_meal_type_for_select = 'morning_snack'; }
elseif ($current_hour_for_select >= 12 && $current_hour_for_select < 15) { $default_meal_type_for_select = 'lunch'; }
elseif ($current_hour_for_select >= 15 && $current_hour_for_select < 18) { $default_meal_type_for_select = 'afternoon_snack'; }
elseif ($current_hour_for_select >= 18 && $current_hour_for_select < 21) { $default_meal_type_for_select = 'dinner'; }
else { $default_meal_type_for_select = 'supper'; }

$page_title = htmlspecialchars($recipe['name']);
$extra_css = ['recipe_detail_page.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container recipe-detail-container">
    <div class="header-nav recipe-detail-header">
        <a href="javascript:history.back()" class="back-button" aria-label="Voltar">
            <i class="fas fa-chevron-left"></i>
        </a>
        <a href="#" class="recipe-action-favorite favorite-toggle-btn <?php echo $is_favorited_by_user ? 'is-favorited' : ''; ?>" 
           data-recipe-id="<?php echo $recipe['id']; ?>"
           data-csrf-token="<?php echo $csrf_token_for_html; ?>"
           aria-label="<?php echo $is_favorited_by_user ? 'Desfavoritar receita' : 'Favoritar receita'; ?>">
            <i class="<?php echo $is_favorited_by_user ? 'fas' : 'far'; ?> fa-heart"></i>
        </a>
    </div>

    <?php if ($recipe['image_filename']): ?>
    <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename']); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="recipe-detail-image">
    <?php else: ?>
    <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/placeholder_food.jpg'; ?>" alt="Imagem não disponível" class="recipe-detail-image">
    <?php endif; ?>

    <div class="recipe-main-info card-shadow-light">
        <h1 class="recipe-name-main"><?php echo htmlspecialchars($recipe['name']); ?></h1>
        <?php if (!empty($recipe['description'])): ?>
            <p class="recipe-description-short"><?php echo nl2br(htmlspecialchars($recipe['description'])); ?></p>
        <?php endif; ?>
        <div class="recipe-rating"> <!-- Estático por enquanto -->
            <i class="fas fa-star"></i> <i class="fas fa-star"></i> <i class="fas fa-star"></i> <i class="fas fa-star-half-alt"></i> <i class="far fa-star"></i>
        </div>
    </div>

   <!-- BLOCO NOVO E CORRIGIDO (À PROVA DE FALHAS) -->
<div class="recipe-macros-summary card-shadow-light">
    <div class="macro-info-item">
        <span class="value"><?php echo number_format($recipe['total_kcal_per_serving'], 0, ',', '.'); ?></span>
        <span class="label">Kcal</span>
    </div>
    <div class="macro-info-item">
        <span class="value"><?php echo number_format($recipe['total_carbs_g_per_serving'], 1, ',', '.'); ?>g</span>
        <span class="label">Carbo</span>
    </div>
    <div class="macro-info-item">
        <span class="value"><?php echo number_format($recipe['total_fat_g_per_serving'], 1, ',', '.'); ?>g</span>
        <span class="label">Gordura</span>
    </div>
    <div class="macro-info-item">
        <span class="value"><?php echo number_format($recipe['total_protein_g_per_serving'], 1, ',', '.'); ?>g</span>
        <span class="label">Proteína</span>
    </div>
    <p class="recipe-serving-info">
        Informação nutricional por <?php echo htmlspecialchars(strtolower($recipe['servings'] ?? 'porção')); ?>
    </p>
</div>
<!-- FIM DO BLOCO CORRIGIDO -->

    <div class="recipe-timing-servings card-shadow-light">
        <?php
            $total_time = ($recipe['prep_time_minutes'] ?? 0) + ($recipe['cook_time_minutes'] ?? 0);
        ?>
        <?php if ($total_time > 0): ?>
        <div class="timing-item"><i class="far fa-clock"></i> <?php echo $total_time; ?> min</div>
        <?php endif; ?>
        <?php if (!empty($recipe['servings'])): ?>
        <div class="servings-item"><i class="fas fa-utensils"></i> Rende <?php echo htmlspecialchars($recipe['servings']); ?></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($ingredients)): ?>
    <div class="recipe-section card-shadow-light">
        <h3 class="recipe-section-title">Ingredientes</h3>
        <ul class="recipe-ingredient-list">
            <?php foreach($ingredients as $ingredient): ?>
                <li>- <?php echo htmlspecialchars($ingredient); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="recipe-section card-shadow-light">
        <h3 class="recipe-section-title">Modo de Preparo</h3>
        <div class="recipe-instructions">
            <?php
            $steps = explode("\n", trim($recipe['instructions']));
            $step_number = 1;
            foreach ($steps as $step_text) {
                $step_text_trimmed = trim($step_text);
                if (!empty($step_text_trimmed)) {
                    $step_text_cleaned = preg_replace('/^\d+[\.\)]\s*/', '', $step_text_trimmed);
                    echo '<div class="instruction-step"><span class="step-number">' . $step_number++ . '</span><p>' . nl2br(htmlspecialchars($step_text_cleaned)) . '</p></div>';
                }
            }
            ?>
        </div>
    </div>

    <?php if (!empty($recipe['notes'])): ?>
    <div class="recipe-section recipe-notes-card card-shadow-light">
        <h3 class="recipe-section-title">Observação</h3>
        <p><?php echo nl2br(htmlspecialchars($recipe['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <div class="register-meal-section card-shadow">
        <h3 class="recipe-section-title text-center">Registrar esta Refeição</h3>
        <form action="<?php echo BASE_APP_URL; ?>/process_log_meal.php" method="POST" id="log-meal-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_for_html; ?>">
            <input type="hidden" name="recipe_id" value="<?php echo $recipe['id']; ?>">
            <input type="hidden" name="kcal_per_serving" value="<?php echo $recipe['total_kcal_per_serving']; ?>">
            <input type="hidden" name="protein_per_serving" value="<?php echo $recipe['total_protein_g_per_serving']; ?>">
            <input type="hidden" name="carbs_per_serving" value="<?php echo $recipe['total_carbs_g_per_serving']; ?>">
            <input type="hidden" name="fat_per_serving" value="<?php echo $recipe['total_fat_g_per_serving']; ?>">

            <div class="form-group">
                <label for="meal_type">Refeição:</label>
                <select name="meal_type" id="meal_type" class="form-control">
                    <option value="breakfast" <?php if($default_meal_type_for_select == 'breakfast') echo 'selected';?>>Café da Manhã</option>
                    <option value="morning_snack" <?php if($default_meal_type_for_select == 'morning_snack') echo 'selected';?>>Lanche da Manhã</option>
                    <option value="lunch" <?php if($default_meal_type_for_select == 'lunch') echo 'selected';?>>Almoço</option>
                    <option value="afternoon_snack" <?php if($default_meal_type_for_select == 'afternoon_snack') echo 'selected';?>>Lanche da Tarde</option>
                    <option value="dinner" <?php if($default_meal_type_for_select == 'dinner') echo 'selected';?>>Jantar</option>
                    <option value="supper" <?php if($default_meal_type_for_select == 'supper') echo 'selected';?>>Ceia</option>
                    <option value="pre_workout">Pré-Treino</option>
                    <option value="post_workout">Pós-Treino</option>
                </select>
            </div>
            <div class="form-group">
                <label for="date_consumed">Data:</label>
                <select name="date_consumed" id="date_consumed" class="form-control">
                    <option value="<?php echo date('Y-m-d'); ?>">Hoje, <?php echo date('d/m'); ?></option>
                    <option value="<?php echo date('Y-m-d', strtotime('-1 day')); ?>">Ontem, <?php echo date('d/m', strtotime('-1 day')); ?></option>
                    <option value="<?php echo date('Y-m-d', strtotime('-2 days')); ?>">Anteontem, <?php echo date('d/m', strtotime('-2 days')); ?></option>
                </select>
            </div>
             <div class="form-group">
                <label for="servings_consumed">Porções consumidas:</label>
                <input type="number" name="servings_consumed" id="servings_consumed" class="form-control" value="1.0" min="0.1" step="0.1" required>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Registrar no Diário</button>
        </form>
    </div>
</div>

<?php
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>