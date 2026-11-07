CREATE DATABASE IF NOT EXISTS ruta_del_rio
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ruta_del_rio;

CREATE TABLE IF NOT EXISTS clientes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombres VARCHAR(80) NOT NULL,
  apellidos VARCHAR(80) NOT NULL,
  email VARCHAR(254) NOT NULL,
  celular VARCHAR(30) NOT NULL,
  nacimiento DATE NOT NULL,
  ciudad VARCHAR(100) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clientes_email (email),
  KEY idx_clientes_celular (celular)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cliente_id BIGINT UNSIGNED NOT NULL,
  email_reserva VARCHAR(254) NOT NULL,
  celular_reserva VARCHAR(30) NOT NULL,
  fecha DATE NOT NULL,
  hora_inicio TIME NOT NULL,
  hora_fin TIME NOT NULL,
  recorrido ENUM('cienagas', 'magdalena', 'completa') NOT NULL,
  personas TINYINT UNSIGNED NOT NULL,
  nivel ENUM('ninguna', 'basica', 'avanzada') NOT NULL,
  chaleco BOOLEAN NOT NULL DEFAULT FALSE,
  almuerzo BOOLEAN NOT NULL DEFAULT FALSE,
  fotos BOOLEAN NOT NULL DEFAULT FALSE,
  comentarios VARCHAR(1000) NULL,
  estado ENUM('pendiente', 'confirmada', 'cancelada') NOT NULL DEFAULT 'pendiente',
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_reservas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes (id),
  UNIQUE KEY uq_cliente_fecha (cliente_id, fecha),
  UNIQUE KEY uq_email_fecha (email_reserva, fecha),
  UNIQUE KEY uq_celular_fecha (celular_reserva, fecha),
  KEY idx_reservas_horario (fecha, hora_inicio, hora_fin, estado),
  CONSTRAINT chk_reservas_hora CHECK (hora_fin > hora_inicio),
  CONSTRAINT chk_reservas_personas CHECK (personas BETWEEN 1 AND 12)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS administradores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_administradores_email (email)
) ENGINE=InnoDB;
