"use client";

import * as React from "react";
import { ChevronDown, ChevronUp } from "lucide-react";
import { ChatCitation } from "@/types/chat";
import { SourceCitation } from "./source-citation";
import { cn } from "@/lib/utils";

interface AnalysisResultProps {
  content: string;
  citations?: ChatCitation[];
  onCitationClick?: (citation: ChatCitation) => void;
  className?: string;
}

function renderInlineFormatting(text: string): React.ReactNode {
  // Handle inline code `...` and bold **...**
  const tokens = text.split(/(`[^`]+`|\*\*[^*]+\*\*)/g);
  return tokens.map((token, idx) => {
    if (token.startsWith("`") && token.endsWith("`") && token.length > 2) {
      return (
        <code
          key={idx}
          className="rounded bg-slate-100 border border-slate-200/80 px-1.5 py-0.5 font-mono text-[11px] text-slate-800"
        >
          {token.slice(1, -1)}
        </code>
      );
    }
    if (token.startsWith("**") && token.endsWith("**") && token.length > 4) {
      return (
        <strong key={idx} className="font-semibold text-slate-900">
          {token.slice(2, -2)}
        </strong>
      );
    }
    return token;
  });
}

function renderBlocks(markdown: string): React.ReactNode[] {
  const lines = markdown.split(/\r?\n/);
  const elements: React.ReactNode[] = [];
  let i = 0;

  while (i < lines.length) {
    const line = lines[i];
    const trimmed = line.trim();

    if (!trimmed) {
      i++;
      continue;
    }

    // Horizontal rule
    if (/^(-{3,}|\*{3,})$/.test(trimmed)) {
      elements.push(<hr key={`hr_${i}`} className="my-3 border-slate-200" />);
      i++;
      continue;
    }

    // Code block ```
    if (trimmed.startsWith("```")) {
      const codeLines: string[] = [];
      i++;
      while (i < lines.length && !lines[i].trim().startsWith("```")) {
        codeLines.push(lines[i]);
        i++;
      }
      i++; // skip closing ```
      elements.push(
        <pre
          key={`code_${i}`}
          className="my-2.5 overflow-x-auto rounded-lg border border-slate-200 bg-slate-900 p-3 text-xs text-slate-100 font-mono leading-relaxed"
        >
          <code>{codeLines.join("\n")}</code>
        </pre>
      );
      continue;
    }

    // Markdown Table
    if (
      trimmed.includes("|") &&
      i + 1 < lines.length &&
      /^\s*\|?[\s-:|]+\|[\s-:|]*$/.test(lines[i + 1])
    ) {
      const parseRow = (rowStr: string) =>
        rowStr
          .trim()
          .replace(/^\|/, "")
          .replace(/\|$/, "")
          .split("|")
          .map((c) => c.trim());

      const headers = parseRow(trimmed);
      i += 2; // skip header and separator
      const rows: string[][] = [];
      while (i < lines.length && lines[i].trim().includes("|")) {
        rows.push(parseRow(lines[i]));
        i++;
      }

      elements.push(
        <div
          key={`tbl_${i}`}
          className="my-3 overflow-x-auto rounded-lg border border-slate-200"
        >
          <table className="w-full text-left text-xs border-collapse">
            <thead className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
              <tr>
                {headers.map((h, hIdx) => (
                  <th key={hIdx} className="px-3 py-2">
                    {renderInlineFormatting(h)}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 bg-white">
              {rows.map((r, rIdx) => (
                <tr key={rIdx} className="hover:bg-slate-50/70">
                  {r.map((cell, cIdx) => (
                    <td key={cIdx} className="px-3 py-2 text-slate-700">
                      {renderInlineFormatting(cell)}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      );
      continue;
    }

    // Headings
    const headingMatch = trimmed.match(/^(#{1,4})\s+(.*)$/);
    if (headingMatch) {
      const level = headingMatch[1].length;
      const text = headingMatch[2];
      elements.push(
        <h3
          key={`h_${i}`}
          className={cn(
            "font-semibold text-slate-900 tracking-tight",
            level === 1 && "text-base mt-4 mb-1.5",
            level === 2 && "text-sm mt-3.5 mb-1.5",
            level >= 3 && "text-xs uppercase tracking-wider text-slate-600 mt-3 mb-1"
          )}
        >
          {renderInlineFormatting(text)}
        </h3>
      );
      i++;
      continue;
    }

    // Blockquote
    if (trimmed.startsWith(">")) {
      const quoteLines: string[] = [];
      while (i < lines.length && lines[i].trim().startsWith(">")) {
        quoteLines.push(lines[i].trim().replace(/^>\s?/, ""));
        i++;
      }
      elements.push(
        <blockquote
          key={`q_${i}`}
          className="my-2.5 border-l-2 border-indigo-400 bg-indigo-50/40 pl-3 py-1.5 text-xs italic text-slate-700 rounded-r-md"
        >
          {renderInlineFormatting(quoteLines.join(" "))}
        </blockquote>
      );
      continue;
    }

    // Unordered list
    if (/^[-*•]\s+/.test(trimmed)) {
      const items: string[] = [];
      while (i < lines.length && /^[-*•]\s+/.test(lines[i].trim())) {
        items.push(lines[i].trim().replace(/^[-*•]\s+/, ""));
        i++;
      }
      elements.push(
        <ul
          key={`ul_${i}`}
          className="my-2 space-y-1.5 pl-4 list-disc marker:text-slate-400 text-[13px] text-slate-700"
        >
          {items.map((item, idx) => (
            <li key={idx} className="leading-relaxed">
              {renderInlineFormatting(item)}
            </li>
          ))}
        </ul>
      );
      continue;
    }

    // Ordered list
    if (/^\d+\.\s+/.test(trimmed)) {
      const items: string[] = [];
      while (i < lines.length && /^\d+\.\s+/.test(lines[i].trim())) {
        items.push(lines[i].trim().replace(/^\d+\.\s+/, ""));
        i++;
      }
      elements.push(
        <ol
          key={`ol_${i}`}
          className="my-2 space-y-1.5 pl-4 list-decimal marker:text-slate-500 marker:font-medium text-[13px] text-slate-700"
        >
          {items.map((item, idx) => (
            <li key={idx} className="leading-relaxed">
              {renderInlineFormatting(item)}
            </li>
          ))}
        </ol>
      );
      continue;
    }

    // Standard Paragraph
    const paraLines: string[] = [trimmed];
    i++;
    while (
      i < lines.length &&
      lines[i].trim() !== "" &&
      !lines[i].trim().startsWith("#") &&
      !lines[i].trim().startsWith("```") &&
      !lines[i].trim().startsWith(">") &&
      !/^[-*•]\s+/.test(lines[i].trim()) &&
      !/^\d+\.\s+/.test(lines[i].trim())
    ) {
      paraLines.push(lines[i].trim());
      i++;
    }

    elements.push(
      <p key={`p_${i}`} className="my-2 text-[13px] text-slate-700 leading-relaxed">
        {renderInlineFormatting(paraLines.join(" "))}
      </p>
    );
  }

  return elements;
}

export function AnalysisResult({
  content,
  citations,
  onCitationClick,
  className,
}: AnalysisResultProps) {
  return (
    <div className={cn("space-y-3", className)}>
      <div className="max-w-none break-words leading-relaxed text-slate-800 text-[13px]">
        {renderBlocks(content)}
      </div>

      {/* Inline Source Citations */}
      {citations && citations.length > 0 && (
        <div className="pt-2 border-t border-slate-100 space-y-1">
          <span className="text-[10px] font-semibold uppercase tracking-wider text-slate-400 block">
            Source Citations
          </span>
          <div className="flex flex-wrap gap-1.5">
            {citations.map((cite, idx) => (
              <SourceCitation
                key={idx}
                citation={cite}
                onSelect={onCitationClick}
              />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
