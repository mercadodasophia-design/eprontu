-- Script para criar a tabela fila_espera
-- Execute este script no banco de dados PostgreSQL

CREATE TABLE IF NOT EXISTS fila_espera (
    id SERIAL PRIMARY KEY,
    fila_id INTEGER NOT NULL,
    paciente_id INTEGER NOT NULL,
    procedimento_id VARCHAR(50) NOT NULL,
    especialidade_id INTEGER NOT NULL,
    unidade_id VARCHAR(50) NOT NULL,
    medico_solicitante_id VARCHAR(50) NOT NULL,
    usuario_regulador_id VARCHAR(50),
    
    status VARCHAR(20) NOT NULL DEFAULT 'pendente',
    prioridade VARCHAR(20) NOT NULL DEFAULT 'eletiva',
    pontuacao_clinica INTEGER DEFAULT 0,
    
    data_solicitacao TIMESTAMP NOT NULL,
    data_prazo TIMESTAMP,
    data_regulacao TIMESTAMP,
    data_agendamento TIMESTAMP,
    data_previsao_atendimento TIMESTAMP,
    data_conclusao TIMESTAMP,
    data_entrada_fila TIMESTAMP NOT NULL DEFAULT NOW(),
    
    motivo_clinico TEXT NOT NULL,
    observacoes_regulacao TEXT,
    documentos_anexos JSONB DEFAULT '[]'::jsonb,
    
    posicao_fila INTEGER DEFAULT 0,
    tempo_espera_estimado DECIMAL(10, 2) DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    
    CONSTRAINT fk_fila FOREIGN KEY (fila_id) REFERENCES filas(id) ON DELETE RESTRICT,
    CONSTRAINT fk_paciente FOREIGN KEY (paciente_id) REFERENCES paciente(codpaciente) ON DELETE RESTRICT,
    CONSTRAINT fk_especialidade FOREIGN KEY (especialidade_id) REFERENCES especialidades(codespecialidade) ON DELETE RESTRICT,
    CONSTRAINT fk_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(codunidades) ON DELETE RESTRICT,
    CONSTRAINT chk_status CHECK (status IN ('pendente', 'regulado', 'agendado', 'concluido', 'cancelado')),
    CONSTRAINT chk_prioridade CHECK (prioridade IN ('emergencia', 'urgente', 'prioritaria', 'eletiva'))
);

-- Índices para melhorar performance
CREATE INDEX IF NOT EXISTS idx_fila_espera_status ON fila_espera(status);
CREATE INDEX IF NOT EXISTS idx_fila_espera_prioridade ON fila_espera(prioridade);
CREATE INDEX IF NOT EXISTS idx_fila_espera_paciente ON fila_espera(paciente_id);
CREATE INDEX IF NOT EXISTS idx_fila_espera_especialidade ON fila_espera(especialidade_id);
CREATE INDEX IF NOT EXISTS idx_fila_espera_unidade ON fila_espera(unidade_id);
CREATE INDEX IF NOT EXISTS idx_fila_espera_data_entrada ON fila_espera(data_entrada_fila);

-- Comentários nas colunas
COMMENT ON TABLE fila_espera IS 'Tabela para armazenar pacientes na fila de espera SUS';
COMMENT ON COLUMN fila_espera.status IS 'Status: pendente, regulado, agendado, concluido, cancelado';
COMMENT ON COLUMN fila_espera.prioridade IS 'Prioridade: emergencia, urgente, prioritaria, eletiva';

