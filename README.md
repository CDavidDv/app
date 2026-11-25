# Un pedacito de todos

Plataforma privada para reunir cartas, fotos, videos y mensajes para un cumpleaños. El nombre, la edad y las fechas se configuran en `.env` (`CELEBRANT_*`, `BIRTHDAY_DATE`, `SUBMISSIONS_CLOSE_AT`) y luego en el panel.

- **Invitados**: entran con un enlace secreto, se identifican y dejan uno o varios recuerdos. Al terminar reciben un **enlace personal** para volver a editar desde cualquier dispositivo hasta la fecha límite. Además, el dispositivo los recuerda con una cookie cifrada durante 120 días.
- **Cumpleañera**: tiene su propio enlace secreto (y una palabra secreta opcional). Ve todo agrupado por persona y por tipo: cartas con animación de apertura, galería de fotos ampliable, videos en la página, mensajes, canciones (enlace a YouTube, Spotify, Apple Music, etc., que se abre en su app) y recuerdos especiales.
- **Administrador**: panel en `/admin` con resumen, moderación (favoritas, ocultar, editar, eliminar, descargar), personas, lista de invitados, enlaces y configuración.

## Cómo entran los amigos

1. En `/admin/enlaces` copias el **enlace de invitación general** (o el mensaje listo para WhatsApp) y lo mandas al grupo.
2. Opcional: en `/admin/invitados` pegas la lista de nombres y generas un **enlace personal por invitado**. Este enlace precarga su nombre y marca automáticamente que ya participó, así sabes quién falta.
3. Cada persona escribe su nombre y su relación. No hay contraseñas. Al enviar, recibe *“Tu enlace personal”* para volver a ver, editar o agregar más.
4. La recepción se cierra sola en la **fecha límite** (por defecto el 5 de diciembre a las 00:00, hora de Ciudad de México). Se cambia en Configuración.
5. El día del cumpleaños: activas **“Página de recuerdos visible para ella”** y le entregas su enlace (`/admin/enlaces`). Hasta entonces, si entra, verá *“Tu regalo se está preparando”* con una cuenta regresiva.

## Stack

Laravel 12 (PHP 8.2+) · MySQL/MariaDB · Vue 3 + TypeScript + Vite + Tailwind CSS 4 · Un solo proyecto: Laravel sirve la API y la SPA compilada.

```
app/
  Http/Controllers/        AccessController (enlaces, estado), ParticipantController, UploadController,
                           MediaController (archivos autorizados), BirthdayController, Admin/*
  Http/Middleware/         SecurityHeaders (CSP, noindex, no-referrer, HSTS…)
  Models/                  EventSetting, Guest, AccessToken, Participant, Contribution, MediaFile,
                           UploadSession, Question, Answer
  Services/                MediaService (subida por partes, validación MIME real, miniaturas, entrega con Range/S3)
                           ContributionWriter (validación por tipo)
  Support/                 Access (quién es quién), Present (serialización), Text (sanitización)
config/pedacito.php        límites, formatos y disco de archivos
database/migrations, seeders
resources/js/              SPA Vue (views/participate, views/birthday, views/admin, components)
tests/Feature/             19 pruebas de flujos principales y seguridad
```

## Ejecutar en local

Requisitos: PHP 8.2+ con `pdo_mysql`, `fileinfo`, `gd`, `zip`; Composer; Node 20+; MySQL/MariaDB.

```bash
composer install
npm install
cp .env.example .env        # ajusta DB_*, APP_URL=http://127.0.0.1:8000, APP_ENV=local, APP_DEBUG=true, SESSION_SECURE_COOKIE=false
php artisan key:generate
php artisan migrate --seed
npm run build               # o "npm run dev" mientras desarrollas
php artisan serve
```

Credenciales de desarrollo (desde `.env`): `admin@pedacito.local` / `cambia-esta-clave`.
Si `ADMIN_PASSWORD` está vacío, el seeder genera una contraseña y la muestra en consola.

Los enlaces de invitación y de la cumpleañera se ven en `/admin/enlaces`.

Pruebas: crea la base `pedacito_test` y ejecuta `php artisan test`.

## Variables de entorno principales

| Variable | Uso |
|---|---|
| `APP_KEY` | Cifra sesiones, cookies y la copia de los enlaces. **No la cambies después de lanzar**: los enlaces guardados dejarían de poder mostrarse. |
| `APP_URL` | URL pública con `https://`. Se usa para generar los enlaces. |
| `APP_TIMEZONE` | `America/Mexico_City` (fecha límite y fechas). |
| `CELEBRANT_NAME`, `CELEBRANT_SHORT_NAME`, `CELEBRANT_AGE` | Nombre completo, nombre corto y edad (solo se usan al crear la base; después se editan en Configuración). |
| `BIRTHDAY_DATE`, `SUBMISSIONS_CLOSE_AT` | Fecha del cumpleaños (`AAAA-MM-DD`) y cierre de recepción (`AAAA-MM-DD HH:MM:SS`). |
| `DB_*` | Conexión MySQL. |
| `SESSION_SECURE_COOKIE` | `true` en producción (HTTPS). |
| `ADMIN_EMAIL`, `ADMIN_PASSWORD`, `ADMIN_NAME` | Administrador inicial que crea el seeder. |
| `MEDIA_DISK` | `media` (local privado, por defecto) o `s3`. |
| `MEDIA_MAX_IMAGE_MB`, `MEDIA_MAX_VIDEO_MB` | Límites por archivo (25 MB / 800 MB). |
| `MEDIA_CHUNK_MB` | Tamaño de cada parte de subida (5 MB). Debe ser menor que `upload_max_filesize` de PHP. |
| `AWS_*` | Solo si `MEDIA_DISK=s3` (Amazon S3, Cloudflare R2, Backblaze, etc.). |

