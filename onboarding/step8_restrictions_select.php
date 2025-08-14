<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php';
require_once '../includes/auth.php';
requireLogin();
require_once '../includes/db.php';

if (session_status() == PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['onboarding_data']['has_dietary_restrictions']) || $_SESSION['onboarding_data']['has_dietary_restrictions'] === false) {
    $_SESSION['onboarding_data']['selected_restrictions'] = [];
    header("Location: " . BASE_APP_URL . "/onboarding/step2_register_details.php");
    exit();
}

$selected_restrictions_from_session = $_SESSION['onboarding_data']['selected_restrictions'] ?? [];
$error_message = '';
$restriction_options = []; // Todas as opções do banco
$options_map_by_slug = []; // Mapa de slug para ID, para fácil acesso no JS

$result_options = $conn->query("SELECT id, name, slug FROM sf_dietary_restrictions_options ORDER BY name ASC");
if ($result_options) {
    while ($row_option = $result_options->fetch_assoc()) {
        $restriction_options[] = $row_option;
        $options_map_by_slug[$row_option['slug']] = $row_option['id']; // Cria o mapa
    }
} else {
    error_log("Error fetching restriction options: " . $conn->error);
    $error_message = "Erro ao carregar opções de restrição.";
}

// IDs das restrições chave (você pode buscar isso do DB se preferir, mas hardcoding slugs é comum)
$vegan_id = $options_map_by_slug['vegan'] ?? null;
$vegetarian_id = $options_map_by_slug['vegetarian'] ?? null;
$pescetarian_id = $options_map_by_slug['pescetarian'] ?? null;
$no_eggs_id = $options_map_by_slug['no_eggs'] ?? null;
$no_dairy_id = $options_map_by_slug['dairy_lactose_free'] ?? null;
$no_fish_id = $options_map_by_slug['no_fish_seafood'] ?? null;

// Lógica de pré-seleção se voltando para a página
if (!empty($selected_restrictions_from_session)) {
    if ($vegan_id && in_array($vegan_id, $selected_restrictions_from_session)) {
        if ($no_eggs_id && !in_array($no_eggs_id, $selected_restrictions_from_session)) $selected_restrictions_from_session[] = $no_eggs_id;
        if ($no_dairy_id && !in_array($no_dairy_id, $selected_restrictions_from_session)) $selected_restrictions_from_session[] = $no_dairy_id;
        if ($no_fish_id && !in_array($no_fish_id, $selected_restrictions_from_session)) $selected_restrictions_from_session[] = $no_fish_id;
        // Desmarcar vegetariano e pescetariano
        if ($vegetarian_id) $selected_restrictions_from_session = array_diff($selected_restrictions_from_session, [$vegetarian_id]);
        if ($pescetarian_id) $selected_restrictions_from_session = array_diff($selected_restrictions_from_session, [$pescetarian_id]);
    } elseif ($vegetarian_id && in_array($vegetarian_id, $selected_restrictions_from_session)) {
        if ($no_fish_id && !in_array($no_fish_id, $selected_restrictions_from_session)) $selected_restrictions_from_session[] = $no_fish_id;
        // Desmarcar pescetariano
        if ($pescetarian_id) $selected_restrictions_from_session = array_diff($selected_restrictions_from_session, [$pescetarian_id]);
    }
    // Garantir que não haja duplicatas
    $selected_restrictions_from_session = array_values(array_unique($selected_restrictions_from_session));
    $_SESSION['onboarding_data']['selected_restrictions'] = $selected_restrictions_from_session; // Atualiza a sessão
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $posted_restrictions = $_POST['restrictions'] ?? [];
    $valid_posted_restrictions = [];
    // ... (sua validação de $posted_restrictions permanece a mesma) ...
    foreach($posted_restrictions as $posted_id) {
        $isValid = false;
        foreach($restriction_options as $option) {
            if ($option['id'] == $posted_id) { $isValid = true; break; }
        }
        if ($isValid) { $valid_posted_restrictions[] = (int)$posted_id; }
    }

    // Aplicar lógica de implicação também no POST antes de salvar
    if ($vegan_id && in_array($vegan_id, $valid_posted_restrictions)) {
        if ($no_eggs_id && !in_array($no_eggs_id, $valid_posted_restrictions)) $valid_posted_restrictions[] = $no_eggs_id;
        if ($no_dairy_id && !in_array($no_dairy_id, $valid_posted_restrictions)) $valid_posted_restrictions[] = $no_dairy_id;
        if ($no_fish_id && !in_array($no_fish_id, $valid_posted_restrictions)) $valid_posted_restrictions[] = $no_fish_id;
        if ($vegetarian_id) $valid_posted_restrictions = array_diff($valid_posted_restrictions, [$vegetarian_id]);
        if ($pescetarian_id) $valid_posted_restrictions = array_diff($valid_posted_restrictions, [$pescetarian_id]);
    } elseif ($vegetarian_id && in_array($vegetarian_id, $valid_posted_restrictions)) {
        if ($no_fish_id && !in_array($no_fish_id, $valid_posted_restrictions)) $valid_posted_restrictions[] = $no_fish_id;
        if ($pescetarian_id) $valid_posted_restrictions = array_diff($valid_posted_restrictions, [$pescetarian_id]);
    }
    $valid_posted_restrictions = array_values(array_unique($valid_posted_restrictions));

    $_SESSION['onboarding_data']['selected_restrictions'] = $valid_posted_restrictions;
    header("Location: " . BASE_APP_URL . "/onboarding/step2_register_details.php");
    exit();
}
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

$page_title = "Selecione Suas Restrições";
require_once '../includes/layout_header.php';
?>

    <div class="container">
        <!-- ... seu HTML para header-nav, título ... -->
        <form action="<?php echo BASE_ASSET_URL; ?>/onboarding/step8_restrictions_select.php" method="POST">
            <?php if (!empty($error_message)): ?>
                <p class="error-message text-center mb-2"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>

            <?php if (empty($restriction_options) && empty($error_message)): ?>
                <p class="text-center">Nenhuma opção de restrição cadastrada no momento.</p>
            <?php else: ?>
                <?php foreach ($restriction_options as $option): ?>
                    <label class="selectable-option <?php if(in_array($option['id'], $selected_restrictions_from_session)) echo 'selected'; ?>"
                           data-restriction-id="<?php echo $option['id']; ?>"
                           data-restriction-slug="<?php echo htmlspecialchars($option['slug']); // Adiciona o slug como data attribute ?>">
                        <input type="checkbox"
                               name="restrictions[]"
                               value="<?php echo $option['id']; ?>"
                               <?php if(in_array($option['id'], $selected_restrictions_from_session)) echo 'checked'; ?>
                               data-slug="<?php echo htmlspecialchars($option['slug']); // Adiciona o slug ao input também ?>">
                        <span class="option-text"><?php echo htmlspecialchars($option['name']); ?></span>
                    </label>
                <?php endforeach; ?>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary" style="margin-top: 30px;">Continuar</button>
        </form>
    </div>

    <!-- Passar o mapa de slugs e IDs para o JavaScript -->
    <script>
        const restrictionSlugsToIds = <?php echo json_encode($options_map_by_slug); ?>;
    </script>

<?php
// layout_footer.php já inclui o script.js principal
require_once '../includes/layout_footer.php';
?>