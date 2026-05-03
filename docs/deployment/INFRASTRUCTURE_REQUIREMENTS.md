# Infrastructure requirements

QUIZSNAP expects a typical **Laravel** stack. Below is the minimum for **staging / production rehearsal**.

## Application runtime

| Component | Role |
|-----------|------|
| **PHP-FPM** | Serves PHP; match Composer PHP constraint; enable `pdo_mysql`, `mbstring`, `openssl`, `json`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `bcmath` (and **phpredis** if using Redis client `phpredis`) |
| **Nginx or Apache** | TLS termination, static files, `try_files` to `public/index.php`, WebSocket upgrade proxy to Reverb |
| **Node.js** | Build-time only (`npm ci`, `npm run build`) unless you run a separate asset pipeline |

## Data and caching

| Component | Role |
|-----------|------|
| **MySQL 8.x** (or compatible) | Primary database; `utf8mb4` |
| **Redis** | Exam OTP storage, locks, rate limits, quiz config cache, active session counters, proctoring flood limits; Reverb horizontal scaling optional |

## Real-time

| Component | Role |
|-----------|------|
| **Laravel Reverb** | WebSocket server for `ShouldBroadcastNow` events (proctoring / governance / held results) |
| **HTTPS + WSS** | Public site over TLS; browser connects to **secure** WebSocket endpoint; configure proxy `Upgrade` and `Connection` headers |

## Storage

| Area | Requirement |
|------|-------------|
| **`storage/app/private`** | Sensitive files (proctoring evidence, course materials, portraits, extracted text). **Not** directly exposed by the web server |
| **`storage/app/public`** | May still hold **legacy** uploads; keep behind app policy; `storage:link` only if you intentionally serve legacy public files |
| **Disk space** | Plan for exam artifacts growth; monitor usage |

## Backups

| Target | Notes |
|--------|--------|
| **MySQL** | Automated dumps (daily minimum); test restore quarterly |
| **Private storage** | Backup `storage/app/private` (or volume snapshot) with same retention as DB |
| **`.env`** | Store in secrets manager; **never** commit |

## Logs and operations

| Topic | Recommendation |
|-------|----------------|
| **Log rotation** | Rotate `storage/logs/laravel.log` (or `LOG_CHANNEL` files) via `logrotate` or platform logging |
| **Monitoring** | HTTP 5xx rate, queue depth, Redis latency, Reverb process alive, MySQL connections |
| **Alerting** | Page on Redis down, Reverb down, DB connection exhaustion |

## Network / security

- Restrict MySQL and Redis to **application subnets** (not public internet)
- Use **strong** `APP_KEY`, Reverb app secret, DB and Redis passwords
- Set `APP_DEBUG=false` in production
