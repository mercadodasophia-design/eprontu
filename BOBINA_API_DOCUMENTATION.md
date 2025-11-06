# 📋 DOCUMENTAÇÃO DA API DA BOBINA

## 🎯 **VISÃO GERAL**
Esta documentação descreve todas as rotas da API da Bobina do sistema e-prontu, organizadas por funcionalidade e tipo de procedimento.

---

## 🔗 **ENDPOINTS PRINCIPAIS**

### **Base URL:** `/api/`

---

## 🔬 **EXAMES OFTALMOLÓGICOS**

### **Endpoint:** `/api/bobina-exames/{acao}`

#### **1. PIO (Pressão Intraocular)**
```http
POST   /api/bobina-exames/pio
GET    /api/bobina-exames/pio?prontuario={id}&date={data}
```

**POST - Criar PIO:**
```json
{
  "prontuario": 12345,
  "medico": 1,
  "data": "2025-01-15",
  "hora": "14:30:00",
  "olho": "OD",
  "pressao": 15.5,
  "alvo": 12.0,
  "metodo": "Goldmann",
  "medicamento": "Pilocarpina",
  "observacoes": "Paciente colaborativo",
  "usuario": 1
}
```

**GET - Listar PIO:**
- `prontuario` (obrigatório) - Número do prontuário
- `date` (opcional) - Data específica (YYYY-MM-DD)

#### **2. Biomicroscopia**
```http
POST   /api/bobina-exames/biomicroscopia
GET    /api/bobina-exames/biomicroscopia?prontuario={id}&date={data}
```

**POST - Criar Biomicroscopia:**
```json
{
  "prontuario": 12345,
  "medico": 1,
  "data": "2025-01-15",
  "hora": "14:30:00",
  "olho": "OD",
  "ceratometria": "Dados de ceratometria",
  "cornea": "Córnea transparente",
  "cristalino": "Cristalino transparente",
  "iris": "Íris normal",
  "pupila": "Pupila isocórica",
  "camara": "Câmara anterior normal",
  "angulo": "Ângulo aberto",
  "observacoes": "Exame normal",
  "usuario": 1
}
```

#### **3. Paquimetria**
```http
POST   /api/bobina-exames/paquimetria
GET    /api/bobina-exames/paquimetria?prontuario={id}&date={data}
```

**POST - Criar Paquimetria:**
```json
{
  "prontuario": 12345,
  "medico": 1,
  "data": "2025-01-15",
  "hora": "14:30:00",
  "olho": "OD",
  "espessura": 520.5,
  "metodo": "Ultrassom",
  "observacoes": "Espessura normal",
  "usuario": 1
}
```

#### **4. Retina**
```http
POST   /api/bobina-exames/retina
GET    /api/bobina-exames/retina?prontuario={id}&date={data}
```

**POST - Criar Exame de Retina:**
```json
{
  "prontuario": 12345,
  "medico": 1,
  "data": "2025-01-15",
  "hora": "14:30:00",
  "olho": "OD",
  "campos": {
    "papila": "Normal",
    "macula": "Normal",
    "vasos": "Normais"
  },
  "conclusao": "Retina normal",
  "oct": "Dados do OCT",
  "observacoes": "Exame completo",
  "usuario": 1
}
```

#### **5. Gonioscopia**
```http
POST   /api/bobina-exames/gonioscopia
GET    /api/bobina-exames/gonioscopia?prontuario={id}&date={data}
```

#### **6. Refração**
```http
POST   /api/bobina-exames/refracao
GET    /api/bobina-exames/refracao?prontuario={id}&date={data}
```

#### **7. Campimetria**
```http
POST   /api/bobina-exames/campimetria
GET    /api/bobina-exames/campimetria?prontuario={id}&date={data}
```

---

## 🏥 **PROCEDIMENTOS CIRÚRGICOS**

### **Endpoint:** `/api/bobina-cirurgias/{acao}`

#### **1. Catarata**
```http
POST   /api/bobina-cirurgias/catarata
GET    /api/bobina-cirurgias/catarata?prontuario={id}&date={data}
```

**POST - Criar Cirurgia de Catarata:**
```json
{
  "prontuario": 12345,
  "medico": 1,
  "data": "2025-01-15",
  "hora": "08:00:00",
  "olho": "OD",
  "tipo_cirurgia": "Facoemulsificação",
  "tecnica": "Técnica padrão",
  "complicacoes": "Nenhuma",
  "observacoes": "Cirurgia bem-sucedida",
  "usuario": 1
}
```

