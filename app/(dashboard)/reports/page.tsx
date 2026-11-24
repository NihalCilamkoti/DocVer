"use client";

import * as React from "react";
import Link from "next/link";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { FileText, Download, Eye, Loader2 } from "lucide-react";
import { comparisonService } from "@/services/api/comparison-service";
import { ComparisonJob } from "@/types/comparison";
import { formatDate } from "@/lib/utils";

export default function ReportsPage() {
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

  const handleDownloadPdf = (jobId: string) => {
    const backendUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";
    window.open(`${backendUrl}/comparisons/${jobId}/report/pdf`, "_blank");
  };

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-text-main flex items-center gap-2">
          <FileText className="h-6 w-6 text-primary-600" />
          Generated Verification Reports
        </h1>
        <p className="text-sm text-text-secondary mt-0.5">
          Download PDF certification reports and verified evidence audits
        </p>
      </div>

      {loading ? (
        <div className="p-16 text-center text-xs text-text-secondary flex items-center justify-center gap-2">
          <Loader2 className="h-4 w-4 animate-spin text-primary-600" />
          Loading reports...
        </div>
      ) : jobs.length === 0 ? (
        <div className="p-16 text-center text-xs text-text-secondary bg-white rounded-xl border border-border shadow-subtle space-y-3">
          <p>No verified reports available. Run a document comparison to generate reports.</p>
          <Link href="/dashboard/compare">
            <Button size="sm" className="text-xs">Start Comparison Analysis</Button>
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {jobs.map((r) => (
            <Card key={r.id} className="bg-white border-border shadow-subtle flex flex-col justify-between">
              <CardHeader className="p-5 pb-3">
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <CardTitle className="text-base font-bold text-text-main">{r.title}</CardTitle>
                    <CardDescription className="text-xs text-text-secondary mt-1">
                      Completed: {formatDate(r.createdAt)}
                    </CardDescription>
                  </div>
                  <Badge variant="outline" className="text-[11px] font-mono">
                    {r.summary.overallSimilarityScore}% Match
                  </Badge>
                </div>
              </CardHeader>

              <CardContent className="p-5 pt-0 space-y-4">
                <div className="text-xs text-text-secondary bg-slate-50 p-2.5 rounded-lg border border-slate-100 flex justify-between">
                  <span>Verified Differences: <strong className="text-text-main">{r.summary.totalDifferences}</strong></span>
                  <span>High Risk: <strong className={r.summary.highRiskCount > 0 ? "text-error font-bold" : "text-emerald-600"}>{r.summary.highRiskCount}</strong></span>
                </div>

                <div className="flex items-center gap-2 pt-1">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => handleDownloadPdf(r.id)}
                    className="w-full gap-1.5 text-xs hover:border-primary-300 hover:text-primary-700"
                  >
                    <Download className="h-3.5 w-3.5" /> Download PDF
                  </Button>
                  <Link href={`/dashboard/compare?id=${r.id}`} className="w-full">
                    <Button variant="default" size="sm" className="w-full gap-1.5 text-xs">
                      <Eye className="h-3.5 w-3.5" /> View in App
                    </Button>
                  </Link>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
