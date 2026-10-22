CREATE DATABASE IF NOT EXISTS sigsm1
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sigsm1;

-- Roles de las cuentas:
-- sigsm_admin@localhost: administracion completa, limitada a la base sigsm1
-- sigsm_app@127.0.0.1: esta cuenta la usa PHP; solo lee las vistas de consulta y tiene permisos de escritura puntuales en documentos, encuestas y auth_tokens
CREATE USER IF NOT EXISTS 'sigsm_admin'@'localhost'
    IDENTIFIED BY '1d6945167e4eb6a61693961a201806e226ecbaf6aa12ddfab50ea2e2bac4ec89';
GRANT ALL PRIVILEGES ON sigsm1.* TO 'sigsm_admin'@'localhost';

CREATE USER IF NOT EXISTS 'sigsm_app'@'127.0.0.1'
    IDENTIFIED BY '3747eedda426c2821890a7fa485c748375cfb636e347159b658092a7561710f9';

CREATE TABLE IF NOT EXISTS usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  documento VARCHAR(20) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  clave VARCHAR(255) NOT NULL,
  rol ENUM('funcionario') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_documento (documento)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categorias_documentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categorias_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS documentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  descripcion TEXT NOT NULL,
  especialista VARCHAR(160) NOT NULL,
  archivo_url VARCHAR(255) NOT NULL DEFAULT '',
  peso_kb INT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NULL,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_documentos_categoria (categoria_id),
  CONSTRAINT fk_documentos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_documentos(id),
  CONSTRAINT fk_documentos_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS encuestas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  documento_id INT UNSIGNED NULL,
  servicio VARCHAR(100) NOT NULL,
  calificacion_info ENUM('Excelente', 'Buena', 'Regular', 'Mala') NOT NULL,
  datosSatisfaccion TINYINT UNSIGNED NOT NULL,
  comprension ENUM('Si', 'Parcialmente', 'No') NOT NULL,
  utilidad ENUM('Muy util', 'Util', 'Poco util', 'Nada util') NOT NULL,
  comentario TEXT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_encuestas_documento FOREIGN KEY (documento_id) REFERENCES documentos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS auth_tokens (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  usuario_id INT NOT NULL,
  expires_at BIGINT NOT NULL,	
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auth_tokens_user (usuario_id),
  INDEX idx_auth_tokens_expiration (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Vistas de lectura para consultas repetidas de la API
CREATE OR REPLACE VIEW sigsm1.vw_documentos_activos AS
SELECT d.id,
       d.titulo,
       d.descripcion,
       d.especialista,
       d.archivo_url,
       d.peso_kb,
       d.fecha_actualizacion,
       c.id AS categoria_id,
       c.nombre AS categoria_nombre
FROM sigsm1.documentos AS d
JOIN sigsm1.categorias_documentos AS c ON c.id = d.categoria_id
WHERE d.activo = 1;

CREATE OR REPLACE VIEW sigsm1.vw_encuestas_detalle AS
SELECT servicio, calificacion_info, datosSatisfaccion, comentario, creado_en
FROM sigsm1.encuestas;

CREATE OR REPLACE VIEW sigsm1.vw_encuestas_resumen AS
SELECT servicio,
COUNT(*) AS total_respuestas,
SUM(datosSatisfaccion) AS suma_satisfaccion
FROM sigsm1.encuestas
GROUP BY servicio;

-- Le doy algunos permisos especificos al usuario de PHP
GRANT SELECT ON sigsm1.usuarios TO 'sigsm_app'@'127.0.0.1';
GRANT INSERT ON sigsm1.usuarios TO 'sigsm_app'@'127.0.0.1';
GRANT UPDATE (clave) ON sigsm1.usuarios TO 'sigsm_app'@'127.0.0.1';
GRANT INSERT ON sigsm1.documentos TO 'sigsm_app'@'127.0.0.1';
GRANT UPDATE ON sigsm1.documentos TO 'sigsm_app'@'127.0.0.1';
GRANT INSERT ON sigsm1.encuestas TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON sigsm1.auth_tokens TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT ON sigsm1.vw_documentos_activos TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT ON sigsm1.vw_encuestas_detalle TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT ON sigsm1.vw_encuestas_resumen TO 'sigsm_app'@'127.0.0.1';

-- Contraseña para todos: 1234
INSERT INTO usuarios (documento, nombre, clave, rol) VALUES
('11111111', 'Sujeto1 Documentación', '1234', 'funcionario'),
('22222222', 'Sujeto2 Documentación', '1234', 'funcionario'),
('33333333', 'Sujeto3 Documentación', '1234', 'funcionario')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), clave = VALUES(clave), rol = VALUES(rol), activo = 1;

INSERT INTO categorias_documentos (nombre) VALUES
('Nefrología'),
('Cardiología'),
('Urología'),
('Trasplante'),
('Imagenología'),
('Neurología');

INSERT INTO documentos (categoria_id, titulo, descripcion, especialista, archivo_url, peso_kb, creado_por) VALUES
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Nefrología'),
  'Plan de alta de enfermería',
  'Indicaciones de cuidados de enfermería posteriores al alta.',
  'Servicio de Nefrología',
  'https://localhost/sigsm/Archivos/plan_alta_enfermeria.pdf',
  340,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Nefrología'),
  'Indicaciones de ingreso a centro',
  'Instrucciones para el ingreso al centro de diálisis.',
  'Servicio de Nefrología',
  'https://localhost/sigsm/Archivos/indicaciones_ingreso_centro.pdf',
  210,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Nefrología'),
  'Indicaciones de enfermería para trasplantados',
  'Cuidados de enfermería recomendados para pacientes trasplantados.',
  'Servicio de Nefrología',
  'https://localhost/sigsm/Archivos/indicaciones_enfermeria_trasplantados.pdf',
  275,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Cardiología'),
  'Ecocardiograma con dobutamina',
  'Resultado e indicaciones del estudio de esfuerzo farmacológico.',
  'Servicio de Cardiología',
  'https://localhost/sigsm/Archivos/ecocardiograma_dobutamina.pdf',
  512,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Cardiología'),
  'Ecocardiograma transesofágico',
  'Información correspondiente al estudio ecocardiográfico transesofágico.',
  'Servicio de Cardiología',
  'https://localhost/sigsm/Archivos/ecocardiograma_transesofagico.pdf',
  480,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Cardiología'),
  'Centellograma de perfusión miocárdica',
  'Información sobre el estudio de perfusión miocárdica.',
  'Servicio de Cardiología',
  'https://localhost/sigsm/Archivos/centellograma_perfusion.pdf',
  398,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Urología'),
  'Prostatectomía radical',
  'Indicaciones y cuidados posteriores a una prostatectomía radical.',
  'Servicio de Urología',
  'https://localhost/sigsm/Archivos/prostatectomia_radical.pdf',
  260,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Trasplante'),
  'Guía de cuidados post-trasplante',
  'Recomendaciones generales para pacientes trasplantados.',
  'Servicio de Trasplante',
  'https://localhost/sigsm/Archivos/guia_cuidados_post_trasplante.pdf',
  305,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Imagenología'),
  'Preparación para estudios de imagen',
  'Indicaciones generales para la preparación previa a estudios de imagenología.',
  'Servicio de Imagenología',
  'https://localhost/sigsm/Archivos/preparacion_estudios_imagen.pdf',
  190,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Neurología'),
  'Guía de cuidados neurológicos',
  'Recomendaciones generales para pacientes con estudios neurológicos.',
  'Servicio de Neurología',
  'https://localhost/sigsm/Archivos/guia_cuidados_neurologicos.pdf',
  225,
  2
);