#### **2. Glaucoma**
```http
POST   /api/bobina-cirurgias/glaucoma
GET    /api/bobina-cirurgias/glaucoma?prontuario={id}&date={data}
```

#### **3. Retina**
```http
POST   /api/bobina-cirurgias/retina
GET    /api/bobina-cirurgias/retina?prontuario={id}&date={data}
```

#### **4. Córnea**
```http
POST   /api/bobina-cirurgias/cornea
GET    /api/bobina-cirurgias/cornea?prontuario={id}&date={data}
```

#### **5. LIO (Lente Intraocular)**
```http
POST   /api/bobina-cirurgias/lio
GET    /api/bobina-cirurgias/lio?prontuario={id}&date={data}
```

**POST - Criar LIO:**
```json
{
  "prontuario": 12345,
  "medico": 1,
  "data": "2025-01-15",
  "hora": "08:00:00",
  "olho": "OD",
  "tipo_lio": "Monofocal",
  "potencia": 20.0,
  "material": "Acrílico",
  "observacoes": "LIO implantada com sucesso",
  "usuario": 1
}
```

---

## 💊 **MEDICAMENTOS E PRESCRIÇÕES**

### **Endpoint:** `/api/bobina-medicamentos/{acao}`

#### **1. Prescrição**
```http
POST   /api/bobina-medicamentos/prescricao
GET    /api/bobina-medicamentos/prescricao?prontuario={id}&date={data}
PUT    /api/bobina-medicamentos/prescricao?id={id}
DELETE /api/bobina-medicamentos/prescricao?id={id}
```

**POST - Criar Prescrição:**
```json
{
  "prontuario": 12345,
  "medico": 1,
  "data": "2025-01-15",
  "hora": "14:30:00",
  "observacoes": "Prescrição de rotina",
  "medicamentos": [
    {
      "medicamento_id": 1,
      "dosagem": "500mg",
      "posologia": "1 comprimido 3x ao dia",
      "quantidade": 30,
      "observacoes": "Tomar com água"
    }
  ],
  "usuario": 1
}
```

#### **2. Medicamento**
```http
POST   /api/bobina-medicamentos/medicamento
GET    /api/bobina-medicamentos/medicamento?id={id}&nome={nome}
```

#### **3. Dosagem**
```http
POST   /api/bobina-medicamentos/dosagem
GET    /api/bobina-medicamentos/dosagem?medicamento_id={id}
```

#### **4. Posologia**
```http
POST   /api/bobina-medicamentos/posologia
GET    /api/bobina-medicamentos/posologia?medicamento_id={id}
```

---

## 📄 **DOCUMENTOS E ANAMNESE**

### **Endpoint:** `/api/bobina-documentos/{acao}`

#### **1. Anamnese**
```http
POST   /api/bobina-documentos/anamnese
GET    /api/bobina-documentos/anamnese?prontuario={id}&date={data}
PUT    /api/bobina-documentos/anamnese?id={id}
```

**POST - Criar Anamnese:**
```json
{
  "prontuario": 12345,
  "medico": 1,
  "data": "2025-01-15",
  "hora": "14:30:00",
  "queixa_principal": "Dor de cabeça",
  "historia_doenca": "Histórico detalhado",
  "antecedentes_pessoais": "Hipertensão",
  "antecedentes_familiares": "Diabetes na família",
  "medicamentos_uso": "Losartana 50mg",
  "alergias": "Penicilina",
  "observacoes": "Paciente colaborativo",
  "usuario": 1
}
```

#### **2. Documento**
```http
POST   /api/bobina-documentos/documento
GET    /api/bobina-documentos/documento?prontuario={id}&tipo={tipo}&date={data}
PUT    /api/bobina-documentos/documento?id={id}
DELETE /api/bobina-documentos/documento?id={id}
```

#### **3. Portfólio**
```http
POST   /api/bobina-documentos/portfolio
GET    /api/bobina-documentos/portfolio?prontuario={id}&categoria={categoria}&date={data}
DELETE /api/bobina-documentos/portfolio?id={id}
```

#### **4. Laudo**
```http
POST   /api/bobina-documentos/laudo
GET    /api/bobina-documentos/laudo?prontuario={id}&tipo_exame={tipo}&date={data}
```

---

## ⏰ **TIMELINE E RELATÓRIOS**

### **Endpoint:** `/api/bobina-timeline/{acao}`

