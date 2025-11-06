<?php
/**
 * Rotas de Receituário
 * Endpoints: /api/receituario (POST, GET)
 */

function validarReceituario($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    if (empty($payload['medicamento']) || !is_string($payload['medicamento'])) {
        $errors[] = 'medicamento é obrigatório';
    }
    if (empty($payload['posologia']) || !is_string($payload['posologia'])) {
        $errors[] = 'posologia é obrigatória';
    }
    if (isset($payload['quantidade']) && $payload['quantidade'] !== '' && !is_numeric($payload['quantidade'])) {
        $errors[] = 'quantidade deve ser numérica';
    }
    return $errors;
}

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) {
            $input = $_POST;
        }
        if (!$input || !is_array($input)) {
            $response->error('Payload inválido', HTTP_BAD_REQUEST);
        }

        $errors = validarReceituario($input);
        if (!empty($errors)) {
            $response->validation($errors, 'Dados de receituário inválidos');
        }

        try {
            $db = Database::getInstance();

            $prontuario = (int)$input['prontuario'];
            $medicamento = trim($input['medicamento']);
            $posologia = trim($input['posologia']);
            $quantidade = isset($input['quantidade']) && $input['quantidade'] !== '' ? (string)$input['quantidade'] : null;
            $via = isset($input['via']) ? trim($input['via']) : null;
            $medico = isset($input['medico']) && is_numeric($input['medico']) ? (int)$input['medico'] : 0; // até integrar token

            // Verifica/insere medicamento em 'medicamentos'
            $exists = $db->fetchOne("SELECT codmaterial FROM medicamentos WHERE material = ?", [$medicamento]);
            if (!$exists) {
                $db->insert('medicamentos', [
                    'material' => $medicamento,
                    'categoria' => 15,
                    'tipomedicamento' => 'G'
                ]);
            }

            // Insere prescrição em 'medicamentosemuso'
            $id = $db->insert('medicamentosemuso', [
                'paciente' => $prontuario,
                'medicamentoemuso' => $medicamento,
                'qtde' => $quantidade,
                'vezes' => $posologia,
                'medico' => $medico,
                'data' => date('Y-m-d'),
                'viamed' => $via,
                'conduta' => 'MANTIDA'
            ]);

            $response->success([
                'id' => $id ? (string)$id : null,
                'prontuario' => $prontuario,
                'medicamento' => $medicamento,
                'quantidade' => $quantidade,
                'posologia' => $posologia,
                'via' => $via,
                'medico' => (string)$medico
            ], 'Item de receituário registrado');
        } catch (Exception $e) {
            $response->error('Erro ao salvar receituário', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }
        break;

    case 'GET':
        $schemaOnly = isset($_GET['schema']) && (strtolower((string)$_GET['schema']) === '1' || strtolower((string)$_GET['schema']) === 'true');
        if ($schemaOnly) {
            $response->success([
                'request' => [ 'query' => [ 'prontuario' => 'number (opcional quando schema=1)', 'medico' => 'number (opcional)', 'data' => 'YYYY-MM-DD (opcional)', 'today' => '1|true (opcional)', 'schema' => '1' ] ],
                'response' => [
                    'items' => [[
                        'medicamentoemuso' => 'string',
                        'vezes' => 'string|int|null',
                        'viamed' => 'string|null',
                        'qtde' => 'string|int|null',
                        'conduta' => 'string|null',
                        'data' => 'YYYY-MM-DD',
                        'medico' => 'string|number',
                        'paciente' => 'number'
                    ]],
                    'count' => 'number'
                ]
            ], 'Schema de Receituário');
            break;
        }

        $prontuario = $_GET['prontuario'] ?? null;
        if (!$prontuario || !is_numeric($prontuario) || (int)$prontuario <= 0) {
            $response->error('Prontuário inválido', HTTP_BAD_REQUEST);
        }

        try {
            $db = Database::getInstance();
            $params = [(int)$prontuario];
            $sql = "SELECT medicamentoemuso, vezes, viamed, qtde FROM medicamentosemuso WHERE paciente = ?";

            // Filtros opcionais: medico, data específica, hoje
            $medico = isset($_GET['medico']) && is_numeric($_GET['medico']) ? (int)$_GET['medico'] : null;
            $date = isset($_GET['data']) ? trim($_GET['data']) : null; // formato 'Y-m-d'
            $today = isset($_GET['today']) ? filter_var($_GET['today'], FILTER_VALIDATE_BOOLEAN) : true; // default igual legado

            if ($medico !== null) {
                $sql .= " AND medico = ?";
                $params[] = $medico;
            }
            if ($date) {
                $sql .= " AND data = ?";
                $params[] = $date;
            } elseif ($today) {
                $sql .= " AND data = current_date";
            }

            $sql .= " ORDER BY data DESC";
            $items = $db->fetchAll($sql, $params);

            $response->success([
                'prontuario' => (int)$prontuario,
                'items' => $items,
                'count' => count($items)
            ], 'Receituário consultado');
        } catch (Exception $e) {
            $response->error('Erro ao consultar receituário', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }
        break;

    default:
        $response->error('Método não permitido', HTTP_BAD_REQUEST);
        break;
}
?>