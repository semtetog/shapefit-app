<?php
// --- INÍCIO DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---
require_once '../includes/config.php'; // Para BASE_APP_URL e BASE_ASSET_URL
require_once '../includes/auth.php';
requireLogin();

if (session_status() == PHP_SESSION_NONE) { session_start(); }

$errors = [];
// Carrega dados da sessão ou POST para pré-preenchimento
// Para os campos principais, se o POST existir, ele tem prioridade sobre a sessão para reexibir o que foi submetido.
$name = $_POST['name'] ?? ($_SESSION['onboarding_data']['name'] ?? '');
$uf_selected = $_POST['uf'] ?? ($_SESSION['onboarding_data']['uf'] ?? '');
$city_selected_php = $_POST['city'] ?? ($_SESSION['onboarding_data']['city'] ?? '');
$phone_ddd = $_POST['phone_ddd'] ?? ($_SESSION['onboarding_data']['phone_ddd'] ?? '');
$phone_number = $_POST['phone_number'] ?? ($_SESSION['onboarding_data']['phone_number'] ?? '');

// --- Lógica para buscar Estados (UFs) ---
$ufs_from_api = [];
$api_url_estados = "https://servicodados.ibge.gov.br/api/v1/localidades/estados?orderBy=nome";
$json_estados = @file_get_contents($api_url_estados);

if ($json_estados !== false) {
    $lista_estados_api = json_decode($json_estados, true);
    if (is_array($lista_estados_api)) {
        foreach ($lista_estados_api as $estado) {
            if (isset($estado['sigla']) && isset($estado['nome'])) {
                $ufs_from_api[] = ['sigla' => $estado['sigla'], 'nome' => $estado['nome']];
            }
        }
    }
}

