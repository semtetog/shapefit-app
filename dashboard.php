<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once 'includes/config.php';
require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';
require_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];

// Verificar se o onboarding foi completado
$stmt_check_onboarding = $conn->prepare("SELECT onboarding_complete FROM sf_users WHERE id = ?");
if (!$stmt_check_onboarding) {
    error_log("Dashboard Error - Prepare sf_users failed: " . $conn->error);
    die("Ocorreu um erro inesperado. Tente novamente mais tarde.");
}
$stmt_check_onboarding->bind_param("i", $user_id);
$stmt_check_onboarding->execute();
$result_check_onboarding = $stmt_check_onboarding->get_result();
$user_onboarding_status = $result_check_onboarding->fetch_assoc();
$stmt_check_onboarding->close();

if (!$user_onboarding_status || !$user_onboarding_status['onboarding_complete']) {
    header("Location: " . BASE_APP_URL . "/onboarding/step1_intro.php");
    exit();
}

// Buscar dados do perfil do usuário
$stmt_profile_data = $conn->prepare("
    SELECT u.name, up.dob, up.gender, up.height_cm, up.weight_kg, up.objective, up.activity_level
    FROM sf_users u
    JOIN sf_user_profiles up ON u.id = up.user_id
    WHERE u.id = ?
");
if (!$stmt_profile_data) {
    error_log("Dashboard Error - Prepare profile data failed: " . $conn->error);
    die("Ocorreu um erro ao buscar seus dados. Tente novamente mais tarde.");
}
$stmt_profile_data->bind_param("i", $user_id);
$stmt_profile_data->execute();
$result_profile_data = $stmt_profile_data->get_result();

if ($result_profile_data->num_rows === 0) {
    error_log("Dashboard: User profile not found for user_id: " . $user_id . ". Forcing onboarding.");
    $stmt_reset_onboarding = $conn->prepare("UPDATE sf_users SET onboarding_complete = FALSE WHERE id = ?");
    if($stmt_reset_onboarding) {
        $stmt_reset_onboarding->bind_param("i", $user_id);
        $stmt_reset_onboarding->execute();
        $stmt_reset_onboarding->close();
    }
    header("Location: " . BASE_APP_URL . "/onboarding/step1_intro.php?error=profile_missing_critical");
    exit();
}
$profile = $result_profile_data->fetch_assoc();
$stmt_profile_data->close();

$age_years = calculateAge($profile['dob']);
$daily_calories = calculateTargetDailyCalories(
    $profile['gender'],
    (float)$profile['weight_kg'],
    (int)$profile['height_cm'],
    $age_years,
    $profile['activity_level'],
    $profile['objective']
);
$macros = calculateMacronutrients($daily_calories, $profile['objective']);


$current_date = date('Y-m-d');
$daily_tracking = getDailyTrackingRecord($conn, $user_id, $current_date);

$kcal_consumed = $daily_tracking['kcal_consumed'] ?? 0;
$carbs_consumed = $daily_tracking['carbs_consumed_g'] ?? 0.00;
$protein_consumed = $daily_tracking['protein_consumed_g'] ?? 0.00;
$fat_consumed = $daily_tracking['fat_consumed_g'] ?? 0.00;

// ========================================================
// CORREÇÃO DA LÓGICA DE ÁGUA AQUI
// ========================================================
$water_data = getWaterIntakeSuggestion((float)$profile['weight_kg']);
$water_cups = $water_data['cups'];
$water_ml = $water_data['total_ml'];
// ========================================================

$page_title = "Minha Meta";
$extra_css = ['main_app_specific.css']; // Ou o nome do arquivo onde você colocou o CSS
require_once 'includes/layout_header.php';
?>

    <div class="container">
        <div class="page-header-with-help">
    <h1 class="page-title">Minha meta</h1>
    <button id="open-help-modal-btn" class="help-icon-button" aria-label="Ajuda sobre as metas">
        <i class="fas fa-question-circle"></i>
    </button>
</div>

        <div class="goal-card">
            <div class="main-goal">
                <h3>Calorias diárias</h3>
                <div class="calories"><?php echo number_format($daily_calories, 0, '', ''); ?></div>
            </div>

            <div class="macros-grid">
             <div class="macro-item">
    <h4>Carboidratos</h4>
    <div>
        <span class="underlined-container">
            <span class="value value-number">
                <?php
                    // Calcula a porcentagem do consumo em relação à meta
                    $carb_progress = ($macros['carbs_g'] > 0) ? round(($carbs_consumed / $macros['carbs_g']) * 100) : 0;
                    echo min(100, $carb_progress); // Mostra no máximo 100%
                ?>
            </span>
            <span class="value-unit">%</span>
        </span>
    </div>
    <div class="value-grams"><?php echo htmlspecialchars(round($macros['carbs_g'])); ?><span class="value-unit">g</span></div>
</div>

                <div class="macro-item">
    <h4>Proteína</h4>
    <div>
        <span class="underlined-container">
            <span class="value value-number">
                <?php
                    // Calcula a porcentagem do consumo em relação à meta
                    $protein_progress = ($macros['protein_g'] > 0) ? round(($protein_consumed / $macros['protein_g']) * 100) : 0;
                    echo min(100, $protein_progress); // Mostra no máximo 100%
                ?>
            </span>
            <span class="value-unit">%</span>
        </span>
    </div>
    <div class="value-grams"><?php echo htmlspecialchars(round($macros['protein_g'])); ?><span class="value-unit">g</span></div>
</div>
               <div class="macro-item">
    <h4>Gordura</h4>
    <div>
        <span class="underlined-container">
            <span class="value value-number">
                <?php
                    // Calcula a porcentagem do consumo em relação à meta
                    $fat_progress = ($macros['fat_g'] > 0) ? round(($fat_consumed / $macros['fat_g']) * 100) : 0;
                    echo min(100, $fat_progress); // Mostra no máximo 100%
                ?>
            </span>
            <span class="value-unit">%</span>
        </span>
    </div>
    <div class="value-grams"><?php echo htmlspecialchars(round($macros['fat_g'])); ?><span class="value-unit">g</span></div>
</div>
                
                <!-- ============================================= -->
                <!-- CORREÇÃO DA EXIBIÇÃO DA ÁGUA AQUI             -->
                <!-- ============================================= -->
                <div class="macro-item">
                    <h4>Água</h4>
                    <div class="value"><?php echo htmlspecialchars($water_cups); ?> <span class="value-unit" style="font-size:22px;">copos</span></div>
                     <div class="value-grams">(aprox. <?php echo htmlspecialchars(number_format($water_ml, 0, ',', '.')); ?><span class="value-unit">ml</span>)</div>
                </div>
                <!-- ============================================= -->

            </div>

            <button type="button" class="btn btn-primary" onclick="window.location.href='<?php echo BASE_APP_URL; ?>/main_app.php';">Continuar para o App</button>
        </div>
         <p class="text-center mt-3"><a href="<?php echo BASE_APP_URL; ?>/onboarding/step1_intro.php" style="color: var(--secondary-text-color); font-size: 0.9em; text-decoration:none;">Refazer questionário</a></p>
    </div>
    
    

<!-- ======================================================== -->
<!--      HTML COMPLETO E FINAL DO MODAL DE AJUDA           -->
<!-- ======================================================== -->
<div id="goal-help-modal" class="modal-container" style="display: none;">
    
    <!-- O fundo escuro com blur, que cobre toda a tela -->
    <div class="modal-backdrop" data-action="close-modal"></div>
    
    <!-- O conteúdo do modal que fica na frente do fundo -->
    <div class="modal-content stylish-modal">
        <button class="modal-close-btn" data-action="close-modal" aria-label="Fechar">
            ×
        </button>

        <div class="modal-header">
            <div class="modal-icon-wrapper">
                <i class="fas fa-bullseye"></i>
            </div>
            <h2>Entendendo Suas Metas</h2>
        </div>

        <div class="modal-body">
            <p>Esta tela mostra o seu <strong>progresso diário</strong> em relação às metas que calculamos para você atingir seu objetivo.</p>
            
            <div class="explanation-item">
                <div class="item-icon calories">
                    <i class="fas fa-fire-alt"></i>
                </div>
                <div class="item-text">
                    <strong>Calorias Diárias:</strong> É o total de energia que você deve consumir hoje.
                </div>
            </div>

            <div class="explanation-item">
                <div class="item-icon macros">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="item-text">
                    <strong>Macronutrientes (Carboidratos, Proteína, Gordura):</strong> A porcentagem (%) indica o quanto você <strong>já consumiu</strong> da sua meta em gramas (g) para hoje. O objetivo é chegar a 100% em cada um deles!
                </div>
            </div>

            <div class="explanation-item">
                <div class="item-icon water">
                    <i class="fas fa-tint"></i>
                </div>
                <div class="item-text">
                    <strong>Água:</strong> Sua meta de hidratação diária, calculada com base no seu peso para manter seu corpo funcionando bem.
                </div>
            </div>

            <p class="final-tip">Continue registrando suas refeições no App para ver seu progresso aumentar!</p>
        </div>
        
        <div class="modal-footer">
            <button class="btn btn-primary" data-action="close-modal">Entendi!</button>
        </div>
    </div>
</div>

<?php

$extra_js = ['dashboard_logic.js']; // Nome do seu novo arquivo JS
require_once 'includes/layout_footer.php';
?>