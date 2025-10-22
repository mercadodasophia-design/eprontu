<?php
/**
 * Rotas do Dashboard Analytics
 * Endpoints: /api/dashboard/*
 */

// Incluir classes necessárias
require_once '../classes/Dashboard.php';

$dashboard = new Dashboard();

// Debug: mostrar informações
if (isset($_GET['debug'])) {
    echo json_encode([
        'method' => $method,
        'action' => $action,
        'endpoint' => $endpoint
    ]);
    exit();
}

// Determinar ação baseada no método HTTP e parâmetros
switch ($method) {
    case 'GET':
        switch ($action) {
            case 'estatisticas':
                // GET /api/dashboard/estatisticas
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                $profissional = $_GET['profissional'] ?? null;
                $especialidade = $_GET['especialidade'] ?? null;
                $unidade = $_GET['unidade'] ?? null;
                
                try {
                    $result = $dashboard->getEstatisticas(
                        $dataInicio,
                        $dataFim,
                        $profissional,
                        $especialidade,
                        $unidade
                    );
                    $response->success($result, 'Estatísticas carregadas com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar estatísticas: ' . $e->getMessage(), 500);
                }
                break;
                
            case 'atendimentos-periodo':
                // GET /api/dashboard/atendimentos-periodo
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                $profissional = $_GET['profissional'] ?? null;
                $especialidade = $_GET['especialidade'] ?? null;
                $unidade = $_GET['unidade'] ?? null;
                
                try {
                    $result = $dashboard->getAtendimentosPeriodo(
                        $dataInicio,
                        $dataFim,
                        $profissional,
                        $especialidade,
                        $unidade
                    );
                    $response->success($result, 'Dados de período carregados com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar dados de período: ' . $e->getMessage(), 500);
                }
                break;
                
            case 'atendimentos-status':
                // GET /api/dashboard/atendimentos-status
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                $profissional = $_GET['profissional'] ?? null;
                $especialidade = $_GET['especialidade'] ?? null;
                $unidade = $_GET['unidade'] ?? null;
                
                try {
                    $result = $dashboard->getAtendimentosStatus(
                        $dataInicio,
                        $dataFim,
                        $profissional,
                        $especialidade,
                        $unidade
                    );
                    $response->success($result, 'Dados de status carregados com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar dados de status: ' . $e->getMessage(), 500);
                }
                break;
                
            case 'atendimentos-profissional':
                // GET /api/dashboard/atendimentos-profissional
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                $profissional = $_GET['profissional'] ?? null;
                $especialidade = $_GET['especialidade'] ?? null;
                $unidade = $_GET['unidade'] ?? null;
                
                try {
                    $result = $dashboard->getAtendimentosProfissional(
                        $dataInicio,
                        $dataFim,
                        $profissional,
                        $especialidade,
                        $unidade
                    );
                    $response->success($result, 'Dados de profissionais carregados com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar dados de profissionais: ' . $e->getMessage(), 500);
                }
                break;
                
            case 'novos-pacientes':
                // GET /api/dashboard/novos-pacientes
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                $profissional = $_GET['profissional'] ?? null;
                $especialidade = $_GET['especialidade'] ?? null;
                $unidade = $_GET['unidade'] ?? null;
                
                try {
                    $result = $dashboard->getNovosPacientes(
                        $dataInicio,
                        $dataFim,
                        $profissional,
                        $especialidade,
                        $unidade
                    );
                    $response->success($result, 'Dados de pacientes carregados com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar dados de pacientes: ' . $e->getMessage(), 500);
                }
                break;
                
            case 'atendimentos-especialidade':
                // GET /api/dashboard/atendimentos-especialidade
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                $profissional = $_GET['profissional'] ?? null;
                $especialidade = $_GET['especialidade'] ?? null;
                $unidade = $_GET['unidade'] ?? null;
                
                try {
                    $result = $dashboard->getAtendimentosEspecialidade(
                        $dataInicio,
                        $dataFim,
                        $profissional,
                        $especialidade,
                        $unidade
                    );
                    $response->success($result, 'Dados de especialidades carregados com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar dados de especialidades: ' . $e->getMessage(), 500);
                }
                break;
                
            case 'tempo-atendimento':
                // GET /api/dashboard/tempo-atendimento
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                $profissional = $_GET['profissional'] ?? null;
                $especialidade = $_GET['especialidade'] ?? null;
                $unidade = $_GET['unidade'] ?? null;
                
                try {
                    $result = $dashboard->getTempoAtendimento(
                        $dataInicio,
                        $dataFim,
                        $profissional,
                        $especialidade,
                        $unidade
                    );
                    $response->success($result, 'Dados de tempo carregados com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar dados de tempo: ' . $e->getMessage(), 500);
                }
                break;
                
            case 'medicamentos-prescritos':
                // GET /api/dashboard/medicamentos-prescritos
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                $profissional = $_GET['profissional'] ?? null;
                $especialidade = $_GET['especialidade'] ?? null;
                $unidade = $_GET['unidade'] ?? null;
                
                try {
                    $result = $dashboard->getMedicamentosPrescritos(
                        $dataInicio,
                        $dataFim,
                        $profissional,
                        $especialidade,
                        $unidade
                    );
                    $response->success($result, 'Dados de medicamentos carregados com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar dados de medicamentos: ' . $e->getMessage(), 500);
                }
                break;
                
            case 'documentos-gerados':
                // GET /api/dashboard/documentos-gerados
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                $profissional = $_GET['profissional'] ?? null;
                $especialidade = $_GET['especialidade'] ?? null;
                $unidade = $_GET['unidade'] ?? null;
                
                try {
                    $result = $dashboard->getDocumentosGerados(
                        $dataInicio,
                        $dataFim,
                        $profissional,
                        $especialidade,
                        $unidade
                    );
                    $response->success($result, 'Dados de documentos carregados com sucesso');
                } catch (Exception $e) {
                    $response->error('Erro ao carregar dados de documentos: ' . $e->getMessage(), 500);
                }
                break;
                
            default:
                $response->error('Ação não encontrada', 404);
                break;
        }
        break;
        
    default:
        $response->error('Método não permitido', 405);
        break;
}
?>
