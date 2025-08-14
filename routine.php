<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$current_date = date('Y-m-d');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token_for_html = $_SESSION['csrf_token'];

// 1. Busca todos os itens do dia
$all_routine_items = getRoutineItemsForUser($conn, $user_id, $current_date);

// 2. Separa os itens em 'a fazer' e 'concluídos'
$routine_todos = [];
$routine_completed = [];
foreach ($all_routine_items as $item) {
    // Usando a comparação correta que já arrumamos
    if ($item['completion_status'] == 1) {
        $routine_completed[] = $item;
    } else {
        $routine_todos[] = $item;
    }
}

// 3. Calcula o progresso baseado nas listas separadas
$total_items = count($all_routine_items);
$completed_count = count($routine_completed);
$progress_percentage = ($total_items > 0) ? round(($completed_count / $total_items) * 100) : 0;

// 4. Prepara para o layout
$page_title = "Rotina Diária";
// Usaremos o mesmo CSS do main_app e um específico para a rotina se necessário
$extra_css = ['main_app_specific.css', 'routine_page.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<!-- Usamos a classe 'main-app-interface' para herdar os estilos bonitos -->
<div class="main-app-interface">
    <div class="container routine-page-container">
        <div class="header-nav">
            <a href="<?php echo BASE_APP_URL; ?>/main_app.php" class="back-button"><i class="fas fa-arrow-left"></i></a>
            <h1 class="page-title">Sua Rotina</h1>
        </div>

        <!-- Card de Progresso redesenhado -->
        <div class="routine-progress-card card-shadow">
            <h3>Progresso de Hoje</h3>
            <div class="progress-info">
                <span class="progress-text"><?php echo $completed_count; ?>/<?php echo $total_items; ?> concluídas</span>
                <span class="progress-percentage"><?php echo $progress_percentage; ?>%</span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?php echo $progress_percentage; ?>%;"></div>
            </div>
        </div>

        <!-- Seção "A Fazer" -->
        <div class="routine-list-section">
            <h2 class="section-title">A Fazer</h2>
            <?php if (!empty($routine_todos)): ?>
                <ul class="routine-list" id="routine-list-todo">
                    <?php foreach($routine_todos as $item): ?>
                        <li class="routine-list-item card-shadow-light" data-routine-id="<?php echo $item['id']; ?>">
                            <p><?php echo htmlspecialchars($item['title']); ?></p>
                            <div class="routine-actions">
                                 <button class="routine-btn-action no" data-action="0" aria-label="Não cumpriu"><i class="fas fa-times"></i></button>
                                 <button class="routine-btn-action yes" data-action="1" aria-label="Cumpriu"><i class="fas fa-check"></i></button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
    <!-- Card de celebração quando TUDO foi concluído -->
    <div class="all-done-card">
        <i class="fas fa-trophy"></i> <!-- Usando um ícone diferente aqui para variar -->
        <p>Parabéns! Você completou todas as suas tarefas de hoje.</p>
    </div>
<?php endif; ?>
        </div>

        <!-- Seção "Concluídas" (só aparece se houver itens) -->
        <?php if (!empty($routine_completed)): ?>
        <div class="routine-list-section completed-section">
            <h2 class="section-title">Concluídas</h2>
            <ul class="routine-list" id="routine-list-completed">
                <?php foreach($routine_completed as $item): ?>
                    <li class="routine-list-item card-shadow-light is-completed" data-routine-id="<?php echo $item['id']; ?>">
                        <p><?php echo htmlspecialchars($item['title']); ?></p>
                        <div class="completion-checkmark">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </div>
</div>

<input type="hidden" id="csrf_token_main_app" value="<?php echo htmlspecialchars($csrf_token_for_html); ?>">

<?php
$extra_js = ['script.js'];
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>