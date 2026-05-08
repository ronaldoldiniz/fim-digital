-- FIM Digital - Banco de Dados
-- Morotó Indústria e Comércio de Aspiradores Industriais LTDA
-- CNPJ: 24.390.515/0001-03

-- Nota: O comando CREATE DATABASE foi removido para compatibilidade com hospedagens. 
-- Importe este arquivo diretamente dentro do banco de dados criado no seu painel.

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    login VARCHAR(50) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    perfil ENUM('OPERADOR','ADMINISTRATIVO','GESTOR') NOT NULL DEFAULT 'OPERADOR',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Senha padrão: admin123
INSERT INTO usuarios (nome, login, senha_hash, perfil) VALUES
('Administrador','admin','$2y$10$8K1p/a0dL1LXMw0HQ4n5IeQfGZx0k1DjT6oN/C1rnelFMqFhxK7G2','GESTOR');

CREATE TABLE IF NOT EXISTS equipamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_serie_motor VARCHAR(100) DEFAULT NULL,
    id_interno VARCHAR(20) NOT NULL UNIQUE,
    tipo_identificacao ENUM('SERIE','INTERNO') NOT NULL DEFAULT 'INTERNO',
    modelo_equipamento VARCHAR(150) DEFAULT NULL,
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_serie (numero_serie_motor),
    INDEX idx_interno (id_interno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS registros_fim (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipamento_id INT NOT NULL,
    natureza ENUM('NOVO','CONSERTO') NOT NULL,
    numero_conserto VARCHAR(50) DEFAULT NULL,
    status ENUM('EM_BANCADA','EM_MONTAGEM','AGUARDANDO_CLIENTE','FINALIZADO') NOT NULL DEFAULT 'EM_BANCADA',
    data_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_finalizacao DATETIME DEFAULT NULL,
    operador_inicio_id INT NOT NULL,
    operador_final_id INT DEFAULT NULL,
    FOREIGN KEY (equipamento_id) REFERENCES equipamentos(id) ON DELETE RESTRICT,
    FOREIGN KEY (operador_inicio_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_status (status), INDEX idx_equipamento (equipamento_id), INDEX idx_conserto (numero_conserto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dados_bancada (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registro_id INT NOT NULL UNIQUE,
    -- Tensão de Teste Na Geração (sem 760V)
    tensao_teste_220 VARCHAR(20) DEFAULT NULL,
    tensao_teste_380 VARCHAR(20) DEFAULT NULL,
    tensao_teste_440 VARCHAR(20) DEFAULT NULL,
    -- Pressões
    pressao_negativa_mmhg DECIMAL(10,2) DEFAULT NULL,
    pressao_positiva_mmh2o DECIMAL(10,2) DEFAULT NULL,
    -- Opcionais (azul)
    temperatura_c DECIMAL(6,2) DEFAULT NULL,
    diametro_mm DECIMAL(8,2) DEFAULT NULL,
    vazao_m3min DECIMAL(10,4) DEFAULT NULL,
    ruido_db DECIMAL(6,1) DEFAULT NULL,
    folga_radial_mm DECIMAL(8,3) DEFAULT NULL,
    folga_axial_mm DECIMAL(8,3) DEFAULT NULL,
    -- Corrente Operação Normal (trifásica)
    corrente_normal_fase_r DECIMAL(10,3) DEFAULT NULL,
    corrente_normal_fase_s DECIMAL(10,3) DEFAULT NULL,
    corrente_normal_fase_t DECIMAL(10,3) DEFAULT NULL,
    desvio_normal DECIMAL(6,2) DEFAULT NULL,
    -- Corrente Carga Máxima (trifásica)
    corrente_carga_fase_r DECIMAL(10,3) DEFAULT NULL,
    corrente_carga_fase_s DECIMAL(10,3) DEFAULT NULL,
    corrente_carga_fase_t DECIMAL(10,3) DEFAULT NULL,
    desvio_carga DECIMAL(6,2) DEFAULT NULL,
    -- Isolação (trifásica)
    isolacao_fase_r DECIMAL(10,2) DEFAULT NULL,
    isolacao_fase_s DECIMAL(10,2) DEFAULT NULL,
    isolacao_fase_t DECIMAL(10,2) DEFAULT NULL,
    -- Dados do Motor
    descricao_motor VARCHAR(255) DEFAULT NULL,
    numero_serie_motor VARCHAR(100) DEFAULT NULL,
    data_fabricacao_motor DATE DEFAULT NULL,
    tensao_motor_220 VARCHAR(20) DEFAULT NULL,
    tensao_motor_380 VARCHAR(20) DEFAULT NULL,
    tensao_motor_440 VARCHAR(20) DEFAULT NULL,
    frequencia_50hz TINYINT(1) DEFAULT 0,
    frequencia_60hz TINYINT(1) DEFAULT 0,
    potencia_cv DECIMAL(10,2) DEFAULT NULL,
    potencia_kw DECIMAL(10,2) DEFAULT NULL,
    fator_servico VARCHAR(20) DEFAULT NULL,
    corrente_nominal_220 DECIMAL(10,3) DEFAULT NULL,
    corrente_nominal_380 DECIMAL(10,3) DEFAULT NULL,
    corrente_nominal_440 DECIMAL(10,3) DEFAULT NULL,
    rolamento_frontal VARCHAR(80) DEFAULT NULL,
    rolamento_traseiro VARCHAR(80) DEFAULT NULL,
    lubrificacao_rolamento VARCHAR(100) DEFAULT NULL,
    -- Observações
    observacoes TEXT DEFAULT NULL,
    -- Vibração 9 pontos (mm/s)
    vibracao_x1 DECIMAL(8,2) DEFAULT NULL, vibracao_y1 DECIMAL(8,2) DEFAULT NULL, vibracao_z1 DECIMAL(8,2) DEFAULT NULL,
    vibracao_x2 DECIMAL(8,2) DEFAULT NULL, vibracao_y2 DECIMAL(8,2) DEFAULT NULL, vibracao_z2 DECIMAL(8,2) DEFAULT NULL,
    vibracao_x3 DECIMAL(8,2) DEFAULT NULL, vibracao_y3 DECIMAL(8,2) DEFAULT NULL, vibracao_z3 DECIMAL(8,2) DEFAULT NULL,
    classificacao_vibracao CHAR(1) DEFAULT NULL,
    pontos_medidos_foto VARCHAR(255) DEFAULT NULL,
    -- Na Utilização
    pressao_utilizacao_mmhg DECIMAL(10,2) DEFAULT NULL,
    rotor_diametro DECIMAL(8,3) DEFAULT NULL,
    rotor_espessura DECIMAL(8,3) DEFAULT NULL,
    tipo_fabricacao ENUM('AS','MO','MA','MI') DEFAULT NULL,
    -- Metadados
    data_preenchimento DATETIME DEFAULT NULL,
    operador_id INT DEFAULT NULL,
    FOREIGN KEY (registro_id) REFERENCES registros_fim(id) ON DELETE CASCADE,
    FOREIGN KEY (operador_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dados_cliente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registro_id INT NOT NULL UNIQUE,
    nome_cliente VARCHAR(200) DEFAULT NULL,
    numero_pedido VARCHAR(50) DEFAULT NULL,
    numero_nota_fiscal VARCHAR(50) DEFAULT NULL,
    aplicacao VARCHAR(255) DEFAULT NULL,
    localizacao VARCHAR(255) DEFAULT NULL,
    data_entrega DATE DEFAULT NULL,
    responsavel_tecnico VARCHAR(150) DEFAULT NULL,
    observacoes_finais TEXT DEFAULT NULL,
    data_preenchimento DATETIME DEFAULT NULL,
    operador_id INT DEFAULT NULL,
    FOREIGN KEY (registro_id) REFERENCES registros_fim(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS historico_cliente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registro_id INT NOT NULL,
    cliente_anterior VARCHAR(200) NOT NULL,
    cliente_novo VARCHAR(200) NOT NULL,
    motivo ENUM('Alteração de Pedido','Otimização de Performance','Realocação de Estoque','Outro') NOT NULL,
    motivo_detalhe TEXT DEFAULT NULL,
    usuario_id INT NOT NULL,
    data_alteracao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (registro_id) REFERENCES registros_fim(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_registro (registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS log_edicoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registro_id INT NOT NULL,
    campo VARCHAR(100) NOT NULL,
    valor_antigo TEXT DEFAULT NULL,
    valor_novo TEXT DEFAULT NULL,
    usuario_id INT NOT NULL,
    data DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    motivo TEXT DEFAULT NULL,
    FOREIGN KEY (registro_id) REFERENCES registros_fim(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE RESTRICT,
    INDEX idx_registro_log (registro_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sequencial_fim (
    ano INT NOT NULL PRIMARY KEY,
    ultimo_numero INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
