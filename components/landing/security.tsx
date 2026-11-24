"use client";

import * as React from "react";
import {
  ShieldCheck,
  Lock,
  Server,
  FileCheck2,
  EyeOff,
  UserCheck,
} from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";

const SECURITY_POINTS = [
  {
    icon: Lock,
    title: "Zero Model Retention",
    desc: "Your confidential agreements, trade secrets, and financial filings are never used to train or fine-tune public foundation models.",
  },
  {
    icon: Server,
    title: "Isolated Processing Sandboxes",
    desc: "Every comparison job executes in an isolated ephemeral execution environment that is wiped clean upon job termination.",
  },
  {
    icon: ShieldCheck,
    title: "End-to-End Encryption",
    desc: "Data in transit is guarded with TLS 1.3; data at rest is encrypted with AES-256 with enterprise customer-managed keys (CMK) support.",
  },
  {
    icon: EyeOff,
    title: "Strict Role-Based Access Control",
    desc: "Control document visibility across organizations, branches, and teams with granular workspace access policies.",
  },
  {
    icon: FileCheck2,
    title: "Comprehensive Audit Logs",
    desc: "Immutable activity logs recording every view, query, export, and deletion timestamp for regulatory compliance.",
  },
  {
    icon: UserCheck,
    title: "Enterprise Compliance Ready",
    desc: "Engineered with controls mapped to SOC 2 Type II, ISO 27001, and GDPR privacy mandates.",
  },
];

export function LandingSecurity() {
  return (
    <section id="security" className="py-20 bg-background">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-2xl mx-auto mb-16">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-success-50 text-success-700 text-xs font-semibold mb-3">
            <ShieldCheck className="h-3.5 w-3.5" />
            Bank-Grade Confidentiality & Privacy
          </div>
          <h2 className="text-3xl font-bold tracking-tight text-text-main sm:text-4xl">
            Security built for high-stakes agreements
          </h2>
          <p className="mt-3 text-base text-text-secondary">
            We treat your sensitive legal and financial data with uncompromising security standards.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {SECURITY_POINTS.map((item, idx) => (
            <Card key={idx} className="bg-white border-border shadow-subtle">
              <CardContent className="p-6">
                <div className="h-10 w-10 rounded-xl bg-slate-100 text-text-main flex items-center justify-center mb-4">
                  <item.icon className="h-5 w-5 text-primary-600" />
                </div>
                <h3 className="text-base font-bold text-text-main mb-2">
                  {item.title}
                </h3>
                <p className="text-sm text-text-secondary leading-relaxed">
                  {item.desc}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      </div>
    </section>
  );
}
