<?php
// Rota Campanhas - Interações: CRUD de interações de mailings

require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../config/server_config.php';
require_once __DIR__ . '/../config/database.php';

$response = new Response();

// Usa variáveis compartilhadas do index.php
// Para /api/campanhas/interacoes/{acao}, a ação está em $segments[2]
if (!isset($method) || !isset($segments)) {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $segments = explode('/', trim($uri, '/'));
    if (isset($segments[0]) && $segments[0] === 'e-prontu') array_shift($segments);
    if (isset($segments[0]) && $segments[0] === 'api') array_shift($segments);
    // Para /api/campanhas/interacoes/{acao}, a ação está em $segments[2]
    $action = $segments[2] ?? '';
} else {
    // Se $segments já existe, pegar ação do índice correto
    $action = $segments[2] ?? '';
}

$action = $action ?? '';

// Cria a tabela de interações caso não exista (PostgreSQL)
function ensureInteracoesTable(PDO $pdo) {
    try {
        $exists = $pdo->query("SELECT to_regclass('public.mailing_interacoes')")->fetchColumn();
        if (!$exists) {
            $sql = "CREATE TABLE IF NOT EXISTS public.mailing_interacoes (
                id SERIAL PRIMARY KEY,
                mailing_id INTEGER NOT NULL,
                campanha_id TEXT,
                paciente_id INTEGER NOT NULL,
                canal_origem TEXT,
                anotacoes TEXT,
                clips JSONB DEFAULT '[]'::jsonb,
                usuario_id TEXT,
                usuario_nome TEXT,
                data_interacao TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
                enviar_crm BOOLEAN DEFAULT TRUE,
                resultado_contato TEXT,
                tipos_interacao_complementar JSONB DEFAULT '[]'::jsonb,
                proxima_acao JSONB,
                anexos JSONB DEFAULT '[]'::jsonb,
                created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW(),
                updated_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NOW()
            )";
            $pdo->exec($sql);
            
            // Criar índices
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mailing_interacoes_mailing_id ON public.mailing_interacoes(mailing_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mailing_interacoes_paciente_id ON public.mailing_interacoes(paciente_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mailing_interacoes_campanha_id ON public.mailing_interacoes(campanha_id)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_mailing_interacoes_data_interacao ON public.mailing_interacoes(data_interacao)");
        }
    } catch (Exception $e) {
        // Se não conseguir criar/verificar, seguir em frente
    }
}

try {
    switch ($action) {
        case 'salvar':
            // Salva uma nova interação
            if ($method !== 'POST') {
                $response->error('Método não suportado. Use POST.', HTTP_METHOD_NOT_ALLOWED);
            }

            $raw = file_get_contents('php://input');
            $input = json_decode($raw, true);
            if (!$input || !is_array($input)) {
                $response->validation(['body' => 'JSON inválido ou vazio'], 'Dados inválidos');
            }

            // Validações básicas
            $errors = [];
            if (empty($input['mailing_id'])) $errors['mailing_id'] = 'mailing_id é obrigatório';
            if (empty($input['paciente_id'])) $errors['paciente_id'] = 'paciente_id é obrigatório';
            if (!empty($errors)) {
                $response->validation($errors, 'Campos obrigatórios ausentes');
            }

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureInteracoesTable($pdo);

            // Preparar dados
            $mailingId = (int)$input['mailing_id'];
            $campanhaId = isset($input['campanha_id']) ? trim((string)$input['campanha_id']) : null;
            $pacienteId = (int)$input['paciente_id'];
            $canalOrigem = isset($input['canal_origem']) ? trim((string)$input['canal_origem']) : null;
            $anotacoes = isset($input['anotacoes']) ? trim((string)$input['anotacoes']) : null;
            $clips = isset($input['clips']) && is_array($input['clips']) ? $input['clips'] : [];
            $usuarioId = isset($input['usuario_id']) ? trim((string)$input['usuario_id']) : null;
            $usuarioNome = isset($input['usuario_nome']) ? trim((string)$input['usuario_nome']) : null;
            $dataInteracao = isset($input['data_interacao']) ? $input['data_interacao'] : date('Y-m-d H:i:s');
            $enviarCrm = isset($input['enviar_crm']) ? (bool)$input['enviar_crm'] : true;
            
            // Novos campos
            $resultadoContato = isset($input['resultado_contato']) ? $input['resultado_contato'] : null;
            if (is_array($resultadoContato) && isset($resultadoContato['name'])) {
                $resultadoContato = $resultadoContato['name'];
            } elseif (is_string($resultadoContato)) {
                // Já está como string
            } else {
                $resultadoContato = null;
            }
            
            $tiposInteracao = isset($input['tipos_interacao_complementar']) && is_array($input['tipos_interacao_complementar']) 
                ? $input['tipos_interacao_complementar'] 
                : [];
            $proximaAcao = isset($input['proxima_acao']) && is_array($input['proxima_acao']) ? $input['proxima_acao'] : null;
            $anexos = isset($input['anexos']) && is_array($input['anexos']) ? $input['anexos'] : [];

            // Converter data se necessário
            if (is_string($dataInteracao)) {
                try {
                    $dt = new DateTime($dataInteracao);
                    $dataInteracao = $dt->format('Y-m-d H:i:s');
                } catch (Exception $e) {
                    $dataInteracao = date('Y-m-d H:i:s');
                }
            }

            $sql = "INSERT INTO public.mailing_interacoes 
                    (mailing_id, campanha_id, paciente_id, canal_origem, anotacoes, clips, usuario_id, usuario_nome, 
                     data_interacao, enviar_crm, resultado_contato, tipos_interacao_complementar, proxima_acao, anexos)
                    VALUES 
                    (:mailing_id, :campanha_id, :paciente_id, :canal_origem, :anotacoes, :clips, :usuario_id, :usuario_nome,
                     :data_interacao, :enviar_crm, :resultado_contato, :tipos_interacao_complementar, :proxima_acao, :anexos)
                    RETURNING id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':mailing_id' => $mailingId,
                ':campanha_id' => $campanhaId,
                ':paciente_id' => $pacienteId,
                ':canal_origem' => $canalOrigem,
                ':anotacoes' => $anotacoes,
                ':clips' => json_encode($clips, JSON_UNESCAPED_UNICODE),
                ':usuario_id' => $usuarioId,
                ':usuario_nome' => $usuarioNome,
                ':data_interacao' => $dataInteracao,
                ':enviar_crm' => $enviarCrm,
                ':resultado_contato' => $resultadoContato,
                ':tipos_interacao_complementar' => json_encode($tiposInteracao, JSON_UNESCAPED_UNICODE),
                ':proxima_acao' => $proximaAcao ? json_encode($proximaAcao, JSON_UNESCAPED_UNICODE) : null,
                ':anexos' => json_encode($anexos, JSON_UNESCAPED_UNICODE),
            ]);

            $newId = $stmt->fetchColumn();

            // Buscar registro completo
            $stmt2 = $pdo->prepare("SELECT * FROM public.mailing_interacoes WHERE id = :id");
            $stmt2->execute([':id' => $newId]);
            $row = $stmt2->fetch(PDO::FETCH_ASSOC);

            $interacao = [
                'id' => (int)$row['id'],
                'mailing_id' => (int)$row['mailing_id'],
                'campanha_id' => $row['campanha_id'],
                'paciente_id' => (int)$row['paciente_id'],
                'canal_origem' => $row['canal_origem'],
                'anotacoes' => $row['anotacoes'],
                'clips' => json_decode($row['clips'], true) ?? [],
                'usuario_id' => $row['usuario_id'],
                'usuario_nome' => $row['usuario_nome'],
                'data_interacao' => $row['data_interacao'],
                'enviar_crm' => (bool)$row['enviar_crm'],
                'resultado_contato' => $row['resultado_contato'],
                'tipos_interacao_complementar' => json_decode($row['tipos_interacao_complementar'], true) ?? [],
                'proxima_acao' => $row['proxima_acao'] ? json_decode($row['proxima_acao'], true) : null,
                'anexos' => json_decode($row['anexos'], true) ?? [],
            ];

            $response->success(['interacao' => $interacao], 'Interação salva com sucesso');
            break;

        case 'historico':
            // Histórico de interações por mailing_id
            if ($method !== 'GET') {
                $response->error('Método não suportado. Use GET.', HTTP_METHOD_NOT_ALLOWED);
            }

            $mailingId = isset($_GET['mailing_id']) ? (int)$_GET['mailing_id'] : 0;
            if ($mailingId <= 0) {
                $response->validation(['mailing_id' => 'mailing_id é obrigatório e deve ser maior que zero'], 'Dados inválidos');
            }

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureInteracoesTable($pdo);

            $stmt = $pdo->prepare("SELECT * FROM public.mailing_interacoes WHERE mailing_id = :mailing_id ORDER BY data_interacao DESC");
            $stmt->execute([':mailing_id' => $mailingId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $interacoes = [];
            foreach ($rows as $row) {
                $interacoes[] = [
                    'id' => (int)$row['id'],
                    'mailing_id' => (int)$row['mailing_id'],
                    'campanha_id' => $row['campanha_id'],
                    'paciente_id' => (int)$row['paciente_id'],
                    'canal_origem' => $row['canal_origem'],
                    'anotacoes' => $row['anotacoes'],
                    'clips' => json_decode($row['clips'], true) ?? [],
                    'usuario_id' => $row['usuario_id'],
                    'usuario_nome' => $row['usuario_nome'],
                    'data_interacao' => $row['data_interacao'],
                    'enviar_crm' => (bool)$row['enviar_crm'],
                    'resultado_contato' => $row['resultado_contato'],
                    'tipos_interacao_complementar' => json_decode($row['tipos_interacao_complementar'], true) ?? [],
                    'proxima_acao' => $row['proxima_acao'] ? json_decode($row['proxima_acao'], true) : null,
                    'anexos' => json_decode($row['anexos'], true) ?? [],
                ];
            }

            $response->success($interacoes, 'Histórico carregado com sucesso');
            break;

        case 'paciente':
            // Histórico de interações por paciente_id (com filtros opcionais)
            if ($method !== 'GET') {
                $response->error('Método não suportado. Use GET.', HTTP_METHOD_NOT_ALLOWED);
            }

            $pacienteId = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : 0;
            if ($pacienteId <= 0) {
                $response->validation(['paciente_id' => 'paciente_id é obrigatório e deve ser maior que zero'], 'Dados inválidos');
            }

            $campanhaId = isset($_GET['campanha_id']) ? trim((string)$_GET['campanha_id']) : null;
            $canal = isset($_GET['canal']) ? trim((string)$_GET['canal']) : null;
            $dataInicio = isset($_GET['data_inicio']) ? trim((string)$_GET['data_inicio']) : null;
            $dataFim = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : null;

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureInteracoesTable($pdo);

            $sql = "SELECT * FROM public.mailing_interacoes WHERE paciente_id = :paciente_id";
            $params = [':paciente_id' => $pacienteId];

            if ($campanhaId !== null && $campanhaId !== '') {
                $sql .= " AND campanha_id = :campanha_id";
                $params[':campanha_id'] = $campanhaId;
            }
            if ($canal !== null && $canal !== '') {
                $sql .= " AND canal_origem = :canal";
                $params[':canal'] = $canal;
            }
            if ($dataInicio !== null && $dataInicio !== '') {
                $sql .= " AND data_interacao >= :data_inicio";
                $params[':data_inicio'] = $dataInicio;
            }
            if ($dataFim !== null && $dataFim !== '') {
                $sql .= " AND data_interacao <= :data_fim";
                $params[':data_fim'] = $dataFim;
            }

            $sql .= " ORDER BY data_interacao DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $interacoes = [];
            foreach ($rows as $row) {
                $interacoes[] = [
                    'id' => (int)$row['id'],
                    'mailing_id' => (int)$row['mailing_id'],
                    'campanha_id' => $row['campanha_id'],
                    'paciente_id' => (int)$row['paciente_id'],
                    'canal_origem' => $row['canal_origem'],
                    'anotacoes' => $row['anotacoes'],
                    'clips' => json_decode($row['clips'], true) ?? [],
                    'usuario_id' => $row['usuario_id'],
                    'usuario_nome' => $row['usuario_nome'],
                    'data_interacao' => $row['data_interacao'],
                    'enviar_crm' => (bool)$row['enviar_crm'],
                    'resultado_contato' => $row['resultado_contato'],
                    'tipos_interacao_complementar' => json_decode($row['tipos_interacao_complementar'], true) ?? [],
                    'proxima_acao' => $row['proxima_acao'] ? json_decode($row['proxima_acao'], true) : null,
                    'anexos' => json_decode($row['anexos'], true) ?? [],
                ];
            }

            $response->success($interacoes, 'Histórico do paciente carregado com sucesso');
            break;

        case 'estatisticas':
            // Estatísticas de interações por campanha
            if ($method !== 'GET') {
                $response->error('Método não suportado. Use GET.', HTTP_METHOD_NOT_ALLOWED);
            }

            $campanhaId = isset($_GET['campanha_id']) ? trim((string)$_GET['campanha_id']) : '';
            if ($campanhaId === '') {
                $response->validation(['campanha_id' => 'campanha_id é obrigatório'], 'Dados inválidos');
            }

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureInteracoesTable($pdo);

            // Total de interações
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM public.mailing_interacoes WHERE campanha_id = :campanha_id");
            $stmt->execute([':campanha_id' => $campanhaId]);
            $totalInteracoes = (int)$stmt->fetchColumn();

            // Contatos únicos processados
            $stmt = $pdo->prepare("SELECT COUNT(DISTINCT paciente_id) FROM public.mailing_interacoes WHERE campanha_id = :campanha_id");
            $stmt->execute([':campanha_id' => $campanhaId]);
            $contatosProcessados = (int)$stmt->fetchColumn();

            // Total de contatos na campanha (precisa buscar da tabela campanhas)
            $totalContatos = 0;
            try {
                $stmt = $pdo->prepare("SELECT leads_count FROM public.campanhas WHERE id = :id");
                $stmt->execute([':id' => $campanhaId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $totalContatos = (int)$row['leads_count'];
                }
            } catch (Exception $e) {
                // Ignora erro
            }

            // Taxa de contato
            $taxaContato = $totalContatos > 0 ? ($contatosProcessados / $totalContatos) : 0.0;

            // Interações por canal
            $stmt = $pdo->prepare("SELECT canal_origem, COUNT(*) as total FROM public.mailing_interacoes WHERE campanha_id = :campanha_id AND canal_origem IS NOT NULL GROUP BY canal_origem");
            $stmt->execute([':campanha_id' => $campanhaId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $interacoesPorCanal = [];
            foreach ($rows as $row) {
                $interacoesPorCanal[$row['canal_origem']] = (int)$row['total'];
            }

            // Resultados de contato
            $stmt = $pdo->prepare("SELECT resultado_contato, COUNT(*) as total FROM public.mailing_interacoes WHERE campanha_id = :campanha_id AND resultado_contato IS NOT NULL GROUP BY resultado_contato");
            $stmt->execute([':campanha_id' => $campanhaId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $resultadosContato = [];
            foreach ($rows as $row) {
                $resultadosContato[$row['resultado_contato']] = (int)$row['total'];
            }

            $estatisticas = [
                'total_interacoes' => $totalInteracoes,
                'contatos_processados' => $contatosProcessados,
                'total_contatos' => $totalContatos,
                'taxa_contato' => round($taxaContato, 2),
                'interacoes_por_canal' => $interacoesPorCanal,
                'resultados_contato' => $resultadosContato,
            ];

            $response->success($estatisticas, 'Estatísticas carregadas com sucesso');
            break;

        case 'campanha':
            // Interações por campanha (com filtros opcionais)
            if ($method !== 'GET') {
                $response->error('Método não suportado. Use GET.', HTTP_METHOD_NOT_ALLOWED);
            }

            $campanhaId = isset($_GET['campanha_id']) ? trim((string)$_GET['campanha_id']) : '';
            if ($campanhaId === '') {
                $response->validation(['campanha_id' => 'campanha_id é obrigatório'], 'Dados inválidos');
            }

            $canal = isset($_GET['canal']) ? trim((string)$_GET['canal']) : null;
            $dataInicio = isset($_GET['data_inicio']) ? trim((string)$_GET['data_inicio']) : null;
            $dataFim = isset($_GET['data_fim']) ? trim((string)$_GET['data_fim']) : null;

            $db = Database::getInstance();
            $pdo = $db->getConnection();
            ensureInteracoesTable($pdo);

            $sql = "SELECT * FROM public.mailing_interacoes WHERE campanha_id = :campanha_id";
            $params = [':campanha_id' => $campanhaId];

            if ($canal !== null && $canal !== '') {
                $sql .= " AND canal_origem = :canal";
                $params[':canal'] = $canal;
            }
            if ($dataInicio !== null && $dataInicio !== '') {
                $sql .= " AND data_interacao >= :data_inicio";
                $params[':data_inicio'] = $dataInicio;
            }
            if ($dataFim !== null && $dataFim !== '') {
                $sql .= " AND data_interacao <= :data_fim";
                $params[':data_fim'] = $dataFim;
            }

            $sql .= " ORDER BY data_interacao DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $interacoes = [];
            foreach ($rows as $row) {
                $interacoes[] = [
                    'id' => (int)$row['id'],
                    'mailing_id' => (int)$row['mailing_id'],
                    'campanha_id' => $row['campanha_id'],
                    'paciente_id' => (int)$row['paciente_id'],
                    'canal_origem' => $row['canal_origem'],
                    'anotacoes' => $row['anotacoes'],
                    'clips' => json_decode($row['clips'], true) ?? [],
                    'usuario_id' => $row['usuario_id'],
                    'usuario_nome' => $row['usuario_nome'],
                    'data_interacao' => $row['data_interacao'],
                    'enviar_crm' => (bool)$row['enviar_crm'],
                    'resultado_contato' => $row['resultado_contato'],
                    'tipos_interacao_complementar' => json_decode($row['tipos_interacao_complementar'], true) ?? [],
                    'proxima_acao' => $row['proxima_acao'] ? json_decode($row['proxima_acao'], true) : null,
                    'anexos' => json_decode($row['anexos'], true) ?? [],
                ];
            }

            $response->success($interacoes, 'Interações da campanha carregadas com sucesso');
            break;

        default:
            $response->error('Ação de interações não suportada: ' . $action, 404);
    }
} catch (Exception $e) {
    $response->error('Erro na rota interações: ' . $e->getMessage(), 500);
}
?>

