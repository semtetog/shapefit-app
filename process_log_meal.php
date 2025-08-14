<?php
// public_html/shapefit/process_log_meal.php
require_once 'includes/config.php';
require_once APP_ROOT_PATH . '/includes/auth.php';
requireLogin();
require_once APP_ROOT_PATH . '/includes/db.php';
require_once APP_ROOT_PATH . '/includes/functions.php'; // Para getDailyTrackingRecord

$user_id = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validar CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Erro de validação de segurança. Tente novamente.'];
        header("Location: " . BASE_APP_URL . "/main_app.php"); // Redireciona para um lugar seguro
        exit();
    }

    $recipe_id = filter_input(INPUT_POST, 'recipe_id', FILTER_VALIDATE_INT);
    $date_consumed_str = trim($_POST['date_consumed'] ?? date('Y-m-d'));
    $meal_type = trim($_POST['meal_type'] ?? '');
    $servings_consumed = filter_input(INPUT_POST, 'servings_consumed', FILTER_VALIDATE_FLOAT, ['options' => ['min_range' => 0.1]]);

    $kcal_per_serving = filter_input(INPUT_POST, 'kcal_per_serving', FILTER_VALIDATE_INT);
    $protein_per_serving = filter_input(INPUT_POST, 'protein_per_serving', FILTER_VALIDATE_FLOAT);
    $carbs_per_serving = filter_input(INPUT_POST, 'carbs_per_serving', FILTER_VALIDATE_FLOAT);
    $fat_per_serving = filter_input(INPUT_POST, 'fat_per_serving', FILTER_VALIDATE_FLOAT);

    $errors = [];
    if (!$recipe_id) $errors[] = "ID da receita inválido.";
    if (empty($meal_type)) $errors[] = "Tipo de refeição não selecionado.";
    if (!$servings_consumed) $errors[] = "Número de porções inválido.";
    if ($kcal_per_serving === false || $protein_per_serving === false || $carbs_per_serving === false || $fat_per_serving === false) {
        $errors[] = "Dados nutricionais da receita ausentes ou inválidos.";
    }

    $date_obj = DateTime::createFromFormat('Y-m-d', $date_consumed_str);
    if (!$date_obj || $date_obj->format('Y-m-d') !== $date_consumed_str) {
        $errors[] = "Data de consumo inválida.";
    } else {
        $date_consumed = $date_obj->format('Y-m-d');
    }

    if (!empty($errors)) {
        $_SESSION['alert_message'] = ['type' => 'danger', 'message' => "Erro ao registrar refeição: " . implode(" ", $errors)];
        header("Location: " . BASE_APP_URL . "/view_recipe.php?id=" . ($recipe_id ?? '')); // Tenta voltar para a receita
        exit();
    }

    // Calcular totais consumidos
    $total_kcal = round($kcal_per_serving * $servings_consumed);
    $total_protein = round($protein_per_serving * $servings_consumed, 2);
    $total_carbs = round($carbs_per_serving * $servings_consumed, 2);
    $total_fat = round($fat_per_serving * $servings_consumed, 2);

    $conn->begin_transaction();
    try {
        // 1. Inserir no log de refeições do usuário
        $stmt_log = $conn->prepare("INSERT INTO sf_user_meal_log (user_id, recipe_id, date_consumed, meal_type, servings_consumed, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt_log) throw new Exception("Prepare sf_user_meal_log failed: " . $conn->error);
        
        $stmt_log->bind_param("iisssdidd", $user_id, $recipe_id, $date_consumed, $meal_type, $servings_consumed, $total_kcal, $total_protein, $total_carbs, $total_fat);
        if (!$stmt_log->execute()) throw new Exception("Execute sf_user_meal_log failed: " . $stmt_log->error);
        $stmt_log->close();

        // 2. Atualizar o registro diário do usuário (sf_user_daily_tracking)
        $daily_tracking_record = getDailyTrackingRecord($conn, $user_id, $date_consumed); // Garante que o registro exista
        if (!$daily_tracking_record) {
            throw new Exception("Falha ao obter ou criar registro diário para {$date_consumed}.");
        }

        $stmt_update_daily = $conn->prepare("
            UPDATE sf_user_daily_tracking
            SET kcal_consumed = kcal_consumed + ?,
                protein_consumed_g = protein_consumed_g + ?,
                carbs_consumed_g = carbs_consumed_g + ?,
                fat_consumed_g = fat_consumed_g + ?
            WHERE user_id = ? AND date = ?
        ");
        if (!$stmt_update_daily) throw new Exception("Prepare sf_user_daily_tracking update failed: " . $conn->error);

        $stmt_update_daily->bind_param("idddis", $total_kcal, $total_protein, $total_carbs, $total_fat, $user_id, $date_consumed);
        if (!$stmt_update_daily->execute()) throw new Exception("Execute sf_user_daily_tracking update failed: " . $stmt_update_daily->error);
        
        // Verificar se alguma linha foi realmente atualizada
        // if ($stmt_update_daily->affected_rows === 0) {
        //    throw new Exception("Nenhum registro diário encontrado para atualizar para user_id {$user_id} na data {$date_consumed}.");
        // }
        $stmt_update_daily->close();

        $conn->commit();

        unset($_SESSION['csrf_token']); // Limpar o token usado
        $_SESSION['alert_message'] = ['type' => 'success', 'message' => 'Refeição registrada com sucesso!'];
        
        // Redirecionar para main_app.php. Se a data logada for hoje, o usuário verá a atualização.
        // Se for uma data passada, ele não verá a atualização imediata nos círculos da main_app (que mostram "hoje"),
        // a menos que você implemente uma forma de visualizar dias passados na main_app.
        $redirect_url = BASE_APP_URL . "/main_app.php";
        if ($date_consumed !== date('Y-m-d')) {
            // Para o futuro: $redirect_url .= "?show_date=" . $date_consumed;
        }
        header("Location: " . $redirect_url);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Erro ao registrar refeição para user_id {$user_id}, recipe_id {$recipe_id}: " . $e->getMessage());
        $_SESSION['alert_message'] = ['type' => 'danger', 'message' => 'Ops! Ocorreu um erro ao registrar sua refeição. Por favor, tente novamente.'];
        header("Location: " . BASE_APP_URL . "/view_recipe.php?id=" . ($recipe_id ?? ''));
        exit();
    }

} else {
    // Se não for POST, redirecionar para um local seguro
    header("Location: " . BASE_APP_URL . "/main_app.php");
    exit();
}
?>