#!/usr/bin/env bash
set -e

echo "=== Purging Ephemeral Storage & Expired Chunks ==="

if [ -d "backend" ]; then
    cd backend && php artisan docver:purge-temporary --hours=24
    cd ..
fi

echo "✔ Ephemeral cleanup complete."
