<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Definir o local para português para formatar os nomes dos meses
setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'portuguese');

$user_id = $_SESSION['user_id'];

// --- LÓGICA DE FILTRO DE DATA ---
$filter_month_str = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $filter_month_str) || $filter_month_str === '0000-00') {
    $filter_month_str = date('Y-m');
}
$start_date = $filter_month_str . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// --- BUSCA DE DADOS DO USUÁRIO ---
$stmt_user = $conn->prepare("SELECT u.name, u.points, r.rank FROM sf_users u LEFT JOIN (SELECT id, RANK() OVER (ORDER BY points DESC, name ASC) as rank FROM sf_users) r ON u.id = r.id WHERE u.id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$user_data = $stmt_user->get_result()->fetch_assoc();
$stmt_user->close();
$user_points = $user_data['points'] ?? 0;
$user_rank = $user_data['rank'] ?? 0;


// ============================================================================================
// === NOVO SISTEMA DE NÍVEIS HÍBRIDO (Mecânica Antiga + Nomes Novos) ==========================
// ============================================================================================

function toRoman($number) {
    $map = [10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
    $roman = '';
    while ($number > 0) { foreach ($map as $val => $char) { if ($number >= $val) { $roman .= $char; $number -= $val; break; } } }
    return $roman;
}

// 1. Definição das Categorias principais
$level_categories = [
    ['name' => 'Franguinho', 'threshold' => 0], ['name' => 'Frango', 'threshold' => 1500], ['name' => 'Frango de Elite', 'threshold' => 4000],
    ['name' => 'Atleta de Bronze', 'threshold' => 8000], ['name' => 'Atleta de Prata', 'threshold' => 14000], ['name' => 'Atleta de Ouro', 'threshold' => 22000], ['name' => 'Atleta de Platina', 'threshold' => 32000], ['name' => 'Atleta de Diamante', 'threshold' => 45000],
    ['name' => 'Elite', 'threshold' => 60000], ['name' => 'Mestre', 'threshold' => 80000], ['name' => 'Virtuoso', 'threshold' => 105000],
    ['name' => 'Campeão', 'threshold' => 135000], ['name' => 'Titã', 'threshold' => 170000], ['name' => 'Pioneiro', 'threshold' => 210000], ['name' => 'Lenda', 'threshold' => 255000],
];

// 2. Geração do mapa de níveis detalhado (todos os 150 níveis)
$final_levels_map = [];
$level_counter = 1;
foreach ($level_categories as $index => $category) {
    $next_threshold = isset($level_categories[$index + 1]) ? $level_categories[$index + 1]['threshold'] : ($category['threshold'] + ($category['threshold'] - $level_categories[$index - 1]['threshold']));
    $points_in_category = $next_threshold - $category['threshold'];
    $points_per_sublevel = $points_in_category > 0 ? $points_in_category / 10 : 0;

    for ($i = 0; $i < 10; $i++) {
        $final_levels_map[$level_counter] = [
            'name' => $category['name'] . ' ' . toRoman($i + 1),
            'points_required' => $category['threshold'] + ($i * $points_per_sublevel)
        ];
        $level_counter++;
    }
}

/**
 * Calcula o progresso do usuário usando a lógica nível-a-nível (como no sistema antigo).
 * @return array com nome do nível, progresso e pontos restantes para o PRÓXIMO subnível.
 */
function calculate_user_progress($points, $levels_map) {
    $current_level_num = 1;
    $points_at_current_level_start = 0;
    $points_for_next_level = 0;
    $is_max_level = false;

    // Encontra o nível atual do usuário
    foreach ($levels_map as $level_num => $level_data) {
        if ($points >= $level_data['points_required']) {
            $current_level_num = $level_num;
        } else {
            break;
        }
    }
    
    $points_at_current_level_start = $levels_map[$current_level_num]['points_required'];

    // Verifica se está no nível máximo
    if (!isset($levels_map[$current_level_num + 1])) {
        $is_max_level = true;
        $points_for_next_level = $points_at_current_level_start;
    } else {
        $points_for_next_level = $levels_map[$current_level_num + 1]['points_required'];
    }

    $level_name = $levels_map[$current_level_num]['name'];
    
    // Calcula o progresso e os pontos restantes exatamente como na lógica antiga
    $level_progress_points = $points - $points_at_current_level_start;
    $total_points_for_this_level = $points_for_next_level - $points_at_current_level_start;
    
    if ($is_max_level || $total_points_for_this_level <= 0) {
        $progress_percentage = 100;
        $points_remaining = 0;
    } else {
        $progress_percentage = round(($level_progress_points / $total_points_for_this_level) * 100);
        $points_remaining = $total_points_for_this_level - $level_progress_points;
    }

    return [
        'name' => $level_name,
        'progress_percentage' => $progress_percentage,
        'points_remaining' => $points_remaining,
        'is_max_level' => $is_max_level
    ];
}

// --- CALCULA OS DETALHES DO NÍVEL DO USUÁRIO USANDO A LÓGICA CORRETA ---
$level_details = calculate_user_progress($user_points, $final_levels_map);
$current_level = $level_details['name'];
$level_progress_percentage = $level_details['progress_percentage'];
$points_remaining_for_next_level = $level_details['points_remaining'];

// ===================================================================
// === FIM DO NOVO SISTEMA DE NÍVEIS =================================
// ===================================================================

// (O resto do seu PHP de busca de dados permanece exatamente o mesmo...)
$raw_log = [];
$stmt_log = $conn->prepare("SELECT points_awarded, action_key, action_context_id, timestamp FROM sf_user_points_log WHERE user_id = ? AND date_awarded BETWEEN ? AND ? ORDER BY timestamp DESC");
$stmt_log->bind_param("iss", $user_id, $start_date, $end_date);
$stmt_log->execute();
$result = $stmt_log->get_result();
while($row = $result->fetch_assoc()) { $raw_log[] = $row; }
$stmt_log->close();
$processed_log = []; $water_log_by_day = [];
foreach ($raw_log as $log_item) { if ($log_item['action_key'] === 'WATER_CUP_LOGGED') { $day_key = date('Y-m-d', strtotime($log_item['timestamp'])); if (!isset($water_log_by_day[$day_key])) { $water_log_by_day[$day_key] = ['total_points' => 0, 'cup_count' => 0, 'log_entries' => []]; } $water_log_by_day[$day_key]['total_points'] += $log_item['points_awarded']; $water_log_by_day[$day_key]['cup_count']++; $water_log_by_day[$day_key]['log_entries'][] = ['time' => date('H:i', strtotime($log_item['timestamp'])), 'points' => $log_item['points_awarded']]; } else { $processed_log[] = $log_item; } }
foreach ($water_log_by_day as $day => $water_data) { if (empty($water_data['log_entries'])) continue; $first_entry_time = min(array_column($water_data['log_entries'], 'time')); $reference_timestamp = date('Y-m-d H:i:s', strtotime($day . ' ' . $first_entry_time)); $processed_log[] = ['action_key' => 'WATER_LOG_GROUPED', 'points_awarded' => $water_data['total_points'], 'timestamp' => $reference_timestamp, 'details' => $water_data]; }
usort($processed_log, function($a, $b) { return strtotime($b['timestamp']) - strtotime($a['timestamp']); });
$points_log_grouped = [];
foreach ($processed_log as $log_item) { $date = new DateTime($log_item['timestamp'], new DateTimeZone('America/Sao_Paulo')); $today = new DateTime('now', new DateTimeZone('America/Sao_Paulo')); $yesterday = (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->modify('-1 day'); $date_key = ($date->format('Y-m-d') === $today->format('Y-m-d')) ? 'Hoje' : (($date->format('Y-m-d') === $yesterday->format('Y-m-d')) ? 'Ontem' : $date->format('d/m/Y')); $points_log_grouped[$date_key][] = $log_item; }

// --- BUSCAR MESES DISPONÍVEIS ---
$available_months = [];
$stmt_months = $conn->prepare("SELECT DISTINCT DATE_FORMAT(date_awarded, '%Y-%m') as month_key, STR_TO_DATE(CONCAT(DATE_FORMAT(date_awarded, '%Y-%m'), '-01'), '%Y-%m-%d') as month_date FROM sf_user_points_log WHERE user_id = ? AND date_awarded > '0000-00-00' ORDER BY month_key DESC");
$stmt_months->bind_param("i", $user_id);
$stmt_months->execute();
$months_result = $stmt_months->get_result();
while($row = $months_result->fetch_assoc()) {
    $row['month_display'] = date('m/y', strtotime($row['month_date']));
    $available_months[] = $row;
}
$stmt_months->close();

function getActionDetails($key, $details_data = null) { if (strpos($key, 'MEAL_LOGGED_') === 0) { $meal_type_slug = str_replace('MEAL_LOGGED_', '', $key); $meal_type_names = ['breakfast' => 'Café da Manhã', 'morning_snack' => 'Lanche da Manhã', 'lunch' => 'Almoço', 'afternoon_snack' => 'Lanche da Tarde', 'dinner' => 'Jantar', 'supper' => 'Ceia', 'pre_workout' => 'Pré-Treino', 'post_workout' => 'Pós-Treino']; $meal_name = $meal_type_names[$meal_type_slug] ?? 'Refeição'; return ['icon' => 'fa-utensils', 'text' => "Registro de {$meal_name}", 'color' => '#ff6b00']; } if ($key === 'WATER_LOG_GROUPED' && $details_data) { $cup_text = $details_data['cup_count'] > 1 ? 'copos registrados' : 'copo registrado'; return ['icon' => 'fa-tint', 'text' => "{$details_data['cup_count']} {$cup_text}", 'color' => '#34aadc']; } $details = [ 'ROUTINE_COMPLETE' => ['image_url' => 'https://i.ibb.co/tPFzcnw9/POINTSGREEN.webp', 'text' => 'Tarefa de rotina concluída', 'color' => '#4caf50'], 'WATER_GOAL_MET'   => ['icon' => 'fa-award', 'text' => 'Bônus: Meta de hidratação!', 'color' => '#f0ad4e'], 'CALORIE_GOAL_MET' => ['icon' => 'fa-bullseye', 'text' => 'Bônus: Meta de calorias!', 'color' => '#f0ad4e'], 'PROTEIN_GOAL_MET' => ['icon' => 'fa-drumstick-bite', 'text' => 'Bônus: Meta de proteína!', 'color' => '#f0ad4e'], 'CARBS_GOAL_MET'   => ['icon' => 'fa-bread-slice', 'text' => 'Bônus: Meta de carboidratos!', 'color' => '#f0ad4e'], 'FAT_GOAL_MET'     => ['icon' => 'fa-bacon', 'text' => 'Bônus: Meta de gordura!', 'color' => '#f0ad4e'], ]; return $details[$key] ?? ['icon' => 'fa-question-circle', 'text' => 'Ação registrada', 'color' => '#A0A0A0']; }

$page_title = "Minha Jornada";
$extra_css = ['points_history.css'];
$extra_js = ['points_history_logic.js'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<!-- ESTILO FINAL COM FONTE DINÂMICA -->
<style>
.history-filter-bar { background-color: var(--surface-color); border-radius: 12px; padding: 12px 20px; margin: 20px 0 15px 0; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); border: 1px solid var(--border-color); }
.history-filter-bar .form-group { margin-bottom: 0; display: flex; align-items: center; gap: 15px; }
.history-filter-bar .form-group label { margin-bottom: 0; font-size: 0.9em; font-weight: 500; color: var(--secondary-text-color); flex-shrink: 0; }
.history-filter-bar select#month-filter { flex-grow: 1; font-weight: 600; text-align: right; padding-left: 10px; cursor: pointer; }
.history-filter-bar select#month-filter:hover { border-color: var(--accent-orange); }
.hero-points-display .points-value { font-size: 52px; transition: font-size 0.2s ease-in-out; }
.hero-points-display .points-value.medium-font { font-size: 44px; }
.hero-points-display .points-value.small-font { font-size: 36px; }
</style>

<div class="container points-v2-container">
    <div class="header-nav">
        <a href="<?php echo BASE_APP_URL; ?>/main_app.php" class="back-button"><i class="fas fa-arrow-left"></i></a>
        <h1 class="page-title">Minha Jornada</h1>
    </div>

    <div class="hero-card">
        <div class="hero-points-display">
           <?php
                $formatted_points_str = fmod($user_points, 1) == 0 ? number_format($user_points, 0, ',', '.') : number_format($user_points, 1, ',', '.');
                $points_len = strlen($formatted_points_str);
                $font_class = '';
                if ($points_len >= 8) { $font_class = ' small-font'; } elseif ($points_len >= 7) { $font_class = ' medium-font'; }
           ?>
           <span class="points-value<?php echo $font_class; ?>"><?php echo $formatted_points_str; ?></span>
            <span class="points-label">PONTOS</span>
        </div>
        <div class="hero-level-progress">
            <div class="level-info">
                <?php // ATUALIZAÇÃO: A palavra "Nível" foi removida, pois o nome da categoria já é completo. ?>
                <span class="level-tag"><?php echo $current_level; ?></span>
                <a href="<?php echo BASE_APP_URL; ?>/ranking.php" class="rank-button"><i class="fas fa-trophy"></i><div class="rank-info"><span class="rank-value"><?php echo $user_rank; ?>º</span><span class="rank-label">Ranking</span></div></a>
            </div>
            <div class="progress-bar-container"><div class="progress-bar-fill" style="width: <?php echo $level_progress_percentage; ?>%;"></div></div>
            <div class="next-level-text">
                <?php if ($level_details['is_max_level']): ?>
                    <strong>Você está no nível máximo!</strong> 🚀
                <?php else: ?>
                    Faltam <strong><?php echo fmod($points_remaining_for_next_level, 1) == 0 ? number_format($points_remaining_for_next_level, 0, ',', '.') : number_format($points_remaining_for_next_level, 1, ',', '.'); ?></strong> pontos...
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="history-filter-bar">
        <form id="filter-form" action="" method="GET">
            <div class="form-group">
                <label for="month-filter">Histórico de</label>
                <select name="month" id="month-filter" class="form-control" onchange="this.form.submit()">
                    <?php if (empty($available_months)): ?><option>Nenhum histórico</option><?php else: ?>
                        <?php foreach($available_months as $month): ?>
                            <option value="<?php echo htmlspecialchars($month['month_key']); ?>" <?php echo ($filter_month_str == $month['month_key']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($month['month_display']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </form>
    </div>

    <div class="history-feed">
        <?php if (!empty($points_log_grouped)): ?>
            <?php foreach ($points_log_grouped as $date_group => $logs): ?>
                <div class="feed-date-separator"><span><?php echo $date_group; ?></span></div>
                <div class="feed-group">
                    <?php foreach ($logs as $log_item): $details_data = $log_item['details'] ?? null; $details = getActionDetails($log_item['action_key'], $details_data); $is_expandable = ($log_item['action_key'] === 'WATER_LOG_GROUPED'); ?>
                        <div class="feed-item <?php if($is_expandable) echo 'expandable'; ?>" <?php if($is_expandable) echo 'role="button" tabindex="0"'; ?>>
                            <div class="feed-icon" style="background-color: <?php echo htmlspecialchars($details['color']); ?>20;"><?php if (isset($details['image_url'])): ?><div style="background-image: url('<?php echo htmlspecialchars($details['image_url']); ?>'); background-size: 40%; background-position: center; background-repeat: no-repeat; width: 100%; height: 100%;"></div><?php else: ?><i class="fas <?php echo htmlspecialchars($details['icon']); ?>" style="color: <?php echo htmlspecialchars($details['color']); ?>;"></i><?php endif; ?></div>
                            <div class="feed-info"><p class="feed-reason"><?php echo htmlspecialchars($details['text']); ?></p><span class="feed-time"><?php echo date('H:i', strtotime($log_item['timestamp'])); ?></span></div>
                           <span class="feed-points"><?php $points = (float)$log_item['points_awarded']; $formatted_points = fmod($points, 1) == 0 ? number_format($points, 0) : number_format($points, 1, ','); echo ($points > 0 ? '+' : '') . $formatted_points; ?></span>
                            <?php if($is_expandable): ?><i class="fas fa-chevron-down expand-arrow"></i><?php endif; ?>
                        </div>
                        <?php if($is_expandable && isset($details_data['log_entries'])): ?>
                            <div class="expandable-content"><div class="water-log-details"><?php usort($details_data['log_entries'], function($a, $b) { return strtotime($a['time']) - strtotime($b['time']); }); $cup_number = 1; foreach($details_data['log_entries'] as $entry): ?><div class="water-detail-row"><span class="detail-cup-info"><i class="fas fa-tint"></i>Copo <?php echo $cup_number++; ?></span><span class="detail-time"><?php echo htmlspecialchars($entry['time']); ?></span><span class="detail-points"><?php $points_entry = (float)$entry['points']; $formatted_points_entry = fmod($points_entry, 1) == 0 ? number_format($points_entry, 0) : number_format($points_entry, 1, ','); echo ($points_entry > 0 ? '+' : '') . $formatted_points_entry . ' pt'; ?></span></div><?php endforeach; ?></div></div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="feed-empty-state">
                 <i class="fas fa-calendar-times"></i>
                 <h3>Nenhuma Atividade Registrada</h3>
                <?php
                    $display_month_name = "o período selecionado";
                    try { $dateObj = new DateTime($start_date); $display_month_name = ucfirst(strftime('%B de %Y', $dateObj->getTimestamp())); } catch (Exception $e) { /* Usa o padrão seguro */ }
                ?>
                <p>Não encontramos registros de pontos para <?php echo htmlspecialchars($display_month_name); ?>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; 
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>