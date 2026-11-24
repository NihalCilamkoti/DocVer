import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import { Providers } from "@/components/providers";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-sans",
  display: "swap",
});

export const metadata: Metadata = {
  title: "DocVer — AI Document Comparison & Verification SaaS",
  description:
    "Verify documents, detect critical discrepancies, compare clauses, and get evidence-backed answers with AI.",
  keywords: [
    "document comparison",
    "contract diff",
    "AI verification",
    "clause comparison",
    "legal tech",
    "due diligence",
  ],
  icons: {
    icon: "/icon.svg",
  },
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="en" className={inter.variable}>
      <body className="min-h-screen bg-background font-sans antialiased text-text-main">
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
