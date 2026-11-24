# DocVer AI Infrastructure — Three-Provider Architecture & Automatic Quota Failover

DocVer uses an enterprise-grade multi-engine AI orchestration layer supporting three primary providers:

1. **Google Gemini** (Primary / Fast & Reasoning)
2. **Groq** (Ultra-Low Latency LPU Inference & Fast Extraction)
3. **OpenRouter** (Unified Fallback & Claude/GPT Models)

---

## 1. How to Obtain API Keys

### Google Gemini API
1. Navigate to [Google AI Studio](https://aistudio.google.com/).
2. Sign in with your Google account.
3. Click **Get API key** in the left navigation sidebar.
4. Create a new key or associate with an existing Google Cloud project.
5. Copy the generated key. (Free tier includes up to 15 RPM / 1,500 RPD).

### Groq API
1. Visit the [Groq Console](https://console.groq.com/).
2. Create an account or sign in with GitHub/Google.
3. Navigate to **API Keys** (`https://console.groq.com/keys`).
4. Click **Create API Key**, label it `DocVer-Production`, and copy the key.
5. Groq offers fast inference using LPU chips (30 RPM / 14,400 RPD on `llama-3.3-70b-versatile` and `llama-3.1-8b-instant`).

### OpenRouter API
1. Go to [OpenRouter](https://openrouter.ai/).
2. Sign in and navigate to **Keys** (`https://openrouter.ai/keys`).
3. Click **Create Key**, set a name (`DocVer-Fallback`), and optionally define a credit spending limit.
4. Top up credits under **Credits** (minimum \$5-\$10) to access Claude 3.5 Sonnet, GPT-4o, and other foundation models.

---

## 2. Environment Configuration

Add the following environment variables to your `.env` file (see `.env.example`):

```dotenv
# AI Provider Orchestration
AI_PRIMARY_PROVIDER=gemini
AI_FALLBACK_ORDER=gemini,groq,openrouter
AI_MAX_RETRIES=2
AI_ENABLE_AUTOMATIC_FAILOVER=true
AI_DAILY_QUOTA_SAFETY_MARGIN=5

# Google Gemini API
GEMINI_API_KEY=your_gemini_api_key_here
GEMINI_BASE_URL=https://generativelanguage.googleapis.com/v1beta
GEMINI_MODEL=gemini-2.0-flash
GEMINI_REASONING_MODEL=gemini-1.5-pro
GEMINI_TIMEOUT_SECONDS=60
GEMINI_ENABLED=true

# Groq API
GROQ_API_KEY=your_groq_api_key_here
GROQ_BASE_URL=https://api.groq.com/openai/v1
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_FAST_MODEL=llama-3.1-8b-instant
GROQ_REASONING_MODEL=llama-3.3-70b-versatile
GROQ_TIMEOUT_SECONDS=45
GROQ_ENABLED=true

# OpenRouter API
OPENROUTER_API_KEY=your_openrouter_api_key_here
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_MODEL=anthropic/claude-3.5-sonnet
OPENROUTER_REASONING_MODEL=openai/gpt-4o
OPENROUTER_TIMEOUT_SECONDS=60
OPENROUTER_ENABLED=true

# Circuit Breaker & Safety Margins
AI_CB_FAILURE_THRESHOLD=3
AI_CB_COOLDOWN_SECONDS=60
```

> **Security Rule:** Never expose API keys to Next.js or browser client assets. All AI calls flow through the authenticated Laravel backend service layer.

---

## 3. Provider Limits & Pricing Matrix

| Provider | Default Model | Typical RPM | Typical RPD | Input Cost / 1M | Output Cost / 1M | Best Task Category |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Google Gemini** | `gemini-2.0-flash` | 15 RPM | 1,500 RPD | \$0.075 | \$0.30 | General, Document QA, Chat |
| **Groq LPU** | `llama-3.3-70b-versatile` | 30 RPM | 14,400 RPD | \$0.59 | \$0.79 | Extraction, Fast Verification |
| **OpenRouter** | `anthropic/claude-3.5-sonnet`| 200 RPM | Metered | \$3.00 | \$15.00 | Deep Contradiction, Complex Legal |

---

## 4. Automatic Failover Behavior

The orchestration engine routes requests through a deterministic priority pipeline:
`Gemini → Groq → OpenRouter`

### Failover Triggers:
1. **HTTP 429 (Rate Limit):** Provider rate limit exceeded. Orchestrator reads `Retry-After` header, increments circuit breaker, and immediately fails over to the next eligible candidate.
2. **Proactive Safety Margin (5%):** If remaining daily requests or tokens fall $\le 5\%$ of the limit, the system proactively switches to the next provider *before* hard upstream rejection.
3. **RPM / TPM Sliding Windows:** Real-time Redis minute buckets track active RPM and TPM. Requests exceeding threshold failover instantly.
4. **Timeouts & Server Errors (5xx):** Transient failures trigger exponential backoff with jitter (max 2 retries). If upstream fails, failover executes.
5. **Task Capability:** If a model lacks structured JSON output or task context length, it routes to a capable provider.

### Error Classification:
- **Rate Limits (429):** Failover triggered cleanly.
- **Authentication (401/403):** Logged with critical error classification to avoid confusing bad credentials with exhaustion.
- **Payment / Billing (402):** Logged clearly as balance exhaustion.
- **Context Length (400):** Logged with token count advisory.

### Preservation of Long-Running Comparison Jobs:
If **all** providers become temporarily exhausted:
- The comparison job is **never dropped or deleted**.
- Status updates to `temporarily_unavailable`.
- Intermediate state (`completed_chunks` and `last_completed_stage`) is stored in MySQL.
- Upon quota reset (00:00 UTC) or admin retry, the job resumes from the last uncompleted stage rather than restarting from page 1.
- Deterministic idempotency keys prevent duplicate findings.

---

## 5. Admin Provider Dashboard

DocVer provides an enterprise admin dashboard at:
`/dashboard/admin/providers`

**Capabilities:**
- Real-time status badges (`Healthy`, `Degraded`, `Quota Exhausted`, `Unconfigured`).
- Daily quota progress meters for requests and tokens.
- Live RPM & TPM tracking.
- Countdown timer to 00:00 UTC daily quota reset.
- Live Failover Audit Event log.
- One-click manual primary switch (`Set as Primary`) and ping tests.

---

## 6. How to Add a New Provider in the Future

Adding a 4th provider (e.g. `Anthropic`, `Mistral`, `Ollama`) is straightforward:

1. **Create Provider Adapter:**
   Create `app/Services/AI/Providers/NewProvider.php` extending `BaseAIProvider`:
   ```php
   class NewProvider extends BaseAIProvider
   {
       public function getName(): string { return 'newprovider'; }
       public function isConfigured(): bool { return !empty($this->apiKey); }
       public function generate(AIRequest $request): AIResponse { ... }
       public function stream(AIRequest $request): Generator { ... }
       public function ping(): bool { ... }
   }
   ```
2. **Register in `config/ai.php`:**
   Add provider credentials, limits, costs, and task models under `config/ai.php`.
3. **Register in `AIProviderManager.php`:**
   Inject the new provider in `AIProviderManager::__construct()` and call `$this->registerProvider($newProvider)`.
4. **Append to `AI_FALLBACK_ORDER`:**
   Update `.env`:
   ```dotenv
   AI_FALLBACK_ORDER=gemini,groq,openrouter,newprovider
   ```
The circuit breaker, health monitor, quota tracker, and admin dashboard automatically pick up the new provider!
