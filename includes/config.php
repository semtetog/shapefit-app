<?php
// Arquivo: public_html/shapefit/includes/config.php

// Iniciar sessão se ainda não iniciada (ESSENCIAL ANTES DE QUALQUER USO DE $_SESSION)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Definição de Caminhos Fundamentais ---
// Se config.php está em /.../public_html/shapefit/includes/config.php
// APP_ROOT_PATH será /.../public_html/shapefit/
if (!defined('APP_ROOT_PATH')) {
    define('APP_ROOT_PATH', dirname(__DIR__)); // dirname() sobe um nível a partir da pasta 'includes'
}

// --- Configurações de Ambiente ---
$is_local_environment = (
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
);
if (!defined('IS_LOCAL_ENV')) {
    define('IS_LOCAL_ENV', $is_local_environment);
}

// --- CONTROLE DE ERROS E LOGGING ---
// Este bloco deve vir DEPOIS da definição de APP_ROOT_PATH e IS_LOCAL_ENV

if (IS_LOCAL_ENV) {
    // Em ambiente de desenvolvimento, mostre todos os erros no navegador
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Em ambiente de produção, não mostre erros para o usuário no navegador
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    // Loga a maioria dos erros, exceto deprecated e strict, que podem ser muito verbosos
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Configurar para logar erros em um arquivo dentro da pasta da aplicação
ini_set('log_errors', 1); // Habilita o logging de erros em arquivo

// Define o caminho para o diretório de logs DENTRO da sua pasta /shapefit
$log_directory = APP_ROOT_PATH . '/logs'; // Ex: /caminho/para/public_html/shapefit/logs

// Tenta criar o diretório de logs se ele não existir
if (!is_dir($log_directory)) {
    // O @ suprime erros se a criação falhar (ex: por permissão)
    // 0755 dá permissão de leitura/execução para todos, escrita apenas para o dono (usuário do servidor web)
    if (!@mkdir($log_directory, 0755, true) && !is_dir($log_directory)) { // Verifica se realmente não foi criado
        // Não conseguiu criar o diretório, logue para o log padrão do sistema como fallback
        error_log("ALERTA: Não foi possível criar o diretório de logs em {$log_directory}. Verifique as permissões. Erros PHP podem não ser logados no arquivo customizado.");
        // Você pode optar por não definir um error_log customizado se o diretório não puder ser criado,
        // ou definir para um local alternativo, ou deixar o PHP usar o log padrão do servidor.
        // Por enquanto, vamos prosseguir, mas os erros podem não ir para o arquivo desejado.
    }
}

// Define o arquivo de log.
// Garanta que o servidor web (usuário Apache/Nginx) tenha permissão de escrita neste arquivo/pasta.
$error_log_file = $log_directory . '/shapefit_php_errors.log';
ini_set('error_log', $error_log_file);

// Opcional: Para testar se o logging está funcionando, você pode forçar um notice aqui,
// mas é melhor testar com um erro real de um script.
// trigger_error("Config.php: Logging de erro configurado para " . $error_log_file, E_USER_NOTICE);

// --- FIM DO CONTROLE DE ERROS E LOGGING ---


// --- URLs Base ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

// *** IMPORTANTE: Subdiretório da sua aplicação ShapeFit na URL ***
$app_subdirectory = ''; // Se acessa via http://applovechat.com/shapefit/

if (!defined('BASE_APP_URL')) {
    define('BASE_APP_URL', rtrim($protocol . $host . $app_subdirectory, '/'));
}
if (!defined('BASE_ASSET_URL')) {
    define('BASE_ASSET_URL', BASE_APP_URL); // Assets usarão a mesma URL base
}


// --- Configurações do Banco de Dados (SUBSTITUA COM SEUS DADOS REAIS) ---
if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1:3306'); 
if (!defined('DB_USER')) define('DB_USER', 'u785537399_shapefit'); 
if (!defined('DB_PASS')) define('DB_PASS', 'Gameroficial2*');
if (!defined('DB_NAME')) define('DB_NAME', 'u785537399_shapefit');
// --- Outras Configurações ---
if (!defined('SITE_NAME')) define('SITE_NAME', 'ShapeFit');
if (!defined('ASSET_VERSION')) define('ASSET_VERSION', '1.0.5'); // Incremente para cache busting

// Define o fuso horário padrão
date_default_timezone_set('America/Sao_Paulo');

// --- FIM DAS CONFIGURAÇÕES ---
?>