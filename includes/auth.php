<?php
// No TOPO de auth.php, inclua config.php para ter acesso a BASE_APP_URL
// __DIR__ aqui refere-se à pasta 'includes'
require_once __DIR__ . '/config.php';

if (session_status() == PHP_SESSION_NONE) {
    // session_set_cookie_params deve ser chamado ANTES de session_start()
    // Idealmente, isso já está no seu config.php antes de session_start() lá
    // Se não, mova para lá ou garanta a ordem aqui.
    // session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']); 
    session_start();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        // Salvar a URL que o usuário tentou acessar para redirecionar após o login
        // $_SESSION['redirect_url_after_login'] = $_SERVER['REQUEST_URI']; // Opcional, mas útil

        // Usa BASE_APP_URL definida em config.php
        header("Location: " . BASE_APP_URL . "/auth/login.php");
        exit();
    }
}

function requireGuest() {
    if (isLoggedIn()) {
        // Decide para onde redirecionar se já estiver logado
        // dashboard.php ou main_app.php são opções comuns
        header("Location: " . BASE_APP_URL . "/main_app.php"); // Alterado para main_app.php como destino padrão
        exit();
    }
}

function regenerateSession() {
    // Evita loop de regeneração se já foi feito nesta requisição
    // Esta lógica de OBSOLETE/EXPIRES é uma forma de tentar lidar com a perda de sessão
    // durante a regeneração, mas pode ser simplificada.
    // Uma regeneração simples é geralmente suficiente:
    if (isset($_SESSION['SESSION_REGENERATED_RECENTLY']) && $_SESSION['SESSION_REGENERATED_RECENTLY'] > time() - 5) {
        return; // Já regenerada nos últimos 5 segundos
    }

    session_regenerate_id(true); // true para deletar o arquivo da sessão antiga
    $_SESSION['SESSION_REGENERATED_RECENTLY'] = time(); // Marca que foi regenerada
}

// Opcional: Adicionar no início de scripts que precisam de sessão segura, após o login
// if (isset($_SESSION['user_id']) && (!isset($_SESSION['LAST_ACTIVITY']) || (time() - $_SESSION['LAST_ACTIVITY'] > 1800))) {
//    // última atividade há mais de 30 minutos
//    regenerateSession();
// }
// $_SESSION['LAST_ACTIVITY'] = time(); // atualiza o timestamp da última atividade
?>