if (empty($ufs_from_api)) {
    error_log("ShapeFit - Falha ao buscar UFs da API do IBGE, usando lista de fallback.");
    $ufs_from_api = [
        ['sigla' => 'AC', 'nome' => 'Acre'], ['sigla' => 'AL', 'nome' => 'Alagoas'], ['sigla' => 'AP', 'nome' => 'Amapá'],
        ['sigla' => 'AM', 'nome' => 'Amazonas'], ['sigla' => 'BA', 'nome' => 'Bahia'], ['sigla' => 'CE', 'nome' => 'Ceará'],
        ['sigla' => 'DF', 'nome' => 'Distrito Federal'], ['sigla' => 'ES', 'nome' => 'Espírito Santo'], ['sigla' => 'GO', 'nome' => 'Goiás'],
        ['sigla' => 'MA', 'nome' => 'Maranhão'], ['sigla' => 'MT', 'nome' => 'Mato Grosso'], ['sigla' => 'MS', 'nome' => 'Mato Grosso do Sul'],
        ['sigla' => 'MG', 'nome' => 'Minas Gerais'], ['sigla' => 'PA', 'nome' => 'Pará'], ['sigla' => 'PB', 'nome' => 'Paraíba'],
        ['sigla' => 'PR', 'nome' => 'Paraná'], ['sigla' => 'PE', 'nome' => 'Pernambuco'], ['sigla' => 'PI', 'nome' => 'Piauí'],
        ['sigla' => 'RJ', 'nome' => 'Rio de Janeiro'], ['sigla' => 'RN', 'nome' => 'Rio Grande do Norte'], ['sigla' => 'RS', 'nome' => 'Rio Grande do Sul'],
        ['sigla' => 'RO', 'nome' => 'Rondônia'], ['sigla' => 'RR', 'nome' => 'Roraima'], ['sigla' => 'SC', 'nome' => 'Santa Catarina'],
        ['sigla' => 'SP', 'nome' => 'São Paulo'], ['sigla' => 'SE', 'nome' => 'Sergipe'], ['sigla' => 'TO', 'nome' => 'Tocantins']
    ];
}
// --- Fim da Lógica para buscar Estados ---


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Os valores já foram pegos acima para pré-preenchimento,
    // mas garantimos que estamos validando os do POST.
    $name = trim($_POST['name'] ?? ''); // Pega do POST para validação
    $uf_selected = trim($_POST['uf'] ?? '');
    $city_selected_php = trim($_POST['city'] ?? '');
    $phone_ddd = trim($_POST['phone_ddd'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');


    if (empty($name)) $errors['name'] = "Nome é obrigatório.";
    if (empty($uf_selected)) $errors['uf'] = "UF é obrigatória.";
    if (empty($city_selected_php)) $errors['city'] = "Cidade é obrigatória.";
    
    // --- VALIDAÇÃO PARA TELEFONE OBRIGATÓRIO ---
    if (empty($phone_ddd)) {
        $errors['phone_ddd'] = "DDD é obrigatório.";
    } elseif (!preg_match('/^[0-9]{2}$/', $phone_ddd)) {
        $errors['phone_ddd'] = "DDD inválido (2 dígitos).";
    }

    if (empty($phone_number)) {
        $errors['phone_number'] = "Celular é obrigatório.";
    } elseif (!preg_match('/^[0-9]{8,9}$/', $phone_number)) {
        $errors['phone_number'] = "Celular inválido (8 ou 9 dígitos).";
    }
    // A validação de preenchimento conjunto não é mais necessária se ambos são obrigatórios.

    if (empty($errors)) {
        $_SESSION['onboarding_data']['name'] = $name;
        $_SESSION['onboarding_data']['uf'] = $uf_selected;
        $_SESSION['onboarding_data']['city'] = $city_selected_php;
        $_SESSION['onboarding_data']['phone_ddd'] = $phone_ddd;
        $_SESSION['onboarding_data']['phone_number'] = $phone_number;

        header("Location: " . BASE_APP_URL . "/onboarding/step3_personal_data.php");
        exit();
    }
}
// --- FIM DA LÓGICA PHP ESPECÍFICA DA PÁGINA ---

$page_title = "Finalizar Cadastro";
require_once '../includes/layout_header.php';
?>

    <div class="container">
        <div class="header-nav">
            <?php
            $link_voltar_cadastro = isset($_SESSION['onboarding_data']['has_dietary_restrictions']) && $_SESSION['onboarding_data']['has_dietary_restrictions'] === true
                           ? BASE_ASSET_URL . "/onboarding/step8_restrictions_select.php"
                           : BASE_ASSET_URL . "/onboarding/step7_restrictions_ask.php";
            ?>
            <a href="<?php echo $link_voltar_cadastro; ?>" class="back-button"><</a>
            <a href="<?php echo $link_voltar_cadastro; ?>" class="back-button-text">Voltar</a>
        </div>
        <h1 class="page-title">Finalize seu<br>cadastro</h1>
        <p class="page-subtitle">Complete seu cadastro para começar a usar o app.</p>

        <form action="<?php echo BASE_ASSET_URL; ?>/onboarding/step2_register_details.php" method="POST">
            <div class="form-group">
                <input type="text" id="name" name="name" class="form-control" placeholder="Nome" required value="<?php echo htmlspecialchars($name); ?>">
                <?php if (isset($errors['name'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['name']); ?></p><?php endif; ?>
            </div>

            <div style="display: flex; gap: 10px;">
                <div class="form-group" style="flex-basis: 90px; flex-grow: 0; flex-shrink: 0;">
                    <select id="uf_select" name="uf" class="form-control" required>
                        <option value="">UF</option>
                        <?php foreach ($ufs_from_api as $estado_data): ?>
                            <option value="<?php echo htmlspecialchars($estado_data['sigla']); ?>" <?php if ($uf_selected == $estado_data['sigla']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($estado_data['sigla']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['uf'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['uf']); ?></p><?php endif; ?>
                </div>
                <div class="form-group" style="flex-grow: 1;">
                    <select id="city_select" name="city" class="form-control" required disabled>
                        <option value="">Selecione um estado</option>
                    </select>
                    <input type="hidden" id="city_selected_on_load" value="<?php echo htmlspecialchars($city_selected_php); ?>">
                    <?php if (isset($errors['city'])): ?><p class="error-message"><?php echo htmlspecialchars($errors['city']); ?></p><?php endif; ?>
                </div>
            </div>

            <div class="phone-input-group">
                <div class="form-group">
                    <input type="text" id="phone_prefix" name="phone_prefix" class="form-control" value="+55" readonly>
                </div>
                <div class="form-group">
                    <input type="tel" id="phone_ddd" name="phone_ddd" class="form-control" placeholder="DDD" maxlength="2" pattern="[0-9]{2}" value="<?php echo htmlspecialchars($phone_ddd); ?>" required>
                </div>
                <div class="form-group">
                    <input type="tel" id="phone_number" name="phone_number" class="form-control" placeholder="Celular" maxlength="9" pattern="[0-9]{8,9}" value="<?php echo htmlspecialchars($phone_number); ?>" required>
                </div>
            </div>
            <!-- Exibir erros específicos para DDD e Celular -->
            <?php if (isset($errors['phone_ddd'])): ?>
                <p class="error-message" style="margin-top:-15px; margin-bottom:15px;"><?php echo htmlspecialchars($errors['phone_ddd']); ?></p>
            <?php elseif (isset($errors['phone_number'])): // Usar elseif para não mostrar ambos se o DDD já falhou ?>
                <p class="error-message" style="margin-top:-15px; margin-bottom:15px;"><?php echo htmlspecialchars($errors['phone_number']); ?></p>
            <?php elseif (isset($errors['phone'])): // Mantém o erro genérico 'phone' se ainda for usado para outros casos ?>
                <p class="error-message" style="margin-top:-15px; margin-bottom:15px;"><?php echo htmlspecialchars($errors['phone']); ?></p>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">Continuar</button>
        </form>
    </div>

<?php
require_once '../includes/layout_footer.php';
?>