INSERT INTO encuestas (documento_id, servicio, calificacion_info, datosSatisfaccion, comprension, utilidad, comentario) VALUES
(4, 'Cardiología', 'Excelente', 5, 'Si', 'Muy util', 'La información fue muy clara y fácil de comprender.'),
(1, 'Nefrología', 'Buena', 4, 'Si', 'Util', 'El documento fue útil y respondió las dudas principales.'),
(5, 'Cardiología', 'Excelente', 5, 'Si', 'Muy util', 'Todo estaba explicado correctamente.'),
(7, 'Urología', 'Regular', 3, 'Parcialmente', 'Util', 'La información es correcta, pero podría tener más imágenes.'),
(8, 'Trasplante', 'Excelente', 5, 'Si', 'Muy util', 'Muy fácil de entender y con información completa.'),
(2, 'Nefrología', 'Buena', 4, 'Si', 'Util', 'Las indicaciones son claras.'),
(3, 'Nefrología', 'Regular', 2, 'Parcialmente', 'Poco util', 'Algunas indicaciones podrían estar explicadas con mayor detalle.'),
(9, 'Imagenología', 'Buena', 4, 'Si', 'Util', 'La información fue clara.'),
(10, 'Neurología', 'Mala', 1, 'No', 'Nada util', 'La información no fue suficiente para comprender el procedimiento.'),
(6, 'Cardiología', 'Regular', 3, 'Parcialmente', 'Util', 'El documento podría incluir una explicación más detallada.');


