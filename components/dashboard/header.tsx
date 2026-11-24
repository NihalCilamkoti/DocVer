"use client";

import * as React from "react";
import Link from "next/link";
import {
  Menu,
  FilePlus2,
  Building2,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/components/auth-provider";

interface HeaderProps {
  onToggleSidebar?: () => void;
}

export function DashboardHeader({ onToggleSidebar }: HeaderProps) {
  const { user } = useAuth();
  const initials = user?.name ? user.name.slice(0, 2).toUpperCase() : "DV";
  const displayName = user?.name || "DocVer Workspace";
  const workspaceName = user?.workspaces?.[0]?.name || "Verification Workspace";

  return (
    <header className="sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-border bg-white/95 px-4 sm:px-6 backdrop-blur-sm">
      <div className="flex items-center space-x-3">
        <Button
          variant="ghost"
          size="icon"
          onClick={onToggleSidebar}
          className="md:hidden"
          aria-label="Toggle navigation"
        >
          <Menu className="h-5 w-5 text-text-secondary" />
        </Button>

        <div className="flex items-center space-x-2">
          <div className="flex items-center space-x-1.5 text-xs text-text-secondary">
            <Building2 className="h-4 w-4 text-text-muted" />
            <span className="font-semibold text-text-main">{workspaceName}</span>
          </div>
        </div>
      </div>

      <div className="flex items-center space-x-3">
        <Link href="/dashboard/compare">
          <Button size="sm" className="gap-1.5 shadow-sm text-xs font-semibold">
            <FilePlus2 className="h-4 w-4" />
            <span className="hidden sm:inline">New Comparison</span>
          </Button>
        </Link>

        {/* User avatar pill */}
        <div className="flex items-center space-x-2 pl-2 border-l border-border">
          <div className="h-8 w-8 rounded-full bg-gradient-to-tr from-primary-600 to-ai-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
            {initials}
          </div>
          <div className="hidden lg:flex flex-col text-left">
            <span className="text-xs font-bold text-text-main leading-none">
              {displayName}
            </span>
            {user?.email && (
              <span className="text-[10px] text-text-secondary mt-0.5 truncate max-w-[140px]">
                {user.email}
              </span>
            )}
          </div>
        </div>
      </div>
    </header>
  );
}
