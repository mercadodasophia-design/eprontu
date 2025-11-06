<?php
/**
 * Rotas de Timeline e Relatórios da Bobina
 * Endpoints: /api/bobina/timeline/{acao}
 * Ações suportadas: timeline, relatorio, estatisticas, dashboard
 */

// Funções de validação
function validarTimeline($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    return $errors;
}

function validarRelatorio($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    if (empty($payload['tipo_relatorio'])) {
        $errors[] = 'tipo_relatorio é obrigatório';
    }
    return $errors;
}

function validarEstatisticas($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    return $errors;
}

// Switch para diferentes métodos HTTP
switch ($method) {
    case 'GET':
        switch ($action) {
            case 'timeline':
                $prontuario = $_GET['prontuario'] ?? null;
                $data_inicio = $_GET['data_inicio'] ?? null;
                $data_fim = $_GET['data_fim'] ?? null;
                $tipo = $_GET['tipo'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    // Buscar todos os eventos do paciente
                    $events = [];
                    
                    // Exames PIO
                    $sql_pio = "SELECT 'pio' as tipo, data_exame as data, hora_exame as hora, 'PIO' as titulo, CONCAT('PIO: ', pressao, ' mmHg') as descricao, olho, 'exame' as categoria FROM pio WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data_inicio) {
                        $sql_pio .= " AND data_exame >= ?";
                        $params[] = $data_inicio;
                    }
                    if ($data_fim) {
                        $sql_pio .= " AND data_exame <= ?";
                        $params[] = $data_fim;
                    }
                    
                    $stmt = $db->prepare($sql_pio);
                    $stmt->execute($params);
                    $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));
                    
                    // Biomicroscopia
                    $sql_bio = "SELECT 'biomicroscopia' as tipo, data_exame as data, hora_exame as hora, 'Biomicroscopia' as titulo, 'Exame de biomicroscopia realizado' as descricao, olho, 'exame' as categoria FROM biomicroscopia WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data_inicio) {
                        $sql_bio .= " AND data_exame >= ?";
                        $params[] = $data_inicio;
                    }
                    if ($data_fim) {
                        $sql_bio .= " AND data_exame <= ?";
                        $params[] = $data_fim;
                    }
                    
                    $stmt = $db->prepare($sql_bio);
                    $stmt->execute($params);
                    $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));
                    
                    // Paquimetria
                    $sql_paq = "SELECT 'paquimetria' as tipo, data_exame as data, hora_exame as hora, 'Paquimetria' as titulo, CONCAT('Espessura: ', espessura, ' μm') as descricao, olho, 'exame' as categoria FROM paquimetria WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data_inicio) {
                        $sql_paq .= " AND data_exame >= ?";
                        $params[] = $data_inicio;
                    }
                    if ($data_fim) {
                        $sql_paq .= " AND data_exame <= ?";
                        $params[] = $data_fim;
                    }
                    
                    $stmt = $db->prepare($sql_paq);
                    $stmt->execute($params);
                    $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));
                    
                    // Prescrições
                    $sql_presc = "SELECT 'prescricao' as tipo, data_prescricao as data, hora_prescricao as hora, 'Prescrição' as titulo, 'Prescrição médica realizada' as descricao, NULL as olho, 'medicamento' as categoria FROM prescricoes WHERE paciente = ?";
                    $params = [$prontuario];
                    
                    if ($data_inicio) {
                        $sql_presc .= " AND data_prescricao >= ?";
                        $params[] = $data_inicio;
                    }
                    if ($data_fim) {
                        $sql_presc .= " AND data_prescricao <= ?";
                        $params[] = $data_fim;
                    }
                    
                    $stmt = $db->prepare($sql_presc);
                    $stmt->execute($params);
                    $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));
                    
                    // Cirurgias
                    $sql_cir = "SELECT 'cirurgia' as tipo, data_cirurgia as data, hora_cirurgia as hora, CONCAT('Cirurgia: ', tipo_cirurgia) as titulo, 'Cirurgia realizada' as descricao, olho, 'cirurgia' as categoria FROM cirurgias_catarata WHERE paciente = ?
                              UNION ALL
                              SELECT 'cirurgia' as tipo, data_cirurgia as data, hora_cirurgia as hora, CONCAT('Cirurgia: ', tipo_cirurgia) as titulo, 'Cirurgia realizada' as descricao, olho, 'cirurgia' as categoria FROM cirurgias_glaucoma WHERE paciente = ?
                              UNION ALL
                              SELECT 'cirurgia' as tipo, data_cirurgia as data, hora_cirurgia as hora, CONCAT('Cirurgia: ', tipo_cirurgia) as titulo, 'Cirurgia realizada' as descricao, olho, 'cirurgia' as categoria FROM cirurgias_retina WHERE paciente = ?
                              UNION ALL
                              SELECT 'cirurgia' as tipo, data_cirurgia as data, hora_cirurgia as hora, CONCAT('Cirurgia: ', tipo_cirurgia) as titulo, 'Cirurgia realizada' as descricao, olho, 'cirurgia' as categoria FROM cirurgias_cornea WHERE paciente = ?";
                    $params = [$prontuario, $prontuario, $prontuario, $prontuario];
                    
                    if ($data_inicio) {
                        $sql_cir .= " AND data_cirurgia >= ?";
                        $params[] = $data_inicio;
                        $params[] = $data_inicio;
                        $params[] = $data_inicio;
                        $params[] = $data_inicio;
                    }
                    if ($data_fim) {
                        $sql_cir .= " AND data_cirurgia <= ?";
                        $params[] = $data_fim;
                        $params[] = $data_fim;
                        $params[] = $data_fim;
                        $params[] = $data_fim;
                    }
                    
                    $stmt = $db->prepare($sql_cir);
                    $stmt->execute($params);
                    $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));
                    
                    // Ordenar por data e hora
                    usort($events, function($a, $b) {
                        $dateA = $a['data'] . ' ' . $a['hora'];
                        $dateB = $b['data'] . ' ' . $b['hora'];
                        return strtotime($dateB) - strtotime($dateA);
                    });
                    
                    // Filtrar por tipo se especificado
                    if ($tipo) {
                        $events = array_filter($events, function($event) use ($tipo) {
                            return $event['categoria'] === $tipo;
                        });
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'data' => array_values($events)
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar timeline: ' . $e->getMessage()]);
                }
                break;
                
            case 'relatorio':
                $prontuario = $_GET['prontuario'] ?? null;
                $tipo_relatorio = $_GET['tipo_relatorio'] ?? null;
                $data_inicio = $_GET['data_inicio'] ?? null;
                $data_fim = $_GET['data_fim'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                if (!$tipo_relatorio) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Tipo de relatório é obrigatório']);
                    exit;
                }
                
                try {
                    $relatorio = [];
                    
                    switch ($tipo_relatorio) {
                        case 'exames':
                            $sql = "SELECT 'PIO' as tipo, COUNT(*) as total FROM pio WHERE paciente = ?
                                    UNION ALL
                                    SELECT 'Biomicroscopia' as tipo, COUNT(*) as total FROM biomicroscopia WHERE paciente = ?
                                    UNION ALL
                                    SELECT 'Paquimetria' as tipo, COUNT(*) as total FROM paquimetria WHERE paciente = ?
                                    UNION ALL
                                    SELECT 'Retina' as tipo, COUNT(*) as total FROM retina WHERE paciente = ?
                                    UNION ALL
                                    SELECT 'Gonioscopia' as tipo, COUNT(*) as total FROM gonioscopia WHERE paciente = ?";
                            $params = [$prontuario, $prontuario, $prontuario, $prontuario, $prontuario];
                            
                            if ($data_inicio) {
                                $sql .= " AND data_exame >= ?";
                                $params[] = $data_inicio;
                                $params[] = $data_inicio;
                                $params[] = $data_inicio;
                                $params[] = $data_inicio;
                                $params[] = $data_inicio;
                            }
                            if ($data_fim) {
                                $sql .= " AND data_exame <= ?";
                                $params[] = $data_fim;
                                $params[] = $data_fim;
                                $params[] = $data_fim;
                                $params[] = $data_fim;
                                $params[] = $data_fim;
                            }
                            
                            $stmt = $db->prepare($sql);
                            $stmt->execute($params);
                            $relatorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;
                            
                        case 'cirurgias':
                            $sql = "SELECT 'Catarata' as tipo, COUNT(*) as total FROM cirurgias_catarata WHERE paciente = ?
                                    UNION ALL
                                    SELECT 'Glaucoma' as tipo, COUNT(*) as total FROM cirurgias_glaucoma WHERE paciente = ?
                                    UNION ALL
                                    SELECT 'Retina' as tipo, COUNT(*) as total FROM cirurgias_retina WHERE paciente = ?
                                    UNION ALL
                                    SELECT 'Córnea' as tipo, COUNT(*) as total FROM cirurgias_cornea WHERE paciente = ?";
                            $params = [$prontuario, $prontuario, $prontuario, $prontuario];
                            
                            if ($data_inicio) {
                                $sql .= " AND data_cirurgia >= ?";
                                $params[] = $data_inicio;
                                $params[] = $data_inicio;
                                $params[] = $data_inicio;
                                $params[] = $data_inicio;
                            }
                            if ($data_fim) {
                                $sql .= " AND data_cirurgia <= ?";
                                $params[] = $data_fim;
                                $params[] = $data_fim;
                                $params[] = $data_fim;
                                $params[] = $data_fim;
                            }
                            
                            $stmt = $db->prepare($sql);
                            $stmt->execute($params);
                            $relatorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;
                            
                        case 'medicamentos':
                            $sql = "SELECT COUNT(*) as total_prescricoes FROM prescricoes WHERE paciente = ?";
                            $params = [$prontuario];
                            
                            if ($data_inicio) {
                                $sql .= " AND data_prescricao >= ?";
                                $params[] = $data_inicio;
                            }
                            if ($data_fim) {
                                $sql .= " AND data_prescricao <= ?";
                                $params[] = $data_fim;
                            }
                            
                            $stmt = $db->prepare($sql);
                            $stmt->execute($params);
                            $relatorio = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            break;
                            
                        default:
                            http_response_code(400);
                            echo json_encode(['error' => 'Tipo de relatório não suportado']);
                            exit;
                    }
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $relatorio
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao gerar relatório: ' . $e->getMessage()]);
                }
                break;
                
            case 'estatisticas':
                $prontuario = $_GET['prontuario'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $estatisticas = [];
                    
                    // Total de exames
                    $sql_exames = "SELECT 
                                    (SELECT COUNT(*) FROM pio WHERE paciente = ?) as pio,
                                    (SELECT COUNT(*) FROM biomicroscopia WHERE paciente = ?) as biomicroscopia,
                                    (SELECT COUNT(*) FROM paquimetria WHERE paciente = ?) as paquimetria,
                                    (SELECT COUNT(*) FROM retina WHERE paciente = ?) as retina,
                                    (SELECT COUNT(*) FROM gonioscopia WHERE paciente = ?) as gonioscopia";
                    $params = [$prontuario, $prontuario, $prontuario, $prontuario, $prontuario];
                    
                    $stmt = $db->prepare($sql_exames);
                    $stmt->execute($params);
                    $estatisticas['exames'] = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Total de cirurgias
                    $sql_cirurgias = "SELECT 
                                        (SELECT COUNT(*) FROM cirurgias_catarata WHERE paciente = ?) as catarata,
                                        (SELECT COUNT(*) FROM cirurgias_glaucoma WHERE paciente = ?) as glaucoma,
                                        (SELECT COUNT(*) FROM cirurgias_retina WHERE paciente = ?) as retina,
                                        (SELECT COUNT(*) FROM cirurgias_cornea WHERE paciente = ?) as cornea";
                    $params = [$prontuario, $prontuario, $prontuario, $prontuario];
                    
                    $stmt = $db->prepare($sql_cirurgias);
                    $stmt->execute($params);
                    $estatisticas['cirurgias'] = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Total de prescrições
                    $sql_prescricoes = "SELECT COUNT(*) as total FROM prescricoes WHERE paciente = ?";
                    $stmt = $db->prepare($sql_prescricoes);
                    $stmt->execute([$prontuario]);
                    $estatisticas['prescricoes'] = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Total de documentos
                    $sql_documentos = "SELECT COUNT(*) as total FROM documentos WHERE paciente = ?";
                    $stmt = $db->prepare($sql_documentos);
                    $stmt->execute([$prontuario]);
                    $estatisticas['documentos'] = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $estatisticas
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar estatísticas: ' . $e->getMessage()]);
                }
                break;
                
            case 'dashboard':
                $prontuario = $_GET['prontuario'] ?? null;
                
                if (!$prontuario) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Prontuário é obrigatório']);
                    exit;
                }
                
                try {
                    $dashboard = [];
                    
                    // Últimos exames
                    $sql_ultimos_exames = "SELECT 'PIO' as tipo, data_exame as data, hora_exame as hora, pressao as valor, olho FROM pio WHERE paciente = ? ORDER BY data_exame DESC, hora_exame DESC LIMIT 5
                                          UNION ALL
                                          SELECT 'Biomicroscopia' as tipo, data_exame as data, hora_exame as hora, 'Realizado' as valor, olho FROM biomicroscopia WHERE paciente = ? ORDER BY data_exame DESC, hora_exame DESC LIMIT 5
                                          UNION ALL
                                          SELECT 'Paquimetria' as tipo, data_exame as data, hora_exame as hora, espessura as valor, olho FROM paquimetria WHERE paciente = ? ORDER BY data_exame DESC, hora_exame DESC LIMIT 5
                                          ORDER BY data DESC, hora DESC LIMIT 10";
                    
                    $stmt = $db->prepare($sql_ultimos_exames);
                    $stmt->execute([$prontuario, $prontuario, $prontuario]);
                    $dashboard['ultimos_exames'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Próximas consultas
                    $sql_proximas_consultas = "SELECT data_consulta, hora_consulta, medico, observacoes FROM consultas WHERE paciente = ? AND data_consulta >= CURDATE() ORDER BY data_consulta ASC, hora_consulta ASC LIMIT 5";
                    $stmt = $db->prepare($sql_proximas_consultas);
                    $stmt->execute([$prontuario]);
                    $dashboard['proximas_consultas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Medicamentos em uso
                    $sql_medicamentos_uso = "SELECT pm.dosagem, pm.posologia, m.nome as medicamento, p.data_prescricao 
                                            FROM prescricoes_medicamentos pm 
                                            LEFT JOIN medicamentos m ON pm.medicamento_id = m.id 
                                            LEFT JOIN prescricoes p ON pm.prescricao_id = p.id 
                                            WHERE p.paciente = ? 
                                            ORDER BY p.data_prescricao DESC 
                                            LIMIT 10";
                    $stmt = $db->prepare($sql_medicamentos_uso);
                    $stmt->execute([$prontuario]);
                    $dashboard['medicamentos_uso'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $dashboard
                    ]);
                } catch (Exception $e) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao buscar dashboard: ' . $e->getMessage()]);
                }
                break;
                
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Ação não encontrada']);
                break;
        }
        break;
        
    case 'POST':
        switch ($action) {
            case 'timeline':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarTimeline($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                // Timeline é apenas leitura, não há criação
                http_response_code(405);
                echo json_encode(['error' => 'Timeline é apenas leitura']);
                break;
                
            case 'relatorio':
                $input = json_decode(file_get_contents('php://input'), true);
                $errors = validarRelatorio($input);
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Dados inválidos', 'details' => $errors]);
                    exit;
                }
                
                // Relatórios são apenas leitura, não há criação
                http_response_code(405);
                echo json_encode(['error' => 'Relatórios são apenas leitura']);
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
