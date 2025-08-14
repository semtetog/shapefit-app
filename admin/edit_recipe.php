<?php
// admin/edit_recipe.php (Versão Final e Completa)

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
requireAdminLogin();

$page_slug = 'recipes';
$page_title = 'Nova Receita'; // Título padrão

// --- Dados Iniciais ---
$recipe_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$recipe = [
    'id' => null, 'name' => '', 'description' => '', 'instructions' => '', 'notes' => '',
    'prep_time_minutes' => '', 'cook_time_minutes' => '', 'servings' => '', 
    'total_kcal_per_serving' => '', 'protein_g_per_serving' => '', 'carbs_g_per_serving' => '', 'fat_g_per_serving' => '',
    'meal_type_suggestion' => 'almoco_jantar', 'is_public' => 1, 'image_filename' => null, 'user_id' => null // user_id (autor) é opcional
];
$ingredients = [];
$selected_categories = [];

// --- Busca todas as categorias disponíveis para o formulário ---
$all_categories = $conn->query("SELECT id, name FROM sf_recipe_categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);

// --- Se for EDIÇÃO, busca os dados da receita existente ---
if ($recipe_id) {
    $page_title = 'Editar Receita';
    
    // Busca dados principais da receita
    $stmt = $conn->prepare("SELECT * FROM sf_recipes WHERE id = ?");
    $stmt->bind_param("i", $recipe_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $recipe = $result->fetch_assoc();
        
        // Busca ingredientes da receita
        $stmt_ing = $conn->prepare("SELECT * FROM sf_recipe_ingredients WHERE recipe_id = ? ORDER BY id ASC");
        $stmt_ing->bind_param("i", $recipe_id);
        $stmt_ing->execute();
        $ingredients = $stmt_ing->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_ing->close();

        // Busca categorias da receita
        $stmt_cat = $conn->prepare("SELECT category_id FROM sf_recipe_has_categories WHERE recipe_id = ?");
        $stmt_cat->bind_param("i", $recipe_id);
        $stmt_cat->execute();
        $cat_result = $stmt_cat->get_result();
        while($row = $cat_result->fetch_assoc()){
            $selected_categories[] = $row['category_id'];
        }
        $stmt_cat->close();

    } else {
        die("Receita não encontrada.");
    }
    $stmt->close();
}

require_once __DIR__ . '/includes/header.php';
?>

<h2><?php echo $page_title; ?></h2>

<!-- O action aponta para o script que vai salvar os dados -->
<form action="save_recipe.php" method="POST" enctype="multipart/form-data" id="recipe-form">
    <!-- Campo oculto para saber se é uma edição ou criação -->
    <input type="hidden" name="recipe_id" value="<?php echo htmlspecialchars($recipe['id'] ?? ''); ?>">

    <div class="edit-grid">
        <!-- Coluna da Esquerda: Conteúdo Principal -->
        <div class="grid-col-main">
            <div class="content-card">
                <h3>Detalhes da Receita</h3>
                <div class="form-group">
                    <label for="name">Nome da Receita</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($recipe['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Descrição Curta (chamada para a receita)</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($recipe['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="instructions">Modo de Preparo</label>
                    <textarea id="instructions" name="instructions" class="form-control" rows="8" placeholder="Use um item por linha. Ex:
1. Pique a cebola.
2. Refogue no azeite."><?php echo htmlspecialchars($recipe['instructions']); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="notes">Notas e Dicas (Opcional)</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($recipe['notes']); ?></textarea>
                </div>
            </div>

            <div class="content-card">
                <h3>Ingredientes</h3>
                <div id="ingredients-container">
                    <?php if (empty($ingredients)): ?>
                        <div class="ingredient-row">
                            <input type="text" name="ingredient_name[]" class="form-control" placeholder="Nome do Ingrediente (ex: Ovo)" required>
                            <input type="text" name="ingredient_quantity[]" class="form-control" placeholder="Quantidade (ex: 2 unidades)" required>
                            <button type="button" class="btn-remove-ingredient" title="Remover">×</button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ingredients as $ingredient): ?>
                            <div class="ingredient-row">
                                <input type="text" name="ingredient_name[]" class="form-control" value="<?php echo htmlspecialchars($ingredient['name']); ?>" required>
                                <input type="text" name="ingredient_quantity[]" class="form-control" value="<?php echo htmlspecialchars($ingredient['quantity_display']); ?>" required>
                                <button type="button" class="btn-remove-ingredient" title="Remover">×</button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" id="btn-add-ingredient" class="btn btn-secondary" style="margin-top: 15px;"><i class="fas fa-plus"></i> Adicionar Ingrediente</button>
            </div>
        </div>

        <!-- Coluna da Direita: Metadados e Configurações -->
        <div class="grid-col-side">
            <div class="content-card">
                <h3>Publicação</h3>
                <div class="form-group">
                    <label for="is_public">Status</label>
                    <select id="is_public" name="is_public" class="form-control">
                        <option value="1" <?php if($recipe['is_public'] == 1) echo 'selected'; ?>>Pública (visível para todos)</option>
                        <option value="0" <?php if($recipe['is_public'] == 0) echo 'selected'; ?>>Privada (rascunho)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="image">Imagem da Receita</label>
                    <input type="file" id="image" name="image" class="form-control" accept="image/jpeg, image/png, image/webp">
                    <?php if ($recipe['image_filename']): ?>
                        <div class="image-preview">
                            <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . $recipe['image_filename']; ?>" alt="Preview">
                            <span>Imagem atual. Envie uma nova para substituir.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="content-card">
                <h3>Categorização</h3>
                <div class="form-group">
                    <label for="meal_type_suggestion">Sugerir para Refeição Principal</label>
                    <select id="meal_type_suggestion" name="meal_type_suggestion" class="form-control">
                        <option value="cafe_da_manha" <?php if($recipe['meal_type_suggestion'] == 'cafe_da_manha') echo 'selected'; ?>>Café da Manhã</option>
                        <option value="almoco_jantar" <?php if($recipe['meal_type_suggestion'] == 'almoco_jantar') echo 'selected'; ?>>Almoço / Jantar</option>
                        <option value="lanche" <?php if($recipe['meal_type_suggestion'] == 'lanche') echo 'selected'; ?>>Lanche / Ceia</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Outras Categorias</label>
                    <div class="checkbox-group-scroll">
                        <?php foreach($all_categories as $category): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" id="category_<?php echo $category['id']; ?>" name="categories[]" value="<?php echo $category['id']; ?>" <?php if (in_array($category['id'], $selected_categories)) echo 'checked'; ?>>
                                <label for="category_<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
             <div class="content-card">
                <h3>Detalhes Adicionais</h3>
                <div class="form-group-grid-2">
                    <div class="form-group"><label for="prep_time_minutes">Preparo (min)</label><input type="number" name="prep_time_minutes" class="form-control" value="<?php echo htmlspecialchars($recipe['prep_time_minutes']); ?>"></div>
                    <div class="form-group"><label for="cook_time_minutes">Cozimento (min)</label><input type="number" name="cook_time_minutes" class="form-control" value="<?php echo htmlspecialchars($recipe['cook_time_minutes']); ?>"></div>
                    <div class="form-group"><label for="servings">Porções</label><input type="number" name="servings" class="form-control" value="<?php echo htmlspecialchars($recipe['servings']); ?>"></div>
                </div>
            </div>
            <div class="content-card">
                <h3>Informações Nutricionais (por porção)</h3>
                <div class="form-group-grid-2">
                    <div class="form-group"><label for="total_kcal_per_serving">Calorias (kcal)</label><input type="number" name="total_kcal_per_serving" class="form-control" value="<?php echo htmlspecialchars($recipe['total_kcal_per_serving']); ?>"></div>
                    <div class="form-group"><label for="protein_g_per_serving">Proteínas (g)</label><input type="number" step="0.1" name="protein_g_per_serving" class="form-control" value="<?php echo htmlspecialchars($recipe['protein_g_per_serving']); ?>"></div>
                    <div class="form-group"><label for="carbs_g_per_serving">Carboidratos (g)</label><input type="number" step="0.1" name="carbs_g_per_serving" class="form-control" value="<?php echo htmlspecialchars($recipe['carbs_g_per_serving']); ?>"></div>
                    <div class="form-group"><label for="fat_g_per_serving">Gorduras (g)</label><input type="number" step="0.1" name="fat_g_per_serving" class="form-control" value="<?php echo htmlspecialchars($recipe['fat_g_per_serving']); ?>"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="form-actions-footer">
        <a href="recipes.php" class="btn btn-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Salvar Receita</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ingredientsContainer = document.getElementById('ingredients-container');
    
    document.getElementById('btn-add-ingredient').addEventListener('click', function() {
        const newRow = document.createElement('div');
        newRow.className = 'ingredient-row';
        newRow.innerHTML = `
            <input type="text" name="ingredient_name[]" class="form-control" placeholder="Nome do Ingrediente (ex: Ovo)" required>
            <input type="text" name="ingredient_quantity[]" class="form-control" placeholder="Quantidade (ex: 2 unidades)" required>
            <button type="button" class="btn-remove-ingredient" title="Remover">×</button>
        `;
        ingredientsContainer.appendChild(newRow);
    });

    ingredientsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-remove-ingredient')) {
            if (ingredientsContainer.querySelectorAll('.ingredient-row').length > 1) {
                e.target.closest('.ingredient-row').remove();
            } else {
                alert('A receita deve ter pelo menos um ingrediente.');
            }
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>