import Link from "next/link";
import { Button } from "@/components/ui/button";
import { FileQuestion, ArrowLeft } from "lucide-react";

export default function NotFound() {
  return (
    <div className="min-h-[60vh] flex flex-col items-center justify-center p-6 text-center space-y-4">
      <div className="h-12 w-12 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center">
        <FileQuestion className="h-6 w-6" />
      </div>
      <h2 className="text-2xl font-bold text-text-main">Page Not Found</h2>
      <p className="text-xs text-text-secondary max-w-sm">
        The requested verification report or dashboard page does not exist or has been archived.
      </p>
      <Link href="/dashboard">
        <Button variant="outline" size="sm" className="gap-2 text-xs">
          <ArrowLeft className="h-3.5 w-3.5" /> Return to Dashboard
        </Button>
      </Link>
    </div>
  );
}
