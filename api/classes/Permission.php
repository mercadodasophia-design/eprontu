<?php
/**
 * Classe de Gerenciamento de Permissões
 */

class Permission {
    private $db;
    private $response;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->response = new Response();
    }
    
    /**
     * Verificar permissão do usuário
     */
    public function checkPermission($userId, $module, $action = null) {
        try {
            $sql = "SELECT {$module}" . ($action ? ", {$action}" : "") . " 
                    FROM usuarios_permissao 
                    WHERE codusuario = :id";
            
            $permission = $this->db->fetchOne($sql, ['id' => $userId]);
            
            if (!$permission) {
                return false;
            }
            
            // Verificar permissão básica
            if ($permission[$module] !== 'S') {
                return false;
            }
            
            // Verificar permissão específica se fornecida
            if ($action && isset($permission[$action])) {
                return $permission[$action] === 'S';
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obter todas as permissões do usuário
     */
    public function getUserPermissions($userId) {
        try {
            $sql = "SELECT * FROM usuarios_permissao WHERE codusuario = :id";
            $permissions = $this->db->fetchOne($sql, ['id' => $userId]);
            
            if (!$permissions) {
                $this->response->error('Permissões não encontradas', HTTP_NOT_FOUND);
            }
            
            $this->response->success($permissions, 'Permissões carregadas com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro ao carregar permissões: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Atualizar permissões do usuário
     */
    public function updatePermissions($userId, $permissions) {
        try {
            // Validar dados
            $allowedModules = [
                'agenda', 'tp_agenda', 'agenda_medica', 'tp_agenda_medica',
                'atendimento', 'tp_atendimento', 'cadastro_tabelas', 'tp_cadastro_tabelas',
                'cirurgia', 'tp_cirurgia', 'configuracao', 'tp_configuracao',
                'contas_medicas', 'tp_contas_medicas', 'financeiro', 'tp_financeiro',
                'gestao_pendentes', 'tp_gestao_pendentes', 'mapa_cirurgico', 'tp_mapa_cirurgico',
                'negociacao', 'tp_negociacao', 'paciente', 'tp_paciente',
                'profissional', 'tp_profissional', 'recebimento_caixa', 'tp_recebimento_caixa',
                'relatorios', 'tp_relatorios', 'usuario', 'tp_usuario',
                'parceiro_agenda', 'tp_parceiro_agenda'
            ];
            
            $updateData = [];
            foreach ($allowedModules as $module) {
                if (isset($permissions[$module])) {
                    $updateData[$module] = $permissions[$module];
                }
            }
            
            if (empty($updateData)) {
                $this->response->error('Nenhuma permissão válida para atualização', HTTP_BAD_REQUEST);
            }
            
            // Verificar se já existe registro
            $existing = $this->db->fetchOne("SELECT codusuario FROM usuarios_permissao WHERE codusuario = :id", ['id' => $userId]);
            
            if ($existing) {
                // Atualizar existente
                $this->db->update('usuarios_permissao', $updateData, 'codusuario = :id', ['id' => $userId]);
            } else {
                // Criar novo
                $updateData['codusuario'] = $userId;
                $updateData['data_criacao'] = date('Y-m-d H:i:s');
                $this->db->insert('usuarios_permissao', $updateData);
            }
            
            $this->response->success(null, 'Permissões atualizadas com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro ao atualizar permissões: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Obter módulos disponíveis
     */
    public function getAvailableModules() {
        try {
            $modules = [
                'agenda' => 'Agenda',
                'agenda_medica' => 'Agenda Médica',
                'atendimento' => 'Atendimento',
                'cadastro_tabelas' => 'Cadastro de Tabelas',
                'cirurgia' => 'Cirurgia',
                'configuracao' => 'Configuração',
                'contas_medicas' => 'Contas Médicas',
                'financeiro' => 'Financeiro',
                'gestao_pendentes' => 'Gestão de Pendentes',
                'mapa_cirurgico' => 'Mapa Cirúrgico',
                'negociacao' => 'Negociação',
                'paciente' => 'Paciente',
                'profissional' => 'Profissional',
                'recebimento_caixa' => 'Recebimento de Caixa',
                'relatorios' => 'Relatórios',
                'usuario' => 'Usuário',
                'parceiro_agenda' => 'Parceiro Agenda'
            ];
            
            $this->response->success($modules, 'Módulos carregados com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro ao carregar módulos: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Verificar se usuário tem permissão para módulo
     */
    public function hasPermission($userId, $module) {
        return $this->checkPermission($userId, $module);
    }
}
?>
