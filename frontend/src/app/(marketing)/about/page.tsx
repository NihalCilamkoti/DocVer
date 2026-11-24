"use client";

import Link from "next/link";
import { Button } from "@/components/ui/button";
import { ShieldCheck, FileCheck2, Cpu, ArrowRight } from "lucide-react";

export default function AboutPage() {
  return (
    <div className="max-w-4xl mx-auto py-16 px-4 space-y-12">
      <div className="text-center space-y-4">
        <h1 className="text-4xl font-extrabold text-text-main tracking-tight">About DocVer</h1>
        <p className="text-lg text-text-secondary max-w-2xl mx-auto">
          Enterprise AI verification and document comparison engine built for legal teams, auditors, and financial institutions.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div className="bg-white p-6 rounded-xl border border-border shadow-subtle space-y-3">
          <div className="h-10 w-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center font-bold">
            <ShieldCheck className="h-5 w-5" />
          </div>
          <h3 className="font-bold text-text-main">Strict Privacy First</h3>
          <p className="text-sm text-text-secondary">
            Never store customer document content permanently. Ephemeral encrypted indexing with automated retention purges.
          </p>
        </div>

        <div className="bg-white p-6 rounded-xl border border-border shadow-subtle space-y-3">
          <div className="h-10 w-10 rounded-lg bg-ai-light text-ai-600 flex items-center justify-center font-bold">
            <Cpu className="h-5 w-5" />
          </div>
          <h3 className="font-bold text-text-main">Tri-Provider AI Engine</h3>
          <p className="text-sm text-text-secondary">
            Google Gemini, Groq LPU, and OpenRouter orchestration with automated quota failover and proactive safety margins.
          </p>
        </div>

        <div className="bg-white p-6 rounded-xl border border-border shadow-subtle space-y-3">
          <div className="h-10 w-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
            <FileCheck2 className="h-5 w-5" />
          </div>
          <h3 className="font-bold text-text-main">Zero Hallucinations</h3>
          <p className="text-sm text-text-secondary">
            Every difference is verified against ground truth character citations and page references before entering reports.
          </p>
        </div>
      </div>

      <div className="text-center pt-8">
        <Link href="/dashboard">
          <Button className="gap-2">
            Open Dashboard <ArrowRight className="h-4 w-4" />
          </Button>
        </Link>
      </div>
    </div>
  );
}
