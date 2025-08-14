<?php
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();

$page_title = "Refeições";
$extra_css = ['meal_types_overview_specific.css'];
// Adiciona o CSS da página principal para garantir a consistência do layout
$extra_css[] = 'main_app_specific.css'; 

require_once APP_ROOT_PATH . '/includes/layout_header.php';

// Array com os tipos de refeição
$meal_types = [
    ['name' => 'Café da Manhã', 'slug' => 'cafe_da_manha', 'image' => 'breakfast.png'],
    ['name' => 'Almoço', 'slug' => 'almoco', 'image' => 'lunch.png'],
    ['name' => 'Lanche', 'slug' => 'lanche', 'image' => 'snack.png'],
    ['name' => 'Jantar', 'slug' => 'jantar', 'image' => 'dinner.png'],
];
?>

<div class="container meal-types-overview-container">
    
    <!-- =================================================================== -->
    <!--      CABEÇALHO CORRIGIDO COM BOTÃO DE VOLTAR      -->
    <!-- =================================================================== -->
    <div class="header-nav">
        <a href="<?php echo BASE_APP_URL; ?>/main_app.php" class="back-button" aria-label="Voltar">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h1 class="page-title text-center" style="margin-left: 10px; flex-grow: 1;"><?php echo $page_title; ?></h1>
    </div>
    <!-- =================================================================== -->

    <p class="page-subtitle text-center" style="margin-bottom: 30px;">Veja nossas opções de cardápio e programe suas próximas refeições.</p>

    <div class="meal-type-grid">
        <?php foreach ($meal_types as $meal): ?>
            <?php
                $link_param = isset($meal['category_slug']) ? 'category_slug=' . $meal['category_slug'] : 'meal_type=' . $meal['slug'];
            ?>
            <a href="<?php echo BASE_APP_URL; ?>/recipe_list.php?<?php echo $link_param; ?>" class="meal-type-card-v2">
                <div class="meal-type-card-image-wrapper">
                    <img src="<?php echo BASE_ASSET_URL; ?>/assets/images/meal_types/<?php echo htmlspecialchars($meal['image']); ?>" alt="<?php echo htmlspecialchars($meal['name']); ?>">
                </div>
                <div class="meal-type-card-title-wrapper">
                    <h3><?php echo htmlspecialchars($meal['name']); ?></h3>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<?php
// ===================================================================
//      ADICIONANDO O MENU DE NAVEGAÇÃO INFERIOR
// ===================================================================
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; 
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
// ===================================================================
?>