## Despliegue en Hostinger (plan Premium/Business con SSH)

> Nada se ha publicado. Estos pasos los haces tú cuando quieras lanzar.

1. **hPanel → Avanzado → Configuración de PHP**: PHP **8.2 o superior**. Extensiones `fileinfo`, `gd`, `zip`, `pdo_mysql` activas. En “Opciones de PHP”: `upload_max_filesize = 16M`, `post_max_size = 16M`, `max_execution_time = 120`, `memory_limit = 256M`.
2. **Bases de datos → MySQL**: crea base, usuario y contraseña.
3. **SSL**: activa el certificado gratuito y fuerza HTTPS.
4. **Compila en tu laptop** (Hostinger compartido no tiene Node): `npm run build`. Esto genera `public/build/`.
5. **Sube el proyecto** (sin `node_modules`, `.env`, `storage/app/private/*`) a `~/domains/TU-DOMINIO/pedacito` por Git, SFTP o el Administrador de archivos.
6. **Por SSH**:
   ```bash
   cd ~/domains/TU-DOMINIO/pedacito
   composer install --no-dev --optimize-autoloader
   cp .env.example .env && nano .env        # APP_URL, DB_*, ADMIN_PASSWORD (fuerte)
   php artisan key:generate
   php artisan migrate --seed --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   chmod -R 775 storage bootstrap/cache
   # Haz que el dominio sirva la carpeta public:
   mv ~/domains/TU-DOMINIO/public_html ~/domains/TU-DOMINIO/public_html.bak
   ln -s ~/domains/TU-DOMINIO/pedacito/public ~/domains/TU-DOMINIO/public_html
   ```
   Si no puedes usar el enlace simbólico, sube el proyecto dentro de `public_html` y copia `.htaccess.raiz-hostinger` como `public_html/.htaccess` (redirige todo a `public/` y bloquea carpetas internas).
7. **Cron** (hPanel → Avanzado → Cron Jobs), una vez al día o cada hora:
   `cd ~/domains/TU-DOMINIO/pedacito && php artisan schedule:run` — limpia subidas abandonadas.
8. Entra a `https://TU-DOMINIO/admin/login`, cambia la contraseña en Configuración, sube la foto de portada, revisa los textos y copia los enlaces.

### Espacio para videos

Los archivos se guardan en `storage/app/private/media` (fuera de la web; solo se entregan tras validar quién los pide). Revisa la cuota de disco de tu plan: 30 videos de 200 MB son ~6 GB. Si se queda corto, usa almacenamiento compatible con S3:

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
# .env
MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=…  AWS_SECRET_ACCESS_KEY=…  AWS_DEFAULT_REGION=…  AWS_BUCKET=…
AWS_ENDPOINT=…   # para R2/Backblaze
```
El bucket debe ser **privado**; la app entrega URLs firmadas de 20 minutos.

## Seguridad implementada

- Tokens aleatorios de 256 bits; en la base solo se guarda su **hash SHA-256** (más una copia cifrada con `APP_KEY` para que el admin pueda volver a copiarlos). Al abrirlos se cambian por una sesión y se redirige a una URL limpia.
- Admin con sesión Laravel, contraseña bcrypt, regeneración de sesión y **rate limiting** (5 intentos → bloqueo de 2 min).
- CSRF (cookie `XSRF-TOKEN` + cabecera), sesiones cifradas, cookies `HttpOnly` y `SameSite=Lax`.
- Archivos: lista blanca de extensiones **y** verificación del MIME real con `finfo`; imágenes verificadas con `getimagesize`; nombres aleatorios; nunca accesibles por URL directa; autorización en cada petición (cada participante solo ve lo suyo; la cumpleañera solo lo publicado y visible).
- Texto sin HTML (se limpia en servidor y Vue siempre lo muestra como texto) → sin XSS.
- IDs ULID no secuenciales + autorización.
- Cabeceras: CSP estricta, `X-Frame-Options: DENY`, `Referrer-Policy: no-referrer`, `nosniff`, HSTS en HTTPS, `X-Robots-Tag: noindex` y `robots.txt` que bloquea todo.
- Rate limiting en enlaces, participación, subidas y archivos.

## Limitaciones conocidas

- Videos **HEVC/MOV de iPhone** se reproducen en Safari/iPhone, pero algunos Android o Chrome de escritorio podrían no reproducirlos (sin servidor de conversión en hosting compartido). Se pueden descargar desde el panel. Si ella usa iPhone, no hay problema.
- No hay miniaturas para video (se muestra el primer cuadro con `preload="metadata"`).
- El respaldo ZIP se arma en el servidor: con muchos GB puede superar el tiempo límite de PHP; en ese caso descarga por persona.
- Un solo evento por instalación (pensado para este regalo).
