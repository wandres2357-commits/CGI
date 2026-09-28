# Inventario TI en alwaysdata

Adaptación de demostración para PHP puro y MariaDB en alwaysdata. No use datos institucionales reales en el plan gratuito.

## Instalación
1. Cree una cuenta y una base MariaDB en el panel.
2. Cree un sitio tipo PHP y configure la raíz como `/home/CUENTA/inventario-ti/public`.
3. Seleccione PHP 8.5.
4. Acceda por SSH y clone el repositorio en `~/inventario-ti`.
5. Copie `.env.example` a `.env`, reemplace `CUENTA` y asigne secretos reales.
6. Proteja secretos: `chmod 700 .env storage/private storage/sessions`.
7. En Environment > PHP agregue el contenido de `config/php.ini`.
8. Importe el esquema MariaDB mediante phpMyAdmin o SSH.
9. Abra `/health.php`; debe mostrar `status: ok`. Después restrinja o retire este endpoint.

## Cambios respecto de IIS
- Apache/FastCGI administrado sustituye IIS.
- Rutas `C:\...` se sustituyen por `/home/CUENTA/...`.
- MariaDB sustituye MySQL 8.4.
- `.htaccess` sustituye `web.config`.
- Autenticación local temporal sustituye LDAPS para la demo.

## Seguridad
No suba `.env`, respaldos, evidencias ni datos personales a GitHub. Mantenga `public` como única raíz web.
