"use client";

import * as React from "react";
import {
  FileText,
  FileSpreadsheet,
  FileBarChart,
  Image,
  Check,
  Shield,
  FileCode,
} from "lucide-react";
import { Badge } from "@/components/ui/badge";

const FORMATS = [
  {
    category: "Documents & Contracts",
    icon: FileText,
    formats: [
      { name: "PDF", desc: "Native, scanned, flattened & searchable", ext: ".pdf" },
      { name: "Word Documents", desc: "DOC, DOCX with track changes awareness", ext: ".docx, .doc" },
      { name: "Plain Text & Markdown", desc: "TXT, MD, policy text", ext: ".txt" },
    ],
  },
  {
    category: "Financial & Tabular",
    icon: FileSpreadsheet,
    formats: [
      { name: "Excel Spreadsheets", desc: "Multi-sheet numerical audits", ext: ".xlsx, .xls" },
      { name: "CSV Feeds", desc: "Financial ledgers & data dumps", ext: ".csv" },
    ],
  },
  {
    category: "Presentations & Visuals",
    icon: FileBarChart,
    formats: [
      { name: "PowerPoint Decks", desc: "Pitch decks, board slides & summaries", ext: ".pptx, .ppt" },
      { name: "High-Res Scans & Images", desc: "OCR for PNG, JPG, JPEG, WEBP", ext: ".png, .jpg" },
    ],
  },
];

export function LandingCompatibility() {
  return (
    <section id="compatibility" className="py-20 bg-white border-y border-border">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-2xl mx-auto mb-16">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-ai-light text-ai-700 text-xs font-semibold mb-3">
            <FileCode className="h-3.5 w-3.5" />
            Universal Ingestion Engine
          </div>
          <h2 className="text-3xl font-bold tracking-tight text-text-main sm:text-4xl">
            Cross-format document compatibility
          </h2>
          <p className="mt-3 text-base text-text-secondary">
            Compare a PDF against a Word document, or an Excel financial schedule against a revised contract exhibit.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {FORMATS.map((group, idx) => (
            <div
              key={idx}
              className="rounded-2xl border border-border bg-slate-50/50 p-6 flex flex-col justify-between"
            >
              <div>
                <div className="flex items-center space-x-3 mb-6">
                  <div className="h-10 w-10 rounded-xl bg-white border border-border flex items-center justify-center text-primary-600 shadow-sm">
                    <group.icon className="h-5 w-5" />
                  </div>
                  <h3 className="text-base font-bold text-text-main">
                    {group.category}
                  </h3>
                </div>

                <div className="space-y-4">
                  {group.formats.map((fmt, fIdx) => (
                    <div
                      key={fIdx}
                      className="rounded-xl bg-white border border-border/80 p-3.5 shadow-subtle"
                    >
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-sm font-semibold text-text-main">
                          {fmt.name}
                        </span>
                        <Badge variant="outline" className="text-[11px] font-mono">
                          {fmt.ext}
                        </Badge>
                      </div>
                      <p className="text-xs text-text-secondary">{fmt.desc}</p>
                    </div>
                  ))}
                </div>
              </div>

              <div className="mt-6 pt-4 border-t border-border flex items-center text-xs text-text-secondary gap-2">
                <Check className="h-4 w-4 text-success" />
                <span>Supports up to 500 pages per comparison</span>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
