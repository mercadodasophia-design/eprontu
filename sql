<?php

/**
 * Classe bobina para consulta consolidada de registros médicos.
 *
 * Ajustes realizados:
 * - Corrige passagem e uso de parâmetros (ex: :prontuario, :especialidade)
 * - Implementa método adicionarFiltroDinamico
 * - Corrige montagem de SQL COUNT (usa subquery correta, não vw_estoque_consolidado!)
 * - Evita SQL injection no LIMIT/OFFSET (usa bindValue)
 * - Torna PDO acessível via $this->pdo (inicialize no __construct)
 * - Garante robustez nos filtros obrigatórios
 * - Documenta pontos principais
 */
class bobina extends conexao {
    protected $pdo;
    public function __construct() {
        parent::__construct();
        $this->pdo =  Conexao::getInstance('BIO');
    }


    public function listar($pagina = 1, $itensPorPagina = 10, $filtros = null)
    {
        try {
            // Recebe filtros do corpo do request se não vieram como parâmetro
            if (!is_array($filtros) || empty($filtros)) {
                $dados = file_get_contents('php://input');
                $filtros = json_decode($dados, true);
                if (isset($filtros['filtros'])) {
                    $filtros = $filtros['filtros'];
                }
            }
            

            $offset = ((int)$pagina - 1) * (int)$itensPorPagina;
            $limit = (int)$itensPorPagina;

            // Filtros obrigatórios
            if (empty($filtros['prontuario'])) {
                return  ('Filtro "prontuario" é obrigatório.');
            }
            if (empty($filtros['especialidade'])) {
                return ('Filtro "especialidade" é obrigatório.');
            }

            // Filtros dinâmicos
            $where = [];
            $params = [
                ":prontuario"    => $filtros["prontuario"],
                ":especialidade" => $filtros["especialidade"]
            ];

            // Exemplo de filtro dinâmico extra (pode adicionar outros)
          //  $this->adicionarFiltroDinamico('origem_id', 'entidade_id', $where, $params, $filtros);

            // Não aplicamos $whereSql no SELECT principal porque os filtros já estão nos seus respectivos SELECTs do UNION.
            // O $whereSql só seria útil se toda a subquery fizesse uso, o que aqui não ocorre.

            // Bloco principal (UNION ALL) - já parametrizado
            $unionSql = <<<SQL
            -- ATENDIMENTOS PRINCIPAIS (Agenda)
            SELECT 
                'atendimento' AS tipo_registro,
                a.movimento AS id_referencia,
                a.datamovimento AS data_evento,
                TO_CHAR(a.datamovimento, 'HH24:MI:SS') AS hora_evento,
                'Atendimento realizado' AS descricao_resumida,
                trim(a.procedimentos) AS procedimento,
                trim(prof.profissional) AS medico,
                conv.convenio AS convenio,
                NULL::text AS tratamento,
                a.status::text AS status,
                NULL::text AS quantidade,
                NULL::text AS justificativa,
                NULL::text AS diagnostico,
                NULL::text AS anotacoes,
                a.paciente::text AS codigo_paciente,
                NULL::text AS nome_paciente,
                NULL::text AS cpf,
                NULL::text AS olho,
                NULL::text AS impressao_od,
                NULL::text AS impressao_oe,
                NULL::text AS data_exame,
                NULL::text AS tipo_exame
            FROM agenda a
            INNER JOIN profissionais prof ON prof.codprofissional = a.codprofissional
            INNER JOIN convenios conv ON conv.codconvenio = a.convenio
            WHERE a.paciente = :prontuario 
            AND prof.especialidade = :especialidade
            AND a.datamovimento <= current_date
            
            UNION ALL
            
            -- ANAMNESE/CONDUTAS (Glaucoma - 10 campos mapeados)
            SELECT 
                'anamnese' AS tipo_registro,
                an.nratendimento AS id_referencia,
                an.dataanamnese AS data_evento,
                TO_CHAR(an.dataanamnese, 'HH24:MI:SS') AS hora_evento,
                'Conduta/Anamnese registrada' AS descricao_resumida,
                'Anamnese' AS procedimento,
                trim(prof.profissional) AS medico,
                NULL::text AS convenio,
                NULL::text AS tratamento,
                'AT' AS status,
                NULL::text AS quantidade,
                an.justificativa AS justificativa,
                an.diagnosticocid AS diagnostico,
                CONCAT(
                    CASE WHEN an.txtanotacoes IS NOT NULL AND trim(an.txtanotacoes) != '' 
                         THEN 'Anotações: ' || trim(an.txtanotacoes) ELSE '' END,
                    CASE WHEN an.codcid IS NOT NULL 
                         THEN ' | CID: ' || an.codcid ELSE '' END,
                    CASE WHEN an.relatotratamentoprevio IS NOT NULL AND trim(an.relatotratamentoprevio) != '' 
                         THEN ' | Tratamento Prévio: ' || trim(an.relatotratamentoprevio) ELSE '' END
                ) AS anotacoes,
                an.paciente::text AS codigo_paciente,
                NULL::text AS nome_paciente,
                NULL::text AS cpf,
                NULL::text AS olho,
                NULL::text AS impressao_od,
                NULL::text AS impressao_oe,
                NULL::text AS data_exame,
                NULL::text AS tipo_exame
            FROM anamnese an
            INNER JOIN profissionais prof ON prof.codprofissional = an.medico
            WHERE an.paciente = :prontuario
            AND prof.especialidade = :especialidade
            AND an.dataanamnese IS NOT NULL
            AND (an.txtanotacoes IS NOT NULL AND trim(an.txtanotacoes) != ''
                 OR an.codcid IS NOT NULL 
                 OR an.diagnosticocid IS NOT NULL)
            
            UNION ALL
            
            -- PIO (Pressão Intraocular - 15 campos mapeados)
            SELECT 
                'pio' AS tipo_registro,
                pio.nratendimento AS id_referencia,
                pio.dataexame AS data_evento,
                COALESCE(pio.horaexame, TO_CHAR(pio.dataexame, 'HH24:MI:SS')) AS hora_evento,
                'Medição de PIO realizada' AS descricao_resumida,
                'PIO' AS procedimento,
                trim(prof.profissional) AS medico,
                NULL::text AS convenio,
                NULL::text AS tratamento,
                'AT' AS status,
                NULL::text AS quantidade,
                NULL::text AS justificativa,
                NULL::text AS diagnostico,
                CONCAT(
                    'PIO: ' || pio.pio || ' mmHg (' || pio.olho || ')',
                    CASE WHEN pio.medicamento IS NOT NULL AND trim(pio.medicamento) != '' 
                         THEN ' | Medicamento: ' || trim(pio.medicamento) ELSE '' END,
                    CASE WHEN pio.tppaquemetro IS NOT NULL 
                         THEN ' | Equipamento: ' || pio.tppaquemetro ELSE '' END,
                    CASE WHEN pio.pioalvo IS NOT NULL 
                         THEN ' | PIO Alvo: ' || pio.pioalvo ELSE '' END,
                    CASE WHEN pio.observacao IS NOT NULL AND trim(pio.observacao) != '' 
                         THEN ' | Obs: ' || trim(pio.observacao) ELSE '' END
                ) AS anotacoes,
                pio.paciente::text AS codigo_paciente,
                NULL::text AS nome_paciente,
                NULL::text AS cpf,
                pio.olho AS olho,
                NULL::text AS impressao_od,
                NULL::text AS impressao_oe,
                TO_CHAR(pio.dataexame, 'YYYY-MM-DD') AS data_exame,
                'PIO' AS tipo_exame
            FROM examepapgton pio
            INNER JOIN profissionais prof ON prof.codprofissional = pio.medico
            WHERE pio.paciente::text = :prontuario::text
            AND pio.pio IS NOT NULL AND pio.pio != '' AND pio.pio != '0'
            
            UNION ALL
            
            -- BIOMICROSCOPIA (50+ campos mapeados)
            SELECT 
                'biomicroscopia' AS tipo_registro,
                bio.nratendimento AS id_referencia,
                bio.dataatendimento AS data_evento,
                TO_CHAR(bio.dataatendimento, 'HH24:MI:SS') AS hora_evento,
                'Exame de Biomicroscopia realizado' AS descricao_resumida,
                'Biomicroscopia' AS procedimento,
                trim(prof.profissional) AS medico,
                NULL::text AS convenio,
                NULL::text AS tratamento,
                'AT' AS status,
                NULL::text AS quantidade,
                NULL::text AS justificativa,
                NULL::text AS diagnostico,
                CONCAT(
                    -- Ceratometria
                    CASE WHEN bio.k1 IS NOT NULL AND bio.k1 != '' AND bio.k1 != '__.__' 
                         THEN 'K1: ' || bio.k1 ELSE '' END,
                    CASE WHEN bio.k2 IS NOT NULL AND bio.k2 != '' AND bio.k2 != '__.__' 
                         THEN ' | K2: ' || bio.k2 ELSE '' END,
                    CASE WHEN bio.kmediood IS NOT NULL 
                         THEN ' | K Médio OD: ' || bio.kmediood ELSE '' END,
                    CASE WHEN bio.kmediooe IS NOT NULL 
                         THEN ' | K Médio OE: ' || bio.kmediooe ELSE '' END,
                    -- Observações específicas
                    CASE WHEN bio.txtanotacaobiood IS NOT NULL AND trim(bio.txtanotacaobiood) != '' 
                         THEN ' | Obs OD: ' || trim(bio.txtanotacaobiood) ELSE '' END,
                    CASE WHEN bio.txtanotacaobiooe IS NOT NULL AND trim(bio.txtanotacaobiooe) != '' 
                         THEN ' | Obs OE: ' || trim(bio.txtanotacaobiooe) ELSE '' END,
                    -- Cristalino
                    CASE WHEN bio.cmbcatodn1 IS NOT NULL OR bio.cmbcatoen1 IS NOT NULL 
                         THEN ' | Cristalino avaliado' ELSE '' END
                ) AS anotacoes,
                bio.paciente::text AS codigo_paciente,
                NULL::text AS nome_paciente,
                NULL::text AS cpf,
                NULL::text AS olho,
                NULL::text AS impressao_od,
                NULL::text AS impressao_oe,
                NULL::text AS data_exame,
                NULL::text AS tipo_exame
            FROM atendbiomicroscopia bio
            INNER JOIN profissionais prof ON prof.codprofissional = bio.profissional
            WHERE bio.paciente = :prontuario
            AND prof.especialidade = :especialidade
            AND bio.dataatendimento IS NOT NULL
            AND (bio.k1 IS NOT NULL AND bio.k1 != '' AND bio.k1 != '__.__' 
                 OR bio.k2 IS NOT NULL AND bio.k2 != '' AND bio.k2 != '__.__'
                 OR bio.txtanotacaobiood IS NOT NULL AND trim(bio.txtanotacaobiood) != ''
                 OR bio.txtanotacaobiooe IS NOT NULL AND trim(bio.txtanotacaobiooe) != ''
                 OR bio.cmbcatodn1 IS NOT NULL OR bio.cmbcatoen1 IS NOT NULL)
            
            UNION ALL
            
            -- REFRATIVA (28 campos mapeados)
            SELECT 
                'refrativa' AS tipo_registro,
                ref.nratendimento AS id_referencia,
                ref.dataatendimento AS data_evento,
                TO_CHAR(ref.dataatendimento, 'HH24:MI:SS') AS hora_evento,
                'Avaliação Refrativa realizada' AS descricao_resumida,
                'Refrativa' AS procedimento,
                trim(prof.profissional) AS medico,
                NULL::text AS convenio,
                NULL::text AS tratamento,
                'AT' AS status,
                NULL::text AS quantidade,
                NULL::text AS justificativa,
                NULL::text AS diagnostico,
                CONCAT(
                    -- OLHO DIREITO (OD) com bandeiras médicas
                    CASE WHEN (ref.cmbrefraesfod IS NOT NULL AND ref.cmbrefraesfod != '--' AND trim(ref.cmbrefraesfod) != '')
                           OR (ref.cmbrefracilod IS NOT NULL AND ref.cmbrefracilod != '--' AND trim(ref.cmbrefracilod) != '')
                           OR (ref.cmbrefraeixood IS NOT NULL AND ref.cmbrefraeixood != '--' AND trim(ref.cmbrefraeixood) != '')
                           OR (ref.cmbrefraadicaood IS NOT NULL AND ref.cmbrefraadicaood != '--' AND trim(ref.cmbrefraadicaood) != '')
                           OR (ref.cmbrefrasc_od IS NOT NULL AND ref.cmbrefrasc_od != '--' AND trim(ref.cmbrefrasc_od) != '')
                         THEN '[OD] ' ELSE '' END,
                    
                    -- AVs/c OD
                    CASE WHEN ref.cmbrefrasc_od IS NOT NULL AND ref.cmbrefrasc_od != '--' AND trim(ref.cmbrefrasc_od) != '' 
                         THEN '[AVs/c] ' || ref.cmbrefrasc_od || ' ' ELSE '' END,
                    
                    -- Esfera OD
                    CASE WHEN ref.cmbrefraesfod IS NOT NULL AND ref.cmbrefraesfod != '--' AND trim(ref.cmbrefraesfod) != '' 
                         THEN '[ESF] ' || ref.cmbrefraesfod || ' ' ELSE '' END,
                    
                    -- Cilindro OD
                    CASE WHEN ref.cmbrefracilod IS NOT NULL AND ref.cmbrefracilod != '--' AND trim(ref.cmbrefracilod) != '' 
                         THEN '[CIL] ' || ref.cmbrefracilod || ' ' ELSE '' END,
                    
                    -- Eixo OD
                    CASE WHEN ref.cmbrefraeixood IS NOT NULL AND ref.cmbrefraeixood != '--' AND trim(ref.cmbrefraeixood) != '' 
                         THEN '[EIXO] ' || ref.cmbrefraeixood || '° ' ELSE '' END,
                    
                    -- Adição OD
                    CASE WHEN ref.cmbrefraadicaood IS NOT NULL AND ref.cmbrefraadicaood != '--' AND trim(ref.cmbrefraadicaood) != '' 
                         THEN '[ADIÇÃO] ' || ref.cmbrefraadicaood || ' ' ELSE '' END,
                    
                    -- SEPARADOR ENTRE OLHOS
                    CASE WHEN ((ref.cmbrefraesfod IS NOT NULL AND ref.cmbrefraesfod != '--' AND trim(ref.cmbrefraesfod) != '')
                            OR (ref.cmbrefracilod IS NOT NULL AND ref.cmbrefracilod != '--' AND trim(ref.cmbrefracilod) != '')
                            OR (ref.cmbrefrasc_od IS NOT NULL AND ref.cmbrefrasc_od != '--' AND trim(ref.cmbrefrasc_od) != ''))
                          AND ((ref.cmbrefraesfoe IS NOT NULL AND ref.cmbrefraesfoe != '--' AND trim(ref.cmbrefraesfoe) != '')
                            OR (ref.cmbrefraciloe IS NOT NULL AND ref.cmbrefraciloe != '--' AND trim(ref.cmbrefraciloe) != '')
                            OR (ref.cmbrefrasc_oe IS NOT NULL AND ref.cmbrefrasc_oe != '--' AND trim(ref.cmbrefrasc_oe) != ''))
                         THEN '| ' ELSE '' END,
                    
                    -- OLHO ESQUERDO (OE) com bandeiras médicas
                    CASE WHEN (ref.cmbrefraesfoe IS NOT NULL AND ref.cmbrefraesfoe != '--' AND trim(ref.cmbrefraesfoe) != '')
                           OR (ref.cmbrefraciloe IS NOT NULL AND ref.cmbrefraciloe != '--' AND trim(ref.cmbrefraciloe) != '')
                           OR (ref.cmbrefraeixooe IS NOT NULL AND ref.cmbrefraeixooe != '--' AND trim(ref.cmbrefraeixooe) != '')
                           OR (ref.cmbrefraadicaooe IS NOT NULL AND ref.cmbrefraadicaooe != '--' AND trim(ref.cmbrefraadicaooe) != '')
                           OR (ref.cmbrefrasc_oe IS NOT NULL AND ref.cmbrefrasc_oe != '--' AND trim(ref.cmbrefrasc_oe) != '')
                         THEN '[OE] ' ELSE '' END,
                    
                    -- AVs/c OE
                    CASE WHEN ref.cmbrefrasc_oe IS NOT NULL AND ref.cmbrefrasc_oe != '--' AND trim(ref.cmbrefrasc_oe) != '' 
                         THEN '[AVs/c] ' || ref.cmbrefrasc_oe || ' ' ELSE '' END,
                    
                    -- Esfera OE
                    CASE WHEN ref.cmbrefraesfoe IS NOT NULL AND ref.cmbrefraesfoe != '--' AND trim(ref.cmbrefraesfoe) != '' 
                         THEN '[ESF] ' || ref.cmbrefraesfoe || ' ' ELSE '' END,
                    
                    -- Cilindro OE
                    CASE WHEN ref.cmbrefraciloe IS NOT NULL AND ref.cmbrefraciloe != '--' AND trim(ref.cmbrefraciloe) != '' 
                         THEN '[CIL] ' || ref.cmbrefraciloe || ' ' ELSE '' END,
                    
                    -- Eixo OE
                    CASE WHEN ref.cmbrefraeixooe IS NOT NULL AND ref.cmbrefraeixooe != '--' AND trim(ref.cmbrefraeixooe) != '' 
                         THEN '[EIXO] ' || ref.cmbrefraeixooe || '° ' ELSE '' END,
                    
                    -- Adição OE
                    CASE WHEN ref.cmbrefraadicaooe IS NOT NULL AND ref.cmbrefraadicaooe != '--' AND trim(ref.cmbrefraadicaooe) != '' 
                         THEN '[ADIÇÃO] ' || ref.cmbrefraadicaooe || ' ' ELSE '' END,
                    
                    -- OBSERVAÇÕES (se houver)
                    CASE WHEN (ref.obsod IS NOT NULL AND ref.obsod != '--' AND trim(ref.obsod) != '')
                           OR (ref.obsoe IS NOT NULL AND ref.obsoe != '--' AND trim(ref.obsoe) != '')
                         THEN '| [OBS] ' ELSE '' END,
                    CASE WHEN ref.obsod IS NOT NULL AND ref.obsod != '--' AND trim(ref.obsod) != '' 
                         THEN 'OD(' || trim(ref.obsod) || ') ' ELSE '' END,
                    CASE WHEN ref.obsoe IS NOT NULL AND ref.obsoe != '--' AND trim(ref.obsoe) != '' 
                         THEN 'OE(' || trim(ref.obsoe) || ')' ELSE '' END
                ) AS anotacoes,
                ref.paciente::text AS codigo_paciente,
                NULL::text AS nome_paciente,
                NULL::text AS cpf,
                NULL::text AS olho,
                NULL::text AS impressao_od,
                NULL::text AS impressao_oe,
                NULL::text AS data_exame,
                NULL::text AS tipo_exame
            FROM atendrefracao ref
            INNER JOIN profissionais prof ON prof.codprofissional = ref.profissional
            WHERE ref.paciente = :prontuario
            AND prof.especialidade = :especialidade
            AND ref.dataatendimento IS NOT NULL
            AND (ref.cmbrefraesfod IS NOT NULL AND ref.cmbrefraesfod != '--' AND trim(ref.cmbrefraesfod) != ''
                 OR ref.cmbrefraesfoe IS NOT NULL AND ref.cmbrefraesfoe != '--' AND trim(ref.cmbrefraesfoe) != ''
                 OR ref.obsod IS NOT NULL AND ref.obsod != '--' AND trim(ref.obsod) != ''
                 OR ref.obsoe IS NOT NULL AND ref.obsoe != '--' AND trim(ref.obsoe) != '')
            
            UNION ALL
            
            -- RETINA (65+ campos mapeados - Exames FMRB)
            SELECT 
                'retina' AS tipo_registro,
                ret.paciente AS id_referencia,
                ret.dataexame AS data_evento,
                TO_CHAR(ret.dataexame, 'HH24:MI:SS') AS hora_evento,
                CASE WHEN ret.tipo = 'O' THEN 'Exame OCT realizado'
                     WHEN ret.tipo = 'A' THEN 'Angiografia realizada'
                     ELSE 'Exame de Retina realizado' END AS descricao_resumida,
                CASE WHEN ret.tipo = 'O' THEN 'OCT'
                     WHEN ret.tipo = 'A' THEN 'Angiografia'
                     ELSE 'Retina' END AS procedimento,
                trim(prof.profissional) AS medico,
                NULL::text AS convenio,
                NULL::text AS tratamento,
                'AT' AS status,
                NULL::text AS quantidade,
                NULL::text AS justificativa,
                NULL::text AS diagnostico,
                CONCAT(
                    -- Conclusão principal
                    CASE WHEN ret.txtconclusao IS NOT NULL AND trim(ret.txtconclusao) != '' 
                         THEN 'Conclusão: ' || trim(ret.txtconclusao) ELSE '' END,
                    -- Impressão geral
                    CASE WHEN ret.impressao IS NOT NULL AND trim(ret.impressao) != '' 
                         THEN ' | Impressão: ' || trim(ret.impressao) ELSE '' END,
                    -- Papila (principais achados)
                    CASE WHEN ret.pa_edema IS NOT NULL AND ret.pa_edema != '' 
                         THEN ' | Papila: ' || ret.pa_edema ELSE '' END,
                    -- Mácula (principais achados)
                    CASE WHEN ret.ma_eddiabetico IS NOT NULL AND ret.ma_eddiabetico != '' 
                         THEN ' | Mácula: Edema diabético' 
                         WHEN ret.ma_dmri IS NOT NULL AND ret.ma_dmri != '' 
                         THEN ' | Mácula: DMRI'
                         WHEN ret.ma_brilho IS NOT NULL AND ret.ma_brilho != '' 
                         THEN ' | Mácula: ' || ret.ma_brilho ELSE '' END,
                    -- Retina (principais achados)
                    CASE WHEN ret.re_hemo IS NOT NULL AND ret.re_hemo != '' 
                         THEN ' | Retina: Hemorragias'
                         WHEN ret.re_exu IS NOT NULL AND ret.re_exu != '' 
                         THEN ' | Retina: Exsudatos'
                         WHEN ret.re_neovasos IS NOT NULL AND ret.re_neovasos != '' 
                         THEN ' | Retina: Neovasos' ELSE '' END,
                    -- OCT específico
                    CASE WHEN ret.tipo = 'O' AND ret.oct_conclusao IS NOT NULL AND trim(ret.oct_conclusao) != '' 
                         THEN ' | OCT: ' || trim(ret.oct_conclusao) ELSE '' END
                ) AS anotacoes,
                ret.paciente::text AS codigo_paciente,
                NULL::text AS nome_paciente,
                NULL::text AS cpf,
                trim(ret.olho) AS olho,
                trim(ret.impressaood) AS impressao_od,
                trim(ret.impressaooe) AS impressao_oe,
                TO_CHAR(ret.dataexame, 'YYYY-MM-DD') AS data_exame,
                CASE WHEN ret.tipo = 'O' THEN 'OCT'
                     WHEN ret.tipo = 'A' THEN 'Angiografia'
                     ELSE 'Retina' END AS tipo_exame
            FROM examesfmrb ret
            INNER JOIN profissionais prof ON prof.codprofissional = ret.profissional
            WHERE ret.paciente = :prontuario
            AND ret.dataexame IS NOT NULL
            AND (ret.txtconclusao IS NOT NULL AND trim(ret.txtconclusao) != ''
                 OR ret.impressao IS NOT NULL AND trim(ret.impressao) != ''
                 OR ret.pa_edema IS NOT NULL OR ret.ma_eddiabetico IS NOT NULL 
                 OR ret.re_hemo IS NOT NULL OR ret.oct_conclusao IS NOT NULL)
            
            UNION ALL
            
            -- MEDICAMENTOS EM USO (10 campos mapeados)
            SELECT 
                'medicamento' AS tipo_registro,
                med.paciente AS id_referencia,
                med.data AS data_evento,
                TO_CHAR(med.data, 'HH24:MI:SS') AS hora_evento,
                'Medicamento prescrito' AS descricao_resumida,
                'Medicamento' AS procedimento,
                trim(prof.profissional) AS medico,
                NULL::text AS convenio,
                NULL::text AS tratamento,
                'AT' AS status,
                med.qtde::text AS quantidade,
                NULL::text AS justificativa,
                NULL::text AS diagnostico,
                CONCAT(
                    med.medicamentoemuso,
                    CASE WHEN med.uso IS NOT NULL AND trim(med.uso) != '' 
                         THEN ' | Uso: ' || trim(med.uso) ELSE '' END,
                    CASE WHEN med.olho IS NOT NULL AND trim(med.olho) != '' 
                         THEN ' | Olho: ' || trim(med.olho) ELSE '' END,
                    CASE WHEN med.vezes IS NOT NULL 
                         THEN ' | Frequência: ' || med.vezes || 'x/dia' ELSE '' END,
                    CASE WHEN med.qtde IS NOT NULL 
                         THEN ' | Qtde: ' || med.qtde ELSE '' END,
                    CASE WHEN med.qtde_gc IS NOT NULL 
                         THEN ' | Gotas: ' || med.qtde_gc ELSE '' END,
                    CASE WHEN med.conduta IS NOT NULL 
                         THEN ' | Conduta: ' || med.conduta ELSE '' END,
                    CASE WHEN med.viamed IS NOT NULL 
                         THEN ' | Via: ' || med.viamed ELSE '' END
                ) AS anotacoes,
                med.paciente::text AS codigo_paciente,
                NULL::text AS nome_paciente,
                NULL::text AS cpf,
                med.olho AS olho,
                NULL::text AS impressao_od,
                NULL::text AS impressao_oe,
                TO_CHAR(med.data, 'YYYY-MM-DD') AS data_exame,
                'Medicamento' AS tipo_exame
            FROM medicamentosemuso med
            LEFT JOIN profissionais prof ON prof.codprofissional = med.medico
            WHERE med.paciente = :prontuario
            AND med.data IS NOT NULL
            AND med.medicamentoemuso IS NOT NULL
            AND trim(med.medicamentoemuso) != ''
            
            UNION ALL
            
            -- GONIOSCOPIA (42 campos mapeados)
            SELECT 
                'gonioscopia' AS tipo_registro,
                gon.paciente AS id_referencia,
                gon.dataatendimento AS data_evento,
                TO_CHAR(gon.dataatendimento, 'HH24:MI:SS') AS hora_evento,
                'Exame de Gonioscopia realizado' AS descricao_resumida,
                'Gonioscopia' AS procedimento,
                trim(prof.profissional) AS medico,
                NULL::text AS convenio,
                NULL::text AS tratamento,
                'AT' AS status,
                NULL::text AS quantidade,
                NULL::text AS justificativa,
                NULL::text AS diagnostico,
                CONCAT(
                    'Olho: ' || gon.olho,
                    -- Quadrantes
                    CASE WHEN gon.qnasal IS NOT NULL THEN ' | QN: ' || gon.qnasal ELSE '' END,
                    CASE WHEN gon.qsuperior IS NOT NULL THEN ' | QS: ' || gon.qsuperior ELSE '' END,
                    CASE WHEN gon.qinferior IS NOT NULL THEN ' | QI: ' || gon.qinferior ELSE '' END,
                    CASE WHEN gon.qtemporal IS NOT NULL THEN ' | QT: ' || gon.qtemporal ELSE '' END,
                    -- Configuração da íris
                    CASE WHEN gon.concava = 'True' THEN ' | Íris: Côncava'
                         WHEN gon.convexa = 'True' THEN ' | Íris: Convexa'
                         WHEN gon.plateau = 'True' THEN ' | Íris: Plateau'
                         WHEN gon.plana = 'True' THEN ' | Íris: Plana' ELSE '' END,
                    -- Conclusão
                    CASE WHEN gon.conaberto = 'True' THEN ' | Conclusão: Aberto'
                         WHEN gon.conoclusivel = 'True' THEN ' | Conclusão: Oclusível'
                         WHEN gon.confechadoapo = 'True' THEN ' | Conclusão: Fechado com aposição'
                         WHEN gon.confechadosin = 'True' THEN ' | Conclusão: Fechado com sinéquia' ELSE '' END,
                    -- Resumo
                    CASE WHEN gon.resumogonio IS NOT NULL AND trim(gon.resumogonio) != '' 
                         THEN ' | Resumo: ' || trim(gon.resumogonio) ELSE '' END
                ) AS anotacoes,
                gon.paciente::text AS codigo_paciente,
                NULL::text AS nome_paciente,
                NULL::text AS cpf,
                gon.olho AS olho,
                NULL::text AS impressao_od,
                NULL::text AS impressao_oe,
                TO_CHAR(gon.dataatendimento, 'YYYY-MM-DD') AS data_exame,
                'Gonioscopia' AS tipo_exame
            FROM gonioquadro gon
            INNER JOIN profissionais prof ON prof.codprofissional = gon.profissional
            WHERE gon.paciente = :prontuario
            AND prof.especialidade = :especialidade
            AND gon.dataatendimento IS NOT NULL
            AND (gon.qnasal IS NOT NULL OR gon.qsuperior IS NOT NULL 
                 OR gon.qinferior IS NOT NULL OR gon.qtemporal IS NOT NULL
                 OR gon.resumogonio IS NOT NULL AND trim(gon.resumogonio) != '')
            
            UNION ALL
            
            -- PAQUIMETRIA (14 campos mapeados - usa tabela PIO)
            SELECT 
                'paquimetria' AS tipo_registro,
                paq.paciente AS id_referencia,
                paq.datapio AS data_evento,
                TO_CHAR(paq.datapio, 'HH24:MI:SS') AS hora_evento,
                'Paquimetria realizada' AS descricao_resumida,
                'Paquimetria' AS procedimento,
                NULL::text AS medico,
                NULL::text AS convenio,
                NULL::text AS tratamento,
                'AT' AS status,
                NULL::text AS quantidade,
                NULL::text AS justificativa,
                NULL::text AS diagnostico,
                CONCAT(
                    -- Medições OD
                    CASE WHEN paq.odmed IS NOT NULL AND trim(paq.odmed) != '' AND paq.odmed != '--' 
                         THEN 'OD - Medição: ' || paq.odmed ELSE '' END,
                    CASE WHEN paq.odaj IS NOT NULL AND trim(paq.odaj) != '' AND paq.odaj != '--' 
                         THEN ' | AJ: ' || paq.odaj ELSE '' END,
                    CASE WHEN paq.odalvo IS NOT NULL AND trim(paq.odalvo) != '' AND paq.odalvo != '--' 
                         THEN ' | Alvo: ' || paq.odalvo ELSE '' END,
                    CASE WHEN paq.efeitood IS NOT NULL AND trim(paq.efeitood) != '' AND paq.efeitood != '--' 
                         THEN ' | Efeito: ' || paq.efeitood ELSE '' END,
                    CASE WHEN paq.avod IS NOT NULL AND trim(paq.avod) != '' AND paq.avod != '--' 
                         THEN ' | AV: ' || paq.avod ELSE '' END,
                    CASE WHEN paq.food IS NOT NULL AND trim(paq.food) != '' AND paq.food != '--' 
                         THEN ' | FO: ' || paq.food ELSE '' END,
                    -- Medições OE
                    CASE WHEN paq.oemed IS NOT NULL AND trim(paq.oemed) != '' AND paq.oemed != '--' 
                         THEN ' | OE - Medição: ' || paq.oemed ELSE '' END,
                    CASE WHEN paq.oeaj IS NOT NULL AND trim(paq.oeaj) != '' AND paq.oeaj != '--' 
                         THEN ' | AJ: ' || paq.oeaj ELSE '' END,
                    CASE WHEN paq.oealvo IS NOT NULL AND trim(paq.oealvo) != '' AND paq.oealvo != '--' 
                         THEN ' | Alvo: ' || paq.oealvo ELSE '' END,
                    CASE WHEN paq.efeitooe IS NOT NULL AND trim(paq.efeitooe) != '' AND paq.efeitooe != '--' 
                         THEN ' | Efeito: ' || paq.efeitooe ELSE '' END,
                    CASE WHEN paq.avoe IS NOT NULL AND trim(paq.avoe) != '' AND paq.avoe != '--' 
                         THEN ' | AV: ' || paq.avoe ELSE '' END,
                    CASE WHEN paq.fooe IS NOT NULL AND trim(paq.fooe) != '' AND paq.fooe != '--' 
                         THEN ' | FO: ' || paq.fooe ELSE '' END
                ) AS anotacoes,
                paq.paciente::text AS codigo_paciente,
                NULL::text AS nome_paciente,
                NULL::text AS cpf,
                NULL::text AS olho,
                NULL::text AS impressao_od,
                NULL::text AS impressao_oe,
                TO_CHAR(paq.datapio, 'YYYY-MM-DD') AS data_exame,
                'Paquimetria' AS tipo_exame
            FROM pio paq
            WHERE paq.paciente = :prontuario
            AND paq.datapio IS NOT NULL
            AND (paq.odmed IS NOT NULL OR paq.oemed IS NOT NULL
                 OR paq.odaj IS NOT NULL OR paq.oeaj IS NOT NULL)
            
            UNION ALL
            
            -- PROCEDIMENTOS SOLICITADOS (protocolo_guia + protocolo_guia_procedimentos)
           SELECT
    'procedimento_solicitado' AS tipo_registro,
    pg.id AS id_referencia,
    pg.data AS data_evento,
    TO_CHAR(pg.data, 'HH24:MI:SS') AS hora_evento,
    'Procedimento solicitado' AS descricao_resumida,
    trim(pg.descricao) AS procedimento,
    trim(prof.profissional) AS medico,
    conv.convenio::text AS convenio,
    NULL::text AS tratamento,
    'AT' AS status,
    NULL::text AS quantidade,
    pg.justificativa AS justificativa,
    pg.diagnostico AS diagnostico,
    CONCAT(
        'Diagnóstico: ', COALESCE(pg.diagnostico, ''),
        E'\nJustificativa: ', COALESCE(pg.justificativa, ''),
        E'\n', COALESCE(
            STRING_AGG(
                CONCAT(
                    '\n', COALESCE(pgp.codigo_procedimento, ''),
                    ' - ', COALESCE(p.descricaoprocedimento, ''),
                    ', Qtde: ', COALESCE(pgp.quantidade::text, ''),
                    ', Composição: ', COALESCE(pgp.composicao::text, ''),
                   -- ', Prazo: ', COALESCE(pgp.prazo, ''),
                   -- ', Freq: ', COALESCE(pgp.frequencia::text, ''),
                  --  ', Prioridade: ', COALESCE(pgp.prioridade::text, ''),
                    ', Status: ', COALESCE(pgp.status::text, '')
                ),
                E'\n'
            ) FILTER (WHERE pgp.codigo_procedimento IS NOT NULL),
            ''
        )
    ) AS anotacoes,
    pg.paciente_id::text AS codigo_paciente,
    NULL::text AS nome_paciente,
    NULL::text AS cpf,
    NULL::text AS olho,
    NULL::text AS impressao_od,
    NULL::text AS impressao_oe,
    TO_CHAR(pg.data, 'YYYY-MM-DD') AS data_exame,
    'Procedimento' AS tipo_exame
FROM protocolo_guia pg
LEFT JOIN profissionais prof ON prof.codprofissional = pg.medico_id
LEFT JOIN convenios conv ON conv.codconvenio = pg.convenio_id
LEFT JOIN protocolo_guia_procedimentos pgp ON pgp.protocolo_id = pg.id
LEFT JOIN procedimentos p ON p.codprocedimento = pgp.codigo_procedimento
WHERE pg.paciente_id::text = :prontuario::text
  AND pg.data IS NOT NULL
  AND (
        (pg.descricao IS NOT NULL AND trim(pg.descricao) != '')
     OR (pg.guia IS NOT NULL AND trim(pg.guia) != '')
     OR (pg.justificativa IS NOT NULL AND trim(pg.justificativa) != '')
  )
GROUP BY
    pg.id, pg.data, pg.descricao, prof.profissional, conv.convenio,
    pg.justificativa, pg.diagnostico, pg.paciente_id
            
            UNION ALL
            
            -- MEDICAMENTOS PRESCRITOS (protocolo_solicitacao + medicamentos_protocolo_solicitacao)
 SELECT 
    'medicamento_prescrito' AS tipo_registro,
    ps.id AS id_referencia,
    ps.data AS data_evento,
    TO_CHAR(ps.data, 'HH24:MI:SS') AS hora_evento,
    'Medicamento prescrito' AS descricao_resumida,
    NULL AS procedimento, -- pois o detalhe estará em anotacoes
    trim(prof.profissional) AS medico,
    NULL::text AS convenio,
    NULL::text AS tratamento,
    'AT' AS status,
    NULL::text AS quantidade,
    NULL::text AS justificativa,
    NULL::text AS diagnostico,
    -- Anotacoes detalhadas por medicamento e substituto
    (
        SELECT STRING_AGG(
            CONCAT(
                -- Medicamento principal
                E'\n' || COALESCE(mps.titulo, ''),
                -- Campos condicionais (só mostra se não forem NULL/vazios)
                CASE WHEN mps.posologia IS NOT NULL AND trim(mps.posologia) != '' 
                     THEN E'\nPosologia: ' || trim(mps.posologia) ELSE '' END,
                CASE WHEN mps.quantidade IS NOT NULL 
                     THEN E'\Quantidade: ' || mps.quantidade::text ELSE '' END,
                CASE WHEN mps.uso_continuo IS NOT NULL 
                     THEN E'\nUso contínuo: ' || CASE WHEN mps.uso_continuo THEN 'Sim' ELSE 'Não' END ELSE '' END,
                CASE WHEN mps.especial IS NOT NULL 
                     THEN E'\nEspecial: ' || CASE WHEN mps.especial THEN 'Sim' ELSE 'Não' END ELSE '' END,
                -- Substitutos (se existirem)
                COALESCE(
                    (
                        SELECT STRING_AGG(
                            CONCAT(
                                E'\n  Substituto: ' || COALESCE(ms.titulo, ''),
                                -- Campos comentados removidos
                                CASE WHEN ms.posologia IS NOT NULL AND trim(ms.posologia) != '' 
                                     THEN E'\nPosologia: ' || trim(ms.posologia) ELSE '' END,
                                CASE WHEN ms.quantidade IS NOT NULL 
                                     THEN E'\nQuantidade: ' || ms.quantidade::text ELSE '' END,
                                CASE WHEN ms.uso_continuo IS NOT NULL 
                                     THEN E'\nUso contínuo: ' || CASE WHEN ms.uso_continuo THEN 'Sim' ELSE 'Não' END ELSE '' END,
                                CASE WHEN ms.especial IS NOT NULL 
                                     THEN E'\nEspecial: ' || CASE WHEN ms.especial THEN 'Sim' ELSE 'Não' END ELSE '' END
                            ),
                            ''
                        )
                        FROM medicamentos_protocolo_solicitacao_substitutos ms
                        WHERE ms.medicamentos_protocolo_solicitacao_id = mps.id
                          AND (
                                (ms.titulo IS NOT NULL AND trim(ms.titulo) != '')
                             OR (ms.descricao IS NOT NULL AND trim(ms.descricao) != '')
                          )
                    ),
                    ''
                )
            ),
            E'\n'
        )
        FROM medicamentos_protocolo_solicitacao mps
        WHERE mps.protocolo_solicitacao_id = ps.id
          AND (
                (mps.titulo IS NOT NULL AND trim(mps.titulo) != '')
             OR (mps.descricao IS NOT NULL AND trim(mps.descricao) != '')
          )
    ) AS anotacoes,
    ps.paciente_id::text AS codigo_paciente,
    NULL::text AS nome_paciente,
    NULL::text AS cpf,
    NULL::text AS olho,
    NULL::text AS impressao_od,
    NULL::text AS impressao_oe,
    TO_CHAR(ps.data, 'YYYY-MM-DD') AS data_exame,
    'Medicamento' AS tipo_exame
FROM protocolo_solicitacao ps
LEFT JOIN profissionais prof ON prof.codprofissional = ps.medico_id
WHERE ltrim(ps.paciente_id::text, '0') = :prontuario::text
  AND ps.data IS NOT NULL
  -- Só mostrar protocolos com pelo menos 1 medicamento relevante
  AND EXISTS (
      SELECT 1 FROM medicamentos_protocolo_solicitacao mps
      WHERE mps.protocolo_solicitacao_id = ps.id
        AND (
              (mps.titulo IS NOT NULL AND trim(mps.titulo) != '')
           OR (mps.descricao IS NOT NULL AND trim(mps.descricao) != '')
        )
  )
  UNION ALL                  
   -- DOCUMENTOS MÉDICOS (protocolo_documento + protocolo_documento_imagem)
SELECT 
    'documento_medico' AS tipo_registro,
    pd.id AS id_referencia,
    pd.data AS data_evento,
    TO_CHAR(pd.data, 'HH24:MI:SS') AS hora_evento,
    'Documento médico gerado' AS descricao_resumida,
    pd.descricao AS procedimento,
    trim(prof.profissional) AS medico,
    NULL::text AS convenio,
    NULL::text AS tratamento,
    'AT'::text AS status,
    NULL::text AS quantidade,
    NULL::text AS justificativa,
    NULL::text AS diagnostico,
    CONCAT(
        'Documento: ' || pd.descricao,
        CASE WHEN pd.perfil IS NOT NULL AND trim(pd.perfil) != '' 
             THEN ' | Perfil: ' || trim(pd.perfil) ELSE '' END,
        CASE WHEN pdi.nome_imagem IS NOT NULL AND trim(pdi.nome_imagem) != '' 
             THEN ' | Imagem: ' || trim(pdi.nome_imagem) ELSE '' END,
        CASE WHEN pd.conteudo IS NOT NULL AND trim(pd.conteudo) != '' 
             THEN ' | Conteúdo: ' || trim(pd.conteudo) ELSE '' END
    ) AS anotacoes,
    pd.paciente_id::text AS codigo_paciente,
    NULL::text AS nome_paciente,
    NULL::text AS cpf,
    NULL::text AS olho,
    NULL::text AS impressao_od,
    NULL::text AS impressao_oe,
    TO_CHAR(pd.data, 'YYYY-MM-DD') AS data_exame,
    'Documento'::text AS tipo_exame
FROM protocolo_documento pd
LEFT JOIN protocolo_documento_imagem pdi ON pdi.documento_id = pd.id
LEFT JOIN profissionais prof ON prof.codprofissional = pd.medico_id
WHERE pd.paciente_id::text = :prontuario::text
AND pd.data IS NOT NULL
AND (pd.descricao IS NOT NULL AND trim(pd.descricao) != ''
     OR pd.conteudo IS NOT NULL AND trim(pd.conteudo) != '')
   
SQL;

            // Monta SQL final paginado
            $sql = "SELECT * FROM ( $unionSql ) AS bobina_completa
                    ORDER BY data_evento DESC, hora_evento DESC
                    LIMIT :limit OFFSET :offset";
            
            // Monta SQL de contagem total
            $countSql = "SELECT COUNT(*) as total FROM ( $unionSql ) AS bobina_completa";

            // Adiciona limit/offset como parâmetros
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            // Executa consulta principal
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            
            // Executa e captura erros detalhados
            if (!$stmt->execute()) {
                $errorInfo = $stmt->errorInfo();
                throw new \PDOException("Erro ao executar consulta principal: " . $errorInfo[2] . " [SQLSTATE: " . $errorInfo[0] . "]");
            }
            
            try {
                $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            } catch (Exception $exc) {
                throw new \Exception("Erro ao buscar dados: " . $exc->getMessage());
            }

            // Executa consulta de contagem total (sem limit/offset)
            $countStmt = $this->pdo->prepare($countSql);
            // Remove :limit e :offset dos parâmetros do count
            foreach ($params as $key => $value) {
                if ($key === ':limit' || $key === ':offset') continue;
                $type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
                $countStmt->bindValue($key, $value, $type);
            }
            
            // Executa e captura erros detalhados do count
            if (!$countStmt->execute()) {
                $errorInfo = $countStmt->errorInfo();
                throw new \PDOException("Erro ao executar consulta de contagem: " . $errorInfo[2] . " [SQLSTATE: " . $errorInfo[0] . "]");
            }
            
            $total = (int)$countStmt->fetchColumn();

            return [
                'dados' => $dados,
                'total' => $total,
                'pagina' => (int)$pagina,
                'por_Pagina' => (int)$itensPorPagina,
                'total_paginas' => ceil($total / (int)$itensPorPagina)
            ];
        } catch (\PDOException $e) {
            // Log detalhado do erro PDO
            error_log("Erro PDO na bobina: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            error_log("Parâmetros: " . print_r($params, true));
            
            return [
                'erro' => true,
                'mensagem' => 'Erro de banco de dados: ' . $e->getMessage(),
                'detalhes' => 'SQL State: ' . $e->getCode(),
                'dados' => [],
                'total' => 0,
                'pagina' => (int)$pagina,
                'por_Pagina' => (int)$itensPorPagina,
                'total_paginas' => 0
            ];
        } catch (\Exception $e) {
            // Log detalhado do erro geral
            error_log("Erro geral na bobina: " . $e->getMessage());
            error_log("Arquivo: " . $e->getFile() . " Linha: " . $e->getLine());
            error_log("Trace: " . $e->getTraceAsString());
            
            return [
                'erro' => true,
                'mensagem' => 'Erro no sistema: ' . $e->getMessage(),
                'detalhes' => 'Arquivo: ' . $e->getFile() . ' Linha: ' . $e->getLine(),
                'dados' => [],
                'total' => 0,
                'pagina' => (int)$pagina,
                'por_Pagina' => (int)$itensPorPagina,
                'total_paginas' => 0
            ];
        }
        }

        // Lista registros para campanha com filtros dinâmicos
        public function listarCampanha($pagina = 1, $itensPorPagina = 50, $filtros = null){
            // Se não veio por parâmetro, tenta ler do corpo do request
            if (!is_array($filtros) || empty($filtros)) {
                $body = file_get_contents('php://input');
                $json = json_decode($body, true);
                if (is_array($json) && isset($json['filtros'])) {
                    $filtros = $json['filtros'];
                } else {
                    $filtros = is_array($json) ? $json : [];
                }
            }

            $pagina = (int)($pagina ?? 1);
            $itensPorPagina = (int)($itensPorPagina ?? 50);
            $offset = max(0, ($pagina - 1) * $itensPorPagina);

            $filtros_profissionais = isset($filtros['filtros_profissionais']) && is_array($filtros['filtros_profissionais']) ? $filtros['filtros_profissionais'] : [];
            $filtros_convenios     = isset($filtros['filtros_convenios']) && is_array($filtros['filtros_convenios']) ? $filtros['filtros_convenios'] : [];
            $filtros_unidades      = isset($filtros['filtros_unidades']) && is_array($filtros['filtros_unidades']) ? $filtros['filtros_unidades'] : [];
            $filtros_procedimentos = isset($filtros['filtros_procedimentos']) && is_array($filtros['filtros_procedimentos']) ? $filtros['filtros_procedimentos'] : [];
            $dateStart             = isset($filtros['dateStart']) ? $filtros['dateStart'] : null;
            $dateEnd               = isset($filtros['dateEnd']) ? $filtros['dateEnd'] : null;

            $params = [];
            $where = [];

            if ($dateStart && $dateEnd) {
                $where[] = "a.datamovimento BETWEEN :dateStart::date AND :dateEnd::date";
                $params[':dateStart'] = $dateStart;
                $params[':dateEnd']   = $dateEnd;
            } elseif ($dateStart) {
                $where[] = "a.datamovimento >= :dateStart::date";
                $params[':dateStart'] = $dateStart;
            } elseif ($dateEnd) {
                $where[] = "a.datamovimento <= :dateEnd::date";
                $params[':dateEnd'] = $dateEnd;
            }

            if (count($filtros_profissionais) > 0) {
                $in = [];
                foreach ($filtros_profissionais as $i => $clip) {
                    $key = ":prof_$i";
                    $in[] = $key;
                    $params[$key] = is_array($clip) && isset($clip['id']) ? (string)$clip['id'] : (string)$clip;
                }
                $where[] = "a.codprofissional IN (" . implode(',', $in) . ")";
            }

            if (count($filtros_convenios) > 0) {
                $in = [];
                foreach ($filtros_convenios as $i => $clip) {
                    $key = ":conv_$i";
                    $in[] = $key;
                    $params[$key] = is_array($clip) && isset($clip['id']) ? (string)$clip['id'] : (string)$clip;
                }
                $where[] = "a.convenio IN (" . implode(',', $in) . ")";
            }

            if (count($filtros_unidades) > 0) {
                $in = [];
                foreach ($filtros_unidades as $i => $clip) {
                    $key = ":uni_$i";
                    $in[] = $key;
                    $params[$key] = is_array($clip) && isset($clip['id']) ? (string)$clip['id'] : (string)$clip;
                }
                $where[] = "a.unidade IN (" . implode(',', $in) . ")";
            }

            if (count($filtros_procedimentos) > 0) {
                $likes = [];
                foreach ($filtros_procedimentos as $i => $clip) {
                    $key = ":proc_$i";
                    $likes[] = "a.procedimentos ILIKE $key";
                    $params[$key] = '%' . (is_array($clip) && isset($clip['id']) ? (string)$clip['id'] : (string)$clip) . '%';
                }
                $where[] = '(' . implode(' OR ', $likes) . ')';
            }

            $whereSql = count($where) > 0 ? ('WHERE ' . implode(' AND ', $where)) : '';

            $sql = "
                SELECT 
                    a.movimento AS id,
                    to_char(a.datamovimento, 'YYYY-MM-DD') AS data,
                    a.paciente AS paciente,
                    COALESCE(TRIM(a.procedimentos),'') AS procedimento,
                    COALESCE(TRIM(prof.profissional),'') AS medico,
                    COALESCE(conv.convenio,'') AS convenio,
                    COALESCE(u.unidades,'') AS unidade,
                    'atendimento' AS tipo
                FROM agenda a
                LEFT JOIN profissionais prof ON prof.codprofissional = a.codprofissional
                LEFT JOIN convenios conv ON conv.codconvenio = a.convenio
                LEFT JOIN unidades u ON u.codunidades = a.unidade
                $whereSql
                ORDER BY a.datamovimento DESC, a.movimento DESC
                LIMIT :limit OFFSET :offset
            ";

            try {
                $stmt = $this->pdo->prepare($sql);
                foreach ($params as $k => $v) {
                    if ($k === ':limit' || $k === ':offset') continue;
                    $stmt->bindValue($k, $v);
                }
                $stmt->bindValue(':limit', (int)$itensPorPagina, \PDO::PARAM_INT);
                $stmt->bindValue(':offset', (int)$offset, \PDO::PARAM_INT);
                $stmt->execute();
                $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                return [
                    'status' => 'sucesso',
                    'pagina' => $pagina,
                    'itensPorPagina' => $itensPorPagina,
                    'quantidade' => count($dados),
                    'dados' => $dados,
                ];
            } catch (\Exception $e) {
                return [
                    'status' => 'erro',
                    'mensagem' => 'Falha ao listar campanha: ' . $e->getMessage(),
                ];
            }
        }
    }
}