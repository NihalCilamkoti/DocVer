# Frontend Architecture Specification

The DocVer frontend is built with Next.js 15 App Router in TypeScript strict mode.

## Directory Structure

```
frontend/src/
├── app/                  # Next.js App Router route hierarchy
│   ├── (marketing)/      # Public landing, about, pricing, contact
│   ├── (auth)/           # Login, register, password reset, email verification
│   ├── (dashboard)/      # Workspace dashboard, comparison, history, reports, settings
│   └── admin/            # Super administrator provider & health consoles
├── components/           # Reusable UI primitives (shadcn/ui) and layout components
├── features/             # Domain-specific workflows, schemas, and typed hooks
├── hooks/                # Global React hooks
├── services/             # API clients and WebSocket transports
├── stores/               # Zustand state stores
├── lib/                  # Shared utilities and Axios/Fetch clients
└── types/                # Strict TypeScript contracts and DTOs
```

## Architectural Rules

1. **Clean Separation of Concerns:** UI components render views; API interactions live in `services/api/` and `features/*/services`.
2. **Server State Management:** TanStack Query handles caching, deduplication, and invalidation of remote backend data.
3. **Local State:** Zustand is reserved for active comparison session state and chat panels.
4. **Zero Key Leakage:** AI provider API keys are never bundled or requested by the frontend.
