-- Base de datos: refugio y adopción de mascotas
-- Compatible con MySQL / MariaDB (XAMPP)

CREATE DATABASE IF NOT EXISTS refugio_adopcion
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE refugio_adopcion;

-- Se eliminan en orden inverso a las dependencias
DROP TABLE IF EXISTS solicitudes_adopcion;
DROP TABLE IF EXISTS mascota_fotos;
DROP TABLE IF EXISTS mascotas;
DROP TABLE IF EXISTS especies;
DROP TABLE IF EXISTS usuarios;

CREATE TABLE usuarios (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre        VARCHAR(100) NOT NULL,
  telefono      VARCHAR(20)  NULL,
  correo        VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol           ENUM('usuario','admin') NOT NULL DEFAULT 'usuario',
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_correo (correo)
) ENGINE=InnoDB;

CREATE TABLE especies (
  id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(50)  NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_especies_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE mascotas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  especie_id    INT UNSIGNED NOT NULL,
  nombre        VARCHAR(100) NOT NULL,
  sexo          ENUM('macho','hembra') NOT NULL DEFAULT 'macho',
  raza          VARCHAR(80) NULL,
  descripcion   TEXT NULL,
  edad_meses    SMALLINT UNSIGNED NULL,
  tamano        ENUM('chico','mediano','grande') NOT NULL,
  imagen        VARCHAR(255) NULL,              -- ruta relativa, p. ej. 'img/mascotas/luna.jpg'
  apto_ninos    BOOLEAN NOT NULL DEFAULT FALSE,
  apto_mascotas BOOLEAN NOT NULL DEFAULT FALSE,
  esterilizado  BOOLEAN NOT NULL DEFAULT FALSE,
  vacunado      BOOLEAN NOT NULL DEFAULT FALSE,
  desparasitado BOOLEAN NOT NULL DEFAULT FALSE,
  disponible    BOOLEAN NOT NULL DEFAULT TRUE,
  estado        ENUM('borrador','disponible','reservado','adoptado','en_tratamiento') NOT NULL DEFAULT 'disponible',
  fecha_ingreso DATE NULL,
  PRIMARY KEY (id),
  KEY idx_mascotas_especie (especie_id),
  CONSTRAINT fk_mascotas_especie
    FOREIGN KEY (especie_id) REFERENCES especies (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabla para galería de múltiples fotos por mascota
CREATE TABLE mascota_fotos (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  mascota_id INT UNSIGNED NOT NULL,
  ruta       VARCHAR(255) NOT NULL,
  es_portada BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (id),
  KEY idx_fotos_mascota (mascota_id),
  CONSTRAINT fk_fotos_mascota
    FOREIGN KEY (mascota_id) REFERENCES mascotas (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE solicitudes_adopcion (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  mascota_id INT UNSIGNED NOT NULL,
  fecha      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  motivo     TEXT NULL,
  estado     ENUM('pendiente','aceptada','cancelada') NOT NULL DEFAULT 'pendiente',
  fecha_resolucion DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_solicitudes_usuario (usuario_id),
  KEY idx_solicitudes_mascota (mascota_id),
  CONSTRAINT fk_solicitudes_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_solicitudes_mascota
    FOREIGN KEY (mascota_id) REFERENCES mascotas (id)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE guardados (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id INT UNSIGNED NOT NULL,
  mascota_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_guardado (usuario_id, mascota_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (mascota_id) REFERENCES mascotas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Asegurar especies base
INSERT IGNORE INTO especies (id, nombre) VALUES (1, 'Perro'), (2, 'Gato');