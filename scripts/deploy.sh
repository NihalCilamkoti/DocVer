#!/usr/bin/env bash
set -e

echo "=== Deploying DocVer Production Stack ==="

docker compose -f docker-compose.yml build
docker compose -f docker-compose.yml up -d

echo "✔ DocVer containers deployed successfully!"
