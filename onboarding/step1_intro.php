<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$temp_name = $_SESSION['onboarding_data']['name'] ?? null;
$temp_email = $_SESSION['onboarding_data']['email'] ?? null;

if (!isset($_SESSION['onboarding_data']['step1_visited'])) {
    $_SESSION['onboarding_data'] = [];
    if ($temp_name) $_SESSION['onboarding_data']['name'] = $temp_name;
    if ($temp_email) $_SESSION['onboarding_data']['email'] = $temp_email;
    $_SESSION['onboarding_data']['step1_visited'] = true;
}
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

$page_title = "Calcular Meta";
require_once '../includes/layout_header.php';
?>

    <div class="container">
        <h1 class="page-title" style="margin-top: 50px;">Vamos calcular<br>sua meta?</h1>
        <p class="page-subtitle">Preciso que você responda algumas perguntas.</p>

        <!-- MUDANÇA AQUI: Usar um link <a> estilizado como botão, ou um form com method="GET" -->
        <a href="<?php echo BASE_ASSET_URL; ?>/onboarding/step4_objective.php" class="btn btn-primary" style="margin-top: 50px; display: block; width: 100%; text-align: center;">Continuar</a>
        
        <?php /* Ou, se preferir manter a tag <form> para o botão:
        <form action="<?php echo BASE_ASSET_URL; ?>/onboarding/step4_objective.php" method="GET">
            <button type="submit" class="btn btn-primary" style="margin-top: 50px;">Continuar</button>
        </form>
        */ ?>
    </div>

<?php
require_once '../includes/layout_footer.php';
?>