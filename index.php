<?php
// Inclui os arquivos necessários no início
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Verifica se a requisição está vindo da WebView do app Capacitor
// A string 'wv' no User-Agent é um indicador comum da WebView do Android.
$is_capacitor_app = (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'wv') !== false);

// =========================================================================
// INÍCIO DA LÓGICA DE ROTEAMENTO
// =========================================================================

// SE NÃO FOR O APP (ou seja, um navegador de desktop/mobile normal)
if (!$is_capacitor_app) {
    // Mantém a lógica de redirecionamento original baseada em sessão PHP
    if (isLoggedIn()) {
        header("Location: " . BASE_APP_URL . "/main_app.php"); // Direciona para o app principal
    } else {
        header("Location: " . BASE_APP_URL . "/auth/login.php");
    }
    exit();
}

// SE FOR O APP CAPACITOR, NÃO FAZ NENHUM REDIRECIONAMENTO PHP.
// Em vez disso, renderiza a página de carregamento.
// O script.js vai assumir o controle a partir daqui.
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carregando ShapeFit...</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #121212; }
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #ff6b00; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="loader"></div>

    <script>
        // Define a variável BASE_APP_URL para o JS saber onde está a API remota
        const BASE_APP_URL = "https://www.appshapefit.com"; 
    </script>
    <script src="assets/js/script.js"></script> 
</body>
</html>