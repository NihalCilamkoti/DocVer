# DocVer — Troubleshooting & Diagnostics Guide

This document assists administrators and DevOps engineers in diagnosing common issues across the DocVer stack.

---

## 1. System Health Check

Run the built-in health probe:
```bash
curl -i http://localhost:8000/api/v1/health
```

Expected output:
```json
{
  "status": "healthy",
  "checks": {
    "database": { "status": "up", "message": "Connected" },
    "cache": { "status": "up" },
    "storage": { "status": "up" },
    "ai_providers": {
      "gemini": { "configured": true, "circuit_breaker": "closed" },
      "openrouter": { "configured": true, "circuit_breaker": "closed" }
    }
  }
}
```

---

## 2. Common Issues & Resolutions

### Issue 1: "File exceeds workspace limit" or "Unsupported file signature"
- **Cause:** Uploaded file failed magic byte signature verification or exceeded workspace file size setting.
- **Diagnosis:** Check `max_file_size_mb` in `workspaces` table. Ensure file is not an executable or corrupted container.
- **Resolution:** Verify file integrity and workspace plan limits.

### Issue 2: "Comparison job stuck in processing"
- **Cause:** Redis queue worker is not running or crashed.
- **Diagnosis:**
  ```bash
  php artisan horizon:status
  # Check failed jobs
  php artisan queue:failed
  ```
- **Resolution:**
  ```bash
  # Restart horizon or workers
  php artisan horizon:terminate
  php artisan queue:retry all
  ```

### Issue 3: "Circuit breaker OPEN (cooldown active)"
- **Cause:** The upstream AI provider returned consecutive 429 rate limits or 500 server errors.
- **Diagnosis:**
  Check `GET /api/v1/admin/providers` using a super administrator account.
- **Resolution:**
  Reset the circuit breaker via the admin endpoint:
  ```bash
  curl -X PUT http://localhost:8000/api/v1/admin/providers/gemini \
       -H "Authorization: Bearer <token>" \
       -H "Content-Type: application/json" \
       -d '{"reset_circuit_breaker": true}'
  ```

### Issue 4: "Disk storage filling up"
- **Cause:** Scheduled purge command has not been triggered.
- **Diagnosis:**
  ```bash
  php artisan schedule:list
  ```
- **Resolution:**
  Run the manual purge command:
  ```bash
  php artisan docver:purge-temporary
  ```

---

## 3. Diagnostic Commands Reference

```bash
# Clear application configuration cache
php artisan config:clear
php artisan cache:clear

# Check queue backlog
php artisan queue:monitor redis:documents,redis:ai,redis:default

# Inspect Horizon metrics
php artisan horizon:snapshot
```
