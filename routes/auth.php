<?php
/**
 * Rotas de Autenticação
 * Endpoints: /api/auth/*
 */

$auth = new Auth();

// Debug: mostrar informações
if (isset($_GET['debug'])) {
    echo json_encode([
        'method' => $method,
        'action' => $action,
        'endpoint' => $endpoint
    ]);
    exit();
}

// Determinar ação baseada no método HTTP e parâmetros
switch ($method) {
    case 'POST':
        switch ($action) {
            case 'login':
                // POST /api/auth/login
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input) {
                    $response->error('Dados JSON inválidos', HTTP_BAD_REQUEST);
                }
                
                $email = $input['email'] ?? '';
                $password = $input['password'] ?? '';
                $unit = $input['unit'] ?? '';
                
                $auth->login($email, $password, $unit);
                break;
                
            case 'validate-email':
                // POST /api/auth/validate-email
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input || empty($input['email'])) {
                    $response->error('Email é obrigatório', HTTP_BAD_REQUEST);
                }
                
                $auth->validateEmail($input['email']);
                break;
                
            case 'get-units':
                // POST /api/auth/get-units
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input || empty($input['unit_ids'])) {
                    $response->error('IDs das unidades são obrigatórios', HTTP_BAD_REQUEST);
                }
                
                $auth->getUnits($input['unit_ids']);
                break;
                
            case 'logout':
                // POST /api/auth/logout
                $headers = getallheaders();
                $token = $headers['Authorization'] ?? '';
                $token = str_replace('Bearer ', '', $token);
                
                $auth->logout($token);
                break;
                
            default:
                $response->error('Ação não encontrada', HTTP_NOT_FOUND);
                break;
        }
        break;
        
    case 'GET':
        switch ($action) {
            case 'verify-token':
                // GET /api/auth/verify-token
                $headers = getallheaders();
                $token = $headers['Authorization'] ?? '';
                $token = str_replace('Bearer ', '', $token);
                
                $auth->verifyToken($token);
                break;
                
            default:
                $response->error('Ação não encontrada', HTTP_NOT_FOUND);
                break;
        }
        break;
        
    default:
        $response->error('Método não permitido', HTTP_BAD_REQUEST);
        break;
}
?>
