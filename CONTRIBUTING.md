# Contributing to DocVer

Thank you for your interest in contributing to DocVer, an enterprise AI-powered document comparison and verification platform.

## Architecture Guidelines

1. **Independent Stacks:** The `frontend/` (Next.js 15) and `backend/` (Laravel 11 / PHP 8.3) are independently installable and deployable.
2. **Domain Boundaries:** Backend business logic belongs under `app/Modules/` and domain services, never bloated into controllers.
3. **Frontend Rules:** Reusable UI components belong in `components/ui/`. Domain feature workflows belong in `features/`. Never expose AI provider API keys to the browser.
4. **Zero-Data Retention:** Never permanently persist raw document binaries, extracted page text, or vector embeddings. Use encrypted temporary storage and always clean up.
5. **Testing Requirement:** All contributions must pass `backend: php artisan test` and `frontend: npm run build` before opening a pull request.

## Local Development Workflow

```bash
# 1. Run environment setup
./scripts/setup.sh

# 2. Run backend test suite
cd backend && php artisan test

# 3. Run frontend typecheck and build
cd frontend && npm run build

# 4. Run full test verification
./scripts/test-all.sh
```

## Pull Request Process

1. Fork the repository and create a branch from `develop`.
2. Commit changes with clear, semantic commit messages (`feat:`, `fix:`, `refactor:`, `docs:`).
3. Ensure all tests pass.
4. Submit PR against `develop` with a detailed description of architectural modifications.
