-- Inventario TI
-- Fase 7.1
-- Sedes, tipos de ubicacion y ubicaciones
--
-- Motor objetivo: MariaDB
-- Codificacion: utf8mb4
-- Collation: utf8mb4_unicode_ci
--
-- Importante:
-- MariaDB realiza commits implicitos para operaciones DDL.
-- Esta migracion debe ejecutarse una sola vez y con respaldo previo.

SET NAMES utf8mb4;
SET time_zone = '-05:00';

CREATE TABLE sedes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    codigo VARCHAR(30) NOT NULL,

    nombre VARCHAR(150) NOT NULL,

    descripcion VARCHAR(500) NULL,

    direccion VARCHAR(255) NULL,

    ciudad VARCHAR(120) NULL,

    departamento VARCHAR(120) NULL,

    activo TINYINT(1) NOT NULL DEFAULT 1,

    creado_en DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    actualizado_en DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT uq_sedes_codigo
        UNIQUE (codigo),

    CONSTRAINT chk_sedes_codigo_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(codigo)) > 0
        ),

    CONSTRAINT chk_sedes_nombre_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(nombre)) > 0
        ),

    CONSTRAINT chk_sedes_activo
        CHECK (
            activo IN (0, 1)
        ),

    INDEX idx_sedes_nombre (
        nombre
    ),

    INDEX idx_sedes_ciudad_activo (
        ciudad,
        activo
    ),

    INDEX idx_sedes_departamento_activo (
        departamento,
        activo
    ),

    INDEX idx_sedes_activo_nombre (
        activo,
        nombre
    )
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


CREATE TABLE tipos_ubicacion (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    codigo VARCHAR(30) NOT NULL,

    nombre VARCHAR(80) NOT NULL,

    descripcion VARCHAR(255) NULL,

    activo TINYINT(1) NOT NULL DEFAULT 1,

    creado_en DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    actualizado_en DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT uq_tipos_ubicacion_codigo
        UNIQUE (codigo),

    CONSTRAINT uq_tipos_ubicacion_nombre
        UNIQUE (nombre),

    CONSTRAINT chk_tipos_ubicacion_codigo_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(codigo)) > 0
        ),

    CONSTRAINT chk_tipos_ubicacion_nombre_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(nombre)) > 0
        ),

    CONSTRAINT chk_tipos_ubicacion_activo
        CHECK (
            activo IN (0, 1)
        ),

    INDEX idx_tipos_ubicacion_activo_nombre (
        activo,
        nombre
    )
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


CREATE TABLE ubicaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    sede_id BIGINT UNSIGNED NOT NULL,

    tipo_ubicacion_id BIGINT UNSIGNED NOT NULL,

    ubicacion_padre_id BIGINT UNSIGNED NULL,

    codigo VARCHAR(50) NOT NULL,

    nombre VARCHAR(150) NOT NULL,

    descripcion VARCHAR(500) NULL,

    activo TINYINT(1) NOT NULL DEFAULT 1,

    creado_en DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    actualizado_en DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),

    CONSTRAINT uq_ubicaciones_sede_codigo
        UNIQUE (
            sede_id,
            codigo
        ),

    CONSTRAINT fk_ubicaciones_sede
        FOREIGN KEY (sede_id)
        REFERENCES sedes (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    CONSTRAINT fk_ubicaciones_tipo
        FOREIGN KEY (tipo_ubicacion_id)
        REFERENCES tipos_ubicacion (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    CONSTRAINT fk_ubicaciones_padre
        FOREIGN KEY (ubicacion_padre_id)
        REFERENCES ubicaciones (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    CONSTRAINT chk_ubicaciones_codigo_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(codigo)) > 0
        ),

    CONSTRAINT chk_ubicaciones_nombre_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(nombre)) > 0
        ),

    CONSTRAINT chk_ubicaciones_activo
        CHECK (
            activo IN (0, 1)
        ),

    CONSTRAINT chk_ubicaciones_padre_distinto
        CHECK (
            ubicacion_padre_id IS NULL
            OR ubicacion_padre_id <> id
        ),

    INDEX idx_ubicaciones_sede_activo (
        sede_id,
        activo
    ),

    INDEX idx_ubicaciones_tipo_activo (
        tipo_ubicacion_id,
        activo
    ),

    INDEX idx_ubicaciones_padre (
        ubicacion_padre_id
    ),

    INDEX idx_ubicaciones_sede_padre (
        sede_id,
        ubicacion_padre_id
    ),

    INDEX idx_ubicaciones_sede_nombre (
        sede_id,
        nombre
    ),

    INDEX idx_ubicaciones_activo_nombre (
        activo,
        nombre
    )
) ENGINE = InnoDB
  DEFAULT CHARACTER SET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


INSERT INTO tipos_ubicacion (
    codigo,
    nombre,
    descripcion,
    activo
) VALUES
    (
        'PISO',
        'Piso',
        'Piso o nivel fisico dentro de una sede.',
        1
    ),
    (
        'AREA',
        'Area',
        'Area organizacional o funcional.',
        1
    ),
    (
        'OFICINA',
        'Oficina',
        'Oficina o espacio administrativo.',
        1
    ),
    (
        'SALA',
        'Sala',
        'Sala de reuniones, capacitacion u otro uso.',
        1
    ),
    (
        'BODEGA',
        'Bodega',
        'Espacio de almacenamiento o custodia.',
        1
    ),
    (
        'PUESTO',
        'Puesto',
        'Puesto de trabajo o ubicacion individual.',
        1
    ),
    (
        'OTRO',
        'Otro',
        'Tipo de ubicacion no incluido en el catalogo principal.',
        1
    );