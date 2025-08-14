<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php'; // Caminho relativo para config.php
require_once APP_ROOT_PATH . '/includes/db.php'; // Usando APP_ROOT_PATH para consistência
require_once APP_ROOT_PATH . '/includes/auth.php';
requireGuest();

$errors = [];
$submitted_email = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verificar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = "Erro de validação. Tente novamente.";
        // Opcional: logar a tentativa inválida
        error_log("CSRF token mismatch on login page.");
    } else {
        $email = trim($_POST['email'] ?? '');
        $submitted_email = $email;
        $password = $_POST['password'] ?? '';

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Por favor, insira um email válido.";
        }
        if (empty($password)) {
            $errors['password'] = "Por favor, insira sua senha.";
        }

        if (empty($errors)) {
            $stmt_login = $conn->prepare("SELECT id, password_hash, onboarding_complete, name FROM sf_users WHERE email = ?");
            if ($stmt_login) {
                $stmt_login->bind_param("s", $email);
                $stmt_login->execute();
                $result_login = $stmt_login->get_result();
                $user_login = $result_login->fetch_assoc();
                $stmt_login->close();

                if ($user_login && password_verify($password, $user_login['password_hash'])) {
                    regenerateSession(); // Regenera ID da sessão para segurança
                    $_SESSION['user_id'] = $user_login['id'];
                    $_SESSION['email'] = $email;
                    $_SESSION['user_name'] = $user_login['name']; // Salva o nome do usuário na sessão

                    // Limpar token CSRF antigo e gerar um novo para a próxima requisição
                    unset($_SESSION['csrf_token']); // Remove o token usado
                    // O layout_header irá gerar um novo se não existir

                    if ($user_login['onboarding_complete']) {
                        header("Location: " . BASE_APP_URL . "/main_app.php"); // Redireciona para main_app após login
                    } else {
                        $_SESSION['onboarding_data'] = ['name' => $user_login['name'], 'email' => $email]; // Passa dados para onboarding
                        header("Location: " . BASE_APP_URL . "/onboarding/step1_intro.php");
                    }
                    exit();
                } else {
                    $errors['form'] = "Email ou senha incorretos.";
                }
            } else {
                $errors['form'] = "Erro no sistema de login. Tente mais tarde.";
                error_log("Login error - prepare failed: " . $conn->error);
            }
        }
    }
}

// Gerar CSRF token para o formulário (layout_header já faz isso, mas podemos garantir aqui se ele ainda não foi incluído)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token_for_html = $_SESSION['csrf_token'];
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

$page_title = "Login";
require_once APP_ROOT_PATH . '/includes/layout_header.php'; // Usando APP_ROOT_PATH
?>

<div class="container text-center">
    <img src="<?php echo BASE_ASSET_URL; ?>/assets/images/SHAPE-FIT-LOGO.png" alt="Shape Fit Logo" class="login-logo">
    <!-- Ajuste o caminho da logo acima se estiver diferente. Assumi que está em /assets/images/ -->

    <p class="page-subtitle text-center" style="margin-top: 10px; margin-bottom: 30px;">Acesse sua conta</p>

    <form action="<?php echo BASE_APP_URL; ?>/auth/login.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token_for_html; ?>">
        <?php if (isset($errors['form'])): ?><p class="error-message text-center mb-2"><?php echo htmlspecialchars($errors['form']); ?></p><?php endif; ?>
        
        <div class="form-group">
            <input type="email" name="email" id="email" class="form-control" placeholder="Email" required value="<?php echo htmlspecialchars($submitted_email); ?>">
            <?php if (isset($errors['email'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['email']); ?></p><?php endif; ?>
        </div>
        
        <div class="form-group">
            <input type="password" name="password" id="password" class="form-control" placeholder="Senha" required>
            <?php if (isset($errors['password'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['password']); ?></p><?php endif; ?>
        </div>
        
        <button type="submit" class="btn btn-primary mt-2">Entrar</button>
        
        <p class="text-center mt-3" style="color: var(--secondary-text-color);">
            Não tem uma conta? <a href="<?php echo BASE_APP_URL; ?>/auth/register.php" style="color: var(--accent-orange); text-decoration: none;">Cadastre-se</a>
        </p>
    </form>
</div>

<?php
require_once APP_ROOT_PATH . '/includes/layout_footer.php'; // Usando APP_ROOT_PATH
?>