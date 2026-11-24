"use client";

import * as React from "react";
import { UploadCloud, Cpu, CheckCircle2, MessageSquareText } from "lucide-react";

const STEPS = [
  {
    step: "01",
    title: "Upload Both Versions",
    description:
      "Drop your baseline and revised files (PDF, Word, Excel, scanned docs, or images) into the secure client workspace.",
    icon: UploadCloud,
  },
  {
    step: "02",
    title: "Deep Semantic Extraction",
    description:
      "Our multi-layer parser extracts layout hierarchies, clauses, tables, and numerical metrics into normalized structures.",
    icon: Cpu,
  },
  {
    step: "03",
    title: "Cross-Inspection & Risk Audit",
    description:
      "DocVer identifies additions, deletions, modified clauses, numerical variances, and legal contradictions with risk ratings.",
    icon: CheckCircle2,
  },
  {
    step: "04",
    title: "Query with Cited Evidence",
    description:
      "Interrogate both documents in conversational AI chat, receiving verbatim quotes and clickable page citations.",
    icon: MessageSquareText,
  },
];

export function LandingHowItWorks() {
  return (
    <section id="how-it-works" className="py-20 bg-background">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-2xl mx-auto mb-16">
          <span className="text-xs font-semibold tracking-wider uppercase text-primary-600">
            Intuitive Four-Step Workflow
          </span>
          <h2 className="mt-2 text-3xl font-bold tracking-tight text-text-main sm:text-4xl">
            How DocVer analyzes your documents
          </h2>
          <p className="mt-3 text-base text-text-secondary">
            From raw files to verifiable, auditable comparison reports in seconds.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 relative">
          {STEPS.map((step, idx) => (
            <div
              key={idx}
              className="relative flex flex-col rounded-2xl bg-white border border-border p-6 shadow-subtle hover:shadow-card transition-all"
            >
              <div className="flex items-center justify-between mb-4">
                <span className="text-2xl font-black text-slate-200 font-mono">
                  {step.step}
                </span>
                <div className="h-10 w-10 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center">
                  <step.icon className="h-5 w-5" />
                </div>
              </div>
              <h3 className="text-base font-semibold text-text-main mb-2">
                {step.title}
              </h3>
              <p className="text-sm text-text-secondary leading-relaxed">
                {step.description}
              </p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
