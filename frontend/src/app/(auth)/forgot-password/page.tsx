"use client";

import * as React from "react";
import Link from "next/link";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { ArrowLeft, ArrowRight, CheckCircle2, Loader2, Mail } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";

const forgotSchema = z.object({
  email: z.string().email({ message: "Please enter a valid work email address" }),
});

type ForgotFormValues = z.infer<typeof forgotSchema>;

export default function ForgotPasswordPage() {
  const [isSubmitted, setIsSubmitted] = React.useState(false);

  const {
    register,
    handleSubmit,
    getValues,
    formState: { errors, isSubmitting },
  } = useForm<ForgotFormValues>({
    resolver: zodResolver(forgotSchema),
    defaultValues: { email: "" },
  });

  const onSubmit = async () => {
    // Simulated mock API call
    await new Promise((resolve) => setTimeout(resolve, 800));
    setIsSubmitted(true);
  };

  return (
    <Card className="border-border shadow-card bg-white">
      <CardHeader className="space-y-1.5 p-6 sm:p-8 pb-4">
        <CardTitle className="text-2xl font-bold tracking-tight text-text-main">
          Reset password
        </CardTitle>
        <CardDescription className="text-sm text-text-secondary">
          Enter your email and we&apos;ll send instructions to reset your password.
        </CardDescription>
      </CardHeader>

      <CardContent className="p-6 sm:p-8 pt-2">
        {isSubmitted ? (
          <div className="space-y-4 text-center py-4">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success-50 text-success-600 border border-success-100">
              <CheckCircle2 className="h-6 w-6" />
            </div>
            <h3 className="text-lg font-bold text-text-main">Check your inbox</h3>
            <p className="text-xs text-text-secondary leading-relaxed">
              We have dispatched a password reset link to{" "}
              <strong className="text-text-main">{getValues("email")}</strong>. Please follow the instructions to secure your account.
            </p>
            <div className="pt-2">
              <Link href="/reset-password">
                <Button variant="outline" size="sm" className="w-full">
                  Proceed to Reset Screen (Demo)
                </Button>
              </Link>
            </div>
          </div>
        ) : (
          <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="email">Work Email</Label>
              <div className="relative">
                <Mail className="absolute left-3 top-2.5 h-4 w-4 text-text-muted" />
                <Input
                  id="email"
                  type="email"
                  placeholder="name@company.com"
                  className="pl-9"
                  {...register("email")}
                />
              </div>
              {errors.email && (
                <p className="text-xs text-error">{errors.email.message}</p>
              )}
            </div>

            <Button
              type="submit"
              className="w-full h-11 text-sm font-semibold mt-2 shadow-sm"
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Sending link...
                </>
              ) : (
                <>
                  Send Reset Link
                  <ArrowRight className="ml-2 h-4 w-4" />
                </>
              )}
            </Button>
          </form>
        )}

        <div className="mt-6 text-center text-xs text-text-secondary border-t border-border pt-4">
          <Link
            href="/login"
            className="inline-flex items-center gap-1 font-semibold text-primary-600 hover:underline"
          >
            <ArrowLeft className="h-3.5 w-3.5" />
            Back to login
          </Link>
        </div>
      </CardContent>
    </Card>
  );
}
