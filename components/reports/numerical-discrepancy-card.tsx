"use client";

import * as React from "react";
import { NumericalDiscrepancyItem } from "@/types/comparison";
import { RiskBadge } from "./risk-badge";
import { Badge } from "@/components/ui/badge";
import { Hash, ArrowRight } from "lucide-react";

interface NumericalDiscrepancyCardProps {
  item: NumericalDiscrepancyItem;
}

export function NumericalDiscrepancyCard({ item }: NumericalDiscrepancyCardProps) {
  return (
    <div className="rounded-xl border border-border bg-white shadow-subtle p-5 space-y-4 hover:shadow-card transition-all">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-border pb-3">
        <div className="flex items-center space-x-2">
          <div className="h-7 w-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
            <Hash className="h-4 w-4" />
          </div>
          <h4 className="text-sm font-bold text-text-main">{item.metric}</h4>
        </div>
        <div className="flex items-center space-x-2">
          {item.variancePercentage && (
            <Badge variant="outline" className="font-mono text-xs font-bold text-amber-700 bg-amber-50 border-amber-200">
              Variance: {item.variancePercentage}
            </Badge>
          )}
          <RiskBadge level={item.riskLevel} />
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div className="p-3 rounded-lg bg-slate-50 border border-border">
          <span className="text-[10px] uppercase font-bold text-text-muted block mb-1">
            Doc A Figure (Page {item.docAPage})
          </span>
          <span className="text-base font-extrabold text-text-main font-mono">
            {item.docAValue}
          </span>
        </div>

        <div className="p-3 rounded-lg bg-red-50/50 border border-error/20">
          <span className="text-[10px] uppercase font-bold text-error block mb-1">
            Doc B Figure (Page {item.docBPage})
          </span>
          <span className="text-base font-extrabold text-error font-mono">
            {item.docBValue}
          </span>
        </div>
      </div>

      <p className="text-xs text-text-secondary leading-relaxed pt-1 border-t border-border/50">
        <strong className="text-text-main">Audit Context: </strong>
        {item.context}
      </p>
    </div>
  );
}
