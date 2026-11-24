#!/usr/bin/env bash
set -e

echo "=== Running Full DocVer Test Suite ==="

# 1. Backend PHPUnit / Pest tests
echo "--> Testing Backend (Laravel PHP 8.3)..."
cd backend && php artisan test
cd ..

# 2. Frontend TypeScript typecheck
echo "--> Testing Frontend (TypeScript typecheck)..."
cd frontend && npm run typecheck
cd ..

echo "✔ All tests and builds passed successfully!"
