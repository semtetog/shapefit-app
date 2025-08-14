<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$user_data = getUserProfileData($conn, $user_id);
if (!$user_data) { die("Erro ao carregar dados."); }


// ===================================================================
// === INÍCIO: CÓDIGO PARA CÁLCULO DE NÍVEL ==========================
// ===================================================================

// 1. Obter os pontos do usuário
$user_points = $user_data['points'] ?? 0; // Assumindo que getUserProfileData já retorna os pontos. 
                                         // Se não, teríamos que fazer uma query rápida aqui.

// 2. Definir o sistema de níveis
$level_categories = [
    ['name' => 'Franguinho', 'threshold' => 0], ['name' => 'Frango', 'threshold' => 1500], ['name' => 'Frango de Elite', 'threshold' => 4000],
    ['name' => 'Atleta de Bronze', 'threshold' => 8000], ['name' => 'Atleta de Prata', 'threshold' => 14000], ['name' => 'Atleta de Ouro', 'threshold' => 22000], ['name' => 'Atleta de Platina', 'threshold' => 32000], ['name' => 'Atleta de Diamante', 'threshold' => 45000],
    ['name' => 'Elite', 'threshold' => 60000], ['name' => 'Mestre', 'threshold' => 80000], ['name' => 'Virtuoso', 'threshold' => 105000],
    ['name' => 'Campeão', 'threshold' => 135000], ['name' => 'Titã', 'threshold' => 170000], ['name' => 'Pioneiro', 'threshold' => 210000], ['name' => 'Lenda', 'threshold' => 255000],
];

function toRoman($number) {
    $map = [10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
    $roman = '';
    while ($number > 0) { foreach ($map as $val => $char) { if ($number >= $val) { $roman .= $char; $number -= $val; break; } } }
    return $roman;
}

// 3. Função para calcular o nome do nível (versão simples, sem progresso)
function getUserLevelName($points, $categories) {
    $current_category = null;
    for ($i = count($categories) - 1; $i >= 0; $i--) {
        if ($points >= $categories[$i]['threshold']) {
            $current_category = $categories[$i];
            $next_threshold = isset($categories[$i + 1]) ? $categories[$i + 1]['threshold'] : ($current_category['threshold'] * 2);
            $points_in_category = $next_threshold - $current_category['threshold'];
            $points_per_sublevel = $points_in_category > 0 ? $points_in_category / 10 : 0;
            $points_into_this_category = $points - $current_category['threshold'];
            $sub_level = floor($points_into_this_category / $points_per_sublevel) + 1;
            $sub_level = max(1, min(10, $sub_level));
            if ($points >= $next_threshold && $next_threshold > $current_category['threshold']) { $sub_level = 10; }
            return $current_category['name'] . ' ' . toRoman($sub_level);
        }
    }
    return $categories[0]['name'] . ' I'; // Padrão caso algo dê errado
}

// 4. Calcular o nível do usuário atual
$current_user_level_name = getUserLevelName($user_points, $level_categories);

// ===================================================================
// === FIM: CÓDIGO PARA CÁLCULO DE NÍVEL ============================
// ===================================================================


$imc = calculateIMC((float)$user_data['weight_kg'], (int)$user_data['height_cm']);
$imc_category = getIMCCategory($imc);

$page_title = "Meu Perfil";
$extra_css = ['profile_overview.css'];
$extra_js = ['raphael.min.js', 'justgage.min.js', 'profile_overview_logic.js'];

require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container profile-overview-container">
    <div class="header-nav">
        <a href="<?php echo BASE_APP_URL; ?>/more_options.php" class="back-button"><i class="fas fa-chevron-left"></i></a>
        <h1 class="page-title">Perfil</h1>
    </div>

    <div class="profile-summary-grid">
        <div class="summary-card imc-card">
            <div class="card-label">IMC</div>
            <div id="imc-gauge" class="gauge-container" data-value="<?php echo htmlspecialchars($imc); ?>"></div>
            <div class="card-sub-label"><?php echo htmlspecialchars($imc_category); ?></div>
        </div>
        <div class="summary-card weight-card">
            <div class="card-label">Peso Atual</div>
            <div class="main-value-wrapper">
                <div class="main-value"><?php echo number_format((float)$user_data['weight_kg'], 1); ?></div>
                <div class="card-unit">kg</div>
            </div>
             <a href="<?php echo BASE_APP_URL; ?>/progress.php" class="view-history-link">Ver histórico</a>
        </div>
    </div>
    
    <div class="user-info-list">
        <div class="info-group-title">Minhas Informações</div>
        <div class="info-item"><span class="info-label">Nome</span><span class="info-value"><?php echo htmlspecialchars($user_data['name']); ?></span></div>
        
        <?php // --- LINHA ATUALIZADA --- ?>
        <div class="info-item">
            <span class="info-label">Nível</span>
            <span class="info-value"><?php echo htmlspecialchars($current_user_level_name); ?></span>
        </div>
        
        <div class="info-item"><span class="info-label">Objetivo</span><span class="info-value"><?php
                $objectives = ['lose_fat' => 'Perder Gordura', 'maintain_weight' => 'Manter Peso', 'gain_muscle' => 'Ganhar Massa'];
                echo htmlspecialchars($objectives[$user_data['objective']] ?? 'Não definido');
            ?></span></div>
         <a href="<?php echo BASE_APP_URL; ?>/edit_profile.php" class="info-item-link">
             <span class="info-label">Editar perfil e metas</span><i class="fas fa-chevron-right"></i>
        </a>
    </div>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>