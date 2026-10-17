# Registro de migraciones aplicadas

## Fase 7.1: Sedes y ubicaciones

Base de datos: `cgi_db01`
Motor: MariaDB 11.4.13
Entorno: producción
Fecha de ejecución: 17 de octubre de 2026

## Respaldo previo

Antes de modificar el esquema se creó un respaldo de estructura
y datos:

`/home/cgi/respaldo_pre_fase_7_1_20261017_161741.sql`

El respaldo contiene:

- estructura anterior de `sedes`;
- estructura anterior de `ubicaciones`;
- 11 registros originales de `sedes`;
- 0 registros de `ubicaciones`.

El archivo tiene permisos `600`.

## Evidencia posterior

Después de completar las migraciones se creó la evidencia:

`/home/cgi/evidencia_post_fase_7_1_20261017_163917.sql`

La evidencia contiene la estructura final de:

- `sedes`;
- `tipos_ubicacion`;
- `ubicaciones`.

El archivo tiene permisos `600`.

## 20261013_001_create_sites_and_locations.sql

Estado: **no aplicada**.

La migración intentaba crear desde cero las tablas `sedes` y
`ubicaciones`. Fue deshabilitada porque ambas tablas ya existían
y participaban en relaciones con otros módulos.

Archivo conservado:

`20261013_001_create_sites_and_locations.sql.disabled`

No debe ejecutarse sobre la base de producción existente.

## 20261013_002_adapt_sites_and_locations.sql

Estado: **aplicada parcialmente**.

Aplicó correctamente:

- campo `codigo` en `sedes`;
- campos `direccion`, `ciudad` y `departamento`;
- códigos institucionales de las sedes;
- cambio de nombre de `Administrativa` a `Calle 74`;
- creación de `Medellín` con ID 12 y código 17;
- tabla `tipos_ubicacion`;
- siete tipos iniciales de ubicación;
- columnas `codigo`, `tipo_ubicacion_id` y
  `ubicacion_padre_id` en `ubicaciones`;
- eliminación de la unicidad anterior por nombre.

El último `ALTER TABLE ubicaciones` no se aplicó porque
MariaDB rechazó un `CHECK` que comparaba:

`ubicacion_padre_id <> id`

MariaDB no permite emplear la columna `id` con
`AUTO_INCREMENT` en esa expresión `CHECK`.

La migración `002` no debe volver a ejecutarse sobre esta base.

## 20261013_003_complete_locations_constraints.sql

Estado: **aplicada correctamente**.

Completó:

- `uq_ubicaciones_sede_codigo`;
- `fk_ubicaciones_tipo`;
- `fk_ubicaciones_padre`;
- `idx_ubicaciones_tipo_activo`;
- `idx_ubicaciones_padre`;
- `idx_ubicaciones_sede_padre`;
- `idx_ubicaciones_sede_nombre`;
- `chk_ubicaciones_codigo_no_vacio`;
- `chk_ubicaciones_nombre_no_vacio`.

La migración `003` no debe volver a ejecutarse sobre esta base.

Un segundo intento de ejecución produjo correctamente un error
por índice duplicado, porque la primera ejecución ya había
completado todos los objetos.

## Catálogo institucional resultante

- ID 1, código 15, Calle 74.
- ID 2, código 10, Américas.
- ID 3, código 05, Armenia.
- ID 4, código 03, Cali.
- ID 5, código 14, Chicó.
- ID 6, código 16, Chía.
- ID 7, código 04, Pereira.
- ID 8, código 11, Pontevedra.
- ID 9, código 08, Manizales.
- ID 10, código 07, Tunja.
- ID 11, código 02, Normandía.
- ID 12, código 17, Medellín.

## Resultado final validado

- 12 sedes.
- 12 sedes activas.
- 0 códigos de sede vacíos.
- 0 códigos de sede duplicados.
- 7 tipos de ubicación.
- 7 tipos activos.
- 0 ubicaciones iniciales.
- 14 relaciones esperadas.
- 0 relaciones faltantes.
- 5 índices nuevos de ubicación verificados.
- 2 restricciones `CHECK` nuevas verificadas.
- 2 claves externas nuevas verificadas.

## Relaciones nuevas verificadas

- `ubicaciones.tipo_ubicacion_id` referencia
  `tipos_ubicacion.id`.
- `ubicaciones.ubicacion_padre_id` referencia
  `ubicaciones.id`.

## Relaciones anteriores conservadas

Se verificaron las relaciones de:

- activos con sedes y ubicaciones;
- entregas con sedes y ubicaciones;
- movimientos de activos con sedes y ubicaciones;
- personas con sedes y ubicaciones;
- solicitudes con sedes;
- ubicaciones con sedes.

## Validaciones pendientes de la capa de servicio

Las siguientes reglas no se implementan mediante `CHECK` en
MariaDB y deben validarse en la capa de servicio:

1. Una ubicación no puede ser su propia ubicación padre.
2. La ubicación padre debe pertenecer a la misma sede.
3. La jerarquía no puede contener ciclos.
4. La sede seleccionada debe estar activa.
5. El tipo de ubicación debe estar activo.
6. Una ubicación inactiva no puede utilizarse en operaciones
   nuevas.
7. No se puede desactivar una ubicación con hijas activas.
8. No se puede desactivar una sede con ubicaciones o
   dependencias activas.

## Estado de la fase

La Fase 7.1 queda aprobada y cerrada.

El siguiente bloque corresponde a la Fase 7.2:

- repositorios de sedes y ubicaciones;
- servicios transaccionales;
- validaciones jerárquicas;
- auditoría;
- pruebas automatizadas de reglas de negocio.
