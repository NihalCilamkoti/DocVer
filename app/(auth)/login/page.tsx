"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { ArrowRight, Loader2, Lock, Mail, AlertCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { useAuth } from "@/components/auth-provider";

const loginSchema = z.object({
  email: z.string().email({ message: "Please enter a valid email address" }),
  password: z.string().min(6, { message: "Password must be at least 6 characters" }),
  rememberMe: z.boolean().optional(),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export default function LoginPage() {
  const router = useRouter();
  const { login } = useAuth();
  const [serverError, setServerError] = React.useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: "alex.sterling@enterprise-legal.com",
      password: "password123",
      rememberMe: true,
    },
  });

  const onSubmit = async (data: LoginFormValues) => {
    setServerError(null);
    try {
      await login({
        email: data.email,
        password: data.password,
        remember: data.rememberMe,
      });
      router.push("/dashboard");
    } catch (err: unknown) {
      const error = err as Error & { errors?: Record<string, string[]> };
      if (error.errors) {
        Object.entries(error.errors).forEach(([field, messages]) => {
          if (field === "email" || field === "password") {
            setError(field, { message: messages[0] });
          }
        });
      }
      setServerError(
        error.message || "Invalid credentials. Please verify your email and password."
      );
    }
  };

  return (
    <Card className="border-border shadow-card bg-white">
      <CardHeader className="space-y-1.5 p-6 sm:p-8 pb-4">
        <div className="flex items-center justify-between">
          <CardTitle className="text-2xl font-bold tracking-tight text-text-main">
            Welcome back
          </CardTitle>
          <span className="text-[11px] font-mono px-2 py-0.5 rounded bg-blue-50 text-primary-700 font-semibold border border-blue-100">
            Sanctum Auth
          </span>
        </div>
        <CardDescription className="text-sm text-text-secondary">
          Enter your credentials to access your document verification workspace
        </CardDescription>
      </CardHeader>

      <CardContent className="p-6 sm:p-8 pt-2">
        {serverError && (
          <div className="mb-4 flex items-center gap-2 rounded-lg bg-error-50 p-3 text-xs text-error border border-error-100">
            <AlertCircle className="h-4 w-4 shrink-0" />
            <span>{serverError}</span>
          </div>
        )}

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

          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <Label htmlFor="password">Password</Label>
              <Link
                href="/forgot-password"
                className="text-xs font-medium text-primary-600 hover:underline"
              >
                Forgot password?
              </Link>
            </div>
            <div className="relative">
              <Lock className="absolute left-3 top-2.5 h-4 w-4 text-text-muted" />
              <Input
                id="password"
                type="password"
                placeholder="••••••••"
                className="pl-9"
                {...register("password")}
              />
            </div>
            {errors.password && (
              <p className="text-xs text-error">{errors.password.message}</p>
            )}
          </div>

          <div className="flex items-center space-x-2 pt-1">
            <input
              type="checkbox"
              id="rememberMe"
              className="h-4 w-4 rounded border-border text-primary-600 focus:ring-primary-500"
              {...register("rememberMe")}
            />
            <Label htmlFor="rememberMe" className="text-xs font-normal text-text-secondary cursor-pointer">
              Remember this device for 30 days
            </Label>
          </div>

          <Button
            type="submit"
            className="w-full h-11 text-sm font-semibold mt-2 shadow-sm"
            disabled={isSubmitting}
          >
            {isSubmitting ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Signing in...
              </>
            ) : (
              <>
                Sign In to DocVer
                <ArrowRight className="ml-2 h-4 w-4" />
              </>
            )}
          </Button>
        </form>

        <div className="mt-6 text-center text-xs text-text-secondary border-t border-border pt-4">
          Don&apos;t have an enterprise account?{" "}
          <Link href="/register" className="font-semibold text-primary-600 hover:underline">
            Register here
          </Link>
        </div>
      </CardContent>
    </Card>
  );
}
