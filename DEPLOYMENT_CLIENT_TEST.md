# IERA Client Test Deployment (Free Tier)

This setup deploys:
- Frontend (Vue) -> Netlify (free)
- Backend (Laravel API) -> Render web service with Docker (free)
- Database (PostgreSQL) -> Neon (free)

## 1) Create Neon Postgres (Free)

1. Create a Neon project and database.
2. Copy these values from Neon connection details:
   - `DB_HOST`
   - `DB_PORT` (usually `5432`)
   - `DB_DATABASE`
   - `DB_USERNAME`
   - `DB_PASSWORD`

## 2) Deploy Laravel API on Render (Docker)

Use `render.yaml` in repo root (Blueprint), or configure manually in Render UI:
- Service type: `Web Service`
- Runtime: `Docker`
- Root directory: `Laravel`
- Health check path: `/up`
- Plan: `Free`

Set these environment variables in Render (see `Laravel/.env.render.example`):
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY=<generate from "php artisan key:generate --show">`
- `APP_URL=https://<your-render-service>.onrender.com`
- `LOG_CHANNEL=stderr`
- `DB_CONNECTION=pgsql`
- `DB_HOST=<from neon>`
- `DB_PORT=5432`
- `DB_DATABASE=<from neon>`
- `DB_USERNAME=<from neon>`
- `DB_PASSWORD=<from neon>`
- `DB_SSLMODE=require`
- `SESSION_DRIVER=file`
- `CACHE_STORE=file`
- `QUEUE_CONNECTION=sync`

Notes:
- `Laravel/Dockerfile` already builds the app, installs dependencies, runs migrations + seeders at startup, and creates `public/storage` symlink.
- File uploads are expected to be temporary on Render Free (ephemeral filesystem).

## 3) Deploy Vue Frontend on Netlify

Create a Netlify site from this repo.
The root `netlify.toml` already sets:
- Base directory: `Vue`
- Build command: `npm run build`
- Publish directory: `dist`

Set Netlify environment variable:
- `VITE_API_URL=https://<your-render-service>.onrender.com/api`

SPA routing is already configured in `netlify.toml`:
- Redirect rule `/* -> /index.html` with status `200`

## 4) Smoke Test Checklist

1. Open the Netlify URL and verify app loads.
2. Refresh a deep route (example: `/era-form`) and confirm no 404.
3. Create a new assessment and confirm data persists in Neon DB.
4. Upload photo(s) and confirm immediate display.
5. After Render idle spin-down, retry and confirm app resumes (first request may be slower).
6. After restart/redeploy, confirm old uploaded photos may disappear (accepted for demo).
