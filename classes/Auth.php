<?php
/**
 * Classe de Autenticação
 * Baseada no sistema de login do e-prontu
 */

class Auth {
    private $db;
    private $response;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->response = new Response();
    }
    
    /**
     * Login do usuário
     * Baseado no validacaologin.php
     */
    public function login($email, $password, $unidade) {
        try {
            // Validar dados de entrada
            if (empty($email) || empty($password) || empty($unidade)) {
                $this->response->error('Email, senha e unidade são obrigatórios', HTTP_BAD_REQUEST);
            }
            
            // Normalizar email
            $email = strtolower(trim($email));
            
            // Consultar usuário (baseado na query do validacaologin.php)
            $sql = "SELECT 
                        u.nomeusuario, 
                        TRIM(u.senha) as senha, 
                        u.perfil, 
                        u.status, 
                        u.codusuario,
                        u.unidade, 
                        un.unidades, 
                        u.empresa,
                        TRIM(u.email) as email, 
                        un.logo, 
                        un.lytagenda,
                        u.codmed
                    FROM usuarios u, unidades un
                    WHERE u.email = :email 
                    AND u.senha = :senha 
                    AND u.status = :status 
                    AND u.unidade = :unidade 
                    AND un.codunidades = u.unidade";
            
            $params = [
                'email' => $email,
                'senha' => $password,
                'status' => USER_STATUS_ACTIVE,
                'unidade' => $unidade
            ];
            
            $user = $this->db->fetchOne($sql, $params);
            
            if (!$user) {
                $this->response->error(ERROR_INVALID_CREDENTIALS, HTTP_UNAUTHORIZED);
            }
            
            // Atualizar log de login
            $this->updateLoginLog($user['codusuario']);
            
            // Carregar permissões do usuário
            $permissions = $this->getUserPermissions($user['codusuario']);
            
            // Gerar token JWT
            $token = $this->generateJWT($user);
            
            // Preparar dados de resposta
            $userData = [
                'user' => [
                    'id' => $user['codusuario'],
                    'name' => $user['nomeusuario'],
                    'email' => $user['email'],
                    'profile' => $user['perfil'],
                    'unit' => [
                        'id' => $user['unidade'],
                        'name' => $user['unidades'],
                        'logo' => $user['logo'],
                        'layout' => $user['lytagenda']
                    ],
                    'company' => $user['empresa'],
                    'permissions' => $permissions
                ],
                'token' => $token,
                'expires_in' => JWT_EXPIRATION
            ];
            
            $this->response->auth($userData, 'Login realizado com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro no login: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Validar email do usuário
     * Baseado no validaemail.php
     */
    public function validateEmail($email) {
        try {
            $email = strtolower(trim($email));
            
            $sql = "SELECT DISTINCT(unidade) 
                    FROM usuarios 
                    WHERE email = :email 
                    AND status = :status";
            
            $params = [
                'email' => $email,
                'status' => USER_STATUS_ACTIVE
            ];
            
            $units = $this->db->fetchAll($sql, $params);
            
            if (empty($units)) {
                $this->response->error(ERROR_USER_NOT_FOUND, HTTP_NOT_FOUND);
            }
            
            // Retornar unidades permitidas
            $unitIds = array_column($units, 'unidade');
            $this->response->success([
                'email' => $email,
                'units' => $unitIds
            ], 'Email válido');
            
        } catch (Exception $e) {
            $this->response->error('Erro na validação: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Obter unidades permitidas para o usuário
     * Baseado no getfilterunilogin.php
     */
    public function getUnits($unitIds) {
        try {
            if (empty($unitIds)) {
                $this->response->error('IDs das unidades são obrigatórios', HTTP_BAD_REQUEST);
            }
            
            $placeholders = str_repeat('?,', count($unitIds) - 1) . '?';
            $sql = "SELECT codunidades, unidades 
                    FROM unidades 
                    WHERE ativo = ? 
                    AND codunidades IN ({$placeholders}) 
                    ORDER BY unidades";
            
            $params = array_merge(['S'], $unitIds);
            $units = $this->db->fetchAll($sql, $params);
            
            $this->response->success($units, 'Unidades carregadas com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro ao carregar unidades: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Logout do usuário
     */
    public function logout($token) {
        try {
            // Aqui você pode implementar blacklist de tokens
            // Por enquanto, apenas retorna sucesso
            $this->response->success(null, 'Logout realizado com sucesso');
            
        } catch (Exception $e) {
            $this->response->error('Erro no logout: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Verificar token JWT
     */
    public function verifyToken($token) {
        try {
            if (empty($token)) {
                $this->response->error(ERROR_INVALID_TOKEN, HTTP_UNAUTHORIZED);
            }
            
            // Decodificar token (implementação simples)
            $payload = $this->decodeJWT($token);
            
            if (!$payload) {
                $this->response->error(ERROR_INVALID_TOKEN, HTTP_UNAUTHORIZED);
            }
            
            // Verificar expiração
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                $this->response->error(ERROR_TOKEN_EXPIRED, HTTP_UNAUTHORIZED);
            }
            
            $this->response->success($payload, 'Token válido');
            
        } catch (Exception $e) {
            $this->response->error('Erro na verificação: ' . $e->getMessage(), HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Atualizar log de login
     */
    private function updateLoginLog($userId) {
        $sql = "UPDATE usuarios 
                SET horalogon = :hora, datalogon = :data 
                WHERE codusuario = :id";
        
        $params = [
            'hora' => date('H:i:s'),
            'data' => date('d-m-Y'),
            'id' => $userId
        ];
        
        $this->db->query($sql, $params);
    }
    
    /**
     * Obter permissões do usuário
     */
    private function getUserPermissions($userId) {
        $sql = "SELECT * FROM usuarios_permissao WHERE codusuario = :id";
        $permissions = $this->db->fetchOne($sql, ['id' => $userId]);
        
        return $permissions ?: [];
    }
    
    /**
     * Gerar token JWT (implementação simples)
     */
    private function generateJWT($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => JWT_ALGORITHM]);
        $payload = json_encode([
            'user_id' => $user['codusuario'],
            'email' => $user['email'],
            'unit_id' => $user['unidade'],
            'iat' => time(),
            'exp' => time() + JWT_EXPIRATION
        ]);
        
        $headerEncoded = base64url_encode($header);
        $payloadEncoded = base64url_encode($payload);
        
        $signature = hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, JWT_SECRET, true);
        $signatureEncoded = base64url_encode($signature);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * Decodificar token JWT
     */
    private function decodeJWT($token) {
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        $header = json_decode(base64url_decode($parts[0]), true);
        $payload = json_decode(base64url_decode($parts[1]), true);
        $signature = base64url_decode($parts[2]);
        
        // Verificar assinatura
        $expectedSignature = hash_hmac('sha256', $parts[0] . '.' . $parts[1], JWT_SECRET, true);
        
        if (!hash_equals($signature, $expectedSignature)) {
            return false;
        }
        
        return $payload;
    }
}

/**
 * Funções auxiliares para JWT
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}
?>
