<?php
// Arquivo: public_html/shapefit/api/ajax_search_food.php

// Define o cabeçalho correto para JSON com UTF-8
header('Content-Type: application/json; charset=utf-8');

// Corrige o caminho para os includes, pois o arquivo agora está em /api/
require_once __DIR__ . '/../includes/config.php';
require_once APP_ROOT_PATH . '/includes/db.php';

// A sessão já foi iniciada pelo config.php, não precisa iniciar de novo.
// Garante que o usuário esteja logado.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado.']);
    exit;
}

$term = isset($_GET['term']) ? trim($_GET['term']) : '';

if (mb_strlen($term, 'UTF-8') < 2) {
    echo json_encode(['success' => false, 'message' => 'Termo de busca muito curto.', 'data' => []]);
    exit;
}

$results = [];

// --- BUSCA NO BANCO DE DADOS LOCAL (TACO) ---
try {
    $local_term = '%' . $term . '%';
    $start_term = $term . '%';

    $sql_local = "SELECT taco_id, name_pt, energy_kcal_100g, protein_g_100g, carbohydrate_g_100g, fat_g_100g FROM sf_food_items WHERE name_pt LIKE ? ORDER BY CASE WHEN name_pt LIKE ? THEN 1 ELSE 2 END, LENGTH(name_pt), name_pt LIMIT 15";
    
    $stmt_local = $conn->prepare($sql_local);
    
    if ($stmt_local) {
        $stmt_local->bind_param("ss", $local_term, $start_term);
        $stmt_local->execute();
        $result_set = $stmt_local->get_result();

        while ($row = $result_set->fetch_assoc()) {
            $results[] = [
                'id' => 'taco_' . $row['taco_id'],
                'name' => $row['name_pt'],
                'brand' => 'TACO',
                'image_url' => null,
                'kcal_100g' => $row['energy_kcal_100g'],
                'protein_100g' => $row['protein_g_100g'],
                'carbs_100g' => $row['carbohydrate_g_100g'],
                'fat_100g' => $row['fat_g_100g'],
                'api_serving_size_info' => '100g'
            ];
        }
        $stmt_local->close();
    }
} catch (Exception $e) {
    error_log("Exceção na busca local de alimentos: " . $e->getMessage());
}


// --- BUSCA NA API EXTERNA (Open Food Facts - FALLBACK) ---
// Adicionamos a busca na API externa aqui para ter mais resultados
if (count($results) < 10) {
    // Código omitido para não ser repetitivo, mas você deve manter/adicionar
    // a sua lógica de busca no Open Food Facts aqui, similar à função
    // searchOpenFoodFacts que você tem no functions.php
}


// Garante que a saída seja sempre um JSON válido
echo json_encode(['success' => true, 'data' => $results], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

$conn->close();
?>