<?php
// public_html/shapefit/explore_recipes.php

require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

// --- LÓGICA PRINCIPAL DA PÁGINA ---

$categories_with_recipes = [];

// 1. Busca todas as categorias existentes no banco de dados.
// CORREÇÃO: Removido o "WHERE is_active = TRUE" que causava o erro.
$result_categories = $conn->query("SELECT id, name, slug FROM sf_recipe_categories ORDER BY name ASC");

if ($result_categories && $result_categories->num_rows > 0) {
    
    // 2. Para cada categoria, busca algumas receitas de exemplo.
    while ($category = $result_categories->fetch_assoc()) {
        
        $recipes_in_category = [];
        $stmt = $conn->prepare("
            SELECT r.id, r.name, r.image_filename, r.total_kcal_per_serving 
            FROM sf_recipes r
            JOIN sf_recipe_has_categories rhc ON r.id = rhc.recipe_id
            WHERE rhc.category_id = ? AND r.is_public = TRUE
            ORDER BY RAND() 
            LIMIT 5
        ");

        if ($stmt) {
            $stmt->bind_param("i", $category['id']);
            $stmt->execute();
            $result_recipes = $stmt->get_result();
            
            while ($recipe = $result_recipes->fetch_assoc()) {
                $recipes_in_category[] = $recipe;
            }
            $stmt->close();
        }

        // 3. Adiciona a categoria à lista final apenas se ela tiver receitas associadas.
        if (!empty($recipes_in_category)) {
            $category['recipes'] = $recipes_in_category;
            $categories_with_recipes[] = $category;
        }
    }
}

// --- PREPARAÇÃO PARA O LAYOUT ---
$page_title = "Explorar Receitas";
$extra_css = ['main_app_specific.css'];
$extra_js = ['carousel_logic.js'];
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container main-app-container">
    <div class="header-nav" style="justify-content: center; margin-bottom: 15px;">
        <h1 class="page-title text-center">Explorar Receitas</h1>
    </div>
    <p class="page-subtitle text-center" style="margin-bottom: 30px;">Navegue por nossas coleções de receitas.</p>

    <?php if (!empty($categories_with_recipes)): ?>
        <?php foreach ($categories_with_recipes as $category): ?>
            <section class="meal-suggestions-section">
                <div class="section-header">
                    <h2 class="section-title"><?php echo htmlspecialchars($category['name']); ?></h2>
                    <a href="<?php echo BASE_APP_URL; ?>/recipe_list.php?category_slug=<?php echo htmlspecialchars($category['slug']); ?>" class="view-all-link animated-underline">
                        Ver mais
                    </a>
                </div>
                <div class="suggestions-carousel">
                    <?php foreach($category['recipes'] as $recipe): ?>
                        <div class="suggestion-item card-shadow-light">
                            <a href="<?php echo BASE_APP_URL; ?>/view_recipe.php?id=<?php echo $recipe['id']; ?>" class="recipe-suggestion-link">
                                <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ? $recipe['image_filename'] : 'placeholder_food.jpg'); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>">
                                <div class="recipe-info">
                                    <h3><?php echo htmlspecialchars($recipe['name']); ?></h3>
                                    <span><?php echo round($recipe['total_kcal_per_serving']); ?> kcal</span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-center card-shadow-light" style="padding:20px;">Nenhuma categoria de receita foi encontrada.</p>
    <?php endif; ?>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php'; 
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>