# Load test plan (staging / production rehearsal)

Run these scenarios on an environment that mirrors **production sizing** (PHP-FPM workers, MySQL tier, Redis, Reverb). Record baseline metrics before tuning.

## 1. Concurrent exam starts

- **Goal:** Validate session creation, Redis locks, and rate limits under burst.
- **Method:** Many virtual users start the same published exam within a short window.
- **Watch:** HTTP 429 on start attempts, 503 on OTP backend issues, MySQL `exam_sessions` insert rate, Redis CPU and command latency.

## 2. Answer autosave

- **Goal:** Sustained writes to `exam_session_answers` (and related rows).
- **Method:** Active students save answers on an interval similar to the client.
- **Watch:** MySQL write latency, row lock waits, PHP-FPM queue depth, response time percentiles.

## 3. Proctoring batch events

- **Goal:** Metadata persistence and API throughput for queued snapshots.
- **Method:** Burst POSTs to proctoring upload + event endpoints for many sessions.
- **Watch:** `proctoring_events` insert rate, JSON column size, Redis flood counters, application error rate.

## 4. Reverb subscriptions

- **Goal:** WebSocket capacity and stability.
- **Method:** Many concurrent private channel subscriptions on `exam-session.{sessionId}` with periodic broadcasts (or synthetic broadcast load in staging).
- **Watch:** Reverb CPU/memory, connection count, proxy timeouts, client reconnect rate.

## 5. OTP send and verify

- **Goal:** SMS provider limits and Redis TTL behavior.
- **Method:** Controlled OTP request and verify loops per student identity (respect provider sandbox rules).
- **Watch:** Redis key churn, 503 / “service unavailable” responses, Arkesel or provider error codes (from logs).

## 6. Redis outage behavior (controlled drill)

- **Goal:** Document actual behavior for operators.
- **Method:** Stop Redis briefly during a rehearsal (staging only).
- **Watch:** Exam start outcomes, OTP behavior with `EXAM_OTP_FALLBACK_ENABLED` true vs false, absence of rate limits, misleading “active session” counters.

## 7. MySQL write pressure

- **Goal:** Find saturation point for mixed read/write workload.
- **Method:** Combine scenarios 1–3 with coordinator dashboard reads.
- **Watch:** Threads_running, slow query log, replication lag if using replicas.

## Expected bottlenecks

- **MySQL** — `exam_sessions`, `exam_session_answers`, `proctoring_events`
- **Redis** — single-node throughput and connection limits
- **Reverb** — connection fanout and TLS proxy configuration
- **External SMS** — OTP throughput ceiling

## Pass criteria (example)

Define with stakeholders, for example: p95 API latency under threshold, error rate under threshold, zero uncaught 500s during Redis-up baseline, and a written outcome for the Redis drill.
