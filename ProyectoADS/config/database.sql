-- =========================================================================
-- SISTEMA: HospitalNet
-- ASIGNATURA: Análisis y Diseño de Sistemas
-- ESPECIFICACIÓN: Script de Base de Datos (Normalizado en 3FN)
-- =========================================================================

CREATE DATABASE IF NOT EXISTS hospitalnet_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hospitalnet_db;

-- -------------------------------------------------------------------------
-- 1. TABLA: roles
-- Define los perfiles de acceso (RF-04, TN-10)
-- -------------------------------------------------------------------------
CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT,
    nombre_rol VARCHAR(30) NOT NULL, -- 'Administrador', 'Médico General', 'Médico Especialista'
    descripcion VARCHAR(255) NULL,
    PRIMARY KEY (id_rol),
    UNIQUE KEY uk_nombre_rol (nombre_rol)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- 2. TABLA: usuarios
-- Gestiona las cuentas del personal médico y administrativo (RF-01, RF-03)
-- RNF-01: Contraseñas almacenadas con hash bcrypt (requiere 60 caracteres)
-- -------------------------------------------------------------------------
CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT,
    id_rol INT NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    cedula_profesional VARCHAR(20) NULL, -- NULL para administradores
    especialidad VARCHAR(50) NULL,       -- NULL para médicos generales/administradores
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(60) NOT NULL,  -- Hash seguro Bcrypt (RNF-01)
    activo TINYINT(1) DEFAULT 1,         -- Para activar/desactivar cuentas (RF-03, RN-05)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uk_email (email),
    UNIQUE KEY uk_cedula (cedula_profesional),
    CONSTRAINT fk_usuarios_roles FOREIGN KEY (id_rol) REFERENCES roles (id_rol) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- 3. TABLA: pacientes
