# Health Docker

A modern, production-grade Hospital Management System containerized with Docker.

End-to-end workflow from the front desk through the ward: patient registration, appointment scheduling, outpatient encounters, EMR, inpatient admissions with live bed tracking, pharmacy with FEFO inventory, laboratory with sample workflow, unified billing, a patient-facing marketing site with online booking, and an AI assist chatbot.

## What's inside

```
health-docker/
├── backend/          — Laravel 12 API (PHP 8.3)
├── frontend/         — Next.js 15 (React 19, App Router)
├── docker/           — Dockerfiles for backend + frontend
├── docs/
│   ├── architecture.md
│   ├── install.md
│   ├── api.md
│   └── deployment.md
├── docker-compose.yml
└── README.md
```

## Quickstart

```bash
git clone https://github.com/ZephyrusZorro/health-docker.git
cd health-docker
cp backend/.env.example backend/.env
cp frontend/.env.local.example frontend/.env.local
docker compose up -d --build
```

Wait ~5 min for the first build, then open:

- **Public site** — http://localhost:3000/welcome
- **Staff app** — http://localhost:3000/login
- **API** — http://localhost:8000/api/v1
- **Mailpit** — http://localhost:8025 (captured outbound email)

All 13 demo accounts use password `password`. Start with `admin@nishat.local` to see the full feature set.

Full setup instructions: **[docs/install.md](docs/install.md)**

## What makes this different

- **FHIR R4-shaped data model.** Clinical resources map 1:1 to FHIR at the API boundary. No rewrite needed when you interop.
- **Polymorphic billing.** Every invoice line item points back to the clinical event (encounter, dispense, lab order, bed charge) that created it. Real audit, not just totals.
- **FEFO dispensing, enforced.** Expired batches are refused at the service layer, inside a locked transaction. You can't over-dispense or consume expired stock by accident.
- **Append-only audit with archiving.** 7-year retention (regulator-ready) without a table that grows forever — the `hms:audit-rotate` command ships old entries to object storage as JSONL.
- **Realtime where it matters.** Queue displays, bed boards, and lab result notifications push via Laravel Reverb. No polling.
- **Role ≠ permission.** Controllers authorize on ~60 fine-grained permissions, not on role names. Roles are a UX shortcut for administrators.

## What was not merged from the reference projects

The SRS provided 34 reference healthcare systems (OpenMRS, Bahmni, various "hospital-management" GitHub repos, etc.). We used them for **reference only** — to understand the domain, cross-check terminology, and benchmark feature coverage.

We did **not** inherit their code, schemas, or architecture. This codebase is purpose-built for Nishat Nursing Home's SRS, written from scratch in a consistent style, with every module owned by the same code conventions.

## Reading order

If you're joining the project:

1. [docs/architecture.md](docs/architecture.md) — what lives where and why
2. [docs/api.md](docs/api.md) — the endpoint map
3. [docs/install.md](docs/install.md) — get it running locally
4. Log in and click around — the code is easier to read once you've seen the UI
5. [docs/deployment.md](docs/deployment.md) — when you're ready to ship

## License

Proprietary — Nishat Nursing Home.
