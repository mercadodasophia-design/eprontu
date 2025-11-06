<?php
// Rota Campanhas: criação e futuras ações

require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../config/server_config.php';

$response = new Response();

// Usa variáveis compartilhadas do index.php
if (!isset($method) || !isset($segments)) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $segments = explode('/', trim($uri, '/'));
    if (isset($segments[0]) && $segments[0] === 'e-prontu') array_shift($segments);
    if (isset($segments[0]) && $segments[0] === 'api') array_shift($segments);
    $action = $segments[1] ?? '';
}

$action = $action ?? '';

try {
    switch ($action) {
        case 'add':
            if ($method !== 'POST') {
                $response->error('Método não suportado. Use POST.', HTTP_METHOD_NOT_ALLOWED);
            }

            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);
            if (!$input || !is_array($input)) {
                $response->validation(['body' => 'JSON inválido ou vazio'], 'Dados inválidos');
            }

            // Validações mínimas conforme DOCUMENTACAO_CAMPANHAS.md
            $errors = [];
            if (empty($input['name'])) $errors['name'] = 'Nome da campanha é obrigatório';
            if (empty($input['description'])) $errors['description'] = 'Descrição é obrigatória';
            if (empty($input['objetivo'])) $errors['objetivo'] = 'Objetivo é obrigatório';
            if (!isset($input['mailigs']) || !is_array($input['mailigs'])) $errors['mailigs'] = 'Lista de mailings é obrigatória';

            if (!empty($errors)) {
                $response->validation($errors, 'Campos obrigatórios ausentes');
            }

            // Normaliza estrutura: gera ID e retorna sucesso
            $id = uniqid('cmp_', true);
            $leadsCount = is_array($input['mailigs']) ? count($input['mailigs']) : 0;

            $campanha = [
                'id' => $id,
                'name' => (string)$input['name'],
                'description' => (string)$input['description'],
                'objetivo' => (string)$input['objetivo'],
                'leadsCount' => $leadsCount,
                'script' => (string)($input['script'] ?? ''),
                'dataInicio' => (string)($input['dataInicio'] ?? ''),
                'dataFim' => (string)($input['dataFim'] ?? ''),
                'canal' => $input['canal'] ?? null,
                'responsaveis' => $input['responsaveis'] ?? [],
                'mailigs' => $input['mailigs'] ?? [],
            ];

            // Resposta padrão
            $response->success([
                'campanha' => $campanha
            ], 'Campanha criada com sucesso');
            break;

        default:
            $response->error('Ação de campanhas não suportada: ' . $action, 404);
    }
} catch (Exception $e) {
    $response->error('Erro na rota campanhas: ' . $e->getMessage(), 500);
}
?>