"use client";

import * as React from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";

export function UsageChart() {
  return (
    <Card className="border-border shadow-subtle bg-white">
      <CardHeader className="p-5 pb-3">
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="text-base font-bold text-text-main">
              Monthly Usage Statistics
            </CardTitle>
            <p className="text-xs text-text-secondary">
              Billing cycle resets on December 1st
            </p>
          </div>
          <span className="text-xs font-semibold px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-100">
            Active Tier
          </span>
        </div>
      </CardHeader>

      <CardContent className="p-5 pt-1 space-y-5">
        {/* Metric 1 */}
        <div className="space-y-1.5">
          <div className="flex items-center justify-between text-xs">
            <span className="font-semibold text-text-main">Document Comparisons</span>
            <span className="text-text-secondary font-mono">148 / 250 (59%)</span>
          </div>
          <Progress value={59} className="h-2" />
        </div>

        {/* Metric 2 */}
        <div className="space-y-1.5">
          <div className="flex items-center justify-between text-xs">
            <span className="font-semibold text-text-main">Pages Processed & OCR</span>
            <span className="text-text-secondary font-mono">4,812 / 10,000 (48%)</span>
          </div>
          <Progress value={48} className="h-2" />
        </div>

        {/* Metric 3 */}
        <div className="space-y-1.5">
          <div className="flex items-center justify-between text-xs">
            <span className="font-semibold text-text-main">AI Chat Evidence Queries</span>
            <span className="text-text-secondary font-mono">1,120 / Unlimited</span>
          </div>
          <Progress value={25} className="h-2" />
        </div>

        {/* Storage footprint */}
        <div className="rounded-lg bg-slate-50 p-3 border border-border flex items-center justify-between text-xs">
          <div>
            <span className="font-semibold text-text-main block">Storage Encrypted Cache</span>
            <span className="text-[11px] text-text-secondary">
              Automated 30-day purge enabled
            </span>
          </div>
          <span className="font-mono font-bold text-text-main">2.4 GB / 50 GB</span>
        </div>
      </CardContent>
    </Card>
  );
}
