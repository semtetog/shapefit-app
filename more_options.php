<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
// A função getUserProfileData já busca todos os dados necessários
$user_profile_data = getUserProfileData($conn, $user_id);

if (!$user_profile_data) {
    // Redireciona ou mostra um erro se não encontrar o perfil
    // (Isso é uma boa prática para evitar erros mais abaixo)
    header("Location: " . BASE_APP_URL . "/main_app.php?error=profile_not_found");
    exit();
}

// --- LÓGICA CORRETA PARA PEGAR O PRIMEIRO NOME ---
$first_name = htmlspecialchars(explode(' ', $user_profile_data['name'])[0]);

// =========================================================================
//      LÓGICA CORRETA PARA A FOTO DE PERFIL (A QUE VOCÊ JÁ TINHA)
// =========================================================================
$profile_image_url = '';
// A coluna `profile_image_filename` agora vem de $user_profile_data
$custom_photo_filename = $user_profile_data['profile_image_filename'] ?? null;

if ($custom_photo_filename) {
    $profile_image_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($custom_photo_filename);
} else {
    // Usa o gênero, que também vem de $user_profile_data
    $gender = strtolower($user_profile_data['gender'] ?? 'male');
    $profile_image_url = ($gender === 'female') ? 'https://i.ibb.co/XkpfDjbj/FEMININO.webp' : 'https://i.ibb.co/gLcMfWyn/MASCULINO.webp';
}
// =========================================================================


$page_title = "Mais";
$extra_css = ['more_options.css', 'main_app_specific.css']; // Adicionei o main_app_specific para consistência
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container more-options-container">
    <div class="header-nav" style="margin-bottom: 20px;">
        <h1 class="page-title">Mais</h1>
    </div>

    <!-- CARD DE PERFIL -->
    <a href="<?php echo BASE_APP_URL; ?>/profile_overview.php" class="profile-card-link">
        <div class="profile-card">
            <img src="<?php echo $profile_image_url; ?>" alt="Foto de Perfil" class="profile-picture">
            <div class="profile-info">
                <span class="profile-name"><?php echo $first_name; ?></span>
                <span class="profile-action">Ver e editar perfil</span>
            </div>
            <i class="fas fa-chevron-right arrow-icon"></i>
        </div>
    </a>

    <!-- Grade e Lista de Opções Simplificada -->
    <div class="options-grid">
        <a href="<?php echo BASE_APP_URL; ?>/dashboard.php" class="option-card"><i class="fas fa-bullseye option-icon" style="color: #ff6b00;"></i><span class="option-label">Minha Meta</span></a>
        <a href="<?php echo BASE_APP_URL; ?>/progress.php" class="option-card"><i class="fas fa-chart-line option-icon" style="color: #34aadc;"></i><span class="option-label">Progresso</span></a>
        <a href="<?php echo BASE_APP_URL; ?>/routine.php" class="option-card"><i class="fas fa-check-double option-icon" style="color: #5cb85c;"></i><span class="option-label">Rotina</span></a>
        <a href="<?php echo BASE_APP_URL; ?>/ranking.php" class="option-card"><i class="fas fa-trophy option-icon" style="color: #f0ad4e;"></i><span class="option-label">Ranking</span></a>
    </div>

    <div class="options-list-container">
        <ul class="options-list">
            <!-- NOVO LINK PARA FAVORITOS -->
            <li>
                <a href="<?php echo BASE_APP_URL; ?>/favorite_recipes.php" class="option-item">
                    <i class="fas fa-heart list-icon" style="color: #d9534f;"></i>
                    <span>Meus Favoritos</span>
                    <i class="fas fa-chevron-right arrow-icon-list"></i>
                </a>
            </li>
        </ul>
        <ul class="options-list" style="margin-top: 20px;">
             <li><a href="<?php echo BASE_APP_URL; ?>/auth/logout.php" class="option-item logout-link"><i class="fas fa-sign-out-alt list-icon"></i><span>Sair</span></a></li>
        </ul>
    </div>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>