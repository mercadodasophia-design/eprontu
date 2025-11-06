<?php
/**
 * Rotas de Medicamentos e Prescrições da Bobina
 * Endpoints: /api/bobina/medicamentos/{acao}
 * Ações suportadas: prescricao, medicamento, dosagem, posologia
 */

// Funções de validação
function validarPrescricao($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    if (empty($payload['medico']) || !is_numeric($payload['medico'])) {
        $errors[] = 'medico é obrigatório';
    }
    if (empty($payload['medicamentos']) || !is_array($payload['medicamentos'])) {
        $errors[] = 'medicamentos é obrigatório e deve ser um array';
    }
    return $errors;
}

function validarMedicamento($payload) {
    $errors = [];
    if (empty($payload['nome'])) {
        $errors[] = 'nome do medicamento é obrigatório';
    }
    if (empty($payload['dosagem'])) {
        $errors[] = 'dosagem é obrigatória';
    }
    if (empty($payload['posologia'])) {
        $errors[] = 'posologia é obrigatória';
    }
    return $errors;
}

function validarDosagem($payload) {
    $errors = [];
    if (empty($payload['medicamento_id']) || !is_numeric($payload['medicamento_id'])) {
        $errors[] = 'medicamento_id é obrigatório';
    }
    if (empty($payload['quantidade']) || !is_numeric($payload['quantidade'])) {
        $errors[] = 'quantidade deve ser numérica';
    }
    if (empty($payload['unidade'])) {
        $errors[] = 'unidade é obrigatória';
    }
    return $errors;
}

function validarPosologia($payload) {
    $errors = [];
    if (empty($payload['medicamento_id']) || !is_numeric($payload['medicamento_id'])) {
        $errors[] = 'medicamento_id é obrigatório';
    }
    if (empty($payload['frequencia'])) {
        $errors[] = 'frequencia é obrigatória';
    }
    if (empty($payload['duracao'])) {
        $errors[] = 'duracao é obrigatória';
    }
    return $errors;
}

