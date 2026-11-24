"use client";

import * as React from "react";
import Link from "next/link";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { History, CheckCircle2, AlertTriangle, ArrowRight, Loader2 } from "lucide-react";
import { comparisonService } from "@/services/api/comparison-service";
import { ComparisonJob } from "@/types/comparison";
import { formatDate } from "@/lib/utils";

export default function HistoryPage() {
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

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-text-main flex items-center gap-2">
            <History className="h-6 w-6 text-primary-600" />
            Comparison Audit History
          </h1>
          <p className="text-sm text-text-secondary mt-0.5">
            Audit trail of verified document comparisons and executive summaries
          </p>
        </div>
        <Link href="/dashboard/compare">
          <Button size="sm" className="gap-1.5 text-xs">
            New Comparison
          </Button>
        </Link>
      </div>

      <Card className="bg-white border-border shadow-subtle">
        <CardContent className="p-0 divide-y divide-border">
          {loading ? (
            <div className="p-12 text-center text-xs text-text-secondary flex items-center justify-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin text-primary-600" />
              Loading history...
            </div>
          ) : jobs.length === 0 ? (
            <div className="p-12 text-center text-xs text-text-secondary space-y-3">
              <p>No document comparison audits found in your history.</p>
              <Link href="/dashboard/compare">
                <Button size="sm" className="text-xs">Start Your First Comparison</Button>
              </Link>
            </div>
          ) : (
            jobs.map((c) => (
              <div key={c.id} className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:bg-slate-50/50 transition-colors">
                <div className="space-y-1">
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-sm text-text-main">{c.title}</span>
                    <Badge variant={c.summary.overallSimilarityScore >= 80 ? "success" : "destructive"} className="gap-1 text-[10px] py-0">
                      {c.summary.overallSimilarityScore >= 80 ? (
                        <CheckCircle2 className="h-3 w-3" />
                      ) : (
                        <AlertTriangle className="h-3 w-3" />
                      )}
                      {c.summary.overallSimilarityScore}% Match
                    </Badge>
                  </div>
                  <div className="flex items-center gap-3 text-xs text-text-secondary">
                    <span>ID: <code className="font-mono text-[11px]">{c.id}</code></span>
                    <span>•</span>
                    <span>{formatDate(c.createdAt)}</span>
                    <span>•</span>
                    <span className="text-amber-600 font-medium">{c.summary.totalDifferences} differences flagged</span>
                  </div>
                </div>

                <Link href={`/dashboard/compare?id=${c.id}`}>
                  <Button variant="ghost" size="sm" className="gap-1 text-xs text-primary-600 self-start sm:self-center">
                    View Report <ArrowRight className="h-3.5 w-3.5" />
                  </Button>
                </Link>
              </div>
            ))
          )}
        </CardContent>
      </Card>
    </div>
  );
}
