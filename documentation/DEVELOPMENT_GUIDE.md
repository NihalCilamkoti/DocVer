# Developer Setup & Contribution Guide

## Prerequisites

- PHP 8.3+ with `pdo_mysql`, `redis`, `gd`, `zip`, `bcmath`
- Node.js 20+ & npm 10+
- Composer 2.x
- MySQL 8.0+
- Redis 7.0+
- Tesseract OCR (`brew install tesseract` on macOS, `apt install tesseract-ocr` on Ubuntu)

## Local Setup Instructions

```bash
# 1. Run automated setup script
./scripts/setup.sh

# 2. Start Backend Development Server
cd backend
php artisan serve --port=8000

# In a separate terminal: Start Queue Worker
cd backend
php artisan horizon

# 3. Start Frontend Development Server
cd frontend
npm run dev

# Open http://localhost:3000
```

## Running Automated Tests

```bash
# Run all backend PHP tests
cd backend && php artisan test

# Run frontend Next.js production build
cd frontend && npm run build

# Run everything together
./scripts/test-all.sh
```
