# Middleware Architecture & Execution Order

DocVer implements 14 independent, single-responsibility middleware classes under `app/Http/Middleware/`.

## Pipeline Execution Order

```mermaid
flowchart TD
    Req["Incoming HTTP Request"] --> M1["1. AddRequestId"]
    M1 --> M2["2. SecurityHeaders"]
    M2 --> M3["3. PreventSuspiciousRequests"]
    M3 --> M4["4. VerifyRequestSize"]
    M4 --> M5["5. EnforceApiRateLimits"]
    M5 --> M6["6. AuthenticateRequest"]
    M6 --> M7["7. EnsureUserIsActive"]
    M7 --> M8["8. VerifyWorkspaceAccess"]
    M8 --> M9["9. CheckDailyUsageLimits"]
    M9 --> M10["10. CheckSubscriptionLimits"]
    M10 --> M11["11. CheckDocumentOwnership"]
    M11 --> M12["12. CheckComparisonOwnership"]
    M12 --> M13["13. ValidateUploadSecurity"]
    M13 --> M14["14. EnsureAdminAccess (Admin Routes Only)"]
    M14 --> Controller["Controller Action"]
```

## Middleware Catalog

1. `AddRequestId`: Assigns unique UUID `X-Request-ID` to request and response headers.
2. `SecurityHeaders`: Injects HSTS, X-Frame-Options (DENY), CSP, and nosniff.
3. `PreventSuspiciousRequests`: Detects and blocks directory traversal and SQL injection probes.
4. `VerifyRequestSize`: Enforces max 50MB payload limits.
5. `EnforceApiRateLimits`: Sliding window rate limiter in Redis.
6. `AuthenticateRequest`: Enforces valid Sanctum tokens.
7. `EnsureUserIsActive`: Blocks deactivated accounts.
8. `VerifyWorkspaceAccess`: Prevents unauthorized cross-tenant operations.
9. `CheckDailyUsageLimits`: Tracks per-user daily token limits.
10. `CheckSubscriptionLimits`: Enforces workspace tier quotas.
11. `CheckDocumentOwnership`: IDOR prevention for documents.
12. `CheckComparisonOwnership`: IDOR prevention for comparison jobs.
13. `ValidateUploadSecurity`: Inspects binary signatures and magic bytes.
14. `EnsureAdminAccess`: Verifies super-administrator privileges.
