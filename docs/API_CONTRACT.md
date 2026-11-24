# DocVer API Contract (v1)

This specification defines the communication protocol between the Next.js App Router frontend and the Laravel 11 MySQL/Redis backend.

## Base URL & Transport

- **Local Development:** `http://localhost:8000/api/v1`
- **CSRF Cookie Endpoint:** `http://localhost:8000/sanctum/csrf-cookie`
- **Authentication Mechanism:**
  1. **Browser Cookie Session (Primary):** Authenticated using secure, SameSite, HttpOnly Laravel Sanctum session cookies. Requests must pass `credentials: 'include'`.
  2. **Bearer Token (Headless/Mobile):** Supported via `Authorization: Bearer <token>`.

---

## Standard Response Format

Every API endpoint adheres to the standardized JSON envelope:

### Success Response (`200 OK`, `201 Created`, `202 Accepted`)

```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

### Error Response (`400 Bad Request`, `401 Unauthenticated`, `403 Forbidden`, `404 Not Found`, `422 Validation Failed`, `429 Too Many Requests`)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": [
      "The specific validation message."
    ]
  }
}
```

---

## API Endpoints Reference

### 1. Authentication & Profile (`/api/v1/auth`, `/api/v1/user`)

| Method | Endpoint | Description | Rate Limit |
|---|---|---|---|
| `POST` | `/api/v1/auth/register` | Register new account and auto-provision organization workspace | 60/min |
| `POST` | `/api/v1/auth/login` | Authenticate user; returns Sanctum token and sets HttpOnly cookie | 60/min |
| `POST` | `/api/v1/auth/logout` | Revoke current access token or destroy browser session | 60/min |
| `POST` | `/api/v1/auth/forgot-password` | Dispatches password reset link (anti-enumeration safe) | 60/min |
| `POST` | `/api/v1/auth/reset-password` | Updates password using validated reset token | 60/min |
| `GET` | `/api/v1/user` | Fetches authenticated user profile and workspace affiliations | Auth |
| `PUT` | `/api/v1/user/profile` | Update user name, phone, or avatar URL | Auth |
| `PUT` | `/api/v1/user/password` | Change password with current password verification | Auth |

### 2. Workspaces (`/api/v1/workspaces`)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/workspaces` | List all workspaces accessible by the user |
| `POST` | `/api/v1/workspaces` | Create a new organizational workspace |
| `GET` | `/api/v1/workspaces/{workspace}` | View workspace details and retention policies |
| `PUT` | `/api/v1/workspaces/{workspace}` | Update workspace limits (admin only) |

### 3. Document Staging & Upload (`/api/v1/workspaces/{workspace}/documents`)

| Method | Endpoint | Description | Rate Limit |
|---|---|---|---|
| `POST` | `/api/v1/workspaces/{workspace}/documents/upload` | Multipart form file upload (Slot A or B). Validates MIME, size, computes SHA-256 | 30/min |
| `GET` | `/api/v1/workspaces/{workspace}/documents/{document}` | Inspect document metadata and verification checksum | Auth |

### 4. Comparisons & Jobs (`/api/v1/workspaces/{workspace}/comparisons`, `/api/v1/jobs`)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/workspaces/{workspace}/comparisons` | Paginated comparison audit history |
| `POST` | `/api/v1/workspaces/{workspace}/comparisons` | Queue a new comparison between Document A and Document B |
| `GET` | `/api/v1/workspaces/{workspace}/comparisons/{comparison}` | Retrieve comparison job state, report, and findings |
| `GET` | `/api/v1/jobs/{job}` | Lightweight status polling endpoint (`queued`, `processing`, `completed`, `failed`) |

### 5. Comparison Reports & Findings (`/api/v1/comparisons/{job}/report`)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/comparisons/{job}/report` | Full redline report with matching, missing, modified clauses, numerical discrepancies, and contradictions |

### 6. AI Conversational Chat (`/api/v1/comparisons/{job}/chat`)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/comparisons/{job}/chat` | Retrieve conversation history for this document pair |
| `POST` | `/api/v1/comparisons/{job}/chat` | Send question; returns AI answer with exact document page citations |
| `DELETE` | `/api/v1/comparisons/{job}/chat` | Clear session conversation |

### 7. Usage & Health (`/api/v1/workspaces/{workspace}/usage`, `/api/v1/provider-health`)

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/workspaces/{workspace}/usage` | Current monthly quota metrics (comparisons, pages, storage) |
| `GET` | `/api/v1/provider-health` | Internal OCR and AI engine status and latencies |
