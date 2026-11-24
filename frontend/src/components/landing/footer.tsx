"use client";

import * as React from "react";
import Link from "next/link";
import { ShieldCheck, Github, Twitter, Linkedin } from "lucide-react";

export function LandingFooter() {
  return (
    <footer className="border-t border-border bg-white text-text-secondary text-sm">
      <div className="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-8 mb-12">
          {/* Brand Col */}
          <div className="col-span-2 space-y-4">
            <Link href="/" className="flex items-center space-x-2.5">
              <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-primary-600 to-ai-600 text-white shadow-sm">
                <ShieldCheck className="h-4 w-4" />
              </div>
              <span className="text-lg font-bold tracking-tight text-text-main">
                Doc<span className="text-primary-600">Ver</span>
              </span>
            </Link>
            <p className="text-xs text-text-secondary max-w-sm leading-relaxed">
              DocVer is an AI-powered document comparison and verification platform. Compare massive documents, detect discrepancies, and extract evidence-backed answers.
            </p>
            <div className="flex items-center space-x-4 pt-1">
              <a
                href="#"
                className="text-text-secondary hover:text-text-main transition-colors"
                aria-label="Twitter"
              >
                <Twitter className="h-4 w-4" />
              </a>
              <a
                href="#"
                className="text-text-secondary hover:text-text-main transition-colors"
                aria-label="LinkedIn"
              >
                <Linkedin className="h-4 w-4" />
              </a>
              <a
                href="#"
                className="text-text-secondary hover:text-text-main transition-colors"
                aria-label="GitHub"
              >
                <Github className="h-4 w-4" />
              </a>
            </div>
          </div>

          {/* Product links */}
          <div className="space-y-3">
            <p className="text-xs font-bold uppercase tracking-wider text-text-main">
              Product
            </p>
            <ul className="space-y-2 text-xs">
              <li>
                <Link href="#features" className="hover:text-text-main">
                  Semantic Diff
                </Link>
              </li>
              <li>
                <Link href="#features" className="hover:text-text-main">
                  Risk Scoring
                </Link>
              </li>
              <li>
                <Link href="/dashboard/compare" className="hover:text-text-main font-semibold text-primary-600">
                  Comparison Workspace
                </Link>
              </li>
            </ul>
          </div>

          {/* Solutions links */}
          <div className="space-y-3">
            <p className="text-xs font-bold uppercase tracking-wider text-text-main">
              Capabilities
            </p>
            <ul className="space-y-2 text-xs">
              <li>
                <span className="text-text-secondary">Legal Due Diligence</span>
              </li>
              <li>
                <span className="text-text-secondary">Procurement & Vendor SLAs</span>
              </li>
              <li>
                <span className="text-text-secondary">Discrepancy Audits</span>
              </li>
              <li>
                <span className="text-text-secondary">Evidence Extraction</span>
              </li>
            </ul>
          </div>
        </div>

        <div className="border-t border-border pt-6 flex flex-col sm:flex-row items-center justify-between text-xs text-text-muted">
          <p>© {new Date().getFullYear()} DocVer Technologies Inc. All rights reserved.</p>
          <p className="mt-2 sm:mt-0">
            AI-Powered Document Verification Platform
          </p>
        </div>
      </div>
    </footer>
  );
}
