# DocVer REST API Reference

Base URL: `/api/v1`

## Authentication

All protected endpoints require a Sanctum Bearer token:
`Authorization: Bearer <token>`

### Auth Endpoints
- `POST /auth/register`: Create user account & workspace.
- `POST /auth/login`: Authenticate and issue Bearer token.
- `POST /auth/logout`: Revoke active session token.
- `GET /auth/me`: Current user profile & workspace context.

### Document & Comparison Endpoints
- `POST /comparisons/upload`: Upload exactly two documents for comparison.
- `POST /comparisons`: Create and dispatch comparison job.
- `GET /comparisons/{id}`: Detailed report and findings.
- `GET /comparisons/{id}/status`: Progress tracking (supports SSE stream).
- `POST /comparisons/{id}/cancel`: Abort in-progress comparison.
- `GET /comparisons/{id}/download`: Download verifiable PDF report.
- `POST /comparisons/{id}/chat`: Contextual Q&A with citations.

### Admin Provider Management (Super Admin)
- `GET /admin/providers`: List providers, health, and real-time quotas.
- `PUT /admin/providers/{provider}`: Toggle provider on/off or reorder priority.
- `POST /admin/providers/{provider}/ping`: Test upstream latency.
- `POST /admin/providers/switch-primary`: Manually force primary provider.
- `GET /admin/providers/fallback-events`: Audit log of automatic failover triggers.
- `GET /admin/providers/costs`: Token consumption & cost summary.
