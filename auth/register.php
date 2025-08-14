<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php'; // Caminho relativo
require_once APP_ROOT_PATH . '/includes/db.php'; // Usando APP_ROOT_PATH
require_once APP_ROOT_PATH . '/includes/auth.php';
requireGuest();

$errors = [];
$submitted_name = '';
$submitted_email = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verificar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Erro de validação. Tente novamente.";
        error_log("CSRF token mismatch on register page.");
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $submitted_name = $name;
        $submitted_email = $email;

        if (empty($name)) $errors['name'] = "Nome é obrigatório.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Email inválido.";
        } else {
            $stmt_check_email = $conn->prepare("SELECT id FROM sf_users WHERE email = ?");
            if ($stmt_check_email) {
                $stmt_check_email->bind_param("s", $email);
                $stmt_check_email->execute();
                $result_check_email = $stmt_check_email->get_result();
                if ($result_check_email->num_rows > 0) {
                    $errors['email'] = "Este email já está cadastrado.";
                }
                $stmt_check_email->close();
            } else {
                $errors['form'] = "Erro ao verificar email. Tente mais tarde.";
                error_log("Register error - prepare check_email failed: " . $conn->error);
            }
        }
        if (empty($password) || strlen($password) < 6) {
            $errors['password'] = "Senha deve ter pelo menos 6 caracteres.";
        }
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = "As senhas não coincidem.";
        }

        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            // Define onboarding_complete como FALSE por padrão ao registrar
            $stmt_insert_user = $conn->prepare("INSERT INTO sf_users (name, email, password_hash, onboarding_complete) VALUES (?, ?, ?, FALSE)");
            if ($stmt_insert_user) {
                $stmt_insert_user->bind_param("sss", $name, $email, $password_hash);
                if ($stmt_insert_user->execute()) {
                    regenerateSession();
                    $new_user_id = $stmt_insert_user->insert_id;
                    $_SESSION['user_id'] = $new_user_id;
                    $_SESSION['email'] = $email;
                    $_SESSION['user_name'] = $name;
                    $_SESSION['onboarding_data'] = ['name' => $name, 'email' => $email]; // Para pré-preencher no onboarding

                    // Limpar token CSRF antigo e gerar um novo
                    unset($_SESSION['csrf_token']);

                    header("Location: " . BASE_APP_URL . "/onboarding/step1_intro.php");
                    exit();
                } else {
                    $errors['form'] = "Erro ao registrar. Tente novamente.";
                    error_log("Register error - execute insert_user failed: " . $stmt_insert_user->error);
                }
                $stmt_insert_user->close();
            } else {
                $errors['form'] = "Erro no sistema de registro. Tente mais tarde.";
                error_log("Register error - prepare insert_user failed: " . $conn->error);
            }
        }
    }
}

// Gerar CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token_for_html = $_SESSION['csrf_token'];
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

$page_title = "Cadastro";
require_once APP_ROOT_PATH . '/includes/layout_header.php'; // Usando APP_ROOT_PATH
?>

<div class="container"> <!-- Não precisa de text-center aqui, a logo e títulos já têm -->
    <img src="<?php echo BASE_ASSET_URL; ?>/assets/images/SHAPE-FIT-LOGO.png" alt="Shape Fit Logo" class="login-logo">
    <!-- Ajuste o caminho da logo acima se estiver diferente -->

    <h1 class="page-title text-center" style="margin-top: 0; margin-bottom: 10px;">Criar Conta</h1>
    <p class="page-subtitle text-center">Comece sua jornada no Shape Fit!</p>

    <form action="<?php echo BASE_APP_URL; ?>/auth/register.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_for_html; ?>">
        <?php if (isset($errors['form'])): ?><p class="error-message text-center mb-2"><?php echo htmlspecialchars($errors['form']); ?></p><?php endif; ?>
        
        <div class="form-group">
            <input type="text" name="name" id="name" class="form-control" placeholder="Nome completo" required value="<?php echo htmlspecialchars($submitted_name); ?>">
            <?php if (isset($errors['name'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['name']); ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <input type="email" name="email" id="email" class="form-control" placeholder="Email" required value="<?php echo htmlspecialchars($submitted_email); ?>">
            <?php if (isset($errors['email'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['email']); ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <input type="password" name="password" id="password" class="form-control" placeholder="Senha (mín. 6 caracteres)" required>
            <?php if (isset($errors['password'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['password']); ?></p><?php endif; ?>
        </div>
        <div class="form-group">
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirme a senha" required>
            <?php if (isset($errors['confirm_password'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['confirm_password']); ?></p><?php endif; ?>
        </div>
        
        <button type="submit" class="btn btn-primary mt-2">Cadastrar</button>
        
        <p class="text-center mt-3" style="color: var(--secondary-text-color);">
            Já tem uma conta? <a href="<?php echo BASE_APP_URL; ?>/auth/login.php" style="color: var(--accent-orange); text-decoration: none;">Faça login</a>
        </p>
    </form>
</div>

<?php
require_once APP_ROOT_PATH . '/includes/layout_footer.php'; // Usando APP_ROOT_PATH
?>