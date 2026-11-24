"use client";

import * as React from "react";
import Link from "next/link";
import {
  FileDiff,
  FileText,
  AlertTriangle,
  ArrowRight,
  FilePlus2,
  CheckCircle2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { RecentJobs } from "@/components/dashboard/recent-jobs";
import { comparisonService } from "@/services/api/comparison-service";
import { ComparisonJob } from "@/types/comparison";

export default function DashboardOverviewPage() {
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
        if (mounted) {
          setLoading(false);
        }
      });
    return () => {
      mounted = false;
    };
  }, []);

  const totalComparisons = jobs.length;
  const highRisks = jobs.reduce((sum, j) => sum + (j.summary.highRiskCount || 0), 0);
  const totalDifferences = jobs.reduce((sum, j) => sum + (j.summary.totalDifferences || 0), 0);

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      {/* Simple Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-2">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-text-main">
            Document Verification
          </h1>
          <p className="text-sm text-text-secondary mt-1">
            Compare two documents to reveal altered clauses, changed figures, and missing provisions.
          </p>
        </div>

        <Link href="/dashboard/compare">
          <Button size="lg" className="gap-2 shadow-sm text-xs font-semibold">
            <FilePlus2 className="h-4 w-4" />
            New Comparison
          </Button>
        </Link>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="rounded-xl border border-border bg-white p-5 shadow-subtle flex items-center justify-between">
          <div>
            <span className="text-xs font-semibold uppercase tracking-wider text-text-secondary">
              Total Comparisons
            </span>
            <div className="mt-1 text-2xl font-bold text-text-main font-mono">
              {totalComparisons}
            </div>
          </div>
          <div className="h-10 w-10 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center">
            <FileDiff className="h-5 w-5" />
          </div>
        </div>

        <div className="rounded-xl border border-border bg-white p-5 shadow-subtle flex items-center justify-between">
          <div>
            <span className="text-xs font-semibold uppercase tracking-wider text-text-secondary">
              Total Differences Found
            </span>
            <div className="mt-1 text-2xl font-bold text-text-main font-mono">
              {totalDifferences}
            </div>
          </div>
          <div className="h-10 w-10 rounded-xl bg-ai-light text-ai-600 flex items-center justify-center">
            <FileText className="h-5 w-5" />
          </div>
        </div>

        <div className="rounded-xl border border-border bg-white p-5 shadow-subtle flex items-center justify-between">
          <div>
            <span className="text-xs font-semibold uppercase tracking-wider text-text-secondary">
              High-Risk Discrepancies
            </span>
            <div className="mt-1 text-2xl font-bold font-mono text-text-main">
              {highRisks}
            </div>
          </div>
          <div className={`h-10 w-10 rounded-xl flex items-center justify-center ${highRisks > 0 ? "bg-red-50 text-error" : "bg-emerald-50 text-emerald-600"}`}>
            {highRisks > 0 ? (
              <AlertTriangle className="h-5 w-5" />
            ) : (
              <CheckCircle2 className="h-5 w-5" />
            )}
          </div>
        </div>
      </div>

      {/* Recent Comparisons Table */}
      <div className="space-y-4">
        <RecentJobs jobs={jobs} />
      </div>
    </div>
  );
}
