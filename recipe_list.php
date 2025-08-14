<?php
// public_html/shapefit/recipe_list.php
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php';

$user_id = $_SESSION['user_id'];

$filter_type = null;
$filter_value = null;
$page_heading = "Receitas";

$recipes = [];
$search_term = trim($_GET['search'] ?? '');

$sql_base = "SELECT id, name, image_filename, total_kcal_per_serving, description FROM sf_recipes WHERE is_public = TRUE";
$sql_conditions = [];
$params = [];
$types = "";

if (!empty($_GET['meal_type'])) {
    $filter_type = 'meal_type';
    $filter_value = trim($_GET['meal_type']);
    
    switch ($filter_value) {
        case 'cafe_da_manha':
            $page_heading = "Café da Manhã";
            $sql_conditions[] = "meal_type_suggestion = ?";
            $params[] = "cafe_da_manha";
            $types .= "s";
            break;
        case 'almoco':
            $page_heading = "Almoço";
            $sql_conditions[] = "meal_type_suggestion = ?";
            $params[] = "almoco_jantar";
            $types .= "s";
            break;
        case 'lanche':
            $page_heading = "Lanches";
            $sql_conditions[] = "meal_type_suggestion = ?";
            $params[] = "lanche";
            $types .= "s";
            break;
        case 'jantar':
            $page_heading = "Jantar";
            $sql_conditions[] = "meal_type_suggestion = ?";
            $params[] = "almoco_jantar";
            $types .= "s";
            break;
        default:
            $page_heading = "Receitas";
            break;
    }
} elseif (!empty($_GET['category_slug'])) {
    $filter_type = 'category';
    $filter_value = trim($_GET['category_slug']);
    
    $stmt_cat_name = $conn->prepare("SELECT name FROM sf_recipe_categories WHERE slug = ?");
    if ($stmt_cat_name) {
        $stmt_cat_name->bind_param("s", $filter_value);
        $stmt_cat_name->execute();
        $result_cat_name = $stmt_cat_name->get_result();
        if($cat_row = $result_cat_name->fetch_assoc()){
            $page_heading = "Receitas: " . htmlspecialchars($cat_row['name']);
        }
        $stmt_cat_name->close();
    }

    $sql_base = "SELECT r.id, r.name, r.image_filename, r.total_kcal_per_serving, r.description 
                 FROM sf_recipes r
                 JOIN sf_recipe_has_categories rhc ON r.id = rhc.recipe_id
                 JOIN sf_recipe_categories rc ON rhc.category_id = rc.id
                 WHERE r.is_public = TRUE AND rc.slug = ?";
    $params[] = $filter_value;
    $types .= "s";
}


if (!empty($search_term)) {
    $page_heading = "Busca por: \"" . htmlspecialchars($search_term) . "\"";
    $sql_conditions[] = "name LIKE ?";
    $params[] = "%" . $search_term . "%";
    $types .= "s";
}

$sql_query = $sql_base;
if (!empty($sql_conditions) && $filter_type !== 'category') {
    $sql_query .= " AND " . implode(" AND ", $sql_conditions);
}
$sql_query .= " ORDER BY name ASC";

$stmt_recipes = $conn->prepare($sql_query);

if ($stmt_recipes) {
    if (!empty($types) && !empty($params)) {
        $stmt_recipes->bind_param($types, ...$params);
    }
    $stmt_recipes->execute();
    $result_recipes = $stmt_recipes->get_result();
    while($row = $result_recipes->fetch_assoc()) {
        $recipes[] = $row;
    }
    $stmt_recipes->close();
} else {
    error_log("Erro ao preparar busca de lista de receitas: " . $conn->error . " SQL: " . $sql_query);
}

$extra_css = ['recipe_list_specific.css'];
$page_title = $page_heading;
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="container recipe-list-container" style="padding-bottom: 90px;">
    <div class="header-nav">
        <a href="javascript:history.back()" class="back-button" aria-label="Voltar"><i class="fas fa-chevron-left"></i></a>
        <h1 class="page-title" style="margin-left:10px; margin-bottom:0; font-size: 1.5em;"><?php echo htmlspecialchars($page_heading); ?></h1>
    </div>

    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="GET" class="search-form mb-3">
        <?php if($filter_type === 'meal_type' && !empty($_GET['meal_type'])): ?>
            <input type="hidden" name="meal_type" value="<?php echo htmlspecialchars($_GET['meal_type']); ?>">
        <?php elseif($filter_type === 'category' && !empty($_GET['category_slug'])): ?>
            <input type="hidden" name="category_slug" value="<?php echo htmlspecialchars($_GET['category_slug']); ?>">
        <?php endif; ?>
        <div class="form-group" style="display:flex; gap:10px;">
            <input type="search" name="search" class="form-control" placeholder="Buscar por nome da receita..." value="<?php echo htmlspecialchars($search_term); ?>">
            <button type="submit" class="btn btn-primary" style="width:auto; padding: 12px 15px;"><i class="fas fa-search"></i></button>
        </div>
    </form>

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
        <p class="text-center card-shadow-light" style="padding:20px;">Nenhuma receita encontrada com os critérios selecionados.</p>
    <?php endif; ?>

</div>

<?php
// Adiciona o menu de navegação inferior
include APP_ROOT_PATH . '/includes/layout_bottom_nav.php';

require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>