// Switch para diferentes métodos HTTP
switch ($method) {
    case 'POST':
        switch ($action) {
            case 'prescricao':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarPrescricao($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $db->beginTransaction();
                    
                    // Inserir prescrição
                    $sql = "INSERT INTO prescricoes (paciente, medico, data_prescricao, hora_prescricao, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'],
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $prescricao_id = $db->lastInsertId();
                    
                    // Inserir medicamentos
                    foreach ($input['medicamentos'] as $medicamento) {
                        $sql_med = "INSERT INTO prescricoes_medicamentos (prescricao_id, medicamento_id, dosagem, posologia, quantidade, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
                        $params_med = [
                            $prescricao_id,
                            $medicamento['medicamento_id'],
                            $medicamento['dosagem'],
                            $medicamento['posologia'],
                            $medicamento['quantidade'] ?? 1,
                            $medicamento['observacoes'] ?? null
                        ];
                        
                        $stmt_med = $db->prepare($sql_med);
                        $stmt_med->execute($params_med);
                    }
                    
                    $db->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Prescrição registrada com sucesso',
                        'prescricao_id' => $prescricao_id
                    ]);
                } catch (Exception $e) {
                    $db->rollBack();
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar prescrição: ' . $e->getMessage()]);
                }
                break;
                
            case 'medicamento':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarMedicamento($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO medicamentos (nome, principio_ativo, dosagem, posologia, categoria, observacoes) VALUES (?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['nome'],
                        $input['principio_ativo'] ?? null,
                        $input['dosagem'],
                        $input['posologia'],
                        $input['categoria'] ?? null,
                        $input['observacoes'] ?? null
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Medicamento registrado com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar medicamento: ' . $e->getMessage()]);
                }
                break;
                
            case 'dosagem':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarDosagem($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO dosagens (medicamento_id, quantidade, unidade, via_administracao, observacoes) VALUES (?, ?, ?, ?, ?)";
                    $params = [
                        $input['medicamento_id'],
                        $input['quantidade'],
                        $input['unidade'],
                        $input['via_administracao'] ?? null,
                        $input['observacoes'] ?? null
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Dosagem registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar dosagem: ' . $e->getMessage()]);
                }
                break;
                
            case 'posologia':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarPosologia($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO posologias (medicamento_id, frequencia, duracao, horarios, observacoes) VALUES (?, ?, ?, ?, ?)";
                    $params = [
                        $input['medicamento_id'],
                        $input['frequencia'],
                        $input['duracao'],
                        $input['horarios'] ?? null,
                        $input['observacoes'] ?? null
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Posologia registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar posologia: ' . $e->getMessage()]);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Ação não encontrada']);
                break;
        }
        break;
        
    case 'GET':
        switch ($action) {
            case 'prescricao':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT p.*, m.nome as medico_nome FROM prescricoes p 
                            LEFT JOIN medicos m ON p.medico = m.codigo 
                            WHERE p.paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND p.data_prescricao = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY p.data_prescricao DESC, p.hora_prescricao DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $prescricoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Buscar medicamentos para cada prescrição
                    foreach ($prescricoes as &$prescricao) {
                        $sql_med = "SELECT pm.*, m.nome as medicamento_nome, m.principio_ativo 
                                   FROM prescricoes_medicamentos pm 
                                   LEFT JOIN medicamentos m ON pm.medicamento_id = m.id 
                                   WHERE pm.prescricao_id = ?";
                        $stmt_med = $db->prepare($sql_med);
                        $stmt_med->execute([$prescricao['id']]);
                        $prescricao['medicamentos'] = $stmt_med->fetchAll(PDO::FETCH_ASSOC);
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $prescricoes
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar prescrições: ' . $e->getMessage()]);
                }
                break;
                
            case 'medicamento':
                $id = $_GET['id'] ?? null;
                $nome = $_GET['nome'] ?? null;
                
                try {
                    if ($id) {
                        $sql = "SELECT * FROM medicamentos WHERE id = ?";
                        $params = [$id];
                    } elseif ($nome) {
                        $sql = "SELECT * FROM medicamentos WHERE nome LIKE ?";
                        $params = ["%$nome%"];
                    } else {
                        $sql = "SELECT * FROM medicamentos ORDER BY nome";
                        $params = [];
                    }
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar medicamentos: ' . $e->getMessage()]);
                }
                break;
                
            case 'dosagem':
                $medicamento_id = $_GET['medicamento_id'] ?? null;
                
                if (!$medicamento_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'medicamento_id é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM dosagens WHERE medicamento_id = ? ORDER BY quantidade";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$medicamento_id]);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar dosagens: ' . $e->getMessage()]);
                }
                break;
                
            case 'posologia':
                $medicamento_id = $_GET['medicamento_id'] ?? null;
                
                if (!$medicamento_id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'medicamento_id é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM posologias WHERE medicamento_id = ? ORDER BY frequencia";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$medicamento_id]);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar posologias: ' . $e->getMessage()]);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Ação não encontrada']);
                break;
        }
        break;
        
    case 'PUT':
        switch ($action) {
            case 'prescricao':
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID da prescrição é obrigatório']);
                    exit;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                
                try {
                    $sql = "UPDATE prescricoes SET observacoes = ?, data_prescricao = ?, hora_prescricao = ? WHERE id = ?";
                    $params = [
                        $input['observacoes'] ?? null,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        $id
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Prescrição atualizada com sucesso'
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao atualizar prescrição: ' . $e->getMessage()]);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Ação não encontrada']);
                break;
        }
        break;
        
    case 'DELETE':
        switch ($action) {
            case 'prescricao':
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID da prescrição é obrigatório']);
                    exit;
                }
                
                try {
                    $db->beginTransaction();
                    
                    // Deletar medicamentos da prescrição
                    $sql_med = "DELETE FROM prescricoes_medicamentos WHERE prescricao_id = ?";
                    $stmt_med = $db->prepare($sql_med);
                    $stmt_med->execute([$id]);
                    
                    // Deletar prescrição
                    $sql = "DELETE FROM prescricoes WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$id]);
                    
                    $db->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Prescrição excluída com sucesso'
                    ]);
                } catch (Exception $e) {
                    $db->rollBack();
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao excluir prescrição: ' . $e->getMessage()]);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Ação não encontrada']);
                break;
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
}
?>
