# DocVer Phase 5 — Multi-Provider AI Integration

This document details the multi-provider AI orchestration architecture, automatic failover mechanisms, circuit breakers, security sanitization, and administrative management implemented in DocVer.

---

## 1. Architecture Overview

The system is decoupled from specific AI vendors via a unified provider interface and a resilient routing engine:

```
app/Services/AI/
  Contracts/
    AIProviderInterface.php          # Standard provider contract
  Providers/
    BaseAIProvider.php               # Abstract base with HTTP retries, backoff, jitter & error mapping
    GeminiProvider.php               # Google Gemini REST API (v1beta) integration
    OpenRouterProvider.php           # OpenRouter REST API integration
  Router/
    AIProviderRouter.php             # Primary -> Fallback orchestration, circuit breaker & quota enforcement
  DTOs/
    AIRequest.php                    # Task-type, temperature, schema, tokens, and context metadata
    AIResponse.php                   # Content, structuredData, provider, model, usage, isFallback
    UsageStatsDTO.php                # Prompt tokens, completion tokens, duration ms, estimated USD cost
    StreamChunkDTO.php               # Token deltas for SSE streaming
  Exceptions/
    AIProviderException.php          # Base typed exception with status and Retry-After
    RateLimitException.php           # HTTP 429 with retry-after parsing
    AuthenticationException.php      # HTTP 401 / 403 (invalid API key)
    PaymentRequiredException.php     # HTTP 402 (OpenRouter credit exhaustion)
    ProviderServerException.php      # HTTP 5xx upstream failure
    ContextLengthExceededException.php # Prompt token length overflow
    InvalidStructuredOutputException.php # Malformed or invalid JSON response
    AllProvidersUnavailableException.php # Failover chain exhausted
    QuotaExceededException.php       # User/workspace token quota exhausted
  Health/
    CircuitBreaker.php               # Closed / Open / Half-Open state machine
    ProviderHealthMonitor.php        # Real-time health recording in DB and fast cache
  Quota/
    QuotaManager.php                 # Daily user & monthly workspace token enforcement and tracking
  Security/
    PromptSanitizer.php              # Injection neutralization, XML boundary isolation, output sanitization
```

---

## 2. Integrated Providers & Supported Models

| Provider | Default Model | Reasoning Model | Context Window | Key Features |
|---|---|---|---|---|
| **Google Gemini** | `gemini-2.0-flash` | `gemini-1.5-pro` | Up to 1,000,000 tokens | Native schema-guided JSON (`responseMimeType: "application/json"`), SSE streaming, high-speed multi-document audit. |
| **OpenRouter** | `anthropic/claude-3.5-sonnet` | `openai/gpt-4o` | Up to 200,000 tokens | Universal access to Claude, GPT-4o, and Llama 3 models; native `json_object` mode; SSE chunk streaming. |

### Task-Specific Model Selection
The router resolves models dynamically based on task type:
- **`comparison`**: Uses deep-reasoning models (`gemini-1.5-pro` or `anthropic/claude-3.5-sonnet`) with structured output schema.
- **`chat`**: Uses low-latency models (`gemini-2.0-flash` or `openai/gpt-4o-mini`).
- **`general`**: Balanced general-purpose models.

---

## 3. Automatic Failover & Resilience

### Failover Order
1. **Primary Provider** (default: `gemini`).
2. **Secondary Provider** (default: `openrouter`).
3. Additional eligible configured providers in priority rank.

### Handled Error Scenarios
- **HTTP 429 (Rate Limit):** Extracts `Retry-After` seconds; if cooldown is active, trips circuit breaker and switches immediately to fallback provider.
- **HTTP 401 / 403 (Invalid Credentials):** Tripped as non-retryable provider failure; instantly routes to next provider.
- **HTTP 402 (Payment Required):** Specifically handles OpenRouter credit depletion without crashing client requests.
- **HTTP 5xx & Timeouts:** Applies exponential backoff with random jitter (0-200ms) for transient glitches, then transitions to fallback.
- **Invalid Structured Output:** If an AI model produces unparsable JSON, `InvalidStructuredOutputException` is caught and the request is retried on the secondary provider.
- **Context Length Errors:** Flagged and routed cleanly.

### Circuit Breaker States
- **`CLOSED`**: Standard operating state. Requests pass freely.
- **`OPEN`**: Tripped after consecutive failures (default: 3). Traffic is diverted away from the failing provider during cooldown (default: 60s).
- **`HALF-OPEN`**: After cooldown, trial requests are admitted. If successful (default: 2 consecutive), breaker resets to `CLOSED`. If trial fails, breaker trips back to `OPEN`.

