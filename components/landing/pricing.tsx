"use client";

import * as React from "react";
import Link from "next/link";
import { Check, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardFooter, CardHeader } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";

const TIERS = [
  {
    name: "Starter",
    badge: "Solo Practitioner",
    price: "$49",
    period: "/month",
    description: "Ideal for boutique legal practices and independent contract consultants.",
    features: [
      "50 comparisons / month",
      "Up to 100 pages per document",
      "Semantic clause diff & highlights",
      "Evidence-backed AI chat (500 queries)",
      "Standard email support",
      "Export to PDF & Word redline",
    ],
    cta: "Start Free Trial",
    popular: false,
    buttonVariant: "outline" as const,
  },
  {
    name: "Professional",
    badge: "Most Popular",
    price: "$149",
    period: "/month",
    description: "Designed for mid-market legal teams, corporate counsel, and procurement.",
    features: [
      "250 comparisons / month",
      "Up to 500 pages per document",
      "High-risk clause scoring & warnings",
      "Numerical & financial variance audit",
      "Contradiction detection engine",
      "Unlimited AI conversational queries",
      "Team collaboration (5 seats included)",
      "Priority API & webhook access",
    ],
    cta: "Get Started Now",
    popular: true,
    buttonVariant: "default" as const,
  },
  {
    name: "Enterprise",
    badge: "Maximum Security",
    price: "Custom",
    period: "",
    description: "Dedicated infrastructure, custom SLAs, and governance for large organizations.",
    features: [
      "Unlimited document comparisons",
      "Multi-thousand page document handling",
      "Custom on-premise or VPC deployment",
      "SSO, SAML & Active Directory sync",
      "Customer-managed encryption keys",
      "Dedicated legal engineer support",
      "Custom SLA & SOC2 Type II compliance",
      "Tailored AI fine-tuning on your playbooks",
    ],
    cta: "Talk to Sales",
    popular: false,
    buttonVariant: "outline" as const,
  },
];

export function LandingPricing() {
  return (
    <section id="pricing" className="py-20 bg-white border-y border-border">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center max-w-2xl mx-auto mb-16">
          <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary-50 text-primary-700 text-xs font-semibold mb-3">
            <Sparkles className="h-3.5 w-3.5" />
            Simple, Transparent Pricing
          </div>
          <h2 className="text-3xl font-bold tracking-tight text-text-main sm:text-4xl">
            Choose the plan right for your workload
          </h2>
          <p className="mt-3 text-base text-text-secondary">
            Every plan includes our core semantic diff engine and verifiable evidence citations.
          </p>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 items-stretch">
          {TIERS.map((tier, idx) => (
            <Card
              key={idx}
              className={`relative flex flex-col justify-between ${
                tier.popular
                  ? "border-2 border-primary-600 shadow-elevated ring-4 ring-primary-50"
                  : "border-border shadow-subtle"
              }`}
            >
              {tier.popular && (
                <div className="absolute -top-3.5 left-1/2 -translate-x-1/2">
                  <Badge variant="default" className="px-3 py-1 text-xs font-bold uppercase tracking-wider bg-primary-600 text-white">
                    Most Popular
                  </Badge>
                </div>
              )}

              <CardHeader className="p-6 pb-2">
                <div className="flex items-center justify-between">
                  <h3 className="text-xl font-bold text-text-main">{tier.name}</h3>
                  <Badge variant="secondary" className="text-xs">
                    {tier.badge}
                  </Badge>
                </div>
                <p className="text-xs text-text-secondary mt-1">{tier.description}</p>
                <div className="mt-4 flex items-baseline">
                  <span className="text-4xl font-extrabold tracking-tight text-text-main">
                    {tier.price}
                  </span>
                  {tier.period && (
                    <span className="ml-1 text-sm font-medium text-text-secondary">
                      {tier.period}
                    </span>
                  )}
                </div>
              </CardHeader>

              <CardContent className="p-6 pt-4 flex-1">
                <div className="space-y-3">
                  <p className="text-xs font-semibold uppercase tracking-wider text-text-secondary">
                    Included capabilities:
                  </p>
                  <ul className="space-y-2.5">
                    {tier.features.map((feature, fIdx) => (
                      <li key={fIdx} className="flex items-start text-xs sm:text-sm text-text-main">
                        <Check className="h-4 w-4 text-primary-600 shrink-0 mr-2.5 mt-0.5" />
                        <span>{feature}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </CardContent>

              <CardFooter className="p-6 pt-0">
                <Link href="/dashboard/compare" className="w-full">
                  <Button
                    variant={tier.buttonVariant}
                    className="w-full justify-center h-11 text-sm font-semibold"
                  >
                    {tier.cta}
                  </Button>
                </Link>
              </CardFooter>
            </Card>
          ))}
        </div>
      </div>
    </section>
  );
}
