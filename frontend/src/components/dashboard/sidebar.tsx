"use client";

import * as React from "react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  LayoutDashboard,
  FilePlus2,
  History,
  BarChart3,
  Settings,
  HelpCircle,
  ShieldCheck,
  Sparkles,
} from "lucide-react";
import { cn } from "@/lib/utils";

interface SidebarProps {
  onCloseMobile?: () => void;
}

export function DashboardSidebar({ onCloseMobile }: SidebarProps) {
  const pathname = usePathname();

  const navItems = [
    {
      title: "Overview",
      href: "/dashboard",
      icon: LayoutDashboard,
      active: pathname === "/dashboard",
    },
    {
      title: "New comparison",
      href: "/dashboard/compare",
      icon: FilePlus2,
      active: pathname === "/dashboard/compare",
      highlight: true,
    },
    {
      title: "Recent comparisons",
      href: "/dashboard/comparisons",
      icon: History,
      active: pathname.startsWith("/dashboard/comparisons"),
    },
    {
      title: "Usage",
      href: "/dashboard/usage",
      icon: BarChart3,
      active: pathname.startsWith("/dashboard/usage"),
    },
    {
      title: "Settings",
      href: "/dashboard/settings",
      icon: Settings,
      active: pathname.startsWith("/dashboard/settings"),
    },
    {
      title: "AI Evaluation",
      href: "/dashboard/admin/ai-evaluation",
      icon: Sparkles,
      active: pathname.startsWith("/dashboard/admin/ai-evaluation") || pathname.startsWith("/admin/ai-evaluation"),
      highlight: true,
    },
    {
      title: "Help",
      href: "/dashboard/help",
      icon: HelpCircle,
      active: pathname.startsWith("/dashboard/help"),
    },
  ];

  return (
    <aside className="flex h-full w-64 flex-col border-r border-border bg-white">
      {/* Brand Header */}
      <div className="flex h-16 items-center px-6 border-b border-border justify-between">
        <Link
          href="/dashboard"
          className="flex items-center space-x-2.5 group"
          onClick={onCloseMobile}
        >
          <div className="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-primary-600 to-ai-600 text-white shadow-sm">
            <ShieldCheck className="h-5 w-5" />
          </div>
          <span className="text-lg font-bold tracking-tight text-text-main">
            Doc<span className="text-primary-600">Ver</span>
          </span>
        </Link>
        <span className="text-[10px] font-semibold uppercase tracking-wider bg-slate-100 text-text-secondary px-2 py-0.5 rounded-full">
          v1.0
        </span>
      </div>

      {/* Navigation Links */}
      <div className="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        <div className="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-text-muted">
          Platform
        </div>

        {navItems.map((item) => (
          <Link
            key={item.href}
            href={item.href}
            onClick={onCloseMobile}
            className={cn(
              "group flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium transition-colors",
              item.active
                ? "bg-primary-50 text-primary-700 font-semibold"
                : "text-text-secondary hover:bg-slate-50 hover:text-text-main",
              item.highlight && !item.active && "text-primary-600 bg-primary-50/40"
            )}
          >
            <div className="flex items-center gap-3">
              <item.icon
                className={cn(
                  "h-4 w-4 shrink-0 transition-colors",
                  item.active
                    ? "text-primary-600"
                    : "text-text-secondary group-hover:text-text-main",
                  item.highlight && "text-primary-600"
                )}
              />
              <span>{item.title}</span>
            </div>
            {item.highlight && (
              <Sparkles className="h-3.5 w-3.5 text-primary-600 shrink-0" />
            )}
          </Link>
        ))}
      </div>
    </aside>
  );
}
