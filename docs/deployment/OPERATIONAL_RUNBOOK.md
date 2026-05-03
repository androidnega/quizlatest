# Operational runbook (QUIZSNAP)

Quick triage when something breaks in **staging or production**. No secrets are listed here.

## Students cannot receive OTP (SMS)

1. **System policy** — Confirm exam OTP is enabled in Admin system settings and not overridden elsewhere.
2. **Redis** — OTP state is Redis-backed by default. Check Redis connectivity from the app server (`redis-cli PING` or health dashboard).
3. **Fallback** — If `EXAM_OTP_FALLBACK_ENABLED` is false, Redis outage blocks OTP. If true, confirm the fallback cache store is writable and tested.
4. **Provider credentials** — Arkesel (or configured provider) keys live in **encrypted system settings** in the database, not in `.env`. Verify settings UI and DB backup integrity.
5. **Outbound network** — Firewall must allow HTTPS to the SMS API host; check application logs for HTTP errors from the SMS service.
6. **Rate limits** — Too many sends per student/window returns errors; check logs for rate-limit messages.

## Live proctoring updates stop (no real-time warnings / risk updates)

1. **Reverb process** — Confirm `php artisan reverb:start` (or systemd/supervisor unit) is **running** and restarted after deploys.
2. **Browser WebSocket** — DevTools → Network → **WS**: expect `101` upgrade; failures often show **proxy** misconfiguration.
3. **TLS / WSS** — Public URL must use `https` / `wss` consistently with `REVERB_*` and `VITE_REVERB_*` used at **build time**.
4. **`BROADCAST_CONNECTION`** — Must be `reverb` (or your chosen broadcaster) in `.env` on the app servers.
5. **Channel auth** — Private channels require authenticated users; verify session cookie domain / `SESSION_SECURE_COOKIE` on HTTPS.

## Redis fails or is slow

1. **Infra** — Memory, persistence, replication (if any), `SLOWLOG`, connected clients.
2. **Application** — Spike in 503s during OTP; loss of rate limiting / locks (see [REDIS_POLICY.md](./REDIS_POLICY.md)).
3. **Mitigation** — Restore Redis before re-enabling heavy exam traffic; consider maintenance mode if integrity cannot be guaranteed.

## Reverb fails

1. **Process** — Logs from supervisor/systemd; port bind conflicts.
2. **Nginx** — `proxy_read_timeout`, WebSocket headers, upstream to correct Reverb port.
3. **Scaling** — If multiple Reverb nodes, ensure `REVERB_SCALING_*` and shared Redis are configured per Laravel Reverb docs.

## Uploads fail (proctoring snapshots, course materials, registration portrait)

1. **Disk space** — `storage/app/private` (and `public` if legacy) free space.
2. **Permissions** — Web server user must write to `storage/` and `bootstrap/cache/`.
3. **`FILESYSTEM_DISK`** — Should align with deployment (typically `local` / private root).
4. **PHP limits** — `upload_max_filesize`, `post_max_size`, `max_execution_time` for large files.
5. **Reverse proxy** — `client_max_body_size` (Nginx) or equivalent.

## Database issues

1. **Connections** — Max connections vs PHP-FPM workers; persistent connections policy.
2. **Migrations** — Failed mid-migration: restore from backup; do not leave partial state without DBA guidance.
3. **Slow queries** — Enable slow log during rehearsal; add indexes only after `EXPLAIN` analysis.

## Backup and restore reminders

- **Before** risky operations: fresh **MySQL** dump and **private storage** snapshot.
- **Restore test:** Quarterly restore into an isolated instance to prove backups are usable.
- **Secrets:** Restore `.env` or secrets manager entries separately; they are not in SQL dumps.

## Escalation

- Collect: timestamp, affected exam IDs, user IDs (hashed if reporting externally), request ID / correlation id from logs, Redis/Reverb/MySQL health at that time.
