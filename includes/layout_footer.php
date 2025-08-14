<?php
// public_html/shapefit/includes/layout_footer.php

if (!defined('BASE_ASSET_URL')) { define('BASE_ASSET_URL', '/shapefit'); }
if (!defined('APP_ROOT_PATH')) { define('APP_ROOT_PATH', dirname(__DIR__)); }
?>
    <!-- Conteúdo principal da página vem antes desta linha -->
    
    </div> <!-- Esta div fecha um .container ou .main-app-container que está no layout_header.php ou no corpo da página -->
    
    <!-- ======================================================= -->
    <!-- CENTRAL DE CARREGAMENTO DE SCRIPTS                      -->
    <!-- ======================================================= -->

    <!-- CDNs de Bibliotecas Externas -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/raphael/2.3.0/raphael.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/justgage/1.6.1/justgage.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- ADICIONADO: Scripts que estavam no header, agora centralizados aqui. -->
    <script src="https://cdn.jsdelivr.net/npm/quagga/dist/quagga.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js'></script>


    <?php
    // =======================================================
    // LÓGICA DE CARREGAMENTO DE SCRIPTS DA APLICAÇÃO (OTIMIZADA)
    // =======================================================

    // 1. Carrega o script global primeiro para que suas funções estejam disponíveis para os outros.
    $global_script_path = APP_ROOT_PATH . '/assets/js/script.js';
    if(file_exists($global_script_path)){
        $global_script_url = BASE_ASSET_URL . '/assets/js/script.js';
        $global_script_version = filemtime($global_script_path);
        echo '<script src="' . $global_script_url . '?v=' . $global_script_version . '" defer></script>' . "\n";
    }

    // 2. Carrega os scripts específicos da página (definidos na variável $extra_js).
    if (isset($extra_js) && is_array($extra_js)) {
        foreach ($extra_js as $js_file_name) {
            // Ignora o script.js aqui, pois já o carregamos primeiro
            if (trim($js_file_name) === 'script.js') continue;

            $js_file_path_on_server = APP_ROOT_PATH . '/assets/js/' . trim($js_file_name);
            if (file_exists($js_file_path_on_server)) {
                $js_url_for_browser = BASE_ASSET_URL . '/assets/js/' . htmlspecialchars(trim($js_file_name));
                $js_version = filemtime($js_file_path_on_server);
                // 'defer' garante que o HTML é carregado antes da execução do script.
                echo '<script src="' . $js_url_for_browser . '?v=' . $js_version . '" defer></script>' . "\n";
            }
        }
    }
    ?>
</body>
</html>