#### **1. Timeline**
```http
GET    /api/bobina-timeline/timeline?prontuario={id}&data_inicio={data}&data_fim={data}&tipo={tipo}
```

**Parâmetros:**
- `prontuario` (obrigatório) - Número do prontuário
- `data_inicio` (opcional) - Data de início (YYYY-MM-DD)
- `data_fim` (opcional) - Data de fim (YYYY-MM-DD)
- `tipo` (opcional) - Filtro por tipo (exame, cirurgia, medicamento, documento)

#### **2. Relatório**
```http
GET    /api/bobina-timeline/relatorio?prontuario={id}&tipo_relatorio={tipo}&data_inicio={data}&data_fim={data}
```

**Tipos de Relatório:**
- `exames` - Relatório de exames
- `cirurgias` - Relatório de cirurgias
- `medicamentos` - Relatório de medicamentos

#### **3. Estatísticas**
```http
GET    /api/bobina-timeline/estatisticas?prontuario={id}
```

#### **4. Dashboard**
```http
GET    /api/bobina-timeline/dashboard?prontuario={id}
```

---

## 📊 **RESPOSTAS PADRONIZADAS**

### **Sucesso:**
```json
{
  "success": true,
  "message": "Operação realizada com sucesso",
  "data": { ... }
}
```

### **Erro:**
```json
{
  "error": "Descrição do erro",
  "details": [ "Detalhes específicos" ]
}
```

### **Códigos de Status HTTP:**
- `200` - Sucesso
- `201` - Criado com sucesso
- `400` - Dados inválidos
- `404` - Não encontrado
- `405` - Método não permitido
- `500` - Erro interno do servidor

---

## 🔐 **AUTENTICAÇÃO**

Todas as rotas requerem autenticação via token JWT no header:
```
Authorization: Bearer {token}
```

---

## 📝 **VALIDAÇÕES**

### **Campos Obrigatórios:**
- `prontuario` - Número do prontuário (INT)
- `medico` - Código do médico (INT)
- `data` - Data no formato YYYY-MM-DD
- `hora` - Hora no formato HH:MM:SS

### **Validações Específicas:**
- **Olho:** OD, OE, AO, D, E
- **PIO:** 0-80 mmHg
- **Paquimetria:** 200-800 μm
- **Dosagem:** Valores numéricos positivos

---

## 🚀 **EXEMPLOS DE USO**

### **1. Registrar PIO:**
```bash
curl -X POST http://localhost/api/bobina-exames/pio \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{
    "prontuario": 12345,
    "medico": 1,
    "olho": "OD",
    "pressao": 15.5,
    "metodo": "Goldmann"
  }'
```

### **2. Buscar Timeline:**
```bash
curl -X GET "http://localhost/api/bobina-timeline/timeline?prontuario=12345&data_inicio=2025-01-01&data_fim=2025-01-31" \
  -H "Authorization: Bearer {token}"
```

### **3. Gerar Relatório:**
```bash
curl -X GET "http://localhost/api/bobina-timeline/relatorio?prontuario=12345&tipo_relatorio=exames" \
  -H "Authorization: Bearer {token}"
```

---

## 📋 **TABELAS DO BANCO DE DADOS**

### **Exames:**
- `pio` - Pressão intraocular
- `biomicroscopia` - Biomicroscopia
- `paquimetria` - Paquimetria
- `retina` - Exames de retina
- `gonioscopia` - Gonioscopia
- `refracao` - Refração
- `campimetria` - Campimetria

### **Cirurgias:**
- `cirurgias_catarata` - Cirurgias de catarata
- `cirurgias_glaucoma` - Cirurgias de glaucoma
- `cirurgias_retina` - Cirurgias de retina
- `cirurgias_cornea` - Cirurgias de córnea
- `lio` - Lentes intraoculares

### **Medicamentos:**
- `prescricoes` - Prescrições
- `prescricoes_medicamentos` - Medicamentos das prescrições
- `medicamentos` - Catálogo de medicamentos
- `dosagens` - Dosagens
- `posologias` - Posologias

### **Documentos:**
- `anamnese` - Anamnese
- `documentos` - Documentos médicos
- `portfolio` - Portfólio
- `laudos` - Laudos médicos

---

**📅 Data de Criação:** $(date)  
**👨‍💻 Desenvolvedor:** Sistema e-prontu  
**🔄 Última Atualização:** $(date)  
**📋 Versão:** 1.0.0  
**📊 Total de Endpoints:** 25+ endpoints mapeados
