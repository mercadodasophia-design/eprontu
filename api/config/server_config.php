<?php
/**
 * Configuração do servidor para API e-prontu
 * Porta padrão: 3000
 */

// Configurações do servidor
define('SERVER_PORT', 80);
define('SERVER_HOST', 'localhost');
define('API_BASE_URL', 'http://' . SERVER_HOST . '/e-prontu/api');

// Configurações de desenvolvimento
define('DEBUG_MODE', true);
define('LOG_REQUESTS', true);

// Configurações de CORS
define('CORS_ORIGIN', '*');
define('CORS_METHODS', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
define('CORS_HEADERS', 'Content-Type, Authorization, X-Requested-With, Accept, Origin');
define('CORS_CREDENTIALS', true);
define('CORS_MAX_AGE', 86400);

// Função para configurar headers CORS
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: ' . CORS_ORIGIN);
    header('Access-Control-Allow-Methods: ' . CORS_METHODS);
    header('Access-Control-Allow-Headers: ' . CORS_HEADERS);
    header('Access-Control-Allow-Credentials: ' . (CORS_CREDENTIALS ? 'true' : 'false'));
    header('Access-Control-Max-Age: ' . CORS_MAX_AGE);
    header('Content-Type: application/json; charset=utf-8');
}

// Função para tratar requisições OPTIONS
function handleOptionsRequest() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        setCorsHeaders();
        http_response_code(200);
        exit();
    }
}

// Função para log de requisições (apenas em debug)
function logRequest($method, $url, $data = null) {
    if (DEBUG_MODE && LOG_REQUESTS) {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'url' => $url,
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        error_log('API Request: ' . json_encode($log));
    }
}

// Função para resposta padronizada
function sendResponse($success, $message, $data = null, $code = 200) {
    setCorsHeaders();
    http_response_code($code);
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// Função para erro padronizado
function sendError($message, $code = 400, $details = null) {
    sendResponse(false, $message, $details, $code);
}

// Função para sucesso padronizado
function sendSuccess($message, $data = null, $code = 200) {
    sendResponse(true, $message, $data, $code);
}
?>
