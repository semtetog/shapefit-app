<?php
// public_html/shapefit/diary.php - VERSÃO FINAL E CORRIGIDA

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$user_id = $_SESSION['user_id'];
$selected_date_str = $_GET['date'] ?? date('Y-m-d');
$date_obj = DateTime::createFromFormat('Y-m-d', $selected_date_str);
if (!$date_obj) {
    $selected_date_str = date('Y-m-d');
    $date_obj = new DateTime();
}

$today_str = date('Y-m-d');
$selected_date_display = ($selected_date_str == $today_str) ? "Hoje" : (($selected_date_str == date('Y-m-d', strtotime('-1 day'))) ? "Ontem" : $date_obj->format('d/m/Y'));

// --- Busca de Dados ---
$daily_tracking = getDailyTrackingRecord($conn, $user_id, $selected_date_str);
$kcal_consumed = $daily_tracking['kcal_consumed'] ?? 0;
$carbs_consumed = $daily_tracking['carbs_consumed_g'] ?? 0;
$protein_consumed = $daily_tracking['protein_consumed_g'] ?? 0;
$fat_consumed = $daily_tracking['fat_consumed_g'] ?? 0;

$stmt_profile = $conn->prepare("SELECT weight_kg, dob, gender, height_cm, objective, activity_level FROM sf_user_profiles WHERE user_id = ?");
$stmt_profile->bind_param("i", $user_id);
$stmt_profile->execute();
$user_profile_data = $stmt_profile->get_result()->fetch_assoc();
$stmt_profile->close();

$total_calories_goal = 2000;
$macros_goal = ['protein_g' => 150, 'carbs_g' => 200, 'fat_g' => 60];
if ($user_profile_data) {
    $age = calculateAge($user_profile_data['dob']);
    $total_calories_goal = calculateTargetDailyCalories($user_profile_data['gender'], (float)$user_profile_data['weight_kg'], (int)$user_profile_data['height_cm'], $age, $user_profile_data['activity_level'], $user_profile_data['objective']);
    $macros_goal = calculateMacronutrients($total_calories_goal, $user_profile_data['objective']);
}

