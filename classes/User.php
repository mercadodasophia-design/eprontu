<?php
/**
 * Classe de Gerenciamento de Usuários
 */

class User {
    private $db;
    private $response;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->response = new Response();
    }
    
    /**
     * Obter perfil do usuário
     */
    public function getProfile($userId) {
        try {
            $sql = "SELECT 
                        u.codusuario,
                        u.nomeusuario,
                        u.email,
                        u.perfil,
                        u.status,
                        u.unidade,
                        un.unidades as unidade_nome,
                        u.empresa,
                        u.horalogon,
                        u.datalogon
                    FROM usuarios u
                    LEFT JOIN unidades un ON u.unidade = un.codunidades
                    WHERE u.codusuario = :id";
            
            $user = $this->db->fetchOne($sql, ['id' => $userId]);
            
            if (!$user) {
                $this->response->error(ERROR_USER_NOT_FOUND, HTTP_NOT_FOUND);
            }
            
            // Carregar permissões
            $permissions = $this->getUserPermissions($userId);
            $user['permissions'] = $permissions;
            
            $this->response->success($user, 'Perfil carregado com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro ao carregar perfil: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Atualizar perfil do usuário
     */
    public function updateProfile($userId, $data) {
        try {
            // Validar dados
            $allowedFields = ['nomeusuario', 'email'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            if (empty($updateData)) {
                $this->response->error('Nenhum campo válido para atualização', HTTP_BAD_REQUEST);
            }
            
            // Atualizar no banco
            $this->db->update('usuarios', $updateData, 'codusuario = :id', ['id' => $userId]);
            
            $this->response->success(null, 'Perfil atualizado com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro ao atualizar perfil: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Alterar senha do usuário
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Verificar senha atual
            $sql = "SELECT senha FROM usuarios WHERE codusuario = :id";
            $user = $this->db->fetchOne($sql, ['id' => $userId]);
            
            if (!$user || $user['senha'] !== $currentPassword) {
                $this->response->error('Senha atual incorreta', HTTP_BAD_REQUEST);
            }
            
            // Atualizar senha
            $this->db->update('usuarios', ['senha' => $newPassword], 'codusuario = :id', ['id' => $userId]);
            
            $this->response->success(null, 'Senha alterada com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro ao alterar senha: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Obter permissões do usuário
     */
    public function getPermissions($userId) {
        try {
            $permissions = $this->getUserPermissions($userId);
            $this->response->success($permissions, 'Permissões carregadas com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro ao carregar permissões: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Obter usuários da unidade
     */
    public function getUsersByUnit($unitId) {
        try {
            $sql = "SELECT 
                        u.codusuario,
                        u.nomeusuario,
                        u.email,
                        u.perfil,
                        u.status,
                        u.horalogon,
                        u.datalogon
                    FROM usuarios u
                    WHERE u.unidade = :unit_id
                    ORDER BY u.nomeusuario";
            
            $users = $this->db->fetchAll($sql, ['unit_id' => $unitId]);
            
            $this->response->success($users, 'Usuários carregados com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro ao carregar usuários: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Obter permissões do usuário (método privado)
     */
    private function getUserPermissions($userId) {
        $sql = "SELECT * FROM usuarios_permissao WHERE codusuario = :id";
        $permissions = $this->db->fetchOne($sql, ['id' => $userId]);
        
        return $permissions ?: [];
    }
}
?>
