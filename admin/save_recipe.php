<?php
// admin/save_recipe.php (Versão Final e Completa)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
requireAdminLogin();

// --- Validação Inicial ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: recipes.php");
    exit;
}

// --- Coleta de Dados do Formulário ---
$recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);
$name = trim($_POST['name']);
$description = trim($_POST['description'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$prep_time_minutes = filter_input(INPUT_POST, 'prep_time_minutes', FILTER_VALIDATE_INT);
$cook_time_minutes = filter_input(INPUT_POST, 'cook_time_minutes', FILTER_VALIDATE_INT);
$servings = filter_input(INPUT_POST, 'servings', FILTER_VALIDATE_INT);
$total_kcal_per_serving = filter_input(INPUT_POST, 'total_kcal_per_serving', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$protein_g_per_serving = filter_input(INPUT_POST, 'protein_g_per_serving', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$carbs_g_per_serving = filter_input(INPUT_POST, 'carbs_g_per_serving', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$fat_g_per_serving = filter_input(INPUT_POST, 'fat_g_per_serving', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
$meal_type_suggestion = $_POST['meal_type_suggestion'] ?? 'almoco_jantar';
$is_public = (int)($_POST['is_public'] ?? 0);
$ingredient_names = $_POST['ingredient_name'] ?? [];
$ingredient_quantities = $_POST['ingredient_quantity'] ?? [];
$categories = $_POST['categories'] ?? [];

// Validação básica
if (empty($name)) { die("O nome da receita é obrigatório."); }

// --- Lógica de Upload de Imagem ---
$image_filename = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/webp'];
    if (in_array($file['type'], $allowed_mime_types) && $file['size'] <= 5 * 1024 * 1024) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $image_filename = 'recipe_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $upload_path = APP_ROOT_PATH . '/assets/images/recipes/' . $image_filename;
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            die("Erro ao mover o arquivo de imagem.");
        }
    } else {
        die("Arquivo de imagem inválido ou muito grande.");
    }
}

// --- Operações no Banco de Dados (com Transação) ---
$conn->begin_transaction();
try {
    // Passo 1: Inserir ou Atualizar a Receita Principal
    if ($recipe_id) { // Atualizar existente
        $sql = "UPDATE sf_recipes SET name=?, description=?, instructions=?, notes=?, prep_time_minutes=?, cook_time_minutes=?, servings=?, total_kcal_per_serving=?, protein_g_per_serving=?, carbs_g_per_serving=?, fat_g_per_serving=?, meal_type_suggestion=?, is_public=?";
        // Apenas atualiza a imagem se uma nova foi enviada
        if ($image_filename) { $sql .= ", image_filename=?"; }
        $sql .= " WHERE id=?";
        
        $stmt = $conn->prepare($sql);
        if ($image_filename) {
            $stmt->bind_param("sssiiiiiddssisi", $name, $description, $instructions, $notes, $prep_time_minutes, $cook_time_minutes, $servings, $total_kcal_per_serving, $protein_g_per_serving, $carbs_g_per_serving, $fat_g_per_serving, $meal_type_suggestion, $is_public, $image_filename, $recipe_id);
        } else {
            $stmt->bind_param("sssiiiiiddssisi", $name, $description, $instructions, $notes, $prep_time_minutes, $cook_time_minutes, $servings, $total_kcal_per_serving, $protein_g_per_serving, $carbs_g_per_serving, $fat_g_per_serving, $meal_type_suggestion, $is_public, $recipe_id);
        }
    } else { // Inserir nova
        $sql = "INSERT INTO sf_recipes (name, description, image_filename, instructions, notes, prep_time_minutes, cook_time_minutes, servings, total_kcal_per_serving, protein_g_per_serving, carbs_g_per_serving, fat_g_per_serving, meal_type_suggestion, is_public, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $admin_id = $_SESSION['admin_id']; // Associa a receita ao admin que a criou
        $stmt->bind_param("sssssiiiiddssii", $name, $description, $image_filename, $instructions, $notes, $prep_time_minutes, $cook_time_minutes, $servings, $total_kcal_per_serving, $protein_g_per_serving, $carbs_g_per_serving, $fat_g_per_serving, $meal_type_suggestion, $is_public, $admin_id);
    }
    $stmt->execute();
    if (!$recipe_id) { $recipe_id = $conn->insert_id; } // Pega o ID da nova receita
    $stmt->close();

    // Passo 2: Atualizar Ingredientes (apaga os antigos e insere os novos)
    $stmt_del_ing = $conn->prepare("DELETE FROM sf_recipe_ingredients WHERE recipe_id = ?");
    $stmt_del_ing->bind_param("i", $recipe_id);
    $stmt_del_ing->execute();
    $stmt_del_ing->close();
    
    $stmt_ins_ing = $conn->prepare("INSERT INTO sf_recipe_ingredients (recipe_id, name, quantity_display) VALUES (?, ?, ?)");
    for ($i = 0; $i < count($ingredient_names); $i++) {
        if (!empty($ingredient_names[$i]) && !empty($ingredient_quantities[$i])) {
            $stmt_ins_ing->bind_param("iss", $recipe_id, $ingredient_names[$i], $ingredient_quantities[$i]);
            $stmt_ins_ing->execute();
        }
    }
    $stmt_ins_ing->close();

    // Passo 3: Atualizar Categorias (mesma lógica dos ingredientes)
    $stmt_del_cat = $conn->prepare("DELETE FROM sf_recipe_has_categories WHERE recipe_id = ?");
    $stmt_del_cat->bind_param("i", $recipe_id);
    $stmt_del_cat->execute();
    $stmt_del_cat->close();

    if (!empty($categories)) {
        $stmt_ins_cat = $conn->prepare("INSERT INTO sf_recipe_has_categories (recipe_id, category_id) VALUES (?, ?)");
        foreach ($categories as $category_id) {
            $stmt_ins_cat->bind_param("ii", $recipe_id, $category_id);
            $stmt_ins_cat->execute();
        }
        $stmt_ins_cat->close();
    }

    // Se tudo deu certo, confirma a transação
    $conn->commit();

} catch (Exception $e) {
    // Se algo deu errado, desfaz tudo
    $conn->rollback();
    error_log("Erro ao salvar receita: " . $e->getMessage());
    die("Ocorreu um erro ao salvar a receita. Verifique os logs do servidor.");
}

// Redireciona de volta para a lista de receitas
header("Location: recipes.php?status=success");
exit;
?>