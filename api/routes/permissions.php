<?php
/**
 * Rotas de Permissões
 * Endpoints: /api/permissions/*
 */

$permission = new Permission();

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
            case 'user':
                // GET /api/permissions/user
                $permission->getUserPermissions($userId);
                break;
                
            case 'modules':
                // GET /api/permissions/modules
                $permission->getAvailableModules();
                break;
                
            case 'check':
                // GET /api/permissions/check?module=agenda
                $module = $_GET['module'] ?? '';
                if (empty($module)) {
                    $response->error('Módulo é obrigatório', HTTP_BAD_REQUEST);
                }
                
                $hasPermission = $permission->hasPermission($userId, $module);
                $response->success(['has_permission' => $hasPermission], 'Verificação de permissão realizada');
                break;
                
            default:
                $response->error('Ação não encontrada', HTTP_NOT_FOUND);
                break;
        }
        break;
        
    case 'PUT':
        switch ($action) {
            case 'user':
                // PUT /api/permissions/user
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input) {
                    $response->error('Dados JSON inválidos', HTTP_BAD_REQUEST);
                }
                
                $permission->updatePermissions($userId, $input);
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
