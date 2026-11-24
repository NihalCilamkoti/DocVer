# Database Schema & Entity Relationships

DocVer uses MySQL 8.0 for persistent relational metadata.

## Entity Relationship Overview

```mermaid
erDiagram
    USERS ||--o{ WORKSPACES : owns
    WORKSPACES ||--o{ WORKSPACE_MEMBERS : has
    USERS ||--o{ WORKSPACE_MEMBERS : joins
    WORKSPACES ||--o{ COMPARISON_JOBS : owns
    COMPARISON_JOBS ||--o{ DOCUMENT_METADATA : contains
    COMPARISON_JOBS ||--|| COMPARISON_REPORTS : produces
    COMPARISON_REPORTS ||--o{ COMPARISON_FINDINGS : contains
    WORKSPACES ||--o{ USAGE_RECORDS : logs
    WORKSPACES ||--o{ AUDIT_EVENTS : tracks
```

## Migration Index

1. `users`: Authentication identities, roles (user, workspace_admin, super_admin).
2. `workspaces`: Tenant accounts, tier limits, settings.
3. `workspace_members`: Pivot table for multi-tenant workspace membership.
4. `document_metadata`: Temporary file metadata (hash, pages, size). Never stores document text!
5. `comparison_jobs`: Orchestration state, stage progress, similarity score, resume checkpoints.
6. `comparison_reports`: Executive summary, difference counts, export metadata.
7. `comparison_findings`: Verified findings with character quotes and page numbers.
8. `usage_records`: AI token audit logs and duration.
9. `provider_quotas`: Daily request and token counters, reset timestamps.
10. `provider_health`: Latency, circuit breaker states, and uptime logs.
11. `audit_events`: Security event log.
