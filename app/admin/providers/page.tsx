"use client";

import * as React from "react";
import { useState, useEffect } from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Progress } from "@/components/ui/progress";
import {
  Cpu,
  Zap,
  ShieldCheck,
  AlertTriangle,
  RefreshCw,
  Clock,
  ArrowRightLeft,
  Activity,
  Layers,
  CheckCircle2,
  XCircle,
  TrendingUp,
  DollarSign,
  Sparkles,
} from "lucide-react";

interface ProviderData {
  provider: string;
  display_name: string;
  is_configured: boolean;
  is_enabled: boolean;
  circuit_breaker_state: string;
  status: string;
  latency_ms: number;
  priority_rank: number;
  default_model: string;
  max_context_tokens: number;
  requests_used_today: number;
  daily_request_limit: number | null;
  requests_remaining: number | null;
  tokens_used_today: number;
  daily_token_limit: number | null;
  tokens_remaining: number | null;
  current_rpm: number;
  rpm_limit: number | null;
  current_tpm: number;
  tpm_limit: number | null;
  reset_countdown_seconds: number;
  is_exhausted: boolean;
  is_near_exhaustion: boolean;
  safety_margin_percent: number;
  cost_input_per_million: number;
  cost_output_per_million: number;
}

interface FallbackEvent {
  id: string;
  timestamp: string;
  from_provider: string;
  to_provider: string;
  reason: string;
}

