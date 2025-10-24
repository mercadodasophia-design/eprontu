<?php
/**
 * Constantes da API
 */

// Configurações da API
define('API_VERSION', '1.0.0');
define('API_NAME', 'e-prontu API');

// Configurações de autenticação
define('JWT_SECRET', 'eprontu_jwt_secret_key_2024');
define('JWT_ALGORITHM', 'HS256');
define('JWT_EXPIRATION', 3600); // 1 hora

// Configurações de sessão
define('SESSION_TIMEOUT', 7200); // 2 horas

// Status de usuário
define('USER_STATUS_ACTIVE', 'A');
define('USER_STATUS_INACTIVE', 'I');

// Perfis de usuário
define('USER_PROFILE_ADMIN', 'A');
define('USER_PROFILE_DOCTOR', 'M');
define('USER_PROFILE_SECRETARY', 'S');

// Status de agendamento
define('APPOINTMENT_STATUS_RESERVED', 'P');
define('APPOINTMENT_STATUS_CONFIRMED', 'V');
define('APPOINTMENT_STATUS_ARRIVED', 'C');
define('APPOINTMENT_STATUS_RELEASED', 'L');
define('APPOINTMENT_STATUS_IN_PROGRESS', 'T');
define('APPOINTMENT_STATUS_COMPLETED', 'A');
define('APPOINTMENT_STATUS_CANCELLED', 'I');

// Códigos de resposta HTTP
define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_INTERNAL_SERVER_ERROR', 500);

// Mensagens de erro
define('ERROR_INVALID_CREDENTIALS', 'Credenciais inválidas');
define('ERROR_USER_NOT_FOUND', 'Usuário não encontrado');
define('ERROR_USER_INACTIVE', 'Usuário inativo');
define('ERROR_INVALID_TOKEN', 'Token inválido');
define('ERROR_TOKEN_EXPIRED', 'Token expirado');
define('ERROR_INSUFFICIENT_PERMISSIONS', 'Permissões insuficientes');
define('ERROR_VALIDATION_FAILED', 'Falha na validação dos dados');
define('ERROR_DATABASE_ERROR', 'Erro no banco de dados');
?>
