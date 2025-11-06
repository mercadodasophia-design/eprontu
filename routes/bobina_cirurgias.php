<?php
/**
 * Rotas de Procedimentos Cirúrgicos da Bobina
 * Endpoints: /api/bobina/cirurgias/{acao}
 * Ações suportadas: catarata, glaucoma, retina, cornea, lio
 */

// Funções de validação
function validarCatarata($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    if (empty($payload['tipo_cirurgia'])) {
        $errors[] = 'tipo_cirurgia é obrigatório';
    }
    return $errors;
}

function validarGlaucoma($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    if (empty($payload['tipo_cirurgia'])) {
        $errors[] = 'tipo_cirurgia é obrigatório';
    }
    return $errors;
}

function validarRetinaCirurgia($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    if (empty($payload['tipo_cirurgia'])) {
        $errors[] = 'tipo_cirurgia é obrigatório';
    }
    return $errors;
}

function validarCornea($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    if (empty($payload['tipo_cirurgia'])) {
        $errors[] = 'tipo_cirurgia é obrigatório';
    }
    return $errors;
}

function validarLio($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    if (empty($payload['tipo_lio'])) {
        $errors[] = 'tipo_lio é obrigatório';
    }
    if (empty($payload['potencia']) || !is_numeric($payload['potencia'])) {
        $errors[] = 'potencia deve ser numérica';
    }
    return $errors;
}

// Switch para diferentes métodos HTTP
switch ($method) {
    case 'POST':
        switch ($action) {
            case 'catarata':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarCatarata($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO cirurgias_catarata (paciente, medico, data_cirurgia, hora_cirurgia, olho, tipo_cirurgia, tecnica, complicacoes, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['tipo_cirurgia'],
                        $input['tecnica'] ?? null,
                        $input['complicacoes'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cirurgia de catarata registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar cirurgia de catarata: ' . $e->getMessage()]);
                }
                break;
                
            case 'glaucoma':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarGlaucoma($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO cirurgias_glaucoma (paciente, medico, data_cirurgia, hora_cirurgia, olho, tipo_cirurgia, tecnica, complicacoes, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['tipo_cirurgia'],
                        $input['tecnica'] ?? null,
                        $input['complicacoes'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cirurgia de glaucoma registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar cirurgia de glaucoma: ' . $e->getMessage()]);
                }
                break;
                
            case 'retina':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarRetinaCirurgia($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO cirurgias_retina (paciente, medico, data_cirurgia, hora_cirurgia, olho, tipo_cirurgia, tecnica, complicacoes, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['tipo_cirurgia'],
                        $input['tecnica'] ?? null,
                        $input['complicacoes'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cirurgia de retina registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar cirurgia de retina: ' . $e->getMessage()]);
                }
                break;
                
            case 'cornea':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarCornea($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO cirurgias_cornea (paciente, medico, data_cirurgia, hora_cirurgia, olho, tipo_cirurgia, tecnica, complicacoes, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['tipo_cirurgia'],
                        $input['tecnica'] ?? null,
                        $input['complicacoes'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Cirurgia de córnea registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar cirurgia de córnea: ' . $e->getMessage()]);
                }
                break;
                
            case 'lio':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarLio($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO lio (paciente, medico, data_implante, hora_implante, olho, tipo_lio, potencia, material, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['tipo_lio'],
                        $input['potencia'],
                        $input['material'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'LIO registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar LIO: ' . $e->getMessage()]);
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
            case 'catarata':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM cirurgias_catarata WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_cirurgia = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_cirurgia DESC, hora_cirurgia DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar cirurgias de catarata: ' . $e->getMessage()]);
                }
                break;
                
            case 'glaucoma':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM cirurgias_glaucoma WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_cirurgia = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_cirurgia DESC, hora_cirurgia DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar cirurgias de glaucoma: ' . $e->getMessage()]);
                }
                break;
                
            case 'retina':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM cirurgias_retina WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_cirurgia = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_cirurgia DESC, hora_cirurgia DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar cirurgias de retina: ' . $e->getMessage()]);
                }
                break;
                
            case 'cornea':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM cirurgias_cornea WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_cirurgia = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_cirurgia DESC, hora_cirurgia DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar cirurgias de córnea: ' . $e->getMessage()]);
                }
                break;
                
            case 'lio':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM lio WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_implante = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_implante DESC, hora_implante DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar LIOs: ' . $e->getMessage()]);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Ação não encontrada']);
                break;
        }
        break;
        
    case 'PUT':
        // Implementar atualizações
        http_response_code(501);
        echo json_encode(['error' => 'Atualização não implementada ainda']);
        break;
        
    case 'DELETE':
        // Implementar exclusões
        http_response_code(501);
        echo json_encode(['error' => 'Exclusão não implementada ainda']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
}
?>