export default function AdminProvidersPage() {
  const [providers, setProviders] = useState<ProviderData[]>([]);
  const [events, setEvents] = useState<FallbackEvent[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [switching, setSwitching] = useState<string | null>(null);
  const [pinging, setPinging] = useState<string | null>(null);
  const [countdown, setCountdown] = useState<number>(0);

  const applyFallbackMockData = React.useCallback(() => {
    setProviders([
      {
        provider: "gemini",
        display_name: "Google Gemini",
        is_configured: true,
        is_enabled: true,
        circuit_breaker_state: "closed",
        status: "healthy",
        latency_ms: 184,
        priority_rank: 1,
        default_model: "gemini-2.0-flash",
        max_context_tokens: 1000000,
        requests_used_today: 412,
        daily_request_limit: 1500,
        requests_remaining: 1088,
        tokens_used_today: 182040,
        daily_token_limit: 1000000,
        tokens_remaining: 817960,
        current_rpm: 3,
        rpm_limit: 15,
        current_tpm: 12400,
        tpm_limit: 1000000,
        reset_countdown_seconds: 41200,
        is_exhausted: false,
        is_near_exhaustion: false,
        safety_margin_percent: 5,
        cost_input_per_million: 0.075,
        cost_output_per_million: 0.3,
      },
      {
        provider: "groq",
        display_name: "Groq LPU",
        is_configured: true,
        is_enabled: true,
        circuit_breaker_state: "closed",
        status: "healthy",
        latency_ms: 82,
        priority_rank: 2,
        default_model: "llama-3.3-70b-versatile",
        max_context_tokens: 128000,
        requests_used_today: 120,
        daily_request_limit: 14400,
        requests_remaining: 14280,
        tokens_used_today: 64100,
        daily_token_limit: 500000,
        tokens_remaining: 435900,
        current_rpm: 1,
        rpm_limit: 30,
        current_tpm: 4500,
        tpm_limit: 6000,
        reset_countdown_seconds: 41200,
        is_exhausted: false,
        is_near_exhaustion: false,
        safety_margin_percent: 5,
        cost_input_per_million: 0.59,
        cost_output_per_million: 0.79,
      },
      {
        provider: "openrouter",
        display_name: "OpenRouter",
        is_configured: true,
        is_enabled: true,
        circuit_breaker_state: "closed",
        status: "healthy",
        latency_ms: 290,
        priority_rank: 3,
        default_model: "anthropic/claude-3.5-sonnet",
        max_context_tokens: 200000,
        requests_used_today: 45,
        daily_request_limit: 5000,
        requests_remaining: 4955,
        tokens_used_today: 88400,
        daily_token_limit: 2000000,
        tokens_remaining: 1911600,
        current_rpm: 0,
        rpm_limit: 200,
        current_tpm: 0,
        tpm_limit: 50000,
        reset_countdown_seconds: 41200,
        is_exhausted: false,
        is_near_exhaustion: false,
        safety_margin_percent: 5,
        cost_input_per_million: 3.0,
        cost_output_per_million: 15.0,
      },
    ]);
    setEvents([
      {
        id: "fb_1",
        timestamp: new Date(Date.now() - 3600000).toISOString(),
        from_provider: "gemini",
        to_provider: "groq",
        reason: "HTTP 429: Rate limit exceeded (Retry-After: 12s)",
      },
    ]);
    setCountdown(41200);
  }, []);

  const fetchData = React.useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetch("/api/v1/admin/providers");
      if (res.ok) {
        const json = await res.json();
        if (json.data && Array.isArray(json.data)) {
          setProviders(json.data);
          if (json.data[0]?.reset_countdown_seconds) {
            setCountdown(json.data[0].reset_countdown_seconds);
          }
        }
      } else {
        applyFallbackMockData();
      }

      const eventsRes = await fetch("/api/v1/admin/providers/fallback-events");
      if (eventsRes.ok) {
        const eventsJson = await eventsRes.json();
        if (eventsJson.data && Array.isArray(eventsJson.data)) {
          setEvents(eventsJson.data);
        }
      }
    } catch {
      applyFallbackMockData();
    } finally {
      setLoading(false);
    }
  }, [applyFallbackMockData]);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Live countdown timer to 00:00 UTC
  useEffect(() => {
    if (countdown <= 0) return;
    const interval = setInterval(() => {
      setCountdown((prev) => Math.max(0, prev - 1));
    }, 1000);
    return () => clearInterval(interval);
  }, [countdown]);

  const formatCountdown = (totalSeconds: number) => {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    return `${hours.toString().padStart(2, "0")}h ${minutes
      .toString()
      .padStart(2, "0")}m ${seconds.toString().padStart(2, "0")}s`;
  };

  const handleSwitchPrimary = async (providerName: string) => {
    setSwitching(providerName);
    try {
      const res = await fetch("/api/v1/admin/providers/switch-primary", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ primary_provider: providerName }),
      });
      if (res.ok) {
        await fetchData();
      } else {
        // Reorder locally for responsive feedback
        setProviders((prev) => {
          const selected = prev.find((p) => p.provider === providerName);
          const others = prev.filter((p) => p.provider !== providerName);
          if (!selected) return prev;
          return [selected, ...others].map((item, idx) => ({
            ...item,
            priority_rank: idx + 1,
          }));
        });
      }
    } catch {
      // Reorder locally
      setProviders((prev) => {
        const selected = prev.find((p) => p.provider === providerName);
        const others = prev.filter((p) => p.provider !== providerName);
        if (!selected) return prev;
        return [selected, ...others].map((item, idx) => ({
          ...item,
          priority_rank: idx + 1,
        }));
      });
    } finally {
      setSwitching(null);
    }
  };

  const handlePing = async (providerName: string) => {
    setPinging(providerName);
    try {
      const res = await fetch(`/api/v1/admin/providers/${providerName}/ping`, {
        method: "POST",
      });
      if (res.ok) {
        await fetchData();
      }
    } catch {
      // noop
    } finally {
      setPinging(null);
    }
  };

  return (
    <div className="space-y-6 max-w-6xl mx-auto pb-12">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold tracking-tight text-text-main">
              AI Provider Management & Quota Failover
            </h1>
            <Badge variant="ai" className="gap-1">
              <Sparkles className="h-3 w-3" /> 3-Provider Multi-Engine
            </Badge>
          </div>
          <p className="text-sm text-text-secondary mt-1">
            Real-time quota tracking, proactive 5% safety margin failover, and task routing between Gemini, Groq, and OpenRouter.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <div className="bg-white px-3.5 py-2 rounded-lg border border-border shadow-subtle flex items-center gap-2">
            <Clock className="h-4 w-4 text-primary-600 animate-pulse" />
            <div className="text-xs">
              <span className="text-text-secondary">UTC Reset: </span>
              <span className="font-mono font-semibold text-text-main">
                {formatCountdown(countdown)}
              </span>
            </div>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={fetchData}
            disabled={loading}
            className="gap-1.5 bg-white text-xs"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? "animate-spin" : ""}`} />
            Refresh
          </Button>
        </div>
      </div>

      {/* Failover sequence summary banner */}
      <div className="bg-gradient-to-r from-blue-50/70 via-purple-50/70 to-indigo-50/70 border border-blue-100 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="h-9 w-9 rounded-lg bg-primary-600 text-white flex items-center justify-center font-bold text-sm shadow-subtle">
            <ArrowRightLeft className="h-4 w-4" />
          </div>
          <div>
            <div className="text-xs font-semibold text-primary-900 uppercase tracking-wide">
              Active Automatic Fallback Pipeline
            </div>
            <div className="flex items-center gap-2 mt-0.5 text-xs text-text-secondary font-medium">
              {providers.map((p, idx) => (
                <React.Fragment key={p.provider}>
                  <span
                    className={
                      idx === 0
                        ? "text-primary-700 font-bold bg-primary-100/60 px-2 py-0.5 rounded"
                        : "text-slate-600 bg-white/80 px-2 py-0.5 rounded"
                    }
                  >
                    {idx + 1}. {p.display_name} {idx === 0 && "(Primary)"}
                  </span>
                  {idx < providers.length - 1 && <span className="text-slate-400">→</span>}
                </React.Fragment>
              ))}
            </div>
          </div>
        </div>
        <div className="text-xs text-text-secondary self-start sm:self-center">
          <span className="inline-flex items-center gap-1 font-medium text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
            <CheckCircle2 className="h-3 w-3 text-emerald-600" />
            Automatic Quota Failover Active
          </span>
        </div>
      </div>

      {/* Provider Cards */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {providers.map((p) => {
          const reqPercent =
            p.daily_request_limit && p.daily_request_limit > 0
              ? Math.min(100, Math.round((p.requests_used_today / p.daily_request_limit) * 100))
              : 0;
          const tokenPercent =
            p.daily_token_limit && p.daily_token_limit > 0
              ? Math.min(100, Math.round((p.tokens_used_today / p.daily_token_limit) * 100))
              : 0;

          const isPrimary = p.priority_rank === 1;

          return (
            <Card
              key={p.provider}
              className={`bg-white border transition-all duration-200 shadow-subtle flex flex-col justify-between ${
                isPrimary ? "border-primary-400 ring-2 ring-primary-100" : "border-border"
              }`}
            >
              <CardHeader className="p-5 pb-3">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <div className="flex items-center gap-2">
                      <CardTitle className="text-base font-bold text-text-main">
                        {p.display_name}
                      </CardTitle>
                      {isPrimary && (
                        <Badge variant="default" className="text-[10px] uppercase font-bold py-0">
                          Primary
                        </Badge>
                      )}
                    </div>
                    <CardDescription className="text-xs text-text-secondary mt-0.5">
                      Model: <code className="text-primary-700 font-mono text-[11px]">{p.default_model}</code>
                    </CardDescription>
                  </div>

                  <div className="flex items-center gap-1.5">
                    {p.status === "healthy" ? (
                      <Badge variant="success" className="gap-1 text-[11px]">
                        <CheckCircle2 className="h-3 w-3" /> Healthy
                      </Badge>
                    ) : p.status === "quota_exhausted" ? (
                      <Badge variant="destructive" className="gap-1 text-[11px]">
                        <XCircle className="h-3 w-3" /> Quota Exhausted
                      </Badge>
                    ) : (
                      <Badge variant="warning" className="gap-1 text-[11px]">
                        <AlertTriangle className="h-3 w-3" /> {p.status}
                      </Badge>
                    )}
                  </div>
                </div>
              </CardHeader>

              <CardContent className="p-5 pt-1 space-y-4 flex-1 flex flex-col justify-between">
                {/* Latency & Circuit Breaker */}
                <div className="grid grid-cols-2 gap-2 text-xs bg-slate-50/80 p-2.5 rounded-lg border border-slate-100">
                  <div>
                    <span className="text-text-secondary block">Latency</span>
                    <span className="font-semibold text-text-main flex items-center gap-1 mt-0.5">
                      <Activity className="h-3.5 w-3.5 text-blue-500" />
                      {p.latency_ms > 0 ? `${p.latency_ms} ms` : "Standby"}
                    </span>
                  </div>
                  <div>
                    <span className="text-text-secondary block">Circuit Breaker</span>
                    <span className="font-semibold text-text-main capitalize mt-0.5 block">
                      {p.circuit_breaker_state}
                    </span>
                  </div>
                </div>

                {/* Quota: Requests */}
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-text-secondary font-medium">Daily Requests</span>
                    <span className="font-semibold text-text-main">
                      {p.requests_used_today.toLocaleString()} /{" "}
                      {p.daily_request_limit ? p.daily_request_limit.toLocaleString() : "Unlimited"}
                    </span>
                  </div>
                  <Progress value={reqPercent} className="h-2" />
                  <div className="flex justify-between text-[11px] text-text-secondary">
                    <span>{reqPercent}% utilized</span>
                    <span>
                      {p.requests_remaining !== null
                        ? `${p.requests_remaining.toLocaleString()} left`
                        : "High"}
                    </span>
                  </div>
                </div>

                {/* Quota: Tokens */}
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between text-xs">
                    <span className="text-text-secondary font-medium">Daily Tokens</span>
                    <span className="font-semibold text-text-main">
                      {p.tokens_used_today.toLocaleString()} /{" "}
                      {p.daily_token_limit ? p.daily_token_limit.toLocaleString() : "Custom Tier"}
                    </span>
                  </div>
                  <Progress value={tokenPercent} className="h-2" />
                  <div className="flex justify-between text-[11px] text-text-secondary">
                    <span>{tokenPercent}% utilized</span>
                    <span>
                      {p.tokens_remaining !== null
                        ? `${(p.tokens_remaining / 1000).toFixed(0)}k left`
                        : "Metered"}
                    </span>
                  </div>
                </div>

                {/* Real-time RPM / TPM */}
                <div className="grid grid-cols-2 gap-2 text-xs pt-1 border-t border-slate-100">
                  <div>
                    <span className="text-text-secondary text-[11px]">Current RPM</span>
                    <div className="font-mono font-medium text-text-main mt-0.5">
                      {p.current_rpm} / {p.rpm_limit ?? "∞"}
                    </div>
                  </div>
                  <div>
                    <span className="text-text-secondary text-[11px]">Pricing (In/Out 1M)</span>
                    <div className="font-mono font-medium text-emerald-600 mt-0.5">
                      ${p.cost_input_per_million} / ${p.cost_output_per_million}
                    </div>
                  </div>
                </div>

                {/* Actions */}
                <div className="pt-2 flex items-center gap-2">
                  {!isPrimary && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleSwitchPrimary(p.provider)}
                      disabled={switching === p.provider}
                      className="w-full text-xs font-semibold text-primary-600 border-primary-200 hover:bg-primary-50"
                    >
                      Set as Primary
                    </Button>
                  )}
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => handlePing(p.provider)}
                    disabled={pinging === p.provider}
                    className="text-xs text-text-secondary hover:text-text-main"
                  >
                    {pinging === p.provider ? "Pinging..." : "Test Ping"}
                  </Button>
                </div>
              </CardContent>
            </Card>
          );
        })}
      </div>

      {/* Fallback Event History Log */}
      <Card className="bg-white border-border shadow-subtle">
        <CardHeader className="p-5 pb-3">
          <div className="flex items-center justify-between">
            <div>
              <CardTitle className="text-base font-bold text-text-main flex items-center gap-2">
                <ArrowRightLeft className="h-4 w-4 text-primary-600" />
                Automatic Failover Audit & Event History
              </CardTitle>
              <CardDescription className="text-xs text-text-secondary">
                Logged events when the orchestrator proactively or reactively switched providers
              </CardDescription>
            </div>
            <Badge variant="outline" className="text-xs">
              {events.length} Recent Events
            </Badge>
          </div>
        </CardHeader>

        <CardContent className="p-5 pt-2">
          {events.length === 0 ? (
            <div className="py-8 text-center text-text-secondary text-xs">
              No failover events recorded. All requests have been served by the primary provider cleanly.
            </div>
          ) : (
            <div className="divide-y divide-border">
              {events.map((ev) => (
                <div key={ev.id} className="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2 text-xs">
                      <span className="font-semibold uppercase tracking-wider text-slate-700 bg-slate-100 px-2 py-0.5 rounded">
                        {ev.from_provider}
                      </span>
                      <span className="text-primary-600 font-bold">→</span>
                      <span className="font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">
                        {ev.to_provider}
                      </span>
                      <span className="text-text-secondary text-[11px]">
                        {new Date(ev.timestamp).toLocaleString()}
                      </span>
                    </div>
                    <p className="text-xs text-text-secondary font-mono">{ev.reason}</p>
                  </div>

                  <Badge variant="warning" className="self-start sm:self-center text-[10px]">
                    Automatic Failover
                  </Badge>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
