-- Inventario TI
-- Fase 7.1
-- Adaptacion incremental de sedes y ubicaciones
--
-- Motor validado: MariaDB 11.4
-- Base validada: cgi_db01
--
-- Precondiciones:
-- - sedes existe con 11 registros.
-- - ubicaciones existe con 0 registros.
-- - tipos_ubicacion no existe.
-- - La sede 1 se llama Administrativa.
-- - Las sedes 2 a 11 conservan los nombres validados.
--
-- Esta migracion:
-- - No elimina tablas ni registros.
-- - Conserva todos los IDs y claves externas actuales.
-- - Renombra Administrativa a Calle 74.
-- - Asigna codigos institucionales a las 11 sedes existentes.
-- - Agrega Medellin como sede 12 con codigo 17.
-- - Crea tipos_ubicacion.
-- - Amplia ubicaciones con codigo, tipo y jerarquia.
--
-- MariaDB realiza commits implicitos para DDL.
-- Ejecutar una sola vez y con respaldo previo.

SET NAMES utf8mb4;
SET time_zone = '-05:00';


-- =========================================================
-- 1. AMPLIAR SEDES
-- =========================================================

ALTER TABLE sedes
    ADD COLUMN codigo VARCHAR(30) NULL
        AFTER id,

    ADD COLUMN direccion VARCHAR(255) NULL
        AFTER descripcion,

    ADD COLUMN ciudad VARCHAR(120) NULL
        AFTER direccion,

    ADD COLUMN departamento VARCHAR(120) NULL
        AFTER ciudad;


-- =========================================================
-- 2. ASIGNAR CATALOGO INSTITUCIONAL
-- =========================================================

UPDATE sedes
SET
    nombre = 'Calle 74',
    codigo = '15'
WHERE id = 1
  AND nombre = 'Administrativa';

UPDATE sedes
SET codigo = '10'
WHERE id = 2
  AND nombre = 'Américas';

UPDATE sedes
SET codigo = '05'
WHERE id = 3
  AND nombre = 'Armenia';

UPDATE sedes
SET codigo = '03'
WHERE id = 4
  AND nombre = 'Cali';

UPDATE sedes
SET codigo = '14'
WHERE id = 5
  AND nombre = 'Chicó';

UPDATE sedes
SET codigo = '16'
WHERE id = 6
  AND nombre = 'Chía';

UPDATE sedes
SET codigo = '04'
WHERE id = 7
  AND nombre = 'Pereira';

UPDATE sedes
SET codigo = '11'
WHERE id = 8
  AND nombre = 'Pontevedra';

UPDATE sedes
SET codigo = '08'
WHERE id = 9
  AND nombre = 'Manizales';

UPDATE sedes
SET codigo = '07'
WHERE id = 10
  AND nombre = 'Tunja';

UPDATE sedes
SET
    codigo = '02',
    activo = 1
WHERE id = 11
  AND nombre = 'Normandía';


-- =========================================================
-- 3. CREAR MEDELLIN COMO SEDE 12
-- =========================================================

INSERT INTO sedes (
    id,
    codigo,
    nombre,
    descripcion,
    direccion,
    ciudad,
    departamento,
    activo,
    creado_por
) VALUES (
    12,
    '17',
    'Medellín',
    NULL,
    NULL,
    'Medellín',
    'Antioquia',
    1,
    NULL
);

ALTER TABLE sedes
    AUTO_INCREMENT = 13;


-- =========================================================
-- 4. CONVERTIR CODIGO EN OBLIGATORIO
-- =========================================================
--
-- Este ALTER solo debe ejecutarse después de comprobar que
-- todos los registros recibieron un codigo institucional.

ALTER TABLE sedes
    MODIFY COLUMN codigo
        VARCHAR(30) NOT NULL;


-- =========================================================
-- 5. INDICES Y RESTRICCIONES DE SEDES
-- =========================================================

