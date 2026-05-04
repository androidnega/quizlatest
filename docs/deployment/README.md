# QUIZSNAP deployment documentation

Use these documents for **staging / production rehearsal** and go-live planning.

| Document | Purpose |
|----------|---------|
| [DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md) | Ordered commands and post-deploy checks |
| [INFRASTRUCTURE_REQUIREMENTS.md](./INFRASTRUCTURE_REQUIREMENTS.md) | Servers, TLS, backups |
| [REDIS_POLICY.md](./REDIS_POLICY.md) | Redis expectations and failure modes |
| [LOAD_TEST_PLAN.md](./LOAD_TEST_PLAN.md) | Scenarios and bottlenecks |
| [OPERATIONAL_RUNBOOK.md](./OPERATIONAL_RUNBOOK.md) | Incident triage |

Environment template (no secrets): **`.env.production.example`** at repository root.

**cPanel / shared hosting:** Prefer building assets with `npm run build`, deploy **`public/build`**, point the web root at **`public/`**, and use **Admin → System settings → Infrastructure** to turn off Redis and/or live sockets when those services are not available (cache/DB and HTTP polling fallbacks). Step-by-step commands are in [DEPLOYMENT_CHECKLIST.md](./DEPLOYMENT_CHECKLIST.md) section **cPanel / shared hosting**.
