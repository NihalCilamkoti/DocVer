"use client";

import * as React from "react";
import Link from "next/link";
import {
  FileText,
  AlertTriangle,
  CheckCircle2,
  ExternalLink,
  ArrowRight,
} from "lucide-react";
import { ComparisonJob } from "@/types/comparison";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { formatDate } from "@/lib/utils";

interface RecentJobsProps {
  jobs: ComparisonJob[];
}

export function RecentJobs({ jobs }: RecentJobsProps) {
  return (
    <div className="rounded-xl border border-border bg-white shadow-subtle overflow-hidden">
      <div className="p-5 border-b border-border flex items-center justify-between">
        <div>
          <h3 className="text-base font-bold text-text-main">
            Recent Comparison Jobs
          </h3>
          <p className="text-xs text-text-secondary">
            Latest cross-document verification audits run in your organization
          </p>
        </div>
        <Link href="/dashboard/comparisons">
          <Button variant="ghost" size="sm" className="text-xs text-primary-600">
            View all
            <ArrowRight className="ml-1.5 h-3.5 w-3.5" />
          </Button>
        </Link>
      </div>

      <div className="overflow-x-auto">
        {jobs.length === 0 ? (
          <div className="p-8 text-center text-xs text-text-secondary space-y-3">
            <p>No document comparison audits found in this workspace yet.</p>
            <Link href="/dashboard/compare">
              <Button size="sm" className="text-xs">Launch Your First Comparison</Button>
            </Link>
          </div>
        ) : (
          <table className="w-full text-left text-xs">
            <thead className="bg-slate-50 border-b border-border text-text-secondary font-medium uppercase tracking-wider">
              <tr>
                <th className="px-5 py-3">Comparison Title</th>
                <th className="px-5 py-3">Documents Compared</th>
                <th className="px-5 py-3">Risk Assessment</th>
                <th className="px-5 py-3">Similarity</th>
                <th className="px-5 py-3">Date</th>
                <th className="px-5 py-3 text-right">Action</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {jobs.map((job) => (
                <tr
                  key={job.id}
                  className="hover:bg-slate-50/70 transition-colors group"
                >
                  <td className="px-5 py-4 font-semibold text-text-main max-w-xs">
                    <div className="flex items-center space-x-2">
                      <FileText className="h-4 w-4 text-primary-600 shrink-0" />
                      <span className="truncate">{job.title}</span>
                    </div>
                  </td>
                  <td className="px-5 py-4 text-text-secondary">
                    <div className="flex flex-col space-y-0.5 text-[11px]">
                      <span className="truncate max-w-[200px]">A: {job.docA.name}</span>
                      <span className="truncate max-w-[200px]">B: {job.docB.name}</span>
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
  );
}
