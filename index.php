<?php
/**
 * API REST - Sistema e-prontu
 * Endpoint principal da API
 */

// Incluir configurações
require_once 'config/server_config.php';
require_once 'config/database.php';
require_once 'config/constants.php';

// Configurar headers CORS
setCorsHeaders();

// Tratar requisições OPTIONS
handleOptionsRequest();

// Incluir classes
require_once 'classes/Response.php';
require_once 'classes/Auth.php';
require_once 'classes/User.php';
require_once 'classes/Permission.php';

// Obter método e URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Remover query string da URI
$uri = strtok($uri, '?');

// Dividir URI em segmentos
$segments = explode('/', trim($uri, '/'));

// Remover 'e-prontu' e 'api' do início se presente
if (isset($segments[0]) && $segments[0] === 'e-prontu') {
    array_shift($segments);
}
if (isset($segments[0]) && $segments[0] === 'api') {
    array_shift($segments);
}

// Determinar endpoint
$endpoint = isset($segments[0]) ? $segments[0] : '';
$action = isset($segments[1]) ? $segments[1] : '';

// Debug: mostrar informações
if (isset($_GET['debug'])) {
    echo json_encode([
        'endpoint' => $endpoint,
        'action' => $action,
        'segments' => $segments,
        'uri' => $uri
    ]);
    exit();
}

// Instanciar Response
$response = new Response();

