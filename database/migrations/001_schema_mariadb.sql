-- =============================================================
-- Inventario TI - Esquema inicial para MariaDB 11.4
-- Archivo: database/migrations/001_schema_mariadb.sql
-- Base objetivo: cgi_db01 (seleccionada al ejecutar el cliente)
-- No incluye CREATE DATABASE ni credenciales.
-- =============================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-05:00';
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================
-- Seguridad y usuarios locales
-- =============================================================

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_codigo (codigo),
    UNIQUE KEY uq_roles_nombre (nombre),
    CONSTRAINT chk_roles_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rol_id BIGINT UNSIGNED NOT NULL,
    nombre_usuario VARCHAR(100) NOT NULL,
    nombre_completo VARCHAR(200) NOT NULL,
    correo VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 1,
    intentos_fallidos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME NULL,
    ultimo_acceso_en DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_nombre_usuario (nombre_usuario),
    UNIQUE KEY uq_usuarios_correo (correo),
    KEY idx_usuarios_rol_activo (rol_id, activo),
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_usuarios_activo CHECK (activo IN (0,1)),
    CONSTRAINT chk_usuarios_cambio_password CHECK (debe_cambiar_password IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sesiones_usuario (
    id CHAR(64) NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    direccion_ip VARCHAR(45) NULL,
    agente_usuario VARCHAR(500) NULL,
    iniciada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultima_actividad_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_en DATETIME NOT NULL,
    cerrada_en DATETIME NULL,
    motivo_cierre VARCHAR(100) NULL,
    PRIMARY KEY (id),
    KEY idx_sesiones_usuario_activas (usuario_id, cerrada_en, expira_en),
    CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Organización
-- =============================================================

CREATE TABLE IF NOT EXISTS sedes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_por BIGINT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sedes_nombre (nombre),
    KEY idx_sedes_activo (activo),
    CONSTRAINT fk_sedes_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_sedes_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ubicaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sede_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(160) NOT NULL,
    detalle VARCHAR(255) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_por BIGINT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ubicaciones_sede_nombre (sede_id, nombre),
    KEY idx_ubicaciones_sede_activo (sede_id, activo),
    CONSTRAINT fk_ubicaciones_sede FOREIGN KEY (sede_id) REFERENCES sedes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_ubicaciones_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_ubicaciones_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS personas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sede_id BIGINT UNSIGNED NOT NULL,
    ubicacion_id BIGINT UNSIGNED NOT NULL,
    nombre_completo VARCHAR(200) NOT NULL,
    cargo VARCHAR(160) NOT NULL,
    area_dependencia VARCHAR(160) NULL,
    correo_institucional VARCHAR(254) NOT NULL,
    telefono_extension VARCHAR(50) NULL,
    tipo_documento VARCHAR(30) NULL,
    numero_documento VARCHAR(50) NULL,
    observaciones TEXT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_por BIGINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_personas_correo (correo_institucional),
    UNIQUE KEY uq_personas_documento (tipo_documento, numero_documento),
    KEY idx_personas_nombre (nombre_completo),
    KEY idx_personas_sede (sede_id),
    KEY idx_personas_ubicacion (ubicacion_id),
    KEY idx_personas_activo (activo),
    CONSTRAINT fk_personas_sede FOREIGN KEY (sede_id) REFERENCES sedes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_personas_ubicacion FOREIGN KEY (ubicacion_id) REFERENCES ubicaciones(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_personas_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_personas_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Catálogos de inventario
-- =============================================================

CREATE TABLE IF NOT EXISTS tipos_activo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    es_periferico TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipos_activo_codigo (codigo),
    UNIQUE KEY uq_tipos_activo_nombre (nombre),
    CONSTRAINT chk_tipos_activo_periferico CHECK (es_periferico IN (0,1)),
    CONSTRAINT chk_tipos_activo_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS estados_activo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    permite_asignacion TINYINT(1) NOT NULL DEFAULT 0,
    es_estado_final TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estados_activo_codigo (codigo),
    UNIQUE KEY uq_estados_activo_nombre (nombre),
    CONSTRAINT chk_estados_permite_asignacion CHECK (permite_asignacion IN (0,1)),
    CONSTRAINT chk_estados_final CHECK (es_estado_final IN (0,1)),
    CONSTRAINT chk_estados_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_solicitud (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    requiere_ticket_glpi TINYINT(1) NOT NULL DEFAULT 0,
    requiere_caso_necsoft TINYINT(1) NOT NULL DEFAULT 0,
    requiere_evidencia TINYINT(1) NOT NULL DEFAULT 1,
    requiere_autorizacion TINYINT(1) NOT NULL DEFAULT 0,
    permite_entrega_directa TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipos_solicitud_codigo (codigo),
    UNIQUE KEY uq_tipos_solicitud_nombre (nombre),
    CONSTRAINT chk_tipo_sol_ticket CHECK (requiere_ticket_glpi IN (0,1)),
    CONSTRAINT chk_tipo_sol_necsoft CHECK (requiere_caso_necsoft IN (0,1)),
    CONSTRAINT chk_tipo_sol_evidencia CHECK (requiere_evidencia IN (0,1)),
    CONSTRAINT chk_tipo_sol_autorizacion CHECK (requiere_autorizacion IN (0,1)),
    CONSTRAINT chk_tipo_sol_directa CHECK (permite_entrega_directa IN (0,1)),
    CONSTRAINT chk_tipo_sol_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Activos
-- =============================================================

CREATE TABLE IF NOT EXISTS activos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo_activo_id BIGINT UNSIGNED NOT NULL,
    estado_activo_id BIGINT UNSIGNED NOT NULL,
    sede_actual_id BIGINT UNSIGNED NOT NULL,
    ubicacion_actual_id BIGINT UNSIGNED NOT NULL,
    asignado_actual_id BIGINT UNSIGNED NULL,
    responsable_tecnologia_id BIGINT UNSIGNED NOT NULL,
    placa VARCHAR(100) NOT NULL,
    serial VARCHAR(150) NOT NULL,
    subtipo_periferico VARCHAR(80) NULL,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(150) NOT NULL,
    clasificacion_equipo VARCHAR(10) NOT NULL DEFAULT 'N/A',
    ram_gb SMALLINT UNSIGNED NULL,
    procesador_fabricante VARCHAR(80) NULL,
    procesador_familia VARCHAR(100) NULL,
    procesador_modelo VARCHAR(120) NULL,
    procesador_generacion VARCHAR(80) NULL,
    sistema_operativo VARCHAR(150) NULL,
    nombre_equipo_red VARCHAR(150) NULL,
    monitor_pulgadas DECIMAL(4,1) NULL,
    fecha_inventario DATE NOT NULL,
    condicion_general VARCHAR(10) NOT NULL DEFAULT 'ACTIVO',
    observaciones TEXT NULL,
    creado_por BIGINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_por BIGINT UNSIGNED NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_activos_placa (placa),
    UNIQUE KEY uq_activos_serial (serial),
    KEY idx_activos_tipo (tipo_activo_id),
    KEY idx_activos_estado (estado_activo_id),
    KEY idx_activos_sede_estado (sede_actual_id, estado_activo_id),
    KEY idx_activos_ubicacion (ubicacion_actual_id),
    KEY idx_activos_asignado (asignado_actual_id),
    KEY idx_activos_responsable (responsable_tecnologia_id),
    KEY idx_activos_fecha_inventario (fecha_inventario),
    KEY idx_activos_marca_modelo (marca, modelo),
    CONSTRAINT fk_activos_tipo FOREIGN KEY (tipo_activo_id) REFERENCES tipos_activo(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_activos_estado FOREIGN KEY (estado_activo_id) REFERENCES estados_activo(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_activos_sede FOREIGN KEY (sede_actual_id) REFERENCES sedes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_activos_ubicacion FOREIGN KEY (ubicacion_actual_id) REFERENCES ubicaciones(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_activos_asignado FOREIGN KEY (asignado_actual_id) REFERENCES personas(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_activos_responsable FOREIGN KEY (responsable_tecnologia_id) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_activos_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_activos_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_activos_clasificacion CHECK (clasificacion_equipo IN ('TIPO_1','TIPO_2','N/A')),
    CONSTRAINT chk_activos_condicion CHECK (condicion_general IN ('ACTIVO','INACTIVO')),
    CONSTRAINT chk_activos_ram CHECK (ram_gb IS NULL OR ram_gb > 0),
    CONSTRAINT chk_activos_monitor CHECK (monitor_pulgadas IS NULL OR monitor_pulgadas > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Solicitudes y entregas
-- =============================================================

CREATE TABLE IF NOT EXISTS solicitudes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo_solicitud_id BIGINT UNSIGNED NOT NULL,
    responsable_tecnologia_id BIGINT UNSIGNED NOT NULL,
    sede_destino_id BIGINT UNSIGNED NOT NULL,
    fecha_solicitud DATE NOT NULL,
    estado_entrega VARCHAR(20) NOT NULL DEFAULT 'ABIERTO',
    ticket_glpi VARCHAR(100) NULL,
    caso_necsoft VARCHAR(100) NULL,
    descripcion_solicitud TEXT NOT NULL,
    fecha_limite DATE NULL,
    es_entrega_directa TINYINT(1) NOT NULL DEFAULT 0,
    creado_por BIGINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_por BIGINT UNSIGNED NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_solicitudes_ticket_glpi (ticket_glpi),
    UNIQUE KEY uq_solicitudes_caso_necsoft (caso_necsoft),
    KEY idx_solicitudes_fecha (fecha_solicitud),
    KEY idx_solicitudes_estado_fecha (estado_entrega, fecha_solicitud),
    KEY idx_solicitudes_fecha_limite (estado_entrega, fecha_limite),
    KEY idx_solicitudes_tipo (tipo_solicitud_id),
    KEY idx_solicitudes_sede (sede_destino_id),
    KEY idx_solicitudes_responsable (responsable_tecnologia_id),
    CONSTRAINT fk_solicitudes_tipo FOREIGN KEY (tipo_solicitud_id) REFERENCES tipos_solicitud(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_solicitudes_responsable FOREIGN KEY (responsable_tecnologia_id) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_solicitudes_sede FOREIGN KEY (sede_destino_id) REFERENCES sedes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_solicitudes_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_solicitudes_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_solicitudes_estado CHECK (estado_entrega IN ('ABIERTO','EN_PROCESO','ENTREGADO')),
    CONSTRAINT chk_solicitudes_directa CHECK (es_entrega_directa IN (0,1)),
    CONSTRAINT chk_solicitudes_limite CHECK (fecha_limite IS NULL OR fecha_limite >= fecha_solicitud)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitud_lineas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitud_id BIGINT UNSIGNED NOT NULL,
    tipo_activo_id BIGINT UNSIGNED NOT NULL,
    equipo_solicitado VARCHAR(100) NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    tipo_equipo VARCHAR(10) NOT NULL DEFAULT 'N/A',
    estado_linea VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    observaciones TEXT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lineas_solicitud (solicitud_id),
    KEY idx_lineas_estado (estado_linea),
    KEY idx_lineas_tipo_activo (tipo_activo_id),
    CONSTRAINT fk_lineas_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_lineas_tipo_activo FOREIGN KEY (tipo_activo_id) REFERENCES tipos_activo(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_lineas_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_lineas_tipo_equipo CHECK (tipo_equipo IN ('TIPO_1','TIPO_2','N/A')),
    CONSTRAINT chk_lineas_estado CHECK (estado_linea IN ('PENDIENTE','ENTREGADA'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entregas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitud_id BIGINT UNSIGNED NOT NULL,
    receptor_id BIGINT UNSIGNED NOT NULL,
    asignado_a_id BIGINT UNSIGNED NOT NULL,
    responsable_tecnologia_id BIGINT UNSIGNED NOT NULL,
    sede_destino_id BIGINT UNSIGNED NOT NULL,
    ubicacion_destino_id BIGINT UNSIGNED NOT NULL,
    fecha_entrega DATE NOT NULL,
    observaciones TEXT NULL,
    creado_por BIGINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_entregas_solicitud (solicitud_id),
    KEY idx_entregas_fecha (fecha_entrega),
    KEY idx_entregas_receptor (receptor_id),
    KEY idx_entregas_asignado (asignado_a_id),
    CONSTRAINT fk_entregas_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_entregas_receptor FOREIGN KEY (receptor_id) REFERENCES personas(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_entregas_asignado FOREIGN KEY (asignado_a_id) REFERENCES personas(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_entregas_responsable FOREIGN KEY (responsable_tecnologia_id) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_entregas_sede FOREIGN KEY (sede_destino_id) REFERENCES sedes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_entregas_ubicacion FOREIGN KEY (ubicacion_destino_id) REFERENCES ubicaciones(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_entregas_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrega_lineas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entrega_id BIGINT UNSIGNED NOT NULL,
    solicitud_linea_id BIGINT UNSIGNED NOT NULL,
    cantidad_entregada INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_linea_entregada_una_vez (solicitud_linea_id),
    KEY idx_entrega_lineas_entrega (entrega_id),
    CONSTRAINT fk_entrega_lineas_entrega FOREIGN KEY (entrega_id) REFERENCES entregas(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_entrega_lineas_solicitud FOREIGN KEY (solicitud_linea_id) REFERENCES solicitud_lineas(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_entrega_lineas_cantidad CHECK (cantidad_entregada > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS entrega_activos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entrega_linea_id BIGINT UNSIGNED NOT NULL,
    activo_id BIGINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entrega_linea_activo (entrega_linea_id, activo_id),
    KEY idx_entrega_activos_activo (activo_id),
    CONSTRAINT fk_entrega_activos_linea FOREIGN KEY (entrega_linea_id) REFERENCES entrega_lineas(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_entrega_activos_activo FOREIGN KEY (activo_id) REFERENCES activos(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Movimientos, mantenimiento y bajas
-- =============================================================

CREATE TABLE IF NOT EXISTS movimientos_activo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    activo_id BIGINT UNSIGNED NOT NULL,
    tipo_movimiento VARCHAR(40) NOT NULL,
    fecha_movimiento DATETIME NOT NULL,
    sede_anterior_id BIGINT UNSIGNED NULL,
    sede_nueva_id BIGINT UNSIGNED NULL,
    ubicacion_anterior_id BIGINT UNSIGNED NULL,
    ubicacion_nueva_id BIGINT UNSIGNED NULL,
    persona_anterior_id BIGINT UNSIGNED NULL,
    persona_nueva_id BIGINT UNSIGNED NULL,
    estado_anterior_id BIGINT UNSIGNED NULL,
    estado_nuevo_id BIGINT UNSIGNED NULL,
    solicitud_id BIGINT UNSIGNED NULL,
    entrega_id BIGINT UNSIGNED NULL,
    responsable_tecnologia_id BIGINT UNSIGNED NOT NULL,
    observaciones TEXT NULL,
    creado_por BIGINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_movimientos_activo_fecha (activo_id, fecha_movimiento),
    KEY idx_movimientos_tipo_fecha (tipo_movimiento, fecha_movimiento),
    KEY idx_movimientos_solicitud (solicitud_id),
    KEY idx_movimientos_entrega (entrega_id),
    CONSTRAINT fk_movimientos_activo FOREIGN KEY (activo_id) REFERENCES activos(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_sede_anterior FOREIGN KEY (sede_anterior_id) REFERENCES sedes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_sede_nueva FOREIGN KEY (sede_nueva_id) REFERENCES sedes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_ubicacion_anterior FOREIGN KEY (ubicacion_anterior_id) REFERENCES ubicaciones(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_ubicacion_nueva FOREIGN KEY (ubicacion_nueva_id) REFERENCES ubicaciones(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_persona_anterior FOREIGN KEY (persona_anterior_id) REFERENCES personas(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_persona_nueva FOREIGN KEY (persona_nueva_id) REFERENCES personas(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_estado_anterior FOREIGN KEY (estado_anterior_id) REFERENCES estados_activo(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_estado_nuevo FOREIGN KEY (estado_nuevo_id) REFERENCES estados_activo(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_solicitud FOREIGN KEY (solicitud_id) REFERENCES solicitudes(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_entrega FOREIGN KEY (entrega_id) REFERENCES entregas(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_responsable FOREIGN KEY (responsable_tecnologia_id) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mov_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_movimientos_tipo CHECK (tipo_movimiento IN (
        'REGISTRO_INICIAL','ENTREGA','ASIGNACION','TRASLADO','DEVOLUCION',
        'ENTRADA_MANTENIMIENTO','SALIDA_MANTENIMIENTO','BAJA','REVERSION_ADMINISTRATIVA'
    ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mantenimientos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    activo_id BIGINT UNSIGNED NOT NULL,
    responsable_tecnologia_id BIGINT UNSIGNED NOT NULL,
    tipo_mantenimiento VARCHAR(100) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_finalizacion DATE NULL,
    diagnostico TEXT NOT NULL,
    resultado TEXT NULL,
    proveedor VARCHAR(200) NULL,
    ticket_glpi VARCHAR(100) NULL,
    caso_necsoft VARCHAR(100) NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'ABIERTO',
    observaciones TEXT NULL,
    creado_por BIGINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mantenimientos_activo_estado (activo_id, estado),
    KEY idx_mantenimientos_fecha (fecha_inicio),
    KEY idx_mantenimientos_ticket (ticket_glpi),
    KEY idx_mantenimientos_caso (caso_necsoft),
    CONSTRAINT fk_mantenimientos_activo FOREIGN KEY (activo_id) REFERENCES activos(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mantenimientos_responsable FOREIGN KEY (responsable_tecnologia_id) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_mantenimientos_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_mantenimientos_estado CHECK (estado IN ('ABIERTO','FINALIZADO','CANCELADO')),
    CONSTRAINT chk_mantenimientos_fechas CHECK (fecha_finalizacion IS NULL OR fecha_finalizacion >= fecha_inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS solicitudes_baja (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    activo_id BIGINT UNSIGNED NOT NULL,
    solicitado_por BIGINT UNSIGNED NOT NULL,
    revisado_por BIGINT UNSIGNED NULL,
    fecha_solicitud DATE NOT NULL,
    fecha_revision DATE NULL,
    fecha_baja DATE NULL,
    motivo TEXT NOT NULL,
    observacion_revision TEXT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bajas_activo (activo_id),
    KEY idx_bajas_estado_fecha (estado, fecha_solicitud),
    CONSTRAINT fk_bajas_activo FOREIGN KEY (activo_id) REFERENCES activos(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_bajas_solicitante FOREIGN KEY (solicitado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT fk_bajas_revisor FOREIGN KEY (revisado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_bajas_estado CHECK (estado IN ('PENDIENTE','APROBADA','RECHAZADA','CANCELADA')),
    CONSTRAINT chk_bajas_revision CHECK (fecha_revision IS NULL OR fecha_revision >= fecha_solicitud),
    CONSTRAINT chk_bajas_aprobada CHECK (
        estado <> 'APROBADA' OR (revisado_por IS NOT NULL AND fecha_revision IS NOT NULL AND fecha_baja IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Evidencias, auditoría, informes y parámetros
-- =============================================================

CREATE TABLE IF NOT EXISTS archivos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_interno VARCHAR(255) NOT NULL,
    ruta_relativa VARCHAR(500) NOT NULL,
    tipo_mime VARCHAR(100) NOT NULL,
    extension VARCHAR(10) NOT NULL,
    tamano_bytes BIGINT UNSIGNED NOT NULL,
    hash_sha256 CHAR(64) NOT NULL,
    cargado_por BIGINT UNSIGNED NOT NULL,
    cargado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_archivos_nombre_interno (nombre_interno),
    KEY idx_archivos_hash (hash_sha256),
    KEY idx_archivos_fecha (cargado_en),
    CONSTRAINT fk_archivos_usuario FOREIGN KEY (cargado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_archivos_tamano CHECK (tamano_bytes > 0 AND tamano_bytes <= 10485760),
    CONSTRAINT chk_archivos_extension CHECK (extension IN ('pdf','jpg','jpeg','png')),
    CONSTRAINT chk_archivos_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS archivos_relacionados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    archivo_id BIGINT UNSIGNED NOT NULL,
    entidad_tipo VARCHAR(40) NOT NULL,
    entidad_id BIGINT UNSIGNED NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_archivo_relacion (archivo_id, entidad_tipo, entidad_id),
    KEY idx_archivos_relacionados_entidad (entidad_tipo, entidad_id),
    CONSTRAINT fk_archivos_relacionados_archivo FOREIGN KEY (archivo_id) REFERENCES archivos(id)
        ON UPDATE RESTRICT ON DELETE RESTRICT,
    CONSTRAINT chk_archivos_entidad CHECK (entidad_tipo IN (
        'SOLICITUD','ENTREGA','MOVIMIENTO','MANTENIMIENTO','SOLICITUD_BAJA'
    ))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    fecha_evento DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    direccion_ip VARCHAR(45) NULL,
    agente_usuario VARCHAR(500) NULL,
    modulo VARCHAR(80) NOT NULL,
    accion VARCHAR(80) NOT NULL,
    entidad_tipo VARCHAR(80) NULL,
    entidad_id BIGINT UNSIGNED NULL,
    valores_anteriores LONGTEXT NULL,
    valores_nuevos LONGTEXT NULL,
    resultado VARCHAR(20) NOT NULL,
    motivo VARCHAR(500) NULL,
    id_correlacion CHAR(36) NULL,
    PRIMARY KEY (id),
    KEY idx_auditoria_fecha (fecha_evento),
    KEY idx_auditoria_usuario_fecha (usuario_id, fecha_evento),
    KEY idx_auditoria_entidad (entidad_tipo, entidad_id, fecha_evento),
    KEY idx_auditoria_accion (accion, fecha_evento),
    KEY idx_auditoria_correlacion (id_correlacion),
    CONSTRAINT fk_auditoria_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_auditoria_resultado CHECK (resultado IN ('EXITOSO','FALLIDO')),
    CONSTRAINT chk_auditoria_json_anterior CHECK (valores_anteriores IS NULL OR JSON_VALID(valores_anteriores)),
    CONSTRAINT chk_auditoria_json_nuevo CHECK (valores_nuevos IS NULL OR JSON_VALID(valores_nuevos))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS informes_guardados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    tipo_informe VARCHAR(60) NOT NULL,
    filtros_json LONGTEXT NOT NULL,
    columnas_json LONGTEXT NULL,
    formato_preferido VARCHAR(10) NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_informes_usuario_nombre (usuario_id, nombre),
    KEY idx_informes_usuario_tipo (usuario_id, tipo_informe),
    CONSTRAINT fk_informes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE CASCADE,
    CONSTRAINT chk_informes_filtros_json CHECK (JSON_VALID(filtros_json)),
    CONSTRAINT chk_informes_columnas_json CHECK (columnas_json IS NULL OR JSON_VALID(columnas_json)),
    CONSTRAINT chk_informes_formato CHECK (formato_preferido IS NULL OR formato_preferido IN ('CSV','XLSX','PDF')),
    CONSTRAINT chk_informes_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dias_festivos (
    fecha DATE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (fecha),
    CONSTRAINT chk_festivos_activo CHECK (activo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS parametros_sistema (
    clave VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    tipo_dato VARCHAR(20) NOT NULL,
    descripcion VARCHAR(255) NULL,
    actualizado_por BIGINT UNSIGNED NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (clave),
    CONSTRAINT fk_parametros_usuario FOREIGN KEY (actualizado_por) REFERENCES usuarios(id)
        ON UPDATE RESTRICT ON DELETE SET NULL,
    CONSTRAINT chk_parametros_tipo CHECK (tipo_dato IN ('TEXTO','ENTERO','BOOLEANO','JSON'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Datos iniciales confirmados
-- =============================================================

INSERT IGNORE INTO roles (codigo, nombre, descripcion) VALUES
('ADMINISTRADOR', 'Administrador', 'Acceso completo al sistema'),
('TECNICO', 'Técnico', 'Gestión operativa del inventario');

INSERT IGNORE INTO sedes (nombre) VALUES
('Administrativa'),('Américas'),('Armenia'),('Cali'),('Chicó'),('Chía'),
('Pereira'),('Pontevedra'),('Manizales'),('Tunja'),('Normandía');

INSERT IGNORE INTO tipos_activo (codigo, nombre, es_periferico) VALUES
('PORTATIL', 'Portátil', 0),
('AIO', 'AIO', 0),
('MONITOR', 'Monitor', 0),
('PERIFERICO', 'Periférico', 1);

INSERT IGNORE INTO estados_activo (codigo, nombre, permite_asignacion, es_estado_final) VALUES
('DISPONIBLE', 'Disponible', 1, 0),
('ASIGNADO', 'Asignado', 0, 0),
('DEVUELTO', 'Devuelto', 0, 0),
('EN_MANTENIMIENTO', 'En mantenimiento', 0, 0),
('DADO_DE_BAJA', 'Dado de baja', 0, 1),
('PENDIENTE_ENTREGA', 'Pendiente de entrega', 0, 0);

INSERT IGNORE INTO tipos_solicitud
(codigo,nombre,requiere_ticket_glpi,requiere_caso_necsoft,requiere_evidencia,requiere_autorizacion,permite_entrega_directa) VALUES
('EQUIPO_NUEVO','Equipo nuevo',0,1,1,0,0),
('REPOSICION_DANO','Reposición por daño',1,1,1,0,0),
('REPOSICION_PERDIDA','Reposición por pérdida o hurto',1,1,1,1,0),
('TRASLADO','Traslado',0,0,1,0,0),
('DEVOLUCION','Devolución',0,0,1,0,0),
('MANTENIMIENTO','Mantenimiento',1,1,1,0,0),
('BAJA','Baja',1,0,1,1,0),
('PRESTAMO','Préstamo temporal',0,0,1,0,0),
('CAMBIO_EQUIPO','Cambio de equipo',1,1,1,0,0),
('ENTREGA_DIRECTA','Asignación sin solicitud previa',0,0,1,0,1),
('OTRO','Otro',0,0,1,0,0);

INSERT IGNORE INTO parametros_sistema (clave,valor,tipo_dato,descripcion) VALUES
('DIAS_HABILES_VENCIMIENTO','15','ENTERO','Días hábiles para considerar vencida una solicitud'),
('MINUTOS_INACTIVIDAD_SESION','30','ENTERO','Minutos de inactividad antes del cierre de sesión'),
('HORAS_MAXIMAS_SESION','8','ENTERO','Duración máxima de una sesión'),
('MAXIMO_SESIONES_USUARIO','2','ENTERO','Máximo de sesiones simultáneas'),
('MAXIMO_ARCHIVOS_OPERACION','5','ENTERO','Máximo de evidencias por operación'),
('MAXIMO_BYTES_ARCHIVO','10485760','ENTERO','Tamaño máximo por archivo, 10 MB'),
('DIAS_AUDITORIA_EN_LINEA','90','ENTERO','Periodo mínimo de auditoría disponible en línea');

-- =============================================================
-- Vista compatible con los campos base de solicitudes
-- =============================================================

CREATE OR REPLACE VIEW vista_solicitudes_entregas AS
SELECT
    s.id AS solicitud_id,
    s.fecha_solicitud,
    e.fecha_entrega,
    s.estado_entrega,
    a.placa AS placa_equipo_entregado,
    a.serial AS serial_equipo_entregado,
    s.ticket_glpi,
    s.caso_necsoft,
    ts.nombre AS tipo_solicitud,
    s.descripcion_solicitud,
    sl.equipo_solicitado,
    sl.cantidad,
    sl.tipo_equipo,
    ur.nombre_completo AS responsable_tecnologia,
    s.sede_destino_id,
    pa.nombre_completo AS asignado_a,
    CONCAT_WS(' / ', pa.cargo, ub.nombre, se.nombre) AS cargo_ubicacion
FROM solicitudes s
INNER JOIN tipos_solicitud ts ON ts.id = s.tipo_solicitud_id
INNER JOIN solicitud_lineas sl ON sl.solicitud_id = s.id
INNER JOIN usuarios ur ON ur.id = s.responsable_tecnologia_id
LEFT JOIN entrega_lineas el ON el.solicitud_linea_id = sl.id
LEFT JOIN entregas e ON e.id = el.entrega_id
LEFT JOIN entrega_activos ea ON ea.entrega_linea_id = el.id
LEFT JOIN activos a ON a.id = ea.activo_id
LEFT JOIN personas pa ON pa.id = e.asignado_a_id
LEFT JOIN ubicaciones ub ON ub.id = e.ubicacion_destino_id
LEFT JOIN sedes se ON se.id = e.sede_destino_id;

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación sugerida después de importar:
-- SELECT VERSION();
-- SHOW TABLES;
-- SELECT COUNT(*) AS roles FROM roles;
-- SELECT COUNT(*) AS sedes FROM sedes;
-- SELECT COUNT(*) AS estados FROM estados_activo;
-- SELECT COUNT(*) AS tipos_solicitud FROM tipos_solicitud;
