"use client";

import * as React from "react";
import { usePathname } from "next/navigation";
import { ChatLayout } from "@/components/layout/chat-layout";

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <ChatLayout>
      <main className="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8 min-h-0">
        {children}
      </main>
    </ChatLayout>
  );
}
