<?php
/**
 * Rotas de Documentos e Anamnese da Bobina
 * Endpoints: /api/bobina/documentos/{acao}
 * Ações suportadas: anamnese, documento, portfolio, laudo
 */

// Funções de validação
function validarAnamnese($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    if (empty($payload['medico']) || !is_numeric($payload['medico'])) {
        $errors[] = 'medico é obrigatório';
    }
    return $errors;
}

function validarDocumento($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    if (empty($payload['tipo'])) {
        $errors[] = 'tipo do documento é obrigatório';
    }
    if (empty($payload['nome'])) {
        $errors[] = 'nome do documento é obrigatório';
    }
    return $errors;
}

function validarPortfolio($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    if (empty($payload['categoria'])) {
        $errors[] = 'categoria é obrigatória';
    }
    return $errors;
}

function validarLaudo($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    if (empty($payload['medico']) || !is_numeric($payload['medico'])) {
        $errors[] = 'medico é obrigatório';
    }
    if (empty($payload['tipo_exame'])) {
        $errors[] = 'tipo_exame é obrigatório';
    }
    return $errors;
}

// Switch para diferentes métodos HTTP
switch ($method) {
    case 'POST':
        switch ($action) {
            case 'anamnese':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarAnamnese($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO anamnese (paciente, medico, data_anamnese, hora_anamnese, queixa_principal, historia_doenca, antecedentes_pessoais, antecedentes_familiares, medicamentos_uso, alergias, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'],
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        $input['queixa_principal'] ?? null,
                        $input['historia_doenca'] ?? null,
                        $input['antecedentes_pessoais'] ?? null,
                        $input['antecedentes_familiares'] ?? null,
                        $input['medicamentos_uso'] ?? null,
                        $input['alergias'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Anamnese registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar anamnese: ' . $e->getMessage()]);
                }
                break;
                
            case 'documento':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarDocumento($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO documentos (paciente, medico, data_documento, hora_documento, tipo, nome, descricao, arquivo, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? null,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        $input['tipo'],
                        $input['nome'],
                        $input['descricao'] ?? null,
                        $input['arquivo'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Documento registrado com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar documento: ' . $e->getMessage()]);
                }
                break;
                
            case 'portfolio':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarPortfolio($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO portfolio (paciente, medico, data_portfolio, hora_portfolio, categoria, titulo, descricao, arquivo, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? null,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        $input['categoria'],
                        $input['titulo'] ?? null,
                        $input['descricao'] ?? null,
                        $input['arquivo'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Portfólio registrado com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar portfólio: ' . $e->getMessage()]);
                }
                break;
                
            case 'laudo':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarLaudo($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO laudos (paciente, medico, data_laudo, hora_laudo, tipo_exame, olho, resultado, conclusao, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'],
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        $input['tipo_exame'],
                        $input['olho'] ?? null,
                        $input['resultado'] ?? null,
                        $input['conclusao'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Laudo registrado com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar laudo: ' . $e->getMessage()]);
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
            case 'anamnese':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT a.*, m.nome as medico_nome FROM anamnese a 
                            LEFT JOIN medicos m ON a.medico = m.codigo 
                            WHERE a.paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND a.data_anamnese = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY a.data_anamnese DESC, a.hora_anamnese DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar anamnese: ' . $e->getMessage()]);
                }
                break;
                
            case 'documento':
                $prontuario = $_GET['prontuario'] ?? null;
                $tipo = $_GET['tipo'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT d.*, m.nome as medico_nome FROM documentos d 
                            LEFT JOIN medicos m ON d.medico = m.codigo 
                            WHERE d.paciente = ?";
                    $params = [$prontuario];
                    
                    if ($tipo) {
                        $sql .= " AND d.tipo = ?";
                        $params[] = $tipo;
                    }
                    
                    if ($data) {
                        $sql .= " AND d.data_documento = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY d.data_documento DESC, d.hora_documento DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar documentos: ' . $e->getMessage()]);
                }
                break;
                
            case 'portfolio':
                $prontuario = $_GET['prontuario'] ?? null;
                $categoria = $_GET['categoria'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT p.*, m.nome as medico_nome FROM portfolio p 
                            LEFT JOIN medicos m ON p.medico = m.codigo 
                            WHERE p.paciente = ?";
                    $params = [$prontuario];
                    
                    if ($categoria) {
                        $sql .= " AND p.categoria = ?";
                        $params[] = $categoria;
                    }
                    
                    if ($data) {
                        $sql .= " AND p.data_portfolio = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY p.data_portfolio DESC, p.hora_portfolio DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar portfólio: ' . $e->getMessage()]);
                }
                break;
                
            case 'laudo':
                $prontuario = $_GET['prontuario'] ?? null;
                $tipo_exame = $_GET['tipo_exame'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT l.*, m.nome as medico_nome FROM laudos l 
                            LEFT JOIN medicos m ON l.medico = m.codigo 
                            WHERE l.paciente = ?";
                    $params = [$prontuario];
                    
                    if ($tipo_exame) {
                        $sql .= " AND l.tipo_exame = ?";
                        $params[] = $tipo_exame;
                    }
                    
                    if ($data) {
                        $sql .= " AND l.data_laudo = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY l.data_laudo DESC, l.hora_laudo DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar laudos: ' . $e->getMessage()]);
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
            case 'anamnese':
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID da anamnese é obrigatório']);
                    exit;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                
                try {
                    $sql = "UPDATE anamnese SET queixa_principal = ?, historia_doenca = ?, antecedentes_pessoais = ?, antecedentes_familiares = ?, medicamentos_uso = ?, alergias = ?, observacoes = ? WHERE id = ?";
                    $params = [
                        $input['queixa_principal'] ?? null,
                        $input['historia_doenca'] ?? null,
                        $input['antecedentes_pessoais'] ?? null,
                        $input['antecedentes_familiares'] ?? null,
                        $input['medicamentos_uso'] ?? null,
                        $input['alergias'] ?? null,
                        $input['observacoes'] ?? null,
                        $id
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Anamnese atualizada com sucesso'
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao atualizar anamnese: ' . $e->getMessage()]);
                }
                break;
                
            case 'documento':
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID do documento é obrigatório']);
                    exit;
                }
                
                $input = json_decode(file_get_contents('php://input'), true);
                
                try {
                    $sql = "UPDATE documentos SET nome = ?, descricao = ?, observacoes = ? WHERE id = ?";
                    $params = [
                        $input['nome'] ?? null,
                        $input['descricao'] ?? null,
                        $input['observacoes'] ?? null,
                        $id
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Documento atualizado com sucesso'
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao atualizar documento: ' . $e->getMessage()]);
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
            case 'documento':
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID do documento é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "DELETE FROM documentos WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$id]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Documento excluído com sucesso'
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao excluir documento: ' . $e->getMessage()]);
                }
                break;
                
            case 'portfolio':
                $id = $_GET['id'] ?? null;
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['error' => 'ID do portfólio é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "DELETE FROM portfolio WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$id]);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Portfólio excluído com sucesso'
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao excluir portfólio: ' . $e->getMessage()]);
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
