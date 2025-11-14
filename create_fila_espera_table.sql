-- Script para criar as tabelas do sistema de filas
-- Execute este script no banco de dados PostgreSQL

-- 1. Criar tabela filas (tipos de fila)
CREATE TABLE IF NOT EXISTS filas (
    id SERIAL PRIMARY KEY,
    descricao VARCHAR(255) NOT NULL,
    cor INTEGER NOT NULL,
    tipo_fila VARCHAR(20) NOT NULL DEFAULT 'consulta',
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    
    CONSTRAINT chk_tipo_fila CHECK (tipo_fila IN ('consulta', 'exame', 'cirurgia'))
);

-- Índices para filas
CREATE INDEX IF NOT EXISTS idx_filas_tipo ON filas(tipo_fila);

-- Comentários
COMMENT ON TABLE filas IS 'Tabela para armazenar tipos de filas (consulta, exame, cirurgia)';
COMMENT ON COLUMN filas.cor IS 'Cor da fila em formato ARGB (integer)';
COMMENT ON COLUMN filas.tipo_fila IS 'Tipo: consulta, exame, cirurgia';

-- 2. Criar tabela fila_espera
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
    
    CONSTRAINT chk_status CHECK (status IN ('pendente', 'regulado', 'agendado', 'concluido', 'cancelado')),
    CONSTRAINT chk_prioridade CHECK (prioridade IN ('emergencia', 'urgente', 'prioritaria', 'eletiva'))
);

-- Adicionar foreign key para filas (se a tabela existir)
-- As outras foreign keys foram removidas para evitar erros de constraint
-- Você pode adicioná-las manualmente depois se necessário
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'filas') THEN
        IF NOT EXISTS (
            SELECT 1 FROM information_schema.table_constraints 
            WHERE constraint_name = 'fk_fila' AND table_name = 'fila_espera'
        ) THEN
            ALTER TABLE fila_espera 
            ADD CONSTRAINT fk_fila FOREIGN KEY (fila_id) REFERENCES filas(id) ON DELETE RESTRICT;
        END IF;
    END IF;
END $$;

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

-- 3. Inserir dados iniciais na tabela filas (opcional)
-- Você pode remover ou modificar estes dados conforme necessário
-- Só insere se não existir nenhum registro
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM filas LIMIT 1) THEN
        INSERT INTO filas (descricao, cor, tipo_fila) 
        VALUES 
            ('Fila de Consultas', 4280391411, 'consulta'),
            ('Fila de Exames', 4280391411, 'exame'),
            ('Fila de Cirurgias', 4280391411, 'cirurgia');
    END IF;
END $$;

