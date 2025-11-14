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
// Se $segments não estiver definido, criar a partir da URI
if (!isset($segments)) {
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $segments = explode('/', trim($uri, '/'));
    if (isset($segments[0]) && $segments[0] === 'e-prontu') array_shift($segments);
    if (isset($segments[0]) && $segments[0] === 'api') array_shift($segments);
    if (isset($segments[0]) && $segments[0] === 'fila-espera') array_shift($segments);
} else {
    // Se $segments foi passado pelo index.php, garantir que 'fila-espera' foi removido
    if (isset($segments[0]) && $segments[0] === 'fila-espera') {
        array_shift($segments);
    }
}

// Se $method não estiver definido, obter do $_SERVER
if (!isset($method)) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

// A ação está no primeiro segmento (já removido 'fila-espera' pelo index.php)
$action = $segments[0] ?? '';
$id = $segments[1] ?? null;

// Debug: log para verificar o que está sendo recebido
error_log('Fila Espera - Action: ' . $action . ' | ID: ' . ($id ?? 'null') . ' | Method: ' . $method . ' | Segments: ' . json_encode($segments));

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
            try {
                $dadosJson = Crypto::decryptData($input['dados']);
                $dados = json_decode($dadosJson, true);

                if (!$dados || json_last_error() !== JSON_ERROR_NONE) {
                    $response->error('Dados inválidos: ' . json_last_error_msg(), 400);
                    break;
                }
            } catch (Exception $e) {
                $response->error('Erro ao descriptografar dados: ' . $e->getMessage(), 400);
                break;
            }

            // Validar campos obrigatórios
            $camposObrigatorios = [
                'fila', 'paciente_id', 'procedimento_id', 'especialidade_id',
                'unidade_id', 'medico_solicitante_id', 'data_solicitacao',
                'motivo_clinico'
            ];

            $camposFaltando = [];
            foreach ($camposObrigatorios as $campo) {
                if (!isset($dados[$campo]) || $dados[$campo] === null || $dados[$campo] === '') {
                    $camposFaltando[] = $campo;
                }
            }

            if (!empty($camposFaltando)) {
                $response->error('Campos obrigatórios ausentes: ' . implode(', ', $camposFaltando), 400);
                break;
            }

            // Extrair dados da fila
            $fila = $dados['fila'];
            $filaId = null;
            
            if (is_array($fila)) {
                $filaId = $fila['id'] ?? null;
                
                // Se a fila não tem ID, criar uma nova fila
                if (!$filaId || $filaId <= 0) {
                    try {
                        _criarTabelasSeNaoExistem($db);
                        
                        $descricao = $fila['descricao'] ?? 'Nova Fila';
                        // Converter cor para garantir que seja um valor válido dentro do range de INTEGER
                        $corRaw = $fila['cor'] ?? 4280391411;
                        
                        // Converter para inteiro
                        if (is_string($corRaw)) {
                            $corRaw = (int)$corRaw;
                        } else {
                            $corRaw = (int)$corRaw;
                        }
                        
                        // Se for negativo (valor signed 32-bit), converter para positivo
                        // Color.value em Flutter pode retornar valores negativos quando interpretado como signed
                        if ($corRaw < 0) {
                            // Converter de signed 32-bit para valor positivo equivalente
                            // Usar abs() e depois garantir que esteja no range
                            $cor = abs($corRaw);
                            // Se ainda for muito grande, usar módulo
                            if ($cor > 2147483647) {
                                $cor = $cor % 2147483647;
                            }
                        } else {
                            $cor = $corRaw;
                            // Garantir que não exceda o máximo de INTEGER do PostgreSQL
                            if ($cor > 2147483647) {
                                $cor = $cor % 2147483647;
                            }
                        }
                        
                        // Garantir que seja pelo menos 0
                        if ($cor < 0) {
                            $cor = 4280391411; // Cor padrão (azul)
                        }
                        
                        // Aceitar tanto camelCase quanto snake_case
                        $tipoFila = $fila['tipoFila'] ?? $fila['tipo_fila'] ?? 'consulta';
                        
                        // Verificar se já existe uma fila com essa descrição
                        $filaExistente = $db->fetchOne(
                            "SELECT id FROM filas WHERE descricao = ? AND tipo_fila = ?",
                            [$descricao, $tipoFila]
                        );
                        
                        if ($filaExistente) {
                            $filaId = $filaExistente['id'];
                        } else {
                            // Criar nova fila
                            $sqlNovaFila = "INSERT INTO filas (descricao, cor, tipo_fila, created_at, updated_at) 
                                          VALUES (?, ?, ?, NOW(), NOW()) RETURNING id";
                            $stmt = $db->query($sqlNovaFila, [$descricao, $cor, $tipoFila]);
                            $result = $stmt->fetch();
                            $filaId = $result['id'] ?? null;
                            
                            if (!$filaId) {
                                $response->error('Erro ao criar nova fila', 500);
                                break;
                            }
                        }
                    } catch (Exception $e) {
                        $response->error('Erro ao criar fila: ' . $e->getMessage(), 500);
                        break;
                    }
                }
            } else if (is_numeric($fila)) {
                $filaId = (int)$fila;
            } else if (is_string($fila) && is_numeric($fila)) {
                $filaId = (int)$fila;
            }
            
            if (!$filaId || $filaId <= 0) {
                $response->error('ID da fila não fornecido ou inválido. Fila recebida: ' . json_encode($fila), 400);
                break;
            }
            
            // Converter data_solicitacao para formato correto se necessário
            if (isset($dados['data_solicitacao'])) {
                // Se for string ISO, converter para timestamp
                if (is_string($dados['data_solicitacao'])) {
                    $dados['data_solicitacao'] = date('Y-m-d H:i:s', strtotime($dados['data_solicitacao']));
                }
            }
            
            // Converter data_prazo se fornecido
            if (isset($dados['data_prazo']) && $dados['data_prazo'] !== null && $dados['data_prazo'] !== '') {
                if (is_string($dados['data_prazo'])) {
                    $timestamp = strtotime($dados['data_prazo']);
                    if ($timestamp !== false) {
                        $dados['data_prazo'] = date('Y-m-d H:i:s', $timestamp);
                    } else {
                        $dados['data_prazo'] = null;
                    }
                }
            } else {
                $dados['data_prazo'] = null;
            }

            // Criar tabelas se não existirem
            try {
                _criarTabelasSeNaoExistem($db);
            } catch (Exception $e) {
                $response->error('Erro ao criar/verificar tabelas: ' . $e->getMessage(), 500);
                break;
            }

            // Validar e converter campos INTEGER para garantir que estejam dentro do range
            $pacienteId = (int)$dados['paciente_id'];
            if ($pacienteId < -2147483648 || $pacienteId > 2147483647) {
                $response->error('paciente_id fora do range de INTEGER: ' . $dados['paciente_id'], 400);
                break;
            }
            
            $especialidadeId = (int)$dados['especialidade_id'];
            if ($especialidadeId < -2147483648 || $especialidadeId > 2147483647) {
                $response->error('especialidade_id fora do range de INTEGER: ' . $dados['especialidade_id'], 400);
                break;
            }
            
            $pontuacaoClinica = isset($dados['pontuacao_clinica']) ? (int)$dados['pontuacao_clinica'] : 0;
            if ($pontuacaoClinica < -2147483648 || $pontuacaoClinica > 2147483647) {
                $pontuacaoClinica = 0; // Resetar se fora do range
            }
            
            $posicaoFila = isset($dados['posicao_fila']) ? (int)$dados['posicao_fila'] : 0;
            if ($posicaoFila < -2147483648 || $posicaoFila > 2147483647) {
                $posicaoFila = 0; // Resetar se fora do range
            }

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
                $pacienteId,
                $dados['procedimento_id'],
                $especialidadeId,
                $dados['unidade_id'],
                $dados['medico_solicitante_id'],
                $dados['usuario_regulador_id'] ?? null,
                $dados['status'] ?? 'pendente',
                $dados['prioridade'] ?? 'eletiva',
                $pontuacaoClinica,
                $dados['data_solicitacao'],
                $dados['data_prazo'] ?? null,
                $dados['data_entrada_fila'] ?? date('Y-m-d H:i:s'),
                $dados['motivo_clinico'],
                $dados['observacoes_regulacao'] ?? null,
                $posicaoFila,
                $dados['tempo_espera_estimado'] ?? 0,
            ];

            // Executar inserção
            try {
                // Log dos parâmetros para debug (remover em produção)
                error_log('Parâmetros da inserção: ' . json_encode($params));
                
                $stmt = $db->query($sql, $params);
                $result = $stmt->fetch();
                $novaFilaId = $result['id'] ?? null;

                if (!$novaFilaId) {
                    $response->error('Erro ao criar fila: ID não retornado', 500);
                    break;
                }
            } catch (Exception $e) {
                // Verificar se é erro de tabela não existe
                if (strpos($e->getMessage(), 'does not exist') !== false) {
                    $response->error('Tabela fila_espera não existe. Execute o script create_fila_espera_table.sql no banco de dados.', 500);
                } else {
                    // Incluir informações sobre os parâmetros no erro para debug
                    $paramsInfo = array_map(function($param) {
                        if (is_numeric($param)) {
                            return gettype($param) . '(' . $param . ')';
                        }
                        return gettype($param) . '(' . (is_string($param) ? substr($param, 0, 50) : json_encode($param)) . ')';
                    }, $params);
                    $response->error('Erro ao inserir fila: ' . $e->getMessage() . ' | Parâmetros: ' . json_encode($paramsInfo), 500);
                }
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

            // Garantir que as tabelas existem
            try {
                _criarTabelasSeNaoExistem($db);
            } catch (Exception $e) {
                error_log('Erro ao criar tabelas: ' . $e->getMessage());
                // Continua mesmo se houver erro na criação das tabelas
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

            // Ajustar WHERE para usar apenas fe.* quando não há filtros de tipo
            $whereClause = '';
            if (!empty($where)) {
                // Se tem filtro de tipo, precisa do JOIN com filas
                if (isset($_GET['tipo_fila'])) {
                    $whereClause = 'WHERE ' . implode(' AND ', $where);
                    $sql = "SELECT fe.*, f.descricao as fila_descricao, f.cor as fila_cor, f.tipo_fila as fila_tipo_fila
                            FROM fila_espera fe
                            LEFT JOIN filas f ON fe.fila_id = f.id
                            $whereClause
                            ORDER BY fe.data_entrada_fila ASC";
                } else {
                    // Sem filtro de tipo, não precisa do JOIN
                    $whereClause = 'WHERE ' . implode(' AND ', $where);
                    $sql = "SELECT fe.* FROM fila_espera fe $whereClause ORDER BY fe.data_entrada_fila ASC";
                }
            } else {
                $sql = "SELECT fe.* FROM fila_espera fe ORDER BY fe.data_entrada_fila ASC";
            }

            try {
                $filas = $db->fetchAll($sql, $params);
                error_log('🔵 Filas encontradas: ' . (is_array($filas) ? count($filas) : 'não é array'));
            } catch (Exception $e) {
                error_log('Erro ao buscar filas: ' . $e->getMessage());
                error_log('SQL: ' . $sql);
                error_log('Params: ' . print_r($params, true));
                
                // Se a tabela não existir, criar e retornar lista vazia
                if (strpos($e->getMessage(), 'does not exist') !== false || 
                    strpos($e->getMessage(), 'não existe') !== false) {
                    error_log('⚠️ Tabela não existe, criando...');
                    try {
                        _criarTabelasSeNaoExistem($db);
                        $filas = [];
                    } catch (Exception $e2) {
                        error_log('Erro ao criar tabelas: ' . $e2->getMessage());
                        $response->error('Erro ao buscar filas: ' . $e->getMessage(), 500);
                        break;
                    }
                } else {
                    $response->error('Erro ao buscar filas: ' . $e->getMessage() . ' | SQL: ' . $sql, 500);
                    break;
                }
            }
            
            // Garantir que $filas é sempre um array
            if ($filas === false || $filas === null) {
                error_log('⚠️ Resultado é false/null, usando array vazio');
                $filas = [];
            }
            
            if (!is_array($filas)) {
                error_log('⚠️ Resultado não é array, convertendo para array vazio');
                $filas = [];
            }
            
            // Formatar resposta com objetos aninhados
            $filasFormatadas = [];
            foreach ($filas as $fila) {
                // Buscar dados relacionados de forma segura
                $pacienteNome = 'Paciente ' . ($fila['paciente_id'] ?? '');
                $pacienteCpf = '';
                $pacienteDataNasc = '';
                $pacienteIdade = '';
                $pacienteSexo = '';
                
                $procedimentoDesc = 'Procedimento ' . ($fila['procedimento_id'] ?? '');
                $especialidadeDesc = 'Especialidade ' . ($fila['especialidade_id'] ?? '');
                $unidadeDesc = 'Unidade ' . ($fila['unidade_id'] ?? '');
                $medicoNome = 'Médico ' . ($fila['medico_solicitante_id'] ?? '');
                $medicoCrm = '';
                
                $filaDesc = 'Fila ' . ($fila['fila_id'] ?? '');
                $filaCor = 4280391411;
                $filaTipo = 'consulta';
                
                // Buscar dados do paciente se possível
                if (!empty($fila['paciente_id'])) {
                    try {
                        // Tentar diferentes possibilidades de nome de tabela/coluna
                        $pacienteData = null;
                        $pacienteId = $fila['paciente_id'];
                        
                        // Tentativa 1: paciente.codpaciente (nome correto: nomepaciente, data nascimento com espaço)
                        try {
                            $pacienteData = $db->fetchOne("SELECT nomepaciente, cpf, \"data nascimento\" as datanascimento, sexo FROM paciente WHERE codpaciente = ?", [$pacienteId]);
                        } catch (Exception $e1) {
                            error_log("Erro ao buscar paciente (codpaciente): " . $e1->getMessage());
                            
                            // Tentativa 2: paciente.id
                            try {
                                $pacienteData = $db->fetchOne("SELECT nomepaciente, cpf, \"data nascimento\" as datanascimento, sexo FROM paciente WHERE id = ?", [$pacienteId]);
                            } catch (Exception $e2) {
                                error_log("Erro ao buscar paciente (id): " . $e2->getMessage());
                                
                                // Tentativa 3: pacientes.codpaciente
                                try {
                                    $pacienteData = $db->fetchOne("SELECT nomepaciente, cpf, \"data nascimento\" as datanascimento, sexo FROM pacientes WHERE codpaciente = ?", [$pacienteId]);
                                } catch (Exception $e3) {
                                    error_log("Erro ao buscar paciente (pacientes.codpaciente): " . $e3->getMessage());
                                    
                                    // Tentativa 4: pacientes.id
                                    try {
                                        $pacienteData = $db->fetchOne("SELECT nomepaciente, cpf, \"data nascimento\" as datanascimento, sexo FROM pacientes WHERE id = ?", [$pacienteId]);
                                    } catch (Exception $e4) {
                                        error_log("Erro ao buscar paciente (pacientes.id): " . $e4->getMessage());
                                    }
                                }
                            }
                        }
                        
                        if ($pacienteData) {
                            $pacienteNome = $pacienteData['nomepaciente'] ?? $pacienteNome;
                            $pacienteCpf = $pacienteData['cpf'] ?? '';
                            $pacienteDataNasc = $pacienteData['datanascimento'] ?? '';
                            $pacienteSexo = $pacienteData['sexo'] ?? '';
                            $pacienteIdade = '';
                            
                            error_log("✅ Paciente encontrado: ID=$pacienteId, Nome=$pacienteNome");
                            
                            // Calcular idade a partir da data de nascimento
                            if (!empty($pacienteDataNasc)) {
                                try {
                                    $dataNasc = new DateTime($pacienteDataNasc);
                                    $hoje = new DateTime();
                                    $pacienteIdade = (string)$hoje->diff($dataNasc)->y;
                                } catch (Exception $e) {
                                    error_log("Erro ao calcular idade: " . $e->getMessage());
                                }
                            }
                        } else {
                            error_log("⚠️ Paciente não encontrado: ID=$pacienteId");
                        }
                    } catch (Exception $e) {
                        error_log("❌ Erro ao buscar paciente: " . $e->getMessage());
                        // Ignora erro, usa valores padrão
                    }
                }
                
                // Buscar dados da fila
                if (!empty($fila['fila_id'])) {
                    try {
                        $filaData = $db->fetchOne("SELECT descricao, cor, tipo_fila FROM filas WHERE id = ?", [$fila['fila_id']]);
                        if ($filaData) {
                            $filaDesc = $filaData['descricao'] ?? $filaDesc;
                            $filaCor = $filaData['cor'] ?? $filaCor;
                            $filaTipo = $filaData['tipo_fila'] ?? $filaTipo;
                        }
                    } catch (Exception $e) {
                        // Ignora erro
                    }
                }
                
                // Buscar descrição do procedimento
                if (!empty($fila['procedimento_id'])) {
                    try {
                        $procData = $db->fetchOne("SELECT descricaoprocedimento FROM procedimentos WHERE codprocedimento::text = ?", [$fila['procedimento_id']]);
                        if ($procData) {
                            $procedimentoDesc = $procData['descricaoprocedimento'] ?? $procedimentoDesc;
                        }
                    } catch (Exception $e) {
                        // Ignora erro
                    }
                }
                
                // Buscar descrição da especialidade
                if (!empty($fila['especialidade_id'])) {
                    try {
                        $espData = $db->fetchOne("SELECT especialidade FROM especialidades WHERE codespecialidade = ?", [$fila['especialidade_id']]);
                        if ($espData) {
                            $especialidadeDesc = $espData['especialidade'] ?? $especialidadeDesc;
                        }
                    } catch (Exception $e) {
                        // Ignora erro
                    }
                }
                
                // Buscar descrição da unidade
                if (!empty($fila['unidade_id'])) {
                    try {
                        $unidData = $db->fetchOne("SELECT unidades FROM unidades WHERE codunidades = ?", [$fila['unidade_id']]);
                        if ($unidData) {
                            $unidadeDesc = $unidData['unidades'] ?? $unidadeDesc;
                        }
                    } catch (Exception $e) {
                        // Ignora erro
                    }
                }
                
                // Buscar dados do médico (tabela profissionais, não medicos)
                if (!empty($fila['medico_solicitante_id'])) {
                    try {
                        $medData = $db->fetchOne("SELECT profissional FROM profissionais WHERE codprofissional = ?", [$fila['medico_solicitante_id']]);
                        if ($medData) {
                            $medicoNome = $medData['profissional'] ?? $medicoNome;
                        }
                    } catch (Exception $e) {
                        // Ignora erro
                    }
                }
                
                $filasFormatadas[] = [
                    'id' => $fila['id'] ?? null,
                    'fila_id' => $fila['fila_id'] ?? null,
                    'paciente_id' => $fila['paciente_id'] ?? null,
                    'procedimento_id' => $fila['procedimento_id'] ?? null,
                    'especialidade_id' => $fila['especialidade_id'] ?? null,
                    'unidade_id' => $fila['unidade_id'] ?? null,
                    'medico_solicitante_id' => $fila['medico_solicitante_id'] ?? null,
                    'usuario_regulador_id' => $fila['usuario_regulador_id'] ?? null,
                    'status' => $fila['status'] ?? 'pendente',
                    'prioridade' => $fila['prioridade'] ?? 'eletiva',
                    'pontuacao_clinica' => $fila['pontuacao_clinica'] ?? 0,
                    'data_solicitacao' => $fila['data_solicitacao'] ?? null,
                    'data_prazo' => $fila['data_prazo'] ?? null,
                    'data_regulacao' => $fila['data_regulacao'] ?? null,
                    'data_agendamento' => $fila['data_agendamento'] ?? null,
                    'data_previsao_atendimento' => $fila['data_previsao_atendimento'] ?? null,
                    'data_conclusao' => $fila['data_conclusao'] ?? null,
                    'data_entrada_fila' => $fila['data_entrada_fila'] ?? null,
                    'motivo_clinico' => $fila['motivo_clinico'] ?? '',
                    'observacoes_regulacao' => $fila['observacoes_regulacao'] ?? null,
                    'documentos_anexos' => $fila['documentos_anexos'] ?? '[]',
                    'posicao_fila' => $fila['posicao_fila'] ?? 0,
                    'tempo_espera_estimado' => $fila['tempo_espera_estimado'] ?? 0.0,
                    'created_at' => $fila['created_at'] ?? null,
                    'updated_at' => $fila['updated_at'] ?? null,
                    // Objetos aninhados
                    'fila' => [
                        'id' => $fila['fila_id'] ?? null,
                        'descricao' => $filaDesc,
                        'cor' => $filaCor,
                        'tipoFila' => $filaTipo,
                    ],
                    'paciente' => [
                        'id' => $fila['paciente_id'] ?? null,
                        'nome' => $pacienteNome,
                        'cpf' => $pacienteCpf,
                        'datanascimento' => $pacienteDataNasc,
                        'idade' => $pacienteIdade,
                        'sexo' => $pacienteSexo,
                    ],
                    'procedimento' => [
                        'codprocedimento' => $fila['procedimento_id'] ?? null,
                        'descricaoprocedimento' => $procedimentoDesc,
                    ],
                    'especialidade' => [
                        'codespecialidade' => $fila['especialidade_id'] ?? null,
                        'especialidade' => $especialidadeDesc,
                        'ativo' => true,
                        'id' => $fila['especialidade_id'] ?? null,
                    ],
                    'unidade' => [
                        'cod_unidade' => $fila['unidade_id'] ?? null,
                        'des_unidade' => $unidadeDesc,
                    ],
                    'medico_solicitante' => [
                        'cod_profissional' => $fila['medico_solicitante_id'] ?? null,
                        'des_profissional' => $medicoNome,
                        'crm' => $medicoCrm,
                    ],
                ];
            }

            // Garantir que $filasFormatadas é sempre um array
            if (!is_array($filasFormatadas)) {
                $filasFormatadas = [];
            }
            
            // Criptografar resposta
            try {
                $respostaJson = json_encode($filasFormatadas);
                if ($respostaJson === false) {
                    error_log('Erro ao codificar JSON: ' . json_last_error_msg());
                    error_log('Dados que falharam: ' . print_r($filasFormatadas, true));
                    // Se falhar, retornar array vazio
                    $respostaJson = '[]';
                }
                
                // Garantir que sempre temos um JSON válido (array)
                if (empty($respostaJson) || 
                    $respostaJson === false || 
                    $respostaJson === 'null' || 
                    $respostaJson === 'NULL' ||
                    trim($respostaJson) === '' ||
                    trim($respostaJson) === 'null') {
                    $respostaJson = '[]';
                    error_log('⚠️ Resposta JSON estava vazia/inválida, forçando array vazio');
                }
                
                // Validar que é um JSON válido
                $testDecode = json_decode($respostaJson, true);
                if ($testDecode === null && $respostaJson !== '[]' && $respostaJson !== 'null') {
                    error_log('⚠️ JSON inválido detectado, forçando array vazio. JSON original: ' . substr($respostaJson, 0, 100));
                    $respostaJson = '[]';
                }
                
                error_log('🔵 Resposta JSON final antes de criptografar: ' . substr($respostaJson, 0, 200));
                error_log('🔵 Tamanho da resposta JSON: ' . strlen($respostaJson) . ' bytes');
                
                try {
                    $respostaCriptografada = Crypto::encryptString($respostaJson);
                    error_log('🔵 Resposta criptografada (primeiros 100 chars): ' . substr($respostaCriptografada, 0, 100));
                    error_log('🔵 Tamanho da resposta criptografada: ' . strlen($respostaCriptografada) . ' bytes');
                } catch (Exception $e) {
                    error_log('❌ Erro ao criptografar resposta: ' . $e->getMessage());
                    error_log('❌ JSON que falhou: ' . substr($respostaJson, 0, 200));
                    // Se falhar a criptografia, tentar com array vazio
                    try {
                        $respostaCriptografada = Crypto::encryptString('[]');
                    } catch (Exception $e2) {
                        error_log('❌ Erro crítico ao criptografar array vazio: ' . $e2->getMessage());
                        $response->error('Erro ao criptografar resposta', 500);
                        break;
                    }
                }

                // Validar que a resposta criptografada não está vazia
                if (empty($respostaCriptografada) || strlen($respostaCriptografada) < 20) {
                    error_log('⚠️ Resposta criptografada muito curta ou vazia, tentando novamente...');
                    // Tentar criptografar array vazio novamente
                    $respostaCriptografada = Crypto::encryptString('[]');
                }
                
                // Retornar no formato esperado pelo Flutter (apenas 'dados')
                header('Content-Type: application/json');
                $respostaFinal = [
                    'dados' => $respostaCriptografada
                ];
                
                error_log('🔵 Retornando resposta final. Tamanho dos dados criptografados: ' . strlen($respostaCriptografada) . ' bytes');
                
                echo json_encode($respostaFinal);
                exit;
            } catch (Exception $e) {
                error_log('Erro ao processar resposta: ' . $e->getMessage());
                $response->error('Erro ao processar resposta: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'estatisticas':
            if ($method !== 'GET') {
                $response->error('Método não permitido', 405);
                break;
            }

            try {
                // Aplicar filtros opcionais
                $where = [];
                $params = [];

                if (isset($_GET['unidade_id'])) {
                    $where[] = "fe.unidade_id = ?";
                    $params[] = $_GET['unidade_id'];
                }

                if (isset($_GET['especialidade_id'])) {
                    $where[] = "fe.especialidade_id = ?";
                    $params[] = $_GET['especialidade_id'];
                }

                if (isset($_GET['tipo_fila'])) {
                    $where[] = "f.tipo_fila = ?";
                    $params[] = $_GET['tipo_fila'];
                }

                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

                // Buscar todas as filas com filtros
                $sql = "SELECT fe.*, f.tipo_fila 
                        FROM fila_espera fe
                        LEFT JOIN filas f ON fe.fila_id = f.id
                        $whereClause";
                
                $filas = $db->fetchAll($sql, $params);

                // Calcular estatísticas
                $total = count($filas);
                
                // Distribuição por status
                $porStatus = [
                    'pendente' => 0,
                    'regulado' => 0,
                    'agendado' => 0,
                    'concluido' => 0,
                    'cancelado' => 0
                ];
                
                // Distribuição por prioridade
                $porPrioridade = [
                    'emergencia' => 0,
                    'urgente' => 0,
                    'prioritaria' => 0,
                    'eletiva' => 0
                ];
                
                // Distribuição por tipo
                $porTipo = [
                    'consulta' => 0,
                    'exame' => 0,
                    'cirurgia' => 0
                ];
                
                // Tempos de espera
                $temposEspera = [];
                $temposPorPrioridade = [
                    'emergencia' => [],
                    'urgente' => [],
                    'prioritaria' => [],
                    'eletiva' => []
                ];
                
                $concluidos = 0;
                $hoje = new DateTime();

                foreach ($filas as $fila) {
                    // Contar por status
                    $status = $fila['status'] ?? 'pendente';
                    if (isset($porStatus[$status])) {
                        $porStatus[$status]++;
                    }
                    
                    // Contar por prioridade
                    $prioridade = $fila['prioridade'] ?? 'eletiva';
                    if (isset($porPrioridade[$prioridade])) {
                        $porPrioridade[$prioridade]++;
                    }
                    
                    // Contar por tipo
                    $tipo = $fila['tipo_fila'] ?? 'consulta';
                    if (isset($porTipo[$tipo])) {
                        $porTipo[$tipo]++;
                    }
                    
                    // Calcular tempo de espera
                    if (!empty($fila['data_entrada_fila'])) {
                        try {
                            $dataEntrada = new DateTime($fila['data_entrada_fila']);
                            $dataFim = !empty($fila['data_conclusao']) 
                                ? new DateTime($fila['data_conclusao'])
                                : $hoje;
                            
                            $diasEspera = $dataEntrada->diff($dataFim)->days;
                            $temposEspera[] = $diasEspera;
                            
                            if (isset($temposPorPrioridade[$prioridade])) {
                                $temposPorPrioridade[$prioridade][] = $diasEspera;
                            }
                            
                            if ($status === 'concluido') {
                                $concluidos++;
                            }
                        } catch (Exception $e) {
                            // Ignora erro de data
                        }
                    }
                }
                
                // Calcular médias
                $tempoMedioEspera = !empty($temposEspera) 
                    ? array_sum($temposEspera) / count($temposEspera) 
                    : 0;
                
                $tempoMedioPorPrioridade = [];
                foreach ($temposPorPrioridade as $prioridade => $tempos) {
                    $tempoMedioPorPrioridade[$prioridade] = !empty($tempos)
                        ? array_sum($tempos) / count($tempos)
                        : 0;
                }
                
                // Taxa de conclusão
                $taxaConclusao = $total > 0 ? ($concluidos / $total) : 0;
                
                // Montar resposta
                $estatisticas = [
                    'total' => $total,
                    'por_status' => $porStatus,
                    'por_prioridade' => $porPrioridade,
                    'por_tipo' => $porTipo,
                    'tempo_medio_espera' => round($tempoMedioEspera, 2),
                    'tempo_medio_por_prioridade' => array_map(function($v) {
                        return round($v, 2);
                    }, $tempoMedioPorPrioridade),
                    'taxa_conclusao' => round($taxaConclusao, 2)
                ];
                
                // Criptografar e retornar
                $respostaJson = json_encode($estatisticas);
                $respostaCriptografada = Crypto::encryptString($respostaJson);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'dados' => $respostaCriptografada
                ]);
                exit;
            } catch (Exception $e) {
                error_log('Erro ao calcular estatísticas: ' . $e->getMessage());
                $response->error('Erro ao calcular estatísticas: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'atualizar':
            if ($method !== 'PUT') {
                $response->error('Método não permitido', 405);
                break;
            }

            if (!$id) {
                $response->error('ID não fornecido', 400);
                break;
            }

            try {
                // Verificar se fila existe
                $filaExistente = $db->fetchOne("SELECT * FROM fila_espera WHERE id = ?", [$id]);
                if (!$filaExistente) {
                    $response->error('Fila não encontrada', 404);
                    break;
                }

                // Obter dados criptografados
                $input = json_decode(file_get_contents('php://input'), true);
                if (!isset($input['dados'])) {
                    $response->error('Dados não fornecidos', 400);
                    break;
                }

                // Descriptografar dados
                try {
                    $dadosJson = Crypto::decryptData($input['dados']);
                    $dados = json_decode($dadosJson, true);

                    if (!$dados || json_last_error() !== JSON_ERROR_NONE) {
                        $response->error('Dados inválidos: ' . json_last_error_msg(), 400);
                        break;
                    }
                } catch (Exception $e) {
                    $response->error('Erro ao descriptografar dados: ' . $e->getMessage(), 400);
                    break;
                }

                // Preparar campos para atualização
                $camposUpdate = [];
                $valoresUpdate = [];
                $statusAnterior = $filaExistente['status'];
                $statusNovo = null;

                // Campos que podem ser atualizados
                if (isset($dados['status'])) {
                    $camposUpdate[] = "status = ?";
                    $valoresUpdate[] = $dados['status'];
                    $statusNovo = $dados['status'];
                }

                if (isset($dados['prioridade'])) {
                    $camposUpdate[] = "prioridade = ?";
                    $valoresUpdate[] = $dados['prioridade'];
                }

                if (isset($dados['observacoes_regulacao'])) {
                    $camposUpdate[] = "observacoes_regulacao = ?";
                    $valoresUpdate[] = $dados['observacoes_regulacao'];
                }

                if (isset($dados['data_agendamento'])) {
                    $camposUpdate[] = "data_agendamento = ?";
                    $valoresUpdate[] = $dados['data_agendamento'];
                }

                if (isset($dados['data_previsao_atendimento'])) {
                    $camposUpdate[] = "data_previsao_atendimento = ?";
                    $valoresUpdate[] = $dados['data_previsao_atendimento'];
                }

                if (isset($dados['data_conclusao'])) {
                    $camposUpdate[] = "data_conclusao = ?";
                    $valoresUpdate[] = $dados['data_conclusao'];
                }

                if (isset($dados['documentos_anexos'])) {
                    $documentosJson = is_array($dados['documentos_anexos']) 
                        ? json_encode($dados['documentos_anexos'])
                        : $dados['documentos_anexos'];
                    $camposUpdate[] = "documentos_anexos = ?::jsonb";
                    $valoresUpdate[] = $documentosJson;
                }

                if (empty($camposUpdate)) {
                    $response->error('Nenhum campo para atualizar', 400);
                    break;
                }

                // Adicionar updated_at
                $camposUpdate[] = "updated_at = NOW()";
                
                // Adicionar ID aos valores
                $valoresUpdate[] = $id;

                // Executar update
                $sql = "UPDATE fila_espera SET " . implode(', ', $camposUpdate) . " WHERE id = ?";
                $db->query($sql, $valoresUpdate);

                // Se status mudou, registrar movimentação
                if ($statusNovo && $statusNovo !== $statusAnterior) {
                    try {
                        $historicoAtual = $filaExistente['historico'] ?? '[]';
                        $historicoArray = json_decode($historicoAtual, true) ?? [];
                        
                        $novaMovimentacao = [
                            'data' => date('Y-m-d\TH:i:s'),
                            'descricao' => "Status alterado de {$statusAnterior} para {$statusNovo}",
                            'usuario' => 'Sistema',
                            'status_anterior' => $statusAnterior,
                            'status_novo' => $statusNovo
                        ];
                        
                        $historicoArray[] = $novaMovimentacao;
                        $historicoJson = json_encode($historicoArray);
                        
                        $db->query("UPDATE fila_espera SET historico = ?::jsonb WHERE id = ?", [$historicoJson, $id]);
                    } catch (Exception $e) {
                        error_log('Erro ao registrar movimentação: ' . $e->getMessage());
                        // Não falha a atualização se não conseguir registrar movimentação
                    }
                }

                // Buscar fila atualizada
                $filaAtualizada = $db->fetchOne("SELECT * FROM fila_espera WHERE id = ?", [$id]);
                
                // Buscar dados relacionados (mesma lógica do listar)
                $filaFormatada = _formatarFilaCompleta($db, $filaAtualizada);

                // Criptografar e retornar
                $respostaJson = json_encode($filaFormatada);
                $respostaCriptografada = Crypto::encryptString($respostaJson);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'dados' => $respostaCriptografada
                ]);
                exit;
            } catch (Exception $e) {
                error_log('Erro ao atualizar fila: ' . $e->getMessage());
                $response->error('Erro ao atualizar fila: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'reclassificar':
            if ($method !== 'PATCH') {
                $response->error('Método não permitido', 405);
                break;
            }

            if (!$id) {
                $response->error('ID não fornecido', 400);
                break;
            }

            try {
                // Verificar se fila existe
                $filaExistente = $db->fetchOne("SELECT * FROM fila_espera WHERE id = ?", [$id]);
                if (!$filaExistente) {
                    $response->error('Fila não encontrada', 404);
                    break;
                }

                // Validar que status permite reclassificação
                $statusAtual = $filaExistente['status'] ?? 'pendente';
                if (!in_array($statusAtual, ['pendente', 'regulado'])) {
                    $response->error('Status atual não permite reclassificação. Deve ser pendente ou regulado', 400);
                    break;
                }

                // Obter dados criptografados
                $input = json_decode(file_get_contents('php://input'), true);
                if (!isset($input['dados'])) {
                    $response->error('Dados não fornecidos', 400);
                    break;
                }

                // Descriptografar dados
                try {
                    $dadosJson = Crypto::decryptData($input['dados']);
                    $dados = json_decode($dadosJson, true);

                    if (!$dados || json_last_error() !== JSON_ERROR_NONE) {
                        $response->error('Dados inválidos: ' . json_last_error_msg(), 400);
                        break;
                    }
                } catch (Exception $e) {
                    $response->error('Erro ao descriptografar dados: ' . $e->getMessage(), 400);
                    break;
                }

                // Validar campos obrigatórios
                if (!isset($dados['prioridade']) || !in_array($dados['prioridade'], ['emergencia', 'urgente', 'prioritaria', 'eletiva'])) {
                    $response->error('Prioridade inválida', 400);
                    break;
                }

                $novaPrioridade = $dados['prioridade'];
                $motivo = $dados['motivo'] ?? '';
                $medicoReguladorId = $dados['medico_regulador_id'] ?? null;

                // Calcular nova pontuação clínica
                $diasEspera = 0;
                if (!empty($filaExistente['data_entrada_fila'])) {
                    try {
                        $dataEntrada = new DateTime($filaExistente['data_entrada_fila']);
                        $hoje = new DateTime();
                        $diasEspera = $dataEntrada->diff($hoje)->days;
                    } catch (Exception $e) {
                        // Ignora erro
                    }
                }

                $pontuacaoBase = [
                    'emergencia' => 100,
                    'urgente' => 75,
                    'prioritaria' => 50,
                    'eletiva' => 25
                ];
                $pontuacaoTempo = min($diasEspera * 0.5, 20);
                $novaPontuacao = (int)($pontuacaoBase[$novaPrioridade] + $pontuacaoTempo);

                // Atualizar prioridade e pontuação
                $db->query(
                    "UPDATE fila_espera SET prioridade = ?, pontuacao_clinica = ?, observacoes_regulacao = ?, usuario_regulador_id = ?, updated_at = NOW() WHERE id = ?",
                    [$novaPrioridade, $novaPontuacao, $motivo, $medicoReguladorId, $id]
                );

                // Reordenar posições no grupo
                _reordenarGrupoFila($db, $filaExistente);

                // Registrar movimentação
                try {
                    $historicoAtual = $filaExistente['historico'] ?? '[]';
                    $historicoArray = json_decode($historicoAtual, true) ?? [];
                    
                    $prioridadeAnterior = $filaExistente['prioridade'] ?? 'eletiva';
                    
                    $novaMovimentacao = [
                        'data' => date('Y-m-d\TH:i:s'),
                        'descricao' => "Prioridade reclassificada de {$prioridadeAnterior} para {$novaPrioridade}",
                        'usuario' => 'Sistema',
                        'motivo' => $motivo
                    ];
                    
                    $historicoArray[] = $novaMovimentacao;
                    $historicoJson = json_encode($historicoArray);
                    
                    $db->query("UPDATE fila_espera SET historico = ?::jsonb WHERE id = ?", [$historicoJson, $id]);
                } catch (Exception $e) {
                    error_log('Erro ao registrar movimentação: ' . $e->getMessage());
                }

                $response->success(['message' => 'Prioridade reclassificada com sucesso'], 'Prioridade reclassificada', 200);
            } catch (Exception $e) {
                error_log('Erro ao reclassificar prioridade: ' . $e->getMessage());
                $response->error('Erro ao reclassificar prioridade: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'excluir':
            if ($method !== 'DELETE') {
                $response->error('Método não permitido', 405);
                break;
            }

            if (!$id) {
                $response->error('ID não fornecido', 400);
                break;
            }

            try {
                // Verificar se fila existe
                $filaExistente = $db->fetchOne("SELECT * FROM fila_espera WHERE id = ?", [$id]);
                if (!$filaExistente) {
                    $response->error('Fila não encontrada', 404);
                    break;
                }

                // Obter motivo do cancelamento (query parameter)
                $motivo = $_GET['motivo'] ?? 'Cancelado pelo usuário';

                // Soft delete: atualizar status para 'cancelado'
                $db->query(
                    "UPDATE fila_espera SET status = 'cancelado', updated_at = NOW() WHERE id = ?",
                    [$id]
                );

                // Registrar movimentação
                try {
                    $historicoAtual = $filaExistente['historico'] ?? '[]';
                    $historicoArray = json_decode($historicoAtual, true) ?? [];
                    
                    $statusAnterior = $filaExistente['status'] ?? 'pendente';
                    
                    $novaMovimentacao = [
                        'data' => date('Y-m-d\TH:i:s'),
                        'descricao' => "Fila cancelada. Motivo: {$motivo}",
                        'usuario' => 'Sistema',
                        'status_anterior' => $statusAnterior,
                        'status_novo' => 'cancelado',
                        'motivo' => $motivo
                    ];
                    
                    $historicoArray[] = $novaMovimentacao;
                    $historicoJson = json_encode($historicoArray);
                    
                    $db->query("UPDATE fila_espera SET historico = ?::jsonb WHERE id = ?", [$historicoJson, $id]);
                } catch (Exception $e) {
                    error_log('Erro ao registrar movimentação: ' . $e->getMessage());
                }

                $response->success(['message' => 'Fila excluída com sucesso'], 'Fila excluída', 200);
            } catch (Exception $e) {
                error_log('Erro ao excluir fila: ' . $e->getMessage());
                $response->error('Erro ao excluir fila: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'buscar':
            if ($method !== 'GET') {
                $response->error('Método não permitido', 405);
                break;
            }

            if (!$id) {
                $response->error('ID não fornecido', 400);
                break;
            }

            try {
                // Buscar fila
                $fila = $db->fetchOne("SELECT * FROM fila_espera WHERE id = ?", [$id]);
                if (!$fila) {
                    $response->error('Fila não encontrada', 404);
                    break;
                }

                // Formatar com todos os dados relacionados
                $filaFormatada = _formatarFilaCompleta($db, $fila);

                // Buscar histórico completo
                $historicoJson = $fila['historico'] ?? '[]';
                $historicoArray = json_decode($historicoJson, true) ?? [];
                
                // Ordenar histórico por data (mais recente primeiro)
                usort($historicoArray, function($a, $b) {
                    $dataA = $a['data'] ?? '';
                    $dataB = $b['data'] ?? '';
                    return strcmp($dataB, $dataA); // Descendente
                });

                $filaFormatada['historico'] = $historicoArray;

                // Criptografar e retornar
                $respostaJson = json_encode($filaFormatada);
                $respostaCriptografada = Crypto::encryptString($respostaJson);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'dados' => $respostaCriptografada
                ]);
                exit;
            } catch (Exception $e) {
                error_log('Erro ao buscar fila: ' . $e->getMessage());
                $response->error('Erro ao buscar fila: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'sugerir-proximo':
            if ($method !== 'GET') {
                $response->error('Método não permitido', 405);
                break;
            }

            try {
                // Aplicar filtros opcionais
                $where = ["fe.status = 'pendente'"];
                $params = [];

                if (isset($_GET['unidade_id'])) {
                    $where[] = "fe.unidade_id = ?";
                    $params[] = $_GET['unidade_id'];
                }

                if (isset($_GET['especialidade_id'])) {
                    $where[] = "fe.especialidade_id = ?";
                    $params[] = $_GET['especialidade_id'];
                }

                if (isset($_GET['tipo_fila'])) {
                    $where[] = "f.tipo_fila = ?";
                    $params[] = $_GET['tipo_fila'];
                }

                $whereClause = 'WHERE ' . implode(' AND ', $where);

                // Buscar próximo paciente ordenado por pontuação e tempo
                $sql = "SELECT fe.*, f.tipo_fila 
                        FROM fila_espera fe
                        LEFT JOIN filas f ON fe.fila_id = f.id
                        $whereClause
                        ORDER BY fe.pontuacao_clinica DESC, fe.data_entrada_fila ASC
                        LIMIT 1";
                
                $fila = $db->fetchOne($sql, $params);

                if (!$fila) {
                    // Retornar null se não houver paciente
                    header('Content-Type: application/json');
                    echo json_encode([
                        'dados' => null
                    ]);
                    exit;
                }

                // Formatar com todos os dados relacionados
                $filaFormatada = _formatarFilaCompleta($db, $fila);

                // Criptografar e retornar
                $respostaJson = json_encode($filaFormatada);
                $respostaCriptografada = Crypto::encryptString($respostaJson);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'dados' => $respostaCriptografada
                ]);
                exit;
            } catch (Exception $e) {
                error_log('Erro ao sugerir próximo paciente: ' . $e->getMessage());
                $response->error('Erro ao sugerir próximo paciente: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'regular':
            if ($method !== 'POST') {
                $response->error('Método não permitido', 405);
                break;
            }

            if (!$id) {
                $response->error('ID não fornecido', 400);
                break;
            }

            try {
                // Verificar se fila existe
                $filaExistente = $db->fetchOne("SELECT * FROM fila_espera WHERE id = ?", [$id]);
                if (!$filaExistente) {
                    $response->error('Fila não encontrada', 404);
                    break;
                }

                // Obter dados criptografados
                $input = json_decode(file_get_contents('php://input'), true);
                if (!isset($input['dados'])) {
                    $response->error('Dados não fornecidos', 400);
                    break;
                }

                // Descriptografar dados
                try {
                    $dadosJson = Crypto::decryptData($input['dados']);
                    $dados = json_decode($dadosJson, true);

                    if (!$dados || json_last_error() !== JSON_ERROR_NONE) {
                        $response->error('Dados inválidos: ' . json_last_error_msg(), 400);
                        break;
                    }
                } catch (Exception $e) {
                    $response->error('Erro ao descriptografar dados: ' . $e->getMessage(), 400);
                    break;
                }

                $prioridade = $dados['prioridade'] ?? $filaExistente['prioridade'];
                $observacoes = $dados['observacoes'] ?? '';
                $medicoReguladorId = $dados['medico_regulador_id'] ?? null;

                // Validar prioridade
                if (!in_array($prioridade, ['emergencia', 'urgente', 'prioritaria', 'eletiva'])) {
                    $response->error('Prioridade inválida', 400);
                    break;
                }

                // Calcular nova pontuação clínica
                $diasEspera = 0;
                if (!empty($filaExistente['data_entrada_fila'])) {
                    try {
                        $dataEntrada = new DateTime($filaExistente['data_entrada_fila']);
                        $hoje = new DateTime();
                        $diasEspera = $dataEntrada->diff($hoje)->days;
                    } catch (Exception $e) {
                        // Ignora erro
                    }
                }

                $pontuacaoBase = [
                    'emergencia' => 100,
                    'urgente' => 75,
                    'prioritaria' => 50,
                    'eletiva' => 25
                ];
                $pontuacaoTempo = min($diasEspera * 0.5, 20);
                $novaPontuacao = (int)($pontuacaoBase[$prioridade] + $pontuacaoTempo);

                // Atualizar fila: status para 'regulado', data_regulacao, prioridade, pontuação, observações
                $statusAnterior = $filaExistente['status'] ?? 'pendente';
                
                $db->query(
                    "UPDATE fila_espera SET 
                        status = 'regulado',
                        data_regulacao = NOW(),
                        prioridade = ?,
                        pontuacao_clinica = ?,
                        observacoes_regulacao = ?,
                        usuario_regulador_id = ?,
                        updated_at = NOW()
                     WHERE id = ?",
                    [$prioridade, $novaPontuacao, $observacoes, $medicoReguladorId, $id]
                );

                // Reordenar posições no grupo
                _reordenarGrupoFila($db, $filaExistente);

                // Registrar movimentação
                try {
                    $historicoAtual = $filaExistente['historico'] ?? '[]';
                    $historicoArray = json_decode($historicoAtual, true) ?? [];
                    
                    $novaMovimentacao = [
                        'data' => date('Y-m-d\TH:i:s'),
                        'descricao' => "Regulação aplicada. Status alterado de {$statusAnterior} para regulado",
                        'usuario' => 'Sistema',
                        'status_anterior' => $statusAnterior,
                        'status_novo' => 'regulado',
                        'prioridade' => $prioridade,
                        'observacoes' => $observacoes
                    ];
                    
                    $historicoArray[] = $novaMovimentacao;
                    $historicoJson = json_encode($historicoArray);
                    
                    $db->query("UPDATE fila_espera SET historico = ?::jsonb WHERE id = ?", [$historicoJson, $id]);
                } catch (Exception $e) {
                    error_log('Erro ao registrar movimentação: ' . $e->getMessage());
                }

                $response->success(['message' => 'Regulação aplicada com sucesso'], 'Regulação aplicada', 200);
            } catch (Exception $e) {
                error_log('Erro ao aplicar regulação: ' . $e->getMessage());
                $response->error('Erro ao aplicar regulação: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'reordenar':
            if ($method !== 'POST') {
                $response->error('Método não permitido', 405);
                break;
            }

            try {
                // Obter dados criptografados
                $input = json_decode(file_get_contents('php://input'), true);
                if (!isset($input['dados'])) {
                    $response->error('Dados não fornecidos', 400);
                    break;
                }

                // Descriptografar dados
                try {
                    $dadosJson = Crypto::decryptData($input['dados']);
                    $dados = json_decode($dadosJson, true);

                    if (!$dados || json_last_error() !== JSON_ERROR_NONE) {
                        $response->error('Dados inválidos: ' . json_last_error_msg(), 400);
                        break;
                    }
                } catch (Exception $e) {
                    $response->error('Erro ao descriptografar dados: ' . $e->getMessage(), 400);
                    break;
                }

                // Validar campos obrigatórios
                if (!isset($dados['fila_espera_id']) || !isset($dados['nova_posicao'])) {
                    $response->error('fila_espera_id e nova_posicao são obrigatórios', 400);
                    break;
                }

                $filaEsperaId = $dados['fila_espera_id'];
                $novaPosicao = (int)$dados['nova_posicao'];
                $motivo = $dados['motivo'] ?? 'Reordenação manual';

                // Verificar se fila existe
                $fila = $db->fetchOne("SELECT * FROM fila_espera WHERE id = ?", [$filaEsperaId]);
                if (!$fila) {
                    $response->error('Fila não encontrada', 404);
                    break;
                }

                // Buscar grupo de filas (mesmo tipo, especialidade, unidade)
                $filaData = $db->fetchOne("SELECT tipo_fila FROM filas WHERE id = ?", [$fila['fila_id']]);
                $tipoFila = $filaData['tipo_fila'] ?? 'consulta';

                $sql = "SELECT fe.*, f.tipo_fila 
                        FROM fila_espera fe
                        LEFT JOIN filas f ON fe.fila_id = f.id
                        WHERE f.tipo_fila = ? 
                          AND fe.especialidade_id = ? 
                          AND fe.unidade_id = ?
                          AND fe.status NOT IN ('concluido', 'cancelado')
                        ORDER BY fe.pontuacao_clinica DESC, fe.data_entrada_fila ASC";
                
                $grupoFilas = $db->fetchAll($sql, [
                    $tipoFila,
                    $fila['especialidade_id'],
                    $fila['unidade_id']
                ]);

                $totalNoGrupo = count($grupoFilas);
                if ($novaPosicao < 1 || $novaPosicao > $totalNoGrupo) {
                    $response->error("Nova posição deve estar entre 1 e {$totalNoGrupo}", 400);
                    break;
                }

                // Encontrar índice atual da fila no grupo
                $indiceAtual = -1;
                foreach ($grupoFilas as $index => $filaGrupo) {
                    if ($filaGrupo['id'] == $filaEsperaId) {
                        $indiceAtual = $index;
                        break;
                    }
                }

                if ($indiceAtual === -1) {
                    $response->error('Fila não encontrada no grupo', 404);
                    break;
                }

                // Reordenar: mover para nova posição
                $filaMovida = $grupoFilas[$indiceAtual];
                unset($grupoFilas[$indiceAtual]);
                $grupoFilas = array_values($grupoFilas); // Reindexar

                // Inserir na nova posição
                array_splice($grupoFilas, $novaPosicao - 1, 0, [$filaMovida]);

                // Calcular tempos médios por tipo
                $temposMedios = [
                    'consulta' => 1.0,
                    'exame' => 2.0,
                    'cirurgia' => 5.0
                ];
                $tempoMedio = $temposMedios[$tipoFila] ?? 2.0;

                // Atualizar posições e tempos estimados
                foreach ($grupoFilas as $index => $filaGrupo) {
                    $posicao = $index + 1;
                    $tempoEstimado = ($posicao - 1) * $tempoMedio;
                    
                    $db->query(
                        "UPDATE fila_espera SET posicao_fila = ?, tempo_espera_estimado = ? WHERE id = ?",
                        [$posicao, $tempoEstimado, $filaGrupo['id']]
                    );
                }

                // Registrar movimentação
                try {
                    $historicoAtual = $fila['historico'] ?? '[]';
                    $historicoArray = json_decode($historicoAtual, true) ?? [];
                    
                    $novaMovimentacao = [
                        'data' => date('Y-m-d\TH:i:s'),
                        'descricao' => "Fila reordenada para posição {$novaPosicao}. Motivo: {$motivo}",
                        'usuario' => 'Sistema',
                        'motivo' => $motivo
                    ];
                    
                    $historicoArray[] = $novaMovimentacao;
                    $historicoJson = json_encode($historicoArray);
                    
                    $db->query("UPDATE fila_espera SET historico = ?::jsonb WHERE id = ?", [$historicoJson, $filaEsperaId]);
                } catch (Exception $e) {
                    error_log('Erro ao registrar movimentação: ' . $e->getMessage());
                }

                $response->success(['message' => 'Fila reordenada com sucesso'], 'Fila reordenada', 200);
            } catch (Exception $e) {
                error_log('Erro ao reordenar fila: ' . $e->getMessage());
                $response->error('Erro ao reordenar fila: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'recalcular-posicoes':
            if ($method !== 'POST') {
                $response->error('Método não permitido', 405);
                break;
            }

            try {
                // Aplicar filtros opcionais
                $where = ["fe.status NOT IN ('concluido', 'cancelado')"];
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

                $whereClause = 'WHERE ' . implode(' AND ', $where);

                // Buscar todas as filas com filtros
                $sql = "SELECT fe.*, f.tipo_fila 
                        FROM fila_espera fe
                        LEFT JOIN filas f ON fe.fila_id = f.id
                        $whereClause";
                
                $todasFilas = $db->fetchAll($sql, $params);

                // Agrupar por tipo, especialidade e unidade
                $grupos = [];
                foreach ($todasFilas as $fila) {
                    $chave = $fila['tipo_fila'] . '_' . $fila['especialidade_id'] . '_' . $fila['unidade_id'];
                    if (!isset($grupos[$chave])) {
                        $grupos[$chave] = [];
                    }
                    $grupos[$chave][] = $fila;
                }

                // Calcular tempos médios por tipo
                $temposMedios = [
                    'consulta' => 1.0,
                    'exame' => 2.0,
                    'cirurgia' => 5.0
                ];

                $filasAtualizadas = 0;

                // Para cada grupo, ordenar e atualizar posições
                foreach ($grupos as $grupo) {
                    // Ordenar por pontuação (desc) e tempo (asc)
                    usort($grupo, function($a, $b) {
                        $pontCompare = ($b['pontuacao_clinica'] ?? 0) <=> ($a['pontuacao_clinica'] ?? 0);
                        if ($pontCompare !== 0) return $pontCompare;
                        
                        $dataA = $a['data_entrada_fila'] ?? '';
                        $dataB = $b['data_entrada_fila'] ?? '';
                        return strcmp($dataA, $dataB);
                    });

                    $tipoFila = $grupo[0]['tipo_fila'] ?? 'consulta';
                    $tempoMedio = $temposMedios[$tipoFila] ?? 2.0;

                    // Atualizar posições
                    foreach ($grupo as $index => $fila) {
                        $posicao = $index + 1;
                        $tempoEstimado = ($posicao - 1) * $tempoMedio;
                        
                        $db->query(
                            "UPDATE fila_espera SET posicao_fila = ?, tempo_espera_estimado = ? WHERE id = ?",
                            [$posicao, $tempoEstimado, $fila['id']]
                        );
                        $filasAtualizadas++;
                    }
                }

                $response->success([
                    'message' => 'Posições recalculadas com sucesso',
                    'filas_atualizadas' => $filasAtualizadas
                ], 'Posições recalculadas', 200);
            } catch (Exception $e) {
                error_log('Erro ao recalcular posições: ' . $e->getMessage());
                $response->error('Erro ao recalcular posições: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'mover-frente':
            if ($method !== 'POST') {
                $response->error('Método não permitido', 405);
                break;
            }

            if (!$id) {
                $response->error('ID não fornecido', 400);
                break;
            }

            try {
                // Verificar se fila existe
                $fila = $db->fetchOne("SELECT * FROM fila_espera WHERE id = ?", [$id]);
                if (!$fila) {
                    $response->error('Fila não encontrada', 404);
                    break;
                }

                // Obter motivo (opcional)
                $input = json_decode(file_get_contents('php://input'), true);
                $motivo = 'Urgência clínica';
                if (isset($input['dados'])) {
                    try {
                        $dadosJson = Crypto::decryptData($input['dados']);
                        $dados = json_decode($dadosJson, true);
                        if ($dados && isset($dados['motivo'])) {
                            $motivo = $dados['motivo'];
                        }
                    } catch (Exception $e) {
                        // Ignora erro, usa motivo padrão
                    }
                }

                // Buscar grupo de filas
                $filaData = $db->fetchOne("SELECT tipo_fila FROM filas WHERE id = ?", [$fila['fila_id']]);
                $tipoFila = $filaData['tipo_fila'] ?? 'consulta';

                $sql = "SELECT fe.*, f.tipo_fila 
                        FROM fila_espera fe
                        LEFT JOIN filas f ON fe.fila_id = f.id
                        WHERE f.tipo_fila = ? 
                          AND fe.especialidade_id = ? 
                          AND fe.unidade_id = ?
                          AND fe.status NOT IN ('concluido', 'cancelado')
                        ORDER BY fe.pontuacao_clinica DESC, fe.data_entrada_fila ASC";
                
                $grupoFilas = $db->fetchAll($sql, [
                    $tipoFila,
                    $fila['especialidade_id'],
                    $fila['unidade_id']
                ]);

                // Encontrar e remover a fila do grupo
                $filaMovida = null;
                foreach ($grupoFilas as $index => $filaGrupo) {
                    if ($filaGrupo['id'] == $id) {
                        $filaMovida = $filaGrupo;
                        unset($grupoFilas[$index]);
                        break;
                    }
                }

                if (!$filaMovida) {
                    $response->error('Fila não encontrada no grupo', 404);
                    break;
                }

                // Reindexar e inserir na primeira posição
                $grupoFilas = array_values($grupoFilas);
                array_unshift($grupoFilas, $filaMovida);

                // Calcular tempos médios por tipo
                $temposMedios = [
                    'consulta' => 1.0,
                    'exame' => 2.0,
                    'cirurgia' => 5.0
                ];
                $tempoMedio = $temposMedios[$tipoFila] ?? 2.0;

                // Atualizar posições e tempos estimados
                foreach ($grupoFilas as $index => $filaGrupo) {
                    $posicao = $index + 1;
                    $tempoEstimado = ($posicao - 1) * $tempoMedio;
                    
                    $db->query(
                        "UPDATE fila_espera SET posicao_fila = ?, tempo_espera_estimado = ? WHERE id = ?",
                        [$posicao, $tempoEstimado, $filaGrupo['id']]
                    );
                }

                // Registrar movimentação
                try {
                    $historicoAtual = $fila['historico'] ?? '[]';
                    $historicoArray = json_decode($historicoAtual, true) ?? [];
                    
                    $novaMovimentacao = [
                        'data' => date('Y-m-d\TH:i:s'),
                        'descricao' => "Paciente movido para primeira posição. Motivo: {$motivo}",
                        'usuario' => 'Sistema',
                        'motivo' => $motivo
                    ];
                    
                    $historicoArray[] = $novaMovimentacao;
                    $historicoJson = json_encode($historicoArray);
                    
                    $db->query("UPDATE fila_espera SET historico = ?::jsonb WHERE id = ?", [$historicoJson, $id]);
                } catch (Exception $e) {
                    error_log('Erro ao registrar movimentação: ' . $e->getMessage());
                }

                $response->success(['message' => 'Paciente movido para frente com sucesso'], 'Paciente movido', 200);
            } catch (Exception $e) {
                error_log('Erro ao mover paciente para frente: ' . $e->getMessage());
                $response->error('Erro ao mover paciente para frente: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'movimentacao':
            if ($method !== 'POST') {
                $response->error('Método não permitido', 405);
                break;
            }

            try {
                // Obter dados criptografados
                $input = json_decode(file_get_contents('php://input'), true);
                if (!isset($input['dados'])) {
                    $response->error('Dados não fornecidos', 400);
                    break;
                }

                // Descriptografar dados
                try {
                    $dadosJson = Crypto::decryptData($input['dados']);
                    $dados = json_decode($dadosJson, true);

                    if (!$dados || json_last_error() !== JSON_ERROR_NONE) {
                        $response->error('Dados inválidos: ' . json_last_error_msg(), 400);
                        break;
                    }
                } catch (Exception $e) {
                    $response->error('Erro ao descriptografar dados: ' . $e->getMessage(), 400);
                    break;
                }

                // Validar campos obrigatórios
                if (!isset($dados['fila_espera_id'])) {
                    $response->error('fila_espera_id é obrigatório', 400);
                    break;
                }

                $filaEsperaId = $dados['fila_espera_id'];
                $statusAnterior = $dados['status_anterior'] ?? null;
                $statusNovo = $dados['status_novo'] ?? null;
                $observacao = $dados['observacao'] ?? '';
                $usuarioId = $dados['usuario_id'] ?? null;

                // Verificar se fila existe
                $fila = $db->fetchOne("SELECT * FROM fila_espera WHERE id = ?", [$filaEsperaId]);
                if (!$fila) {
                    $response->error('Fila não encontrada', 404);
                    break;
                }

                // Buscar nome do usuário se possível
                $nomeUsuario = 'Sistema';
                if ($usuarioId) {
                    try {
                        $usuarioData = $db->fetchOne("SELECT nome FROM usuarios WHERE id = ?", [$usuarioId]);
                        if ($usuarioData) {
                            $nomeUsuario = $usuarioData['nome'] ?? 'Usuário ' . $usuarioId;
                        }
                    } catch (Exception $e) {
                        // Ignora erro
                    }
                }

                // Buscar histórico atual
                $historicoAtual = $fila['historico'] ?? '[]';
                $historicoArray = json_decode($historicoAtual, true) ?? [];

                // Criar descrição da movimentação
                $descricao = $observacao;
                if ($statusAnterior && $statusNovo) {
                    $descricao = "Status alterado de {$statusAnterior} para {$statusNovo}";
                    if ($observacao) {
                        $descricao .= ". {$observacao}";
                    }
                } elseif ($observacao) {
                    $descricao = $observacao;
                } else {
                    $descricao = 'Movimentação registrada';
                }

                // Adicionar nova movimentação
                $novaMovimentacao = [
                    'data' => date('Y-m-d\TH:i:s'),
                    'descricao' => $descricao,
                    'usuario' => $nomeUsuario,
                    'usuario_id' => $usuarioId,
                    'status_anterior' => $statusAnterior,
                    'status_novo' => $statusNovo,
                    'observacao' => $observacao
                ];

                $historicoArray[] = $novaMovimentacao;
                $historicoJson = json_encode($historicoArray);

                // Atualizar histórico
                $db->query("UPDATE fila_espera SET historico = ?::jsonb WHERE id = ?", [$historicoJson, $filaEsperaId]);

                // Se status_novo foi fornecido e é diferente do atual, atualizar status
                if ($statusNovo && $statusNovo !== ($fila['status'] ?? '')) {
                    $db->query("UPDATE fila_espera SET status = ?, updated_at = NOW() WHERE id = ?", [$statusNovo, $filaEsperaId]);
                }

                $response->success(['message' => 'Movimentação registrada com sucesso'], 'Movimentação registrada', 201);
            } catch (Exception $e) {
                error_log('Erro ao registrar movimentação: ' . $e->getMessage());
                $response->error('Erro ao registrar movimentação: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'historico':
            if ($method !== 'GET') {
                $response->error('Método não permitido', 405);
                break;
            }

            if (!$id) {
                $response->error('ID não fornecido', 400);
                break;
            }

            try {
                // Verificar se fila existe
                $fila = $db->fetchOne("SELECT historico FROM fila_espera WHERE id = ?", [$id]);
                if (!$fila) {
                    $response->error('Fila não encontrada', 404);
                    break;
                }

                // Buscar histórico
                $historicoJson = $fila['historico'] ?? '[]';
                $historicoArray = json_decode($historicoJson, true) ?? [];

                // Ordenar por data (mais recente primeiro)
                usort($historicoArray, function($a, $b) {
                    $dataA = $a['data'] ?? '';
                    $dataB = $b['data'] ?? '';
                    return strcmp($dataB, $dataA); // Descendente
                });

                // Criptografar e retornar
                $respostaJson = json_encode($historicoArray);
                $respostaCriptografada = Crypto::encryptString($respostaJson);
                
                header('Content-Type: application/json');
                echo json_encode([
                    'dados' => $respostaCriptografada
                ]);
                exit;
            } catch (Exception $e) {
                error_log('Erro ao buscar histórico: ' . $e->getMessage());
                $response->error('Erro ao buscar histórico: ' . $e->getMessage(), 500);
                break;
            }
            break;

        case 'relatorio':
            // Rota para relatórios: /api/fila-espera/relatorio/{tipo}
            $tipoRelatorio = $id ?? ''; // O ID aqui é na verdade o tipo do relatório
            
            if ($method !== 'GET') {
                $response->error('Método não permitido', 405);
                break;
            }

            if ($tipoRelatorio === 'fila') {
                // Relatório completo da fila
                try {
                    // Validar parâmetros obrigatórios
                    if (!isset($_GET['data_inicio']) || !isset($_GET['data_fim'])) {
                        $response->error('data_inicio e data_fim são obrigatórios', 400);
                        break;
                    }

                    $dataInicio = $_GET['data_inicio'];
                    $dataFim = $_GET['data_fim'];

                    // Validar formato de data
                    try {
                        new DateTime($dataInicio);
                        new DateTime($dataFim);
                    } catch (Exception $e) {
                        $response->error('Formato de data inválido. Use YYYY-MM-DD', 400);
                        break;
                    }

                    // Aplicar filtros opcionais
                    $where = [
                        "DATE(fe.data_entrada_fila) >= ?",
                        "DATE(fe.data_entrada_fila) <= ?"
                    ];
                    $params = [$dataInicio, $dataFim];

                    if (isset($_GET['status'])) {
                        $where[] = "fe.status = ?";
                        $params[] = $_GET['status'];
                    }

                    if (isset($_GET['prioridade'])) {
                        $where[] = "fe.prioridade = ?";
                        $params[] = $_GET['prioridade'];
                    }

                    if (isset($_GET['tipo_fila'])) {
                        $where[] = "f.tipo_fila = ?";
                        $params[] = $_GET['tipo_fila'];
                    }

                    if (isset($_GET['unidade_id'])) {
                        $where[] = "fe.unidade_id = ?";
                        $params[] = $_GET['unidade_id'];
                    }

                    if (isset($_GET['especialidade_id'])) {
                        $where[] = "fe.especialidade_id = ?";
                        $params[] = $_GET['especialidade_id'];
                    }

                    $whereClause = 'WHERE ' . implode(' AND ', $where);

                    // Buscar todas as filas com filtros
                    $sql = "SELECT fe.*, f.tipo_fila 
                            FROM fila_espera fe
                            LEFT JOIN filas f ON fe.fila_id = f.id
                            $whereClause
                            ORDER BY fe.data_entrada_fila DESC";
                    
                    $filas = $db->fetchAll($sql, $params);

                    // Calcular métricas
                    $total = count($filas);
                    $concluidos = 0;
                    $cancelados = 0;
                    $emAndamento = 0;
                    $temposEspera = [];
                    $temposRegulacao = [];
                    $temposAgendamento = [];

                    $porStatus = [
                        'pendente' => 0,
                        'regulado' => 0,
                        'agendado' => 0,
                        'concluido' => 0,
                        'cancelado' => 0
                    ];

                    $porPrioridade = [
                        'emergencia' => 0,
                        'urgente' => 0,
                        'prioritaria' => 0,
                        'eletiva' => 0
                    ];

                    $porTipo = [
                        'consulta' => 0,
                        'exame' => 0,
                        'cirurgia' => 0
                    ];

                    $detalhes = [];

                    foreach ($filas as $fila) {
                        $status = $fila['status'] ?? 'pendente';
                        $prioridade = $fila['prioridade'] ?? 'eletiva';
                        $tipo = $fila['tipo_fila'] ?? 'consulta';

                        // Contar por status
                        if (isset($porStatus[$status])) {
                            $porStatus[$status]++;
                        }

                        // Contar por prioridade
                        if (isset($porPrioridade[$prioridade])) {
                            $porPrioridade[$prioridade]++;
                        }

                        // Contar por tipo
                        if (isset($porTipo[$tipo])) {
                            $porTipo[$tipo]++;
                        }

                        // Contar totais
                        if ($status === 'concluido') {
                            $concluidos++;
                        } elseif ($status === 'cancelado') {
                            $cancelados++;
                        } else {
                            $emAndamento++;
                        }

                        // Calcular tempos
                        try {
                            if (!empty($fila['data_entrada_fila'])) {
                                $dataEntrada = new DateTime($fila['data_entrada_fila']);
                                
                                // Tempo total de espera
                                $dataFim = !empty($fila['data_conclusao']) 
                                    ? new DateTime($fila['data_conclusao'])
                                    : new DateTime();
                                $tempoTotal = $dataEntrada->diff($dataFim)->days;
                                $temposEspera[] = $tempoTotal;

                                // Tempo de regulação
                                if (!empty($fila['data_regulacao'])) {
                                    $dataRegulacao = new DateTime($fila['data_regulacao']);
                                    $tempoReg = $dataEntrada->diff($dataRegulacao)->days;
                                    $temposRegulacao[] = $tempoReg;
                                }

                                // Tempo de agendamento
                                if (!empty($fila['data_agendamento']) && !empty($fila['data_regulacao'])) {
                                    $dataAgendamento = new DateTime($fila['data_agendamento']);
                                    $dataRegulacao = new DateTime($fila['data_regulacao']);
                                    $tempoAgend = $dataRegulacao->diff($dataAgendamento)->days;
                                    $temposAgendamento[] = $tempoAgend;
                                }

                                // Adicionar aos detalhes
                                $detalhes[] = [
                                    'id' => $fila['id'],
                                    'paciente_id' => $fila['paciente_id'],
                                    'procedimento_id' => $fila['procedimento_id'],
                                    'data_entrada' => $fila['data_entrada_fila'],
                                    'data_conclusao' => $fila['data_conclusao'] ?? null,
                                    'tempo_total' => $tempoTotal,
                                    'status' => $status,
                                    'prioridade' => $prioridade,
                                    'tipo_fila' => $tipo
                                ];
                            }
                        } catch (Exception $e) {
                            // Ignora erro de data
                        }
                    }

                    // Calcular médias
                    $tempoMedioEspera = !empty($temposEspera) 
                        ? array_sum($temposEspera) / count($temposEspera) 
                        : 0;
                    
                    $tempoMedioRegulacao = !empty($temposRegulacao) 
                        ? array_sum($temposRegulacao) / count($temposRegulacao) 
                        : 0;
                    
                    $tempoMedioAgendamento = !empty($temposAgendamento) 
                        ? array_sum($temposAgendamento) / count($temposAgendamento) 
                        : 0;

                    $taxaConclusao = $total > 0 ? ($concluidos / $total) : 0;

                    // Montar relatório
                    $relatorio = [
                        'periodo' => [
                            'inicio' => $dataInicio,
                            'fim' => $dataFim
                        ],
                        'filtros_aplicados' => [
                            'status' => $_GET['status'] ?? null,
                            'prioridade' => $_GET['prioridade'] ?? null,
                            'tipo_fila' => $_GET['tipo_fila'] ?? null,
                            'unidade_id' => $_GET['unidade_id'] ?? null,
                            'especialidade_id' => $_GET['especialidade_id'] ?? null
                        ],
                        'resumo' => [
                            'total' => $total,
                            'concluidos' => $concluidos,
                            'cancelados' => $cancelados,
                            'em_andamento' => $emAndamento
                        ],
                        'metricas' => [
                            'tempo_medio_espera' => round($tempoMedioEspera, 2),
                            'tempo_medio_regulacao' => round($tempoMedioRegulacao, 2),
                            'tempo_medio_agendamento' => round($tempoMedioAgendamento, 2),
                            'taxa_conclusao' => round($taxaConclusao, 2)
                        ],
                        'distribuicoes' => [
                            'por_status' => $porStatus,
                            'por_prioridade' => $porPrioridade,
                            'por_tipo' => $porTipo
                        ],
                        'detalhes' => array_slice($detalhes, 0, 100) // Limitar a 100 registros
                    ];

                    // Criptografar e retornar
                    $respostaJson = json_encode($relatorio);
                    $respostaCriptografada = Crypto::encryptString($respostaJson);
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'dados' => $respostaCriptografada
                    ]);
                    exit;
                } catch (Exception $e) {
                    error_log('Erro ao gerar relatório de fila: ' . $e->getMessage());
                    $response->error('Erro ao gerar relatório: ' . $e->getMessage(), 500);
                    break;
                }
            } elseif ($tipoRelatorio === 'performance') {
                // Relatório de performance
                try {
                    // Validar parâmetros obrigatórios
                    if (!isset($_GET['data_inicio']) || !isset($_GET['data_fim'])) {
                        $response->error('data_inicio e data_fim são obrigatórios', 400);
                        break;
                    }

                    $dataInicio = $_GET['data_inicio'];
                    $dataFim = $_GET['data_fim'];

                    // Validar formato de data
                    try {
                        new DateTime($dataInicio);
                        new DateTime($dataFim);
                    } catch (Exception $e) {
                        $response->error('Formato de data inválido. Use YYYY-MM-DD', 400);
                        break;
                    }

                    // Buscar todas as filas do período
                    $sql = "SELECT fe.*, f.tipo_fila 
                            FROM fila_espera fe
                            LEFT JOIN filas f ON fe.fila_id = f.id
                            WHERE DATE(fe.data_entrada_fila) >= ? 
                              AND DATE(fe.data_entrada_fila) <= ?
                            ORDER BY fe.data_entrada_fila DESC";
                    
                    $filas = $db->fetchAll($sql, [$dataInicio, $dataFim]);

                    // Calcular tempos por etapa
                    $temposPendenteParaRegulado = [];
                    $temposReguladoParaAgendado = [];
                    $temposAgendadoParaConcluido = [];
                    $temposTotais = [];

                    // Taxas de conversão
                    $totalPendente = 0;
                    $totalRegulado = 0;
                    $totalAgendado = 0;
                    $totalConcluido = 0;

                    $conversoesPendenteRegulado = 0;
                    $conversoesReguladoAgendado = 0;
                    $conversoesAgendadoConcluido = 0;

                    // Performance por prioridade
                    $performancePorPrioridade = [
                        'emergencia' => ['tempos' => [], 'concluidos' => 0, 'total' => 0],
                        'urgente' => ['tempos' => [], 'concluidos' => 0, 'total' => 0],
                        'prioritaria' => ['tempos' => [], 'concluidos' => 0, 'total' => 0],
                        'eletiva' => ['tempos' => [], 'concluidos' => 0, 'total' => 0]
                    ];

                    foreach ($filas as $fila) {
                        $prioridade = $fila['prioridade'] ?? 'eletiva';
                        $status = $fila['status'] ?? 'pendente';

                        // Contar totais
                        if ($status === 'pendente' || $status === 'regulado' || $status === 'agendado' || $status === 'concluido') {
                            $totalPendente++;
                        }
                        if ($status === 'regulado' || $status === 'agendado' || $status === 'concluido') {
                            $totalRegulado++;
                            $conversoesPendenteRegulado++;
                        }
                        if ($status === 'agendado' || $status === 'concluido') {
                            $totalAgendado++;
                            $conversoesReguladoAgendado++;
                        }
                        if ($status === 'concluido') {
                            $totalConcluido++;
                            $conversoesAgendadoConcluido++;
                        }

                        // Performance por prioridade
                        if (isset($performancePorPrioridade[$prioridade])) {
                            $performancePorPrioridade[$prioridade]['total']++;
                            if ($status === 'concluido') {
                                $performancePorPrioridade[$prioridade]['concluidos']++;
                            }
                        }

                        // Calcular tempos
                        try {
                            if (!empty($fila['data_entrada_fila'])) {
                                $dataEntrada = new DateTime($fila['data_entrada_fila']);

                                // Pendente -> Regulado
                                if (!empty($fila['data_regulacao'])) {
                                    $dataRegulacao = new DateTime($fila['data_regulacao']);
                                    $tempo = $dataEntrada->diff($dataRegulacao)->days;
                                    $temposPendenteParaRegulado[] = $tempo;
                                }

                                // Regulado -> Agendado
                                if (!empty($fila['data_regulacao']) && !empty($fila['data_agendamento'])) {
                                    $dataRegulacao = new DateTime($fila['data_regulacao']);
                                    $dataAgendamento = new DateTime($fila['data_agendamento']);
                                    $tempo = $dataRegulacao->diff($dataAgendamento)->days;
                                    $temposReguladoParaAgendado[] = $tempo;
                                }

                                // Agendado -> Concluído
                                if (!empty($fila['data_agendamento']) && !empty($fila['data_conclusao'])) {
                                    $dataAgendamento = new DateTime($fila['data_agendamento']);
                                    $dataConclusao = new DateTime($fila['data_conclusao']);
                                    $tempo = $dataAgendamento->diff($dataConclusao)->days;
                                    $temposAgendadoParaConcluido[] = $tempo;
                                }

                                // Tempo total
                                if (!empty($fila['data_conclusao'])) {
                                    $dataConclusao = new DateTime($fila['data_conclusao']);
                                    $tempoTotal = $dataEntrada->diff($dataConclusao)->days;
                                    $temposTotais[] = $tempoTotal;

                                    // Adicionar ao tempo por prioridade
                                    if (isset($performancePorPrioridade[$prioridade])) {
                                        $performancePorPrioridade[$prioridade]['tempos'][] = $tempoTotal;
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            // Ignora erro de data
                        }
                    }

                    // Calcular médias
                    $tempoMedioPendenteRegulado = !empty($temposPendenteParaRegulado) 
                        ? array_sum($temposPendenteParaRegulado) / count($temposPendenteParaRegulado) 
                        : 0;
                    
                    $tempoMedioReguladoAgendado = !empty($temposReguladoParaAgendado) 
                        ? array_sum($temposReguladoParaAgendado) / count($temposReguladoParaAgendado) 
                        : 0;
                    
                    $tempoMedioAgendadoConcluido = !empty($temposAgendadoParaConcluido) 
                        ? array_sum($temposAgendadoParaConcluido) / count($temposAgendadoParaConcluido) 
                        : 0;
                    
                    $tempoMedioTotal = !empty($temposTotais) 
                        ? array_sum($temposTotais) / count($temposTotais) 
                        : 0;

                    // Calcular taxas de conversão
                    $taxaPendenteRegulado = $totalPendente > 0 
                        ? ($conversoesPendenteRegulado / $totalPendente) 
                        : 0;
                    
                    $taxaReguladoAgendado = $totalRegulado > 0 
                        ? ($conversoesReguladoAgendado / $totalRegulado) 
                        : 0;
                    
                    $taxaAgendadoConcluido = $totalAgendado > 0 
                        ? ($conversoesAgendadoConcluido / $totalAgendado) 
                        : 0;

                    // Calcular performance por prioridade
                    $performancePorPrioridadeFormatada = [];
                    foreach ($performancePorPrioridade as $prioridade => $dados) {
                        $tempoMedio = !empty($dados['tempos']) 
                            ? array_sum($dados['tempos']) / count($dados['tempos']) 
                            : 0;
                        
                        $taxaConclusao = $dados['total'] > 0 
                            ? ($dados['concluidos'] / $dados['total']) 
                            : 0;

                        $performancePorPrioridadeFormatada[$prioridade] = [
                            'tempo_medio' => round($tempoMedio, 2),
                            'taxa_conclusao' => round($taxaConclusao, 2),
                            'total' => $dados['total'],
                            'concluidos' => $dados['concluidos']
                        ];
                    }

                    // Montar relatório
                    $relatorio = [
                        'periodo' => [
                            'inicio' => $dataInicio,
                            'fim' => $dataFim
                        ],
                        'tempos_medios_por_etapa' => [
                            'pendente_para_regulado' => round($tempoMedioPendenteRegulado, 2),
                            'regulado_para_agendado' => round($tempoMedioReguladoAgendado, 2),
                            'agendado_para_concluido' => round($tempoMedioAgendadoConcluido, 2),
                            'total' => round($tempoMedioTotal, 2)
                        ],
                        'taxas_conversao' => [
                            'pendente_para_regulado' => round($taxaPendenteRegulado, 2),
                            'regulado_para_agendado' => round($taxaReguladoAgendado, 2),
                            'agendado_para_concluido' => round($taxaAgendadoConcluido, 2)
                        ],
                        'performance_por_prioridade' => $performancePorPrioridadeFormatada
                    ];

                    // Criptografar e retornar
                    $respostaJson = json_encode($relatorio);
                    $respostaCriptografada = Crypto::encryptString($respostaJson);
                    
                    header('Content-Type: application/json');
                    echo json_encode([
                        'dados' => $respostaCriptografada
                    ]);
                    exit;
                } catch (Exception $e) {
                    error_log('Erro ao gerar relatório de performance: ' . $e->getMessage());
                    $response->error('Erro ao gerar relatório: ' . $e->getMessage(), 500);
                    break;
                }
            } else {
                $response->error('Tipo de relatório inválido. Use "fila" ou "performance"', 400);
                break;
            }
            break;

        default:
            $response->error('Ação não encontrada: ' . $action, 404);
            break;
    }
} catch (Exception $e) {
    // Log do erro completo para debug
    error_log('Erro em fila_espera.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Usar a classe Response para padronizar o formato de erro
    $response->error(
        'Erro: ' . $e->getMessage() . ' | Arquivo: ' . $e->getFile() . ' | Linha: ' . $e->getLine(),
        500
    );
}

/**
 * Formata uma fila com todos os dados relacionados
 */
function _formatarFilaCompleta($db, $fila) {
    // Buscar dados relacionados de forma segura
    $pacienteNome = 'Paciente ' . ($fila['paciente_id'] ?? '');
    $pacienteCpf = '';
    $pacienteDataNasc = '';
    $pacienteIdade = '';
    $pacienteSexo = '';
    
    $procedimentoDesc = 'Procedimento ' . ($fila['procedimento_id'] ?? '');
    $especialidadeDesc = 'Especialidade ' . ($fila['especialidade_id'] ?? '');
    $unidadeDesc = 'Unidade ' . ($fila['unidade_id'] ?? '');
    $medicoNome = 'Médico ' . ($fila['medico_solicitante_id'] ?? '');
    $medicoCrm = '';
    
    $filaDesc = 'Fila ' . ($fila['fila_id'] ?? '');
    $filaCor = 4280391411;
    $filaTipo = 'consulta';
    
    // Buscar dados do paciente
    if (!empty($fila['paciente_id'])) {
        try {
            // Tentar diferentes possibilidades de nome de tabela/coluna
            $pacienteData = null;
            $pacienteId = $fila['paciente_id'];
            
            // Tentativa 1: paciente.codpaciente (nome correto: nomepaciente, data nascimento com espaço)
            try {
                $pacienteData = $db->fetchOne("SELECT nomepaciente, cpf, \"data nascimento\" as datanascimento, sexo FROM paciente WHERE codpaciente = ?", [$pacienteId]);
            } catch (Exception $e1) {
                error_log("Erro ao buscar paciente (codpaciente): " . $e1->getMessage());
                
                // Tentativa 2: paciente.id
                try {
                    $pacienteData = $db->fetchOne("SELECT nomepaciente, cpf, \"data nascimento\" as datanascimento, sexo FROM paciente WHERE id = ?", [$pacienteId]);
                } catch (Exception $e2) {
                    error_log("Erro ao buscar paciente (id): " . $e2->getMessage());
                    
                    // Tentativa 3: pacientes.codpaciente
                    try {
                        $pacienteData = $db->fetchOne("SELECT nomepaciente, cpf, \"data nascimento\" as datanascimento, sexo FROM pacientes WHERE codpaciente = ?", [$pacienteId]);
                    } catch (Exception $e3) {
                        error_log("Erro ao buscar paciente (pacientes.codpaciente): " . $e3->getMessage());
                        
                        // Tentativa 4: pacientes.id
                        try {
                            $pacienteData = $db->fetchOne("SELECT nomepaciente, cpf, \"data nascimento\" as datanascimento, sexo FROM pacientes WHERE id = ?", [$pacienteId]);
                        } catch (Exception $e4) {
                            error_log("Erro ao buscar paciente (pacientes.id): " . $e4->getMessage());
                        }
                    }
                }
            }
            
            if ($pacienteData) {
                $pacienteNome = $pacienteData['nomepaciente'] ?? $pacienteNome;
                $pacienteCpf = $pacienteData['cpf'] ?? '';
                $pacienteDataNasc = $pacienteData['datanascimento'] ?? '';
                $pacienteSexo = $pacienteData['sexo'] ?? '';
                $pacienteIdade = '';
                
                error_log("✅ Paciente encontrado: ID=$pacienteId, Nome=$pacienteNome");
                
                // Calcular idade a partir da data de nascimento
                if (!empty($pacienteDataNasc)) {
                    try {
                        $dataNasc = new DateTime($pacienteDataNasc);
                        $hoje = new DateTime();
                        $pacienteIdade = (string)$hoje->diff($dataNasc)->y;
                    } catch (Exception $e) {
                        error_log("Erro ao calcular idade: " . $e->getMessage());
                    }
                }
            } else {
                error_log("⚠️ Paciente não encontrado: ID=$pacienteId");
            }
        } catch (Exception $e) {
            error_log("❌ Erro ao buscar paciente: " . $e->getMessage());
            // Ignora erro
        }
    }
    
    // Buscar dados da fila
    if (!empty($fila['fila_id'])) {
        try {
            $filaData = $db->fetchOne("SELECT descricao, cor, tipo_fila FROM filas WHERE id = ?", [$fila['fila_id']]);
            if ($filaData) {
                $filaDesc = $filaData['descricao'] ?? $filaDesc;
                $filaCor = $filaData['cor'] ?? $filaCor;
                $filaTipo = $filaData['tipo_fila'] ?? $filaTipo;
            }
        } catch (Exception $e) {
            // Ignora erro
        }
    }
    
    // Buscar descrição do procedimento
    if (!empty($fila['procedimento_id'])) {
        try {
            $procData = $db->fetchOne("SELECT descricaoprocedimento FROM procedimentos WHERE codprocedimento::text = ?", [$fila['procedimento_id']]);
            if ($procData) {
                $procedimentoDesc = $procData['descricaoprocedimento'] ?? $procedimentoDesc;
            }
        } catch (Exception $e) {
            // Ignora erro
        }
    }
    
    // Buscar descrição da especialidade
    if (!empty($fila['especialidade_id'])) {
        try {
            $espData = $db->fetchOne("SELECT especialidade FROM especialidades WHERE codespecialidade = ?", [$fila['especialidade_id']]);
            if ($espData) {
                $especialidadeDesc = $espData['especialidade'] ?? $especialidadeDesc;
            }
        } catch (Exception $e) {
            // Ignora erro
        }
    }
    
    // Buscar descrição da unidade
    if (!empty($fila['unidade_id'])) {
        try {
            $unidData = $db->fetchOne("SELECT unidades FROM unidades WHERE codunidades = ?", [$fila['unidade_id']]);
            if ($unidData) {
                $unidadeDesc = $unidData['unidades'] ?? $unidadeDesc;
            }
        } catch (Exception $e) {
            // Ignora erro
        }
    }
    
    // Buscar dados do médico
    if (!empty($fila['medico_solicitante_id'])) {
        try {
            $medData = $db->fetchOne("SELECT profissional FROM profissionais WHERE codprofissional = ?", [$fila['medico_solicitante_id']]);
            if ($medData) {
                $medicoNome = $medData['profissional'] ?? $medicoNome;
            }
        } catch (Exception $e) {
            // Ignora erro
        }
    }
    
    return [
        'id' => $fila['id'] ?? null,
        'fila_id' => $fila['fila_id'] ?? null,
        'paciente_id' => $fila['paciente_id'] ?? null,
        'procedimento_id' => $fila['procedimento_id'] ?? null,
        'especialidade_id' => $fila['especialidade_id'] ?? null,
        'unidade_id' => $fila['unidade_id'] ?? null,
        'medico_solicitante_id' => $fila['medico_solicitante_id'] ?? null,
        'usuario_regulador_id' => $fila['usuario_regulador_id'] ?? null,
        'status' => $fila['status'] ?? 'pendente',
        'prioridade' => $fila['prioridade'] ?? 'eletiva',
        'pontuacao_clinica' => $fila['pontuacao_clinica'] ?? 0,
        'data_solicitacao' => $fila['data_solicitacao'] ?? null,
        'data_prazo' => $fila['data_prazo'] ?? null,
        'data_regulacao' => $fila['data_regulacao'] ?? null,
        'data_agendamento' => $fila['data_agendamento'] ?? null,
        'data_previsao_atendimento' => $fila['data_previsao_atendimento'] ?? null,
        'data_conclusao' => $fila['data_conclusao'] ?? null,
        'data_entrada_fila' => $fila['data_entrada_fila'] ?? null,
        'motivo_clinico' => $fila['motivo_clinico'] ?? '',
        'observacoes_regulacao' => $fila['observacoes_regulacao'] ?? null,
        'documentos_anexos' => $fila['documentos_anexos'] ?? '[]',
        'posicao_fila' => $fila['posicao_fila'] ?? 0,
        'tempo_espera_estimado' => $fila['tempo_espera_estimado'] ?? 0.0,
        'created_at' => $fila['created_at'] ?? null,
        'updated_at' => $fila['updated_at'] ?? null,
        'fila' => [
            'id' => $fila['fila_id'] ?? null,
            'descricao' => $filaDesc,
            'cor' => $filaCor,
            'tipoFila' => $filaTipo,
        ],
        'paciente' => [
            'id' => $fila['paciente_id'] ?? null,
            'nome' => $pacienteNome,
            'cpf' => $pacienteCpf,
            'datanascimento' => $pacienteDataNasc,
            'idade' => $pacienteIdade,
            'sexo' => $pacienteSexo,
        ],
        'procedimento' => [
            'codprocedimento' => $fila['procedimento_id'] ?? null,
            'descricaoprocedimento' => $procedimentoDesc,
        ],
        'especialidade' => [
            'codespecialidade' => $fila['especialidade_id'] ?? null,
            'especialidade' => $especialidadeDesc,
            'ativo' => true,
            'id' => $fila['especialidade_id'] ?? null,
        ],
        'unidade' => [
            'cod_unidade' => $fila['unidade_id'] ?? null,
            'des_unidade' => $unidadeDesc,
        ],
        'medico_solicitante' => [
            'cod_profissional' => $fila['medico_solicitante_id'] ?? null,
            'des_profissional' => $medicoNome,
            'crm' => $medicoCrm,
        ],
    ];
}

/**
 * Reordena as posições de um grupo de filas
 */
function _reordenarGrupoFila($db, $fila) {
    try {
        // Buscar tipo_fila da fila
        $filaData = $db->fetchOne("SELECT tipo_fila FROM filas WHERE id = ?", [$fila['fila_id']]);
        $tipoFila = $filaData['tipo_fila'] ?? 'consulta';
        
        // Buscar todas as filas do mesmo grupo
        $sql = "SELECT fe.*, f.tipo_fila 
                FROM fila_espera fe
                LEFT JOIN filas f ON fe.fila_id = f.id
                WHERE f.tipo_fila = ? 
                  AND fe.especialidade_id = ? 
                  AND fe.unidade_id = ?
                  AND fe.status NOT IN ('concluido', 'cancelado')
                ORDER BY fe.pontuacao_clinica DESC, fe.data_entrada_fila ASC";
        
        $grupoFilas = $db->fetchAll($sql, [
            $tipoFila,
            $fila['especialidade_id'],
            $fila['unidade_id']
        ]);
        
        // Calcular tempos médios por tipo
        $temposMedios = [
            'consulta' => 1.0,
            'exame' => 2.0,
            'cirurgia' => 5.0
        ];
        $tempoMedio = $temposMedios[$tipoFila] ?? 2.0;
        
        // Atualizar posições e tempos estimados
        foreach ($grupoFilas as $index => $filaGrupo) {
            $posicao = $index + 1;
            $tempoEstimado = ($posicao - 1) * $tempoMedio;
            
            $db->query(
                "UPDATE fila_espera SET posicao_fila = ?, tempo_espera_estimado = ? WHERE id = ?",
                [$posicao, $tempoEstimado, $filaGrupo['id']]
            );
        }
    } catch (Exception $e) {
        error_log('Erro ao reordenar grupo de filas: ' . $e->getMessage());
        // Não falha a operação principal se não conseguir reordenar
    }
}

/**
 * Cria as tabelas necessárias se não existirem
 */
function _criarTabelasSeNaoExistem($db) {
    // 1. Criar tabela filas se não existir
    $checkFilas = $db->fetchOne("SELECT 1 FROM information_schema.tables WHERE table_name = 'filas'", []);
    if (!$checkFilas) {
        $sqlFilas = "CREATE TABLE filas (
            id SERIAL PRIMARY KEY,
            descricao VARCHAR(255) NOT NULL,
            cor INTEGER NOT NULL,
            tipo_fila VARCHAR(20) NOT NULL DEFAULT 'consulta',
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW(),
            CONSTRAINT chk_tipo_fila CHECK (tipo_fila IN ('consulta', 'exame', 'cirurgia'))
        )";
        $db->query($sqlFilas, []);
        
        // Inserir dados iniciais
        $db->query("INSERT INTO filas (descricao, cor, tipo_fila) VALUES 
            ('Fila de Consultas', 4280391411, 'consulta'),
            ('Fila de Exames', 4280391411, 'exame'),
            ('Fila de Cirurgias', 4280391411, 'cirurgia')", []);
    }
    
    // 2. Criar tabela fila_espera se não existir
    $checkFilaEspera = $db->fetchOne("SELECT 1 FROM information_schema.tables WHERE table_name = 'fila_espera'", []);
    if (!$checkFilaEspera) {
        // Verificar qual é a chave primária da tabela paciente
        $pacientePk = $db->fetchOne("
            SELECT column_name 
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu 
                ON tc.constraint_name = kcu.constraint_name
            WHERE tc.table_name = 'paciente' 
                AND tc.constraint_type = 'PRIMARY KEY'
            LIMIT 1
        ", []);
        
        $pacientePkColumn = $pacientePk ? $pacientePk['column_name'] : 'codpaciente';
        
        // Verificar estrutura de outras tabelas
        $especialidadePk = $db->fetchOne("
            SELECT column_name 
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu 
                ON tc.constraint_name = kcu.constraint_name
            WHERE tc.table_name = 'especialidades' 
                AND tc.constraint_type = 'PRIMARY KEY'
            LIMIT 1
        ", []);
        $especialidadePkColumn = $especialidadePk ? $especialidadePk['column_name'] : 'codespecialidade';
        
        $unidadePk = $db->fetchOne("
            SELECT column_name 
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu 
                ON tc.constraint_name = kcu.constraint_name
            WHERE tc.table_name = 'unidades' 
                AND tc.constraint_type = 'PRIMARY KEY'
            LIMIT 1
        ", []);
        $unidadePkColumn = $unidadePk ? $unidadePk['column_name'] : 'codunidades';
        
        // Criar tabela sem foreign keys primeiro (para evitar erros)
        $sqlFilaEspera = "CREATE TABLE fila_espera (
            id SERIAL PRIMARY KEY,
            fila_id INTEGER NOT NULL,
            paciente_id INTEGER NOT NULL,
            procedimento_id VARCHAR(50) NOT NULL,
            especialidade_id INTEGER NOT NULL,
            unidade_id VARCHAR(50) NOT NULL,
            medico_solicitante_id VARCHAR(50) NOT NULL,
            usuario_regulador_id VARCHAR(50),
            
            status VARCHAR(20) NOT NULL DEFAULT 'pendente',
            prioridade VARCHAR(20) NOT NULL DEFAULT 'eletiva',
            pontuacao_clinica INTEGER DEFAULT 0,
            
            data_solicitacao TIMESTAMP NOT NULL,
            data_prazo TIMESTAMP,
            data_regulacao TIMESTAMP,
            data_agendamento TIMESTAMP,
            data_previsao_atendimento TIMESTAMP,
            data_conclusao TIMESTAMP,
            data_entrada_fila TIMESTAMP NOT NULL DEFAULT NOW(),
            
            motivo_clinico TEXT NOT NULL,
            observacoes_regulacao TEXT,
            documentos_anexos JSONB DEFAULT '[]'::jsonb,
            
            posicao_fila INTEGER DEFAULT 0,
            tempo_espera_estimado DECIMAL(10, 2) DEFAULT 0,
            
            created_at TIMESTAMP DEFAULT NOW(),
            updated_at TIMESTAMP DEFAULT NOW(),
            
            CONSTRAINT chk_status CHECK (status IN ('pendente', 'regulado', 'agendado', 'concluido', 'cancelado')),
            CONSTRAINT chk_prioridade CHECK (prioridade IN ('emergencia', 'urgente', 'prioritaria', 'eletiva'))
        )";
        
        $db->query($sqlFilaEspera, []);
        
        // Criar índices
        try {
            $db->query("CREATE INDEX IF NOT EXISTS idx_fila_espera_status ON fila_espera(status)", []);
            $db->query("CREATE INDEX IF NOT EXISTS idx_fila_espera_prioridade ON fila_espera(prioridade)", []);
            $db->query("CREATE INDEX IF NOT EXISTS idx_fila_espera_paciente ON fila_espera(paciente_id)", []);
            $db->query("CREATE INDEX IF NOT EXISTS idx_fila_espera_especialidade ON fila_espera(especialidade_id)", []);
            $db->query("CREATE INDEX IF NOT EXISTS idx_fila_espera_unidade ON fila_espera(unidade_id)", []);
            $db->query("CREATE INDEX IF NOT EXISTS idx_fila_espera_data_entrada ON fila_espera(data_entrada_fila)", []);
        } catch (Exception $e) {
            error_log("Erro ao criar índices: " . $e->getMessage());
        }
        
        // Tentar adicionar foreign key para filas apenas se a tabela existir
        $checkFilasExists = $db->fetchOne("SELECT 1 FROM information_schema.tables WHERE table_name = 'filas'", []);
        if ($checkFilasExists) {
            try {
                // Verificar se a constraint já existe
                $checkConstraint = $db->fetchOne("
                    SELECT 1 FROM information_schema.table_constraints 
                    WHERE constraint_name = 'fk_fila' AND table_name = 'fila_espera'
                ", []);
                
                if (!$checkConstraint) {
                    $db->query("ALTER TABLE fila_espera ADD CONSTRAINT fk_fila FOREIGN KEY (fila_id) REFERENCES filas(id) ON DELETE RESTRICT", []);
                }
            } catch (Exception $e) {
                // Ignora se não conseguir criar foreign key (pode já existir ou ter problema)
                error_log("Não foi possível criar foreign key fk_fila: " . $e->getMessage());
            }
        }
    }
}

