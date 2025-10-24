<?php
/**
 * E-Prontu API - Cloud Run
 * Proxy para API Bioma com CORS habilitado
 */

// Headers para CORS
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log de requisições (para debug)
error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

// Obter dados da requisição
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Roteamento de endpoints
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

try {
    // Endpoint de autenticação
    if (strpos($path, '/auth/validate-email') !== false) {
        handleAuthValidateEmail($data);
        exit();
    }
    // Endpoint de bobina (padrão)
    else {
        handleBobina($data);
    }
    
} catch (Exception $e) {
    // Log do erro
    error_log("API Error: " . $e->getMessage());
    
    // Retornar erro em formato JSON
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// Função para validar email
function handleAuthValidateEmail($data) {
    if (!$data || !isset($data['email'])) {
        throw new Exception('Email é obrigatório');
    }
    
    $email = $data['email'];
    
    // Validação básica de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }
    
    // Simular validação (em produção, validar no banco)
    $response = [
        'success' => true,
        'message' => 'Email válido',
        'data' => [
            'email' => $email,
            'valid' => true
        ]
    ];
    
    echo json_encode($response);
}

// Função para bobina (código original)
function handleBobina($data) {
    // Configurações da API Bioma
    $biomaApiUrl = 'https://bioma.app.br/api/bobina/listar/1/100';
    $biomaToken = '2ece8122bc80db2a816c2df41d6b2a1f';
    
    // Validar dados
    if (!$data || !isset($data['filtros'])) {
        throw new Exception('Dados de requisição inválidos');
    }
    
    $filtros = $data['filtros'];
    $prontuario = $filtros['prontuario'] ?? '';
    $especialidade = $filtros['especialidade'] ?? '1';
    
    if (empty($prontuario)) {
        throw new Exception('Prontuário é obrigatório');
    }
    
    // Preparar requisição para API Bioma
    $requestData = [
        'filtros' => [
            'prontuario' => $prontuario,
            'especialidade' => $especialidade,
        ]
    ];
    
    // Fazer requisição para API Bioma
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $biomaApiUrl . '?token=' . $biomaToken . '&encrypt=false');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: E-Prontu-API/1.0'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('Erro cURL: ' . $error);
    }
    
    if ($httpCode !== 200) {
        throw new Exception('Erro HTTP da API Bioma: ' . $httpCode);
    }
    
    // Retornar resposta da API Bioma
    echo $response;
}

?>