try {
    // Roteamento
    switch ($endpoint) {
        case '':
            header('Content-Type: text/html; charset=utf-8');
            $homePath = __DIR__ . '/home.html';
            if (file_exists($homePath)) {
                readfile($homePath);
            } else {
                echo '<!doctype html><html><head><meta charset="utf-8"><title>API Home</title></head><body><h1>API Home</h1><p>Arquivo home.html não encontrado.</p></body></html>';
            }
            break;
        case 'auth':
            // Debug: verificar se o arquivo existe
            if (!file_exists('routes/auth.php')) {
                $response->error('Arquivo routes/auth.php não encontrado', 500);
            }
            
            // Teste direto para validate-email
            if ($action === 'validate-email' && $method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input || empty($input['email'])) {
                    $response->error('Email é obrigatório', 400);
                }
                
                // Buscar email real no banco de dados
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    $email = strtolower(trim($input['email']));
                    $sql = "SELECT DISTINCT(unidade) FROM usuarios WHERE email = ? AND status = ?";
                    $params = [$email, 'A'];
                    
                    $units = $db->fetchAll($sql, $params);
                    
                    if (empty($units)) {
                        $response->error('Usuário não encontrado', 404);
                    }
                    
                    $unitIds = array_column($units, 'unidade');
                    // Converter para string para evitar erro de tipo
                    $unitIds = array_map('strval', $unitIds);
                    $response->success([
                        'email' => $email,
                        'units' => $unitIds
                    ], 'Email válido');
                } catch (Exception $e) {
                    $response->error('Erro na validação: ' . $e->getMessage(), 500);
                }
            } else if ($action === 'get-units' && $method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                
                if (!$input || empty($input['unit_ids'])) {
                    $response->error('IDs das unidades são obrigatórios', 400);
                }
                
                // Buscar unidades reais do banco de dados
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    $unitIds = $input['unit_ids'];
                    $placeholders = str_repeat('?,', count($unitIds) - 1) . '?';
                    $sql = "SELECT codunidades, unidades FROM unidades WHERE ativo = ? AND codunidades IN ({$placeholders}) ORDER BY unidades";
                    $params = array_merge(['S'], $unitIds);
                    
                    $units = $db->fetchAll($sql, $params);
                    // Converter IDs para string
                    foreach ($units as &$unit) {
                        $unit['codunidades'] = (string)$unit['codunidades'];
                    }
                    $response->success($units, 'Unidades carregadas com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar unidades: ' . $e->getMessage(), 500);
                }
            } else if ($action === 'login' && $method === 'POST') {
                // Agora que sabemos que está funcionando, implementar o login real
                $email = strtolower(trim($_POST['email'] ?? ''));
                $password = trim($_POST['password'] ?? '');
                $unit = trim($_POST['unit'] ?? '');
                
                if (empty($email) || empty($password) || empty($unit)) {
                    $response->error('Email, senha e unidade são obrigatórios', 400);
                }
                
                // Buscar usuário real no banco de dados
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    // Usar a query exata do e-prontu original
                    $sql = "SELECT nomeusuario, TRIM(senha) as senha, perfil, status, codusuario, unidade, unidades.unidades, empresa, TRIM(usuarios.email) as email, unidades.logo, unidades.lytagenda, usuarios.codmed
                            FROM usuarios, unidades
                            WHERE usuarios.email = ? AND senha = ? AND status = ? AND unidade = ? AND codunidades = unidade";
                    $params = [$email, $password, 'A', $unit];
                    
                    $user = $db->fetchOne($sql, $params);
                    
                    if (!$user) {
                        $response->error('Credenciais inválidas', 401);
                    }
                    
                    // Atualizar log de login
                    $updateSql = "UPDATE usuarios SET horalogon = ?, datalogon = ? WHERE codusuario = ?";
                    $updateParams = [date('H:i:s'), date('d-m-Y'), $user['codusuario']];
                    $db->query($updateSql, $updateParams);
                    
                    // Buscar permissões
                    $permSql = "SELECT * FROM usuarios_permissao WHERE codusuario = ?";
                    $permissions = $db->fetchOne($permSql, [$user['codusuario']]);
                    
                    $response->success([
                        'user' => [
                            'codusuario' => (string)$user['codusuario'],
                            'nomeusuario' => (string)$user['nomeusuario'],
                            'email' => (string)$user['email'],
                            'perfil' => (string)$user['perfil'],
                            'status' => (string)$user['status'],
                            'unidade' => (string)$user['unidade'],
                            'empresa' => (string)$user['empresa'],
                            'unidadeData' => [
                                'codunidades' => (string)$user['unidade'],
                                'unidades' => (string)$user['unidades'],
                                'logo' => (string)$user['logo'],
                                'lytagenda' => (string)$user['lytagenda']
                            ],
                            'permissions' => $permissions ?: []
                        ],
                        'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyX2lkIjoi' . $user['codusuario'] . 'IiwiZW1haWwiOiI' . $email . 'IiwidW5pdF9pZCI6Ii' . $unit . 'IiwiaWF0IjoxNjMzNjQ4MDAwLCJleHAiOjE2MzM2NTE2MDB9.example',
                        'expires_in' => 3600
                    ], 'Login realizado com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro no login: ' . $e->getMessage(), 500);
                }
            } else {
                require_once 'routes/auth.php';
            }
            break;
            
        case 'users':
            require_once 'routes/users.php';
            break;
            
        case 'patients':
            require_once 'routes/patients.php';
            break;
            
        case 'appointments':
            require_once 'routes/appointments.php';
            break;
            
        case 'medical-records':
            require_once 'routes/medical-records.php';
            break;
            
        case 'permissions':
            require_once 'routes/permissions.php';
            break;
            
        case 'dashboard':
            require_once 'routes/dashboard.php';
            break;
            
        case 'anamnese':
            require_once 'routes/anamnese.php';
            break;
            
        case 'exames':
            require_once 'routes/exames.php';
            break;
            
        case 'receituario':
            require_once 'routes/receituario.php';
            break;
            
        case 'bobina-exames':
            require_once 'routes/bobina_exames.php';
            break;
            
        case 'bobina-cirurgias':
            require_once 'routes/bobina_cirurgias.php';
            break;
            
        case 'bobina-medicamentos':
            require_once 'routes/bobina_medicamentos.php';
            break;
            
        case 'bobina-documentos':
            require_once 'routes/bobina_documentos.php';
            break;

        case 'campanhas':
            // Verificar se é uma ação de interações
            // Para /api/campanhas/interacoes/{acao}, segments[1] = 'interacoes'
            $campanhaAction = $segments[1] ?? '';
            
            if ($campanhaAction === 'interacoes') {
                // Rota para interações: /api/campanhas/interacoes/{acao}
                // A ação específica (salvar, historico, etc) está em $segments[2]
                require_once 'routes/campanhas_interacoes.php';
            } else {
                // Rota padrão de campanhas
                // A ação está em $segments[1] (listar, add, update, etc)
                require_once 'routes/campanhas.php';
            }
            break;
            
        case 'bobina-timeline':
            require_once 'routes/bobina_timeline.php';
            break;
                   
               case 'especialidades':
                   // GET /api/especialidades
                   try {
                       require_once 'config/database.php';
                       $db = Database::getInstance();
                       
                       // Primeiro, verificar se a tabela existe
                       $checkTable = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_name = 'especialidades'", []);
                       
                       if (empty($checkTable)) {
                           $response->error('Tabela especialidades não encontrada', 404);
                           break;
                       }
                       
                       // Buscar dados da tabela
                       $sql = "SELECT codespecialidade, especialidade FROM especialidades ORDER BY especialidade";
                       $especialidades = $db->fetchAll($sql, []);
                       
                       $response->success($especialidades, 'Especialidades carregadas com sucesso');
                   } catch (Exception $e) {
                       $response->error('Erro ao carregar especialidades: ' . $e->getMessage(), 500);
                   }
                   break;
                   
               case 'profissionais':
                   // POST /api/profissionais
                   try {
                       require_once 'config/database.php';
                       $db = Database::getInstance();
                       
                       $input = json_decode(file_get_contents('php://input'), true);
                       $especialidade = $input['especialidade'] ?? '';
                       
                       $sql = "SELECT codprofissional, profissional FROM profissionais WHERE especialidade = ? ORDER BY profissional";
                       $profissionais = $db->fetchAll($sql, [$especialidade]);
                       
                       $response->success($profissionais, 'Profissionais carregados com sucesso');
                   } catch (Exception $e) {
                       $response->error('Erro ao carregar profissionais: ' . $e->getMessage(), 500);
                   }
                   break;
                   
               case 'setores':
                   // GET /api/setores
                   try {
                       require_once 'config/database.php';
                       $db = Database::getInstance();
                       
                       $sql = "SELECT id_setor, se_nomesetor FROM setores WHERE id_setor <> '0' ORDER BY se_nomesetor";
                       $setores = $db->fetchAll($sql, []);
                       
                       $response->success($setores, 'Setores carregados com sucesso');
                   } catch (Exception $e) {
                       $response->error('Erro ao carregar setores: ' . $e->getMessage(), 500);
                   }
                   break;
                   
               case 'consultorios':
                   // GET /api/consultorios
                   try {
                       require_once 'config/database.php';
                       $db = Database::getInstance();
                       
                       $sql = "SELECT id_setor, se_nomesetor FROM setores WHERE id_setor <> '0' AND se_tpsetor = 2 ORDER BY se_nomesetor";
                       $consultorios = $db->fetchAll($sql, []);
                       
                       $response->success($consultorios, 'Consultórios carregados com sucesso');
                   } catch (Exception $e) {
                       $response->error('Erro ao carregar consultórios: ' . $e->getMessage(), 500);
                   }
                   break;
                   
        case 'atendimento':
            // POST /api/atendimento/iniciar
            if ($action === 'iniciar' && $method === 'POST') {
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    $input = json_decode(file_get_contents('php://input'), true);
                    $paciente = $input['paciente'] ?? '';
                    $nomepaciente = $input['nomepaciente'] ?? '';
                    $idade = $input['idade'] ?? 0;
                    $olho = $input['olho'] ?? '';
                    $convenio = $input['convenio'] ?? '';
                    $datanascimento = $input['datanascimento'] ?? '';
                    $codpro = $input['codpro'] ?? '';
                    $movimento = $input['movimento'] ?? '';
                    $descprocedimento = $input['descprocedimento'] ?? '';
                    $codconvenio = $input['codconvenio'] ?? '';
                    
                    if (empty($paciente) || empty($codpro)) {
                        $response->error('Paciente e profissional são obrigatórios', 400);
                        break;
                    }
                    
                    // Buscar parceiro baseado no movimento (similar ao PHP original)
                    $sqlParceiro = "SELECT parceiro FROM procedimentosagendados WHERE nratendimento = ?";
                    $parceiroResult = $db->fetchOne($sqlParceiro, [$movimento]);
                    $parceiro = $parceiroResult ? $parceiroResult['parceiro'] : 'REDEBIO';
                    
                    $atendimentoData = [
                        'paciente' => $paciente,
                        'nomepaciente' => $nomepaciente,
                        'idade' => $idade,
                        'olho' => $olho,
                        'convenio' => $convenio,
                        'datanascimento' => $datanascimento,
                        'codpro' => $codpro,
                        'movimento' => $movimento,
                        'descprocedimento' => $descprocedimento,
                        'codconvenio' => $codconvenio,
                        'parceiro' => $parceiro,
                    ];
                    
                    $response->success($atendimentoData, 'Atendimento iniciado com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao iniciar atendimento: ' . $e->getMessage(), 500);
                }
            }
            // POST /api/atendimento/salvar-anamnese
            else if ($action === 'salvar-anamnese' && $method === 'POST') {
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    $input = json_decode(file_get_contents('php://input'), true);
                    $paciente = $input['paciente'] ?? '';
                    $codpro = $input['codpro'] ?? '';
                    $datamovimento = $input['datamovimento'] ?? date('Y-m-d');
                    $anamnese = $input['anamnese'] ?? '';
                    
                    if (empty($paciente) || empty($codpro)) {
                        $response->error('Paciente e profissional são obrigatórios', 400);
                        break;
                    }
                    
                    // Salvar anamnese na tabela anamnese (baseado no PHP original)
                    $sql = "INSERT INTO anamnese (paciente, medico, dataanamnese, txtanotacoes, datahora) 
                            VALUES (?, ?, ?, ?, NOW()) 
                            ON CONFLICT (paciente, medico, dataanamnese) 
                            DO UPDATE SET txtanotacoes = ?, datahora = NOW()";
                    
                    $db->execute($sql, [$paciente, $codpro, $datamovimento, $anamnese, $anamnese]);
                    
                    $response->success(null, 'Anamnese salva com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao salvar anamnese: ' . $e->getMessage(), 500);
                }
            }
            // GET /api/atendimento/anamnese
            else if ($action === 'anamnese' && $method === 'GET') {
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    $paciente = $_GET['paciente'] ?? '';
                    $codpro = $_GET['codpro'] ?? '';
                    $datamovimento = $_GET['datamovimento'] ?? date('Y-m-d');
                    
                    if (empty($paciente) || empty($codpro)) {
                        $response->error('Paciente e profissional são obrigatórios', 400);
                        break;
                    }
                    
                    // Buscar anamnese salva
                    $sql = "SELECT txtanotacoes FROM anamnese WHERE paciente = ? AND medico = ? AND dataanamnese = ?";
                    $anamneseResult = $db->fetchOne($sql, [$paciente, $codpro, $datamovimento]);
                    
                    $anamnese = $anamneseResult ? $anamneseResult['txtanotacoes'] : '';
                    
                    $response->success(['anamnese' => $anamnese], 'Anamnese carregada com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar anamnese: ' . $e->getMessage(), 500);
                }
            }
            // POST /api/atendimento/finalizar
            else if ($action === 'finalizar' && $method === 'POST') {
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    $input = json_decode(file_get_contents('php://input'), true);
                    $nratendimento = $input['nratendimento'] ?? '';
                    
                    if (empty($nratendimento)) {
                        $response->error('Número do atendimento é obrigatório', 400);
                        break;
                    }
                    
                    // Atualizar status do atendimento para 'A' (Atendido)
                    $sql = "UPDATE agenda SET status = 'A' WHERE movimento = ?";
                    $db->execute($sql, [$nratendimento]);
                    
                    $response->success(null, 'Atendimento finalizado com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao finalizar atendimento: ' . $e->getMessage(), 500);
                }
            }
            // POST /api/atendimento/marcar-em-atendimento
            else if ($action === 'marcar-em-atendimento' && $method === 'POST') {
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    $input = json_decode(file_get_contents('php://input'), true);
                    $nratendimento = $input['nratendimento'] ?? '';
                    
                    if (empty($nratendimento)) {
                        $response->error('Número do atendimento é obrigatório', 400);
                        break;
                    }
                    
                    // Atualizar status do atendimento para 'T' (Em Atendimento)
                    $sql = "UPDATE agenda SET status = 'T' WHERE movimento = ?";
                    $db->execute($sql, [$nratendimento]);
                    
                    $response->success(null, 'Paciente marcado como em atendimento');
                } catch (Exception $e) {
                    $response->error('Erro ao marcar em atendimento: ' . $e->getMessage(), 500);
                }
            }
            // POST /api/atendimento/validar-parametros
            else if ($action === 'validar-parametros' && $method === 'POST') {
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    $input = json_decode(file_get_contents('php://input'), true);
                    $especialidade = $input['especialidade'] ?? '';
                    $profissional = $input['profissional'] ?? '';
                    $setor = $input['setor'] ?? '';
                    $consultorio = $input['consultorio'] ?? '';
                    $senha = $input['senha'] ?? '';
                    
                    if (empty($profissional) || empty($senha)) {
                        $response->error('Profissional e senha são obrigatórios', 400);
                        break;
                    }
                    
                    // Verificar dados do profissional
                    $sql = "SELECT codprofissional, profissional, senha FROM profissionais WHERE codprofissional = ?";
                    $profissionalData = $db->fetchOne($sql, [$profissional]);
                    
                    if (!$profissionalData) {
                        $response->error('Profissional não encontrado: ' . $profissional, 404);
                        break;
                    }
                    
                    // Comparar senhas (removendo espaços em branco)
                    $senhaBanco = trim($profissionalData['senha']);
                    $senhaValida = ($senhaBanco === $senha);
                    
                    if (!$senhaValida) {
                        $response->error('Senha incorreta. Profissional: ' . $profissionalData['profissional'] . ' | Senha esperada: "' . $senhaBanco . '" | Senha enviada: "' . $senha . '"', 401);
                        break;
                    }
                    
                    $response->success([
                        'profissional_id' => $profissionalData['codprofissional'],
                        'profissional_nome' => $profissionalData['profissional'],
                        'especialidade' => $especialidade,
                        'setor' => $setor,
                        'consultorio' => $consultorio
                    ], 'Parâmetros validados com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao validar parâmetros: ' . $e->getMessage(), 500);
                }
            }
            // POST /api/atendimento/validar-senha
            else if ($action === 'validar-senha' && $method === 'POST') {
                try {
                    require_once 'config/database.php';
                    $db = Database::getInstance();
                    
                    $input = json_decode(file_get_contents('php://input'), true);
                    $profissionalId = $input['profissional_id'] ?? '';
                    $senha = $input['senha'] ?? '';
                    
                    if (empty($profissionalId) || empty($senha)) {
                        $response->error('Profissional e senha são obrigatórios', 400);
                        break;
                    }
                    
                    // Debug: verificar dados do profissional
                    $sql = "SELECT codprofissional, profissional, senha FROM profissionais WHERE codprofissional = ?";
                    $profissional = $db->fetchOne($sql, [$profissionalId]);
                    
                    if (!$profissional) {
                        $response->error('Profissional não encontrado: ' . $profissionalId, 404);
                        break;
                    }
                    
                    // Comparar senhas (removendo espaços em branco)
                    $senhaBanco = trim($profissional['senha']);
                    $senhaValida = ($senhaBanco === $senha);
                    
                    if (!$senhaValida) {
                        $response->error('Senha incorreta. Profissional: ' . $profissional['profissional'] . ' | Senha esperada: "' . $senhaBanco . '" | Senha enviada: "' . $senha . '"', 401);
                        break;
                    }
                    
                    $response->success('Senha validada com sucesso', [
                        'profissional_id' => $profissional['codprofissional'],
                        'profissional_nome' => $profissional['profissional']
                    ]);
                } catch (Exception $e) {
                    $response->error('Erro ao validar senha: ' . $e->getMessage(), 500);
                }
            }
            else {
                $response->error('Ação não encontrada: ' . $action, 404);
            }
            break;
        case 'agenda':
            // POST /api/agenda/pacientes
            if ($action === 'pacientes' && $method === 'POST') {
                       try {
                           require_once 'config/database.php';
                           $db = Database::getInstance();
                           
                           $input = json_decode(file_get_contents('php://input'), true);
                           $data = $input['data'] ?? date('d/m/Y');
                           $profissional = $input['profissional'] ?? '';
                           $setor = $input['setor'] ?? '';
                           $statusFiltro = $input['status_filtro'] ?? '';
                           $ocultarLio = $input['ocultar_lio'] ?? false;
                           
                           
                           if (empty($profissional)) {
                               $response->error('Profissional é obrigatório', 400);
                               break;
                           }
                           
                           // Construir filtros baseados no tabagendconsul2.php
                           $statusfiltro = '';
                           if (!empty($statusFiltro)) {
                               // Processar múltiplos status como no PHP original
                               $statusArray = explode(',', $statusFiltro);
                               $statusPlaceholders = str_repeat('?,', count($statusArray) - 1) . '?';
                               $statusfiltro = "AND ag.status IN ($statusPlaceholders)";
                           }
                           
                           $ocultaLio = $ocultarLio ? "AND rtrim(pa.procedimento) != '998877'" : '';
                           
                           // Query principal EXATA do tabagendconsul2.php
                           $sql = "SELECT 
                               pa.datamovimento, pa.paciente, pa.tratamento AS codtratamento, 
                               pa.procedimento, pa.olho, pa.quantidade, pa.codprofissional, 
                               pa.nratendimento, pa.unidade, pa.especialidades, pa.codtabela,
                               ag.status, ag.riscor, ag.transacao, ag.fila, ag.horamarcacao, 
                               ag.horachegada, pc.nomepaciente, tt.tratamento, cv.convenio, 
                               u.nomeusuario, 
                               date_part('year', age(pc.datanascimento::timestamp with time zone)) AS idade, 
                               pc.datanascimento AS datanascimento
                               FROM procedimentosagendados pa 
                               LEFT JOIN agenda ag ON pa.nratendimento = ag.movimento
                               LEFT JOIN paciente pc ON pa.paciente = pc.codpaciente
                               LEFT JOIN tipotratamento tt ON pa.tratamento = tt.codtratamento
                               LEFT JOIN convenios cv ON pa.codtabela = cv.codconvenio
                               LEFT JOIN usuarios u ON ag.usuarioagendamento = u.codusuario
                               WHERE pa.datamovimento = ? 
                               AND pa.codprofissional = ? 
                               AND pa.unidade = ? 
                               AND pc.rede = ?
                               $statusfiltro
                               $ocultaLio
                               ORDER BY ag.horachegada";
                           
                           // Parâmetros baseados no PHP original
                           // No PHP original: unidade vem da sessão, não do parâmetro setor
                           $params = [$data, $profissional, $setor, 'REDEBIO'];
                           
                           // Adicionar parâmetros de status se existirem
                           if (!empty($statusFiltro)) {
                               $statusArray = explode(',', $statusFiltro);
                               $params = array_merge($params, $statusArray);
                           }
                           
                           $pacientes = $db->fetchAll($sql, $params);
                           
                           // Processar cada paciente para adicionar descrição do procedimento
                           foreach ($pacientes as &$paciente) {
                               // Buscar descrição do procedimento baseado no PHP original
                               if (trim($paciente['codtratamento']) == '22') {
                                   // Material médico - tabelamatmedoperadora
                                   $sqlProc = "SELECT matop_descricao FROM tabelamatmedoperadora 
                                             WHERE matop_codigo = ? AND matop_convenio = ? AND matop_unidade = ?";
                                   $proc = $db->fetchOne($sqlProc, [
                                       $paciente['procedimento'], 
                                       $paciente['codtabela'], 
                                       $paciente['unidade']
                                   ]);
                                   $paciente['descprocedimento'] = $proc ? $proc['matop_descricao'] : $paciente['procedimento'];
                               } else {
                                   // Procedimento normal - tabela procedimentos
                                   $sqlProc = "SELECT descricaoprocedimento FROM procedimentos WHERE codprocedimento = ?";
                                   $proc = $db->fetchOne($sqlProc, [$paciente['procedimento']]);
                                   $paciente['descprocedimento'] = $proc ? $proc['descricaoprocedimento'] : $paciente['procedimento'];
                               }
                               
                               // Para indicações de lente (procedimento = 998877) - como no PHP
                               if (trim($paciente['procedimento']) == '998877') {
                                   // Buscar descrição da lente na tabela mapacirurgia
                                   $sqlLente = "SELECT nomelente FROM mapacirurgia 
                                              WHERE codpaciente = ? AND olho = ? AND status <> 'A' 
                                              AND unidade = ? AND profissional = ?";
                                   $lente = $db->fetchOne($sqlLente, [
                                       $paciente['paciente'],
                                       $paciente['olho'],
                                       $setor,
                                       $profissional
                                   ]);
                                   
                                   if ($lente) {
                                       $paciente['descprocedimento'] = "INDICAR GRAU " . trim($lente['nomelente']);
                                   } else {
                                       $paciente['descprocedimento'] = "INDICAÇÃO DE GRAU DA LIO";
                                   }
                               }
                               
                               // Adicionar campos calculados como no PHP
                               $paciente['tempo_espera'] = calculateTime($paciente['horachegada'], date('H:i'));
                               $paciente['fila_info'] = $paciente['tempo_espera'] . ' | ' . $paciente['fila'];
                           }
                           
                           $response->success($pacientes, 'Pacientes carregados com sucesso');
                       } catch (Exception $e) {
                           $response->error('Erro ao carregar pacientes: ' . $e->getMessage(), 500);
                       }
                   }
                   else {
                       $response->error('Ação não encontrada: ' . $action, 404);
                   }
                   break;
                   
               case 'atendimento':
                   // POST /api/atendimento/validar-senha
                   if ($action === 'validar-senha' && $method === 'POST') {
                       try {
                           require_once 'config/database.php';
                           $db = Database::getInstance();
                           
                           $input = json_decode(file_get_contents('php://input'), true);
                           $profissionalId = $input['profissional_id'] ?? '';
                           $senha = $input['senha'] ?? '';
                           
                           if (empty($profissionalId) || empty($senha)) {
                               $response->error('Profissional e senha são obrigatórios', 400);
                               break;
                           }
                           
                           // Debug: verificar dados do profissional
                           $sql = "SELECT codprofissional, profissional, senha FROM profissionais WHERE codprofissional = ?";
                           $profissional = $db->fetchOne($sql, [$profissionalId]);
                           
                           if (!$profissional) {
                               $response->error('Profissional não encontrado: ' . $profissionalId, 404);
                               break;
                           }
                           
                           // Comparar senhas (removendo espaços em branco)
                           $senhaBanco = trim($profissional['senha']);
                           $senhaValida = ($senhaBanco === $senha);
                           
                           if (!$senhaValida) {
                               $response->error('Senha incorreta. Profissional: ' . $profissional['profissional'] . ' | Senha esperada: "' . $senhaBanco . '" | Senha enviada: "' . $senha . '"', 401);
                               break;
                           }
                           
                           $response->success(['valid' => $senhaValida], 'Validação realizada');
                       } catch (Exception $e) {
                           $response->error('Erro na validação: ' . $e->getMessage(), 500);
                       }
                   }
                   // POST /api/atendimento/iniciar
                   else if ($action === 'iniciar' && $method === 'POST') {
                       try {
                           require_once 'config/database.php';
                           $db = Database::getInstance();
                           
                           $input = json_decode(file_get_contents('php://input'), true);
                           $especialidade = $input['especialidade'] ?? '';
                           $profissional = $input['profissional'] ?? '';
                           $setor = $input['setor'] ?? '';
                           $consultorio = $input['consultorio'] ?? '';
                           $senha = $input['senha'] ?? '';
                           
                           // Debug: verificar dados do profissional
                           $sql = "SELECT codprofissional, profissional, senha FROM profissionais WHERE codprofissional = ?";
                           $profissionalData = $db->fetchOne($sql, [$profissional]);
                           
                           if (!$profissionalData) {
                               $response->error('Profissional não encontrado: ' . $profissional, 404);
                               break;
                           }
                           
                           // Comparar senhas (removendo espaços em branco)
                           $senhaBanco = trim($profissionalData['senha']);
                           $senhaValida = ($senhaBanco === $senha);
                           
                           if (!$senhaValida) {
                               $response->error('Senha incorreta. Profissional: ' . $profissionalData['profissional'] . ' | Senha esperada: "' . $senhaBanco . '" | Senha enviada: "' . $senha . '"', 401);
                               break;
                           }
                           
                           // Simular início do atendimento
                           $response->success([
                               'message' => 'Atendimento iniciado com sucesso',
                               'profissional' => $profissional,
                               'especialidade' => $especialidade,
                               'setor' => $setor,
                               'consultorio' => $consultorio
                           ], 'Atendimento iniciado');
                       } catch (Exception $e) {
                           $response->error('Erro ao iniciar atendimento: ' . $e->getMessage(), 500);
                       }
                   }
                   else {
                       $response->error('Ação não encontrada: ' . $action, 404);
                   }
                   break;
                   
               case 'bobina':
                   // Rota local: encaminha para routes/bobina.php
                   require_once 'routes/bobina.php';
                   break;
                   
               default:
                   $response->error('Endpoint não encontrado: ' . $endpoint . ' | URI: ' . $uri . ' | Segments: ' . implode('/', $segments), 404);
                   break;
    }
    
       } catch (Exception $e) {
           $response->error('Erro interno do servidor: ' . $e->getMessage(), 500);
       }

       /**
        * Calcula diferença de tempo entre dois horários
        * Baseado na função CalculateTime do tabagendconsul2.php
        */
       function calculateTime($time1, $time2) {
           if (empty($time1)) {
               return '00:00';
           }
           
           $time1 = explode(':', $time1);
           $time2 = explode(':', $time2);
           $hours1 = $time1[0];
           $hours2 = $time2[0];
           $mins1 = $time1[1];
           $mins2 = $time2[1];
           $hours = $hours2 - $hours1;
           $mins = 0;
           
           if ($hours < 0) {
               $hours = 24 + $hours;
           }
           
           if ($mins2 >= $mins1) {
               $mins = $mins2 - $mins1;
           } else {
               $mins = ($mins2 + 60) - $mins1;
               $hours--;
           }
           
           if ($mins < 9) {
               $mins = str_pad($mins, 2, '0', STR_PAD_LEFT);
           }
           
           if ($hours < 9) {
               $hours = str_pad($hours, 2, '0', STR_PAD_LEFT);
           }
           
           return $hours . ':' . $mins;
       }
       ?>
