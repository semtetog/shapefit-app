<?php
// public_html/shapefit/includes/layout_header.php

// A sessão já deve ter sido iniciada no config.php, que é chamado antes
require_once __DIR__ . '/config.php';

// Garante que o token CSRF seja criado
if (empty($_SESSION['csrf_token'])) {
    try { 
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        // Fallback caso random_bytes falhe
        $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
    }
}

// Para cache busting do CSS
$main_css_file_path = APP_ROOT_PATH . '/assets/css/style.css';
$main_css_version = file_exists($main_css_file_path) ? filemtime($main_css_file_path) : time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    
    <link rel="stylesheet" href="<?php echo BASE_APP_URL; ?>/assets/css/style.css?v=<?php echo $main_css_version; ?>">
    
    <?php
    if (isset($extra_css) && is_array($extra_css)) {
        foreach ($extra_css as $css_file_name) {
            $extra_css_file_path = APP_ROOT_PATH . '/assets/css/' . htmlspecialchars(trim($css_file_name));
            if (file_exists($extra_css_file_path)) {
                echo '<link rel="stylesheet" href="' . BASE_APP_URL . '/assets/css/' . htmlspecialchars($css_file_name) . '?v=' . filemtime($extra_css_file_path) . '">';
            }
        }
    }
    ?>
    <link rel="icon" href="<?php echo BASE_APP_URL; ?>/favicon.ico" type="image/x-icon">

    <!-- 
    REMOVIDO: Scripts de bibliotecas (quagga, tesseract) movidos para o layout_footer.php
    para evitar bloqueio de renderização da página e garantir uma ordem de carregamento consistente. 
    -->
    
    <!-- VARIÁVEL GLOBAL PARA JAVASCRIPT (ESSENCIAL MANTER AQUI) -->
    <!-- Define a URL base para ser usada em chamadas AJAX nos scripts. -->
    <script>
        const BASE_APP_URL = "<?php echo rtrim(BASE_APP_URL, '/'); ?>";
    </script>
    
</head>
<body>
    <!-- O token CSRF deve estar disponível no DOM o mais cedo possível. -->
    <input type="hidden" id="csrf_token_main_app" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

    <?php if (isset($_SESSION['user_id'])): ?>
    <div id="loader-overlay"><div class="loader"></div></div>
    <?php endif; ?>
    <div id="alert-container"></div>
    
    <!-- Conteúdo principal da página começa aqui -->