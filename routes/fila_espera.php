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
            } catch (Exception $e) {
                error_log('Erro ao buscar filas: ' . $e->getMessage());
                error_log('SQL: ' . $sql);
                error_log('Params: ' . print_r($params, true));
                $response->error('Erro ao buscar filas: ' . $e->getMessage() . ' | SQL: ' . $sql, 500);
                break;
            }
            
            if ($filas === false) {
                $response->error('Erro ao buscar filas: resultado falso', 500);
                break;
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
                        $pacienteData = $db->fetchOne("SELECT nome, cpf, datanascimento, sexo, idade FROM paciente WHERE codpaciente = ?", [$fila['paciente_id']]);
                        if ($pacienteData) {
                            $pacienteNome = $pacienteData['nome'] ?? $pacienteNome;
                            $pacienteCpf = $pacienteData['cpf'] ?? '';
                            $pacienteDataNasc = $pacienteData['datanascimento'] ?? '';
                            $pacienteSexo = $pacienteData['sexo'] ?? '';
                            $pacienteIdade = $pacienteData['idade'] ?? '';
                            
                            // Calcular idade se não vier preenchida
                            if (empty($pacienteIdade) && !empty($pacienteDataNasc)) {
                                try {
                                    $dataNasc = new DateTime($pacienteDataNasc);
                                    $hoje = new DateTime();
                                    $pacienteIdade = (string)$hoje->diff($dataNasc)->y;
                                } catch (Exception $e) {
                                    // Ignora erro
                                }
                            }
                        }
                    } catch (Exception $e) {
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

            // Criptografar resposta
            try {
                $respostaJson = json_encode($filasFormatadas);
                if ($respostaJson === false) {
                    throw new Exception('Erro ao codificar JSON: ' . json_last_error_msg());
                }
                $respostaCriptografada = Crypto::encryptString($respostaJson);

                // Retornar no formato esperado pelo Flutter (apenas 'dados')
                header('Content-Type: application/json');
                echo json_encode([
                    'dados' => $respostaCriptografada
                ]);
                exit;
            } catch (Exception $e) {
                error_log('Erro ao processar resposta: ' . $e->getMessage());
                $response->error('Erro ao processar resposta: ' . $e->getMessage(), 500);
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