---

## 4. Security & API Key Management

1. **Server-Side Exclusivity:**
   - API keys are loaded solely from backend environment variables (`GEMINI_API_KEY`, `OPENROUTER_API_KEY`).
   - Keys are **NEVER** transmitted to the Next.js frontend, bundled into browser JavaScript, or committed to Git.
   - Keys are filtered out of error responses and audit logs.
2. **Prompt Injection Protection (`PromptSanitizer`):**
   - Strips malicious control characters and neutralizes injection triggers (e.g. `ignore all instructions`, `system:`, `<|im_start|>`).
   - All document text is placed strictly inside isolated XML demarcation tags:
     ```xml
     <document_content slot="A" page="3" section="Indemnification">
     ...
     </document_content>
     ```
3. **Structured Schema Validation:**
   - JSON responses are parsed and validated against strict schemas before returning to services.

---

## 5. Admin Provider Management API

Super administrators can inspect and reconfigure AI providers in runtime without application redeployment:

### 1. List Providers & Health Status
- **Endpoint:** `GET /api/v1/admin/providers`
- **Auth:** Super Admin only (`Bearer <token>`)
- **Response:**
  ```json
  {
    "success": true,
    "data": [
      {
        "provider": "gemini",
        "display_name": "Google Gemini",
        "is_configured": true,
        "is_enabled": true,
        "circuit_breaker_state": "closed",
        "status": "healthy",
        "latency_ms": 320,
        "priority_rank": 1,
        "default_model": "gemini-2.0-flash",
        "max_context_tokens": 1000000,
        "supports_json": true,
        "supports_streaming": true
      },
      {
        "provider": "openrouter",
        "display_name": "OpenRouter",
        "is_configured": true,
        "is_enabled": true,
        "circuit_breaker_state": "closed",
        "status": "healthy",
        "latency_ms": 480,
        "priority_rank": 2,
        "default_model": "anthropic/claude-3.5-sonnet",
        "max_context_tokens": 200000,
        "supports_json": true,
        "supports_streaming": true
      }
    ]
  }
  ```

### 2. Update Provider Availability / Priority
- **Endpoint:** `PUT /api/v1/admin/providers/{provider}`
- **Payload:**
  ```json
  {
    "is_enabled": true,
    "reset_circuit_breaker": true,
    "priority": 1
  }
  ```

### 3. Test Connectivity Ping
- **Endpoint:** `POST /api/v1/admin/providers/{provider}/ping`
- **Action:** Executes an upstream probe call and returns real latency and circuit breaker status.

---

## 6. Environment Configuration (`.env`)

Add the following keys to your `.env`:

```env
# AI Providers Configuration
AI_PRIMARY_PROVIDER=gemini
AI_FALLBACK_PROVIDERS=openrouter

# Google Gemini API
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_DEFAULT_MODEL=gemini-2.0-flash
GEMINI_REASONING_MODEL=gemini-1.5-pro
GEMINI_TIMEOUT_SECONDS=60
GEMINI_ENABLED=true

# OpenRouter API
OPENROUTER_API_KEY=your_openrouter_api_key_here
OPENROUTER_DEFAULT_MODEL=anthropic/claude-3.5-sonnet
OPENROUTER_REASONING_MODEL=openai/gpt-4o
OPENROUTER_TIMEOUT_SECONDS=60
OPENROUTER_ENABLED=true

# Circuit Breaker & Quota Defaults
AI_CB_FAILURE_THRESHOLD=3
AI_CB_COOLDOWN_SECONDS=60
AI_USER_DAILY_TOKEN_LIMIT=500000
AI_WORKSPACE_MONTHLY_TOKEN_LIMIT=10000000
```

---

## 7. Testing & Quality Assurance

All automated tests use mocked provider adapters to ensure **zero API quota consumption** during test execution.

```bash
# Run all unit and feature tests
php artisan test

# Verify Next.js frontend builds cleanly
npm run build
```

### Verified Test Suite
- **PHPUnit Tests:** 50 tests passing, 195 assertions.
  - `CircuitBreakerTest` (4 tests)
  - `QuotaManagerTest` (3 tests)
  - `PromptSanitizerTest` (3 tests)
  - `AIProviderRouterTest` (6 tests: primary success, secondary failover, 429 rate limit, 401 auth failure, invalid JSON failover, all providers down)
  - `AdminProviderManagementTest` (3 tests: super admin list without key leakage, non-admin 403 block, status/priority update)
  - Document Pipeline & Auth tests (31 tests)
- **Frontend Build:** 15 static routes compiled cleanly with 0 TypeScript/ESLint errors.
