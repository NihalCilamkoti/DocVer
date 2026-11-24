"use client";

import * as React from "react";
import { ChevronDown } from "lucide-react";

const FAQS = [
  {
    question: "How does DocVer differ from a standard redline tool like Word or Adobe Diff?",
    answer:
      "Traditional diff tools only perform character-level or string-level matching. If an entire section is reworded or moved, standard diffs mark it as a chaotic delete-and-replace. DocVer understands legal semantics: it recognizes when a clause has been rewritten with the same intent, flags numerical alterations, detects subtle additions of late fees, and warns when operative rights have been degraded.",
  },
  {
    question: "Are our proprietary documents used to train AI models?",
    answer:
      "Strictly no. DocVer has zero model retention agreements with our AI infrastructure partners. Your uploaded documents exist only within temporary volatile memory during comparison analysis and are never fed into foundation model training corpora.",
  },
  {
    question: "What document formats and file size limits are supported?",
    answer:
      "DocVer supports PDF, Microsoft Word (DOC, DOCX), Excel (XLS, XLSX), CSV, PowerPoint (PPT, PPTX), plain text, and high-resolution image formats (PNG, JPG, WEBP). Out of the box, files up to 50MB and 500 pages per document are handled with enterprise plans supporting higher throughput.",
  },
  {
    question: "Can I verify cross-format documents, like a PDF against a DOCX?",
    answer:
      "Yes! Our ingestion pipeline parses both documents into an abstract semantic syntax tree. You can seamlessly compare a signed PDF against a newly drafted Word document or compare an original SLA contract with an updated spreadsheet.",
  },
  {
    question: "How accurate are the citations in the AI chat?",
    answer:
      "Every answer generated in our conversational interface is bound to specific line-level quotes and exact document page numbers. Clicking any citation opens the exact evidence preview, allowing you to instantly double-check our AI against the source text.",
  },
  {
    question: "Will DocVer integrate with our internal systems?",
    answer:
      "DocVer is designed with modern headless APIs ready for Laravel and webhook integrations, cloud drive connectors (Google Drive, SharePoint, Box), and document management workflows.",
  },
];

export function LandingFAQ() {
  const [openIndex, setOpenIndex] = React.useState<number | null>(0);

  const toggle = (index: number) => {
    setOpenIndex(openIndex === index ? null : index);
  };

  return (
    <section id="faq" className="py-20 bg-background">
      <div className="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div className="text-center mb-16">
          <span className="text-xs font-semibold tracking-wider uppercase text-primary-600">
            Got Questions?
          </span>
          <h2 className="mt-2 text-3xl font-bold tracking-tight text-text-main sm:text-4xl">
            Frequently Asked Questions
          </h2>
          <p className="mt-3 text-base text-text-secondary">
            Everything you need to know about DocVer&apos;s document verification platform.
          </p>
        </div>

        <div className="space-y-4">
          {FAQS.map((faq, index) => {
            const isOpen = openIndex === index;
            return (
              <div
                key={index}
                className="rounded-xl border border-border bg-white overflow-hidden transition-all shadow-subtle"
              >
                <button
                  type="button"
                  onClick={() => toggle(index)}
                  className="w-full flex items-center justify-between p-5 text-left font-semibold text-text-main hover:bg-slate-50 transition-colors"
                >
                  <span className="text-base">{faq.question}</span>
                  <ChevronDown
                    className={`h-5 w-5 text-text-secondary shrink-0 transition-transform duration-200 ${
                      isOpen ? "rotate-180 text-primary-600" : ""
                    }`}
                  />
                </button>
                {isOpen && (
                  <div className="px-5 pb-5 pt-1 text-sm text-text-secondary leading-relaxed border-t border-border/50">
                    {faq.answer}
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
