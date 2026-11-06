CREATE DATABASE sigsm1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sigsm1;

-- Usuarios, documentación y encuestas.
CREATE TABLE usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  documento VARCHAR(20) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  clave VARCHAR(255) NOT NULL,
  rol ENUM('funcionario','administrador','medico') NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_usuarios_documento (documento)
) ENGINE=InnoDB;

CREATE TABLE categorias_documentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uk_categorias_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE documentos (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  categoria_id INT UNSIGNED NOT NULL,
  titulo VARCHAR(200) NOT NULL,
  descripcion TEXT NOT NULL,
  especialista VARCHAR(160) NOT NULL,
  archivo_url VARCHAR(255) NOT NULL,
  peso_kb INT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_por INT UNSIGNED NULL,
  estado_revision ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'pendiente',
  revisado_por INT UNSIGNED NULL,
  revisado_en DATETIME NULL,
  motivo_rechazo VARCHAR(500) NULL,
  fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_documentos_categoria (categoria_id),
  CONSTRAINT fk_documentos_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_documentos(id),
  CONSTRAINT fk_documentos_usuario FOREIGN KEY (creado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_documentos_revisor FOREIGN KEY (revisado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE encuestas (
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
  CONSTRAINT ck_encuestas_satisfaccion CHECK (datosSatisfaccion BETWEEN 1 AND 5),
  CONSTRAINT fk_encuestas_documento FOREIGN KEY (documento_id) REFERENCES documentos(id)
) ENGINE=InnoDB;

CREATE OR REPLACE VIEW sigsm1.vw_encuestas_detalle AS
SELECT servicio, calificacion_info, datosSatisfaccion, comentario, creado_en
FROM sigsm1.encuestas;

CREATE OR REPLACE VIEW sigsm1.vw_encuestas_resumen AS
SELECT servicio,
COUNT(*) AS total_respuestas,
SUM(datosSatisfaccion) AS suma_satisfaccion
FROM sigsm1.encuestas
GROUP BY servicio;

-- Ambulancias.
CREATE TABLE FUNCIONARIO (
    IdFuncionario INT UNSIGNED NOT NULL AUTO_INCREMENT,
    Nombre VARCHAR(150) NOT NULL,
    PRIMARY KEY (IdFuncionario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE TELEFONOS (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    Telefono VARCHAR(25) NOT NULL,
    PRIMARY KEY (IdFuncionarioFK, Telefono),
    CONSTRAINT CF_Telefonos_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE CHOFER (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    TipoLicencia VARCHAR(30) NOT NULL,
    PRIMARY KEY (IdFuncionarioFK),
    CONSTRAINT CF_Chofer_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ENFERMERO (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    Especialidad VARCHAR(120) NOT NULL,
    PRIMARY KEY (IdFuncionarioFK),
    CONSTRAINT CF_Enfermero_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE MEDICO (
    IdFuncionarioFK INT UNSIGNED NOT NULL,
    PRIMARY KEY (IdFuncionarioFK),
    CONSTRAINT CF_Medico_Funcionario
        FOREIGN KEY (IdFuncionarioFK) REFERENCES FUNCIONARIO (IdFuncionario)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE VEHICULO (
    Matricula VARCHAR(15) NOT NULL,
    TipoVehiculo VARCHAR(50) NOT NULL,
    RequiereEspecializado BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (Matricula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE RUTA (
    IdRuta INT UNSIGNED NOT NULL AUTO_INCREMENT,
    PuntoOrigen VARCHAR(200) NOT NULL,
    PuntoDestino VARCHAR(200) NOT NULL,
    RutinaBase BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (IdRuta),
    KEY INDICE_Ruta_OrigenDestino (PuntoOrigen, PuntoDestino)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ELEMENTOTRASLADADO (
    IdElemento INT UNSIGNED NOT NULL AUTO_INCREMENT,
    TipoElemento ENUM('paciente', 'equipamiento', 'insumo') NOT NULL,
    NroReferencia VARCHAR(50) NULL,
    DatosPaciente TEXT NULL,
    PRIMARY KEY (IdElemento),
    KEY INDICE_Elemento_TipoReferencia (TipoElemento, NroReferencia),
    CONSTRAINT CK_Elemento_DatosPaciente
        CHECK ((TipoElemento = 'paciente') OR DatosPaciente IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE TRASLADO (
    IdTraslado INT UNSIGNED NOT NULL AUTO_INCREMENT,
    Motivo VARCHAR(500) NOT NULL,
    ViaSolicitud VARCHAR(40) NOT NULL,
    Estado ENUM('solicitado', 'pendiente', 'en_camino', 'en_destino', 'en_retorno', 'finalizado', 'cancelado') NOT NULL DEFAULT 'solicitado',
    HoraSalida DATETIME NULL,
    HoraLlegadaEst DATETIME NULL,
    HoraLlegadaDestino DATETIME NULL,
    HoraRetorno DATETIME NULL,
    HoraLlegadaBase DATETIME NULL,
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
    CONSTRAINT CF_Traslado_Responsable
        FOREIGN KEY (IdAdministrativoFK) REFERENCES FUNCIONARIO (IdFuncionario),
    CONSTRAINT CF_Traslado_Medico
        FOREIGN KEY (IdMedicoFK) REFERENCES MEDICO (IdFuncionarioFK),
    CONSTRAINT CF_Traslado_Vehiculo
        FOREIGN KEY (MatriculaFK) REFERENCES VEHICULO (Matricula),
    CONSTRAINT CF_Traslado_Ruta
        FOREIGN KEY (IdRutaFK) REFERENCES RUTA (IdRuta),
    CONSTRAINT CF_Traslado_Elemento
        FOREIGN KEY (IdElementoFK) REFERENCES ELEMENTOTRASLADADO (IdElemento),
    CONSTRAINT CK_Traslado_OrdenTiempos CHECK (
        (HoraLlegadaDestino IS NULL OR HoraSalida IS NULL OR HoraLlegadaDestino >= HoraSalida)
        AND (HoraRetorno IS NULL OR HoraLlegadaDestino IS NULL OR HoraRetorno >= HoraLlegadaDestino)
        AND (HoraLlegadaBase IS NULL OR HoraRetorno IS NULL OR HoraLlegadaBase >= HoraRetorno)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sigsm1.FUNCIONARIO_CUENTA (
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

-- Datos de prueba
-- Funcionarios de prueba: 111111111, 222222222, 333333333 con la contraseña SigsmFunc!84Demo
START TRANSACTION;
INSERT INTO usuarios (documento, nombre, clave, rol) VALUES
('111111111', 'Sujeto1 Documentación', '$2y$12$3PcE1S1en16RAaxjqtZ8YOHiPfiomyJMYv8zFY1f00QjHWPtg4zn2', 'funcionario'),
('222222222', 'Sujeto2 Documentación', '$2y$12$3PcE1S1en16RAaxjqtZ8YOHiPfiomyJMYv8zFY1f00QjHWPtg4zn2', 'funcionario'),
('333333333', 'Sujeto3 Documentación', '$2y$12$3PcE1S1en16RAaxjqtZ8YOHiPfiomyJMYv8zFY1f00QjHWPtg4zn2', 'funcionario');

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
  'plan_alta_enfermeria.pdf',
  340,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Nefrología'),
  'Indicaciones de ingreso a centro',
  'Instrucciones para el ingreso al centro de diálisis.',
  'Servicio de Nefrología',
  'indicaciones_ingreso_centro.pdf',
  210,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Nefrología'),
  'Indicaciones de enfermería para trasplantados',
  'Cuidados de enfermería recomendados para pacientes trasplantados.',
  'Servicio de Nefrología',
  'indicaciones_enfermeria_trasplantados.pdf',
  275,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Cardiología'),
  'Ecocardiograma con dobutamina',
  'Resultado e indicaciones del estudio de esfuerzo farmacológico.',
  'Servicio de Cardiología',
  'ecocardiograma_dobutamina.pdf',
  512,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Cardiología'),
  'Ecocardiograma transesofágico',
  'Información correspondiente al estudio ecocardiográfico transesofágico.',
  'Servicio de Cardiología',
  'ecocardiograma_transesofagico.pdf',
  480,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Cardiología'),
  'Centellograma de perfusión miocárdica',
  'Información sobre el estudio de perfusión miocárdica.',
  'Servicio de Cardiología',
  'centellograma_perfusion.pdf',
  398,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Urología'),
  'Prostatectomía radical',
  'Indicaciones y cuidados posteriores a una prostatectomía radical.',
  'Servicio de Urología',
  'prostatectomia_radical.pdf',
  260,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Trasplante'),
  'Guía de cuidados post-trasplante',
  'Recomendaciones generales para pacientes trasplantados.',
  'Servicio de Trasplante',
  'guia_cuidados_post_trasplante.pdf',
  305,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Imagenología'),
  'Preparación para estudios de imagen',
  'Indicaciones generales para la preparación previa a estudios de imagenología.',
  'Servicio de Imagenología',
  'preparacion_estudios_imagen.pdf',
  190,
  2
),
(
  (SELECT id FROM categorias_documentos WHERE nombre = 'Neurología'),
  'Guía de cuidados neurológicos',
  'Recomendaciones generales para pacientes con estudios neurológicos.',
  'Servicio de Neurología',
  'guia_cuidados_neurologicos.pdf',
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

-- Cuentas nuevas de prueba
-- administrador | documento: 999999999 | contraseña: SigsmAdmin!84Demo
-- medico | documento: 888888888 | contraseña: SigsmMedico!72Demo

INSERT INTO usuarios (documento, nombre, clave, rol, activo) VALUES
  ('999999999', 'Administrador de prueba', '$2y$12$8Pdm/9g6TSVoFFJ28jktyOHqYA6zlpu12tcJgL10lQT4xzUiuV8ky', 'administrador', 1),
  ('888888888', 'Medico de prueba', '$2y$12$yMsW6i2OgIUOhjGxDv1sOe4iX2PRJ2tfIHiBUc/5lKoWUA7sTUW76', 'medico', 1);

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
COMMIT;

-- Cuenta local de PHP.
CREATE USER IF NOT EXISTS 'sigsm_app'@'127.0.0.1' IDENTIFIED BY 'CAMBIAR_CLAVE_MYSQL';
GRANT SELECT, INSERT, UPDATE (clave, activo) ON sigsm1.usuarios TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT ON sigsm1.categorias_documentos TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.documentos TO 'sigsm_app'@'127.0.0.1';
GRANT INSERT ON sigsm1.encuestas TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT ON sigsm1.vw_encuestas_detalle TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT ON sigsm1.vw_encuestas_resumen TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sigsm1.FUNCIONARIO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, DELETE ON sigsm1.FUNCIONARIO_CUENTA TO 'sigsm_app'@'127.0.0.1';
GRANT INSERT ON sigsm1.TELEFONOS TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sigsm1.CHOFER TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sigsm1.ENFERMERO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sigsm1.MEDICO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.VEHICULO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sigsm1.RUTA TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sigsm1.ELEMENTOTRASLADADO TO 'sigsm_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sigsm1.TRASLADO TO 'sigsm_app'@'127.0.0.1';