-- Almacena los datos demográficos y el número de expediente (RF-05, TN-01)
-- RN-02 / ERR-05: Unicidad estricta de la CURP
-- -------------------------------------------------------------------------
CREATE TABLE pacientes (
    id_paciente INT AUTO_INCREMENT, -- Funciona como identificador de Expediente Clínico (TN-02)
    nombre_completo VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    sexo ENUM('Masculino', 'Femenino', 'Otro') NOT NULL,
    curp CHAR(18) NOT NULL, -- Formato oficial de México (ERR-06)
    telefono VARCHAR(15) NOT NULL,
    email VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_paciente),
    UNIQUE KEY uk_curp (curp) -- Restricción de unicidad de CURP (RN-02)
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- 4. TABLA: alergias
-- Registra las reacciones adversas del paciente (RF-10, TN-12)
-- Relación de 1 a Muchos con Pacientes para cumplir 3FN
-- -------------------------------------------------------------------------
CREATE TABLE alergias (
    id_alergia INT AUTO_INCREMENT,
    id_paciente INT NOT NULL,
    sustancia VARCHAR(100) NOT NULL, -- Medicamento, alimento, etc.
    observaciones TEXT NULL,
    PRIMARY KEY (id_alergia),
    CONSTRAINT fk_alergias_pacientes FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- 5. TABLA: consultas
-- Registra el encuentro formal médico-paciente (RF-08, TN-03)
-- RN-03 / ERR-10: Soporta la validación del tiempo de edición mediante 'created_at'
-- -------------------------------------------------------------------------
CREATE TABLE consultas (
    id_consulta INT AUTO_INCREMENT,
    id_paciente INT NOT NULL,
    id_medico INT NOT NULL, -- FK a usuarios (debe ser rol Médico)
    motivo_consulta TEXT NOT NULL,
    signos_vitales VARCHAR(255) NOT NULL, -- Presión, temperatura, FC, FR
    exploracion_fisica TEXT NOT NULL,
    codigo_cie10 VARCHAR(10) NOT NULL, -- Código internacional estandarizado (TN-14, ERR-10)
    diagnostico_descripcion TEXT NOT NULL,
    observaciones TEXT NULL,
    activa TINYINT(1) DEFAULT 1, -- 1 = Abierta/Activa, 0 = Con Alta Médica (RN-06, TN-16)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_consulta),
    CONSTRAINT fk_consultas_pacientes FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_consultas_medicos FOREIGN KEY (id_medico) REFERENCES usuarios (id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- 6. TABLA: prescripciones
-- Recetas médicas digitales vinculadas a una consulta activa (RF-18, TN-08)
-- RN-06 / ERR-11: Dependencia estricta de una consulta del día
-- -------------------------------------------------------------------------
CREATE TABLE prescripciones (
    id_prescripcion INT AUTO_INCREMENT,
    id_consulta INT NOT NULL, -- Relación directa con la consulta que la originó (3FN)
    medicamento VARCHAR(150) NOT NULL,
    dosis VARCHAR(100) NOT NULL,
    frecuencia VARCHAR(100) NOT NULL,
    duracion VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_prescripcion),
    CONSTRAINT fk_prescripciones_consultas FOREIGN KEY (id_consulta) REFERENCES consultas (id_consulta) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- 7. TABLA: referencias_medicas
-- Derivaciones de médico general a especialista (RF-11, RF-12, TN-07)
-- -------------------------------------------------------------------------
CREATE TABLE referencias_medicas (
    id_referencia INT AUTO_INCREMENT,
    id_paciente INT NOT NULL,
    id_medico_emisor INT NOT NULL,    -- Médico General
    id_medico_receptor INT NOT NULL,  -- Médico Especialista
    motivo_clinico TEXT NOT NULL,
    nivel_urgencia ENUM('Normal', 'Prioritario', 'Urgente') NOT NULL, -- Catálogo Triage (TN-13)
    estado ENUM('Pendiente', 'Aceptada', 'Rechazada') DEFAULT 'Pendiente', -- (RF-13)
    motivo_rechazo TEXT NULL, -- Obligatorio si el estado es 'Rechazada'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_referencia),
    CONSTRAINT fk_referencias_pacientes FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_referencias_emisor FOREIGN KEY (id_medico_emisor) REFERENCES usuarios (id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_referencias_receptor FOREIGN KEY (id_medico_receptor) REFERENCES usuarios (id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- -------------------------------------------------------------------------
-- 8. TABLA: citas
-- Gestión de agenda del hospital (RF-14, TN-09)
-- RN-04 / ERR-07: Se validará mediante software que no existan traslapes < 30 min
-- -------------------------------------------------------------------------
CREATE TABLE citas (
    id_cita INT AUTO_INCREMENT,
    id_paciente INT NOT NULL,
    id_medico INT NOT NULL,
    fecha_hora DATETIME NOT NULL, -- Combina fecha y hora para facilitar cálculos de traslape
    estado ENUM('Programada', 'Modificada', 'Cancelada', 'Atendida') DEFAULT 'Programada',
    motivo_cancelacion VARCHAR(255) NULL, -- Requerido si el estado cambia a Cancelada (RF-17)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_cita),
    CONSTRAINT fk_citas_pacientes FOREIGN KEY (id_paciente) REFERENCES pacientes (id_paciente) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_citas_medicos FOREIGN KEY (id_medico) REFERENCES usuarios (id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================================================================
-- INSERCIÓN DE DATOS INICIALES (SEMILLAS / SEEDS)
-- =========================================================================

-- Insertar Roles Institucionales obligatorios
INSERT INTO roles (nombre_rol, descripcion) VALUES 
('Administrador', 'Acceso total y gestión de usuarios del sistema'),
('Médico General', 'Atención de primer nivel, expedientes y referencias'),
('Médico Especialista', 'Atención especializada de pacientes referidos');

-- Insertar Usuario Administrador por defecto 
-- Contraseña por defecto: 'Admin1234' (Hash Bcrypt real generado con costo 10)
INSERT INTO usuarios (id_rol, nombre_completo, cedula_profesional, especialidad, email, password_hash, activo) VALUES 
(1, 'Administrador General HospitalNet', NULL, NULL, 'admin@hospitalnet.com', '$2y$10$7R0Zf9OqX8KzExm8bH6fOuxXbC.C8YvV6f1fRBeKmWnU7gqL/5eG.', 1);