# Production Deployment & Infrastructure Guide

DocVer is architected to deploy via Docker Compose or Kubernetes with isolated frontend and backend services.

## Production Stack Overview

```
Frontend:     Node.js 20 / Next.js 15 Standalone (Container: docver-frontend)
Backend:      PHP 8.3 FPM + Tesseract OCR (Container: docver-app)
Worker 1:     Document Processing Worker (Container: docver-worker-documents)
Worker 2:     AI Orchestration / Laravel Horizon (Container: docver-worker-ai)
Gateway:      Nginx Alpine Reverse Proxy (Container: docver-nginx)
Database:     MySQL 8.0 (Container: docver-db)
Queue/Cache:  Redis 7.0 Alpine (Container: docver-redis)
```

## Quick Production Start

```bash
# 1. Clone repository
git clone git@github.com:your-org/docver.git
cd docver

# 2. Configure environment
cp .env.example .env
# Fill in GEMINI_API_KEY, GROQ_API_KEY, OPENROUTER_API_KEY

# 3. Build & start all containers
docker compose up -d --build

# 4. Run database migrations & key generation
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
```

## Health Verification

```bash
./infrastructure/monitoring/health-check.sh
```
