"use client";

import * as React from "react";
import { CheckCircle2, AlertCircle, Info, X, AlertTriangle } from "lucide-react";
import { cn } from "@/lib/utils";

export interface ToastItem {
  id: string;
  title: string;
  description?: string;
  variant?: "default" | "success" | "error" | "warning";
}

interface ToastBannerProps {
  toast: ToastItem;
  onDismiss: (id: string) => void;
}

export function ToastBanner({ toast, onDismiss }: ToastBannerProps) {
  const variant = toast.variant || "default";

  return (
    <div
      role="status"
      aria-live="polite"
      className={cn(
        "flex items-start gap-2.5 rounded-lg border px-3.5 py-2.5 shadow-elevated text-xs bg-white transition-all max-w-sm",
        variant === "success" && "border-emerald-200 bg-emerald-50/95 text-emerald-950",
        variant === "error" && "border-red-200 bg-red-50/95 text-red-950",
        variant === "warning" && "border-amber-200 bg-amber-50/95 text-amber-950",
        variant === "default" && "border-slate-200 bg-white text-slate-900"
      )}
    >
      {variant === "success" && <CheckCircle2 className="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" />}
      {variant === "error" && <AlertCircle className="h-4 w-4 text-red-600 shrink-0 mt-0.5" />}
      {variant === "warning" && <AlertTriangle className="h-4 w-4 text-amber-600 shrink-0 mt-0.5" />}
      {variant === "default" && <Info className="h-4 w-4 text-slate-600 shrink-0 mt-0.5" />}

      <div className="flex-1 min-w-0">
        <p className="font-semibold leading-tight">{toast.title}</p>
        {toast.description && (
          <p className="text-[11px] opacity-85 mt-0.5 leading-snug">{toast.description}</p>
        )}
      </div>

      <button
        type="button"
        onClick={() => onDismiss(toast.id)}
        className="text-slate-400 hover:text-slate-700 p-0.5 rounded"
        aria-label="Dismiss notification"
      >
        <X className="h-3.5 w-3.5" />
      </button>
    </div>
  );
}
