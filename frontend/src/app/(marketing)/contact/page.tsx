"use client";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Mail, Phone, MapPin } from "lucide-react";

export default function ContactPage() {
  return (
    <div className="max-w-4xl mx-auto py-16 px-4 space-y-12">
      <div className="text-center space-y-3">
        <h1 className="text-4xl font-extrabold text-text-main">Contact Enterprise Sales</h1>
        <p className="text-text-secondary max-w-lg mx-auto">
          Speak with our compliance engineering team about custom integrations or on-prem deployment.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div className="space-y-6">
          <div className="flex items-start gap-4">
            <div className="h-10 w-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center font-bold">
              <Mail className="h-5 w-5" />
            </div>
            <div>
              <h4 className="font-bold text-text-main text-sm">Email Us</h4>
              <p className="text-xs text-text-secondary mt-0.5">enterprise@docver.io</p>
              <p className="text-xs text-text-secondary">support@docver.io</p>
            </div>
          </div>

          <div className="flex items-start gap-4">
            <div className="h-10 w-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center font-bold">
              <Phone className="h-5 w-5" />
            </div>
            <div>
              <h4 className="font-bold text-text-main text-sm">Direct Phone</h4>
              <p className="text-xs text-text-secondary mt-0.5">+1 (888) 362-8371</p>
            </div>
          </div>

          <div className="flex items-start gap-4">
            <div className="h-10 w-10 rounded-lg bg-primary-50 text-primary-600 flex items-center justify-center font-bold">
              <MapPin className="h-5 w-5" />
            </div>
            <div>
              <h4 className="font-bold text-text-main text-sm">Headquarters</h4>
              <p className="text-xs text-text-secondary mt-0.5">500 Howard Street, Suite 400</p>
              <p className="text-xs text-text-secondary">San Francisco, CA 94105</p>
            </div>
          </div>
        </div>

        <Card className="bg-white border-border shadow-subtle">
          <CardHeader className="p-6 pb-2">
            <CardTitle className="text-lg font-bold text-text-main">Send a Message</CardTitle>
            <CardDescription className="text-xs text-text-secondary">We typically reply within 2 business hours.</CardDescription>
          </CardHeader>
          <CardContent className="p-6 pt-2 space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="name" className="text-xs">Full Name</Label>
              <Input id="name" placeholder="Sarah Jenkins" />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="email" className="text-xs">Work Email</Label>
              <Input id="email" type="email" placeholder="sarah@firm.com" />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="message" className="text-xs">Inquiry Details</Label>
              <textarea id="message" rows={4} className="w-full text-xs rounded-md border border-border p-2 focus:outline-none focus:ring-1 focus:ring-primary-500" placeholder="Tell us about your document volume and compliance requirements..." />
            </div>
            <Button className="w-full text-xs">Submit Inquiry</Button>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
