import * as React from "react";
import { AlertCircle, AlertTriangle, CheckCircle, ShieldCheck } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { RiskLevel } from "@/types/comparison";

interface RiskBadgeProps {
  level: RiskLevel;
  showIcon?: boolean;
  className?: string;
}

export function RiskBadge({ level, showIcon = true, className }: RiskBadgeProps) {
  switch (level) {
    case "high":
      return (
        <Badge variant="destructive" className={className}>
          {showIcon && <AlertCircle className="mr-1 h-3 w-3" />}
          High Risk
        </Badge>
      );
    case "medium":
      return (
        <Badge variant="warning" className={className}>
          {showIcon && <AlertTriangle className="mr-1 h-3 w-3" />}
          Medium Risk
        </Badge>
      );
    case "low":
      return (
        <Badge variant="default" className={className}>
          {showIcon && <ShieldCheck className="mr-1 h-3 w-3" />}
          Low Risk
        </Badge>
      );
    case "none":
    default:
      return (
        <Badge variant="success" className={className}>
          {showIcon && <CheckCircle className="mr-1 h-3 w-3" />}
          No Risk / Matching
        </Badge>
      );
  }
}
