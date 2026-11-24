"use client";

import * as React from "react";
import Link from "next/link";
import {
  FileText,
  Search,
  Filter,
  FilePlus2,
  AlertTriangle,
  CheckCircle2,
  ExternalLink,
  Loader2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { formatDate } from "@/lib/utils";
import { comparisonService } from "@/services/api/comparison-service";
import { ComparisonJob } from "@/types/comparison";

export default function ComparisonsPage() {
  const [search, setSearch] = React.useState("");
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

  const filtered = jobs.filter(
    (item) =>
      item.title.toLowerCase().includes(search.toLowerCase()) ||
      item.docA.name.toLowerCase().includes(search.toLowerCase()) ||
      item.docB.name.toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-text-main">
            Recent Comparisons
          </h1>
          <p className="text-sm text-text-secondary">
            Audit history, verification summaries, and generated redlines
          </p>
        </div>
        <Link href="/dashboard/compare">
          <Button className="gap-2 shadow-sm text-xs font-semibold">
            <FilePlus2 className="h-4 w-4" />
            New Comparison
          </Button>
        </Link>
      </div>

      {/* Filter / Search Bar */}
      <div className="flex flex-col sm:flex-row items-center gap-3">
        <div className="relative w-full sm:max-w-md">
          <Search className="absolute left-3 top-2.5 h-4 w-4 text-text-muted" />
          <Input
            placeholder="Search by contract name or document title..."
            className="pl-9"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
      </div>

      {/* Job list table */}
      <div className="rounded-xl border border-border bg-white shadow-subtle overflow-hidden">
        <div className="overflow-x-auto">
          {loading ? (
            <div className="p-12 text-center text-xs text-text-secondary flex items-center justify-center gap-2">
              <Loader2 className="h-4 w-4 animate-spin text-primary-600" />
              Loading comparisons...
            </div>
          ) : filtered.length === 0 ? (
            <div className="p-12 text-center text-xs text-text-secondary space-y-3">
              <p>No document comparison audits found.</p>
              <Link href="/dashboard/compare">
                <Button size="sm" className="text-xs">Launch Your First Comparison</Button>
              </Link>
            </div>
          ) : (
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 border-b border-border text-text-secondary font-medium uppercase tracking-wider">
                <tr>
                  <th className="px-5 py-3">Audit Title</th>
                  <th className="px-5 py-3">Document Pair</th>
                  <th className="px-5 py-3">Discrepancy Severity</th>
                  <th className="px-5 py-3">Similarity Score</th>
                  <th className="px-5 py-3">Completed Date</th>
                  <th className="px-5 py-3 text-right">Report</th>
                </tr>
              </thead>
              <tbody className="divide-y border-border">
                {filtered.map((job) => (
                  <tr key={job.id} className="hover:bg-slate-50/70 transition-colors">
                    <td className="px-5 py-4 font-semibold text-text-main">
                      <div className="flex items-center space-x-2">
                        <FileText className="h-4 w-4 text-primary-600 shrink-0" />
                        <span>{job.title}</span>
                      </div>
                    </td>
                    <td className="px-5 py-4 text-text-secondary">
                      <div className="space-y-0.5 text-[11px]">
                        <div>Doc A: {job.docA.name}</div>
                        <div>Doc B: {job.docB.name}</div>
                      </div>
                    </td>
                    <td className="px-5 py-4">
                      {job.summary.highRiskCount > 0 ? (
                        <Badge variant="destructive" className="gap-1 font-mono text-[11px]">
                          <AlertTriangle className="h-3 w-3" />
                          {job.summary.highRiskCount} High Risks
                        </Badge>
                      ) : (
                        <Badge variant="success" className="gap-1 font-mono text-[11px]">
                          <CheckCircle2 className="h-3 w-3" />
                          Low Risk
                        </Badge>
                      )}
                    </td>
                    <td className="px-5 py-4 font-mono font-medium text-text-main">
                      <div className="flex items-center space-x-2">
                        <span>{job.summary.overallSimilarityScore}%</span>
                        <div className="h-1.5 w-12 bg-slate-100 rounded-full overflow-hidden">
                          <div
                            className="h-full bg-primary-600 rounded-full"
                            style={{ width: `${job.summary.overallSimilarityScore}%` }}
                          />
                        </div>
                      </div>
                    </td>
                    <td className="px-5 py-4 text-text-secondary whitespace-nowrap">
                      {formatDate(job.createdAt)}
                    </td>
                    <td className="px-5 py-4 text-right">
                      <Link href={`/dashboard/compare?id=${job.id}`}>
                        <Button
                          variant="outline"
                          size="sm"
                          className="text-xs h-7 px-2.5 hover:border-primary-300"
                        >
                          <span>View Report</span>
                          <ExternalLink className="ml-1.5 h-3 w-3" />
                        </Button>
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
}
