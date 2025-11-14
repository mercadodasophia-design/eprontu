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

            foreach ($camposObrigatorios as $campo) {
                if (!isset($dados[$campo])) {
                    $response->error("Campo obrigatório ausente: $campo", 400);
                    break 2;
                }
            }

            // Extrair dados da fila
            $fila = $dados['fila'];
            $filaId = is_array($fila) ? ($fila['id'] ?? null) : $fila;
            
            if (!$filaId) {
                $response->error('ID da fila não fornecido', 400);
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
            try {
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
                    $response->error('Erro ao inserir fila: ' . $e->getMessage(), 500);
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
    // Log do erro completo para debug
    error_log('Erro em fila_espera.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Retornar erro detalhado
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'erro',
        'dados' => 'Erro: ' . $e->getMessage() . ' | Arquivo: ' . $e->getFile() . ' | Linha: ' . $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
    exit;
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

