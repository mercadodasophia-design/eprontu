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

            // Normalizações e validações mínimas
            $name = isset($input['name']) ? trim((string)$input['name']) : '';
            $description = isset($input['description']) ? trim((string)$input['description']) : '';
            $objetivo = isset($input['objetivo']) ? trim((string)$input['objetivo']) : '';
            $mailigs = isset($input['mailigs']) && is_array($input['mailigs']) ? $input['mailigs'] : [];

            // Se objetivo vier vazio, usa o nome como fallback
            if ($objetivo === '' && $name !== '') {
                $objetivo = $name;
            }

            $errors = [];
            if ($name === '') $errors['name'] = 'Nome da campanha é obrigatório';
            if (!is_array($mailigs)) $errors['mailigs'] = 'Lista de mailings é obrigatória';

            if (!empty($errors)) {
                $response->validation($errors, 'Campos obrigatórios ausentes');
            }

            // Normaliza estrutura: gera ID e retorna sucesso
            $id = uniqid('cmp_', true);
            $leadsCount = is_array($mailigs) ? count($mailigs) : 0;
            $ownerToken = isset($_GET['token']) ? trim((string)$_GET['token']) : 'anon';
            $createdAt = date('Y-m-d H:i:s');

            $campanha = [
                'id' => $id,
                'name' => $name,
                'description' => $description,
                'objetivo' => $objetivo,
                'leadsCount' => $leadsCount,
                'script' => (string)($input['script'] ?? ''),
                'dataInicio' => (string)($input['dataInicio'] ?? ''),
                'dataFim' => (string)($input['dataFim'] ?? ''),
                'canal' => $input['canal'] ?? null,
                'responsaveis' => $input['responsaveis'] ?? [],
                'mailigs' => $mailigs,
                'ownerToken' => $ownerToken,
                'createdAt' => $createdAt,
            ];

            // Persistir em storage local simples (JSON)
            $storageDir = __DIR__ . '/../storage';
            $storageFile = $storageDir . '/campanhas.json';
            if (!is_dir($storageDir)) {
                @mkdir($storageDir, 0777, true);
            }
            $lista = [];
            if (file_exists($storageFile)) {
                $conteudo = file_get_contents($storageFile);
                $lista = json_decode($conteudo, true);
                if (!is_array($lista)) $lista = [];
            }
            $lista[] = $campanha;
            file_put_contents($storageFile, json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            // Resposta padrão
            $response->success([
                'campanha' => $campanha
            ], 'Campanha criada com sucesso');
            break;

        case 'listar':
            // Lista campanhas do usuário (token) com paginação básica via offset
            if ($method !== 'POST') {
                $response->error('Método não suportado. Use POST.', HTTP_METHOD_NOT_ALLOWED);
            }

            $ownerToken = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $perPage = 10;

            $storageFile = __DIR__ . '/../storage/campanhas.json';
            $lista = [];
            if (file_exists($storageFile)) {
                $conteudo = file_get_contents($storageFile);
                $lista = json_decode($conteudo, true);
                if (!is_array($lista)) $lista = [];
            }

            // Filtra por token se informado
            if ($ownerToken !== '') {
                $lista = array_values(array_filter($lista, function($item) use ($ownerToken) {
                    return isset($item['ownerToken']) && $item['ownerToken'] === $ownerToken;
                }));
            }

            $total = count($lista);
            $slice = array_slice($lista, $offset, $perPage);
            $hasMore = ($offset + $perPage) < $total;

            $response->success([
                'items' => $slice,
                'offset' => $offset,
                'hasMore' => $hasMore,
                'total' => $total
            ], 'Campanhas listadas com sucesso');
            break;

        case 'listar-todas':
            // Lista TODAS as campanhas (sem filtro de token), com paginação via offset
            if ($method !== 'POST') {
                $response->error('Método não suportado. Use POST.', HTTP_METHOD_NOT_ALLOWED);
            }

            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $perPage = 10;

            $storageFile = __DIR__ . '/../storage/campanhas.json';
            $lista = [];
            if (file_exists($storageFile)) {
                $conteudo = file_get_contents($storageFile);
                $lista = json_decode($conteudo, true);
                if (!is_array($lista)) $lista = [];
            }

            $total = count($lista);
            $slice = array_slice($lista, $offset, $perPage);
            $hasMore = ($offset + $perPage) < $total;

            $response->success([
                'items' => $slice,
                'offset' => $offset,
                'hasMore' => $hasMore,
                'total' => $total
            ], 'Campanhas (todas) listadas com sucesso');
            break;

        default:
            $response->error('Ação de campanhas não suportada: ' . $action, 404);
    }
} catch (Exception $e) {
    $response->error('Erro na rota campanhas: ' . $e->getMessage(), 500);
}
?>