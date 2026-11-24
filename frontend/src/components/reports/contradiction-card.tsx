"use client";

import * as React from "react";
import { ContradictionItem } from "@/types/comparison";
import { RiskBadge } from "./risk-badge";
import { Badge } from "@/components/ui/badge";
import { Scale, AlertCircle } from "lucide-react";

interface ContradictionCardProps {
  item: ContradictionItem;
}

export function ContradictionCard({ item }: ContradictionCardProps) {
  return (
    <div className="rounded-xl border border-border bg-white shadow-subtle p-5 space-y-4 hover:shadow-card transition-all">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 border-b border-border pb-3">
        <div className="flex items-center space-x-2">
          <div className="h-7 w-7 rounded-lg bg-purple-50 text-ai-600 flex items-center justify-center">
            <Scale className="h-4 w-4" />
          </div>
          <h4 className="text-sm font-bold text-text-main">{item.topic}</h4>
        </div>
        <RiskBadge level={item.riskLevel} />
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div className="p-3 rounded-lg bg-slate-50 border border-border space-y-1">
          <span className="text-[10px] uppercase font-bold text-text-muted block">
            Document A Clause (p.{item.docAPage})
          </span>
          <p className="text-text-main font-medium">{item.docAClaim}</p>
        </div>

        <div className="p-3 rounded-lg bg-purple-50/50 border border-ai-muted space-y-1">
          <span className="text-[10px] uppercase font-bold text-ai-700 block">
            Conflicting Document B Clause (p.{item.docBPage})
          </span>
          <p className="text-text-main font-medium">{item.docBClaim}</p>
        </div>
      </div>

      <div className="p-3 rounded-lg bg-error-50/50 border border-error-100 flex items-start space-x-2 text-xs">
        <AlertCircle className="h-4 w-4 text-error shrink-0 mt-0.5" />
        <div className="text-text-main">
          <span className="font-semibold text-error">Legal Conflict: </span>
          <span>{item.legalImpact}</span>
        </div>
      </div>
    </div>
  );
}
