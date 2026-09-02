CREATE DATABASE IF NOT EXISTS sigsm1
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sigsm1;

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
