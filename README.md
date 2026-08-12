# RuneVentures

RuneVentures is a mobile-first, location-based browser RPG inspired by the progression philosophy of classic MMORPGs. It uses Laravel as the authoritative game server and React for the game client.

## Current playable slice

- Registration, login, email verification and account security
- Persistent player character and current location
- Connected location travel with server-side validation
- Location content grouped into places, objects, NPCs, resources and monsters
- Woodcutting action that grants logs and XP
- Basic monster combat with XP and drops
- OSRS-style skill overview
- 28-slot inventory
- Combat and prayer interface foundations
- Mobile bottom navigation

## Stack

- Laravel 13 / PHP 8.3+
- React 19 / TypeScript
- Inertia 3 / Vite 8
- Tailwind CSS 4
- SQLite locally
- PostgreSQL in hosted environments

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open `http://127.0.0.1:8000` and use the seeded account:

- Email: `test@example.com`
- Password: `password`

## Free test deployment: Render + Neon

This branch includes a `Dockerfile`, `render.yaml`, and `docker/render-start.sh` for a zero-cost test deployment using a Render Free web service and a Neon Free PostgreSQL database.

### 1. Create Neon database

Create a Neon project and copy its PostgreSQL connection string. Use the connection string that includes SSL, for example:

```text
postgresql://USER:PASSWORD@HOST/DATABASE?sslmode=require
```

Do not commit the real connection string to GitHub.

### 2. Create Render Blueprint

In Render, create a new Blueprint from this GitHub repository and select the `feat/playable-location-game` branch. Render reads `render.yaml` automatically.

When Render asks for the `DATABASE_URL` secret, paste the Neon connection string.

The Blueprint configures:

- Docker runtime
- Free web-service instance
- Frankfurt region
- automatic deploys from the branch
- `/up` health check
- production Laravel settings
- generated Laravel application key
- PostgreSQL connection

### 3. Automatic startup

On each service start, `docker/render-start.sh`:

1. creates the Laravel `APP_KEY` value from the Render-generated secret;
2. maps `DATABASE_URL` to Laravel's `DB_URL`;
3. runs `php artisan migrate --force`;
4. caches production Laravel configuration and views;
5. starts Apache on Render's assigned `$PORT`.

Database data remains in Neon even when the Render Free service spins down.

## Quality checks

```bash
npm run lint:check
npm run types:check
php artisan test
```