ALTER TABLE sedes
    ADD CONSTRAINT uq_sedes_codigo
        UNIQUE (codigo),

    ADD INDEX idx_sedes_codigo_activo (
        codigo,
        activo
    ),

    ADD INDEX idx_sedes_ciudad_activo (
        ciudad,
        activo
    ),

    ADD INDEX idx_sedes_departamento_activo (
        departamento,
        activo
    ),

    ADD CONSTRAINT chk_sedes_codigo_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(codigo)) > 0
        ),

    ADD CONSTRAINT chk_sedes_nombre_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(nombre)) > 0
        );


-- =========================================================
-- 6. CREAR CATALOGO DE TIPOS DE UBICACION
-- =========================================================

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


INSERT INTO tipos_ubicacion (
    codigo,
    nombre,
    descripcion,
    activo
) VALUES
    (
        'PISO',
        'Piso',
        'Piso o nivel físico dentro de una sede.',
        1
    ),
    (
        'AREA',
        'Área',
        'Área organizacional o funcional.',
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
        'Sala de reuniones, capacitación u otro uso.',
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
        'Puesto de trabajo o ubicación individual.',
        1
    ),
    (
        'OTRO',
        'Otro',
        'Tipo no incluido en el catálogo principal.',
        1
    );


-- =========================================================
-- 7. AMPLIAR UBICACIONES
-- =========================================================
--
-- ubicaciones fue validada con cero registros.
-- detalle se conserva por compatibilidad.

ALTER TABLE ubicaciones
    ADD COLUMN tipo_ubicacion_id BIGINT UNSIGNED NULL
        AFTER sede_id,

    ADD COLUMN ubicacion_padre_id BIGINT UNSIGNED NULL
        AFTER tipo_ubicacion_id,

    ADD COLUMN codigo VARCHAR(50) NULL
        AFTER ubicacion_padre_id;


-- =========================================================
-- 8. ELIMINAR UNICIDAD ANTERIOR POR NOMBRE
-- =========================================================

ALTER TABLE ubicaciones
    DROP INDEX uq_ubicaciones_sede_nombre;


-- =========================================================
-- 9. CONVERTIR CAMPOS NUEVOS EN OBLIGATORIOS
-- =========================================================

ALTER TABLE ubicaciones
    MODIFY COLUMN tipo_ubicacion_id
        BIGINT UNSIGNED NOT NULL,

    MODIFY COLUMN codigo
        VARCHAR(50) NOT NULL;


-- =========================================================
-- 10. RELACIONES, INDICES Y RESTRICCIONES
-- La regla que impide usar la propia ubicacion como padre,
-- la pertenencia del padre a la misma sede y la prevencion
-- de ciclos se validaran en la capa de servicio.
-- MariaDB no permite usar id AUTO_INCREMENT en este CHECK.
-- =========================================================

ALTER TABLE ubicaciones
    ADD CONSTRAINT uq_ubicaciones_sede_codigo
        UNIQUE (
            sede_id,
            codigo
        ),

    ADD CONSTRAINT fk_ubicaciones_tipo
        FOREIGN KEY (
            tipo_ubicacion_id
        )
        REFERENCES tipos_ubicacion (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    ADD CONSTRAINT fk_ubicaciones_padre
        FOREIGN KEY (
            ubicacion_padre_id
        )
        REFERENCES ubicaciones (id)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT,

    ADD INDEX idx_ubicaciones_tipo_activo (
        tipo_ubicacion_id,
        activo
    ),

    ADD INDEX idx_ubicaciones_padre (
        ubicacion_padre_id
    ),

    ADD INDEX idx_ubicaciones_sede_padre (
        sede_id,
        ubicacion_padre_id
    ),

    ADD INDEX idx_ubicaciones_sede_nombre (
        sede_id,
        nombre
    ),

    ADD CONSTRAINT chk_ubicaciones_codigo_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(codigo)) > 0
        ),

    ADD CONSTRAINT chk_ubicaciones_nombre_no_vacio
        CHECK (
            CHAR_LENGTH(TRIM(nombre)) > 0
        );
