<?php
// Arquivo: public_html/shapefit/includes/functions.php (VERSÃO CORRIGIDA E LIMPA)

if (session_status() == PHP_SESSION_NONE) { session_start(); }



// Em includes/functions.php

/**
 * Cria uma miniatura quadrada de uma imagem.
 * @param string $source_path Caminho da imagem original.
 * @param string $destination_path Caminho onde a miniatura será salva.
 * @param int $thumb_size O tamanho (largura e altura) da miniatura.
 * @return bool Retorna true em sucesso, false em falha.
 */
function create_thumbnail($source_path, $destination_path, $thumb_size = 200) {
    if (!file_exists($source_path)) {
        return false;
    }

    list($width, $height, $type) = getimagesize($source_path);
    if ($width == 0 || $height == 0) return false;

    // Determina o tipo de imagem e cria o recurso de imagem
    $image_resource = null;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image_resource = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $image_resource = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_WEBP:
            $image_resource = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }

    if (!$image_resource) return false;

    // Cria a imagem da miniatura (o "canvas" em branco)
    $thumb_resource = imagecreatetruecolor($thumb_size, $thumb_size);

    // Lida com a transparência do PNG
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($thumb_resource, false);
        imagesavealpha($thumb_resource, true);
        $transparent = imagecolorallocatealpha($thumb_resource, 255, 255, 255, 127);
        imagefilledrectangle($thumb_resource, 0, 0, $thumb_size, $thumb_size, $transparent);
    }

    // Calcula as dimensões para o corte centralizado
    $src_x = 0;
    $src_y = 0;
    if ($width > $height) { // Imagem horizontal
        $src_x = ($width - $height) / 2;
        $width = $height;
    } elseif ($height > $width) { // Imagem vertical
        $src_y = ($height - $width) / 2;
        $height = $width;
    }

    // Copia e redimensiona a imagem original para a miniatura
    imagecopyresampled($thumb_resource, $image_resource, 0, 0, $src_x, $src_y, $thumb_size, $thumb_size, $width, $height);

    // Salva a miniatura no destino
    $success = false;
    $extension = strtolower(pathinfo($destination_path, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'jpeg':
        case 'jpg':
            $success = imagejpeg($thumb_resource, $destination_path, 85); // 85% de qualidade
            break;
        case 'png':
            $success = imagepng($thumb_resource, $destination_path, 7); // Compressão nível 7
            break;
        case 'webp':
            $success = imagewebp($thumb_resource, $destination_path, 85);
            break;
    }

    // Libera a memória
    imagedestroy($image_resource);
    imagedestroy($thumb_resource);

    return $success;
}


function addPointsToUser(mysqli $conn, int $user_id, float $points_to_add, string $reason): bool {
    // Apenas para se o valor for exatamente zero.
    if ($points_to_add == 0) {
        return true;
    }

    $stmt = $conn->prepare("UPDATE sf_users SET points = points + ? WHERE id = ?");
    if (!$stmt) {
        error_log("Erro ao preparar addPointsToUser: " . $conn->error);
        return false;
    }

    // CORREÇÃO CRÍTICA: O bind_param DEVE ser "di" (double, integer)
    // para que o PHP envie o 0.5 como um número decimal para o MySQL.
    $stmt->bind_param("di", $points_to_add, $user_id);

    $success = $stmt->execute();
    if (!$success) {
        error_log("Erro ao executar addPointsToUser para user {$user_id} (Motivo: {$reason}): " . $stmt->error);
    }
    
    $stmt->close();
    return $success;
}

function removeAccents(string $string): string {
    $unwanted_array = ['Š'=>'S', 'š'=>'s', 'Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r'];
    return strtr($string, $unwanted_array);
}

