# Backend Architecture Specification

The DocVer backend is a production-grade Laravel application utilizing PHP 8.3+, Eloquent ORM, Sanctum, Redis Queues, and Horizon.

## Directory Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/ # Thin API controllers
│   │   ├── Middleware/        # 14 single-responsibility security & rate limit filters
│   │   ├── Requests/          # Form request validation classes
│   │   └── Resources/         # Clean API JSON transformers
│   ├── Models/                # Eloquent models with strict typing and relationships
│   ├── Modules/               # Domain modules (Actions, DTOs, Services)
│   ├── Services/              # Core business engines (AI, Documents, Comparison)
│   └── Jobs/                  # Asynchronous queue workers
```

## Domain Modules (`app/Modules/`)

- **Authentication:** Token issuance, session management, and credential verification.
- **Documents:** Parsing, OCR, chunking, and ephemeral storage.
- **AI:** Tri-provider failover, quota accounting, and model capability routing.
- **Comparison:** Semantic matching, numerical analysis, date comparison, and evidence validation.
- **Reports:** Verifiable PDF generation and executive summaries.
