<?php
// Rota Campanhas: criação e futuras ações

require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../config/server_config.php';
require_once __DIR__ . '/../config/database.php';

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

// Se a ação for 'interacoes', não processar aqui (já foi roteado no index.php)
// Verificar também pelos segments diretamente
$isInteracoes = false;
if (isset($action) && $action === 'interacoes') {
    $isInteracoes = true;
} elseif (isset($segments) && isset($segments[1]) && $segments[1] === 'interacoes') {
    $isInteracoes = true;
}

if ($isInteracoes) {
    // Não fazer nada, já foi roteado para campanhas_interacoes.php
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Esta rota foi redirecionada para campanhas_interacoes.php']);
    exit;
}

$action = $action ?? '';

// Cria a tabela de campanhas caso não exista (PostgreSQL)
function ensureCampanhasTable(PDO $pdo) {
    try {
        $exists = $pdo->query("SELECT to_regclass('public.campanhas')")->fetchColumn();
        if (!$exists) {
            $sql = "CREATE TABLE IF NOT EXISTS public.campanhas (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                description TEXT,
                objetivo TEXT,
                script TEXT,
                data_inicio TEXT,
                data_fim TEXT,
                canal TEXT,
                leads_count INTEGER DEFAULT 0,
                responsaveis JSONB,
                mailigs JSONB,
                owner_token TEXT,
                created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
                archived BOOLEAN DEFAULT FALSE
            )";
            $pdo->exec($sql);
        }
    } catch (Exception $e) {
        // Se não conseguir criar/verificar, seguir em frente para não quebrar a rota
    }
}

