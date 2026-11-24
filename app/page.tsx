import * as React from "react";
import { LandingNavbar } from "@/components/landing/navbar";
import { LandingHero } from "@/components/landing/hero";
import { LandingFeatures } from "@/components/landing/features";
import { LandingHowItWorks } from "@/components/landing/how-it-works";
import { LandingCompatibility } from "@/components/landing/compatibility";
import { LandingSecurity } from "@/components/landing/security";
import { LandingPricing } from "@/components/landing/pricing";
import { LandingFAQ } from "@/components/landing/faq";
import { LandingFooter } from "@/components/landing/footer";

export default function LandingPage() {
  return (
    <div className="min-h-screen bg-background flex flex-col">
      <LandingNavbar />
      <main className="flex-1">
        <LandingHero />
        <LandingFeatures />
        <LandingHowItWorks />
        <LandingCompatibility />
        <LandingSecurity />
        <LandingPricing />
        <LandingFAQ />
      </main>
      <LandingFooter />
    </div>
  );
}
