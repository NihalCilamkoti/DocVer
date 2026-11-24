import * as React from "react";
import Link from "next/link";
import { ShieldCheck, CheckCircle2, Lock } from "lucide-react";

export default function AuthLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="min-h-screen grid grid-cols-1 lg:grid-cols-12 bg-background">
      {/* Left panel: Branding / Testimonial Showcase */}
      <div className="hidden lg:flex lg:col-span-5 bg-gradient-to-br from-primary-700 via-primary-600 to-ai-700 p-12 flex-col justify-between text-white relative overflow-hidden">
        {/* Decorative blur backdrop circles */}
        <div className="absolute top-10 right-10 w-64 h-64 bg-white/10 rounded-full blur-3xl pointer-events-none" />
        <div className="absolute bottom-10 left-10 w-80 h-80 bg-ai-500/20 rounded-full blur-3xl pointer-events-none" />

        <div className="relative z-10">
          <Link href="/" className="inline-flex items-center space-x-2.5">
            <div className="h-10 w-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-sm">
              <ShieldCheck className="h-6 w-6" />
            </div>
            <span className="text-2xl font-bold tracking-tight text-white">
              Doc<span className="text-blue-200">Ver</span>
            </span>
          </Link>

          <div className="mt-16 space-y-4">
            <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-xs font-semibold text-white border border-white/20">
              <Lock className="h-3 w-3" />
              SOC 2 Type II Certified Pipeline
            </div>
            <h2 className="text-3xl font-extrabold leading-tight">
              Enterprise Document Verification and Cross-Inspection.
            </h2>
            <p className="text-blue-100 text-sm leading-relaxed">
              Trusted by corporate counsels, compliance executives, and procurement teams auditing multi-million dollar agreements.
            </p>
          </div>
        </div>

        <div className="relative z-10 space-y-4">
          <div className="space-y-2 border-t border-white/20 pt-6">
            <div className="flex items-center gap-2 text-xs font-medium text-blue-100">
              <CheckCircle2 className="h-4 w-4 text-emerald-300" />
              <span>Full semantic diff & risk scoring</span>
            </div>
            <div className="flex items-center gap-2 text-xs font-medium text-blue-100">
              <CheckCircle2 className="h-4 w-4 text-emerald-300" />
              <span>Verbatim evidence snippets & page references</span>
            </div>
            <div className="flex items-center gap-2 text-xs font-medium text-blue-100">
              <CheckCircle2 className="h-4 w-4 text-emerald-300" />
              <span>Zero model retention & end-to-end encryption</span>
            </div>
          </div>
          <p className="text-[11px] text-blue-200/80">
            Phase 1 Frontend Architecture • Ready for Laravel Authentication API
          </p>
        </div>
      </div>

      {/* Right panel: Content / Auth Form */}
      <div className="lg:col-span-7 flex flex-col justify-center items-center p-6 sm:p-12">
        <div className="w-full max-w-md">
          <div className="lg:hidden mb-8 text-center">
            <Link href="/" className="inline-flex items-center space-x-2">
              <div className="h-9 w-9 rounded-xl bg-primary-600 flex items-center justify-center text-white">
                <ShieldCheck className="h-5 w-5" />
              </div>
              <span className="text-xl font-bold tracking-tight text-text-main">
                Doc<span className="text-primary-600">Ver</span>
              </span>
            </Link>
          </div>
          {children}
        </div>
      </div>
    </div>
  );
}
