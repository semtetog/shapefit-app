<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$seven_days_ago = date('Y-m-d', strtotime('-6 days'));

$weekly_consumption = [];
$stmt_consumption = $conn->prepare("SELECT date, carbs_consumed_g, protein_consumed_g, fat_consumed_g FROM sf_user_daily_tracking WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date ASC");
$stmt_consumption->bind_param("iss", $user_id, $seven_days_ago, $today);
$stmt_consumption->execute();
$result_consumption = $stmt_consumption->get_result();
while ($row = $result_consumption->fetch_assoc()) {
    $weekly_consumption[$row['date']] = $row;
}
$stmt_consumption->close();

$chart_data = ['labels' => [], 'carbs' => [], 'protein' => [], 'fat' => []];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("$seven_days_ago +$i days"));
    $day_initial = mb_substr(ucfirst(strftime('%a', strtotime($date))), 0, 1);
    
    $chart_data['labels'][] = $day_initial;
    $chart_data['carbs'][] = (float)($weekly_consumption[$date]['carbs_consumed_g'] ?? 0);
    $chart_data['protein'][] = (float)($weekly_consumption[$date]['protein_consumed_g'] ?? 0);
    $chart_data['fat'][] = (float)($weekly_consumption[$date]['fat_consumed_g'] ?? 0);
}

$page_title = "Meu Progresso";
$extra_css = ['progress_page.css'];
$extra_js = ['progress_logic.js'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container progress-page-container">
    <div class="header-nav">
        <a href="<?php echo BASE_APP_URL; ?>/main_app.php" class="back-button"><i class="fas fa-chevron-left"></i></a>
        <h1 class="page-title">Progresso</h1>
    </div>

    <!-- CARD DE CONSUMO SEMANAL -->
    <div class="progress-card card-shadow">
        <div class="card-header">
            <h3>Seu consumo</h3>
            <span>Últimos 7 dias</span>
        </div>
        <div class="chart-container">
            <canvas id="consumptionChart"></canvas>
        </div>
        <div class="chart-legend">
            <div class="legend-item"><span class="legend-color" style="background-color: #34aadc;"></span>Proteína</div>
            <div class="legend-item"><span class="legend-color" style="background-color: #f0ad4e;"></span>Gordura</div>
            <div class="legend-item"><span class="legend-color" style="background-color: #A0A0A0;"></span>Carboidrato</div>
        </div>
    </div>
    
   
    </a>
    <a href="<?php echo BASE_APP_URL; ?>/measurements_progress.php" class="progress-section-link card-shadow">
        <span>Suas fotos e medidas</span>
        <i class="fas fa-chevron-right"></i>
    </a>

</div>

<script id="chart-data" type="application/json"><?php echo json_encode($chart_data); ?></script>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>