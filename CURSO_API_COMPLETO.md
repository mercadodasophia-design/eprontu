# 🎓 Curso Completo: API e-prontu PHP

**Guia completo para entender e desenvolver na API e-prontu**

---

## 📚 Índice do Curso

1. [Introdução](#1-introdução)
2. [Arquitetura e Estrutura](#2-arquitetura-e-estrutura)
3. [Configuração e Setup](#3-configuração-e-setup)
4. [Sistema de Roteamento](#4-sistema-de-roteamento)
5. [Classes Principais](#5-classes-principais)
6. [Banco de Dados](#6-banco-de-dados)
7. [Autenticação e Segurança](#7-autenticação-e-segurança)
8. [Criando Novos Endpoints](#8-criando-novos-endpoints)
9. [Deploy e Produção](#9-deploy-e-produção)
10. [Boas Práticas](#10-boas-práticas)
11. [Exercícios Práticos](#11-exercícios-práticos)

---

## 1. Introdução

### O que é esta API?

A **API e-prontu** é uma API REST desenvolvida em PHP para o sistema de gestão médica e-prontu. Ela fornece endpoints para:

- ✅ Autenticação de usuários
- ✅ Gerenciamento de campanhas
- ✅ Interações com pacientes
- ✅ Atendimentos médicos
- ✅ Bobina (histórico médico)
- ✅ Dashboard e relatórios

### Tecnologias Utilizadas

- **PHP 8.2+**: Linguagem principal
- **PostgreSQL**: Banco de dados
- **Apache**: Servidor web
- **Docker**: Containerização
- **Google Cloud Run**: Hospedagem em produção
- **JWT**: Autenticação via tokens

### Estrutura de Pastas

```
e-prontu-php/api/
├── index.php              # Ponto de entrada principal
├── config/                # Configurações
│   ├── database.php       # Conexão com banco
│   ├── server_config.php  # Configurações do servidor
│   └── constants.php      # Constantes da aplicação
├── classes/               # Classes principais
│   ├── Response.php       # Padronização de respostas
│   ├── Auth.php           # Autenticação
│   ├── User.php           # Gerenciamento de usuários
│   └── Permission.php     # Controle de permissões
├── routes/                # Rotas da API
│   ├── auth.php           # Rotas de autenticação
│   ├── campanhas.php      # Rotas de campanhas
│   ├── campanhas_interacoes.php
│   └── ...
├── composer.json          # Dependências PHP
├── Dockerfile            # Configuração Docker
└── deploy.sh            # Script de deploy
```

---

## 2. Arquitetura e Estrutura

### Fluxo de Requisição

```
Cliente (Flutter/Web)
    ↓
HTTP Request (GET/POST/PUT/DELETE)
    ↓
index.php (Roteador Principal)
    ↓
Configurações (CORS, Database, Constants)
    ↓
Switch/Case (Roteamento por Endpoint)
    ↓
routes/{endpoint}.php
    ↓
Classes (Response, Auth, Database)
    ↓
Banco de Dados (PostgreSQL)
    ↓
Resposta JSON Padronizada
    ↓
Cliente
```

### Componentes Principais

#### 1. **index.php** - Roteador Central

O arquivo `index.php` é o ponto de entrada de todas as requisições. Ele:

- Configura headers CORS
- Trata requisições OPTIONS
- Carrega configurações
- Instancia classes principais
- Parse da URI
- Roteia para arquivos específicos em `routes/`

#### 2. **config/** - Configurações

Contém todas as configurações da aplicação:

- **database.php**: Singleton para conexão PostgreSQL
- **server_config.php**: CORS, debug, funções auxiliares
- **constants.php**: Constantes (HTTP codes, status, perfis)

#### 3. **classes/** - Classes Reutilizáveis

Classes que encapsulam lógica comum:

- **Response**: Padroniza todas as respostas JSON
- **Auth**: Gerencia autenticação e tokens JWT
- **Database**: Abstração para operações no banco

#### 4. **routes/** - Endpoints Específicos

Cada arquivo em `routes/` representa um grupo de endpoints relacionados.

---

## 3. Configuração e Setup

### Requisitos

- PHP 8.2 ou superior
- PostgreSQL
- Apache (ou servidor compatível)
- Composer (opcional)
- Docker (para deploy)

### Instalação Local

#### 1. Clonar/Copiar arquivos

```bash
cd e-prontu-php/api
```

#### 2. Configurar Banco de Dados

Edite `config/database.php`:

```php
private $host = 'localhost';        // Seu host PostgreSQL
private $port = '5432';              // Porta padrão
private $dbname = 'bioclinica';      // Nome do banco
private $username = 'seu_usuario';   // Seu usuário
private $password = 'sua_senha';    // Sua senha
```

#### 3. Configurar Servidor

Edite `config/server_config.php` se necessário:

```php
define('CORS_ORIGIN', '*');  // Ou domínio específico
define('DEBUG_MODE', true);  // false em produção
```

#### 4. Testar Conexão

Acesse: `http://localhost/e-prontu/api/config/test_connection.php`

### Estrutura de Configuração

#### database.php - Singleton Pattern

```php
class Database {
    private static $instance = null;
    private $connection;
    
    // Construtor privado (Singleton)
    private function __construct() {
        // Conecta ao PostgreSQL
    }
    
    // Método estático para obter instância
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

**Por que Singleton?**
- Garante apenas uma conexão com o banco
- Economiza recursos
- Facilita gerenciamento

**Uso:**
```php
$db = Database::getInstance();
$users = $db->fetchAll("SELECT * FROM usuarios", []);
```

#### server_config.php - CORS e Helpers

```php
// Configura CORS para permitir requisições do Flutter
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// Trata requisições OPTIONS (preflight)
function handleOptionsRequest() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        setCorsHeaders();
        http_response_code(200);
        exit();
    }
}
```

#### constants.php - Constantes Globais

```php
// Códigos HTTP
define('HTTP_OK', 200);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);

// Status de usuário
define('USER_STATUS_ACTIVE', 'A');
define('USER_STATUS_INACTIVE', 'I');

// Perfis
define('USER_PROFILE_ADMIN', 'A');
define('USER_PROFILE_DOCTOR', 'M');
define('USER_PROFILE_SECRETARY', 'S');
```

---

## 4. Sistema de Roteamento

### Como Funciona o Roteamento

O `index.php` analisa a URI e roteia para o arquivo correto em `routes/`.

#### Exemplo de URI

```
https://api.exemplo.com/api/campanhas/listar
```

**Parse da URI:**

```php
// URI original: /api/campanhas/listar
$uri = $_SERVER['REQUEST_URI'];  // "/api/campanhas/listar"
$uri = strtok($uri, '?');        // Remove query string
$segments = explode('/', trim($uri, '/'));  // ['api', 'campanhas', 'listar']

// Remove prefixos
if ($segments[0] === 'api') array_shift($segments);  // ['campanhas', 'listar']

$endpoint = $segments[0];  // 'campanhas'
$action = $segments[1];   // 'listar'
```

#### Switch de Roteamento

```php
switch ($endpoint) {
    case 'auth':
        require_once 'routes/auth.php';
        break;
        
    case 'campanhas':
        // Verifica se é interações
        if ($segments[1] === 'interacoes') {
            require_once 'routes/campanhas_interacoes.php';
        } else {
            require_once 'routes/campanhas.php';
        }
        break;
        
    case 'dashboard':
        require_once 'routes/dashboard.php';
        break;
        
    default:
        $response->error('Endpoint não encontrado', 404);
}
```

### Variáveis Compartilhadas

O `index.php` define variáveis que são compartilhadas com os arquivos de rota:

```php
// No index.php
$method = $_SERVER['REQUEST_METHOD'];  // GET, POST, PUT, DELETE
$segments = [...];                      // Array de segmentos da URI
$endpoint = 'campanhas';                // Primeiro segmento
$action = 'listar';                     // Segundo segmento
$response = new Response();              // Instância de Response

// No routes/campanhas.php
// Essas variáveis já estão disponíveis!
if ($action === 'listar' && $method === 'GET') {
    // ...
}
```

### Rotas Aninhadas

Para rotas como `/api/campanhas/interacoes/salvar`:

```php
// No index.php
case 'campanhas':
    $campanhaAction = $segments[1] ?? '';
    
    if ($campanhaAction === 'interacoes') {
        // A ação específica está em $segments[2]
        require_once 'routes/campanhas_interacoes.php';
    } else {
        // Ação normal em $segments[1]
        require_once 'routes/campanhas.php';
    }
    break;
```

---

## 5. Classes Principais

### Response.php - Padronização de Respostas

A classe `Response` garante que todas as respostas sigam o mesmo formato.

#### Métodos Disponíveis

```php
class Response {
    // Sucesso
    public function success($data = null, $message = 'Sucesso', $code = 200);
    
    // Erro
    public function error($message = 'Erro', $code = 400, $details = null);
    
    // Validação
    public function validation($errors, $message = 'Dados inválidos');
    
    // Autenticação
    public function auth($data = null, $message = 'Autenticado');
    
    // Permissão negada
    public function forbidden($message = 'Acesso negado');
}
```

#### Formato de Resposta

**Sucesso:**
```json
{
  "success": true,
  "message": "Operação realizada com sucesso",
  "data": { ... },
  "timestamp": "2024-01-15 10:30:00"
}
```

**Erro:**
```json
{
  "success": false,
  "message": "Erro na operação",
  "error": {
    "code": 400,
    "message": "Erro na operação",
    "details": null
  },
  "timestamp": "2024-01-15 10:30:00"
}
```

#### Exemplo de Uso

```php
$response = new Response();

// Sucesso
$response->success(['id' => 123], 'Campanha criada com sucesso');

// Erro
$response->error('Campanha não encontrada', 404);

// Validação
$response->validation([
    'email' => 'Email é obrigatório',
    'senha' => 'Senha deve ter no mínimo 6 caracteres'
]);
```

### Auth.php - Autenticação

Gerencia login, tokens JWT e validação de usuários.

#### Métodos Principais

```php
class Auth {
    // Login
    public function login($email, $password, $unidade);
    
    // Validar email
    public function validateEmail($email);
    
    // Obter unidades
    public function getUnits($unitIds);
    
    // Verificar token
    public function verifyToken($token);
    
    // Logout
    public function logout($token);
}
```

#### Fluxo de Login

```php
// 1. Validar email
POST /api/auth/validate-email
{
  "email": "usuario@exemplo.com"
}

// Resposta:
{
  "success": true,
  "data": {
    "email": "usuario@exemplo.com",
    "units": ["1", "2", "3"]
  }
}

// 2. Obter unidades
POST /api/auth/get-units
{
  "unit_ids": ["1", "2"]
}

// Resposta:
{
  "success": true,
  "data": [
    {"codunidades": "1", "unidades": "Unidade Centro"},
    {"codunidades": "2", "unidades": "Unidade Norte"}
  ]
}

// 3. Login
POST /api/auth/login
{
  "email": "usuario@exemplo.com",
  "password": "senha123",
  "unit": "1"
}

// Resposta:
{
  "success": true,
  "data": {
    "user": { ... },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600
  }
}
```

#### JWT (JSON Web Token)

A API usa JWT para autenticação. O token contém:

```json
{
  "user_id": "123",
  "email": "usuario@exemplo.com",
  "unit_id": "1",
  "iat": 1633648000,
  "exp": 1633651600
}
```

**Estrutura do Token:**
```
header.payload.signature
```

**Geração:**
```php
private function generateJWT($user) {
    $header = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    $payload = base64url_encode(json_encode([
        'user_id' => $user['codusuario'],
        'email' => $user['email'],
        'iat' => time(),
        'exp' => time() + 3600
    ]));
    $signature = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    
    return "$header.$payload.$signature";
}
```

### Database.php - Abstração do Banco

Facilita operações no PostgreSQL com métodos simplificados.

#### Métodos Disponíveis

```php
class Database {
    // Query genérica
    public function query($sql, $params = []);
    
    // Buscar todos
    public function fetchAll($sql, $params = []);
    
    // Buscar um
    public function fetchOne($sql, $params = []);
    
    // Insert
    public function insert($table, $data);
    
    // Update
    public function update($table, $data, $where, $whereParams = []);
    
    // Delete
    public function delete($table, $where, $params = []);
}
```

#### Exemplos de Uso

```php
$db = Database::getInstance();

// SELECT - Buscar todos
$users = $db->fetchAll(
    "SELECT * FROM usuarios WHERE status = ?",
    ['A']
);

// SELECT - Buscar um
$user = $db->fetchOne(
    "SELECT * FROM usuarios WHERE codusuario = ?",
    ['123']
);

// INSERT
$id = $db->insert('campanhas', [
    'name' => 'Campanha Teste',
    'description' => 'Descrição',
    'canal' => 'whatsapp'
]);

// UPDATE
$db->update(
    'campanhas',
    ['name' => 'Novo Nome'],
    'id = ?',
    ['cmp_123']
);

// DELETE
$db->delete('campanhas', 'id = ?', ['cmp_123']);
```

#### Prepared Statements

Todos os métodos usam **prepared statements** para prevenir SQL Injection:

```php
// ✅ SEGURO - Usa prepared statement
$db->fetchAll("SELECT * FROM usuarios WHERE email = ?", [$email]);

// ❌ PERIGOSO - Vulnerável a SQL Injection
$db->fetchAll("SELECT * FROM usuarios WHERE email = '$email'");
```

---

## 6. Banco de Dados

### Estrutura PostgreSQL

A API usa **PostgreSQL** como banco de dados. Principais características:

- Suporte a JSONB (para campos JSON)
- Transações ACID
- Performance otimizada
- Extensões úteis

### Conexão

```php
// config/database.php
private $host = '34.151.218.50';
private $port = '5432';
private $dbname = 'bioclinica_teste';
private $username = 'redebioclinica';
private $password = '061yfmtx7obwzkk';

$dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
$this->connection = new PDO($dsn, $this->username, $this->password);
```

### Tabelas Principais

#### usuarios
```sql
CREATE TABLE usuarios (
    codusuario TEXT PRIMARY KEY,
    nomeusuario TEXT NOT NULL,
    email TEXT UNIQUE,
    senha TEXT,
    perfil TEXT,  -- 'A', 'M', 'S'
    status TEXT,  -- 'A', 'I'
    unidade TEXT,
    empresa TEXT
);
```

#### campanhas
```sql
CREATE TABLE campanhas (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    description TEXT,
    canal TEXT,
    leads_count INTEGER DEFAULT 0,
    responsaveis JSONB,  -- Array de responsáveis
    mailigs JSONB,       -- Array de mailings
    archived BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW()
);
```

#### mailing_interacoes
```sql
CREATE TABLE mailing_interacoes (
    id SERIAL PRIMARY KEY,
    mailing_id INTEGER NOT NULL,
    campanha_id TEXT,
    paciente_id INTEGER NOT NULL,
    anotacoes TEXT,
    clips JSONB DEFAULT '[]',
    data_interacao TIMESTAMP DEFAULT NOW()
);
```

### JSONB - Campos JSON

PostgreSQL suporta JSONB para armazenar dados JSON de forma eficiente:

```php
// Inserir com JSONB
$db->query(
    "INSERT INTO campanhas (id, name, responsaveis) VALUES (?, ?, ?::jsonb)",
    ['cmp_123', 'Campanha', json_encode([['id' => '1', 'nome' => 'João']])]
);

// Buscar e usar JSONB
$campanha = $db->fetchOne("SELECT * FROM campanhas WHERE id = ?", ['cmp_123']);
$responsaveis = json_decode($campanha['responsaveis'], true);
```

---

## 7. Autenticação e Segurança

### Fluxo de Autenticação

```
1. Cliente envia email
   ↓
2. API valida email e retorna unidades
   ↓
3. Cliente seleciona unidade
   ↓
4. Cliente envia email + senha + unidade
   ↓
5. API valida credenciais
   ↓
6. API gera token JWT
   ↓
7. API retorna token + dados do usuário
   ↓
8. Cliente armazena token
   ↓
9. Cliente envia token em requisições futuras
```

### Validação de Token

```php
// No início de rotas protegidas
$headers = getallheaders();
$token = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $token);

$auth = new Auth();
$payload = $auth->verifyToken($token);

if (!$payload) {
    $response->error('Token inválido', 401);
}

// Usar dados do token
$userId = $payload['user_id'];
```

### CORS (Cross-Origin Resource Sharing)

Configurado em `server_config.php`:

```php
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');  // Permite qualquer origem
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}
```

**⚠️ Em produção, use origem específica:**
```php
header('Access-Control-Allow-Origin: https://seu-dominio.com');
```

### SQL Injection Prevention

Sempre use **prepared statements**:

```php
// ✅ SEGURO
$db->fetchAll("SELECT * FROM usuarios WHERE email = ?", [$email]);

// ❌ VULNERÁVEL
$db->fetchAll("SELECT * FROM usuarios WHERE email = '$email'");
```

### Validação de Entrada

Sempre valide dados de entrada:

```php
$input = json_decode(file_get_contents('php://input'), true);

// Validar campos obrigatórios
if (empty($input['email'])) {
    $response->error('Email é obrigatório', 400);
}

// Validar formato
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $response->error('Email inválido', 400);
}

// Validar tipo
if (!is_numeric($input['id'])) {
    $response->error('ID deve ser numérico', 400);
}
```

---

## 8. Criando Novos Endpoints

### Passo a Passo

Vamos criar um endpoint para gerenciar **produtos**:

#### 1. Criar arquivo de rota

Crie `routes/produtos.php`:

```php
<?php
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../config/database.php';

$response = new Response();
$db = Database::getInstance();

// Usar variáveis do index.php
$action = $action ?? '';

try {
    switch ($action) {
        case 'listar':
            // GET /api/produtos/listar
            if ($method !== 'GET') {
                $response->error('Método não suportado', 405);
            }
            
            $produtos = $db->fetchAll("SELECT * FROM produtos ORDER BY nome");
            $response->success($produtos, 'Produtos listados com sucesso');
            break;
            
        case 'criar':
            // POST /api/produtos/criar
            if ($method !== 'POST') {
                $response->error('Método não suportado', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validação
            if (empty($input['nome'])) {
                $response->error('Nome é obrigatório', 400);
            }
            
            // Inserir
            $id = $db->insert('produtos', [
                'nome' => $input['nome'],
                'preco' => $input['preco'] ?? 0,
                'descricao' => $input['descricao'] ?? ''
            ]);
            
            $response->success(['id' => $id], 'Produto criado com sucesso', 201);
            break;
            
        case 'atualizar':
            // PUT /api/produtos/atualizar/{id}
            if ($method !== 'PUT') {
                $response->error('Método não suportado', 405);
            }
            
            $id = $segments[2] ?? '';
            if (empty($id)) {
                $response->error('ID é obrigatório', 400);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Atualizar
            $db->update(
                'produtos',
                [
                    'nome' => $input['nome'] ?? null,
                    'preco' => $input['preco'] ?? null
                ],
                'id = ?',
                [$id]
            );
            
            $response->success(null, 'Produto atualizado com sucesso');
            break;
            
        case 'deletar':
            // DELETE /api/produtos/deletar/{id}
            if ($method !== 'DELETE') {
                $response->error('Método não suportado', 405);
            }
            
            $id = $segments[2] ?? '';
            if (empty($id)) {
                $response->error('ID é obrigatório', 400);
            }
            
            $db->delete('produtos', 'id = ?', [$id]);
            $response->success(null, 'Produto deletado com sucesso');
            break;
            
        default:
            $response->error('Ação não encontrada', 404);
    }
} catch (Exception $e) {
    $response->error('Erro: ' . $e->getMessage(), 500);
}
?>
```

#### 2. Adicionar rota no index.php

```php
// No switch do index.php
case 'produtos':
    require_once 'routes/produtos.php';
    break;
```

#### 3. Testar

```bash
# Listar
curl http://localhost/api/produtos/listar

# Criar
curl -X POST http://localhost/api/produtos/criar \
  -H "Content-Type: application/json" \
  -d '{"nome": "Produto Teste", "preco": 99.90}'

# Atualizar
curl -X PUT http://localhost/api/produtos/atualizar/123 \
  -H "Content-Type: application/json" \
  -d '{"nome": "Novo Nome"}'

# Deletar
curl -X DELETE http://localhost/api/produtos/deletar/123
```

### Padrão de Rota

Siga este padrão para consistência:

```php
<?php
// 1. Incluir dependências
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../config/database.php';

// 2. Instanciar classes
$response = new Response();
$db = Database::getInstance();

// 3. Obter ação
$action = $action ?? '';

// 4. Try-catch principal
try {
    switch ($action) {
        case 'acao1':
            // Validar método
            if ($method !== 'GET') {
                $response->error('Método não suportado', 405);
            }
            
            // Lógica aqui
            break;
            
        default:
            $response->error('Ação não encontrada', 404);
    }
} catch (Exception $e) {
    $response->error('Erro: ' . $e->getMessage(), 500);
}
?>
```

---

## 9. Deploy e Produção

### Google Cloud Run

A API está configurada para deploy no **Google Cloud Run**.

#### Dockerfile

```dockerfile
FROM php:8.2-apache

# Instalar extensões
RUN docker-php-ext-install pdo pdo_pgsql

# Copiar arquivos
COPY . /var/www/html

# Configurar Apache para porta 8080 (requisito Cloud Run)
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
```

#### Deploy Automático

```bash
# 1. Login no Google Cloud
gcloud auth login

# 2. Configurar projeto
gcloud config set project SEU_PROJECT_ID

# 3. Executar script de deploy
chmod +x deploy.sh
./deploy.sh
```

#### deploy.sh

```bash
#!/bin/bash
PROJECT_ID="seu-project-id"
IMAGE_NAME="gcr.io/$PROJECT_ID/e-prontu-api"

# Build
docker build -t $IMAGE_NAME .

# Push
docker push $IMAGE_NAME

# Deploy
gcloud run deploy e-prontu-api \
  --image $IMAGE_NAME \
  --region us-central1 \
  --platform managed \
  --allow-unauthenticated \
  --port 8080
```

### Variáveis de Ambiente

Para produção, use variáveis de ambiente:

```php
// config/database.php
private $host = getenv('DB_HOST') ?: 'localhost';
private $dbname = getenv('DB_NAME') ?: 'bioclinica';
private $username = getenv('DB_USER') ?: 'usuario';
private $password = getenv('DB_PASS') ?: 'senha';
```

### Logs

Ver logs no Cloud Run:

```bash
gcloud run logs read e-prontu-api --region us-central1
```

---

## 10. Boas Práticas

### 1. Sempre Use Prepared Statements

```php
// ✅ BOM
$db->fetchAll("SELECT * FROM usuarios WHERE email = ?", [$email]);

// ❌ RUIM
$db->fetchAll("SELECT * FROM usuarios WHERE email = '$email'");
```

### 2. Valide Sempre os Dados de Entrada

```php
$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['campo_obrigatorio'])) {
    $response->error('Campo obrigatório faltando', 400);
}

if (!is_numeric($input['id'])) {
    $response->error('ID inválido', 400);
}
```

### 3. Use Try-Catch para Erros

```php
try {
    $result = $db->fetchAll($sql, $params);
} catch (Exception $e) {
    $response->error('Erro no banco: ' . $e->getMessage(), 500);
}
```

### 4. Padronize Respostas

Sempre use a classe `Response`:

```php
// ✅ BOM
$response->success($data, 'Operação realizada');

// ❌ RUIM
echo json_encode(['status' => 'ok', 'data' => $data]);
```

### 5. Documente Seu Código

```php
/**
 * Lista todas as campanhas
 * 
 * @param int $offset Offset para paginação
 * @param int $limit Limite de resultados
 * @return array Lista de campanhas
 */
public function listarCampanhas($offset = 0, $limit = 20) {
    // ...
}
```

### 6. Use Constantes

```php
// ✅ BOM
if ($user['status'] === USER_STATUS_ACTIVE) {
    // ...
}

// ❌ RUIM
if ($user['status'] === 'A') {
    // ...
}
```

### 7. Trate Requisições OPTIONS

O `server_config.php` já trata, mas certifique-se de que está funcionando para CORS.

### 8. Logs em Desenvolvimento

```php
if (DEBUG_MODE) {
    error_log("Campanha criada: " . json_encode($data));
}
```

---

## 11. Exercícios Práticos

### Exercício 1: Endpoint de Especialidades

Crie um endpoint que lista especialidades médicas.

**Requisitos:**
- GET `/api/especialidades`
- Retornar todas as especialidades ordenadas por nome
- Usar tabela `especialidades` (campo `codespecialidade` e `especialidade`)

**Solução:**

```php
// No index.php, adicionar:
case 'especialidades':
    require_once 'routes/especialidades.php';
    break;

// Criar routes/especialidades.php:
<?php
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../config/database.php';

$response = new Response();
$db = Database::getInstance();

try {
    if ($method === 'GET') {
        $especialidades = $db->fetchAll(
            "SELECT codespecialidade, especialidade FROM especialidades ORDER BY especialidade",
            []
        );
        $response->success($especialidades, 'Especialidades listadas');
    } else {
        $response->error('Método não permitido', 405);
    }
} catch (Exception $e) {
    $response->error('Erro: ' . $e->getMessage(), 500);
}
?>
```

### Exercício 2: Endpoint com Autenticação

Crie um endpoint protegido que retorna dados do usuário logado.

**Requisitos:**
- GET `/api/users/me`
- Requer token JWT válido
- Retorna dados do usuário autenticado

**Solução:**

```php
// routes/users.php
case 'me':
    if ($method !== 'GET') {
        $response->error('Método não permitido', 405);
    }
    
    // Verificar token
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    
    $auth = new Auth();
    $payload = $auth->verifyToken($token);
    
    if (!$payload) {
        $response->error('Token inválido', 401);
    }
    
    // Buscar usuário
    $user = $db->fetchOne(
        "SELECT * FROM usuarios WHERE codusuario = ?",
        [$payload['user_id']]
    );
    
    $response->success($user, 'Dados do usuário');
    break;
```

### Exercício 3: Endpoint com Validação Completa

Crie um endpoint para criar pacientes com validação completa.

**Requisitos:**
- POST `/api/patients`
- Validar: nome, CPF (formato), data de nascimento
- Retornar erros de validação detalhados

**Solução:**

```php
case 'criar':
    if ($method !== 'POST') {
        $response->error('Método não permitido', 405);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $errors = [];
    
    // Validações
    if (empty($input['nome'])) {
        $errors['nome'] = 'Nome é obrigatório';
    }
    
    if (empty($input['cpf'])) {
        $errors['cpf'] = 'CPF é obrigatório';
    } elseif (!preg_match('/^\d{11}$/', $input['cpf'])) {
        $errors['cpf'] = 'CPF deve ter 11 dígitos';
    }
    
    if (empty($input['datanascimento'])) {
        $errors['datanascimento'] = 'Data de nascimento é obrigatória';
    } elseif (!strtotime($input['datanascimento'])) {
        $errors['datanascimento'] = 'Data inválida';
    }
    
    if (!empty($errors)) {
        $response->validation($errors);
    }
    
    // Inserir
    $id = $db->insert('paciente', [
        'nomepaciente' => $input['nome'],
        'cpf' => $input['cpf'],
        'datanascimento' => $input['datanascimento']
    ]);
    
    $response->success(['id' => $id], 'Paciente criado', 201);
    break;
```

---

## 📝 Resumo Final

### Checklist para Criar um Endpoint

- [ ] Criar arquivo em `routes/`
- [ ] Incluir dependências (Response, Database)
- [ ] Adicionar rota no `index.php`
- [ ] Validar método HTTP
- [ ] Validar dados de entrada
- [ ] Usar prepared statements
- [ ] Tratar erros com try-catch
- [ ] Retornar resposta padronizada
- [ ] Testar endpoint
- [ ] Documentar

### Estrutura de um Endpoint Completo

```php
<?php
// 1. Dependências
require_once __DIR__ . '/../classes/Response.php';
require_once __DIR__ . '/../config/database.php';

// 2. Instâncias
$response = new Response();
$db = Database::getInstance();

// 3. Obter ação
$action = $action ?? '';

// 4. Lógica
try {
    switch ($action) {
        case 'acao':
            // Validar método
            if ($method !== 'GET') {
                $response->error('Método não permitido', 405);
            }
            
            // Validar entrada
            // Processar
            // Retornar resposta
            break;
            
        default:
            $response->error('Ação não encontrada', 404);
    }
} catch (Exception $e) {
    $response->error('Erro: ' . $e->getMessage(), 500);
}
?>
```

---

## 🎯 Próximos Passos

1. **Explorar endpoints existentes** em `routes/`
2. **Ler código de referência** (campanhas.php, auth.php)
3. **Criar endpoints de teste**
4. **Implementar autenticação** em endpoints protegidos
5. **Aprender sobre testes** (PHPUnit)
6. **Otimizar queries** (índices, joins)

---

## 📚 Recursos Adicionais

- [Documentação PHP](https://www.php.net/docs.php)
- [PostgreSQL Docs](https://www.postgresql.org/docs/)
- [PDO Manual](https://www.php.net/manual/pt_BR/book.pdo.php)
- [JWT.io](https://jwt.io/) - Debug de tokens JWT
- [Google Cloud Run Docs](https://cloud.google.com/run/docs)

---

**Boa sorte no desenvolvimento! 🚀**

**Última atualização:** 2024  
**Versão da API:** 1.0.0

