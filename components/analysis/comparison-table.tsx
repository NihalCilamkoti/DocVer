"use client";

import * as React from "react";
import {
  FileText,
  ChevronDown,
  ChevronRight,
  Download,
  CheckCircle2,
  AlertTriangle,
  Eye,
  Scale,
  Hash,
} from "lucide-react";
import { ComparisonJob, ClauseComparisonItem } from "@/types/comparison";
import { RiskBadge } from "@/components/reports/risk-badge";
import { FindingCard } from "./finding-card";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

interface ComparisonTableProps {
  job: ComparisonJob;
  onNavigateToPage?: (slot: "A" | "B", page: number, quote?: string) => void;
}

export function ComparisonTable({
  job,
  onNavigateToPage,
}: ComparisonTableProps) {
  const [activeFilter, setActiveFilter] = React.useState<
    "changed" | "matching" | "numerical" | "contradictions" | "all"
  >("changed");
  const [expandedRowId, setExpandedRowId] = React.useState<string | null>(null);

  const { summary, clauses, numericalDiscrepancies, contradictions } = job;

  const changedClauses = clauses.filter((c) => c.type !== "matching");
  const matchingClauses = clauses.filter((c) => c.type === "matching");

  const handleDownloadPdf = () => {
    const backendUrl =
      process.env.NEXT_PUBLIC_API_URL || "http://127.0.0.1:8000/api/v1";
    window.open(`${backendUrl}/comparisons/${job.id}/report/pdf`, "_blank");
  };

  const renderStatusPill = (type: ClauseComparisonItem["type"], hasEvidence: boolean) => {
    if (!hasEvidence && type !== "matching") {
      return (
        <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-mono bg-slate-100 text-slate-600 border border-slate-200">
          Unconfirmed
        </span>
      );
    }
    switch (type) {
      case "missing":
        return (
          <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-mono font-semibold bg-red-50 text-red-700 border border-red-200">
            Removed
          </span>
        );
      case "added":
        return (
          <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-mono font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
            Added
          </span>
        );
      case "matching":
        return (
          <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-mono bg-slate-100 text-slate-700 border border-slate-200">
            Matching
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-mono font-semibold bg-amber-50 text-amber-800 border border-amber-200">
            Modified
          </span>
        );
    }
  };

  const visibleClauses =
    activeFilter === "changed"
      ? changedClauses
      : activeFilter === "matching"
      ? matchingClauses
      : activeFilter === "all"
      ? clauses
      : [];

  return (
    <div className="rounded-xl border border-slate-200 bg-white shadow-subtle overflow-hidden space-y-4 p-4 sm:p-5">
      {/* Document A vs Document B Header Banner */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 flex-1">
          <div className="flex items-center gap-2.5 rounded-lg border border-slate-200/90 bg-slate-50/70 px-3 py-2">
            <span className="h-5 w-5 rounded bg-blue-100 text-blue-800 font-bold text-[11px] flex items-center justify-center shrink-0">
              A
            </span>
            <div className="min-w-0">
              <p className="text-[10px] uppercase tracking-wider font-semibold text-slate-500">
                Document A (Baseline)
              </p>
              <p className="text-xs font-semibold text-slate-900 truncate" title={job.docA.name}>
                {job.docA.name}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2.5 rounded-lg border border-slate-200/90 bg-slate-50/70 px-3 py-2">
            <span className="h-5 w-5 rounded bg-indigo-100 text-indigo-800 font-bold text-[11px] flex items-center justify-center shrink-0">
              B
            </span>
            <div className="min-w-0">
              <p className="text-[10px] uppercase tracking-wider font-semibold text-slate-500">
                Document B (Comparison)
              </p>
              <p className="text-xs font-semibold text-slate-900 truncate" title={job.docB.name}>
                {job.docB.name}
              </p>
            </div>
          </div>
        </div>

        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={handleDownloadPdf}
          className="h-8 text-xs gap-1.5 shrink-0 self-start sm:self-center"
        >
          <Download className="h-3.5 w-3.5" />
          <span>Export PDF</span>
        </Button>
      </div>

      {/* Concise Overall Summary Metrics Bar */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
        <div className="rounded-lg border border-slate-200/80 bg-slate-50/40 px-3 py-2">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Similarity Score
          </span>
          <div className="text-lg font-bold font-mono text-slate-900 mt-0.5">
            {summary.overallSimilarityScore}%
          </div>
        </div>

        <div className="rounded-lg border border-slate-200/80 bg-slate-50/40 px-3 py-2">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Changed Items
          </span>
          <div className="text-lg font-bold font-mono text-amber-700 mt-0.5">
            {changedClauses.length + numericalDiscrepancies.length + contradictions.length}
          </div>
        </div>

        <div className="rounded-lg border border-slate-200/80 bg-slate-50/40 px-3 py-2">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            High-Risk Items
          </span>
          <div
            className={cn(
              "text-lg font-bold font-mono mt-0.5",
              summary.highRiskCount > 0 ? "text-red-600" : "text-emerald-700"
            )}
          >
            {summary.highRiskCount}
          </div>
        </div>

        <div className="rounded-lg border border-slate-200/80 bg-slate-50/40 px-3 py-2">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-500">
            Matching Clauses
          </span>
          <div className="text-lg font-bold font-mono text-emerald-700 mt-0.5">
            {matchingClauses.length}
          </div>
        </div>
      </div>

      {/* Segment Filter Bar (Separating Changed Information from Matching Information) */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-2.5">
        <div className="flex flex-wrap items-center gap-1 bg-slate-100 p-1 rounded-lg">
          <button
            type="button"
            onClick={() => setActiveFilter("changed")}
            className={cn(
              "px-2.5 py-1 rounded-md text-xs font-medium transition-colors",
              activeFilter === "changed"
                ? "bg-white text-slate-900 shadow-2xs font-semibold"
                : "text-slate-600 hover:text-slate-900"
            )}
          >
            Changed Clauses ({changedClauses.length})
          </button>
          <button
            type="button"
            onClick={() => setActiveFilter("numerical")}
            className={cn(
              "px-2.5 py-1 rounded-md text-xs font-medium transition-colors",
              activeFilter === "numerical"
                ? "bg-white text-slate-900 shadow-2xs font-semibold"
                : "text-slate-600 hover:text-slate-900"
            )}
          >
            Numerical ({numericalDiscrepancies.length})
          </button>
          <button
            type="button"
            onClick={() => setActiveFilter("contradictions")}
            className={cn(
              "px-2.5 py-1 rounded-md text-xs font-medium transition-colors",
              activeFilter === "contradictions"
                ? "bg-white text-slate-900 shadow-2xs font-semibold"
                : "text-slate-600 hover:text-slate-900"
            )}
          >
            Contradictions ({contradictions.length})
          </button>
          <button
            type="button"
            onClick={() => setActiveFilter("matching")}
            className={cn(
              "px-2.5 py-1 rounded-md text-xs font-medium transition-colors",
              activeFilter === "matching"
                ? "bg-white text-slate-900 shadow-2xs font-semibold"
                : "text-slate-600 hover:text-slate-900"
            )}
          >
            Matching ({matchingClauses.length})
          </button>
          <button
            type="button"
            onClick={() => setActiveFilter("all")}
            className={cn(
              "px-2.5 py-1 rounded-md text-xs font-medium transition-colors",
              activeFilter === "all"
                ? "bg-white text-slate-900 shadow-2xs font-semibold"
                : "text-slate-600 hover:text-slate-900"
            )}
          >
            All ({clauses.length})
          </button>
        </div>

        <span className="text-[11px] text-slate-500">
          Click any finding row to expand verbatim Document A & Document B clauses
        </span>
      </div>

      {/* Structured Differences Table */}
      {(activeFilter === "changed" || activeFilter === "matching" || activeFilter === "all") && (
        <div className="overflow-x-auto rounded-lg border border-slate-200">
          {visibleClauses.length === 0 ? (
            <div className="p-6 text-center text-xs text-slate-500">
              No findings in this category.
            </div>
          ) : (
            <table className="w-full text-left text-xs border-collapse">
              <thead className="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                  <th className="py-2.5 px-3 w-8"></th>
                  <th className="py-2.5 px-3">Clause / Section</th>
                  <th className="py-2.5 px-3">Change Type</th>
                  <th className="py-2.5 px-3">Risk</th>
                  <th className="py-2.5 px-3">Doc A Reference</th>
                  <th className="py-2.5 px-3">Doc B Reference</th>
                  <th className="py-2.5 px-3">Summary of Variance</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {visibleClauses.map((clause) => {
                  const isOpen = expandedRowId === clause.id;
                  const hasEvidence =
                    Boolean(clause.docAContent && clause.docAContent.trim().length > 8) ||
                    Boolean(clause.docBContent && clause.docBContent.trim().length > 8);

                  return (
                    <React.Fragment key={clause.id}>
                      <tr
                        onClick={() => setExpandedRowId(isOpen ? null : clause.id)}
                        className={cn(
                          "cursor-pointer transition-colors hover:bg-slate-50/90",
                          isOpen && "bg-slate-50"
                        )}
                      >
                        <td className="py-2.5 px-3 text-slate-400">
                          {isOpen ? (
                            <ChevronDown className="h-3.5 w-3.5" />
                          ) : (
                            <ChevronRight className="h-3.5 w-3.5" />
                          )}
                        </td>
                        <td className="py-2.5 px-3 font-semibold text-slate-900">
                          <div>{clause.title}</div>
                          <div className="text-[10px] font-normal text-slate-500">
                            {clause.category}
                          </div>
                        </td>
                        <td className="py-2.5 px-3">
                          {renderStatusPill(clause.type, hasEvidence)}
                        </td>
                        <td className="py-2.5 px-3">
                          <RiskBadge level={clause.riskLevel} showIcon={false} className="text-[10px] px-2 py-0" />
                        </td>
                        <td className="py-2.5 px-3 font-mono text-[11px] text-slate-600">
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              onNavigateToPage?.("A", clause.docAPage, clause.docAContent.slice(0, 60));
                            }}
                            className="hover:text-blue-600 hover:underline inline-flex items-center gap-1"
                          >
                            <span>Page {clause.docAPage}</span>
                            <Eye className="h-2.5 w-2.5 opacity-60" />
                          </button>
                        </td>
                        <td className="py-2.5 px-3 font-mono text-[11px] text-slate-600">
                          <button
                            type="button"
                            onClick={(e) => {
                              e.stopPropagation();
                              onNavigateToPage?.("B", clause.docBPage, clause.docBContent.slice(0, 60));
                            }}
                            className="hover:text-indigo-600 hover:underline inline-flex items-center gap-1"
                          >
                            <span>Page {clause.docBPage}</span>
                            <Eye className="h-2.5 w-2.5 opacity-60" />
                          </button>
                        </td>
                        <td className="py-2.5 px-3 text-slate-600 max-w-xs truncate">
                          {clause.explanation}
                        </td>
                      </tr>

                      {isOpen && (
                        <tr>
                          <td colSpan={7} className="p-3 bg-slate-50/70 border-t border-slate-200/70">
                            <FindingCard
                              finding={clause}
                              docAName={job.docA.name}
                              docBName={job.docB.name}
                              onNavigateToPage={onNavigateToPage}
                              defaultExpanded
                            />
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  );
                })}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Numerical Discrepancies Table */}
      {activeFilter === "numerical" && (
        <div className="overflow-x-auto rounded-lg border border-slate-200">
          {numericalDiscrepancies.length === 0 ? (
            <div className="p-6 text-center text-xs text-slate-500">
              No numerical discrepancies detected between Document A and Document B.
            </div>
          ) : (
            <table className="w-full text-left text-xs border-collapse">
              <thead className="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                  <th className="py-2.5 px-3">Metric / Term</th>
                  <th className="py-2.5 px-3">Doc A Value ({job.docA.name})</th>
                  <th className="py-2.5 px-3">Doc B Value ({job.docB.name})</th>
                  <th className="py-2.5 px-3">Variance</th>
                  <th className="py-2.5 px-3">Risk</th>
                  <th className="py-2.5 px-3">Context</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {numericalDiscrepancies.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50/80">
                    <td className="py-2.5 px-3 font-semibold text-slate-900 flex items-center gap-1.5">
                      <Hash className="h-3.5 w-3.5 text-amber-600 shrink-0" />
                      <span>{item.metric}</span>
                    </td>
                    <td className="py-2.5 px-3 font-mono font-semibold text-slate-800">
                      {item.docAValue}{" "}
                      <span className="text-[10px] text-slate-400 font-normal">(p.{item.docAPage})</span>
                    </td>
                    <td className="py-2.5 px-3 font-mono font-semibold text-red-700 bg-red-50/40">
                      {item.docBValue}{" "}
                      <span className="text-[10px] text-slate-400 font-normal">(p.{item.docBPage})</span>
                    </td>
                    <td className="py-2.5 px-3 font-mono text-amber-700">
                      {item.variancePercentage || "Changed"}
                    </td>
                    <td className="py-2.5 px-3">
                      <RiskBadge level={item.riskLevel} showIcon={false} className="text-[10px] px-2 py-0" />
                    </td>
                    <td className="py-2.5 px-3 text-slate-600">{item.context}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Contradictions View */}
      {activeFilter === "contradictions" && (
        <div className="space-y-2.5">
          {contradictions.length === 0 ? (
            <div className="p-6 text-center text-xs text-slate-500 rounded-lg border border-slate-200">
              No direct contradictions detected between Document A and Document B.
            </div>
          ) : (
            contradictions.map((c) => (
              <div
                key={c.id}
                className="rounded-lg border border-slate-200 p-3.5 space-y-2.5 bg-white"
              >
                <div className="flex items-center justify-between">
                  <span className="text-xs font-semibold text-slate-900 flex items-center gap-1.5">
                    <Scale className="h-3.5 w-3.5 text-indigo-600" />
                    <span>{c.topic}</span>
                  </span>
                  <RiskBadge level={c.riskLevel} className="text-[10px] px-2 py-0" />
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                  <div className="rounded border border-slate-200 bg-slate-50 p-2.5">
                    <span className="text-[10px] font-semibold uppercase text-slate-500 block mb-1">
                      Document A (p.{c.docAPage})
                    </span>
                    <p className="text-slate-800">{c.docAClaim}</p>
                  </div>
                  <div className="rounded border border-red-200 bg-red-50/40 p-2.5">
                    <span className="text-[10px] font-semibold uppercase text-red-700 block mb-1">
                      Document B (p.{c.docBPage})
                    </span>
                    <p className="text-slate-900">{c.docBClaim}</p>
                  </div>
                </div>
                <p className="text-[11px] text-slate-600">
                  <strong className="text-slate-800">Impact:</strong> {c.legalImpact}
                </p>
              </div>
            ))
          )}
        </div>
      )}
    </div>
  );
}
