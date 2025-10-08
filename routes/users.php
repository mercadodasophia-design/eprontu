<?php
/**
 * Rotas de Usuários
 * Endpoints: /api/users/*
 */

$user = new User();

// Verificar autenticação
$auth = new Auth();
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

if (empty($token)) {
    $response->error('Token de autenticação necessário', HTTP_UNAUTHORIZED);
}

// Verificar token
$tokenData = $auth->verifyToken($token);
if (!$tokenData['success']) {
    $response->error('Token inválido', HTTP_UNAUTHORIZED);
}

$userId = $tokenData['data']['user_id'];

// Determinar ação baseada no método HTTP e parâmetros
switch ($method) {
    case 'GET':
        switch ($action) {
            case 'profile':
                // GET /api/users/profile
                $user->getProfile($userId);
                break;
                
            case 'permissions':
                // GET /api/users/permissions
                $user->getPermissions($userId);
                break;
                
            case 'unit':
                // GET /api/users/unit
                $unitId = $tokenData['data']['unit_id'];
                $user->getUsersByUnit($unitId);
                break;
                
            default:
                $response->error('Ação não encontrada', HTTP_NOT_FOUND);
                break;
        }
        break;
        
    case 'PUT':
        switch ($action) {
            case 'profile':
                // PUT /api/users/profile
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input) {
                    $response->error('Dados JSON inválidos', HTTP_BAD_REQUEST);
                }
                
                $user->updateProfile($userId, $input);
                break;
                
            case 'password':
                // PUT /api/users/password
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input || empty($input['current_password']) || empty($input['new_password'])) {
                    $response->error('Senha atual e nova senha são obrigatórias', HTTP_BAD_REQUEST);
                }
                
                $user->changePassword($userId, $input['current_password'], $input['new_password']);
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
