"use client";

import * as React from "react";
import Link from "next/link";
import { motion } from "framer-motion";
import {
  ArrowRight,
  ShieldCheck,
  Sparkles,
  FileSearch,
  CheckCircle2,
  Lock,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { ComparisonIllustration } from "./comparison-illustration";

export function LandingHero() {
  return (
    <section className="relative overflow-hidden pt-12 pb-20 md:pt-16 md:pb-28">
      {/* Background soft glow orbs */}
      <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[350px] bg-primary-100/35 rounded-full blur-3xl pointer-events-none -z-10" />
      <div className="absolute top-1/3 right-10 w-[300px] h-[250px] bg-ai-light/40 rounded-full blur-3xl pointer-events-none -z-10" />

      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-3xl mx-auto space-y-6">
          {/* Eyebrow Badge */}
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-3.5 py-1 text-xs font-semibold text-primary-700 shadow-subtle"
          >
            <Sparkles className="h-3.5 w-3.5 text-primary-600" />
            <span>Next-Generation Document Intelligence</span>
            <span className="h-1 w-1 rounded-full bg-primary-400" />
            <span className="text-text-secondary font-normal">SOC2 & Enterprise Ready</span>
          </motion.div>

          {/* Headline */}
          <motion.h1
            initial={{ opacity: 0, y: 15 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.1 }}
            className="text-4xl font-extrabold tracking-tight text-text-main sm:text-5xl lg:text-6xl"
          >
            Verify Documents.{" "}
            <span className="bg-gradient-to-r from-primary-600 via-primary-500 to-ai-600 bg-clip-text text-transparent">
              Discover Every Difference.
            </span>
          </motion.h1>

          {/* Subtitle */}
          <motion.p
            initial={{ opacity: 0, y: 15 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.2 }}
            className="text-lg text-text-secondary sm:text-xl font-normal leading-relaxed"
          >
            Compare massive documents, detect discrepancies and get evidence-backed answers with AI.
          </motion.p>

          {/* CTAs */}
          <motion.div
            initial={{ opacity: 0, y: 15 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.3 }}
            className="flex flex-col sm:flex-row items-center justify-center gap-4 pt-2"
          >
            <Link href="/dashboard/compare">
              <Button size="lg" className="h-12 px-7 text-base shadow-elevated gap-2">
                Start Comparing
                <ArrowRight className="h-4 w-4" />
              </Button>
            </Link>
            <Link href="#features">
              <Button variant="outline" size="lg" className="h-12 px-7 text-base">
                Explore Features
              </Button>
            </Link>
          </motion.div>

          {/* Trust points */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.6, delay: 0.4 }}
            className="flex flex-wrap items-center justify-center gap-6 pt-4 text-xs text-text-secondary font-medium"
          >
            <div className="flex items-center gap-1.5">
              <CheckCircle2 className="h-4 w-4 text-emerald-600" />
              <span>Multi-format support (PDF, DOCX, XLSX, TXT)</span>
            </div>
            <div className="flex items-center gap-1.5">
              <ShieldCheck className="h-4 w-4 text-primary-600" />
              <span>Exact line & page evidence citations</span>
            </div>
            <div className="flex items-center gap-1.5">
              <Lock className="h-4 w-4 text-ai-600" />
              <span>Zero model retention by default</span>
            </div>
          </motion.div>
        </div>

        {/* Streamlined Simple Action Card */}
        <div className="mt-12 max-w-2xl mx-auto">
          <div className="rounded-2xl border border-border bg-white p-6 shadow-subtle text-center space-y-4">
            <div className="inline-flex items-center justify-center h-12 w-12 rounded-xl bg-primary-50 text-primary-600">
              <FileSearch className="h-6 w-6" />
            </div>
            <h3 className="text-lg font-bold text-text-main">
              Instant AI Document Verification
            </h3>
            <p className="text-xs text-text-secondary max-w-md mx-auto">
              Upload two documents to detect clause modifications, missing sections, and numerical discrepancies with evidence citations.
            </p>
            <div className="pt-2">
              <Link href="/dashboard/compare">
                <Button size="lg" className="h-11 px-6 shadow-sm gap-2">
                  Launch Compare Workspace
                  <ArrowRight className="h-4 w-4" />
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
