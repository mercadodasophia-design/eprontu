<?php
/**
 * Rotas de Exames
 * Endpoints: /api/exames/{acao}
 * Ações suportadas: pio, biomicroscopia, retina
 */

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
    return $errors;
}

function validarRetina($payload) {
    $errors = [];
    if (empty($payload['prontuario']) || !is_numeric($payload['prontuario'])) {
        $errors[] = 'prontuario inválido';
    }
    $olho = $payload['olho'] ?? '';
    if (!in_array($olho, ['OD', 'OE', 'AO', 'D', 'E'], true)) {
        $errors[] = 'olho deve ser OD, OE ou AO';
    }
    return $errors;
}

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) {
            $input = $_POST;
        }
        if (!$input || !is_array($input)) {
            $response->error('Payload inválido', HTTP_BAD_REQUEST);
        }

        switch ($action) {
            case 'pio':
                $errors = validarPio($input);
                if (!empty($errors)) {
                    $response->validation($errors, 'Dados de PIO inválidos');
                }

                try {
                    $db = Database::getInstance();
                    $prontuario = (int)$input['prontuario'];
                    $tipo = isset($input['tipo']) ? (string)$input['tipo'] : null; // tppaquemetro
                    $olhoIn = strtoupper(trim($input['olho'] ?? ''));
                    $olho = $olhoIn === 'OD' ? 'D' : ($olhoIn === 'OE' ? 'E' : $olhoIn);
                    $pressao = isset($input['pressao']) ? floatval($input['pressao']) : null; // pio
                    $alvo = isset($input['alvo']) ? floatval($input['alvo']) : null; // pioalvo
                    $codmedicacao = isset($input['codmedicacao']) && is_numeric($input['codmedicacao']) ? (int)$input['codmedicacao'] : null;
                    $procedimento = $input['procedimento'] ?? null; // tpprocedimento
                    $observacao = $input['observacao'] ?? null; // observacao
                    $data = $input['data'] ?? date('Y-m-d');
                    $hora = $input['hora'] ?? date('H:i:s');
                    $medico = isset($input['medico']) && is_numeric($input['medico']) ? (int)$input['medico'] : 0;
                    $usuario = isset($input['usuario']) && is_numeric($input['usuario']) ? (int)$input['usuario'] : 0;
                    $nratendimento = $input['nratendimento'] ?? ($input['movimento'] ?? null);
                    $unidade = $input['unidade'] ?? null;
                    $rede = $input['rede'] ?? null;

                    // Derivar nome do medicamento em uso a partir de codmedicacao (se disponível)
                    $medicamentoUso = $input['medicamento'] ?? null;
                    if ($codmedicacao) {
                        $row = $db->fetchOne("SELECT medicacaouso FROM medicamentos WHERE codmaterial = ?", [$codmedicacao]);
                        if ($row && isset($row['medicacaouso'])) {
                            $medicamentoUso = $row['medicacaouso'];
                        }
                    }

                    // Buscar última PIO para preencher ultimapiood/ultimapiooe
                    $ultimapiood = '';
                    $ultimapiooe = '';
                    if (in_array($olho, ['D','E'], true)) {
                        $last = $db->fetchOne("SELECT pio, olho FROM examepapgton WHERE paciente = ? AND olho = ? ORDER BY dataexame DESC LIMIT 1", [$prontuario, $olho]);
                        if ($last && isset($last['pio'])) {
                            if ($olho === 'D') { $ultimapiood = $last['pio']; }
                            if ($olho === 'E') { $ultimapiooe = $last['pio']; }
                        }
                    }

                    // Inserir exame PIO
                    $id = $db->insert('examepapgton', [
                        'dataexame' => $data,
                        'horaexame' => $hora,
                        'medico' => $medico,
                        'medicamento' => $medicamentoUso,
                        'paciente' => $prontuario,
                        'pio' => $pressao,
                        'tppaquemetro' => $tipo,
                        'olho' => $olho,
                        'tpprocedimento' => $procedimento,
                        'observacao' => $observacao,
                        'usuario' => $usuario,
                        'ultimapiood' => $ultimapiood,
                        'ultimapiooe' => $ultimapiooe,
                        'codmedicacao' => $codmedicacao,
                        'pioalvo' => $alvo,
                        'nratendimento' => $nratendimento,
                        'unidade' => $unidade,
                        'rede' => $rede
                    ]);

                    $response->success([
                        'id' => $id ? (string)$id : null,
                        'prontuario' => $prontuario,
                        'tipo' => $tipo,
                        'olho' => $olho,
                        'pressao' => $pressao,
                        'alvo' => $alvo,
                        'medicamento' => $medicamentoUso,
                        'codmedicacao' => $codmedicacao,
                        'procedimento' => $procedimento,
                        'observacao' => $observacao,
                        'data' => $data,
                        'hora' => $hora,
                        'medico' => (string)$medico
                    ], 'PIO registrado');
                } catch (Exception $e) {
                    $response->error('Erro ao salvar PIO', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
                }
                break;

            case 'biomicroscopia':
                $errors = validarBiomicroscopia($input);
                if (!empty($errors)) {
                    $response->validation($errors, 'Dados de biomicroscopia inválidos');
                }

                try {
                    $db = Database::getInstance();

                    $prontuario = (int)$input['prontuario'];
                    $medico = isset($input['medico']) && is_numeric($input['medico']) ? (int)$input['medico'] : 0;
                    $usuario = isset($input['usuario']) && is_numeric($input['usuario']) ? (int)$input['usuario'] : 0;
                    $unidade = $input['unidade'] ?? null;
                    $rede = $input['rede'] ?? null;
                    $nratendimento = $input['nratendimento'] ?? ($input['movimento'] ?? null);
                    $data = $input['data'] ?? date('Y-m-d');

                    // Mapear campos conforme legado (se presentes no payload)
                    $dadosBio = [
                        // Cornea OD
                        'corneaod1' => $input['corneaod1'] ?? null,
                        'corneaod2' => $input['corneaod2'] ?? null,
                        'corneaod3' => $input['corneaod3'] ?? null,
                        'corneaod4' => $input['corneaod4'] ?? null,
                        'corneaod5' => $input['corneaod5'] ?? null,
                        'corneaod6' => $input['corneaod6'] ?? null,
                        'corneaod7' => $input['corneaod7'] ?? null,
                        'corneatempod' => $input['corneatempod'] ?? null,
                        'corneanasalod' => $input['corneanasalod'] ?? null,

                        // Cornea OE
                        'corneaoe1' => $input['corneaoe1'] ?? null,
                        'corneaoe2' => $input['corneaoe2'] ?? null,
                        'corneaoe3' => $input['corneaoe3'] ?? null,
                        'corneaoe4' => $input['corneaoe4'] ?? null,
                        'corneaoe5' => $input['corneaoe5'] ?? null,
                        'corneaoe6' => $input['corneaoe6'] ?? null,
                        'corneaoe7' => $input['corneaoe7'] ?? null,
                        'corneatempoe' => $input['corneatempoe'] ?? null,
                        'corneanasaloe' => $input['corneanasaloe'] ?? null,

                        // Cristalino
                        'cristalino1od' => $input['cristalino1od'] ?? null,
                        'cristalino2od' => $input['cristalino2od'] ?? null,
                        'cristalino3od' => $input['cristalino3od'] ?? null,
                        'cristalinoodt' => $input['cristalinoodt'] ?? null,
                        'cristalinoodf' => $input['cristalinoodf'] ?? null,
                        'cristalino1oe' => $input['cristalino1oe'] ?? null,
                        'cristalino2oe' => $input['cristalino2oe'] ?? null,
                        'cristalino3oe' => $input['cristalino3oe'] ?? null,
                        'cristalinooet' => $input['cristalinooet'] ?? null,
                        'cristalinooef' => $input['cristalinooef'] ?? null,

                        // Cápsula
                        'capsulaod' => $input['capsulaod'] ?? null,
                        'capsulaoe' => $input['capsulaoe'] ?? null,

                        // Observações
                        'obsod' => $input['obsod'] ?? null,
                        'obsoe' => $input['obsoe'] ?? null,

                        // Texto anotação (se disponível)
                        'txtanotacaobiood' => $input['txtanotacaobiood'] ?? null,
                        'txtanotacaobiooe' => $input['txtanotacaobiooe'] ?? null,
                    ];

                    // Verificar existência para upsert
                    $exists = $db->fetchOne("SELECT 1 FROM atendbiomicroscopia WHERE paciente = ? AND profissional = ? AND dataatendimento = ? AND tprefracao = '23'", [$prontuario, $medico, $data]);

                    if ($exists) {
                        // Atualizar
                        $db->update('atendbiomicroscopia', $dadosBio, "paciente = :paciente AND profissional = :profissional AND dataatendimento = :data AND tprefracao = '23'", [
                            ':paciente' => $prontuario,
                            ':profissional' => $medico,
                            ':data' => $data
                        ]);
                        // Atualizar resumo em atendrefracao (se houver observações)
                        if (isset($dadosBio['obsod']) || isset($dadosBio['obsoe'])) {
                            $db->update('atendrefracao', [
                                'obsod' => $dadosBio['obsod'] ?? null,
                                'obsoe' => $dadosBio['obsoe'] ?? null,
                            ], "paciente = :paciente AND profissional = :profissional AND dataatendimento = :data AND tprefracao = '23'", [
                                ':paciente' => $prontuario,
                                ':profissional' => $medico,
                                ':data' => $data
                            ]);
                        }
                        $response->success([
                            'prontuario' => $prontuario,
                            'data' => $data,
                            'medico' => (string)$medico,
                            'updated' => true
                        ], 'Biomicroscopia atualizada');
                    } else {
                        // Inserir
                        $id = $db->insert('atendbiomicroscopia', array_merge($dadosBio, [
                            'paciente' => $prontuario,
                            'profissional' => $medico,
                            'usuario' => $usuario,
                            'dataatendimento' => $data,
                            'tprefracao' => '23',
                            'rede' => $rede,
                            'unidade' => $unidade,
                            'nratendimento' => $nratendimento
                        ]));
                        // Inserir resumo em atendrefracao (se houver observações)
                        if (isset($dadosBio['obsod']) || isset($dadosBio['obsoe'])) {
                            $db->insert('atendrefracao', [
                                'paciente' => $prontuario,
                                'profissional' => $medico,
                                'usuario' => $usuario,
                                'dataatendimento' => $data,
                                'tprefracao' => '23',
                                'rede' => $rede,
                                'unidade' => $unidade,
                                'nratendimento' => $nratendimento,
                                'obsod' => $dadosBio['obsod'] ?? null,
                                'obsoe' => $dadosBio['obsoe'] ?? null,
                            ]);
                        }
                        $response->success([
                            'id' => $id ? (string)$id : null,
                            'prontuario' => $prontuario,
                            'data' => $data,
                            'medico' => (string)$medico
                        ], 'Biomicroscopia registrada');
                    }
                } catch (Exception $e) {
                    $response->error('Erro ao salvar biomicroscopia', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
                }
                break;

            case 'retina':
                $errors = validarRetina($input);
                if (!empty($errors)) {
                    $response->validation($errors, 'Dados de exame de retina inválidos');
                }

                try {
                    $db = Database::getInstance();

                    $prontuario = (int)$input['prontuario'];
                    $tipo = isset($input['tipo']) ? (string)$input['tipo'] : null; // 'A','U','O' ou nulo para padrão
                    $olhoIn = strtoupper(trim($input['olho'] ?? ''));
                    $olho = $olhoIn === 'OD' ? 'D' : ($olhoIn === 'OE' ? 'E' : $olhoIn);
                    $medico = isset($input['medico']) && is_numeric($input['medico']) ? (int)$input['medico'] : 0;
                    $usuario = isset($input['usuario']) && is_numeric($input['usuario']) ? (int)$input['usuario'] : 0;
                    $unidade = $input['unidade'] ?? null;
                    $rede = $input['rede'] ?? null;
                    $nratendimento = $input['nratendimento'] ?? ($input['movimento'] ?? null);
                    $data = $input['data'] ?? date('Y-m-d');

                    // Campos comuns
                    $txtconclusao = $input['txtconclusao'] ?? null;
                    $impressao = $input['impressao'] ?? null;
                    $impressaood = $input['impressaood'] ?? ($olho === 'D' ? $impressao : null);
                    $impressaooe = $input['impressaooe'] ?? ($olho === 'E' ? $impressao : null);

                    // Mapear flags/fatos conforme tipo
                    $dadosRet = [
                        'paciente' => $prontuario,
                        'dataexame' => $data,
                        'profissional' => $medico,
                        'usuario' => $usuario,
                        'olho' => $olho,
                        'tipo' => $tipo,
                        'txtconclusao' => $txtconclusao,
                        'impressao' => $impressao,
                        'impressaood' => $impressaood,
                        'impressaooe' => $impressaooe,
                        // Sinais clínicos (se presentes)
                        'me_to' => $input['me_to'] ?? null,
                        'me_fop' => $input['me_fop'] ?? null,
                        'pa_nitidos' => $input['pa_nitidos'] ?? null,
                        'va_nitidos' => $input['va_nitidos'] ?? null,
                        're_360' => $input['re_360'] ?? null,
                        'ma_brilho' => $input['ma_brilho'] ?? null,
                        'pe_semlesoes' => $input['pe_semlesoes'] ?? null,
                        // Campos OCT (se tipo 'O')
                        'octInfa' => $input['octInfa'] ?? null,
                        'octMacular' => $input['octMacular'] ?? null,
                        'octRetina' => $input['octRetina'] ?? null,
                        'octFoveal' => $input['octFoveal'] ?? null,
                        'octNeuro' => $input['octNeuro'] ?? null,
                        'octRetinianas' => $input['octRetinianas'] ?? null,
                        'octEPR' => $input['octEPR'] ?? null,
                    ];

                    // Upsert por paciente + profissional + tipo + data
                    $exists = $db->fetchOne("SELECT sequencial FROM examesfmrb WHERE paciente = ? AND profissional = ? AND dataexame = ? AND COALESCE(tipo,'') = COALESCE(?, COALESCE(tipo,''))", [$prontuario, $medico, $data, $tipo]);

                    if ($exists && isset($exists['sequencial'])) {
                        $db->update('examesfmrb', $dadosRet, "sequencial = :seq", [ ':seq' => $exists['sequencial'] ]);
                        $response->success([
                            'sequencial' => (string)$exists['sequencial'],
                            'prontuario' => $prontuario,
                            'data' => $data,
                            'tipo' => $tipo,
                            'olho' => $olho,
                            'medico' => (string)$medico,
                            'updated' => true
                        ], 'Exame de retina atualizado');
                    } else {
                        $id = $db->insert('examesfmrb', $dadosRet);
                        $response->success([
                            'id' => $id ? (string)$id : null,
                            'prontuario' => $prontuario,
                            'data' => $data,
                            'tipo' => $tipo,
                            'olho' => $olho,
                            'medico' => (string)$medico
                        ], 'Exame de retina registrado');
                    }
                } catch (Exception $e) {
                    $response->error('Erro ao salvar exame de retina', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
                }
                break;

            default:
                $response->error('Ação de exames não encontrada', HTTP_NOT_FOUND);
                break;
        }
        break;

    case 'GET':
        $schemaOnly = isset($_GET['schema']) && (strtolower((string)$_GET['schema']) === '1' || strtolower((string)$_GET['schema']) === 'true');
        if ($schemaOnly) {
            switch ($action) {
                case 'pio':
                    $response->success([
                        'request' => [ 'query' => [ 'prontuario' => 'number (opcional quando schema=1)', 'rede' => 'string (opcional)', 'schema' => '1' ] ],
                        'response' => [
                            'items' => [[
                                'sequencial' => 'number',
                                'dataexame' => 'YYYY-MM-DD',
                                'horaexame' => 'HH:mm:ss',
                                'medico' => 'string',
                                'profissional' => 'string (nome)',
                                'paciente' => 'number',
                                'pio' => 'number',
                                'tppaquemetro' => 'string|null',
                                'olho' => 'D|E|AO',
                                'tpprocedimento' => 'string|null',
                                'observacao' => 'string|null',
                                'usuario' => 'number',
                                'ultimapiood' => 'number|string|null',
                                'ultimapiooe' => 'number|string|null',
                                'pioalvo' => 'number|null',
                                'intervencaocirurgica' => 'string|null'
                            ]],
                            'count' => 'number'
                        ]
                    ], 'Schema de PIO');
                    break;
                case 'biomicroscopia':
                    $response->success([
                        'request' => [ 'query' => [ 'prontuario' => 'number (opcional quando schema=1)', 'date' => 'YYYY-MM-DD (opcional)', 'schema' => '1' ] ],
                        'response' => [
                            'items' => [[
                                'dataatendimento' => 'YYYY-MM-DD',
                                'profissional' => 'string',
                                'tprefracao' => 'string (sempre 23)',
                                'corneaod1' => 'string|null', 'corneaod2' => 'string|null', 'corneaod3' => 'string|null', 'corneaod4' => 'string|null', 'corneaod5' => 'string|null', 'corneaod6' => 'string|null', 'corneaod7' => 'string|null',
                                'corneatempod' => 'string|null', 'corneanasalod' => 'string|null',
                                'corneaoe1' => 'string|null', 'corneaoe2' => 'string|null', 'corneaoe3' => 'string|null', 'corneaoe4' => 'string|null', 'corneaoe5' => 'string|null', 'corneaoe6' => 'string|null', 'corneaoe7' => 'string|null',
                                'corneatempoe' => 'string|null', 'corneanasaloe' => 'string|null',
                                'cristalino1od' => 'string|null', 'cristalino2od' => 'string|null', 'cristalino3od' => 'string|null', 'cristalinoodt' => 'string|null', 'cristalinoodf' => 'string|null',
                                'cristalino1oe' => 'string|null', 'cristalino2oe' => 'string|null', 'cristalino3oe' => 'string|null', 'cristalinooet' => 'string|null', 'cristalinooef' => 'string|null',
                                'capsulaod' => 'string|null', 'capsulaoe' => 'string|null',
                                'obsod' => 'string|null', 'obsoe' => 'string|null',
                                'txtanotacaobiood' => 'string|null', 'txtanotacaobiooe' => 'string|null'
                            ]],
                            'count' => 'number'
                        ]
                    ], 'Schema de Biomicroscopia');
                    break;
                case 'retina':
                    $response->success([
                        'request' => [ 'query' => [ 'prontuario' => 'number (opcional quando schema=1)', 'tipo' => 'A|U|O (opcional)', 'date' => 'YYYY-MM-DD (opcional)', 'schema' => '1' ] ],
                        'response' => [
                            'items' => [[
                                'sequencial' => 'number',
                                'paciente' => 'number',
                                'dataexame' => 'YYYY-MM-DD',
                                'profissional' => 'string',
                                'usuario' => 'number',
                                'olho' => 'D|E|AO',
                                'tipo' => 'A|U|O|null',
                                'txtconclusao' => 'string|null',
                                'impressao' => 'string|null',
                                'impressaood' => 'string|null',
                                'impressaooe' => 'string|null',
                                'me_to' => 'bool|int|null',
                                'me_fop' => 'bool|int|null',
                                'pa_nitidos' => 'bool|int|null',
                                'va_nitidos' => 'bool|int|null',
                                're_360' => 'bool|int|null',
                                'ma_brilho' => 'bool|int|null',
                                'pe_semlesoes' => 'bool|int|null',
                                'octInfa' => 'string|int|null',
                                'octMacular' => 'string|int|null',
                                'octRetina' => 'string|int|null',
                                'octFoveal' => 'string|int|null',
                                'octNeuro' => 'string|int|null',
                                'octRetinianas' => 'string|int|null',
                                'octEPR' => 'string|int|null'
                            ]],
                            'count' => 'number'
                        ]
                    ], 'Schema de Retina');
                    break;
                default:
                    $response->error('Ação de exames não encontrada', HTTP_NOT_FOUND);
                    break;
            }
            break;
        }

        $prontuario = $_GET['prontuario'] ?? null;
        if (!$prontuario || !is_numeric($prontuario) || (int)$prontuario <= 0) {
            $response->error('Prontuário inválido', HTTP_BAD_REQUEST);
        }

        switch ($action) {
            case 'pio':
                try {
                    $db = Database::getInstance();
                    $params = [(int)$prontuario];
                    $sql = "SELECT e.sequencial, e.dataexame, e.horaexame, e.medico, e.medicamento, e.paciente, e.pio, e.tppaquemetro, e.olho, e.tpprocedimento, e.observacao, e.usuario, e.ultimapiood, e.ultimapiooe, e.pioalvo, e.intervencaocirurgica, p.profissional FROM examepapgton e LEFT JOIN profissionais p ON p.codprofissional = e.medico WHERE e.paciente = ?";

                    $rede = isset($_GET['rede']) ? trim($_GET['rede']) : null;
                    if ($rede) { $sql .= " AND e.rede = ?"; $params[] = $rede; }

                    $sql .= " ORDER BY e.dataexame DESC";
                    $items = $db->fetchAll($sql, $params);

                    $response->success([
                        'prontuario' => (int)$prontuario,
                        'items' => $items,
                        'count' => count($items)
                    ], 'PIO consultado');
                } catch (Exception $e) {
                    $response->error('Erro ao consultar PIO', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
                }
                break;

            case 'biomicroscopia':
                try {
                    $db = Database::getInstance();
                    $params = [(int)$prontuario];
                    $sql = "SELECT b.*, r.obsod, r.obsoe, p.profissional FROM atendbiomicroscopia b LEFT JOIN atendrefracao r ON r.paciente=b.paciente AND r.profissional=b.profissional AND r.dataatendimento=b.dataatendimento AND r.tprefracao='23' LEFT JOIN profissionais p ON p.codprofissional=b.profissional WHERE b.paciente = ?";

                    $date = isset($_GET['date']) ? trim($_GET['date']) : null;
                    if ($date) { $sql .= " AND b.dataatendimento = ?"; $params[] = $date; }

                    $sql .= " ORDER BY b.dataatendimento DESC";
                    $items = $db->fetchAll($sql, $params);

                    $response->success([
                        'prontuario' => (int)$prontuario,
                        'items' => $items,
                        'count' => count($items)
                    ], 'Biomicroscopia consultada');
                } catch (Exception $e) {
                    $response->error('Erro ao consultar biomicroscopia', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
                }
                break;
            case 'retina':
                try {
                    $db = Database::getInstance();
                    $params = [(int)$prontuario];
                    $sql = "SELECT e.*, p.profissional FROM examesfmrb e LEFT JOIN profissionais p ON p.codprofissional = e.profissional WHERE e.paciente = ?";

                    $tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : null;
                    if ($tipo) { $sql .= " AND e.tipo = ?"; $params[] = $tipo; }

                    $date = isset($_GET['date']) ? trim($_GET['date']) : null;
                    if ($date) { $sql .= " AND e.dataexame = ?"; $params[] = $date; }

                    $sql .= " ORDER BY e.dataexame DESC";
                    $items = $db->fetchAll($sql, $params);

                    $response->success([
                        'prontuario' => (int)$prontuario,
                        'items' => $items,
                        'count' => count($items)
                    ], 'Exame de retina consultado');
                } catch (Exception $e) {
                    $response->error('Erro ao consultar exame de retina', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
                }
                break;

            default:
                $response->error('Ação de exames não encontrada', HTTP_NOT_FOUND);
                break;
        }
        break;

    default:
        $response->error('Método não permitido', HTTP_BAD_REQUEST);
        break;
}
?>