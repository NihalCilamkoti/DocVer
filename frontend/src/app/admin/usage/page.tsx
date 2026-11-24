"use client";

import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Progress } from "@/components/ui/progress";
import { BarChart3, TrendingUp, DollarSign, Database } from "lucide-react";

export default function AdminUsagePage() {
  return (
    <div className="space-y-6 max-w-6xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-text-main flex items-center gap-2">
          <BarChart3 className="h-6 w-6 text-primary-600" />
          Enterprise Global Usage & Cost Analytics
        </h1>
        <p className="text-sm text-text-secondary mt-0.5">
          System-wide AI token consumption, API calls, and upstream billing tracking
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
        <Card className="bg-white border-border shadow-subtle p-5">
          <span className="text-xs font-semibold text-text-secondary uppercase">Monthly Tokens</span>
          <div className="text-2xl font-bold text-text-main mt-1">4.2M / 100M</div>
          <Progress value={4.2} className="h-1.5 mt-3" />
        </Card>

        <Card className="bg-white border-border shadow-subtle p-5">
          <span className="text-xs font-semibold text-text-secondary uppercase">Total Comparisons</span>
          <div className="text-2xl font-bold text-text-main mt-1">1,842</div>
          <span className="text-xs text-emerald-600 font-medium mt-2 block">+14% vs last week</span>
        </Card>

        <Card className="bg-white border-border shadow-subtle p-5">
          <span className="text-xs font-semibold text-text-secondary uppercase">Active Workspaces</span>
          <div className="text-2xl font-bold text-text-main mt-1">48</div>
          <span className="text-xs text-text-secondary mt-2 block">100% active</span>
        </Card>

        <Card className="bg-white border-border shadow-subtle p-5">
          <span className="text-xs font-semibold text-text-secondary uppercase">Estimated AI Cost</span>
          <div className="text-2xl font-bold text-emerald-600 mt-1">$42.80</div>
          <span className="text-xs text-text-secondary mt-2 block">Last 30 days</span>
        </Card>
      </div>
    </div>
  );
}
