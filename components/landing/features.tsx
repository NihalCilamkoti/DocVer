"use client";

import * as React from "react";
import {
  FileDiff,
  ShieldAlert,
  Search,
  Hash,
  Scale,
  Brain,
  Layers,
  Sparkles,
} from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";

const FEATURES = [
  {
    icon: FileDiff,
    title: "Semantic & Structural Diff",
    description:
      "Beyond mere text comparison. Detect shifted paragraphs, rewritten language, restructuring, and subtle word swaps with semantic understanding.",
    color: "text-primary-600",
    bg: "bg-primary-50",
  },
  {
    icon: ShieldAlert,
    title: "Automated Risk Scoring",
    description:
      "Every deviation is classified as High, Medium, or Low risk based on operational, legal, and financial liability exposure.",
    color: "text-error",
    bg: "bg-error-50",
  },
  {
    icon: Hash,
    title: "Numerical Discrepancy Auditing",
    description:
      "Pinpoint mismatched figures, rates, percentages, SLA uptime levels, fee structures, and dates across hundreds of pages in seconds.",
    color: "text-amber-600",
    bg: "bg-amber-50",
  },
  {
    icon: Scale,
    title: "Contradiction Detection",
    description:
      "Uncover conflicting statements between different clauses or conflicting agreements (e.g. governing law vs arbitration venue conflicts).",
    color: "text-ai-600",
    bg: "bg-ai-light",
  },
  {
    icon: Brain,
    title: "Evidence-Backed AI Chat",
    description:
      "Ask questions in natural language. Every AI assertion is backed by verbatim quotes and exact document page citations you can verify.",
    color: "text-primary-600",
    bg: "bg-primary-50",
  },
  {
    icon: Layers,
    title: "Massive Volume Handling",
    description:
      "Built for enterprise workloads. Process 500+ page contracts, regulatory filings, financial prospectuses, and complex redlines without stutter.",
    color: "text-ai-600",
    bg: "bg-ai-light",
  },
];

export function LandingFeatures() {
  return (
    <section id="features" className="py-20 bg-white border-y border-border">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-2xl mx-auto mb-16">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary-50 text-primary-700 text-xs font-semibold mb-3">
            <Sparkles className="h-3.5 w-3.5" />
            Capabilities Designed for Enterprise Rigor
          </div>
          <h2 className="text-3xl font-bold tracking-tight text-text-main sm:text-4xl">
            Everything you need to audit critical documents
          </h2>
          <p className="mt-3 text-base text-text-secondary">
            Engineered for legal counsels, procurement teams, auditors, and financial analysts who cannot afford missed discrepancies.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {FEATURES.map((feat, idx) => (
            <Card
              key={idx}
              className="border-border/80 hover:border-primary-200 hover:shadow-card transition-all duration-200 group"
            >
              <CardContent className="p-6">
                <div
                  className={`h-12 w-12 rounded-xl ${feat.bg} ${feat.color} flex items-center justify-center mb-5 transition-transform group-hover:scale-105`}
                >
                  <feat.icon className="h-6 w-6" />
                </div>
                <h3 className="text-lg font-semibold text-text-main mb-2">
                  {feat.title}
                </h3>
                <p className="text-sm text-text-secondary leading-relaxed">
                  {feat.description}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </section>
  );
}
