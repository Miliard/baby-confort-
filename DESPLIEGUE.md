# 🚀 Desplegar Baby-Confort en producción (con Git)

Tu proyecto ya está listo para subirse. Flujo: **cambias local → `git push` → se actualiza en el servidor**.

---

## Paso 0 — Subir a GitHub (una sola vez)

En la carpeta del proyecto:

```bash
git init
git add .
git commit -m "Baby-Confort"
```

Crea un repositorio en https://github.com y sigue las 2 líneas que te da para hacer `git push`.
> El `.gitignore` ya evita subir `node_modules`, `vendor` y `.env` (tus claves nunca se suben).

---

## OPCIÓN A — Railway (recomendada: "push y se actualiza")

1. Entra a https://railway.app e inicia sesión con GitHub.
2. **New Project → Deploy from GitHub repo** → elige tu repo.
3. **+ New → Database → MySQL** (agrega la base de datos).
4. En tu servicio web → pestaña **Variables**, agrega:
   - `APP_KEY` → genérala local con `php artisan key:generate --show` y pega el valor.
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_URL=https://TU-APP.up.railway.app` (o tu dominio)
   - `WHATSAPP_NUMBER=50368601764`
   - `DB_CONNECTION=mysql`
   - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` → **cópialos de la pestaña Variables de la base MySQL** que creó Railway (los da con nombres tipo `MYSQLHOST`, etc.; usa esos valores).
5. En **Settings → Deploy → Start Command** (si no toma el Procfile):
   `php artisan serve --host 0.0.0.0 --port $PORT`
6. Cuando termine el primer deploy, abre la **consola** del servicio (o usa `railway run`) y corre:
   ```bash
   php artisan migrate --seed --force
   php artisan storage:link
   ```
7. Crea tu usuario admin (si no usas el del seeder):
   ```bash
   php artisan make:filament-user
   ```
8. Abre la URL → tu tienda. El panel en `/admin`.

**Para futuros cambios:** editas local → `git push` → Railway redespliega solo. 🎉

---

## OPCIÓN B — Hostinger (la más barata)

1. Compra un plan (Cloud o VPS con PHP 8.2+ y MySQL).
2. En hPanel: crea una **base de datos MySQL** (anota host, nombre, usuario, contraseña).
3. Usa la **integración Git** de hPanel para conectar tu repo (o sube por SSH y haz `git clone`).
4. Por SSH, en la carpeta del proyecto:
   ```bash
   composer install --no-dev --optimize-autoloader
   cp .env.production.example .env      # y edítalo con tus datos
   php artisan key:generate
   php artisan migrate --seed --force
   php artisan storage:link
   php artisan config:cache
   ```
5. Apunta el dominio a la carpeta **`public/`** del proyecto.
6. Crea tu admin: `php artisan make:filament-user`.

**Cambios futuros:** `git pull` en el servidor + `php artisan migrate --force` si hubo cambios de base.

---

## ⚠️ Importante sobre las fotos

- Las fotos por **link** (las de Aiwibi) funcionan perfecto en producción.
- Las fotos que **subes como archivo** se guardan en el servidor; en Railway el disco es temporal y podrían borrarse al redesplegar. Recomendación: usa **links** para los productos, o más adelante te conecto un almacenamiento permanente (S3) para las subidas.

## ✅ Checklist de producción
- [ ] `APP_ENV=production` y `APP_DEBUG=false`
- [ ] `APP_KEY` generada
- [ ] Base MySQL configurada
- [ ] `php artisan migrate --seed --force` corrido
- [ ] `php artisan storage:link` corrido
- [ ] Usuario admin creado
- [ ] Dominio apuntando a `public/`
