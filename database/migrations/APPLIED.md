\# Registro de migraciones aplicadas



\## Fase 7.1: Sedes y ubicaciones



Base de datos: `cgi\_db01`  

Motor: MariaDB 11.4.13  

Entorno: producción  

Fecha de ejecución: 17 de octubre de 2026



\## Respaldo previo



Se creó un respaldo de estructura y datos antes de modificar

el esquema:



`/home/cgi/respaldo\_pre\_fase\_7\_1\_20261017\_161741.sql`



El archivo contiene:



\- estructura de `sedes`;

\- estructura de `ubicaciones`;

\- 11 registros originales de `sedes`;

\- 0 registros de `ubicaciones`.



\## Evidencia posterior



Se creó una evidencia estructural después de completar las

migraciones:



`/home/cgi/evidencia\_post\_fase\_7\_1\_20261017\_163917.sql`



El archivo contiene la estructura final de:



\- `sedes`;

\- `tipos\_ubicacion`;

\- `ubicaciones`.



\## 20261013\_001\_create\_sites\_and\_locations.sql



Estado: no aplicada.



La migración original intentaba crear desde cero las tablas

`sedes` y `ubicaciones`. Fue deshabilitada porque ambas tablas

ya existían y tenían claves externas con otros módulos.



Archivo conservado:



`20261013\_001\_create\_sites\_and\_locations.sql.disabled`



No debe ejecutarse sobre la base de producción existente.



\## 20261013\_002\_adapt\_sites\_and\_locations.sql



Estado: aplicada parcialmente.



Aplicó correctamente:



\- campo `codigo` en `sedes`;

\- campos `direccion`, `ciudad` y `departamento`;

\- códigos institucionales de las sedes;

\- cambio de nombre de `Administrativa` a `Calle 74`;

\- creación de `Medellín` con ID 12 y código 17;

\- tabla `tipos\_ubicacion`;

\- siete tipos iniciales de ubicación;

\- columnas `codigo`, `tipo\_ubicacion\_id` y

&#x20; `ubicacion\_padre\_id` en `ubicaciones`;

\- eliminación de la unicidad anterior por nombre.



El último `ALTER TABLE ubicaciones` falló porque MariaDB no

permite utilizar la columna `id` AUTO\_INCREMENT dentro del

`CHECK` que comparaba:



`ubicacion\_padre\_id <> id`



No debe volver a ejecutarse sobre esta base.



\## 20261013\_003\_complete\_locations\_constraints.sql



Estado: aplicada correctamente.



Completó:



\- `uq\_ubicaciones\_sede\_codigo`;

\- `fk\_ubicaciones\_tipo`;

\- `fk\_ubicaciones\_padre`;

\- `idx\_ubicaciones\_tipo\_activo`;

\- `idx\_ubicaciones\_padre`;

\- `idx\_ubicaciones\_sede\_padre`;

\- `idx\_ubicaciones\_sede\_nombre`;

\- `chk\_ubicaciones\_codigo\_no\_vacio`;

\- `chk\_ubicaciones\_nombre\_no\_vacio`.



No debe volver a ejecutarse sobre esta base.



\## Catálogo institucional resultante



\- ID 1, código 15, Calle 74.

\- ID 2, código 10, Américas.

\- ID 3, código 05, Armenia.

\- ID 4, código 03, Cali.

\- ID 5, código 14, Chicó.

\- ID 6, código 16, Chía.

\- ID 7, código 04, Pereira.

\- ID 8, código 11, Pontevedra.

\- ID 9, código 08, Manizales.

\- ID 10, código 07, Tunja.

\- ID 11, código 02, Normandía.

\- ID 12, código 17, Medellín.



\## Resultado final validado



\- 12 sedes.

\- 12 sedes activas.

\- 0 códigos de sede vacíos.

\- 0 códigos de sede duplicados.

\- 7 tipos de ubicación.

\- 7 tipos activos.

\- 0 ubicaciones iniciales.

\- 14 relaciones esperadas.

\- 0 relaciones faltantes.



\## Validaciones de jerarquía pendientes del servicio



Las siguientes reglas no se implementan mediante `CHECK` en

MariaDB y deberán validarse en la capa de servicio:



1\. Una ubicación no puede ser su propia ubicación padre.

2\. La ubicación padre debe pertenecer a la misma sede.

3\. La jerarquía no puede contener ciclos.

4\. La sede seleccionada debe estar activa.

5\. El tipo de ubicación debe estar activo.

6\. Una ubicación inactiva no puede utilizarse en operaciones

&#x20;  nuevas.

7\. No se puede desactivar una ubicación con hijas activas.

8\. No se puede desactivar una sede con ubicaciones o

&#x20;  dependencias activas.

