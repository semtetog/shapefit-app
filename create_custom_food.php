<?php
// public_html/shapefit/create_custom_food.php

require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();

// --- LÓGICA PARA PRÉ-PREENCHER O FORMULÁRIO COM DADOS DA URL (DO OCR) ---
$kcal_prefill = isset($_GET['kcal_100g']) ? htmlspecialchars($_GET['kcal_100g']) : '';
$protein_prefill = isset($_GET['protein_100g']) ? htmlspecialchars($_GET['protein_100g']) : '';
$carbs_prefill = isset($_GET['carbs_100g']) ? htmlspecialchars($_GET['carbs_100g']) : '';
$fat_prefill = isset($_GET['fat_100g']) ? htmlspecialchars($_GET['fat_100g']) : '';
$food_name_prefill = isset($_GET['food_name']) ? htmlspecialchars($_GET['food_name']) : '';
$brand_name_prefill = isset($_GET['brand_name']) ? htmlspecialchars($_GET['brand_name']) : '';


// --- VARIÁVEIS PARA O TEMPLATE ---
$page_title = "Cadastrar Novo Alimento";
$extra_css = ['add-food-page.css']; // Reutiliza o estilo da página de adicionar alimento
$extra_js = []; // Esta página não precisa de JS complexo
require_once APP_ROOT_PATH . '/includes/layout_header.php';
?>

<div class="add-food-interface">
    <div class="container add-food-container">
        <header class="header-nav">
            <!-- Volta para a página anterior, que é a de adicionar refeição -->
            <a href="javascript:history.back()" class="back-button" aria-label="Voltar"><i class="fas fa-arrow-left"></i></a>
            <h1 class="page-title">Cadastrar Alimento</h1>
        </header>

        <main>
            <form action="<?php echo BASE_APP_URL; ?>/process_save_custom_food.php" method="POST">
                <!-- O token CSRF é gerado e incluído pelo layout_header.php -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="card-shadow manual-entry-card">
                    <div class="form-group">
                        <label for="food_name">Nome do Alimento</label>
                        <input type="text" id="food_name" name="food_name" class="form-control" placeholder="Ex: Pão de Forma Integral" value="<?php echo $food_name_prefill; ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="brand_name">Marca (opcional)</label>
                        <input type="text" id="brand_name" name="brand_name" class="form-control" placeholder="Ex: Wickbold" value="<?php echo $brand_name_prefill; ?>">
                    </div>
                    
                    <hr class="form-divider">
                    
                    <p class="form-section-title">Informação Nutricional (por 100g)</p>
                    
                    <div class="form-group">
                        <label for="kcal_100g">Calorias (kcal)</label>
                        <input type="number" id="kcal_100g" name="kcal_100g" class="form-control" placeholder="Ex: 250" value="<?php echo $kcal_prefill; ?>" required step="any">
                    </div>
                    <div class="form-group">
                        <label for="protein_100g">Proteínas (g)</label>
                        <input type="number" id="protein_100g" name="protein_100g" class="form-control" placeholder="Ex: 8.5" value="<?php echo $protein_prefill; ?>" required step="any">
                    </div>
                    <div class="form-group">
                        <label for="carbs_100g">Carboidratos (g)</label>
                        <input type="number" id="carbs_100g" name="carbs_100g" class="form-control" placeholder="Ex: 45.2" value="<?php echo $carbs_prefill; ?>" required step="any">
                    </div>
                    <div class="form-group">
                        <label for="fat_100g">Gorduras (g)</label>
                        <input type="number" id="fat_100g" name="fat_100g" class="form-control" placeholder="Ex: 4.1" value="<?php echo $fat_prefill; ?>" required step="any">
                    </div>
                </div>
                
                <!-- O footer com o botão de salvar fica fixo na parte de baixo -->
                <footer class="form-actions-fixed">
                    <button type="submit" class="btn btn-primary">Salvar Alimento no Meu Diário</button>
                </footer>
            </form>
        </main>
    </div>
</div>

<?php
require_once APP_ROOT_PATH . '/includes/layout_footer.php';
?>