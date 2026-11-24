"use client";

import { useEffect } from "react";
import { Button } from "@/components/ui/button";
import { AlertTriangle, RefreshCw } from "lucide-react";

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    // Log error to monitoring
    console.error("DocVer Frontend Unhandled Error:", error);
  }, [error]);

  return (
    <div className="min-h-[60vh] flex flex-col items-center justify-center p-6 text-center space-y-4">
      <div className="h-12 w-12 rounded-full bg-error-50 text-error-600 flex items-center justify-center">
        <AlertTriangle className="h-6 w-6" />
      </div>
      <h2 className="text-xl font-bold text-text-main">Something went wrong</h2>
      <p className="text-xs text-text-secondary max-w-md">
        An unexpected error occurred while rendering the page. The system administrator has been notified.
      </p>
      <Button onClick={() => reset()} variant="outline" size="sm" className="gap-2 text-xs">
        <RefreshCw className="h-3.5 w-3.5" /> Try Again
      </Button>
    </div>
  );
}
