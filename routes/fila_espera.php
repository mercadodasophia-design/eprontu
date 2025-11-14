<?php
/**
 * Rotas para o módulo de Filas de Espera SUS
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../classes/Crypto.php';

$db = Database::getInstance();
$response = new Response();

// Obter método e ação
$method = $_SERVER['REQUEST_METHOD'];
$action = $segments[1] ?? '';
$id = $segments[2] ?? null;

try {
    switch ($action) {
        case 'criar':
            if ($method !== 'POST') {
                $response->error('Método não permitido', 405);
                break;
            }

            // Obter dados criptografados
            $input = json_decode(file_get_contents('php://input'), true);
            if (!isset($input['dados'])) {
                $response->error('Dados não fornecidos', 400);
                break;
            }

            // Descriptografar dados
            $dadosJson = Crypto::decryptData($input['dados']);
            $dados = json_decode($dadosJson, true);

            if (!$dados) {
                $response->error('Dados inválidos', 400);
                break;
            }

            // Validar campos obrigatórios
            $camposObrigatorios = [
                'fila', 'paciente_id', 'procedimento_id', 'especialidade_id',
                'unidade_id', 'medico_solicitante_id', 'data_solicitacao',
                'motivo_clinico'
            ];

            foreach ($camposObrigatorios as $campo) {
                if (!isset($dados[$campo])) {
                    $response->error("Campo obrigatório ausente: $campo", 400);
                    break 2;
                }
            }

            // Extrair dados da fila
            $fila = $dados['fila'];
            $filaId = is_array($fila) ? ($fila['id'] ?? null) : $fila;

            // Preparar dados para inserção
            $sql = "INSERT INTO fila_espera (
                fila_id,
                paciente_id,
                procedimento_id,
                especialidade_id,
                unidade_id,
                medico_solicitante_id,
                usuario_regulador_id,
                status,
                prioridade,
                pontuacao_clinica,
                data_solicitacao,
                data_prazo,
                data_entrada_fila,
                motivo_clinico,
                observacoes_regulacao,
                posicao_fila,
                tempo_espera_estimado,
                created_at,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            RETURNING id";

            $params = [
                $filaId,
                $dados['paciente_id'],
                $dados['procedimento_id'],
                $dados['especialidade_id'],
                $dados['unidade_id'],
                $dados['medico_solicitante_id'],
                $dados['usuario_regulador_id'] ?? null,
                $dados['status'] ?? 'pendente',
                $dados['prioridade'] ?? 'eletiva',
                $dados['pontuacao_clinica'] ?? 0,
                $dados['data_solicitacao'],
                $dados['data_prazo'] ?? null,
                $dados['data_entrada_fila'] ?? date('Y-m-d H:i:s'),
                $dados['motivo_clinico'],
                $dados['observacoes_regulacao'] ?? null,
                $dados['posicao_fila'] ?? 0,
                $dados['tempo_espera_estimado'] ?? 0,
            ];

            // Executar inserção
            $result = $db->query($sql, $params);
            $novaFilaId = $result[0]['id'] ?? null;

            if (!$novaFilaId) {
                $response->error('Erro ao criar fila', 500);
                break;
            }

            // Buscar fila criada
            $sqlBuscar = "SELECT * FROM fila_espera WHERE id = ?";
            $filaCriada = $db->fetchOne($sqlBuscar, [$novaFilaId]);

            if (!$filaCriada) {
                $response->error('Fila criada mas não encontrada', 500);
                break;
            }

            // Criptografar resposta
            $respostaJson = json_encode($filaCriada);
            $respostaCriptografada = Crypto::encryptString($respostaJson);

            // Retornar no formato esperado pelo Flutter (apenas 'dados')
            header('Content-Type: application/json');
            echo json_encode([
                'dados' => $respostaCriptografada
            ]);
            http_response_code(201);
            exit;
            break;

        case 'listar':
            if ($method !== 'GET') {
                $response->error('Método não permitido', 405);
                break;
            }

            // Construir query com filtros
            $where = [];
            $params = [];

            if (isset($_GET['tipo_fila'])) {
                $where[] = "f.tipo_fila = ?";
                $params[] = $_GET['tipo_fila'];
            }

            if (isset($_GET['especialidade_id'])) {
                $where[] = "fe.especialidade_id = ?";
                $params[] = $_GET['especialidade_id'];
            }

            if (isset($_GET['unidade_id'])) {
                $where[] = "fe.unidade_id = ?";
                $params[] = $_GET['unidade_id'];
            }

            if (isset($_GET['status'])) {
                $where[] = "fe.status = ?";
                $params[] = $_GET['status'];
            }

            if (isset($_GET['prioridade'])) {
                $where[] = "fe.prioridade = ?";
                $params[] = $_GET['prioridade'];
            }

            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT fe.* FROM fila_espera fe
                    LEFT JOIN filas f ON fe.fila_id = f.id
                    $whereClause
                    ORDER BY fe.data_entrada_fila ASC";

            $filas = $db->fetchAll($sql, $params);

            // Criptografar resposta
            $respostaJson = json_encode($filas);
            $respostaCriptografada = Crypto::encryptString($respostaJson);

            // Retornar no formato esperado pelo Flutter (apenas 'dados')
            header('Content-Type: application/json');
            echo json_encode([
                'dados' => $respostaCriptografada
            ]);
            exit;
            break;

        default:
            $response->error('Ação não encontrada: ' . $action, 404);
            break;
    }
} catch (Exception $e) {
    $response->error('Erro: ' . $e->getMessage(), 500);
}

