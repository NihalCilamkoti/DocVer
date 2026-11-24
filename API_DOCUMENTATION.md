# DocVer — API Reference (v1)

All DocVer API endpoints are versioned under `/api/v1`.

---

## Authentication & Headers

Protected routes require a Bearer token or Sanctum HttpOnly cookie session:
```http
Authorization: Bearer <sanctum_personal_access_token>
Accept: application/json
```

---

## Endpoints

### 1. System Health Probe
- **`GET /api/v1/health`**
  - **Auth:** Public
  - **Description:** Verifies database, cache, local storage, and AI circuit breaker status.
  - **Response:** `200 OK` (`{"status": "healthy", "checks": { ... }}`)

### 2. Authentication
- **`POST /api/v1/auth/register`** — User registration.
- **`POST /api/v1/auth/login`** — Sanctum token issue.
- **`POST /api/v1/auth/logout`** — Revokes current token.
- **`POST /api/v1/auth/forgot-password`** — Password reset link.
- **`POST /api/v1/auth/reset-password`** — Completes password reset.
- **`GET /api/v1/user`** — Current authenticated user profile.

### 3. Document Ingestion
- **`POST /api/v1/upload`**
  - **Auth:** Bearer Token
  - **Payload:** `multipart/form-data` (`file`, `slot: "A"|"B"`, `workspace_id`, `comparison_job_id`)
  - **Validation:** Inspects magic signatures (blocks executables), verifies ZIP bomb ratio (< 100:1), checks SHA-256 duplicate uploads.
  - **Response:** `201 Created`

### 4. Comparisons & Processing Status
- **`POST /api/v1/workspaces/{workspace}/comparisons`**
  - **Payload:** `{"title": "Contract Audit", "document_a_id": 1, "document_b_id": 2}`
  - **Response:** `202 Accepted`
- **`GET /api/v1/comparisons/{id}/status`**
  - **Polling:** Standard JSON with `progress` ($0-100\%$) and `stage`.
  - **SSE Streaming:** Pass `Accept: text/event-stream` for live token progress stream.
- **`POST /api/v1/comparisons/{id}/cancel`**
  - **Action:** Halts running pipeline, sets `status: cancelled`, triggers immediate ephemeral data cleanup.

### 5. Reports & PDF Export
- **`GET /api/v1/comparisons/{job}/report`** — JSON report with validated findings and metrics.
- **`GET /api/v1/comparisons/{job}/report/pdf`** — Downloads professional audit PDF report with evidence quotes.

### 6. Document-Aware AI Chat
- **`GET /api/v1/comparisons/{job}/chat`** — Retrieves conversation history.
- **`POST /api/v1/comparisons/{job}/chat`** — Submit question.
  - **Payload:** `{"message": "Compare payment terms", "stream": false}`
  - **Response:** Evidence citations (`docLabel`, `page`, `quote`), content, and follow-ups.
- **`DELETE /api/v1/comparisons/{job}/chat`** — Clears conversation memory.

### 7. Admin Provider Management
- **`GET /api/v1/admin/providers`** — Lists providers, health, and circuit breaker status (Super Admin only).
- **`PUT /api/v1/admin/providers/{provider}`** — Enable/disable, reset circuit breaker, change priority ranking.
- **`POST /api/v1/admin/providers/{provider}/ping`** — Upstream connectivity check.
