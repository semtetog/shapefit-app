<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

// Usamos um valor distinto (ex: 'not_set') para saber se já foi definido na sessão,
// diferente de já ter sido definido como false (0).
$has_restrictions_from_session = $_SESSION['onboarding_data']['has_dietary_restrictions'] ?? 'not_set';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $has_restrictions_input = $_POST['has_restrictions'] ?? null; // null se não enviado

    if ($has_restrictions_input === null) {
        $error_message = "Por favor, selecione uma opção.";
        // Mantém o valor da sessão para re-exibir, ou 'not_set' se for a primeira vez
        $has_restrictions_to_display = $has_restrictions_from_session;
    } else {
        $has_restrictions_bool = ($has_restrictions_input === '1');
        $_SESSION['onboarding_data']['has_dietary_restrictions'] = $has_restrictions_bool;
        $has_restrictions_to_display = $has_restrictions_bool; // Atualiza para re-exibir

        if ($has_restrictions_bool) {
            // Próximo passo na NOVA ORDEM: step7 -> step8_restrictions_select
            header("Location: " . BASE_APP_URL . "/onboarding/step8_restrictions_select.php");
        } else {
            // Próximo passo na NOVA ORDEM (se não tem restrições): step7 -> step2_register_details
            $_SESSION['onboarding_data']['selected_restrictions'] = [];
            header("Location: " . BASE_APP_URL . "/onboarding/step2_register_details.php");
        }
        exit();
    }
} else {
    // Se não é POST, usa o valor da sessão
    $has_restrictions_to_display = $has_restrictions_from_session;
}
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

$page_title = "Restrições Alimentares";
require_once '../includes/layout_header.php';
?>

    <div class="container">
        <div class="header-nav">
            <!-- Voltar para o passo anterior na NOVA ORDEM: step6_bowel -->
            <a href="<?php echo BASE_ASSET_URL; ?>/onboarding/step6_bowel.php" class="back-button"><</a>
            <a href="<?php echo BASE_ASSET_URL; ?>/onboarding/step6_bowel.php" class="back-button-text">Voltar</a>
        </div>
        <h1 class="page-title">Você possui alguma<br>restrição alimentar?</h1>
        <p class="page-subtitle">Eu só posso te indicar o que você pode ou prefere comer.</p>

        <form action="<?php echo BASE_ASSET_URL; ?>/onboarding/step7_restrictions_ask.php" method="POST">
             <?php if (!empty($error_message)): ?><p class="error-message text-center mb-2"><?php echo htmlspecialchars($error_message); ?></p><?php endif; ?>

            <label class="selectable-option <?php if($has_restrictions_to_display === true) echo 'selected'; ?>">
                <input type="radio" name="has_restrictions" value="1" <?php if($has_restrictions_to_display === true) echo 'checked'; ?> required>
                <span class="option-text">Possuo restrições alimentares</span>
            </label>

            <label class="selectable-option <?php if($has_restrictions_to_display === false && $has_restrictions_to_display !== 'not_set') echo 'selected'; ?>">
                <input type="radio" name="has_restrictions" value="0" <?php if($has_restrictions_to_display === false && $has_restrictions_to_display !== 'not_set') echo 'checked'; ?> required>
                <span class="option-text">Não possuo restrições alimentares</span>
            </label>

            <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Continuar</button>
        </form>
    </div>

<?php
require_once '../includes/layout_footer.php';
?>