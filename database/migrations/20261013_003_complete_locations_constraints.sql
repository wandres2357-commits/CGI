-- Inventario TI
-- Fase 7.1
-- Completar relaciones, indices y restricciones
-- despues de la ejecucion parcial de la migracion 002
--
-- Estado previo validado:
-- - ubicaciones contiene cero registros.
-- - tipo_ubicacion_id existe y es NOT NULL.
-- - ubicacion_padre_id existe y permite NULL.
-- - codigo existe y es NOT NULL.
-- - uq_ubicaciones_sede_nombre ya no existe.
-- - Los objetos agregados por esta migracion no existen.
--
-- La prevencion de padre propio, referencias entre sedes
-- distintas y ciclos jerarquicos se implementara en servicio.
--
-- Ejecutar una sola vez.

SET NAMES utf8mb4;
SET time_zone = '-05:00';

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