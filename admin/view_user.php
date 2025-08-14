<?php
// admin/view_user.php (VERSÃO COM DESIGN PROFISSIONAL)

// --- INCLUDES E AUTENTICAÇÃO ---
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/includes/auth_admin.php';
$conn = require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/functions_admin.php';
requireAdminLogin();

// --- VALIDAÇÃO E BUSCA DE DADOS ---
$user_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$user_id) {
    header("Location: users.php");
    exit;
}

$user_data = getUserProfileData($conn, $user_id);
if (!$user_data) {
    $error_message = "Erro: Paciente com o ID " . htmlspecialchars($user_id) . " não foi encontrado ou não possui um perfil.";
    $page_title = "Erro";
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container"><p class="error-message">' . $error_message . '</p></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// --- DADOS PARA AS ABAS ---
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$daysToShow = 7;
$startDate = date('Y-m-d', strtotime($endDate . " -" . ($daysToShow - 1) . " days"));
$meal_history = getGroupedMealHistory($conn, $user_id, $startDate, $endDate);

// =========================================================================
//  NOVA LÓGICA PARA BUSCAR O GRÁFICO DE PESO (A CORREÇÃO FINAL)
// =========================================================================

// 1. Busca todos os registros da tabela de histórico, como antes.
$stmt_weight_history = $conn->prepare("SELECT date_recorded, weight_kg FROM sf_user_weight_history WHERE user_id = ? ORDER BY date_recorded ASC");
$stmt_weight_history->bind_param("i", $user_id);
$stmt_weight_history->execute();
$history_result = $stmt_weight_history->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_weight_history->close();

// 2. Pega o peso ATUAL que está no perfil do usuário.
//    A variável $user_data já foi carregada no início do arquivo.
$current_weight_from_profile = (float)($user_data['weight_kg'] ?? 0);
$all_weights = [];

// 3. Adiciona os pesos do histórico ao nosso array mestre.
//    Usamos a data como chave para evitar duplicatas.
foreach ($history_result as $row) {
    $date_key = date('Y-m-d', strtotime($row['date_recorded']));
    $all_weights[$date_key] = (float)$row['weight_kg'];
}

// 4. Adiciona o peso ATUAL do perfil ao array mestre.
//    Se já houver um registro para a data de hoje no histórico, esta linha vai
//    simplesmente sobrescrever com o valor mais recente, o que é o correto.
//    Se não houver, ela adiciona o peso atual como um novo ponto.
if ($current_weight_from_profile > 0) {
    $today_key = date('Y-m-d');
    $all_weights[$today_key] = $current_weight_from_profile;
}

// 5. Ordena o array pela data (chave) para garantir que o gráfico fique cronológico.
ksort($all_weights);

// 6. Finalmente, formata os dados para o JavaScript.
$weight_chart_data = ['labels' => [], 'data' => []];
foreach ($all_weights as $date => $weight) {
    $weight_chart_data['labels'][] = date('d/m/Y', strtotime($date));
    $weight_chart_data['data'][] = $weight;
}

// =========================================================================
//  FIM DA NOVA LÓGICA
// =========================================================================

$stmt_photos = $conn->prepare("SELECT date_recorded, photo_front, photo_side, photo_back FROM sf_user_measurements WHERE user_id = ? AND (photo_front IS NOT NULL OR photo_side IS NOT NULL OR photo_back IS NOT NULL) ORDER BY date_recorded DESC");
$stmt_photos->bind_param("i", $user_id);
$stmt_photos->execute();
$photo_history = $stmt_photos->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_photos->close();

// --- PREPARAÇÃO DE DADOS PARA EXIBIÇÃO ---
$page_slug = 'users';
$page_title = 'Dossiê: ' . htmlspecialchars($user_data['name']);
$extra_js = ['user_view_logic.js'];

// ARRAYS DE MAPEAMENTO PARA TRADUÇÃO
$objective_names = ['lose_fat' => 'Emagrecimento', 'gain_muscle' => 'Hipertrofia', 'maintain_weight' => 'Manter Peso'];
$activity_level_names = [
    'sedentary' => 'Sedentário', 'sedentary_to_1x' => 'Muito Leve (1x/sem)', 'light_2_3x' => 'Leve (2-3x/sem)',
    'moderate_3_5x' => 'Moderado (3-5x/sem)', 'intense_5x_plus' => 'Intenso (5x+/sem)', 'athlete' => 'Atleta'
];
$gender_names = ['male' => 'Masculino', 'female' => 'Feminino', 'other' => 'Outro'];
$bowel_movement_names = ['daily' => 'Diariamente', 'alternate_days' => 'Dias alternados', 'every_3_plus_days' => 'A cada 3+ dias'];
$meal_type_names = [
    'breakfast' => 'Café da Manhã', 'morning_snack' => 'Lanche da Manhã', 'lunch' => 'Almoço',
    'afternoon_snack' => 'Lanche da Tarde', 'dinner' => 'Jantar', 'supper' => 'Ceia',
    'pre_workout' => 'Pré-Treino', 'post_workout' => 'Pós-Treino'
];

// CÁLCULOS E FORMATAÇÃO DE DADOS
$age_years = !empty($user_data['dob']) ? calculateAge($user_data['dob']) : 'N/A';
$total_daily_calories_goal = calculateTargetDailyCalories($user_data['gender'] ?? 'female', (float)($user_data['weight_kg'] ?? 0), (int)($user_data['height_cm'] ?? 0), $age_years, $user_data['activity_level'] ?? 'sedentary', $user_data['objective'] ?? 'maintain_weight');
$macros_goal = calculateMacronutrients($total_daily_calories_goal, $user_data['objective'] ?? 'maintain_weight');
$water_goal_data = getWaterIntakeSuggestion((float)($user_data['weight_kg'] ?? 0));
$full_phone = !empty($user_data['phone_ddd']) && !empty($user_data['phone_number']) ? '(' . htmlspecialchars($user_data['phone_ddd']) . ') ' . htmlspecialchars($user_data['phone_number']) : 'Não informado';
$location = !empty($user_data['city']) && !empty($user_data['uf']) ? htmlspecialchars($user_data['city']) . ' - ' . htmlspecialchars($user_data['uf']) : 'Não informado';

// LÓGICA DE AVATAR
$avatar_html = '';
if (!empty($user_data['profile_image_filename'])) {
    $original_path = APP_ROOT_PATH . '/assets/images/users/' . $user_data['profile_image_filename'];
    if (file_exists($original_path)) {
        $avatar_url = BASE_ASSET_URL . '/assets/images/users/' . htmlspecialchars($user_data['profile_image_filename']);
        $avatar_html = '<img src="' . $avatar_url . '" alt="Foto de ' . htmlspecialchars($user_data['name']) . '" class="profile-avatar-large">';
    }
}
if (empty($avatar_html)) {
    $name_parts = explode(' ', trim($user_data['name']));
    $initials = count($name_parts) > 1 ? strtoupper(substr($name_parts[0], 0, 1) . substr(end($name_parts), 0, 1)) : (!empty($name_parts[0]) ? strtoupper(substr($name_parts[0], 0, 2)) : '??');
    $bgColor = '#' . substr(md5($user_data['name']), 0, 6);
    $avatar_html = '<div class="initials-avatar large" style="background-color: ' . $bgColor . ';">' . $initials . '</div>';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- CABEÇALHO COM DADOS DE CONTATO -->
<div class="view-user-header">
    <div class="user-main-info">
        <?php echo $avatar_html; ?>
        <div class="user-contact-details">
            <h2><?php echo htmlspecialchars($user_data['name']); ?></h2>
            <p><i class="fas fa-envelope icon-sm"></i> <?php echo htmlspecialchars($user_data['email']); ?></p>
            <p><i class="fas fa-phone-alt icon-sm"></i> <?php echo $full_phone; ?></p>
            <p><i class="fas fa-map-marker-alt icon-sm"></i> <?php echo $location; ?></p>
        </div>
    </div>
</div>

<!-- GRID COM CARDS DE INFORMAÇÕES DETALHADAS -->
<div class="details-grid-3-cols">
    <!-- CARD 1: METAS -->
    <div class="dashboard-card">
        <h3>Meta Calórica e Macros</h3>
        <div class="meta-card-main">
            <span class="meta-value"><?php echo $total_daily_calories_goal; ?></span>
            <span class="meta-label">Kcal / dia</span>
        </div>
        <div class="meta-card-macros">
            <div><span><?php echo $macros_goal['carbs_g']; ?>g</span>Carboidratos</div>
            <div><span><?php echo $macros_goal['protein_g']; ?>g</span>Proteínas</div>
            <div><span><?php echo $macros_goal['fat_g']; ?>g</span>Gorduras</div>
        </div>
    </div>

    <!-- CARD 2: DADOS FÍSICOS -->
    <div class="dashboard-card">
        <h3>Dados Físicos</h3>
        <div class="physical-data-grid">
            <div class="data-item"><i class="fas fa-birthday-cake icon"></i><label>Idade</label><span><?php echo $age_years; ?> anos</span></div>
            <div class="data-item"><i class="fas fa-weight icon"></i><label>Peso Atual</label><span><?php echo number_format((float)($user_data['weight_kg'] ?? 0), 1, ',', '.'); ?> kg</span></div>
            <div class="data-item"><i class="fas fa-ruler-vertical icon"></i><label>Altura</label><span><?php echo htmlspecialchars($user_data['height_cm'] ?? 'N/A'); ?> cm</span></div>
            <div class="data-item"><i class="fas fa-venus-mars icon"></i><label>Gênero</label><span><?php echo $gender_names[$user_data['gender']] ?? 'Não informado'; ?></span></div>
        </div>
    </div>

    <!-- CARD 3: PLANO E PREFERÊNCIAS -->
    <div class="dashboard-card">
        <h3>Plano e Preferências</h3>
         <div class="physical-data-grid">
            <div class="data-item"><i class="fas fa-bullseye icon"></i><label>Objetivo</label><span><?php echo $objective_names[$user_data['objective']] ?? 'Não informado'; ?></span></div>
            <div class="data-item"><i class="fas fa-running icon"></i><label>Nível Atividade</label><span><?php echo $activity_level_names[$user_data['activity_level']] ?? 'Não informado'; ?></span></div>
            <div class="data-item"><i class="fas fa-tint icon"></i><label>Meta de Água</label><span><?php echo $water_goal_data['cups']; ?> copos/dia</span></div>
            <div class="data-item"><i class="fas fa-poo icon"></i><label>Intestino</label><span><?php echo $bowel_movement_names[$user_data['bowel_movement']] ?? 'Não informado'; ?></span></div>
            <div class="data-item wide">
                <i class="fas fa-ban icon"></i><label>Restrições</label>
                <span class="wrap-text">
                    <?php 
                        if (!empty($user_data['restrictions_list'])) {
                            echo htmlspecialchars(implode(', ', $user_data['restrictions_list']));
                        } else {
                            echo 'Nenhuma.';
                        }
                    ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- SISTEMA DE ABAS -->
<div class="tabs-container">
    <div class="tab-link active" data-tab="diary">Diário</div>
    <div class="tab-link" data-tab="progress">Progresso</div>
    <div class="tab-link" data-tab="measurements">Medidas</div>
</div>

<!-- CONTEÚDO DAS ABAS (seu código original, sem alterações) -->
<div id="tab-diary" class="tab-content active">
    <div class="dashboard-card">
        <div class="card-header-flex">
            <h3>Histórico do Diário</h3>
            <form method="GET" class="date-filter-form">
                <input type="hidden" name="id" value="<?php echo $user_id; ?>">
                <label for="end_date">Mostrar semana terminando em:</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" onchange="this.form.submit()">
            </form>
        </div>
        <div class="diary-history-container">
            <?php if (empty($meal_history)): ?>
                <p class="empty-state">O paciente ainda não registrou nenhuma refeição neste período.</p>
            <?php else: ?>
                <?php foreach ($meal_history as $date => $meals): ?>
                    <div class="diary-day-group">
                        <h4 class="day-header"><?php echo date('d/m/Y', strtotime($date)); ?></h4>
                        <?php foreach ($meals as $meal_type_slug => $items): 
                            $total_kcal = array_sum(array_column($items, 'kcal_consumed'));
                            $total_prot = array_sum(array_column($items, 'protein_consumed_g'));
                            $total_carb = array_sum(array_column($items, 'carbs_consumed_g'));
                            $total_fat  = array_sum(array_column($items, 'fat_consumed_g'));
                        ?>
                            <div class="meal-card">
                                <div class="meal-card-header">
                                    <h5><?php echo $meal_type_names[$meal_type_slug] ?? ucfirst($meal_type_slug); ?></h5>
                                    <div class="meal-card-totals">
                                        <strong><?php echo round($total_kcal); ?> kcal</strong>
                                        (P:<?php echo round($total_prot); ?>g, C:<?php echo round($total_carb); ?>g, G:<?php echo round($total_fat); ?>g)
                                    </div>
                                </div>
                                <ul class="food-item-list">
                                    <?php foreach ($items as $item): ?>
                                        <li>
                                            <span class="food-name"><?php echo htmlspecialchars($item['food_name']); ?></span>
                                            <span class="food-quantity"><?php echo htmlspecialchars($item['quantity_display']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="tab-progress" class="tab-content">
    <div class="progress-grid">
        <div class="dashboard-card weight-history-card">
            <h4>Histórico de Peso</h4>

            <?php // CONDIÇÃO CORRIGIDA: Verifica se existe PELO MENOS UM registro de peso. ?>
            <?php if (empty($weight_chart_data['data'])): ?>
                
                <p class="empty-state">O paciente ainda não registrou nenhum peso.</p>
            
            <?php else: ?>
                
                <canvas id="weightHistoryChart"></canvas>
                
                <?php // MENSAGEM DE AJUDA: Aparece se só tiver um ponto de dado. ?>
                <?php if (count($weight_chart_data['data']) < 2): ?>
                    <p class="info-message-chart">Aguardando o próximo registro de peso para traçar a linha de progresso.</p>
                <?php endif; ?>

            <?php endif; ?>
        </div>
        <div class="dashboard-card photos-history-card">
            <h4>Fotos de Progresso</h4>
            <?php if (empty($photo_history)): ?>
                <p class="empty-state">Nenhuma foto de progresso encontrada.</p>
            <?php else: ?>
                <div class="photo-gallery">
                    <?php foreach($photo_history as $photo_set): ?>
                        <?php foreach(['photo_front' => 'Frente', 'photo_side' => 'Lado', 'photo_back' => 'Costas'] as $photo_type => $label): ?>
                            <?php if(!empty($photo_set[$photo_type])): ?>
                                <a href="<?php echo BASE_ASSET_URL . '/assets/images/progress/' . htmlspecialchars($photo_set[$photo_type]); ?>" target="_blank" class="photo-item">
                                    <img src="<?php echo BASE_ASSET_URL . '/assets/images/progress/' . htmlspecialchars($photo_set[$photo_type]); ?>" loading="lazy" alt="Foto de progresso - <?php echo $label; ?>">
                                    <div class="photo-date">
                                        <span><?php echo $label; ?></span>
                                        <span><?php echo date('d/m/Y', strtotime($photo_set['date_recorded'])); ?></span>
                                    </div>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="tab-measurements" class="tab-content">
    <div class="dashboard-card">
        <h3>Histórico de Medidas Corporais</h3>
        <p class="empty-state">Funcionalidade a ser implementada.</p>
    </div>
</div>

<script>
// Passa os dados do histórico de peso para o JavaScript no formato correto
const userViewData = {
    weightHistory: <?php echo json_encode($weight_chart_data); ?>
};
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
$conn->close();
?>