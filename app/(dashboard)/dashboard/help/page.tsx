"use client";

import * as React from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import {
  HelpCircle,
  BookOpen,
  MessageSquare,
  ShieldCheck,
  FileCheck2,
  ExternalLink,
} from "lucide-react";

export default function HelpPage() {
  return (
    <div className="space-y-6 max-w-4xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-text-main">
          Help & Support Center
        </h1>
        <p className="text-sm text-text-secondary">
          Guides, best practices, and expert assistance for document verification
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card className="bg-white border-border shadow-subtle">
          <CardContent className="p-6 space-y-3">
            <div className="h-10 w-10 rounded-xl bg-primary-50 text-primary-600 flex items-center justify-center">
              <BookOpen className="h-5 w-5" />
            </div>
            <h3 className="text-base font-bold text-text-main">
              Verification Best Practices
            </h3>
            <p className="text-xs text-text-secondary leading-relaxed">
              Learn how to prepare multi-format documents (PDF, DOCX, XLSX) for optimal OCR accuracy, clause hierarchy indexing, and numerical verification.
            </p>
            <Button variant="outline" size="sm" className="text-xs">
              Read Guide
            </Button>
          </CardContent>
        </Card>

        <Card className="bg-white border-border shadow-subtle">
          <CardContent className="p-6 space-y-3">
            <div className="h-10 w-10 rounded-xl bg-ai-light text-ai-600 flex items-center justify-center">
              <MessageSquare className="h-5 w-5" />
            </div>
            <h3 className="text-base font-bold text-text-main">
              AI Query Syntax & Prompts
            </h3>
            <p className="text-xs text-text-secondary leading-relaxed">
              Explore prompt strategies for cross-examining indemnities, payment schedules, contradictory clauses, and missing liability exclusions.
            </p>
            <Button variant="outline" size="sm" className="text-xs">
              Prompt Handbook
            </Button>
          </CardContent>
        </Card>
      </div>

      <Card className="bg-white border-border shadow-subtle">
        <CardHeader className="p-6 pb-2">
          <CardTitle className="text-base font-bold text-text-main">
            Contact Enterprise Support
          </CardTitle>
          <CardDescription className="text-xs text-text-secondary">
            Our legal technology engineering team is on standby for custom parsing needs.
          </CardDescription>
        </CardHeader>
        <CardContent className="p-6 pt-2">
          <div className="flex flex-col sm:flex-row items-center justify-between gap-4 rounded-xl bg-slate-50 p-4 border border-border">
            <div>
              <p className="text-xs font-semibold text-text-main">
                Dedicated Enterprise Support Desk
              </p>
              <p className="text-[11px] text-text-secondary">
                Response time SLA: under 1 hour for critical audit blocks.
              </p>
            </div>
            <a href="mailto:support@docver.com">
              <Button size="sm" className="text-xs font-semibold">
                Email Engineering Support
              </Button>
            </a>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
