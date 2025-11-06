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
            // Lista campanhas do usuário (token) com paginação via offset/page/limit
            // Aceita GET ou POST

            // Params por GET
            $ownerToken = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 0;

            // Params por POST (JSON)
            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true);
                if (is_array($input)) {
                    $ownerToken = isset($input['token']) ? trim((string)$input['token']) : $ownerToken;
                    $offset = isset($input['offset']) ? (int)$input['offset'] : $offset;
                    $page = isset($input['page']) ? (int)$input['page'] : $page;
                    $limit = isset($input['limit']) ? (int)$input['limit'] : $limit;
                    $perPage = isset($input['per_page']) ? (int)$input['per_page'] : $perPage;
                }
            }

            // Resolve page/limit
            $effectiveLimit = $perPage > 0 ? $perPage : ($limit > 0 ? $limit : 10);
            $start = ($page > 1) ? (($page - 1) * $effectiveLimit) : $offset;
            if ($start < 0) $start = 0;

            $storageFile = __DIR__ . '/../storage/campanhas.json';
            $lista = [];
            if (file_exists($storageFile)) {
                $conteudo = file_get_contents($storageFile);
                $lista = json_decode($conteudo, true);
                if (!is_array($lista)) $lista = [];
            }

            // Não filtrar por token: retornar todas as campanhas

            // Ignora arquivados caso exista a flag
            $lista = array_values(array_filter($lista, function($item) {
                return empty($item['archived']);
            }));

            $total = count($lista);
            $slice = array_slice($lista, $start, $effectiveLimit);
            $hasMore = ($start + $effectiveLimit) < $total;

            $response->success([
                'items' => $slice,
                'offset' => $start,
                'page' => $page > 0 ? $page : 1,
                'limit' => $effectiveLimit,
                'hasMore' => $hasMore,
                'total' => $total
            ], 'Campanhas listadas com sucesso');
            break;

        case 'listar-todas':
            // Lista TODAS as campanhas (sem filtro de token), com paginação via offset/page/limit
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 0;

            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true);
                if (is_array($input)) {
                    $offset = isset($input['offset']) ? (int)$input['offset'] : $offset;
                    $page = isset($input['page']) ? (int)$input['page'] : $page;
                    $limit = isset($input['limit']) ? (int)$input['limit'] : $limit;
                    $perPage = isset($input['per_page']) ? (int)$input['per_page'] : $perPage;
                }
            }

            $effectiveLimit = $perPage > 0 ? $perPage : ($limit > 0 ? $limit : 10);
            $start = ($page > 1) ? (($page - 1) * $effectiveLimit) : $offset;
            if ($start < 0) $start = 0;

            $storageFile = __DIR__ . '/../storage/campanhas.json';
            $lista = [];
            if (file_exists($storageFile)) {
                $conteudo = file_get_contents($storageFile);
                $lista = json_decode($conteudo, true);
                if (!is_array($lista)) $lista = [];
            }

            // Ignora arquivados
            $lista = array_values(array_filter($lista, function($item) {
                return empty($item['archived']);
            }));

            $total = count($lista);
            $slice = array_slice($lista, $start, $effectiveLimit);
            $hasMore = ($start + $effectiveLimit) < $total;

            $response->success([
                'items' => $slice,
                'offset' => $start,
                'page' => $page > 0 ? $page : 1,
                'limit' => $effectiveLimit,
                'hasMore' => $hasMore,
                'total' => $total
            ], 'Campanhas (todas) listadas com sucesso');
            break;

        case 'update':
            // Atualiza uma campanha existente (PUT/POST)
            if (!in_array($method, ['PUT', 'POST'])) {
                $response->error('Método não suportado. Use PUT ou POST.', HTTP_METHOD_NOT_ALLOWED);
            }
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);
            if (!$input || !is_array($input) || empty($input['id'])) {
                $response->validation(['id' => 'ID da campanha é obrigatório'], 'Dados inválidos');
            }

            $storageFile = __DIR__ . '/../storage/campanhas.json';
            $lista = [];
            if (file_exists($storageFile)) {
                $conteudo = file_get_contents($storageFile);
                $lista = json_decode($conteudo, true);
                if (!is_array($lista)) $lista = [];
            }
            $updated = null;
            foreach ($lista as &$item) {
                if (isset($item['id']) && $item['id'] === $input['id']) {
                    // Merge simples dos campos
                    foreach ($input as $k => $v) {
                        if ($k === 'id') continue;
                        $item[$k] = $v;
                    }
                    $updated = $item;
                    break;
                }
            }
            if ($updated === null) {
                $response->error('Campanha não encontrada para atualização', 404);
            }
            file_put_contents($storageFile, json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $response->success(['campanha' => $updated], 'Campanha atualizada com sucesso');
            break;

        case 'delete':
            // Exclusão/arquivamento (DELETE/POST)
            if (!in_array($method, ['DELETE', 'POST'])) {
                $response->error('Método não suportado. Use DELETE ou POST.', HTTP_METHOD_NOT_ALLOWED);
            }
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);
            if (!$input || !is_array($input) || empty($input['id'])) {
                $response->validation(['id' => 'ID da campanha é obrigatório'], 'Dados inválidos');
            }
            $storageFile = __DIR__ . '/../storage/campanhas.json';
            $lista = [];
            if (file_exists($storageFile)) {
                $conteudo = file_get_contents($storageFile);
                $lista = json_decode($conteudo, true);
                if (!is_array($lista)) $lista = [];
            }
            $deleted = false;
            foreach ($lista as &$item) {
                if (isset($item['id']) && $item['id'] === $input['id']) {
                    $item['archived'] = true;
                    $deleted = true;
                    break;
                }
            }
            if (!$deleted) {
                $response->error('Campanha não encontrada para exclusão', 404);
            }
            file_put_contents($storageFile, json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $response->success(null, 'Campanha arquivada com sucesso');
            break;

        case 'arquivar':
            if ($method !== 'POST') {
                $response->error('Método não suportado. Use POST.', HTTP_METHOD_NOT_ALLOWED);
            }
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);
            if (!$input || !is_array($input) || empty($input['id'])) {
                $response->validation(['id' => 'ID da campanha é obrigatório'], 'Dados inválidos');
            }
            $storageFile = __DIR__ . '/../storage/campanhas.json';
            $lista = [];
            if (file_exists($storageFile)) {
                $conteudo = file_get_contents($storageFile);
                $lista = json_decode($conteudo, true);
                if (!is_array($lista)) $lista = [];
            }
            $ok = false;
            foreach ($lista as &$item) {
                if (isset($item['id']) && $item['id'] === $input['id']) {
                    $item['archived'] = true;
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $response->error('Campanha não encontrada para arquivar', 404);
            }
            file_put_contents($storageFile, json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $response->success(null, 'Campanha arquivada com sucesso');
            break;

        case 'restaurar':
            if ($method !== 'POST') {
                $response->error('Método não suportado. Use POST.', HTTP_METHOD_NOT_ALLOWED);
            }
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);
            if (!$input || !is_array($input) || empty($input['id'])) {
                $response->validation(['id' => 'ID da campanha é obrigatório'], 'Dados inválidos');
            }
            $storageFile = __DIR__ . '/../storage/campanhas.json';
            $lista = [];
            if (file_exists($storageFile)) {
                $conteudo = file_get_contents($storageFile);
                $lista = json_decode($conteudo, true);
                if (!is_array($lista)) $lista = [];
            }
            $ok = false;
            foreach ($lista as &$item) {
                if (isset($item['id']) && $item['id'] === $input['id']) {
                    $item['archived'] = false;
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $response->error('Campanha não encontrada para restaurar', 404);
            }
            file_put_contents($storageFile, json_encode($lista, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $response->success(null, 'Campanha restaurada com sucesso');
            break;

        default:
            $response->error('Ação de campanhas não suportada: ' . $action, 404);
    }
} catch (Exception $e) {
    $response->error('Erro na rota campanhas: ' . $e->getMessage(), 500);
}
?>