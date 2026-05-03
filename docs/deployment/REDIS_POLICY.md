# Redis policy (QUIZSNAP)

This document describes how the application **uses** Redis and what operators should assume in **production**.

## Production stance

**Treat Redis as required for live exams** in production. Run a highly available Redis where possible, monitor it continuously, and **alert immediately** on connection failures or elevated latency.

## What uses Redis

Examples (non-exhaustive; see `App\Services\ExamRedisService`, `App\Services\ExamOtpService`):

- Exam **OTP** state (hashed codes, TTL, verify attempts) — primary store is Redis when available
- Exam **session start locks** and **rate limits** (starts, OTP sends, proctoring event flood windows)
- **Quiz config** caching for hot exam paths
- **Active session counters** (global and per-exam keys)

## OTP: fails closed when Redis is unavailable

When **SMS / exam OTP** is enabled in system policy:

- If Redis is **not** reachable and **`EXAM_OTP_FALLBACK_ENABLED`** is **false** (default), OTP-related backend paths **fail closed** (e.g. service unavailable / blocked start), rather than silently succeeding without verification storage.

Operators must either:

- Keep Redis healthy, **or**
- Explicitly enable and **test** the configured OTP fallback (`EXAM_OTP_FALLBACK_ENABLED` + `EXAM_OTP_FALLBACK_CACHE_STORE`) and understand its semantics before relying on it.

## Other protections: degrade when Redis is unavailable

Several Redis-backed helpers are **best-effort**: if Redis is down, the code may **skip** rate limiting, **skip** locks, **skip** counters, or **fall back** to the database for quiz reads. That improves availability but **reduces** safety guarantees (e.g. duplicate start attempts become more likely under load).

**Do not** interpret “the site still loads” as “Redis is optional for exam integrity.”

## Monitoring checklist

- Redis **PING** or INFO from the app host
- Memory usage and **eviction** policy (avoid unexpected key loss)
- Connection count vs PHP-FPM worker count
- Correlation: Redis outage timestamps vs spike in duplicate sessions or OTP errors

## Summary

| Condition | OTP (when enabled) | Locks / rate limits / counters |
|-----------|--------------------|--------------------------------|
| Redis up | Intended behavior | Intended behavior |
| Redis down, OTP fallback **off** | **Fail closed** | **Degrade** (protections may no-op) |
| Redis down, OTP fallback **on** | Fallback path (must be tested) | **Degrade** |
