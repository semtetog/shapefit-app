<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$limit = 50;

// ===================================================================
// === NOVO SISTEMA DE NÍVEIS POR CATEGORIA (v3.0) ===================
// ===================================================================

/**
 * Define as 15 categorias de níveis e o total de pontos necessários para alcançá-las.
 * Cada categoria principal contém 10 subníveis (I a X).
 */
$level_categories = [
    // Tier 1: Iniciante
    ['name' => 'Franguinho',           'threshold' => 0],      // Níveis 1-10
    ['name' => 'Frango',               'threshold' => 1500],   // Níveis 11-20
    ['name' => 'Frango de Elite',      'threshold' => 4000],   // Níveis 21-30
    
    // Tier 2: Atleta (Metais Preciosos)
    ['name' => 'Atleta de Bronze',     'threshold' => 8000],   // Níveis 31-40
    ['name' => 'Atleta de Prata',      'threshold' => 14000],  // Níveis 41-50
    ['name' => 'Atleta de Ouro',       'threshold' => 22000],  // Níveis 51-60
    ['name' => 'Atleta de Platina',    'threshold' => 32000],  // Níveis 61-70
    ['name' => 'Atleta de Diamante',   'threshold' => 45000],  // Níveis 71-80
    
    // Tier 3: Maestria
    ['name' => 'Elite',                'threshold' => 60000],  // Níveis 81-90
    ['name' => 'Mestre',               'threshold' => 80000],  // Níveis 91-100
    ['name' => 'Virtuoso',             'threshold' => 105000], // Níveis 101-110
    
    // Tier 4: Lendário
    ['name' => 'Campeão',              'threshold' => 135000], // Níveis 111-120
    ['name' => 'Titã',                 'threshold' => 170000], // Níveis 121-130
    ['name' => 'Pioneiro',             'threshold' => 210000], // Níveis 131-140
    ['name' => 'Lenda',                'threshold' => 255000], // Níveis 141-150
];

/**
 * Converte um número de 1 a 10 para seu equivalente em algarismo romano.
 */
