<?php
// public_html/shapefit/includes/layout_bottom_nav.php

// Determinar qual item está ativo baseado na página atual
$current_page_script = basename($_SERVER['PHP_SELF']);

// Lista de páginas que ativam o ícone "Mais"
$more_pages = [
    'more_options.php',
    'profile_overview.php', // Mantido por segurança
    'progress.php',
    'routine.php',
    'ranking.php' // Já pensando no futuro
];
?>
<nav class="bottom-nav">
    <a href="<?php echo BASE_APP_URL; ?>/main_app.php" class="nav-item <?php if($current_page_script === 'main_app.php') echo 'active'; ?>" aria-label="Início">
        <i class="fas fa-home"></i><span>Início</span>
    </a>
    <a href="<?php echo BASE_APP_URL; ?>/meal_types_overview.php" class="nav-item <?php if($current_page_script === 'meal_types_overview.php' || $current_page_script === 'recipe_list.php') echo 'active'; ?>" aria-label="Refeições">
        <i class="fas fa-utensils"></i><span>Refeições</span>
    </a>
    <a href="<?php echo BASE_APP_URL; ?>/diary.php" class="nav-item <?php if($current_page_script === 'diary.php') echo 'active'; ?>" aria-label="Diário">
        <i class="fas fa-book-open"></i><span>Diário</span>
    </a>
    <a href="<?php echo BASE_APP_URL; ?>/explore_recipes.php" class="nav-item <?php if($current_page_script === 'explore_recipes.php' || $current_page_script === 'view_recipe.php') echo 'active'; ?>" aria-label="Receitas">
        <i class="fas fa-apple-alt"></i><span>Receitas</span>
    </a>
    <a href="<?php echo BASE_APP_URL; ?>/more_options.php" class="nav-item <?php if(in_array($current_page_script, $more_pages)) echo 'active'; ?>" aria-label="Mais">
         <i class="fas fa-ellipsis-h"></i><span>Mais</span>
    </a>
</nav>