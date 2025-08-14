<?php
// admin/recipes.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
require_once __DIR__ . '/../includes/db.php';

requireAdminLogin();

$page_slug = 'recipes';
$page_title = 'Gerenciar Receitas';

// Lógica de busca e filtro (placeholder por enquanto)
$search_term = $_GET['search'] ?? '';

// Busca as receitas
// Vamos simplificar por enquanto, buscando todas.
$sql = "SELECT id, name, created_at, is_public, image_filename FROM sf_recipes ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$recipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();


require_once __DIR__ . '/includes/header.php';
?>

<h2>Receitas</h2>
<div class="toolbar">
    <form method="GET" class="search-form">
        <input type="text" name="search" placeholder="Buscar por nome da receita..." value="<?php echo htmlspecialchars($search_term); ?>">
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
    <a href="edit_recipe.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nova Receita</a>
</div>

<div class="content-card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Imagem</th>
                <th>Nome da Receita</th>
                <th>Data de Criação</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recipes)): ?>
                <tr>
                    <td colspan="5" class="empty-state">Nenhuma receita encontrada.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recipes as $recipe): ?>
                    <tr>
                        <td>
                            <img src="<?php echo BASE_ASSET_URL . '/assets/images/recipes/' . ($recipe['image_filename'] ?: 'placeholder_food.jpg'); ?>" alt="Foto de <?php echo htmlspecialchars($recipe['name']); ?>" class="table-image-preview">
                        </td>
                        <td><?php echo htmlspecialchars($recipe['name']); ?></td>
                        <td><?php echo date('d/m/Y', strtotime($recipe['created_at'])); ?></td>
                        <td>
                            <span class="status-badge <?php echo $recipe['is_public'] ? 'status-public' : 'status-private'; ?>">
                                <?php echo $recipe['is_public'] ? 'Pública' : 'Privada'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?php echo BASE_APP_URL . '/view_recipe.php?id=' . $recipe['id']; ?>" target="_blank" class="btn-action view" title="Ver no App"><i class="fas fa-eye"></i></a>
                            <a href="edit_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn-action edit" title="Editar"><i class="fas fa-pencil-alt"></i></a>
                            <a href="delete_recipe.php?id=<?php echo $recipe['id']; ?>" class="btn-action delete" title="Apagar" onclick="return confirm('Tem certeza que deseja apagar esta receita? Esta ação não pode ser desfeita.');"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>