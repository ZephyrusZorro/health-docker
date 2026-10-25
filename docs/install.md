# Local installation

You have two paths: **Docker** (recommended, matches production) or **manual** (faster iteration on code).

## Option A — Docker

```bash
git clone https://github.com/ZephyrusZorro/health-docker.git
cd health-docker
cp backend/.env.example backend/.env
cp frontend/.env.local.example frontend/.env.local
docker compose up -d --build
```

First run takes 4–6 minutes (image builds + composer install + npm install + migrate + seed). On subsequent runs, `docker compose up` is ready in ~20 seconds.

When healthy:

| Service | URL |
|---------|-----|
| Frontend | http://localhost:3000 |
| Backend API | http://localhost:8000/api/v1 |
| Mailpit | http://localhost:8025 |
| MinIO console | http://localhost:9001 |
| Reverb (WS) | ws://localhost:8080 |

### Resetting

```bash
docker compose down -v           # drop volumes too
docker compose up -d --build
```

## Option B — Manual (no Docker)

Prereqs: PHP 8.3, Composer 2, Node 20+, PostgreSQL 16, Redis 7.

```bash
# Backend
cd backend
cp .env.example .env
composer install
php artisan key:generate
createdb nishat_hms
php artisan migrate --seed
php artisan serve             # :8000
php artisan queue:work        # separate terminal
php artisan reverb:start      # separate terminal, :8080

# Frontend
cd ../frontend
cp .env.local.example .env.local
npm install
npm run dev                   # :3000
```

## Demo users

All passwords are `password`.

| Role | Email |
|------|-------|
| Super Admin | `super@nishat.local` |
| Hospital Admin | `admin@nishat.local` |
| Receptionist | `reception@nishat.local` |
| Doctor (Cardio) | `arun@nishat.local` |
| Doctor (Gen Med) | `meera@nishat.local` |
| Doctor (Peds) | `rohan@nishat.local` |
| Nurse | `lakshmi@nishat.local` |
| Nurse | `ravi@nishat.local` |
| Pharmacist | `pharmacy@nishat.local` |
| Lab Technician | `lab@nishat.local` |
| Cashier | `cashier@nishat.local` |
| Management | `management@nishat.local` |

The seeder also creates 30 realistic en_IN patients, 30+ medications with stock batches, 30 lab tests with reference ranges, 14 days of schedule slots, 30 historical appointments (half completed with vitals and Rx), 10 lab orders, 3 IPD admissions, and 10 invoices in varying states of payment.

## Troubleshooting

**Migration fails with "relation already exists"** — you ran migrations against a pre-seeded DB. Run `php artisan migrate:fresh --seed`.

**Frontend shows "Network Error" on login** — the frontend expects the backend at `NEXT_PUBLIC_API_URL`. In Docker, it's set via the compose file; in manual mode, check `frontend/.env.local`.

**Reverb connection refused** — check that `php artisan reverb:start` is running, and that `NEXT_PUBLIC_WS_HOST`/`WS_PORT` match.

**Queue jobs not running** — `php artisan queue:work` must be running. In production, use a supervisor-managed process.
