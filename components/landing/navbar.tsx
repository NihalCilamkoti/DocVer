"use client";

import * as React from "react";
import Link from "next/link";
import { ShieldCheck, ArrowRight, Menu, X, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/button";

export function LandingNavbar() {
  const [mobileMenuOpen, setMobileMenuOpen] = React.useState(false);

  return (
    <header className="sticky top-0 z-40 w-full border-b border-border/70 bg-white/90 backdrop-blur-md">
      <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <Link href="/" className="flex items-center space-x-2.5 group">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-primary-600 to-ai-600 text-white shadow-sm transition-transform group-hover:scale-105">
            <ShieldCheck className="h-5 w-5" />
          </div>
          <div className="flex flex-col">
            <span className="text-xl font-bold tracking-tight text-text-main">
              Doc<span className="text-primary-600">Ver</span>
            </span>
            <span className="text-[10px] font-medium tracking-wide uppercase text-text-secondary -mt-1">
              AI Verification
            </span>
          </div>
        </Link>

        {/* Desktop Navigation */}
        <nav className="hidden md:flex items-center space-x-8 text-sm font-medium text-text-secondary">
          <Link
            href="#features"
            className="transition-colors hover:text-text-main"
          >
            Features
          </Link>
          <Link
            href="/dashboard/compare"
            className="transition-colors hover:text-primary-600 font-semibold"
          >
            Compare Documents
          </Link>
        </nav>

        {/* Desktop CTA */}
        <div className="hidden md:flex items-center space-x-3">
          <Link href="/dashboard/compare">
            <Button size="sm" className="shadow-sm">
              <Sparkles className="mr-1.5 h-3.5 w-3.5 text-ai-muted" />
              Compare Now
            </Button>
          </Link>
        </div>

        {/* Mobile menu button */}
        <div className="flex md:hidden">
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
            aria-label="Toggle menu"
          >
            {mobileMenuOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
          </Button>
        </div>
      </div>

      {/* Mobile Menu Dropdown */}
      {mobileMenuOpen && (
        <div className="md:hidden border-b border-border bg-white px-4 pt-2 pb-6 space-y-3 shadow-lg animate-in slide-in-from-top-2">
          <div className="flex flex-col space-y-2.5 pt-2 text-base font-medium text-text-main">
            <Link
              href="#features"
              onClick={() => setMobileMenuOpen(false)}
              className="px-2 py-1.5 rounded-md hover:bg-slate-50"
            >
              Features
            </Link>
            <Link
              href="/dashboard/compare"
              onClick={() => setMobileMenuOpen(false)}
              className="px-2 py-1.5 rounded-md hover:bg-slate-50 text-primary-600 font-semibold"
            >
              Compare Documents
            </Link>
          </div>
          <div className="pt-2 border-t border-border flex flex-col space-y-2">
            <Link href="/dashboard/compare" onClick={() => setMobileMenuOpen(false)}>
              <Button className="w-full justify-center">
                <Sparkles className="mr-1.5 h-4 w-4 text-ai-muted" />
                Compare Now
              </Button>
            </Link>
          </div>
        </div>
      )}
    </header>
  );
}
