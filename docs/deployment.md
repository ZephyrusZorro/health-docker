# Production Deployment

This isn't a Kubernetes / Terraform recipe — just the hygiene checklist you need before putting the system in front of real patients.

## The services you must run

| Process | Instances | Notes |
|---------|-----------|-------|
| `php artisan octane:start` (or PHP-FPM) | 2+ behind a load balancer | Laravel app serving the API |
| `next start` | 2+ behind a load balancer | Next.js frontend |
| `php artisan queue:work --queue=critical,default` | 2+ workers | Background jobs; supervisor-managed |
| `php artisan reverb:start` | 1+ (can be scaled with Redis pub/sub) | WebSocket server |
| `php artisan schedule:run` via cron (every minute) | 1 | Cron entry — see below |
| PostgreSQL 16 | primary + 1 replica minimum | |
| Redis 7 | 1+ | Cache + queue + session + Reverb pub/sub |

Cron entry on the scheduler host:

```
* * * * * cd /var/www/health-docker/backend && php artisan schedule:run >> /dev/null 2>&1
```

## Environment checklist

In `backend/.env`:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generated via php artisan key:generate>
APP_URL=https://hms.nishat.example

DB_CONNECTION=pgsql
DB_HOST=<primary>
# For read-heavy traffic, configure read replica separately in config/database.php

REDIS_HOST=<your redis>
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

BROADCAST_CONNECTION=reverb
REVERB_APP_KEY=<long random>
REVERB_APP_SECRET=<long random>
REVERB_HOST=hms.nishat.example
REVERB_PORT=443
REVERB_SCHEME=https

MAIL_MAILER=smtp
MAIL_HOST=<your smtp>
# SMS_DRIVER and WHATSAPP_DRIVER configured per vendor

FILESYSTEM_DISK=s3
AWS_BUCKET=nishat-hms-prod
AWS_ENDPOINT=<if using MinIO/compatible>

SANCTUM_STATEFUL_DOMAINS=hms.nishat.example
SESSION_SECURE_COOKIE=true
```

In `frontend/.env.production`:

```
NEXT_PUBLIC_API_URL=https://hms.nishat.example/api/v1
NEXT_PUBLIC_WS_HOST=hms.nishat.example
NEXT_PUBLIC_WS_PORT=443
NEXT_PUBLIC_REVERB_APP_KEY=<matches backend>
```

## Pre-deploy checklist

- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan event:cache`
- [ ] `php artisan view:cache`
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=RoleAndPermissionSeeder --force` (every deploy — keeps RBAC in sync with code)
- [ ] `npm run build` in `frontend/`
- [ ] Verify `schedule:run` cron is active
- [ ] Verify queue workers are alive
- [ ] Backup the database **immediately before** running migrations
- [ ] Put the site in maintenance mode during migrations if they alter hot tables

## Post-deploy smoke test

1. `GET /api/v1/public/doctors` returns the directory (proves public path works)
2. Log in as `admin@` and confirm the dashboard loads
3. Book an appointment through the public wizard
4. Log in as the receptionist, check the patient in
5. Log in as the doctor, start consultation, record vitals, issue prescription
6. Log in as the pharmacist, dispense the medication, verify stock decremented
7. Log in as the cashier, finalise the invoice, collect payment
8. Verify all of the above appear in `admin → audit logs`

If all eight steps pass, the core workflow is functional.

## Backup strategy

- **Database**: `pg_dump --format=custom` hourly, kept for 30 days; daily snapshots kept for 1 year
- **Object storage**: S3 versioning + lifecycle → glacier after 90 days
- **Audit archives** (produced by `hms:audit-rotate`): mirror to a write-once bucket — the law requires 7 years, so plan for retrieval up to that age

## Observability

- App logs: JSON to stdout → ship to your log aggregator
- Laravel Telescope in staging only; **never production** (it writes every request)
- Metrics: the Horizon dashboard covers queue health; add Prometheus exporters for API latency and error rate
- Alert on: queue backlog > 1000, failed job count > 10/hour, p95 latency > 1s, error rate > 1%

## Security baseline

- HTTPS only, HSTS enabled
- Content Security Policy header set (see `AppServiceProvider`)
- `bcrypt` cost 12 (enforced in `AppServiceProvider`)
- Rate limits active on auth, public, and authenticated routes
- Audit log retention: 7 years (2555 days), enforced by `hms:audit-rotate`
- Role-permission assignments: only `super_admin` can reseed; all other role edits require approval workflow
- PII in logs: redacted via `LogsActivity` trait — password/token/secret/PII fields are hashed

## Patient data export

Right-to-export is implemented at `POST /patients/{id}/export`. It renders a complete patient bundle (demographics, encounters, observations, prescriptions, lab results, invoices) as a FHIR R4 JSON bundle. Generated files are stored in object storage with a 24-hour signed URL.
