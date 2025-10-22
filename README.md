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






