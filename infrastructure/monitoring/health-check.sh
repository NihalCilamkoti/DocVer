#!/usr/bin/env bash
set -e

echo "=== DocVer Infrastructure Health Check ==="

# 1. Check MySQL
if nc -z 127.0.0.1 3306; then
    echo "✔ MySQL is reachable on port 3306"
else
    echo "✘ MySQL is not responding"
fi

# 2. Check Redis
if nc -z 127.0.0.1 6379; then
    echo "✔ Redis is reachable on port 6379"
else
    echo "✘ Redis is not responding"
fi

# 3. Check Web API Endpoint
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/api/v1/health || true)
if [ "$HTTP_STATUS" = "200" ]; then
    echo "✔ DocVer Backend API is healthy (HTTP 200)"
else
    echo "ℹ Backend API status: $HTTP_STATUS"
fi

echo "=== Health check completed ==="
