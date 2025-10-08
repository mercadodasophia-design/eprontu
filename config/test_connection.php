<?php
/**
 * Teste de conexão da API
 * Verifica se a API está funcionando corretamente
 */

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Tratar requisições OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Teste de conexão com banco
try {
    require_once 'database.php';
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    $response = [
        'success' => true,
        'message' => 'API funcionando corretamente',
        'data' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'database_connection' => 'OK',
            'php_version' => PHP_VERSION,
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'method' => $_SERVER['REQUEST_METHOD'],
            'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'N/A'
        ]
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Erro na conexão: ' . $e->getMessage(),
        'data' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $e->getMessage()
        ]
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
