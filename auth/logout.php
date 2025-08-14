<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
// Não precisa de db.php, mas pode precisar de config.php para BASE_APP_URL
// e session_start()
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php'; // Para BASE_APP_URL

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

header("Location: " . BASE_APP_URL . "/auth/login.php");
exit();
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

// Página de redirecionamento puro, sem HTML.
?>