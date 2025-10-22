#!/bin/bash

# Script para deploy da API no Google Cloud Run
# Uso: ./deploy.sh

echo "🚀 Iniciando deploy da API E-Prontu para Cloud Run..."

# Verificar se gcloud está instalado
if ! command -v gcloud &> /dev/null; then
    echo "❌ Google Cloud CLI não encontrado. Instale em: https://cloud.google.com/sdk/docs/install"
    exit 1
fi

# Verificar se está logado
if ! gcloud auth list --filter=status:ACTIVE --format="value(account)" | grep -q .; then
    echo "🔐 Fazendo login no Google Cloud..."
    gcloud auth login
fi

# Definir projeto (substitua pelo seu PROJECT_ID)
PROJECT_ID="e-prontu"
REGION="us-central1"
SERVICE_NAME="e-prontu-api"

echo "📋 Configurando projeto: $PROJECT_ID"
gcloud config set project $PROJECT_ID

echo "🏗️ Fazendo build da imagem Docker..."
docker build -t gcr.io/$PROJECT_ID/$SERVICE_NAME .

echo "📤 Enviando imagem para Container Registry..."
docker push gcr.io/$PROJECT_ID/$SERVICE_NAME

echo "🚀 Deployando para Cloud Run..."
gcloud run deploy $SERVICE_NAME \
  --image gcr.io/$PROJECT_ID/$SERVICE_NAME \
  --region $REGION \
  --platform managed \
  --allow-unauthenticated \
  --port 8080 \
  --memory 512Mi \
  --cpu 1 \
  --max-instances 10 \
  --timeout 300

echo "✅ Deploy concluído!"
echo "🌐 URL da API: https://$SERVICE_NAME-$PROJECT_ID.a.run.app"






