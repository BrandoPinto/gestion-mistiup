# Despliegue en cPanel — Mistiup

Guía para publicar y mantener el sistema en un hosting con cPanel. El servidor **no necesita Node**:
el frontend se compila en tu computadora y se sube ya listo.

---

## 1. Requisitos del hosting

| Requisito | Detalle |
|---|---|
| PHP | **8.3 o superior** (cPanel → *Select PHP Version* o *MultiPHP Manager*) |
| Extensiones PHP | `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip` |
| Base de datos | MySQL 8 o MariaDB 10.6+ |
| Cron | Acceso a *Cron Jobs* en cPanel |
| SSL | Certificado activo (AutoSSL / Let's Encrypt). El sistema exige HTTPS en producción |
| Terminal (recomendado) | *Terminal* o SSH en cPanel. Sin terminal se puede, pero es más lento |

Límites PHP sugeridos (*MultiPHP INI Editor*): `memory_limit = 256M`, `upload_max_filesize = 8M`,
`post_max_size = 10M`, `max_execution_time = 60`.

---

## 2. Preparar el paquete (en tu computadora)

```powershell
powershell -ExecutionPolicy Bypass -File scripts\empaquetar.ps1
```

Genera `dist\mistiup-AAAAMMDD-HHMM.zip` con el frontend compilado y las dependencias PHP de producción.
No incluye `.env`, archivos subidos locales, tests ni `node_modules`.

---

## 3. Estructura recomendada en el servidor

El código **no** debe quedar dentro de `public_html`: solo la carpeta `public` debe ser accesible desde la web.

```
/home/USUARIO/
├── mistiup/            ← todo el ZIP descomprimido aquí (app, vendor, storage, .env…)
│   └── public/         ← raíz web del dominio o subdominio
└── public_html/        ← sitio principal (no se toca)
```

**Opción A (recomendada): subdominio o dominio adicional.**
En cPanel → *Dominios* → crear `gestion.tudominio.com` y en **Raíz del documento** poner `mistiup/public`.

**Opción B: dominio principal (`public_html`).** Solo si no se puede cambiar la raíz del documento:
1. Copiar el **contenido** de `mistiup/public/` dentro de `public_html/` (incluye `.htaccess` y `build/`).
2. Editar `public_html/index.php` y cambiar las rutas:
   ```php
   if (file_exists($maintenance = __DIR__.'/../mistiup/storage/framework/maintenance.php')) {
   require __DIR__.'/../mistiup/vendor/autoload.php';
   $app = require_once __DIR__.'/../mistiup/bootstrap/app.php';
   $app->usePublicPath(__DIR__);
   ```
3. En cada actualización, volver a copiar `public/build/` a `public_html/build/`.

---

## 4. Primera instalación

### 4.1 Base de datos
cPanel → *Bases de datos MySQL*:
1. Crear la base (p. ej. `USUARIO_mistiup`).
2. Crear un usuario **exclusivo** con contraseña fuerte.
3. Agregar el usuario a la base con **todos los privilegios sobre esa base** (no sobre otras).

### 4.2 Subir archivos
1. *Administrador de archivos* → subir el ZIP a `/home/USUARIO/` → **Extraer** en `mistiup/`.
2. Copiar `mistiup/.env.production.example` como `mistiup/.env` y completar:
   - `APP_URL` (con `https://`)
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `ADMIN_EMAIL`, `ADMIN_PASSWORD` (solo para crear el primer administrador)
3. Permisos: carpetas `755`, archivos `644`. `storage/` y `bootstrap/cache/` deben poder escribirse
   por PHP (`755` basta en cPanel con suPHP/LSAPI; usar `775` solo si el hosting lo pide).
   `.env` → `600`.

### 4.3 Comandos (Terminal de cPanel)
```bash
cd ~/mistiup
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
```
> Si `php` no es la versión correcta, usar la ruta completa, p. ej. `/opt/cpanel/ea-php83/root/usr/bin/php`.

Después del seed, **borrar `ADMIN_PASSWORD` del `.env`** y ejecutar `php artisan config:cache` otra vez.
Ingresar al sistema y cambiar la contraseña desde *Mi cuenta*.

### 4.4 Archivos subidos (storage)
Los logos y comprobantes se guardan en `storage/app/private` y se sirven **a través de la aplicación**
(con control de acceso). **No hace falta `php artisan storage:link`** y no debe exponerse `storage/` a la web.

### 4.5 Sin terminal
Si el hosting no ofrece terminal, los comandos de 4.3 pueden ejecutarse creando **una sola vez** un cron
temporal (p. ej. `cd ~/mistiup && php artisan migrate --force`) y eliminándolo apenas corra.

---

## 5. Cron (tareas automáticas)

cPanel → *Cron Jobs* → **un único** cron cada minuto:

```
* * * * * cd /home/USUARIO/mistiup && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

El scheduler (hora de Lima) se encarga de:

| Tarea | Frecuencia | Qué hace |
|---|---|---|
| `billing:generate-charges` | cada hora (min. 05) | Crea los cobros de los próximos *N* días (Configuración → Cobros) |
| `contracts:complete-finished` | diario 00:20 | Marca como finalizados los contratos que terminaron |
| `reminders:dispatch` | cada hora (min. 15), 07:00–21:00 | Emite los recordatorios internos (campana) |

Todas son **idempotentes**: si el cron falla un día, la siguiente ejecución recupera lo pendiente sin duplicar.
Comprobar con `php artisan schedule:list`.

---

## 6. Variables de entorno importantes

| Variable | Producción | Por qué |
|---|---|---|
| `APP_ENV` | `production` | Desactiva herramientas de desarrollo |
| `APP_DEBUG` | `false` | **Nunca** `true`: mostraría detalles internos |
| `APP_URL` | `https://…` | Enlaces públicos de cotizaciones y PDF |
| `SESSION_SECURE_COOKIE` | `true` | Cookie solo por HTTPS |
| `SESSION_ENCRYPT` | `true` | Sesiones cifradas en la base de datos |
| `LOG_CHANNEL` / `LOG_LEVEL` | `daily` / `warning` | Logs rotativos (14 días) en `storage/logs` |
| `QUEUE_CONNECTION` | `sync` | No hay workers en segundo plano |
| `TRUSTED_PROXIES` | vacío | Completar solo si se usa Cloudflare u otro proxy |
| `BILLING_CHARGE_LEAD_DAYS` | `45` | Valor inicial; luego se cambia desde Configuración |

---

## 7. Actualizar a una nueva versión

1. En tu computadora: `scripts\empaquetar.ps1`.
2. En el servidor: `php artisan down` (muestra "En mantenimiento").
3. **Respaldar** base de datos y `storage/app/private` (ver sección 8).
4. Subir el ZIP y extraerlo **sobre** `mistiup/` (sobrescribir). El ZIP no trae `.env` ni `storage` con datos,
   así que no los pisa.
5. Ejecutar:
   ```bash
   cd ~/mistiup
   php artisan migrate --force
   php artisan optimize:clear
   php artisan optimize
   php artisan up
   ```
6. Opción B (`public_html`): copiar de nuevo `public/build/` a `public_html/build/`.

---

## 8. Respaldos (backups)

Lo que hay que respaldar:
- **Base de datos** (todo el negocio: clientes, cobros, pagos, cotizaciones, historial).
- **`storage/app/private`** (logo y comprobantes de pago).
- **`.env`** (guardarlo aparte, en un lugar seguro: contiene `APP_KEY`, sin la cual las sesiones cifradas no sirven).

Recomendado:
1. **Copias del hosting**: activar las copias automáticas de cPanel (*JetBackup* o *Backup*), si el plan las incluye.
2. **Volcado diario propio** (además del anterior). Crear `~/.my.cnf` con permisos `600`:
   ```ini
   [client]
   user=USUARIO_mistiup
   password=LA_CONTRASEÑA
   ```
   y un cron diario:
   ```
   30 3 * * * mysqldump --defaults-extra-file=$HOME/.my.cnf --single-transaction USUARIO_mistiup | gzip > $HOME/backups/mistiup-$(date +\%F).sql.gz && find $HOME/backups -name 'mistiup-*.sql.gz' -mtime +30 -delete
   ```
   (crear antes la carpeta `~/backups`, fuera de `public_html`).
3. **Copia fuera del servidor** al menos una vez por semana (descargar el último volcado y `storage/app/private`).
4. **Probar la restauración** de vez en cuando en una base de prueba: un respaldo que nunca se restauró no está comprobado.

---

## 9. Seguridad en producción (lista de verificación)

- [ ] `APP_DEBUG=false` y `APP_ENV=production`.
- [ ] HTTPS activo; `APP_URL` con `https://`.
- [ ] La raíz web apunta a `public/` (o se usó la opción B). `https://dominio/.env` debe dar **404/403**.
- [ ] `.env` con permisos `600`; `ADMIN_PASSWORD` borrado después del primer seed.
- [ ] Usuario MySQL exclusivo, sin privilegios sobre otras bases.
- [ ] Cron configurado (`php artisan schedule:list`).
- [ ] Respaldos automáticos funcionando.

Ya incorporado en la aplicación: CSRF, contraseñas con hash, límite de intentos de login y del enlace público,
cabeceras de seguridad (sin iframes, `nosniff`, referrer restringido), archivos privados con control de
acceso y validación de tipo/tamaño, tokens públicos largos y revocables, y páginas de error sin detalles internos.

---

## 10. Problemas frecuentes

| Síntoma | Causa probable / solución |
|---|---|
| Error 500 al entrar | Revisar `storage/logs/laravel-AAAA-MM-DD.log`. Suele ser `.env` (BD) o permisos de `storage/` |
| Página sin estilos | Falta `public/build/` (opción B: copiarlo a `public_html/build/`) |
| "La sesión expiró" al iniciar sesión | `SESSION_SECURE_COOKIE=true` sin HTTPS, o `APP_URL` con otro dominio |
| No se generan cobros/recordatorios | El cron no corre: revisar la ruta de PHP y del proyecto en *Cron Jobs* |
| Cambios en `.env` no se aplican | Ejecutar `php artisan config:cache` (la configuración queda en caché) |
| PDF sin tildes o con otra fuente | Verificar que `resources/fonts/` se subió completo |
