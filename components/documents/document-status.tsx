"use client";

import * as React from "react";
import {
  CheckCircle2,
  Loader2,
  AlertCircle,
  FileWarning,
  Upload,
  FileSearch,
  Cpu,
} from "lucide-react";
import { DocumentProcessingStatus } from "@/types/comparison";
import { cn } from "@/lib/utils";

interface DocumentStatusProps {
  status: DocumentProcessingStatus;
  progress?: number;
  className?: string;
  compact?: boolean;
}

const STATUS_META: Record<
  DocumentProcessingStatus,
  {
    label: string;
    colorClass: string;
    dotClass: string;
    isAnimated?: boolean;
  }
> = {
  idle: {
    label: "Pending",
    colorClass: "bg-slate-100 text-slate-600 border-slate-200",
    dotClass: "bg-slate-400",
  },
  uploading: {
    label: "Uploading",
    colorClass: "bg-blue-50 text-blue-700 border-blue-200",
    dotClass: "bg-blue-600",
    isAnimated: true,
  },
  uploaded: {
    label: "Uploaded",
    colorClass: "bg-indigo-50 text-indigo-700 border-indigo-200",
    dotClass: "bg-indigo-600",
    isAnimated: true,
  },
  extracting: {
    label: "Extracting text",
    colorClass: "bg-amber-50 text-amber-700 border-amber-200",
    dotClass: "bg-amber-500",
    isAnimated: true,
  },
  processing: {
    label: "Processing",
    colorClass: "bg-purple-50 text-purple-700 border-purple-200",
    dotClass: "bg-purple-600",
    isAnimated: true,
  },
  ready: {
    label: "Ready",
    colorClass: "bg-emerald-50 text-emerald-700 border-emerald-200",
    dotClass: "bg-emerald-600",
  },
  failed: {
    label: "Failed",
    colorClass: "bg-red-50 text-red-700 border-red-200",
    dotClass: "bg-red-600",
  },
  error: {
    label: "Failed",
    colorClass: "bg-red-50 text-red-700 border-red-200",
    dotClass: "bg-red-600",
  },
  unsupported: {
    label: "Unsupported file",
    colorClass: "bg-amber-50 text-amber-800 border-amber-300",
    dotClass: "bg-amber-600",
  },
};

export function DocumentStatus({
  status,
  className,
  compact = false,
}: DocumentStatusProps) {
  const meta = STATUS_META[status] || STATUS_META.idle;

  const renderIcon = () => {
    switch (status) {
      case "uploading":
        return <Upload className="h-3 w-3 animate-pulse shrink-0" />;
      case "uploaded":
        return <Loader2 className="h-3 w-3 animate-spin shrink-0" />;
      case "extracting":
        return <FileSearch className="h-3 w-3 animate-pulse shrink-0" />;
      case "processing":
        return <Cpu className="h-3 w-3 animate-spin shrink-0" />;
      case "ready":
        return <CheckCircle2 className="h-3 w-3 shrink-0 text-emerald-600" />;
      case "failed":
      case "error":
        return <AlertCircle className="h-3 w-3 shrink-0 text-red-600" />;
      case "unsupported":
        return <FileWarning className="h-3 w-3 shrink-0 text-amber-600" />;
      default:
        return <span className={cn("h-1.5 w-1.5 rounded-full", meta.dotClass)} />;
    }
  };

  return (
    <span
      role="status"
      aria-live="polite"
      className={cn(
        "inline-flex items-center gap-1 rounded-full border font-medium transition-colors",
        compact ? "px-1.5 py-0.5 text-[10px]" : "px-2 py-0.5 text-[11px]",
        meta.colorClass,
        className
      )}
    >
      {renderIcon()}
      <span>{meta.label}</span>
    </span>
  );
}
