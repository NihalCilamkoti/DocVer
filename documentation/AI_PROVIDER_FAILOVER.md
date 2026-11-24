# Automatic AI Quota Failover & Job Preservation

## Failover Sequence

The orchestrator follows the deterministic chain:
$$\text{Google Gemini} \longrightarrow \text{Groq} \longrightarrow \text{OpenRouter}$$

```mermaid
stateDiagram-v2
    [*] --> Primary_Gemini
    Primary_Gemini --> Groq_LPU: 429 Rate Limit / Quota Exhausted (<= 5% Margin) / Timeout
    Groq_LPU --> OpenRouter: 429 Rate Limit / Daily Quota Reached
    OpenRouter --> TemporarilyUnavailable: All Candidates Exhausted
    
    TemporarilyUnavailable --> ResumeOnReset: Resumes at 00:00 UTC from last_completed_stage
```

## Proactive 5% Safety Margin

`AIQuotaManager` monitors daily requests and daily tokens. When allowance $\le 5\%$, the provider is preemptively bypassed before a hard HTTP 429 failure occurs.

## Job Preservation Guarantee

If all AI providers fail:
1. Comparison job is marked `status: 'temporarily_unavailable'`.
2. Intermediate chunks (`completed_chunks`) and stage (`last_completed_stage`) remain safely stored in MySQL.
3. Upon daily quota reset or manual retry, the job resumes from the exact uncompleted stage without re-processing earlier chunks.
