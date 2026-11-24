"use client";

import * as React from "react";
import { useComparisonStore } from "@/stores/comparison-store";
import { AppSidebar } from "./app-sidebar";
import { MobileNavigation } from "./mobile-navigation";
import { ToastBanner } from "@/components/ui/toast";

interface ChatLayoutProps {
  children: React.ReactNode;
}

export function ChatLayout({ children }: ChatLayoutProps) {
  const {
    sidebarCollapsed,
    toggleSidebar,
    toasts,
    dismissToast,
  } = useComparisonStore();

  const [mobileSidebarOpen, setMobileSidebarOpen] = React.useState(false);
  const [mobileContextOpen, setMobileContextOpen] = React.useState(false);

  return (
    <div className="flex h-screen w-screen overflow-hidden bg-background">
      {/* Desktop Left Sidebar (Collapsible) */}
      <div className="hidden md:flex md:shrink-0">
        <AppSidebar
          collapsed={sidebarCollapsed}
          onToggleCollapse={toggleSidebar}
        />
      </div>

      {/* Mobile Overlays */}
      <MobileNavigation
        sidebarOpen={mobileSidebarOpen}
        onCloseSidebar={() => setMobileSidebarOpen(false)}
        contextOpen={mobileContextOpen}
        onCloseContext={() => setMobileContextOpen(false)}
      />

      {/* Main Content Area */}
      <div className="flex flex-1 flex-col min-w-0 overflow-hidden">
        {children}
      </div>

      {/* Global Toast Notifications Stack */}
      {toasts.length > 0 && (
        <div
          className="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-auto"
          aria-live="polite"
        >
          {toasts.map((t) => (
            <ToastBanner key={t.id} toast={t} onDismiss={dismissToast} />
          ))}
        </div>
      )}
    </div>
  );
}
