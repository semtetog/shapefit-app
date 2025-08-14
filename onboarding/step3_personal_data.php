<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$errors = [];
$dob = $_SESSION['onboarding_data']['dob'] ?? '';
$gender = $_SESSION['onboarding_data']['gender'] ?? '';
$height_cm = $_SESSION['onboarding_data']['height_cm'] ?? '';
// Para o peso, vamos pegar o valor formatado se existir, senão o valor cru
$weight_display = isset($_SESSION['onboarding_data']['weight_kg']) ? str_replace('.', ',', $_SESSION['onboarding_data']['weight_kg']) : '';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $height_cm = trim($_POST['height_cm'] ?? '');
    $weight_input = trim($_POST['weight_kg'] ?? ''); // Peso como o usuário digitou
    $weight_display = $weight_input; // Para re-exibir no campo em caso de erro

    // Validação do peso: converter vírgula para ponto ANTES de validar/salvar
    $weight_kg_numeric = str_replace(',', '.', $weight_input);

    if (empty($dob)) { $errors['dob'] = "Data de nascimento é obrigatória."; }
    else {
        $d = DateTime::createFromFormat('Y-m-d', $dob);
        if (!$d || $d->format('Y-m-d') !== $dob || new DateTime() < $d) {
            $errors['dob'] = "Data de nascimento inválida ou futura.";
        }
    }
    if (empty($gender) || !in_array($gender, ['male', 'female', 'other'])) { $errors['gender'] = "Sexo é obrigatório."; }
    if (empty($height_cm) || !filter_var($height_cm, FILTER_VALIDATE_INT, ["options" => ["min_range" => 50, "max_range" => 300]])) {
        $errors['height_cm'] = "Altura inválida (entre 50 e 300 cm).";
    }
    // Validar $weight_kg_numeric
    if (empty($weight_input)) { // Verifica se o campo original estava vazio
        $errors['weight_kg'] = "Peso é obrigatório.";
    } elseif (!is_numeric($weight_kg_numeric) || !filter_var($weight_kg_numeric, FILTER_VALIDATE_FLOAT, ["options" => ["min_range" => 20, "max_range" => 500]])) {
        $errors['weight_kg'] = "Peso inválido (entre 20 e 500 kg). Use números.";
    }


    if (empty($errors)) {
        $_SESSION['onboarding_data']['dob'] = $dob;
        $_SESSION['onboarding_data']['gender'] = $gender;
        $_SESSION['onboarding_data']['height_cm'] = (int)$height_cm;
        $_SESSION['onboarding_data']['weight_kg'] = number_format((float)$weight_kg_numeric, 2, '.', ''); // Salva com ponto e 2 decimais

        // Próximo passo na NOVA ORDEM: step3_personal_data -> process_onboarding.php
        header("Location: " . BASE_APP_URL . "/onboarding/process_onboarding.php");
        exit();
    }
}
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

$page_title = "Dados Pessoais";
require_once '../includes/layout_header.php';
?>

    <div class="container">
        <div class="header-nav">
            <!-- Voltar para o passo anterior na NOVA ORDEM: step2_register_details -->
            <a href="<?php echo BASE_ASSET_URL; ?>/onboarding/step2_register_details.php" class="back-button"><</a>
            <a href="<?php echo BASE_ASSET_URL; ?>/onboarding/step2_register_details.php" class="back-button-text">Voltar</a>
        </div>
        <h1 class="page-title">Agora, alguns<br>dados pessoais</h1>
        <p class="page-subtitle">Estas informações são essenciais para calcular sua meta.</p>

        <?php if(isset($_SESSION['onboarding_errors']) && !empty($_SESSION['onboarding_errors'])): ?>
            <div style="background-color: #5c2323; color:white; padding:10px; border-radius:5px; margin-bottom:15px;">
                <strong>Ocorreram os seguintes erros no processamento:</strong><br>
                <?php foreach($_SESSION['onboarding_errors'] as $err): ?>
                    - <?php echo htmlspecialchars($err); ?><br>
                <?php endforeach; ?>
                Por favor, verifique os dados e tente novamente.
            </div>
            <?php unset($_SESSION['onboarding_errors']); // Limpa após exibir ?>
        <?php endif; ?>
        <?php if(isset($_GET['validation_failed']) || isset($_GET['processing_error'])): ?>
             <p class="error-message text-center mb-2">Alguns dados parecem incorretos ou houve um problema ao salvar. Por favor, verifique e tente novamente.</p>
        <?php endif; ?>


        <form action="<?php echo BASE_ASSET_URL; ?>/onboarding/step3_personal_data.php" method="POST">
          <!-- Dentro do <form> -->
<div class="form-group">
    <input type="text" 
           id="dob_input"
           name="dob"
           class="form-control"
           placeholder="Qual é sua data de nascimento?"
           value="<?php echo htmlspecialchars($dob); ?>" 
           max="<?php echo date('Y-m-d'); ?>"
           required>
    <?php if (isset($errors['dob'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['dob']); ?></p><?php endif; ?>
</div>

            <div class="form-group">
                <select id="gender" name="gender" class="form-control" required>
                    <option value="" disabled <?php if(empty($gender)) echo 'selected';?>>Sexo</option>
                    <option value="male" <?php if($gender == 'male') echo 'selected';?>>Masculino</option>
                    <option value="female" <?php if($gender == 'female') echo 'selected';?>>Feminino</option>
                    <option value="other" <?php if($gender == 'other') echo 'selected';?>>Outro</option>
                </select>
                <?php if (isset($errors['gender'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['gender']); ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <input type="number" id="height_cm" name="height_cm" class="form-control" placeholder="Quanto você mede? (cm)" required min="50" max="300" step="1" value="<?php echo htmlspecialchars($height_cm); ?>">
                <?php if (isset($errors['height_cm'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['height_cm']); ?></p><?php endif; ?>
            </div>

            <div class="form-group">
                <input type="text" id="weight_kg" name="weight_kg" class="form-control" placeholder="Quanto você pesa? (kg)" required pattern="[0-9]+([,\.][0-9]{1,2})?" title="Use números, ex: 70 ou 70,5" value="<?php echo htmlspecialchars($weight_display); // Exibe o valor como digitado ou da sessão ?>">
                <small style="color: var(--secondary-text-color); font-size: 0.8em;">Use vírgula para decimais, ex: 70,5</small>
                <?php if (isset($errors['weight_kg'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['weight_kg']); ?></p><?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary">Continuar</button>
        </form>
    </div>
    <!-- Removido o script JS inline, pois o PHP agora lida com a conversão do peso -->

<?php
require_once '../includes/layout_footer.php';
?>