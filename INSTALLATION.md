# DocVer — Installation & Local Development Setup

This guide details the step-by-step local development setup for both the Next.js frontend and Laravel backend.

---

## System Requirements

- **PHP:** 8.3+ with `pdo_mysql`, `mbstring`, `zip`, `gd`, `intl`, `bcmath`, `opcache`, `redis`
- **Composer:** 2.6+
- **Node.js:** 20+ with `npm`
- **MySQL:** 8.0+
- **Redis:** 7.0+

---

## 1. Backend Setup (Laravel)

### Step 1: Install Dependencies
```bash
composer install
```

### Step 2: Environment Configuration
Copy `.env.example` to `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

Configure your local MySQL and Redis credentials in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=docver_db
DB_USERNAME=docver
DB_PASSWORD=docver_secret

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

AI_PRIMARY_PROVIDER=gemini
GEMINI_API_KEY=your_gemini_api_key_here
OPENROUTER_API_KEY=your_openrouter_api_key_here
```

### Step 3: Run Database Migrations & Seeders
```bash
# Create database if not exists
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS docver_db; CREATE DATABASE IF NOT EXISTS docver_testing;"

# Run migrations and seed sample workspace and accounts
php artisan migrate --seed
```

### Step 4: Run Horizon / Queue Workers
```bash
# Start Horizon dashboard and workers
php artisan horizon

# Or start dedicated document and AI queue workers manually:
php artisan queue:work redis --queue=documents --timeout=180
php artisan queue:work redis --queue=ai --timeout=120
```

### Step 5: Start Backend Server
```bash
php artisan serve --port=8000
```

---

## 2. Frontend Setup (Next.js)

### Step 1: Install Dependencies
```bash
npm install
```

### Step 2: Environment Configuration
Create or verify `.env.local`:
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api/v1
```

### Step 3: Start Development Server
```bash
npm run dev
```
Open [http://localhost:3000](http://localhost:3000) in your browser.

---

## 3. Running Test Suites

```bash
# Run all 76 unit and feature tests
php artisan test

# Verify Next.js TypeScript and production build
npm run build
```
