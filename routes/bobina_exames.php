<?php
/**
 * Rotas de Exames Oftalmológicos da Bobina
 * Endpoints: /api/bobina/exames/{acao}
 * Ações suportadas: pio, biomicroscopia, paquimetria, retina, gonioscopia, refracao, campimetria
 */

// Funções de validação
function validarPio($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    if (!isset($payload['pressao']) || $payload['pressao'] === '' || !is_numeric($payload['pressao'])) {
        $errors[] = 'pressao deve ser numérica';
    } else {
        $p = floatval($payload['pressao']);
        if ($p < 0 || $p > 80) { $errors[] = 'pressao fora do intervalo 0–80'; }
    }
    if (isset($payload['alvo']) && $payload['alvo'] !== '' && !is_numeric($payload['alvo'])) {
        $errors[] = 'alvo deve ser numérico';
    }
    return $errors;
}

function validarBiomicroscopia($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    return $errors;
}

function validarPaquimetria($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    if (!isset($payload['espessura']) || $payload['espessura'] === '' || !is_numeric($payload['espessura'])) {
        $errors[] = 'espessura deve ser numérica';
    } else {
        $e = floatval($payload['espessura']);
        if ($e < 200 || $e > 800) { $errors[] = 'espessura fora do intervalo 200–800'; }
    }
    return $errors;
}

function validarRetina($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    return $errors;
}

function validarGonioscopia($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    return $errors;
}

function validarRefracao($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    return $errors;
}

function validarCampimetria($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = strtoupper(trim($payload['olho'] ?? ''));
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    return $errors;
}

// Switch para diferentes métodos HTTP
switch ($method) {
    case 'POST':
        switch ($action) {
            case 'pio':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarPio($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO pio (paciente, medico, data_exame, hora_exame, olho, pressao, alvo, metodo, medicamento, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['pressao'],
                        $input['alvo'] ?? null,
                        $input['metodo'] ?? null,
                        $input['medicamento'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'PIO registrado com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar PIO: ' . $e->getMessage()]);
                }
                break;
                
            case 'biomicroscopia':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarBiomicroscopia($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO biomicroscopia (paciente, medico, data_exame, hora_exame, olho, ceratometria, cornea, cristalino, iris, pupila, camara, angulo, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['ceratometria'] ?? null,
                        $input['cornea'] ?? null,
                        $input['cristalino'] ?? null,
                        $input['iris'] ?? null,
                        $input['pupila'] ?? null,
                        $input['camara'] ?? null,
                        $input['angulo'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Biomicroscopia registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar biomicroscopia: ' . $e->getMessage()]);
                }
                break;
                
            case 'paquimetria':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarPaquimetria($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO paquimetria (paciente, medico, data_exame, hora_exame, olho, espessura, metodo, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['espessura'],
                        $input['metodo'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Paquimetria registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar paquimetria: ' . $e->getMessage()]);
                }
                break;
                
            case 'retina':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarRetina($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO retina (paciente, medico, data_exame, hora_exame, olho, campos, conclusao, oct, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        json_encode($input['campos'] ?? []),
                        $input['conclusao'] ?? null,
                        $input['oct'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Exame de retina registrado com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar exame de retina: ' . $e->getMessage()]);
                }
                break;
                
            case 'gonioscopia':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarGonioscopia($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO gonioscopia (paciente, medico, data_exame, hora_exame, olho, angulo, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['angulo'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Gonioscopia registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar gonioscopia: ' . $e->getMessage()]);
                }
                break;
                
            case 'refracao':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarRefracao($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO refracao (paciente, medico, data_exame, hora_exame, olho, esferico, cilindrico, eixo, acuidade, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['esferico'] ?? null,
                        $input['cilindrico'] ?? null,
                        $input['eixo'] ?? null,
                        $input['acuidade'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Refração registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar refração: ' . $e->getMessage()]);
                }
                break;
                
            case 'campimetria':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarCampimetria($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                try {
                    $sql = "INSERT INTO campimetria (paciente, medico, data_exame, hora_exame, olho, tipo, resultado, observacoes, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $params = [
                        $input['prontuario'],
                        $input['medico'] ?? 1,
                        $input['data'] ?? date('Y-m-d'),
                        $input['hora'] ?? date('H:i:s'),
                        strtoupper($input['olho']),
                        $input['tipo'] ?? null,
                        $input['resultado'] ?? null,
                        $input['observacoes'] ?? null,
                        $input['usuario'] ?? 1
                    ];
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $id = $db->lastInsertId();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Campimetria registrada com sucesso',
                        'id' => $id
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao registrar campimetria: ' . $e->getMessage()]);
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
            case 'pio':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM pio WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_exame = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_exame DESC, hora_exame DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar PIO: ' . $e->getMessage()]);
                }
                break;
                
            case 'biomicroscopia':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM biomicroscopia WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_exame = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_exame DESC, hora_exame DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar biomicroscopia: ' . $e->getMessage()]);
                }
                break;
                
            case 'paquimetria':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM paquimetria WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_exame = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_exame DESC, hora_exame DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar paquimetria: ' . $e->getMessage()]);
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
                    $sql = "SELECT * FROM retina WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_exame = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_exame DESC, hora_exame DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar retina: ' . $e->getMessage()]);
                }
                break;
                
            case 'gonioscopia':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM gonioscopia WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_exame = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_exame DESC, hora_exame DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar gonioscopia: ' . $e->getMessage()]);
                }
                break;
                
            case 'refracao':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM refracao WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_exame = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_exame DESC, hora_exame DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar refração: ' . $e->getMessage()]);
                }
                break;
                
            case 'campimetria':
                $prontuario = $_GET['prontuario'] ?? null;
                $data = $_GET['date'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $sql = "SELECT * FROM campimetria WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data) {
                        $sql .= " AND data_exame = ?";
                        $params[] = $data;
                    }
                    
                    $sql .= " ORDER BY data_exame DESC, hora_exame DESC";
                    
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $result
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar campimetria: ' . $e->getMessage()]);
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
