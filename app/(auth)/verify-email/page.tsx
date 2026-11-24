"use client";

import * as React from "react";
import Link from "next/link";
import { MailCheck, ArrowRight, RefreshCw } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";

export default function VerifyEmailPage() {
  const [resending, setResending] = React.useState(false);
  const [resent, setResent] = React.useState(false);

  const handleResend = async () => {
    setResending(true);
    await new Promise((resolve) => setTimeout(resolve, 800));
    setResending(false);
    setResent(true);
    setTimeout(() => setResent(false), 4000);
  };

  return (
    <Card className="border-border shadow-card bg-white">
      <CardHeader className="space-y-1.5 p-6 sm:p-8 pb-4 text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 border border-primary-100 mb-2">
          <MailCheck className="h-7 w-7" />
        </div>
        <CardTitle className="text-2xl font-bold tracking-tight text-text-main">
          Verify your email
        </CardTitle>
        <CardDescription className="text-sm text-text-secondary max-w-sm mx-auto">
          We&apos;ve sent an email verification link to confirm your corporate domain.
        </CardDescription>
      </CardHeader>

      <CardContent className="p-6 sm:p-8 pt-2 space-y-5">
        <div className="rounded-xl bg-slate-50 border border-border p-4 text-xs text-text-secondary space-y-2">
          <p className="font-semibold text-text-main">Next steps:</p>
          <ol className="list-decimal pl-4 space-y-1 leading-relaxed">
            <li>Open the verification email sent from <code>verify@docver.com</code>.</li>
            <li>Click the secure <strong>Verify Organization Email</strong> button.</li>
            <li>Your DocVer workspace will be activated with enterprise privileges.</li>
          </ol>
        </div>

        {resent && (
          <div className="rounded-lg bg-success-50 p-3 text-xs text-success-700 border border-success-100 text-center font-medium">
            A new verification email has been dispatched.
          </div>
        )}

        <div className="flex flex-col gap-2.5">
          <Link href="/dashboard">
            <Button className="w-full h-11 text-sm font-semibold shadow-sm">
              Enter Workspace Directly (Demo)
              <ArrowRight className="ml-2 h-4 w-4" />
            </Button>
          </Link>

          <Button
            variant="outline"
            onClick={handleResend}
            disabled={resending}
            className="w-full text-xs font-medium"
          >
            <RefreshCw
              className={`mr-2 h-3.5 w-3.5 ${resending ? "animate-spin" : ""}`}
            />
            {resending ? "Resending..." : "Didn't receive email? Resend"}
          </Button>
        </div>

        <div className="text-center text-xs text-text-secondary border-t border-border pt-4">
          Need assistance or enterprise domain whitelisting?{" "}
          <a href="mailto:support@docver.com" className="font-semibold text-primary-600 hover:underline">
            Contact Support
          </a>
        </div>
      </CardContent>
    </Card>
  );
}
