# E-Prontu API - Google Cloud Run

API PHP para o sistema E-Prontu, hospedada no Google Cloud Run.

## 🚀 Deploy Rápido

### Pré-requisitos:
- Google Cloud CLI instalado
- Docker instalado
- Conta Google Cloud ativa

### 1. Login no Google Cloud:
```bash
gcloud auth login
```

### 2. Configurar projeto:
```bash
gcloud config set project SEU_PROJECT_ID
```

### 3. Deploy automático:
```bash
chmod +x deploy.sh
./deploy.sh
```

## 🔧 Deploy Manual

### 1. Build da imagem:
```bash
docker build -t gcr.io/SEU_PROJECT_ID/e-prontu-api .
```

### 2. Push para Container Registry:
```bash
docker push gcr.io/SEU_PROJECT_ID/e-prontu-api
```

### 3. Deploy no Cloud Run:
```bash
gcloud run deploy e-prontu-api \
  --image gcr.io/SEU_PROJECT_ID/e-prontu-api \
  --region us-central1 \
  --platform managed \
  --allow-unauthenticated \
  --port 8080
```

## 📋 Configurações

- **Porta**: 8080 (requisito do Cloud Run)
- **Memória**: 512Mi
- **CPU**: 1 vCPU
- **Máx. instâncias**: 10
- **Timeout**: 300s

## 🌐 URL da API

Após o deploy, a API estará disponível em:
```
https://e-prontu-api-SEU_PROJECT_ID.a.run.app
```

## 📝 Uso

### Endpoint: POST /
```json
{
  "filtros": {
    "prontuario": "185355",
    "especialidade": "1"
  }
}
```

### Resposta:
```json
{
  "status": "sucesso",
  "dados": {
    "dados": [...]
  }
}
```

## 🔍 Logs

Para ver os logs:
```bash
gcloud run logs read e-prontu-api --region us-central1
```

## 💰 Custos

- **Gratuito** até 2 milhões de requests/mês
- **Depois**: ~$0.40 por milhão de requests
- **Muito barato** para começar!






## Endpoints de Atendimento (Novos)

Estes endpoints foram adicionados para iniciar a integração do módulo de atendimento com respostas padronizadas (stub) e validações mínimas.

### Anamnese
- `POST /api/anamnese`
- `GET /api/anamnese?prontuario=:id`

Exemplo `POST /api/anamnese`:
```json
{
  "prontuario": 12345,
  "anotacao": "Paciente relata dor ocular há 2 dias.",
  "vitais": {
    "pa": "120/80",
    "pulso": 72,
    "temperatura": 36.7
  }
}
```

### Exames
- `POST /api/exames/pio`
- `GET /api/exames/pio?prontuario=:id`
- `POST /api/exames/biomicroscopia`
- `GET /api/exames/biomicroscopia?prontuario=:id`
- `POST /api/exames/retina`
- `GET /api/exames/retina?prontuario=:id`

Exemplo `POST /api/exames/pio`:
```json
{
  "prontuario": 12345,
  "tipo": "Goldmann",
  "olho": "OD",
  "pressao": 14.2,
  "alvo": 12.0,
  "medicacoes": "Timolol 0.5%",
  "observacao": "Sem alterações",
  "data": "2025-10-24",
  "hora": "09:15"
}
```

### Receituário
- `POST /api/receituario`
- `GET /api/receituario?prontuario=:id`

Exemplo `POST /api/receituario`:
```json
{
  "prontuario": 12345,
  "medicamento": "Naproxeno",
  "quantidade": 20,
  "posologia": "1 comprimido 2x ao dia por 10 dias",
  "via": "oral"
}
```

Notas:
- Todas as respostas atuais retornam `status: "stub"` e não persistem dados.
- Validações mínimas já aplicadas para evitar erros comuns (tipos e formatos).
- Autenticação por token será integrada quando o fluxo de `Auth->verifyToken` for ajustado para retornar dados sem interromper a execução.






