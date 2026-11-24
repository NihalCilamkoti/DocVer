"use client";

import * as React from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Progress } from "@/components/ui/progress";
import { BarChart3, Cpu, HardDrive, ShieldCheck, Loader2 } from "lucide-react";
import { comparisonService } from "@/services/api/comparison-service";
import { useAuth } from "@/components/auth-provider";
import { ComparisonJob } from "@/types/comparison";

export default function UsagePage() {
  const { user } = useAuth();
  const [jobs, setJobs] = React.useState<ComparisonJob[]>([]);
  const [loading, setLoading] = React.useState(true);

  React.useEffect(() => {
    let mounted = true;
    comparisonService
      .listComparisons()
      .then((data) => {
        if (mounted) {
          setJobs(data);
          setLoading(false);
        }
      })
      .catch(() => {
        if (mounted) setLoading(false);
      });
    return () => {
      mounted = false;
    };
  }, []);

  const comparisonsUsed = jobs.length;
  const comparisonsLimit = 250;
  const comparisonsPercent = Math.min(100, Math.round((comparisonsUsed / comparisonsLimit) * 100));

  const totalPages = jobs.reduce((acc, j) => acc + (j.docA.pages || 0) + (j.docB.pages || 0), 0);
  const pagesLimit = 10000;
  const pagesPercent = Math.min(100, Math.round((totalPages / pagesLimit) * 100));

  const workspaceName = user?.workspaces?.[0]?.name || "Default Workspace";
  const planName = user?.workspaces?.[0]?.plan ? `${user.workspaces[0].plan.toUpperCase()} Plan` : "Standard Plan";

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-text-main">
          Usage & Quotas
        </h1>
        <p className="text-sm text-text-secondary mt-0.5">
          Real-time volume and document processing activity for {workspaceName}
        </p>
      </div>

      {loading ? (
        <div className="p-12 text-center text-xs text-text-secondary flex items-center justify-center gap-2">
          <Loader2 className="h-4 w-4 animate-spin text-primary-600" />
          Loading usage...
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Card className="bg-white border-border shadow-subtle">
            <CardContent className="p-5">
              <div className="flex items-center justify-between mb-3">
                <span className="text-xs font-semibold text-text-secondary uppercase">
                  Comparisons Run
                </span>
                <div className="h-8 w-8 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center">
                  <BarChart3 className="h-4 w-4" />
                </div>
              </div>
              <div className="text-2xl font-bold text-text-main font-mono">
                {comparisonsUsed} <span className="text-xs font-normal text-text-secondary">/ {comparisonsLimit}</span>
              </div>
              <p className="text-xs text-text-secondary mt-1">{comparisonsPercent}% utilized</p>
              <Progress value={comparisonsPercent} className="h-1.5 mt-3" />
            </CardContent>
          </Card>

          <Card className="bg-white border-border shadow-subtle">
            <CardContent className="p-5">
              <div className="flex items-center justify-between mb-3">
                <span className="text-xs font-semibold text-text-secondary uppercase">
                  Pages Processed
                </span>
                <div className="h-8 w-8 rounded-lg bg-ai-light text-ai-600 flex items-center justify-center">
                  <Cpu className="h-4 w-4" />
                </div>
              </div>
              <div className="text-2xl font-bold text-text-main font-mono">
                {totalPages} <span className="text-xs font-normal text-text-secondary">/ {pagesLimit}</span>
              </div>
              <p className="text-xs text-text-secondary mt-1">{pagesPercent}% of document limit</p>
              <Progress value={pagesPercent} className="h-1.5 mt-3" />
            </CardContent>
          </Card>

          <Card className="bg-white border-border shadow-subtle">
            <CardContent className="p-5">
              <div className="flex items-center justify-between mb-3">
                <span className="text-xs font-semibold text-text-secondary uppercase">
                  Data Retention
                </span>
                <div className="h-8 w-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                  <ShieldCheck className="h-4 w-4" />
                </div>
              </div>
              <div className="text-2xl font-bold text-text-main font-mono">
                Zero
              </div>
              <p className="text-xs text-text-secondary mt-1">Raw files discarded after comparison</p>
              <div className="h-1.5 bg-emerald-100 rounded-full mt-3 overflow-hidden">
                <div className="h-full bg-emerald-600 rounded-full w-full" />
              </div>
            </CardContent>
          </Card>
        </div>
      )}

      <Card className="bg-white border-border shadow-subtle">
        <CardHeader className="p-6 pb-2">
          <CardTitle className="text-lg font-bold text-text-main">
            {planName}
          </CardTitle>
          <CardDescription className="text-xs text-text-secondary">
            Workspace: {workspaceName}
          </CardDescription>
        </CardHeader>
        <CardContent className="p-6 pt-4 space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div className="p-3.5 rounded-lg bg-slate-50 border border-border">
              <span className="font-semibold text-text-main block mb-1">
                Zero Model Training Retention
              </span>
              <p className="text-text-secondary">
                Customer document text and extracted clauses are never stored permanently or used for public foundation model training.
              </p>
            </div>
            <div className="p-3.5 rounded-lg bg-slate-50 border border-border">
              <span className="font-semibold text-text-main block mb-1">
                Multi-Provider AI Fallback
              </span>
              <p className="text-text-secondary">
                Automatic failover across Google Gemini, Groq, and OpenRouter to prevent rate-limit interruptions.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