function calculateAge(string $dob_string): int { if (empty($dob_string)) { return 0; } try { $dob = new DateTime($dob_string); $now = new DateTime(); return ($dob > $now) ? 0 : (int)$now->diff($dob)->y; } catch (Exception $e) { return 0; } }
function calculateIMC(float $weight_kg, int $height_cm): float { if ($height_cm <= 0 || $weight_kg <= 0) { return 0.0; } $height_m = (float)$height_cm / 100.0; if ($height_m == 0) { return 0.0; } return round($weight_kg / ($height_m * $height_m), 2); }
function getIMCCategory(float $imc): string { if ($imc < 18.5) return "Abaixo do peso"; if ($imc < 25) return "Peso Ideal"; if ($imc < 30) return "Sobrepeso"; if ($imc < 35) return "Obesidade Grau I"; if ($imc < 40) return "Obesidade Grau II"; return "Obesidade Grau III"; }
function calculateTMB_MifflinStJeor(string $gender, float $weight_kg, int $height_cm, int $age_years): float { if (!in_array(strtolower($gender), ['male', 'female']) || $weight_kg <= 0 || $height_cm <= 0 || $age_years <= 0) { return 0.0; } return (strtolower($gender) == 'male') ? (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age_years) + 5 : (10 * $weight_kg) + (6.25 * $height_cm) - (5 * $age_years) - 161; }
function calculateTargetDailyCalories(string $gender, float $weight_kg, int $height_cm, int $age_years, string $activity_level_key, string $objective_key): int { $basal_rate = calculateTMB_MifflinStJeor($gender, $weight_kg, $height_cm, $age_years); if ($basal_rate <= 0) { return 2000; } $activity_factors = ['sedentary_to_1x' => 1.2, 'light_2_3x' => 1.375, 'moderate_3_5x' => 1.55, 'intense_5x_plus' => 1.725, 'athlete' => 1.9]; $activity_factor = $activity_factors[$activity_level_key] ?? 1.2; $maintenance_calories = $basal_rate * $activity_factor; $calorie_adjustment = 0; switch (strtolower($objective_key)) { case 'lose_fat': $calorie_adjustment = -500; break; case 'gain_muscle': $calorie_adjustment = 300; break; } $target_calories = $maintenance_calories + $calorie_adjustment; $min_calories = (strtolower($gender) == 'male') ? 1500 : 1200; return (int)round(max($min_calories, $target_calories)); }
function calculateMacronutrients(int $total_calories, string $objective_key): array { $protein_perc = 0.30; $carbs_perc = 0.40; $fat_perc = 0.30; switch (strtolower($objective_key)) { case 'lose_fat': $protein_perc = 0.40; $carbs_perc = 0.30; $fat_perc = 0.30; break; case 'gain_muscle': $protein_perc = 0.30; $carbs_perc = 0.45; $fat_perc = 0.25; break; } $macros = ['protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0]; if ($total_calories <= 0) { return $macros; } $macros['protein_g'] = (int)round(($total_calories * $protein_perc) / 4); $macros['carbs_g'] = (int)round(($total_calories * $carbs_perc) / 4); $macros['fat_g'] = (int)round(($total_calories * $fat_perc) / 9); return $macros; }
// NOVA VERSÃO - Cole isto no lugar da função antiga

/**
 * Calcula a sugestão de ingestão de água com base no peso e retorna um array detalhado.
 * A base de cálculo é 45ml de água por kg de peso.
 *
 * @param float $weight_kg O peso do usuário em quilogramas.
 * @param int $mls_per_kg A quantidade de ml de água por kg de peso (padrão: 45ml).
 * @param int $cup_size_ml O tamanho de um copo padrão em ml (padrão: 250ml).
 * @return array Um array contendo 'total_ml', 'cups', e 'cup_size_ml'.
 */
function getWaterIntakeSuggestion(float $weight_kg, int $mls_per_kg = 45, int $cup_size_ml = 250): array {
    // Define uma meta mínima segura caso o peso seja inválido ou muito baixo
    if ($weight_kg <= 0) {
        return [
            'total_ml'    => 2000,
            'cups'        => 8,
            'cup_size_ml' => 250
        ];
    }

    // 1. Calcula o total de mililitros necessários (45ml por kg)
    $total_ml = $weight_kg * $mls_per_kg;

    // 2. Calcula a quantidade de copos, arredondando para CIMA para garantir que a meta seja atingida.
    // Ex: 3375ml / 250ml = 13.5. ceil() arredonda para 14.
    $cups = ceil($total_ml / $cup_size_ml);

    return [
        'total_ml'    => (int) round($total_ml),
        'cups'        => (int) $cups,
        'cup_size_ml' => $cup_size_ml
    ];
}
// Em: includes/functions.php
// SUBSTITUA A FUNÇÃO ANTIGA POR ESTA VERSÃO COMPLETA E CORRIGIDA:

function getDailyTrackingRecord($conn, $user_id, $date) {
    // Passo 1: Tenta buscar um registro existente para o usuário e a data.
    $stmt_find = $conn->prepare("
        SELECT * FROM sf_user_daily_tracking 
        WHERE user_id = ? AND date = ?
    ");
    if (!$stmt_find) {
        error_log("getDailyTrackingRecord Error - Prepare SELECT failed: " . $conn->error);
        return null; // Retorna nulo em caso de erro grave
    }
    $stmt_find->bind_param("is", $user_id, $date);
    $stmt_find->execute();
    $result = $stmt_find->get_result();
    $tracking_record = $result->fetch_assoc();
    $stmt_find->close();

    // Passo 2: Verifica se um registro foi encontrado.
    if ($tracking_record) {
        // Se encontrou, retorna os dados. Tudo certo.
        return $tracking_record;
    } else {
        // Passo 3: Se NENHUM registro foi encontrado, CRIA UM NOVO registro zerado para o dia.
        // Este é o passo mais importante que pode estar faltando.
        $stmt_create = $conn->prepare("
            INSERT INTO sf_user_daily_tracking (user_id, date, kcal_consumed, protein_consumed_g, carbs_consumed_g, fat_consumed_g, water_consumed_cups) 
            VALUES (?, ?, 0, 0.0, 0.0, 0.0, 0)
        ");
        if (!$stmt_create) {
            error_log("getDailyTrackingRecord Error - Prepare INSERT failed: " . $conn->error);
            return null; // Retorna nulo em caso de erro grave
        }
        $stmt_create->bind_param("is", $user_id, $date);
        
        if ($stmt_create->execute()) {
            // Se a inserção foi bem-sucedida, retorna um array com os valores zerados.
            $stmt_create->close();
            return [
                'id' => $conn->insert_id,
                'user_id' => $user_id,
                'date' => $date,
                'kcal_consumed' => 0,
                'protein_consumed_g' => 0.00,
                'carbs_consumed_g' => 0.00,
                'fat_consumed_g' => 0.00,
                'water_consumed_cups' => 0,
                // Adicione outras colunas com valores padrão se houver
            ];
        } else {
            // Se a inserção falhou.
            error_log("getDailyTrackingRecord Error - Execute INSERT failed: " . $stmt_create->error);
            $stmt_create->close();
            return null; // Retorna nulo em caso de falha na criação
        }
    }
}
// EM: includes/functions.php
// SUBSTITUA A FUNÇÃO ATUAL PELA VERSÃO ABAIXO (CORRIGE O ERRO MYSQLI_COLUMN)

/**
 * Busca os dados combinados e COMPLETOS do perfil de um usuário.
 *
 * @param mysqli $conn A conexão com o banco de dados.
 * @param int $user_id O ID do usuário a ser buscado.
 * @return array|null Retorna um array associativo com os dados do usuário ou null se não for encontrado.
 */
function getUserProfileData(mysqli $conn, int $user_id): ?array {
    // Query COMPLETA: Seleciona todos os campos relevantes das tabelas 'users' e 'profiles'.
   $sql = "SELECT 
            u.id, u.name, u.email, u.points, u.uf, u.city, u.phone_ddd, u.phone_number,
            p.profile_image_filename, p.dob, p.gender, p.height_cm, p.weight_kg,
                p.objective, p.activity_level, p.bowel_movement, p.has_dietary_restrictions
            FROM 
                sf_users u
            LEFT JOIN 
                sf_user_profiles p ON u.id = p.user_id
            WHERE 
                u.id = ?";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Prepare failed in getUserProfileData: " . $conn->error);
        return null;
    }
    
    $stmt->bind_param("i", $user_id);
    
    if (!$stmt->execute()) {
        error_log("Execute failed in getUserProfileData: " . $stmt->error);
        $stmt->close();
        return null;
    }
    
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if (!$data) {
        error_log("Nenhum perfil encontrado para o usuário ID: {$user_id} em getUserProfileData");
        return null;
    }

    // Agora, vamos buscar também as restrições alimentares selecionadas
    if (!empty($data['has_dietary_restrictions'])) { // Verifica se o campo é "truthy" (1)
        $stmt_restrictions = $conn->prepare(
            "SELECT o.name 
             FROM sf_user_selected_restrictions usr
             JOIN sf_dietary_restrictions_options o ON usr.restriction_id = o.id
             WHERE usr.user_id = ?"
        );
        if($stmt_restrictions) {
            $stmt_restrictions->bind_param("i", $user_id);
            $stmt_restrictions->execute();
            $restrictions_result = $stmt_restrictions->get_result();
            
            // [INÍCIO DA CORREÇÃO] - Substitui fetch_all por um loop while
            $restrictions_list = [];
            while ($row = $restrictions_result->fetch_array(MYSQLI_NUM)) {
                $restrictions_list[] = $row[0]; // Adiciona apenas o primeiro elemento (a coluna 'name')
            }
            $data['restrictions_list'] = $restrictions_list;
            // [FIM DA CORREÇÃO]

            $stmt_restrictions->close();
        } else {
            $data['restrictions_list'] = [];
        }
    } else {
        $data['restrictions_list'] = [];
    }

    return $data;
}
// Em: includes/functions.php
// SUBSTITUA A FUNÇÃO getRoutineItemsForUser PELA VERSÃO ABAIXO:

function getRoutineItemsForUser($conn, $user_id, $date) {
    // Esta consulta foi ajustada para buscar TODAS as missões do dia e
    // usar um CASE para verificar o status de conclusão de cada uma.
    $sql = "
        SELECT 
            ri.id, 
            ri.title, 
            ri.icon_class,
            CASE 
                WHEN url.is_completed = 1 THEN 1
                ELSE 0 
            END AS completion_status
        FROM 
            sf_routine_items ri
        LEFT JOIN 
            sf_user_routine_log url 
        ON 
            ri.id = url.routine_item_id 
            AND url.user_id = ? 
            AND url.date = ?
        WHERE 
            ri.is_active = 1
            AND ri.default_for_all_users = 1 -- Assumindo que são missões para todos
    ";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Erro na preparação da query em getRoutineItemsForUser: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    
    $stmt->close();
    return $items;
}
/**
 * Busca sugestões de refeições. Tenta encontrar pelo tipo de refeição do horário atual.
 * Se não encontrar, busca 5 receitas aleatórias quaisquer para nunca deixar a seção vazia.
 *
 * @param mysqli $conn A conexão com o banco de dados.
 * @return array Um array com os dados da sugestão.
 */
function getMealSuggestions(mysqli $conn): array {
    $current_hour = (int)date('G');
    $meal_info = ['display_name' => 'Jantar', 'db_param' => 'almoco_jantar', 'greeting' => 'Hora do Jantar!'];
    
    // Determina o tipo de refeição e saudação com base na hora
    if ($current_hour >= 5 && $current_hour < 10) {
        $meal_info = ['display_name' => 'Café da Manhã', 'db_param' => 'cafe_da_manha', 'greeting' => 'Bom dia!'];
    } elseif ($current_hour >= 10 && $current_hour < 12) {
        $meal_info = ['display_name' => 'Lanche da Manhã', 'db_param' => 'lanche', 'greeting' => 'Hora do Lanche!'];
    } elseif ($current_hour >= 12 && $current_hour < 15) {
        $meal_info = ['display_name' => 'Almoço', 'db_param' => 'almoco_jantar', 'greeting' => 'Hora do Almoço!'];
    } elseif ($current_hour >= 15 && $current_hour < 18) {
        $meal_info = ['display_name' => 'Lanche da Tarde', 'db_param' => 'lanche', 'greeting' => 'Hora do Lanche!'];
    } elseif ($current_hour >= 18 && $current_hour < 21) {
        $meal_info = ['display_name' => 'Jantar', 'db_param' => 'almoco_jantar', 'greeting' => 'Hora do Jantar!'];
    } else {
        $meal_info = ['display_name' => 'Ceia', 'db_param' => 'lanche', 'greeting' => 'Fome fora de hora?'];
    }

    $recipes = [];
    
    // 1ª Tentativa: Buscar receitas para o tipo de refeição específico do horário
    $stmt = $conn->prepare("SELECT id, name, image_filename, total_kcal_per_serving FROM sf_recipes WHERE meal_type_suggestion = ? AND is_public = TRUE ORDER BY RAND() LIMIT 5");
    if ($stmt) {
        $stmt->bind_param("s", $meal_info['db_param']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recipes[] = $row;
        }
        $stmt->close();
    }

    // 2ª Tentativa (FALLBACK): Se a primeira busca não retornou nada, busca 5 receitas quaisquer.
    if (empty($recipes)) {
        // Este é o "plano B" para garantir que nunca fique vazio
        $result_fallback = $conn->query("SELECT id, name, image_filename, total_kcal_per_serving FROM sf_recipes WHERE is_public = TRUE ORDER BY RAND() LIMIT 5");
        if ($result_fallback) {
            while ($row = $result_fallback->fetch_assoc()) {
                $recipes[] = $row;
            }
        }
    }

    $meal_info['recipes'] = $recipes;
    return $meal_info;
}

function getCaloriesByMealType(mysqli $conn, int $user_id, string $date): array { $calories_by_meal_type = array_fill_keys(['breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'supper'], 0); $stmt = $conn->prepare("SELECT meal_type, SUM(kcal_consumed) as total_kcal FROM sf_user_meal_log WHERE user_id = ? AND date_consumed = ? GROUP BY meal_type"); if ($stmt) { $stmt->bind_param("is", $user_id, $date); $stmt->execute(); $result = $stmt->get_result(); while ($row = $result->fetch_assoc()) { if (array_key_exists($row['meal_type'], $calories_by_meal_type)) { $calories_by_meal_type[$row['meal_type']] = (int)$row['total_kcal']; } } $stmt->close(); } return $calories_by_meal_type; }
function checkAndAwardMacroGoals(mysqli $conn, int $user_id, string $date): int { $profile = getUserProfileData($conn, $user_id); $tracking = getDailyTrackingRecord($conn, $user_id, $date); if (!$profile || !$tracking) return 0; $age = calculateAge($profile['dob']); $calories_goal = calculateTargetDailyCalories($profile['gender'], (float)$profile['weight_kg'], (int)$profile['height_cm'], $age, $profile['activity_level'], $profile['objective']); $macros_goal = calculateMacronutrients($calories_goal, $profile['objective']); $points_definitions = [ 'CALORIE_GOAL_MET'   => ['goal' => $calories_goal, 'current' => $tracking['kcal_consumed'], 'points' => 20], 'PROTEIN_GOAL_MET'   => ['goal' => $macros_goal['protein_g'], 'current' => $tracking['protein_consumed_g'], 'points' => 15], 'CARBS_GOAL_MET'     => ['goal' => $macros_goal['carbs_g'], 'current' => $tracking['carbs_consumed_g'], 'points' => 10], 'FAT_GOAL_MET'       => ['goal' => $macros_goal['fat_g'], 'current' => $tracking['fat_consumed_g'], 'points' => 10], ]; $total_points_awarded = 0; $stmt_points_log = $conn->prepare("INSERT IGNORE INTO sf_user_points_log (user_id, points_awarded, action_key, date_awarded, timestamp) VALUES (?, ?, ?, ?, ?)"); $php_timestamp = date('Y-m-d H:i:s'); foreach ($points_definitions as $action_key => $data) { if ($data['goal'] > 0 && $data['current'] >= $data['goal']) { $points = (float)$data['points']; $stmt_points_log->bind_param("idsss", $user_id, $points, $action_key, $date, $php_timestamp); $stmt_points_log->execute(); if ($stmt_points_log->affected_rows > 0) { addPointsToUser($conn, $user_id, $points, "Meta de {$action_key} atingida"); $total_points_awarded += $points; } } } $stmt_points_log->close(); return $total_points_awarded; }
function searchOpenFoodFacts(?mysqli $conn, string $searchTerm, int $pageSize = 20): ?array { if (!defined('SITE_NAME') || !defined('BASE_APP_URL') || empty(trim($searchTerm))) { return []; } $params = ['search_terms' => $searchTerm, 'tagtype_0' => 'product_name', 'tag_contains_0' => 'contains', 'tag_0' => $searchTerm, 'countries_tags_pt' => 'brasil', 'lc' => 'pt', 'page_size' => $pageSize, 'json' => 1, 'fields' => 'product_name_pt,product_name,code,brands,image_front_thumb_url,nutriments,serving_size,quantity']; $url = "https://world.openfoodfacts.org/api/v2/search?" . http_build_query($params); $foods_data_from_api = []; try { $ch = curl_init($url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); curl_setopt($ch, CURLOPT_USERAGENT, SITE_NAME . ' App/1.0 (+' . BASE_APP_URL . ')'); curl_setopt($ch, CURLOPT_TIMEOUT, 15); $json_response = curl_exec($ch); $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); if ($json_response === false || $http_code !== 200) { throw new Exception("API request failed."); } $response_data = json_decode($json_response, true); if (json_last_error() !== JSON_ERROR_NONE || !isset($response_data['products'])) { throw new Exception("Invalid JSON response."); } foreach ($response_data['products'] as $product) { $name_to_check = trim($product['product_name_pt'] ?? $product['product_name'] ?? ''); if (empty($name_to_check)) { continue; } $kcal_100g = $product['nutriments']['energy-kcal_100g'] ?? ($product['nutriments']['energy_100g'] ?? 0) / 4.184; if ($kcal_100g <= 0 && !isset($product['nutriments']['proteins_100g'])) { continue; } $foods_data_from_api[] = ['id' => $product['code'] ?? 'NOCODE_' . uniqid(), 'name' => $name_to_check, 'brand' => $product['brands'] ?? '', 'image_url' => $product['image_front_thumb_url'] ?? null, 'api_serving_size_info' => $product['serving_size'] ?? null, 'api_quantity_info' => $product['quantity'] ?? null, 'kcal_100g' => round((float)$kcal_100g), 'protein_100g' => (float)($product['nutriments']['proteins_100g'] ?? 0), 'carbs_100g' => (float)($product['nutriments']['carbohydrates_100g'] ?? 0), 'fat_100g' => (float)($product['nutriments']['fat_100g'] ?? 0), 'all_nutriments_api' => $product['nutriments'] ?? []]; } } catch (Exception $e) { return null; } return $foods_data_from_api; }
function generateSlug(string $text): string { $text = preg_replace('~[^\pL\d]+~u', '-', $text); $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text); $text = preg_replace('~[^-\w]+~', '', $text); $text = trim($text, '-'); $text = preg_replace('~-+~', '-', $text); $text = strtolower($text); if (empty($text)) { return 'n-a-' . substr(md5(uniqid(rand(), true)), 0, 6); } return $text; }

?>