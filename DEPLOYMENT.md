# DocVer — Production Deployment Architecture & Guide

This document outlines the production hosting architecture, infrastructure configurations, containerization, and process supervision for DocVer.

---

## 1. Recommended Production Architecture

- **Frontend:** [Vercel](https://vercel.com) (Edge network, global CDN, automatic SSL).
- **Backend API:** Linux VPS (Ubuntu 22.04 LTS / 24.04 LTS) running PHP 8.3-FPM + Nginx.
- **Database:** Managed MySQL 8.0 (AWS RDS, DigitalOcean Managed Database, or hardened local instance).
- **In-Memory Cache & Queues:** Managed Redis 7.0+ cluster.
- **Process Supervision:** Supervisor / systemd managing PHP-FPM, Horizon, and cron scheduler.

---

## 2. Docker Deployment

DocVer provides an enterprise Docker configuration:

```bash
# Build and run complete multi-container stack
docker-compose up -d --build

# View container status
docker-compose ps

# Run migrations inside container
docker-compose exec app php artisan migrate --force
```

### Internal Network Security
- MySQL (`db`) and Redis (`redis`) containers are bound strictly to the internal bridge network `docver-net`.
- No database or Redis ports are exposed publicly to the host interface.

---

## 3. Nginx Web Server Configuration

Place the Nginx configuration at `/etc/nginx/sites-available/docver.conf`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.docver.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.docver.example.com;

    ssl_certificate /etc/letsencrypt/live/api.docver.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.docver.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    root /var/www/docver/public;
    index index.php index.html;

    client_max_body_size 50M;

    # Security Headers
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

---

## 4. Supervisor Configuration

Place configuration at `/etc/supervisor/conf.d/docver.conf`:

```ini
[program:docver-horizon]
process_name=%(program_name)s
command=php /var/www/docver/artisan horizon
autostart=true
autorestart=true
user=docver
redirect_stderr=true
stdout_logfile=/var/log/docver/horizon.log
stopwaitsecs=3600

[program:docver-scheduler]
process_name=%(program_name)s
command=/bin/sh -c "while [ true ]; do (php /var/www/docver/artisan schedule:run --verbose --no-interaction &); sleep 60; done"
autostart=true
autorestart=true
user=docver
redirect_stderr=true
stdout_logfile=/var/log/docver/scheduler.log
```

---

## 5. Automated Data Purge Cron

DocVer automatically registers the temporary data purge in the Laravel scheduler:
```php
Schedule::command('docver:purge-temporary')->hourly()->withoutOverlapping();
```
Ensures disk binaries and expired Redis chunks are purged hourly.
