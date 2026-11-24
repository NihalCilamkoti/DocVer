import * as React from "react";
import { LucideIcon, ArrowUpRight } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { cn } from "@/lib/utils";

interface StatsCardProps {
  title: string;
  value: string | number;
  change?: string;
  isPositive?: boolean;
  description?: string;
  icon: LucideIcon;
  iconColor?: string;
  iconBg?: string;
}

export function StatsCard({
  title,
  value,
  change,
  isPositive = true,
  description,
  icon: Icon,
  iconColor = "text-primary-600",
  iconBg = "bg-primary-50",
}: StatsCardProps) {
  return (
    <Card className="border-border shadow-subtle bg-white hover:shadow-card transition-all">
      <CardContent className="p-6">
        <div className="flex items-center justify-between mb-4">
          <span className="text-xs font-semibold uppercase tracking-wider text-text-secondary">
            {title}
          </span>
          <div
            className={cn(
              "h-10 w-10 rounded-xl flex items-center justify-center shrink-0",
              iconBg,
              iconColor
            )}
          >
            <Icon className="h-5 w-5" />
          </div>
        </div>

        <div className="space-y-1">
          <div className="text-2xl sm:text-3xl font-extrabold text-text-main tracking-tight">
            {value}
          </div>

          <div className="flex items-center text-xs space-x-1.5 pt-1">
            {change && (
              <span
                className={cn(
                  "font-semibold flex items-center",
                  isPositive ? "text-success" : "text-error"
                )}
              >
                <ArrowUpRight className="h-3 w-3 mr-0.5" />
                {change}
              </span>
            )}
            {description && (
              <span className="text-text-secondary">{description}</span>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  );
}
