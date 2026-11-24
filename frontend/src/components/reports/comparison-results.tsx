"use client";

import * as React from "react";
import { ComparisonJob } from "@/types/comparison";
import { ClauseDiffCard } from "./clause-diff-card";
import { NumericalDiscrepancyCard } from "./numerical-discrepancy-card";
import { ContradictionCard } from "./contradiction-card";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/components/ui/tabs";
import { Badge } from "@/components/ui/badge";
import {
  FileDiff,
  AlertTriangle,
  FileCheck2,
  Scale,
  Hash,
  Download,
  Info,
  Sparkles,
} from "lucide-react";
import { Button } from "@/components/ui/button";

interface ComparisonResultsProps {
  job: ComparisonJob;
}

export function ComparisonResults({ job }: ComparisonResultsProps) {
  const { summary, clauses, numericalDiscrepancies, contradictions } = job;

  const modifiedClauses = clauses.filter((c) => c.type === "modified");
  const missingClauses = clauses.filter((c) => c.type === "missing");
  const matchingClauses = clauses.filter((c) => c.type === "matching");

  const handleDownloadPdf = () => {
    const backendUrl = process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api/v1";
    window.open(`${backendUrl}/comparisons/${job.id}/report/pdf`, "_blank");
  };

  return (
    <div className="space-y-6">
      {/* Summary Scorecards Row */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Similarity Score */}
        <div className="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs flex flex-col justify-between hover:border-indigo-200 transition-all">
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
            Similarity Match
          </span>
          <div className="mt-2 flex items-baseline justify-between">
            <span className="text-3xl font-extrabold text-indigo-600 font-mono">
              {summary.overallSimilarityScore}%
            </span>
            <span className="text-xs text-slate-500 font-medium">
              {summary.totalDifferences} Total Diffs
            </span>
          </div>
          <div className="mt-3 h-1.5 w-full bg-slate-100 rounded-full overflow-hidden">
            <div
              className="h-full bg-gradient-to-r from-indigo-500 to-violet-600 rounded-full transition-all duration-500"
              style={{ width: `${summary.overallSimilarityScore}%` }}
            />
          </div>
        </div>

        {/* Key Differences Count */}
        <div className="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs flex flex-col justify-between hover:border-amber-200 transition-all">
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-600">
            Key Differences
          </span>
          <div className="mt-2 flex items-baseline justify-between">
            <span className="text-3xl font-extrabold text-amber-600 font-mono">
              {summary.highRiskCount}
            </span>
            {summary.highRiskCount > 0 ? (
              <Badge variant="outline" className="text-[10px] border-amber-300 text-amber-700 bg-amber-50">
                Notice
              </Badge>
            ) : (
              <span className="text-xs text-emerald-600 font-medium">Aligned</span>
            )}
          </div>
          <p className="mt-3 text-[11px] text-slate-500">
            {summary.highRiskCount > 0
              ? `${summary.highRiskCount} significant difference identified`
              : "No critical variances detected"}
          </p>
        </div>

        {/* Values & Metrics */}
        <div className="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs flex flex-col justify-between hover:border-violet-200 transition-all">
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-600">
            Values & Metrics
          </span>
          <div className="mt-2 flex items-baseline justify-between">
            <span className="text-3xl font-extrabold text-violet-600 font-mono">
              {summary.numericalDiscrepanciesCount}
            </span>
            <span className="text-xs text-slate-500 font-mono">
              Rates & Dates
            </span>
          </div>
          <p className="mt-3 text-[11px] text-slate-500">
            {summary.numericalDiscrepanciesCount > 0
              ? `${summary.numericalDiscrepanciesCount} numerical difference noted`
              : "Numerical metrics consistent"}
          </p>
        </div>

        {/* Distinct Differences */}
        <div className="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-xs flex flex-col justify-between hover:border-indigo-200 transition-all">
          <span className="text-xs font-semibold uppercase tracking-wider text-slate-600">
            Conflicting Items
          </span>
          <div className="mt-2 flex items-baseline justify-between">
            <span className="text-3xl font-extrabold text-slate-800 font-mono">
              {summary.contradictionsCount}
            </span>
            {summary.contradictionsCount > 0 && (
              <Badge variant="outline" className="text-[10px] border-indigo-200 text-indigo-700 bg-indigo-50">
                Check
              </Badge>
            )}
          </div>
          <p className="mt-3 text-[11px] text-slate-500">
            {summary.contradictionsCount > 0
              ? `${summary.contradictionsCount} contrasting point found`
              : "Consistent between documents"}
          </p>
        </div>
      </div>

      {/* Real AI Cross-Comparison Analysis Card */}
      {summary.executiveSummary && (
        <div className="rounded-xl border border-ai-200/90 bg-gradient-to-br from-ai-50/60 via-white to-primary-50/30 p-5 shadow-subtle space-y-3">
          <div className="flex items-center justify-between border-b border-ai-100 pb-3">
            <div className="flex items-center gap-2">
              <div className="h-7 w-7 rounded-lg bg-ai-100 flex items-center justify-center text-ai-700">
                <Sparkles className="h-4 w-4" />
              </div>
              <div>
                <h3 className="text-sm font-bold text-text-main">
                  AI Cross-Comparison Analysis
                </h3>
                <p className="text-[11px] text-text-secondary">
                  Evidence-based synthesis generated by AI examining Document A vs Document B
                </p>
              </div>
            </div>
            <Badge variant="outline" className="text-[11px] bg-white text-emerald-700 border-emerald-300 gap-1">
              <FileCheck2 className="h-3.5 w-3.5 text-emerald-600" />
              Verified Quotes
            </Badge>
          </div>
          <div className="text-xs text-text-main leading-relaxed whitespace-pre-wrap font-sans space-y-2">
            {summary.executiveSummary}
          </div>
        </div>
      )}

      {/* Tabs for Segmenting Results */}
      <Tabs defaultValue="all" className="w-full">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-border pb-3">
          <TabsList className="bg-slate-100 p-1">
            <TabsTrigger value="all" className="text-xs">
              All Items ({clauses.length + numericalDiscrepancies.length + contradictions.length})
            </TabsTrigger>
            <TabsTrigger value="modified" className="text-xs">
              Modified ({modifiedClauses.length})
            </TabsTrigger>
            <TabsTrigger value="missing" className="text-xs">
              Missing ({missingClauses.length})
            </TabsTrigger>
            <TabsTrigger value="numerical" className="text-xs">
              Numerical ({numericalDiscrepancies.length})
            </TabsTrigger>
            <TabsTrigger value="contradictions" className="text-xs">
              Contradictions ({contradictions.length})
            </TabsTrigger>
            <TabsTrigger value="matching" className="text-xs">
              Matching ({matchingClauses.length})
            </TabsTrigger>
          </TabsList>

          <Button
            variant="outline"
            size="sm"
            onClick={handleDownloadPdf}
            className="text-xs gap-1.5 self-end sm:self-center hover:border-primary-300 hover:text-primary-700"
          >
            <Download className="h-3.5 w-3.5" />
            Export Redline PDF
          </Button>
        </div>

        {/* Tab Content: All */}
        <TabsContent value="all" className="space-y-4 pt-2">
          {clauses.map((clause) => (
            <ClauseDiffCard key={clause.id} clause={clause} />
          ))}
          {numericalDiscrepancies.map((num) => (
            <NumericalDiscrepancyCard key={num.id} item={num} />
          ))}
          {contradictions.map((con) => (
            <ContradictionCard key={con.id} item={con} />
          ))}
        </TabsContent>

        {/* Tab Content: Modified */}
        <TabsContent value="modified" className="space-y-4 pt-2">
          {modifiedClauses.map((clause) => (
            <ClauseDiffCard key={clause.id} clause={clause} />
          ))}
        </TabsContent>

        {/* Tab Content: Missing */}
        <TabsContent value="missing" className="space-y-4 pt-2">
          {missingClauses.map((clause) => (
            <ClauseDiffCard key={clause.id} clause={clause} />
          ))}
        </TabsContent>

        {/* Tab Content: Numerical */}
        <TabsContent value="numerical" className="space-y-4 pt-2">
          {numericalDiscrepancies.map((num) => (
            <NumericalDiscrepancyCard key={num.id} item={num} />
          ))}
        </TabsContent>

        {/* Tab Content: Contradictions */}
        <TabsContent value="contradictions" className="space-y-4 pt-2">
          {contradictions.map((con) => (
            <ContradictionCard key={con.id} item={con} />
          ))}
        </TabsContent>

        {/* Tab Content: Matching */}
        <TabsContent value="matching" className="space-y-4 pt-2">
          {matchingClauses.map((clause) => (
            <ClauseDiffCard key={clause.id} clause={clause} />
          ))}
        </TabsContent>
      </Tabs>
    </div>
  );
}
