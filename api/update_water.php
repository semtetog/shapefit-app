<?php
// VERSÃO FINAL - COM LÓGICA DE META E BÔNUS
define('IS_AJAX_REQUEST', true);
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

// --- Validação de Segurança ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Acesso negado.']));
}

$user_id = $_SESSION['user_id'];
$new_cup_count = filter_input(INPUT_POST, 'cups', FILTER_VALIDATE_INT);
$date = date('Y-m-d');

if ($new_cup_count === false || $new_cup_count < 0) {
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Quantidade inválida.']));
}

try {
    $conn->begin_transaction();

    // 1. DADOS INICIAIS (O "ANTES")
    // Pega o registro de hoje para saber quantos copos já tinha
    $daily_tracking = getDailyTrackingRecord($conn, $user_id, $date);
    if (!$daily_tracking) throw new Exception("Não foi possível obter o registro diário.");
    $old_cup_count = (int)$daily_tracking['water_consumed_cups'];

    // Pega o perfil para calcular a meta de água
    $user_profile = getUserProfileData($conn, $user_id);
    if (!$user_profile) throw new Exception("Não foi possível obter o perfil do usuário.");
    
    $water_goal_data = getWaterIntakeSuggestion((float)$user_profile['weight_kg']);
    $water_goal = $water_goal_data['cups'];

    $total_points_change = 0.0;
    
    // 2. LÓGICA DE PONTOS POR COPO (SÓ CONTA ATÉ ATINGIR A META)
    $points_per_cup = 0.5;
    if ($new_cup_count > $old_cup_count) { // GANHO
        for ($i = $old_cup_count + 1; $i <= $new_cup_count; $i++) {
            // SÓ DÁ PONTOS SE O COPO ATUAL FOR MENOR OU IGUAL À META
            if ($i <= $water_goal) {
                $php_timestamp = date('Y-m-d H:i:s');
                $action_key = 'WATER_CUP_LOGGED';
                
                $stmt_add = $conn->prepare("INSERT IGNORE INTO sf_user_points_log (user_id, points_awarded, action_key, action_context_id, date_awarded, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_add->bind_param("idsiss", $user_id, $points_per_cup, $action_key, $i, $date, $php_timestamp);
                $stmt_add->execute();
                if ($stmt_add->affected_rows > 0) {
                    $total_points_change += $points_per_cup;
                }
                $stmt_add->close();
            }
        }
    } else { // PERDA
        for ($i = $new_cup_count + 1; $i <= $old_cup_count; $i++) {
            // SÓ REMOVE PONTOS SE O COPO ESTAVA DENTRO DA META
            if ($i <= $water_goal) {
                $action_key = 'WATER_CUP_LOGGED';
                $stmt_del = $conn->prepare("DELETE FROM sf_user_points_log WHERE user_id = ? AND action_key = ? AND action_context_id = ? AND date_awarded = ?");
                $stmt_del->bind_param("isis", $user_id, $action_key, $i, $date);
                $stmt_del->execute();
                if ($stmt_del->affected_rows > 0) {
                    $total_points_change -= $points_per_cup;
                }
                $stmt_del->close();
            }
        }
    }
    
    // 3. LÓGICA DE BÔNUS DE 10 PONTOS (QUANDO CRUZA A LINHA DA META)
    $points_bonus = 10.0;
    $action_key_bonus = 'WATER_GOAL_MET';
    $old_status_met = ($old_cup_count >= $water_goal);
    $new_status_met = ($new_cup_count >= $water_goal);

    // Se a meta foi atingida AGORA (antes não estava), dá o bônus
    if ($new_status_met && !$old_status_met) {
        $total_points_change += $points_bonus;
        $php_timestamp = date('Y-m-d H:i:s');
        $stmt_bonus_add = $conn->prepare("INSERT IGNORE INTO sf_user_points_log (user_id, points_awarded, action_key, date_awarded, timestamp) VALUES (?, ?, ?, ?, ?)");
        $stmt_bonus_add->bind_param("idsss", $user_id, $points_bonus, $action_key_bonus, $date, $php_timestamp);
        $stmt_bonus_add->execute();
        $stmt_bonus_add->close();
    } 
    // Se a meta ESTAVA atingida e agora NÃO ESTÁ MAIS, remove o bônus
    elseif (!$new_status_met && $old_status_met) {
        $total_points_change -= $points_bonus;
        $stmt_bonus_del = $conn->prepare("DELETE FROM sf_user_points_log WHERE user_id = ? AND action_key = ? AND date_awarded = ?");
        $stmt_bonus_del->bind_param("iss", $user_id, $action_key_bonus, $date);
        $stmt_bonus_del->execute();
        $stmt_bonus_del->close();
    }
    
    // 4. ATUALIZA O TOTAL DE PONTOS DO USUÁRIO
    if ($total_points_change != 0) {
        addPointsToUser($conn, $user_id, $total_points_change, "Ajuste de hidratação");
    }

    // 5. ATUALIZA A CONTAGEM DE ÁGUA NA TABELA DE TRACKING
    $stmt_update = $conn->prepare("UPDATE sf_user_daily_tracking SET water_consumed_cups = ? WHERE id = ?");
    $stmt_update->bind_param("ii", $new_cup_count, $daily_tracking['id']);
    $stmt_update->execute();
    $stmt_update->close();

    $conn->commit();
    
    // 6. PREPARA A RESPOSTA PARA O FRONTEND
    $events = [];
    $points_from_bonus_event = ($new_status_met && !$old_status_met) ? $points_bonus : ((!$new_status_met && $old_status_met) ? -$points_bonus : 0);
    $points_from_cups_event = $total_points_change - $points_from_bonus_event;
    
    // Adiciona os eventos ao array de resposta, que o JS vai ler
    if ($points_from_cups_event != 0) {
        $events[] = ['type' => ($points_from_cups_event > 0 ? 'gain' : 'loss'), 'points' => $points_from_cups_event];
    }
    if ($points_from_bonus_event > 0) {
        $events[] = ['type' => 'bonus', 'points' => $points_from_bonus_event];
    } elseif ($points_from_bonus_event < 0) {
        // Quando o bônus é perdido, usamos o tipo 'loss' para o popup
        $events[] = ['type' => 'loss', 'points' => $points_from_bonus_event];
    }

    // Busca o total final de pontos para enviar de volta
    $stmt_total = $conn->prepare("SELECT points FROM sf_users WHERE id = ?");
    $stmt_total->bind_param("i", $user_id);
    $stmt_total->execute();
    $total_points = $stmt_total->get_result()->fetch_assoc()['points'];
    $stmt_total->close();

    echo json_encode(['status' => 'success', 'events' => $events, 'new_total_points' => $total_points]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    error_log("Erro em update_water.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'ERRO NO SERVIDOR: ' . $e->getMessage()]);
}

$conn->close();
?>