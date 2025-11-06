<?php
/**
 * Rotas de Anamnese
 * Endpoints: /api/anamnese (POST, GET)
 */

function validarAnamnesePayload($payload) {
    $errors = [];

    $prontuario = $payload['prontuario'] ?? null;
    if (!$prontuario || !is_numeric($prontuario) || (int)$prontuario <= 0) {
        $errors[] = 'prontuario inválido';
    }

    $v = $payload['vitais'] ?? [];
    if (isset($v['pa']) && !empty($v['pa'])) {
        if (!preg_match('/^\d{2,3}\/\d{2,3}$/', trim($v['pa']))) {
            $errors[] = 'pa deve estar no formato NN/NN';
        }
    }
    foreach (['peso','altura','imc','temperatura','glicemia','hbglic','gliccap'] as $campo) {
        if (isset($v[$campo]) && $v[$campo] !== '' && !is_numeric($v[$campo])) {
            $errors[] = "$campo deve ser numérico";
        }
    }
    foreach (['pulso','freqcardiaca'] as $campoInt) {
        if (isset($v[$campoInt]) && $v[$campoInt] !== '' && !ctype_digit(strval($v[$campoInt]))) {
            $errors[] = "$campoInt deve ser inteiro";
        }
    }

    return $errors;
}

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !is_array($input)) { $input = $_POST; }
        if (!$input || !is_array($input)) { $response->error('Payload inválido', HTTP_BAD_REQUEST); }

        $errors = validarAnamnesePayload($input);
        if (!empty($errors)) { $response->validation($errors, 'Dados de anamnese inválidos'); }

        try {
            $db = Database::getInstance();
            $prontuario = (int)$input['prontuario'];
            $anotacao = isset($input['anotacao']) ? rtrim($input['anotacao']) : null;
            $vitais = $input['vitais'] ?? [];
            $medico = isset($input['medico']) && is_numeric($input['medico']) ? (int)$input['medico'] : 0;
            $usuario = isset($input['usuario']) && is_numeric($input['usuario']) ? (int)$input['usuario'] : 0;
            $rede = $input['rede'] ?? null;
            $unidade = $input['unidade'] ?? null;
            $movimento = $input['nratendimento'] ?? ($input['movimento'] ?? null);

            $data = date('Y-m-d');
            $hora = date('H:i:s');

            // Atualizar agenda para "T" (em atendimento) no dia
            $db->update('agenda', ['status' => 'T'], 'paciente = :paciente AND datamovimento = :data AND codprofissional = :medico AND status <> :stat', [
                ':paciente' => $prontuario,
                ':data' => $data,
                ':medico' => $medico,
                ':stat' => 'A'
            ]);

            // Verificar existência da anamnese no dia (paciente + data)
            $rows = $db->fetchAll('SELECT medico FROM anamnese WHERE paciente = ? AND dataanamnese = ?', [$prontuario, $data]);

            // Mapear vitais para colunas
            $dados = [
                'txtanotacoes' => $anotacao,
                'usuario' => $usuario,
                'txtpeso' => $vitais['peso'] ?? null,
                'txtaltura' => $vitais['altura'] ?? null,
                'txtsuperficiecorporea' => $vitais['imc'] ?? null,
                'txtpa' => $vitais['pa'] ?? null,
                'txtpulso' => $vitais['pulso'] ?? null,
                'txtfreqmed' => $vitais['freqcardiaca'] ?? null,
                'txttemperatura' => $vitais['temperatura'] ?? null,
                'txtglicemia' => $vitais['glicemia'] ?? null,
                'txtcircunabd' => $vitais['hbglic'] ?? null,
                'txtglicosecap' => $vitais['gliccap'] ?? null,
                'nratendimento' => $movimento,
                'unidade' => $unidade,
                'rede' => $rede
            ];

            if (empty($rows)) {
                // Inserir nova anamnese
                $id = $db->insert('anamnese', array_merge($dados, [
                    'paciente' => $prontuario,
                    'dataanamnese' => $data,
                    'medico' => $medico
                ]));

                // Atualizar hora de atendimento na agenda
                $db->update('agenda', ['horaatendimento' => $hora], 'paciente = :paciente AND datamovimento = :data AND codprofissional = :medico', [
                    ':paciente' => $prontuario,
                    ':data' => $data,
                    ':medico' => $medico
                ]);

                $response->success([
                    'id' => $id ? (string)$id : null,
                    'prontuario' => $prontuario,
                    'anotacao' => $anotacao,
                    'vitais' => $vitais
                ], 'Anamnese registrada');
            } else {
                // Atualizar registro existente: preferir do mesmo médico, senão tenta médico 000
                $medicosExistentes = array_map(function($r){ return (string)$r['medico']; }, $rows);
                $alvoMedico = in_array((string)$medico, $medicosExistentes, true) ? (string)$medico : (in_array('000', $medicosExistentes, true) ? '000' : (string)$medico);

                $db->update('anamnese', $dados, 'paciente = :paciente AND medico = :medico AND dataanamnese = :data', [
                    ':paciente' => $prontuario,
                    ':medico' => $alvoMedico,
                    ':data' => $data
                ]);

                $response->success([
                    'updated' => true,
                    'prontuario' => $prontuario,
                    'anotacao' => $anotacao,
                    'vitais' => $vitais
                ], 'Anamnese atualizada');
            }
        } catch (Exception $e) {
            $response->error('Erro ao salvar anamnese', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }
        break;

    case 'GET':
        $schemaOnly = isset($_GET['schema']) && (strtolower((string)$_GET['schema']) === '1' || strtolower((string)$_GET['schema']) === 'true');
        if ($schemaOnly) {
            $response->success([
                'request' => [ 'query' => [ 'prontuario' => 'number (opcional quando schema=1)', 'date' => 'YYYY-MM-DD (opcional)', 'schema' => '1' ] ],
                'response' => [
                    'items' => [[
                        'dataanamnese' => 'YYYY-MM-DD',
                        'profissional' => 'string',
                        'usuario' => 'number',
                        'pressaoart' => 'string|null',
                        'FC' => 'string|int|null',
                        'FR' => 'string|int|null',
                        'TAX' => 'string|int|null',
                        'peso' => 'string|int|null',
                        'altura' => 'string|int|null',
                        'embarazo' => 'string|int|null',
                        'observacoes' => 'string|null'
                    ]],
                    'count' => 'number'
                ]
            ], 'Schema de Anamnese');
            break;
        }

        $prontuario = $_GET['prontuario'] ?? null;
        if (!$prontuario || !is_numeric($prontuario) || (int)$prontuario <= 0) {
            $response->error('Prontuário inválido', HTTP_BAD_REQUEST);
        }

        try {
            $db = Database::getInstance();
            $params = [(int)$prontuario];
            $sql = 'SELECT paciente, dataanamnese, medico, txtanotacoes, txtpeso, txtaltura, txtsuperficiecorporea, txtpa, txtpulso, txtfreqmed, txttemperatura, txtglicemia, txtcircunabd, txtglicosecap FROM anamnese WHERE paciente = ?';
            $date = isset($_GET['date']) ? trim($_GET['date']) : null; // YYYY-MM-DD
            if ($date) { $sql .= ' AND dataanamnese = ?'; $params[] = $date; }
            $sql .= ' ORDER BY dataanamnese DESC LIMIT 20';

            $rows = $db->fetchAll($sql, $params);
            $items = array_map(function($r) {
                return [
                    'data' => $r['dataanamnese'],
                    'medico' => (string)$r['medico'],
                    'anotacao' => $r['txtanotacoes'],
                    'vitais' => [
                        'peso' => $r['txtpeso'],
                        'altura' => $r['txtaltura'],
                        'imc' => $r['txtsuperficiecorporea'],
                        'pa' => $r['txtpa'],
                        'pulso' => $r['txtpulso'],
                        'freqcardiaca' => $r['txtfreqmed'],
                        'temperatura' => $r['txttemperatura'],
                        'glicemia' => $r['txtglicemia'],
                        'hbglic' => $r['txtcircunabd'],
                        'gliccap' => $r['txtglicosecap']
                    ]
                ];
            }, $rows);

            $response->success([
                'prontuario' => (int)$prontuario,
                'items' => $items,
                'count' => count($items)
            ], 'Anamnese consultada');
        } catch (Exception $e) {
            $response->error('Erro ao consultar anamnese', HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }
        break;

    default:
        $response->error('Método não permitido', HTTP_BAD_REQUEST);
        break;
}
?>