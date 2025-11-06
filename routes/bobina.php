<?php
// Rota Bobina agregadora para listagem com filtros de campanha

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Response.php';

$response = new Response();

// Usa variáveis compartilhadas de index.php: $method, $segments, $action
if (!isset($method) || !isset($segments)) {
    // Fallback se acessado diretamente
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $segments = explode('/', trim($uri, '/'));
    // remove e-prontu/api se presentes
    if (isset($segments[0]) && $segments[0] === 'e-prontu') array_shift($segments);
    if (isset($segments[0]) && $segments[0] === 'api') array_shift($segments);
    $action = $segments[1] ?? '';
}

$page = isset($segments[2]) ? (int)$segments[2] : 1;
$limit = isset($segments[3]) ? (int)$segments[3] : 50;
$encrypt = isset($_GET['encrypt']) ? ($_GET['encrypt'] !== 'false') : false;

try {
    if ($action === 'listar' && in_array($method, ['POST','GET'])) {
        $db = Database::getInstance();

        // Ler filtros do body JSON (POST) ou query (GET)
        $input = [];
        if ($method === 'POST') {
            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true) ?: [];
        }

        $filtros = $input['filtros'] ?? $input;

        $filtros_profissionais = (isset($filtros['filtros_profissionais']) && is_array($filtros['filtros_profissionais'])) ? $filtros['filtros_profissionais'] : [];
        $filtros_convenios     = (isset($filtros['filtros_convenios']) && is_array($filtros['filtros_convenios'])) ? $filtros['filtros_convenios'] : [];
        $filtros_unidades      = (isset($filtros['filtros_unidades']) && is_array($filtros['filtros_unidades'])) ? $filtros['filtros_unidades'] : [];
        $filtros_procedimentos = (isset($filtros['filtros_procedimentos']) && is_array($filtros['filtros_procedimentos'])) ? $filtros['filtros_procedimentos'] : [];
        $dateStart             = isset($filtros['dateStart']) ? $filtros['dateStart'] : null;
        $dateEnd               = isset($filtros['dateEnd']) ? $filtros['dateEnd'] : null;

        $params = [];
        $where = [];

        if ($dateStart && $dateEnd) {
            $where[] = "a.datamovimento BETWEEN ?::date AND ?::date";
            $params[] = $dateStart;
            $params[] = $dateEnd;
        } elseif ($dateStart) {
            $where[] = "a.datamovimento >= ?::date";
            $params[] = $dateStart;
        } elseif ($dateEnd) {
            $where[] = "a.datamovimento <= ?::date";
            $params[] = $dateEnd;
        }

        if (count($filtros_profissionais) > 0) {
            $ids = [];
            foreach ($filtros_profissionais as $clip) {
                $ids[] = is_array($clip) && isset($clip['id']) ? (string)$clip['id'] : (string)$clip;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "a.codprofissional IN ($placeholders)";
            $params = array_merge($params, $ids);
        }

        if (count($filtros_convenios) > 0) {
            $ids = [];
            foreach ($filtros_convenios as $clip) {
                $ids[] = is_array($clip) && isset($clip['id']) ? (string)$clip['id'] : (string)$clip;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "a.convenio IN ($placeholders)";
            $params = array_merge($params, $ids);
        }

        if (count($filtros_unidades) > 0) {
            $ids = [];
            foreach ($filtros_unidades as $clip) {
                $ids[] = is_array($clip) && isset($clip['id']) ? (string)$clip['id'] : (string)$clip;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where[] = "a.unidade IN ($placeholders)";
            $params = array_merge($params, $ids);
        }

        if (count($filtros_procedimentos) > 0) {
            $likes = [];
            foreach ($filtros_procedimentos as $clip) {
                $likes[] = "a.procedimentos ILIKE ?";
                $params[] = '%' . (is_array($clip) && isset($clip['id']) ? (string)$clip['id'] : (string)$clip) . '%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }

        $whereSql = count($where) > 0 ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT 
                a.movimento AS id,
                to_char(a.datamovimento, 'YYYY-MM-DD') AS data,
                a.paciente AS paciente,
                COALESCE(TRIM(a.procedimentos),'') AS procedimento,
                COALESCE(TRIM(prof.profissional),'') AS medico,
                COALESCE(conv.convenio,'') AS convenio,
                COALESCE(u.unidades,'') AS unidade,
                'atendimento' AS tipo
            FROM agenda a
            LEFT JOIN profissionais prof ON prof.codprofissional = a.codprofissional
            LEFT JOIN convenios conv ON conv.codconvenio = a.convenio
            LEFT JOIN unidades u ON u.codunidades = a.unidade
            $whereSql
            ORDER BY a.datamovimento DESC, a.movimento DESC
            LIMIT ? OFFSET ?
        ";

        $params[] = (int)$limit;
        $params[] = max(0, ($page - 1) * $limit);

        $dados = $db->fetchAll($sql, $params);

        // Formato esperado no Flutter (status + dados)
        $payload = [
            'status' => 'sucesso',
            'pagina' => $page,
            'itensPorPagina' => $limit,
            'quantidade' => count($dados),
            'dados' => $dados,
        ];

        // Sem criptografia por padrão, compatível com encrypt=false
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
        exit();
    }

    $response->error('Ação não suportada em bobina: ' . $action, 404);
} catch (Exception $e) {
    $response->error('Erro na rota bobina: ' . $e->getMessage(), 500);
}
?>