try {
    switch ($action) {
        case 'seed':
            // Popular tabela com campanhas de exemplo (DEV)
            if (!in_array($method, ['POST', 'GET'])) {
                $response->error('Método não suportado. Use POST ou GET.', HTTP_METHOD_NOT_ALLOWED);
            }
            $count = isset($_GET['count']) ? (int)$_GET['count'] : 5;
            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true);
                if (is_array($input) && isset($input['count'])) {
                    $count = (int)$input['count'];
                }
            }
            if ($count <= 0) $count = 5;

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureCampanhasTable($pdo);

            $ownerToken = isset($_GET['token']) ? trim((string)$_GET['token']) : 'anon';
            $now = date('Y-m-d H:i:s');
            $inserted = 0;
            for ($i = 0; $i < $count; $i++) {
                $id = uniqid('cmp_', true);
                $sql = "INSERT INTO public.campanhas (id, name, description, objetivo, script, data_inicio, data_fim, canal, leads_count, responsaveis, mailigs, owner_token, created_at, archived)
                        VALUES (:id, :name, :description, :objetivo, :script, :data_inicio, :data_fim, :canal, 0, '[]'::jsonb, '[]'::jsonb, :owner_token, :created_at, FALSE)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':id' => $id,
                    ':name' => 'Campanha Exemplo ' . ($i + 1),
                    ':description' => 'Campanha criada via seed para testes',
                    ':objetivo' => 'Teste de listagem',
                    ':script' => '',
                    ':data_inicio' => $now,
                    ':data_fim' => '',
                    ':canal' => null,
                    ':owner_token' => $ownerToken,
                    ':created_at' => $now,
                ]);
                $inserted++;
            }

            $total = (int)$pdo->query("SELECT COUNT(*) FROM public.campanhas")->fetchColumn();
            $response->success([
                'inserted' => $inserted,
                'total' => $total
            ], 'Seed realizado com sucesso');
            break;
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

            if ($objetivo === '' && $name !== '') {
                $objetivo = $name;
            }

            $errors = [];
            if ($name === '') $errors['name'] = 'Nome da campanha é obrigatório';
            if (!is_array($mailigs)) $errors['mailigs'] = 'Lista de mailings é obrigatória';
            if (!empty($errors)) {
                $response->validation($errors, 'Campos obrigatórios ausentes');
            }

            // Normaliza estrutura para salvar no banco
            $id = uniqid('cmp_', true);
            $leadsCount = is_array($mailigs) ? count($mailigs) : 0;
            $ownerToken = isset($_GET['token']) ? trim((string)$_GET['token']) : 'anon';
            $createdAt = date('Y-m-d H:i:s');

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureCampanhasTable($pdo);

            // Alocação de contatos por usuário (round-robin) com base em 'responsaveis'
            $responsaveisInput = isset($input['responsaveis']) && is_array($input['responsaveis']) ? $input['responsaveis'] : [];
            $responsaveisIds = [];
            foreach ($responsaveisInput as $r) {
                if (is_array($r) && isset($r['id'])) {
                    $responsaveisIds[] = (string)$r['id'];
                } elseif (is_object($r) && isset($r->id)) {
                    $responsaveisIds[] = (string)$r->id;
                }
            }

            $mailigsAssigned = [];
            if (!empty($responsaveisIds) && is_array($mailigs)) {
                $idx = 0;
                $countResp = count($responsaveisIds);
                foreach ($mailigs as $m) {
                    // Garante estrutura de array
                    if (!is_array($m)) { $m = []; }
                    $m['responsavel_id'] = $responsaveisIds[$idx % $countResp];
                    $mailigsAssigned[] = $m;
                    $idx++;
                }
            } else {
                $mailigsAssigned = is_array($mailigs) ? $mailigs : [];
            }

            $sql = "INSERT INTO public.campanhas (id, name, description, objetivo, script, data_inicio, data_fim, canal, leads_count, responsaveis, mailigs, owner_token, created_at, archived)
                    VALUES (:id, :name, :description, :objetivo, :script, :data_inicio, :data_fim, :canal, :leads_count, :responsaveis, :mailigs, :owner_token, :created_at, FALSE)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':description', $description);
            $stmt->bindValue(':objetivo', $objetivo);
            $stmt->bindValue(':script', (string)($input['script'] ?? ''));
            $stmt->bindValue(':data_inicio', (string)($input['dataInicio'] ?? ''));
            $stmt->bindValue(':data_fim', (string)($input['dataFim'] ?? ''));
            // Canal pode vir como string ou objeto; guarda como texto JSON se necessário
            $canalVal = $input['canal'] ?? null;
            if (is_array($canalVal)) { $canalVal = json_encode($canalVal, JSON_UNESCAPED_UNICODE); }
            $stmt->bindValue(':canal', $canalVal);
            $stmt->bindValue(':leads_count', (int)$leadsCount, PDO::PARAM_INT);
            $stmt->bindValue(':responsaveis', json_encode($responsaveisInput, JSON_UNESCAPED_UNICODE));
            $stmt->bindValue(':mailigs', json_encode($mailigsAssigned, JSON_UNESCAPED_UNICODE));
            $stmt->bindValue(':owner_token', $ownerToken);
            $stmt->bindValue(':created_at', $createdAt);
            $stmt->execute();

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
                'responsaveis' => $responsaveisInput,
                'mailigs' => $mailigsAssigned,
                'ownerToken' => $ownerToken,
                'createdAt' => $createdAt,
            ];

            $response->success(['campanha' => $campanha], 'Campanha criada e salva no banco com sucesso');
            break;

        case 'listar':
            // Lista campanhas direto do banco com paginação
            // Aceita GET ou POST

            // Params por GET
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
            $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 0;

            // Params por POST (JSON)
            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true);
                if (is_array($input)) {
                    $offset = isset($input['offset']) ? (int)$input['offset'] : $offset;
                    $page = isset($input['page']) ? (int)$input['page'] : $page;
                    $limit = isset($input['limit']) ? (int)$input['limit'] : $limit;
                    $perPage = isset($input['per_page']) ? (int)$input['per_page'] : $perPage;
                    $usuarioId = isset($input['usuario_id']) ? trim((string)$input['usuario_id']) : '';
                }
            }

            $effectiveLimit = $perPage > 0 ? $perPage : ($limit > 0 ? $limit : 10);
            $start = ($page > 1) ? (($page - 1) * $effectiveLimit) : $offset;
            if ($start < 0) $start = 0;

            // Suporte a filtro por usuário participante
            if (!isset($usuarioId)) {
                $usuarioId = isset($_GET['usuario_id']) ? trim((string)$_GET['usuario_id']) : '';
            }

            // Função helper para normalizar linhas em objetos esperados pelo app
            $normalize = function(array $row) {
                // Responsáveis podem vir como JSON/texto; tentar decodificar
                $responsaveis = [];
                if (isset($row['responsaveis'])) {
                    if (is_array($row['responsaveis'])) {
                        $responsaveis = $row['responsaveis'];
                    } else {
                        $tmp = json_decode((string)$row['responsaveis'], true);
                        if (is_array($tmp)) $responsaveis = $tmp; else $responsaveis = [];
                    }
                }

                return [
                    'id' => (string)($row['id'] ?? $row['campanha_id'] ?? ''),
                    'name' => (string)($row['name'] ?? $row['nome'] ?? $row['titulo'] ?? ''),
                    'description' => (string)($row['description'] ?? $row['descricao'] ?? ''),
                    'objetivo' => (string)($row['objetivo'] ?? $row['goal'] ?? ''),
                    'leadsCount' => (int)($row['leads_count'] ?? $row['leadsCount'] ?? 0),
                    'script' => (string)($row['script'] ?? ''),
                    'dataInicio' => (string)($row['data_inicio'] ?? $row['dataInicio'] ?? ''),
                    'dataFim' => (string)($row['data_fim'] ?? $row['dataFim'] ?? ''),
                    'canal' => $row['canal'] ?? null,
                    'responsaveis' => $responsaveis,
                    'mailigs' => []
                ];
            };

            // Consulta ao banco com fallback de criação de tabela
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureCampanhasTable($pdo);

            $items = [];
            $total = 0;
            try {
                $sql = "SELECT id, name, description, objetivo, leads_count, script, data_inicio, data_fim, canal, responsaveis, mailigs, created_at
                        FROM public.campanhas
                        WHERE (archived IS NULL OR archived = false)
                        ORDER BY COALESCE(created_at, NOW()) DESC
                        LIMIT :limit OFFSET :offset";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':limit', (int)$effectiveLimit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', (int)$start, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $responsaveis = [];
                    if (isset($row['responsaveis'])) {
                        $tmp = json_decode((string)$row['responsaveis'], true);
                        if (is_array($tmp)) $responsaveis = $tmp;
                    }
                    // Se solicitado, filtra campanhas em que o usuário participa
                    if ($usuarioId !== '') {
                        $participa = false;
                        foreach ($responsaveis as $r) {
                            $rid = '';
                            if (is_array($r) && isset($r['id'])) { $rid = (string)$r['id']; }
                            elseif (is_object($r) && isset($r->id)) { $rid = (string)$r->id; }
                            if ($rid !== '' && $rid === $usuarioId) { $participa = true; break; }
                        }
                        if (!$participa) { continue; }
                    }
                    $items[] = [
                        'id' => (string)$row['id'],
                        'name' => (string)($row['name'] ?? ''),
                        'description' => (string)($row['description'] ?? ''),
                        'objetivo' => (string)($row['objetivo'] ?? ''),
                        'leadsCount' => (int)($row['leads_count'] ?? 0),
                        'script' => (string)($row['script'] ?? ''),
                        'dataInicio' => (string)($row['data_inicio'] ?? ''),
                        'dataFim' => (string)($row['data_fim'] ?? ''),
                        'canal' => $row['canal'] ?? null,
                        'responsaveis' => $responsaveis,
                        'mailigs' => [],
                    ];
                }

                $countSql = "SELECT COUNT(*) FROM public.campanhas WHERE (archived IS NULL OR archived = false)";
                $total = (int)$pdo->query($countSql)->fetchColumn();
            } catch (Exception $e) {
                // Em caso de erro inesperado, retorna vazio
                $items = [];
                $total = 0;
            }

            $hasMore = ($start + $effectiveLimit) < $total;
            $response->success([
                'items' => $items,
                'offset' => $start,
                'page' => $page > 0 ? $page : 1,
                'limit' => $effectiveLimit,
                'hasMore' => $hasMore,
                'total' => $total
            ], 'Campanhas listadas com sucesso');
            break;

        case 'listar-todas':
            // Mesma lógica de listar, sem token, direto no banco
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
                    $usuarioId = isset($input['usuario_id']) ? trim((string)$input['usuario_id']) : '';
                }
            }

            $effectiveLimit = $perPage > 0 ? $perPage : ($limit > 0 ? $limit : 10);
            $start = ($page > 1) ? (($page - 1) * $effectiveLimit) : $offset;
            if ($start < 0) $start = 0;

            // Suporte a filtro por usuário participante
            if (!isset($usuarioId)) {
                $usuarioId = isset($_GET['usuario_id']) ? trim((string)$_GET['usuario_id']) : '';
            }

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureCampanhasTable($pdo);

            $items = [];
            $total = 0;
            try {
                $sql = "SELECT id, name, description, objetivo, leads_count, script, data_inicio, data_fim, canal, responsaveis, mailigs, created_at
                        FROM public.campanhas
                        WHERE (archived IS NULL OR archived = false)
                        ORDER BY COALESCE(created_at, NOW()) DESC
                        LIMIT :limit OFFSET :offset";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':limit', (int)$effectiveLimit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', (int)$start, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $responsaveis = [];
                    if (isset($row['responsaveis'])) {
                        $tmp = json_decode((string)$row['responsaveis'], true);
                        if (is_array($tmp)) $responsaveis = $tmp;
                    }
                    if ($usuarioId !== '') {
                        $participa = false;
                        foreach ($responsaveis as $r) {
                            $rid = '';
                            if (is_array($r) && isset($r['id'])) { $rid = (string)$r['id']; }
                            elseif (is_object($r) && isset($r->id)) { $rid = (string)$r->id; }
                            if ($rid !== '' && $rid === $usuarioId) { $participa = true; break; }
                        }
                        if (!$participa) { continue; }
                    }
                    $items[] = [
                        'id' => (string)$row['id'],
                        'name' => (string)($row['name'] ?? ''),
                        'description' => (string)($row['description'] ?? ''),
                        'objetivo' => (string)($row['objetivo'] ?? ''),
                        'leadsCount' => (int)($row['leads_count'] ?? 0),
                        'script' => (string)($row['script'] ?? ''),
                        'dataInicio' => (string)($row['data_inicio'] ?? ''),
                        'dataFim' => (string)($row['data_fim'] ?? ''),
                        'canal' => $row['canal'] ?? null,
                        'responsaveis' => $responsaveis,
                        'mailigs' => [],
                    ];
                }

                $countSql = "SELECT COUNT(*) FROM public.campanhas WHERE (archived IS NULL OR archived = false)";
                $total = (int)$pdo->query($countSql)->fetchColumn();
            } catch (Exception $e) {
                $items = [];
                $total = 0;
            }

            $hasMore = ($start + $effectiveLimit) < $total;
            $response->success([
                'items' => $items,
                'offset' => $start,
                'page' => $page > 0 ? $page : 1,
                'limit' => $effectiveLimit,
                'hasMore' => $hasMore,
                'total' => $total
            ], 'Campanhas (todas) listadas com sucesso');
            break;

        case 'update':
            // Atualiza uma campanha existente na tabela (PUT/POST)
            if (!in_array($method, ['PUT', 'POST'])) {
                $response->error('Método não suportado. Use PUT ou POST.', HTTP_METHOD_NOT_ALLOWED);
            }
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);
            if (!$input || !is_array($input) || empty($input['id'])) {
                $response->validation(['id' => 'ID da campanha é obrigatório'], 'Dados inválidos');
            }

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureCampanhasTable($pdo);

            // Campos permitidos para update
            $fields = [
                'name', 'description', 'objetivo', 'script',
                'dataInicio', 'dataFim', 'canal', 'responsaveis', 'mailigs',
                'leadsCount', 'archived'
            ];
            $setParts = [];
            $params = [':id' => $input['id']];
            foreach ($fields as $f) {
                if (array_key_exists($f, $input)) {
                    switch ($f) {
                        case 'dataInicio': $col = 'data_inicio'; break;
                        case 'dataFim': $col = 'data_fim'; break;
                        case 'leadsCount': $col = 'leads_count'; break;
                        default: $col = $f; break;
                    }
                    $setParts[] = "$col = :$col";
                    $val = $input[$f];
                    if (in_array($f, ['responsaveis','mailigs'])) {
                        $val = json_encode($val, JSON_UNESCAPED_UNICODE);
                    }
                    $params[":$col"] = $val;
                }
            }
            if (empty($setParts)) {
                $response->validation(['fields' => 'Nenhum campo para atualizar'], 'Dados inválidos');
            }
            $sql = "UPDATE public.campanhas SET " . implode(', ', $setParts) . " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // Retorna registro atualizado
            $stmt2 = $pdo->prepare("SELECT id, name, description, objetivo, leads_count, script, data_inicio, data_fim, canal, responsaveis, mailigs, archived, created_at FROM public.campanhas WHERE id = :id");
            $stmt2->execute([':id' => $input['id']]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $response->error('Campanha não encontrada após atualização', 404); }

            $resp = [];
            $resp['id'] = (string)$row['id'];
            $resp['name'] = (string)($row['name'] ?? '');
            $resp['description'] = (string)($row['description'] ?? '');
            $resp['objetivo'] = (string)($row['objetivo'] ?? '');
            $resp['leadsCount'] = (int)($row['leads_count'] ?? 0);
            $resp['script'] = (string)($row['script'] ?? '');
            $resp['dataInicio'] = (string)($row['data_inicio'] ?? '');
            $resp['dataFim'] = (string)($row['data_fim'] ?? '');
            $resp['canal'] = $row['canal'] ?? null;
            $resp['responsaveis'] = json_decode((string)($row['responsaveis'] ?? '[]'), true) ?? [];
            $resp['mailigs'] = [];
            $response->success(['campanha' => $resp], 'Campanha atualizada com sucesso');
            break;

        case 'delete':
            // Arquiva campanha (DELETE/POST) no banco
            if (!in_array($method, ['DELETE', 'POST'])) {
                $response->error('Método não suportado. Use DELETE ou POST.', HTTP_METHOD_NOT_ALLOWED);
            }
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);
            if (!$input || !is_array($input) || empty($input['id'])) {
                $response->validation(['id' => 'ID da campanha é obrigatório'], 'Dados inválidos');
            }
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureCampanhasTable($pdo);
            $stmt = $pdo->prepare('UPDATE public.campanhas SET archived = TRUE WHERE id = :id');
            $stmt->execute([':id' => $input['id']]);
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
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureCampanhasTable($pdo);
            $stmt = $pdo->prepare('UPDATE public.campanhas SET archived = TRUE WHERE id = :id');
            $stmt->execute([':id' => $input['id']]);
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
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureCampanhasTable($pdo);
            $stmt = $pdo->prepare('UPDATE public.campanhas SET archived = FALSE WHERE id = :id');
            $stmt->execute([':id' => $input['id']]);
            $response->success(null, 'Campanha restaurada com sucesso');
            break;

        case 'contatos':
            // Lista os contatos (mailigs) vinculados a uma campanha específica
            // Aceita GET ou POST com parâmetros: id (obrigatório), offset, limit
            if (!in_array($method, ['GET', 'POST'])) {
                $response->error('Método não suportado. Use GET ou POST.', HTTP_METHOD_NOT_ALLOWED);
            }

            $id = isset($_GET['id']) ? trim((string)$_GET['id']) : '';
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
            $usuarioId = isset($_GET['usuario_id']) ? trim((string)$_GET['usuario_id']) : '';

            if ($method === 'POST') {
                $raw = file_get_contents('php://input');
                $input = json_decode($raw, true);
                if (is_array($input)) {
                    $id = isset($input['id']) ? trim((string)$input['id']) : $id;
                    $offset = isset($input['offset']) ? (int)$input['offset'] : $offset;
                    $limit = isset($input['limit']) ? (int)$input['limit'] : $limit;
                    $usuarioId = isset($input['usuario_id']) ? trim((string)$input['usuario_id']) : $usuarioId;
                }
            }

            if ($id === '') {
                $response->validation(['id' => 'ID da campanha é obrigatório'], 'Dados inválidos');
            }

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureCampanhasTable($pdo);

            try {
                $stmt = $pdo->prepare("SELECT mailigs, leads_count FROM public.campanhas WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $response->success([
                        'items' => [],
                        'offset' => 0,
                        'limit' => $limit,
                        'total' => 0,
                        'id' => $id
                    ], 'Campanha não encontrada');
                }

                $mailigsJson = (string)($row['mailigs'] ?? '[]');
                $mailigsArr = json_decode($mailigsJson, true);
                if (!is_array($mailigsArr)) { $mailigsArr = []; }

                $total = count($mailigsArr);
                if ($offset < 0) $offset = 0;
                if ($limit <= 0) $limit = 20;
                if ($offset > $total) { $offset = $total; }
                // Se usuário foi informado, filtra os itens atribuídos a ele
                if ($usuarioId !== '') {
                    $mailigsArr = array_values(array_filter($mailigsArr, function($m) use ($usuarioId) {
                        if (is_array($m)) {
                            $rid = isset($m['responsavel_id']) ? (string)$m['responsavel_id'] : '';
                            return $rid !== '' && $rid === $usuarioId;
                        } elseif (is_object($m)) {
                            $rid = isset($m->responsavel_id) ? (string)$m->responsavel_id : '';
                            return $rid !== '' && $rid === $usuarioId;
                        }
                        return false;
                    }));
                }

                $items = array_slice($mailigsArr, $offset, $limit);

                $response->success([
                    'items' => $items,
                    'offset' => $offset,
                    'limit' => $limit,
                    'total' => $total,
                    'id' => $id,
                ], 'Contatos da campanha listados com sucesso');
            } catch (Exception $e) {
                $response->error('Falha ao consultar contatos: ' . $e->getMessage(), 500);
            }
            break;

        default:
            $response->error('Ação de campanhas não suportada: ' . $action, 404);
    }
} catch (Exception $e) {
    $response->error('Erro na rota campanhas: ' . $e->getMessage(), 500);
}
?>