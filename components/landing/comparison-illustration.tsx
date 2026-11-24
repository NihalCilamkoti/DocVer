"use client";

import * as React from "react";
import { motion } from "framer-motion";
import {
  FileText,
  AlertTriangle,
  CheckCircle2,
  Sparkles,
  ArrowRight,
  TrendingDown,
  Layers,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";

export function ComparisonIllustration() {
  return (
    <div className="relative w-full max-w-4xl mx-auto p-4 sm:p-6 lg:p-8">
      {/* Decorative ambient gradient */}
      <div className="absolute inset-0 bg-gradient-to-r from-primary-100/40 via-ai-light/30 to-primary-50/50 rounded-3xl blur-2xl -z-10" />

      {/* Main Container Card */}
      <div className="relative rounded-2xl border border-border bg-white/95 p-6 shadow-elevated backdrop-blur-sm overflow-hidden">
        {/* Top Control Bar Mock */}
        <div className="flex items-center justify-between pb-4 border-b border-border mb-6">
          <div className="flex items-center space-x-2">
            <span className="h-3 w-3 rounded-full bg-red-400" />
            <span className="h-3 w-3 rounded-full bg-amber-400" />
            <span className="h-3 w-3 rounded-full bg-green-400" />
            <span className="ml-3 text-xs font-mono font-medium text-text-secondary">
              DocVer AI Cross-Inspection Pipeline
            </span>
          </div>
          <div className="flex items-center space-x-2">
            <Badge variant="ai" className="gap-1 animate-pulse-slow">
              <Sparkles className="h-3 w-3 text-ai-600" />
              Active Verification
            </Badge>
            <Badge variant="outline" className="text-[11px] font-mono">
              Real-time Verification
            </Badge>
          </div>
        </div>

        {/* Dual Document Comparison Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6 relative">
          {/* Animated Connecting Line in Center */}
          <div className="hidden md:flex absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 z-20 items-center justify-center">
            <motion.div
              animate={{ rotate: [0, 360] }}
              transition={{ duration: 18, repeat: Infinity, ease: "linear" }}
              className="h-10 w-10 rounded-full bg-white border border-border shadow-card flex items-center justify-center text-primary-600"
            >
              <Sparkles className="h-5 w-5 text-ai-600" />
            </motion.div>
          </div>

          {/* Document A (Original / Benchmark) */}
          <motion.div
            initial={{ opacity: 0, x: -20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.6 }}
            className="rounded-xl border border-border bg-slate-50/60 p-4.5 space-y-4"
          >
            <div className="flex items-center justify-between border-b border-border/60 pb-3">
              <div className="flex items-center space-x-2">
                <div className="h-8 w-8 rounded-lg bg-blue-100 text-primary-600 flex items-center justify-center font-bold text-xs">
                  A
                </div>
                <div>
                  <h4 className="text-xs font-bold text-text-main flex items-center gap-1.5">
                    <FileText className="h-3.5 w-3.5 text-text-secondary" />
                    MSA_Executed_v2.1.pdf
                  </h4>
                  <p className="text-[11px] text-text-secondary">
                    Baseline Document • 42 Pages
                  </p>
                </div>
              </div>
              <Badge variant="secondary" className="text-[10px]">
                Original
              </Badge>
            </div>

            {/* Document A Clause Sample */}
            <div className="space-y-3 font-mono text-[11px] text-text-secondary">
              <div className="p-2.5 rounded-lg bg-white border border-border/80">
                <span className="text-[10px] uppercase font-bold text-text-muted tracking-wider block mb-1">
                  Section 14.2 — Liability Cap
                </span>
                <p className="text-text-main font-sans">
                  The total aggregate liability of either party shall be limited to{" "}
                  <span className="bg-blue-100 text-primary-700 px-1 py-0.5 rounded font-semibold">
                    twice the annual contract value ($2,000,000 USD)
                  </span>
                  .
                </p>
              </div>

              <div className="p-2.5 rounded-lg bg-white border border-border/80">
                <span className="text-[10px] uppercase font-bold text-text-muted tracking-wider block mb-1">
                  Section 5.3 — Invoicing Terms
                </span>
                <p className="text-text-main font-sans">
                  Invoices payable within{" "}
                  <span className="bg-slate-200 text-slate-800 px-1 py-0.5 rounded font-medium">
                    sixty (60) calendar days
                  </span>
                  . No late interest fees apply.
                </p>
              </div>

              <div className="p-2.5 rounded-lg bg-white border border-border/80">
                <span className="text-[10px] uppercase font-bold text-text-muted tracking-wider block mb-1">
                  Section 19 — Work For Hire
                </span>
                <p className="text-text-main font-sans">
                  All custom deliverables and source assets are owned solely by Client.
                </p>
              </div>
            </div>
          </motion.div>

          {/* Document B (Revised / Counter-party) */}
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 0.6 }}
            className="rounded-xl border border-border bg-slate-50/60 p-4.5 space-y-4"
          >
            <div className="flex items-center justify-between border-b border-border/60 pb-3">
              <div className="flex items-center space-x-2">
                <div className="h-8 w-8 rounded-lg bg-purple-100 text-ai-600 flex items-center justify-center font-bold text-xs">
                  B
                </div>
                <div>
                  <h4 className="text-xs font-bold text-text-main flex items-center gap-1.5">
                    <FileText className="h-3.5 w-3.5 text-text-secondary" />
                    MSA_Draft_v2.4_Proposed.pdf
                  </h4>
                  <p className="text-[11px] text-text-secondary">
                    Counter-Proposal • 45 Pages
                  </p>
                </div>
              </div>
              <Badge variant="ai" className="text-[10px]">
                Revision
              </Badge>
            </div>

            {/* Document B Clause Sample with Discrepancy highlights */}
            <div className="space-y-3 font-mono text-[11px] text-text-secondary">
              <div className="p-2.5 rounded-lg bg-white border border-error/40 relative overflow-hidden">
                <div className="absolute right-2 top-2">
                  <Badge variant="destructive" className="text-[9px] py-0 px-1.5">
                    -50% Reduction
                  </Badge>
                </div>
                <span className="text-[10px] uppercase font-bold text-error tracking-wider block mb-1">
                  Section 14.2 — Liability Cap Modified
                </span>
                <p className="text-text-main font-sans">
                  Liability shall not exceed{" "}
                  <span className="bg-red-100 text-error-700 px-1 py-0.5 rounded font-semibold line-through">
                    $2M cap
                  </span>{" "}
                  <span className="bg-red-50 text-error font-semibold px-1 py-0.5 rounded">
                    fees paid in preceding 12 mos ($1,000,000 USD)
                  </span>
                  .
                </p>
              </div>

              <div className="p-2.5 rounded-lg bg-white border border-amber-300">
                <div className="absolute right-2 top-2">
                  <Badge variant="warning" className="text-[9px] py-0 px-1.5">
                    30-Day Shift
                  </Badge>
                </div>
                <span className="text-[10px] uppercase font-bold text-amber-700 tracking-wider block mb-1">
                  Section 5.3 — Net 30 + 1.5% Late Fee
                </span>
                <p className="text-text-main font-sans">
                  Invoices payable within{" "}
                  <span className="bg-amber-100 text-amber-900 px-1 py-0.5 rounded font-semibold">
                    thirty (30) business days
                  </span>{" "}
                  subject to{" "}
                  <span className="bg-amber-100 text-amber-900 px-1 py-0.5 rounded font-semibold">
                    1.5% interest/month
                  </span>
                  .
                </p>
              </div>

              <div className="p-2.5 rounded-lg bg-red-50/50 border border-dashed border-red-300">
                <span className="text-[10px] uppercase font-bold text-error tracking-wider block mb-1">
                  Section 19 — Omitted Entirely
                </span>
                <p className="text-error font-sans text-xs italic">
                  [Clause omitted: Client loses exclusive IP assignment]
                </p>
              </div>
            </div>
          </motion.div>
        </div>

        {/* Bottom Floating AI Insight Banner */}
        <motion.div
          initial={{ opacity: 0, y: 15 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.3, duration: 0.5 }}
          className="mt-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 rounded-xl bg-gradient-to-r from-ai-light/70 to-blue-50/70 border border-ai-muted p-3.5"
        >
          <div className="flex items-center space-x-3">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-ai-600 text-white shadow-sm">
              <Sparkles className="h-4 w-4" />
            </div>
            <div>
              <p className="text-xs font-semibold text-text-main">
                AI Cross-Comparison Insight
              </p>
              <p className="text-[11px] text-text-secondary">
                Detected 1 critical IP omission, $1M liability drop, and 30-day payment contraction with evidence citations.
              </p>
            </div>
          </div>
          <div className="flex items-center space-x-2 self-end sm:self-center">
            <span className="inline-flex items-center gap-1 text-[11px] font-semibold text-success-700 bg-success-50 px-2.5 py-1 rounded-full border border-success-100">
              <CheckCircle2 className="h-3.5 w-3.5" />
              Verified Quotes
            </span>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
