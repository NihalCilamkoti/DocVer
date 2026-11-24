#!/usr/bin/env bash
set -e

echo "=== DocVer Environment Setup ==="

# Backend setup
echo "1. Setting up backend dependencies..."
if [ -d "backend" ]; then
    cd backend
    if [ ! -f ".env" ]; then
        cp .env.example .env
        echo "Created backend/.env from .env.example"
    fi
    composer install --no-interaction
    php artisan key:generate
    php artisan migrate --force
    cd ..
fi

# Frontend setup
echo "2. Setting up frontend dependencies..."
if [ -d "frontend" ]; then
    cd frontend
    if [ ! -f ".env.local" ]; then
        cp .env.example .env.local 2>/dev/null || true
    fi
    npm install
    cd ..
fi

echo "✔ DocVer setup complete!"