-- ---------------------------------------------------------------------------------------------------------------------------
-- ================================================= Módulo de ambulancias ===================================================
-- ---------------------------------------------------------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS traslados_ambulancia (
    Id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    TipoElemento ENUM('paciente', 'equipamiento', 'insumo') NOT NULL,
    NombreElemento VARCHAR(150) NOT NULL,
    CiPaciente VARCHAR(12) NULL,
    Vehiculo ENUM('ambulancia01', 'ambulancia02', 'ambulancia03', 'auto01', 'auto02') NOT NULL,
    Chofer VARCHAR(150) NOT NULL,
    Enfermero VARCHAR(150) NULL,
    Origen ENUM('hc', 'urgencias', 'otro') NOT NULL,
    Destino VARCHAR(200) NOT NULL,
    TipoTraslado ENUM('interno', 'externo') NOT NULL,
    HoraSalidaPrevista TIME NULL,
    HoraLlegadaPrevista TIME NULL,
    Prioridad ENUM('baja', 'media', 'alta') NOT NULL,
    Observaciones VARCHAR(2000) NULL,
    Estado ENUM('pendiente', 'en_camino', 'en_destino', 'en_retorno', 'finalizado', 'cancelado') NOT NULL DEFAULT 'pendiente',
    SalidaEn DATETIME NULL,
    LlegadaDestinoEn DATETIME NULL,
    RetornoEn DATETIME NULL,
    LlegadaBaseEn DATETIME NULL,
    CreadoPor INT UNSIGNED NOT NULL,
    CreadoEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ActualizadoEn TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    VehiculoActivo VARCHAR(20) AS (
        CASE
            WHEN Estado IN ('pendiente', 'en_camino', 'en_destino', 'en_retorno') THEN Vehiculo
            ELSE NULL
        END
    ) STORED,
    PRIMARY KEY (Id),
    UNIQUE KEY UQ_Traslados_VehiculoActivo (VehiculoActivo),
    KEY INDICE_Traslados_EstadoPrioridad (Estado, Prioridad),
    KEY INDICE_Traslados_ActualizadoEn (ActualizadoEn),
    CONSTRAINT CF_Traslados_CreadoPor
        FOREIGN KEY (CreadoPor) REFERENCES usuarios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modelo relacional normalizado de transporte.
CREATE TABLE IF NOT EXISTS FUNCIONARIO (
    IdFuncionario INT UNSIGNED NOT NULL AUTO_INCREMENT,
    Nombre VARCHAR(150) NOT NULL,
    PRIMARY KEY (IdFuncionario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS TELEFONOS (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    Telefono VARCHAR(25) NOT NULL,
    PRIMARY KEY (IdFuncionarioFK, Telefono),
    CONSTRAINT CF_Telefonos_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS CHOFER (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    TipoLicencia VARCHAR(30) NOT NULL,
    PRIMARY KEY (IdFuncionarioFK),
    CONSTRAINT CF_Chofer_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ENFERMERO (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    Especialidad VARCHAR(120) NOT NULL,
    PRIMARY KEY (IdFuncionarioFK),
    CONSTRAINT CF_Enfermero_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ADMINISTRATIVO (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    PRIMARY KEY (IdFuncionarioFK),
    CONSTRAINT CF_Administrativo_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS MEDICO (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    PRIMARY KEY (IdFuncionarioFK),
    CONSTRAINT CF_Medico_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS VEHICULO (
    Matricula VARCHAR(15) NOT NULL,
    TipoVehiculo VARCHAR(50) NOT NULL,
    RequiereEspecializado BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (Matricula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS RUTA (
    IdRuta INT UNSIGNED NOT NULL AUTO_INCREMENT,
    PuntoOrigen VARCHAR(200) NOT NULL,
    PuntoDestino VARCHAR(200) NOT NULL,
    RutinaBase BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (IdRuta),
    KEY INDICE_Ruta_OrigenDestino (PuntoOrigen, PuntoDestino)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ELEMENTOTRASLADADO (
    IdElemento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    TipoElemento ENUM('paciente', 'equipamiento', 'insumo') NOT NULL,
    NroReferencia VARCHAR(50) NULL,
    DatosPaciente TEXT NULL,
    PRIMARY KEY (IdElemento),
    KEY INDICE_Elemento_TipoReferencia (TipoElemento, NroReferencia),
    CONSTRAINT CK_Elemento_DatosPaciente
        CHECK ((TipoElemento = 'paciente') OR DatosPaciente IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS TRASLADO (
    IdTraslado INT UNSIGNED NOT NULL AUTO_INCREMENT,
    Motivo VARCHAR(500) NOT NULL,
    ViaSolicitud VARCHAR(40) NOT NULL,
    Estado ENUM('solicitado', 'pendiente', 'en_camino', 'en_destino', 'en_retorno', 'finalizado', 'cancelado') NOT NULL DEFAULT 'solicitado',
    HoraSalida DATETIME NULL,
    HoraLlegadaEst DATETIME NULL,
    HoraLlegadaEffect DATETIME NULL,
    IdChoferFK INT UNSIGNED NOT NULL,
    IdEnfermeroFK INT UNSIGNED NULL,
    IdAdministrativoFK INT UNSIGNED NOT NULL,
    IdMedicoFK INT UNSIGNED NULL,
    MatriculaFK VARCHAR(15) NOT NULL,
    IdRutaFK INT UNSIGNED NOT NULL,
    IdElementoFK INT UNSIGNED NOT NULL,
    PRIMARY KEY (IdTraslado),
    KEY INDICE_Traslado_EstadoHora (Estado, HoraSalida),
    KEY INDICE_Traslado_Chofer (IdChoferFK),
    KEY INDICE_Traslado_Enfermero (IdEnfermeroFK),
    KEY INDICE_Traslado_Administrativo (IdAdministrativoFK),
    KEY INDICE_Traslado_Medico (IdMedicoFK),
    KEY INDICE_Traslado_Vehiculo (MatriculaFK),
    KEY INDICE_Traslado_Ruta (IdRutaFK),
    KEY INDICE_Traslado_Elemento (IdElementoFK),
    CONSTRAINT CF_Traslado_Chofer
        FOREIGN KEY (IdChoferFK) REFERENCES CHOFER (IdFuncionarioFK),
    CONSTRAINT CF_Traslado_Enfermero
        FOREIGN KEY (IdEnfermeroFK) REFERENCES ENFERMERO (IdFuncionarioFK),
    CONSTRAINT CF_Traslado_Administrativo
        FOREIGN KEY (IdAdministrativoFK) REFERENCES ADMINISTRATIVO (IdFuncionarioFK),
    CONSTRAINT CF_Traslado_Medico
        FOREIGN KEY (IdMedicoFK) REFERENCES MEDICO (IdFuncionarioFK),
    CONSTRAINT CF_Traslado_Vehiculo
        FOREIGN KEY (MatriculaFK) REFERENCES VEHICULO (Matricula),
    CONSTRAINT CF_Traslado_Ruta
        FOREIGN KEY (IdRutaFK) REFERENCES RUTA (IdRuta),
    CONSTRAINT CF_Traslado_Elemento
        FOREIGN KEY (IdElementoFK) REFERENCES ELEMENTOTRASLADADO (IdElemento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE TRASLADO
    CHANGE COLUMN HoraLlegadaEffect HoraLlegadaDestino DATETIME NULL,
    ADD COLUMN HoraRetorno DATETIME NULL AFTER HoraLlegadaDestino,
    ADD COLUMN HoraLlegadaBase DATETIME NULL AFTER HoraRetorno,
    ADD CONSTRAINT CK_Traslado_OrdenTiempos CHECK (
        (HoraLlegadaDestino IS NULL OR HoraSalida IS NULL OR HoraLlegadaDestino >= HoraSalida)
        AND (HoraRetorno IS NULL OR HoraLlegadaDestino IS NULL OR HoraRetorno >= HoraLlegadaDestino)
        AND (HoraLlegadaBase IS NULL OR HoraRetorno IS NULL OR HoraLlegadaBase >= HoraRetorno)
    );

CREATE TABLE IF NOT EXISTS sigsm1.FUNCIONARIO_CUENTA (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    IdUsuarioFK INT UNSIGNED NOT NULL,
    PRIMARY KEY (IdFuncionarioFK),
    UNIQUE KEY UQ_FuncionarioCuenta_Usuario (IdUsuarioFK),
    CONSTRAINT CF_FuncionarioCuenta_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES sigsm1.FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT CF_FuncionarioCuenta_Usuario
        FOREIGN KEY (IdUsuarioFK) REFERENCES sigsm1.usuarios (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

GRANT SELECT, INSERT, DELETE ON sigsm1.FUNCIONARIO_CUENTA TO 'sigsm_app'@'127.0.0.1';
GRANT UPDATE (activo) ON sigsm1.usuarios TO 'sigsm_app'@'127.0.0.1';

-- Permisos de la cuenta de aplicación existente.
GRANT SELECT, INSERT, UPDATE ON sigsm1.traslados_ambulancia TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.FUNCIONARIO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON sigsm1.TELEFONOS TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.CHOFER TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.ENFERMERO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.ADMINISTRATIVO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.MEDICO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.VEHICULO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.RUTA TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.ELEMENTOTRASLADADO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.TRASLADO TO 'sigsm_app'@'127.0.0.1';


-- Datos de prueba

INSERT INTO FUNCIONARIO (Nombre)
SELECT datos.Nombre
FROM (
    SELECT 'Carlos Demo Chofer' AS Nombre
    UNION ALL SELECT 'Lucía Demo Enfermera'
    UNION ALL SELECT 'Dra. Demo Médico'
) AS datos
WHERE NOT EXISTS (
    SELECT 1 FROM FUNCIONARIO existente WHERE existente.Nombre = datos.Nombre
);

INSERT INTO CHOFER (IdFuncionarioFK, TipoLicencia)
SELECT f.IdFuncionario, 'A'
FROM FUNCIONARIO f
WHERE f.Nombre = 'Carlos Demo Chofer'
  AND NOT EXISTS (
      SELECT 1 FROM CHOFER c WHERE c.IdFuncionarioFK = f.IdFuncionario
  );

INSERT INTO ENFERMERO (IdFuncionarioFK, Especialidad)
SELECT f.IdFuncionario, 'Emergencias'
FROM FUNCIONARIO f
WHERE f.Nombre = 'Lucía Demo Enfermera'
  AND NOT EXISTS (
      SELECT 1 FROM ENFERMERO e WHERE e.IdFuncionarioFK = f.IdFuncionario
  );

INSERT INTO MEDICO (IdFuncionarioFK)
SELECT f.IdFuncionario
FROM FUNCIONARIO f
WHERE f.Nombre = 'Dra. Demo Médico'
  AND NOT EXISTS (
      SELECT 1 FROM MEDICO m WHERE m.IdFuncionarioFK = f.IdFuncionario
  );

INSERT INTO VEHICULO (Matricula, TipoVehiculo, RequiereEspecializado)
SELECT 'DEMO-AMB-01', 'Ambulancia de soporte avanzado', TRUE
WHERE NOT EXISTS (SELECT 1 FROM VEHICULO WHERE Matricula = 'DEMO-AMB-01');

INSERT INTO VEHICULO (Matricula, TipoVehiculo, RequiereEspecializado)
SELECT 'DEMO-AMB-02', 'Ambulancia de traslado', FALSE
WHERE NOT EXISTS (SELECT 1 FROM VEHICULO WHERE Matricula = 'DEMO-AMB-02');

INSERT INTO RUTA (PuntoOrigen, PuntoDestino, RutinaBase)
SELECT 'Base Central (demo)', 'Emergencia (demo)', TRUE
WHERE NOT EXISTS (
    SELECT 1 FROM RUTA
    WHERE PuntoOrigen = 'Base Central (demo)' AND PuntoDestino = 'Emergencia (demo)'
);

INSERT INTO RUTA (PuntoOrigen, PuntoDestino, RutinaBase)
SELECT 'Emergencia (demo)', 'Centro de diagnóstico (demo)', FALSE
WHERE NOT EXISTS (
    SELECT 1 FROM RUTA
    WHERE PuntoOrigen = 'Emergencia (demo)' AND PuntoDestino = 'Centro de diagnóstico (demo)'
);

INSERT INTO ELEMENTOTRASLADADO (TipoElemento, NroReferencia, DatosPaciente)
SELECT 'paciente', 'DEMO-PAC-001', 'Paciente ficticio de demostración; no representa a una persona real.'
WHERE NOT EXISTS (
    SELECT 1 FROM ELEMENTOTRASLADADO WHERE NroReferencia = 'DEMO-PAC-001'
);

INSERT INTO ELEMENTOTRASLADADO (TipoElemento, NroReferencia, DatosPaciente)
SELECT 'equipamiento', 'DEMO-EQ-001', NULL
WHERE NOT EXISTS (
    SELECT 1 FROM ELEMENTOTRASLADADO WHERE NroReferencia = 'DEMO-EQ-001'
);