// LÓGICA DE BUSCA CORRETA COM LEFT JOIN
$logged_meals = [];
$stmt_meals = $conn->prepare("
    SELECT 
        log.id, 
        log.meal_type, 
        log.custom_meal_name, 
        log.kcal_consumed,
        r.name AS recipe_name
    FROM 
        sf_user_meal_log AS log
    LEFT JOIN 
        sf_recipes AS r ON log.recipe_id = r.id
    WHERE 
        log.user_id = ? AND log.date_consumed = ? 
    ORDER BY 
        log.logged_at ASC
");

$stmt_meals->bind_param("is", $user_id, $selected_date_str);
$stmt_meals->execute();
$result_meals = $stmt_meals->get_result();
while ($row = $result_meals->fetch_assoc()) {
    $logged_meals[$row['meal_type']][] = $row;
}
$stmt_meals->close();

$meal_type_names = ['breakfast' => 'Café da Manhã', 'morning_snack' => 'Lanche da Manhã', 'lunch' => 'Almoço', 'afternoon_snack' => 'Lanche da Tarde', 'dinner' => 'Jantar', 'supper' => 'Ceia', 'pre_workout' => 'Pré-Treino', 'post_workout' => 'Pós-Treino'];

$page_title = "Diário";
$extra_css = ['main_app_specific.css', 'diary.css']; 
$extra_js = ['diary_logic.js'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container diary-container">
    <div class="diary-header">
        <h1 class="page-title">Diário</h1>
        <div class="date-selector">
            <a href="?date=<?php echo date('Y-m-d', strtotime($selected_date_str . ' -1 day')); ?>" class="date-nav-arrow" aria-label="Dia anterior"><i class="fas fa-chevron-left"></i></a>
            <span id="current-diary-date" data-date="<?php echo $selected_date_str; ?>"><?php echo htmlspecialchars($selected_date_display); ?></span>
            <a href="?date=<?php echo date('Y-m-d', strtotime($selected_date_str . ' +1 day')); ?>" class="date-nav-arrow <?php if ($selected_date_str == $today_str) echo 'disabled'; ?>" aria-label="Próximo dia"><i class="fas fa-chevron-right"></i></a>
        </div>
    </div>

    <div class="diary-summary-card card-shadow">
        <div class="summary-row">
            <span></span>
            <span class="summary-header">Objetivo</span>
            <span class="summary-header">Consumo</span>
            <span class="summary-header">Restante</span>
        </div>
        <div class="summary-row">
            <span class="metric-name">Calorias</span>
            <span><?php echo round($total_calories_goal); ?></span>
            <span><?php echo round($kcal_consumed); ?></span>
            <span class="<?php echo ($total_calories_goal - $kcal_consumed < 0) ? 'negative' : 'positive'; ?>"><?php echo round($total_calories_goal - $kcal_consumed); ?></span>
        </div>
        <div class="summary-row">
            <span class="metric-name">Carboidratos</span>
            <span><?php echo round($macros_goal['carbs_g']); ?>g</span>
            <span><?php echo round($carbs_consumed); ?>g</span>
            <span class="<?php echo ($macros_goal['carbs_g'] - $carbs_consumed < 0) ? 'negative' : 'positive'; ?>"><?php echo round($macros_goal['carbs_g'] - $carbs_consumed); ?>g</span>
        </div>
        <div class="summary-row">
            <span class="metric-name">Proteínas</span>
            <span><?php echo round($macros_goal['protein_g']); ?>g</span>
            <span><?php echo round($protein_consumed); ?>g</span>
            <span class="<?php echo ($macros_goal['protein_g'] - $protein_consumed < 0) ? 'negative' : 'positive'; ?>"><?php echo round($macros_goal['protein_g'] - $protein_consumed); ?>g</span>
        </div>
        <div class="summary-row">
            <span class="metric-name">Gorduras</span>
            <span><?php echo round($macros_goal['fat_g']); ?>g</span>
            <span><?php echo round($fat_consumed); ?>g</span>
            <span class="<?php echo ($macros_goal['fat_g'] - $fat_consumed < 0) ? 'negative' : 'positive'; ?>"><?php echo round($macros_goal['fat_g'] - $fat_consumed); ?>g</span>
        </div>
    </div>

    <a href="<?php echo BASE_APP_URL . '/add_food_to_diary.php?date=' . $selected_date_str; ?>" class="btn btn-primary btn-add-meal-diary">
        <i class="fas fa-plus"></i> Adicionar Refeição
    </a>

    <div class="logged-meals-container">
        <?php foreach ($meal_type_names as $type_key => $type_name): ?>
            <?php // CONDIÇÃO CORRETA: SÓ EXIBE O CARD SE TIVER ITENS PARA AQUELE TIPO DE REFEIÇÃO
            if (!empty($logged_meals[$type_key])):
                $meal_group_kcal = array_sum(array_column($logged_meals[$type_key], 'kcal_consumed'));
            ?>
                <div class="meal-group card-shadow">
                    <div class="meal-group-header">
                        <h3 class="meal-group-title"><?php echo htmlspecialchars($type_name); ?></h3>
                        <span class="meal-group-total-kcal"><?php echo round($meal_group_kcal); ?> kcal</span>
                    </div>
                    <ul class="meal-items-list">
                        <?php foreach ($logged_meals[$type_key] as $meal_item): ?>
                            <?php
                                // Lógica para pegar o nome correto
                                $display_name = !empty($meal_item['recipe_name']) ? $meal_item['recipe_name'] : $meal_item['custom_meal_name'];
                            ?>
                            <li>
                                <span class="meal-item-name"><?php echo htmlspecialchars($display_name); ?></span>
                                <span class="meal-item-kcal"><?php echo round($meal_item['kcal_consumed']); ?> kcal</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="<?php echo BASE_APP_URL . '/add_food_to_diary.php?date=' . $selected_date_str . '&meal_type=' . $type_key; ?>" class="add-item-to-group-btn">Adicionar Alimento</a>
                </div>
            <?php endif; // Fim da condição ?>
        <?php endforeach; ?>

        <?php if (empty($logged_meals)): ?>
            <p class="empty-diary-message card-shadow">Nada registrado para este dia. Comece adicionando uma refeição!</p>
        <?php endif; ?>
    </div>
</div>

<!-- Input escondido para o diary_logic.js funcionar corretamente -->
<input type="hidden" id="base_app_url_for_js" value="<?php echo BASE_APP_URL; ?>">

<?php
if (isset($_SESSION['points_earned_popup']) && $_SESSION['points_earned_popup'] > 0) {
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            showSinglePopup({$_SESSION['points_earned_popup']}, 'bonus');
        });
    </script>";
    unset($_SESSION['points_earned_popup']);
}
?>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; 
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>