function toRoman($number) {
    $map = [10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I'];
    $roman = '';
    while ($number > 0) {
        foreach ($map as $val => $char) {
            if ($number >= $val) {
                $roman .= $char;
                $number -= $val;
                break;
            }
        }
    }
    return $roman;
}

/**
 * Calcula o nível categórico completo do usuário com base em seus pontos.
 */
function getUserLevel($points, $categories) {
    $current_category = null;
    $next_category_threshold = 0;

    for ($i = count($categories) - 1; $i >= 0; $i--) {
        if ($points >= $categories[$i]['threshold']) {
            $current_category = $categories[$i];
            if (isset($categories[$i + 1])) {
                $next_category_threshold = $categories[$i + 1]['threshold'];
            } else {
                $next_category_threshold = $current_category['threshold'] * 2;
            }
            break;
        }
    }

    if ($current_category === null) {
        $current_category = $categories[0];
        $next_category_threshold = $categories[1]['threshold'];
    }

    $category_start_points = $current_category['threshold'];
    $points_needed_for_full_category = $next_category_threshold - $category_start_points;
    
    if ($points_needed_for_full_category <= 0) {
        return $current_category['name'] . ' X';
    }

    $points_into_this_category = $points - $category_start_points;
    $points_per_sublevel = $points_needed_for_full_category / 10;
    
    $sub_level = floor($points_into_this_category / $points_per_sublevel) + 1;
    $sub_level = max(1, min(10, $sub_level));

    if ($points >= $next_category_threshold && $next_category_threshold > $category_start_points) {
        $sub_level = 10;
    }

    return $current_category['name'] . ' ' . toRoman($sub_level);
}

// ===================================================================
// === FIM DO NOVO SISTEMA DE NÍVEIS ==================================
// ===================================================================

$rankings = [];
$stmt = $conn->prepare("SELECT u.id, u.name, u.points, up.profile_image_filename, up.gender, RANK() OVER (ORDER BY u.points DESC, u.name ASC) as user_rank FROM sf_users u LEFT JOIN sf_user_profiles up ON u.id = up.user_id ORDER BY user_rank ASC LIMIT ?");
$stmt->bind_param("i", $limit);
$stmt->execute();

$result = $stmt->get_result();
$current_user_in_top_list = false;
while ($row = $result->fetch_assoc()) {
    // ATUALIZAÇÃO: Passa o array de categorias para a função
    $row['level'] = getUserLevel($row['points'], $level_categories);
    if ($row['id'] == $user_id) { $current_user_in_top_list = true; }
    $rankings[] = $row;
}
$stmt->close();

$current_user_data = null;
if (!$current_user_in_top_list && $user_id) {
    $stmt_user = $conn->prepare("SELECT u.id, u.name, u.points, up.profile_image_filename, up.gender, r.user_rank FROM sf_users u LEFT JOIN sf_user_profiles up ON u.id = up.user_id JOIN (SELECT id, RANK() OVER (ORDER BY points DESC, name ASC) as user_rank FROM sf_users) r ON u.id = r.id WHERE u.id = ?");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $current_user_data = $stmt_user->get_result()->fetch_assoc();
    if($current_user_data){
        // ATUALIZAÇÃO: Passa o array de categorias para a função
        $current_user_data['level'] = getUserLevel($current_user_data['points'], $level_categories);
    }
    $stmt_user->close();
}

function getUserProfileImageUrl($player_data) {
    if (!empty($player_data['profile_image_filename'])) { return BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($player_data['profile_image_filename']); }
    $gender = strtolower($player_data['gender'] ?? 'male');
    return ($gender === 'female') ? 'https://i.ibb.co/XkpfDjbj/FEMININO.webp' : 'https://i.ibb.co/gLcMfWyn/MASCULINO.webp';
}

// URL da imagem de placeholder para os slots vazios do pódio.
$placeholder_image_url = 'https://cdn.jsdelivr.net/gh/semtetog/shapefit/sin-foto.webp
';

$page_title = "Ranking";
$extra_css = ['ranking_page.css'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container ranking-page-container">
    <div class="header-nav">
        <a href="<?php echo BASE_APP_URL; ?>/more_options.php" class="back-button"><i class="fas fa-arrow-left"></i></a>
        <h1 class="page-title">Ranking Global</h1>
    </div>

    <div class="podium">
        <?php // Posição 2 (Segundo Lugar) ?>
        <div class="podium-place second">
            <div class="podium-picture-wrapper">
                <img src="<?php echo isset($rankings[1]) ? getUserProfileImageUrl($rankings[1]) : $placeholder_image_url; ?>" alt="2º Lugar" class="podium-picture">
                <div class="podium-rank-badge">2</div>
            </div>
            <span class="podium-name"><?php echo isset($rankings[1]) ? htmlspecialchars(explode(' ', $rankings[1]['name'])[0]) : '-'; ?></span>
            <span class="podium-level"><?php echo isset($rankings[1]) ? $rankings[1]['level'] : '-'; ?></span>
            <span class="podium-points"><?php echo isset($rankings[1]) ? number_format($rankings[1]['points'], 0, ',', '.') . ' pts' : '-'; ?></span>
        </div>
        
        <?php // Posição 1 (Primeiro Lugar) ?>
        <div class="podium-place first">
             <div class="podium-picture-wrapper">
                <img src="<?php echo isset($rankings[0]) ? getUserProfileImageUrl($rankings[0]) : $placeholder_image_url; ?>" alt="1º Lugar" class="podium-picture">
                <div class="podium-rank-badge"><i class="fas fa-crown"></i></div>
            </div>
            <span class="podium-name"><?php echo isset($rankings[0]) ? htmlspecialchars(explode(' ', $rankings[0]['name'])[0]) : '-'; ?></span>
            <span class="podium-level"><?php echo isset($rankings[0]) ? $rankings[0]['level'] : '-'; ?></span>
            <span class="podium-points"><?php echo isset($rankings[0]) ? number_format($rankings[0]['points'], 0, ',', '.') . ' pts' : '-'; ?></span>
        </div>
        
        <?php // Posição 3 (Terceiro Lugar) ?>
        <div class="podium-place third">
             <div class="podium-picture-wrapper">
                <img src="<?php echo isset($rankings[2]) ? getUserProfileImageUrl($rankings[2]) : $placeholder_image_url; ?>" alt="3º Lugar" class="podium-picture">
                <div class="podium-rank-badge">3</div>
            </div>
            <span class="podium-name"><?php echo isset($rankings[2]) ? htmlspecialchars(explode(' ', $rankings[2]['name'])[0]) : '-'; ?></span>
            <span class="podium-level"><?php echo isset($rankings[2]) ? $rankings[2]['level'] : '-'; ?></span>
            <span class="podium-points"><?php echo isset($rankings[2]) ? number_format($rankings[2]['points'], 0, ',', '.') . ' pts' : '-'; ?></span>
        </div>
    </div>

    <div class="ranking-list-section">
        <ul class="ranking-list">
            <?php foreach (array_slice($rankings, 3) as $player): 
                $is_current_user = ($player['id'] == $user_id);
            ?>
                <li class="ranking-item <?php if($is_current_user) echo 'current-user'; ?>">
                    <span class="item-rank"><?php echo $player['user_rank']; ?>º</span>
                    <img src="<?php echo getUserProfileImageUrl($player); ?>" alt="..." class="item-picture">
                    <div class="item-info-container">
                        <div class="item-name-row">
                            <span class="item-name"><?php echo htmlspecialchars(explode(' ', $player['name'])[0]); ?></span>
                        </div>
                        <div class="item-level-container">
                            <span class="item-level"><?php echo $player['level']; ?></span>
                        </div>
                    </div>
                    <span class="item-points"><?php echo number_format($player['points'], 0, ',', '.'); ?> pts</span>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <?php if (empty($rankings)): ?>
             <div style="text-align: center; padding: 40px; color: #999;">
                <i class="fas fa-trophy fa-2x" style="margin-bottom: 15px;"></i>
                <p>Seja o primeiro a entrar no Ranking!</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($current_user_data): ?>
    <div class="current-user-sticky-card">
        <div class="ranking-item current-user">
            <span class="item-rank"><?php echo $current_user_data['user_rank']; ?>º</span>
            <img src="<?php echo getUserProfileImageUrl($current_user_data); ?>" alt="Sua foto" class="item-picture">
            <div class="item-info-container">
                <div class="item-name-row">
                    <span class="item-name">Você</span>
                </div>
                <div class="item-level-container">
                    <span class="item-level"><?php echo $current_user_data['level']; ?></span>
                </div>
            </div>
            <span class="item-points"><?php echo number_format($current_user_data['points'], 0, ',', '.'); ?> pts</span>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; 
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>