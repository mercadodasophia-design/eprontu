<?php
/**
 * Classe para padronização de respostas da API
 */

class Response {
    
    /**
     * Resposta de sucesso
     */
    public function success($data = null, $message = 'Sucesso', $code = HTTP_OK) {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code($code);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Resposta de erro
     */
    public function error($message = 'Erro', $code = HTTP_BAD_REQUEST, $details = null) {
        $response = [
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code($code);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Resposta de validação
     */
    public function validation($errors, $message = 'Dados inválidos') {
        $response = [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(HTTP_BAD_REQUEST);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Resposta de autenticação
     */
    public function auth($data = null, $message = 'Autenticado com sucesso') {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(HTTP_OK);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    
    /**
     * Resposta de permissão negada
     */
    public function forbidden($message = 'Acesso negado') {
        $response = [
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => HTTP_FORBIDDEN,
                'message' => $message
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(HTTP_FORBIDDEN);
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
}
?>
