# DocVer System Architecture

DocVer is an enterprise-grade, privacy-first AI SaaS for legal and financial document verification and comparison.

```mermaid
graph TD
    Client["Browser / Enterprise Client"] -->|HTTPS / WSS| Nginx["Nginx Gateway (:8080)"]
    Nginx -->|Static & SSR| Frontend["Next.js 15 App Router (:3000)"]
    Nginx -->|API /api/v1| Backend["Laravel 11 PHP 8.3 FPM (:9000)"]
    
    Backend -->|Metadata & Tenant Audit| MySQL[("MySQL 8.0")]
    Backend -->|Queues, Sliding Rate Limits, Ephemeral Cache| Redis[("Redis 7.0")]
    
    Redis --> WorkerDoc["Document Worker (Tesseract OCR / PDF Parser)"]
    Redis --> WorkerHorizon["Laravel Horizon / Queue Coordinator"]
    
    Backend -->|Fallback AI Orchestration| AIProviders["AI Provider Cluster"]
    AIProviders --> Gemini["Google Gemini (Primary)"]
    AIProviders --> Groq["Groq LPU (Ultra-Fast)"]
    AIProviders --> OpenRouter["OpenRouter (Claude 3.5 Sonnet / GPT-4o)"]
```

## Modular Domain Boundaries

- **Frontend (`frontend/`):** Independent Next.js application utilizing React 19, TypeScript strict mode, Tailwind CSS, shadcn/ui, TanStack Query, and Zustand.
- **Backend (`backend/`):** Standard Laravel application structured around domain modules under `app/Modules/` and domain services under `app/Services/`.
- **Infrastructure (`infrastructure/`):** Docker, Nginx, MySQL, Redis, and health monitoring definitions.
- **Documentation (`documentation/`):** Complete specifications and operational guides.
