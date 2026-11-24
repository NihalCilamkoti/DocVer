"use client";

import * as React from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import * as z from "zod";
import { ArrowRight, Loader2, Lock, Mail, Building, User, AlertCircle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { useAuth } from "@/components/auth-provider";

const registerSchema = z
  .object({
    name: z.string().min(2, { message: "Name must be at least 2 characters" }),
    organization: z.string().min(2, { message: "Organization is required" }),
    email: z.string().email({ message: "Please enter a valid work email" }),
    password: z.string().min(8, { message: "Password must be at least 8 characters" }),
    confirmPassword: z.string(),
    agreeToTerms: z.boolean().refine((val) => val === true, {
      message: "You must accept the terms of service and privacy policy",
    }),
  })
  .refine((data) => data.password === data.confirmPassword, {
    message: "Passwords do not match",
    path: ["confirmPassword"],
  });

type RegisterFormValues = z.infer<typeof registerSchema>;

export default function RegisterPage() {
  const router = useRouter();
  const { register: registerAuth } = useAuth();
  const [serverError, setServerError] = React.useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      name: "",
      organization: "",
      email: "",
      password: "",
      confirmPassword: "",
      agreeToTerms: false,
    },
  });

  const onSubmit = async (data: RegisterFormValues) => {
    setServerError(null);
    try {
      await registerAuth({
        name: data.name,
        email: data.email,
        organization: data.organization,
        password: data.password,
      });
      router.push("/verify-email?email=" + encodeURIComponent(data.email));
    } catch (err: unknown) {
      const error = err as Error & { errors?: Record<string, string[]> };
      if (error.errors) {
        Object.entries(error.errors).forEach(([field, messages]) => {
          if (["name", "email", "password", "organization"].includes(field)) {
            setError(field as keyof RegisterFormValues, { message: messages[0] });
          }
        });
      }
      setServerError(error.message || "Failed to register workspace. Please verify details.");
    }
  };

  return (
    <Card className="border-border shadow-card bg-white">
      <CardHeader className="space-y-1.5 p-6 sm:p-8 pb-4">
        <div className="flex items-center justify-between">
          <CardTitle className="text-2xl font-bold tracking-tight text-text-main">
            Create an account
          </CardTitle>
          <span className="text-[11px] font-mono px-2 py-0.5 rounded bg-blue-50 text-primary-700 font-semibold border border-blue-100">
            Sanctum Auth
          </span>
        </div>
        <CardDescription className="text-sm text-text-secondary">
          Start verifying contracts and documents with high-precision AI
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
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="name">Full Name</Label>
              <div className="relative">
                <User className="absolute left-3 top-2.5 h-4 w-4 text-text-muted" />
                <Input
                  id="name"
                  placeholder="Eleanor Vance"
                  className="pl-9"
                  {...register("name")}
                />
              </div>
              {errors.name && (
                <p className="text-xs text-error">{errors.name.message}</p>
              )}
            </div>

            <div className="space-y-2">
              <Label htmlFor="organization">Organization</Label>
              <div className="relative">
                <Building className="absolute left-3 top-2.5 h-4 w-4 text-text-muted" />
                <Input
                  id="organization"
                  placeholder="Apex Global LLP"
                  className="pl-9"
                  {...register("organization")}
                />
              </div>
              {errors.organization && (
                <p className="text-xs text-error">{errors.organization.message}</p>
              )}
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="email">Work Email</Label>
            <div className="relative">
              <Mail className="absolute left-3 top-2.5 h-4 w-4 text-text-muted" />
              <Input
                id="email"
                type="email"
                placeholder="eleanor@apex.com"
                className="pl-9"
                {...register("email")}
              />
            </div>
            {errors.email && (
              <p className="text-xs text-error">{errors.email.message}</p>
            )}
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="space-y-2">
              <Label htmlFor="password">Password</Label>
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

            <div className="space-y-2">
              <Label htmlFor="confirmPassword">Confirm Password</Label>
              <div className="relative">
                <Lock className="absolute left-3 top-2.5 h-4 w-4 text-text-muted" />
                <Input
                  id="confirmPassword"
                  type="password"
                  placeholder="••••••••"
                  className="pl-9"
                  {...register("confirmPassword")}
                />
              </div>
              {errors.confirmPassword && (
                <p className="text-xs text-error">
                  {errors.confirmPassword.message}
                </p>
              )}
            </div>
          </div>

          <div className="flex items-start space-x-2 pt-2">
            <input
              type="checkbox"
              id="agreeToTerms"
              className="h-4 w-4 rounded border-border text-primary-600 focus:ring-primary-500 mt-0.5"
              {...register("agreeToTerms")}
            />
            <Label
              htmlFor="agreeToTerms"
              className="text-xs font-normal text-text-secondary cursor-pointer leading-tight"
            >
              I agree to the Terms of Service, Privacy Policy, and Zero Model Retention Agreement.
            </Label>
          </div>
          {errors.agreeToTerms && (
            <p className="text-xs text-error">{errors.agreeToTerms.message}</p>
          )}

          <Button
            type="submit"
            className="w-full h-11 text-sm font-semibold mt-2 shadow-sm"
            disabled={isSubmitting}
          >
            {isSubmitting ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Creating Workspace...
              </>
            ) : (
              <>
                Create Enterprise Account
                <ArrowRight className="ml-2 h-4 w-4" />
              </>
            )}
          </Button>
        </form>

        <div className="mt-6 text-center text-xs text-text-secondary border-t border-border pt-4">
          Already have an account?{" "}
          <Link href="/login" className="font-semibold text-primary-600 hover:underline">
            Sign in
          </Link>
        </div>
      </CardContent>
    </Card>
  );
}
