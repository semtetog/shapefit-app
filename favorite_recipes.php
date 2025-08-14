<?php
// public_html/shapefit/favorite_recipes.php
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$user_id = $_SESSION['user_id'];
$page_heading = "Minhas Receitas Favoritas";

$recipes = [];

// Query SQL para buscar apenas as receitas favoritadas pelo usuário
$sql = "
    SELECT r.id, r.name, r.image_filename, r.total_kcal_per_serving
    FROM sf_recipes r
    JOIN sf_user_favorite_recipes f ON r.id = f.recipe_id
    WHERE f.user_id = ?
    ORDER BY r.name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    $recipes[] = $row;
}
$stmt->close();

$page_title = $page_heading;
$extra_css = ['recipe_list_specific.css']; // Reutiliza o mesmo CSS da lista de receitas
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container recipe-list-container">
    <div class="header-nav">
        <a href="<?php echo BASE_APP_URL; ?>/more_options.php" class="back-button" aria-label="Voltar"><i class="fas fa-chevron-left"></i></a>
        <h1 class="page-title" style="margin-left:10px;"><?php echo $page_heading; ?></h1>
    </div>

    <?php if (!empty($recipes)): ?>
        <div class="recipe-grid">
            <?php foreach($recipes as $recipe): ?>
                <a href="<?php echo BASE_APP_URL; ?>/view_recipe.php?id=<?php echo $recipe['id']; ?>" class="recipe-item-link card-shadow-light">
                    <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . htmlspecialchars($recipe['image_filename'] ? $recipe['image_filename'] : 'placeholder_food.jpg'); ?>" alt="<?php echo htmlspecialchars($recipe['name']); ?>" class="recipe-item-image">
                    <div class="recipe-item-info">
                        <h3 class="recipe-item-name"><?php echo htmlspecialchars($recipe['name']); ?></h3>
                        <p class="recipe-item-kcal"><?php echo round($recipe['total_kcal_per_serving']); ?> kcal</p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="text-center card-shadow-light" style="padding:20px;">Você ainda não favoritou nenhuma receita. Toque no coração ♡ nas receitas que você mais gosta!</p>
    <?php endif; ?>
</div>

<?php
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>