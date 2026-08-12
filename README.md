# RuneVentures

RuneVentures is a mobile-first, location-based browser RPG inspired by the progression philosophy of classic MMORPGs. It uses Laravel as the authoritative game server and React for the game client.

## Current playable slice

- Registration, login, email verification and account security
- Persistent player character and current location
- Connected location travel with server-side validation
- Location content grouped into places, objects, NPCs, resources and monsters
- Woodcutting action that grants logs and XP
- OSRS-style skill overview
- 28-slot inventory
- Combat and prayer interface foundations
- Mobile bottom navigation

## Stack

- Laravel 13 / PHP 8.3+
- React 19 / TypeScript
- Inertia 3 / Vite 8
- Tailwind CSS 4
- SQLite locally; PostgreSQL recommended in production

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

## Quality checks

```bash
npm run lint:check
npm run types:check
php artisan test
```
