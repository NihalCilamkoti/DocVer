"use client";

import * as React from "react";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Check, ShieldCheck, Key } from "lucide-react";
import { useAuth } from "@/components/auth-provider";

export default function SettingsPage() {
  const { user } = useAuth();
  const [saved, setSaved] = React.useState(false);

  const defaultOrg = user?.workspaces?.[0]?.name || "Legal Workspace";
  const defaultEmail = user?.email || "";

  const handleSave = (e: React.FormEvent) => {
    e.preventDefault();
    setSaved(true);
    setTimeout(() => setSaved(false), 3000);
  };

  return (
    <div className="space-y-6 max-w-4xl mx-auto">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-text-main">
          Workspace Settings
        </h1>
        <p className="text-sm text-text-secondary mt-0.5">
          Manage organization profile, data policies, and preferences
        </p>
      </div>

      <Card className="bg-white border-border shadow-subtle">
        <CardHeader className="p-6 pb-3">
          <CardTitle className="text-lg font-bold text-text-main">
            Organization Profile
          </CardTitle>
          <CardDescription className="text-xs text-text-secondary">
            Settings used for document attribution and report headers
          </CardDescription>
        </CardHeader>

        <CardContent className="p-6 pt-1">
          <form onSubmit={handleSave} className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="orgName">Organization / Workspace Name</Label>
                <Input id="orgName" defaultValue={defaultOrg} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="contactEmail">Contact Email</Label>
                <Input id="contactEmail" defaultValue={defaultEmail} placeholder="your.email@domain.com" />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="retention">Document Retention Policy</Label>
              <select
                id="retention"
                className="flex h-10 w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-text-main"
                defaultValue="0"
              >
                <option value="0">Immediate Purge after verification (Zero Retention)</option>
                <option value="7">7 Days Encrypted Cache</option>
                <option value="30">30 Days Encrypted Cache</option>
              </select>
            </div>

            <div className="flex items-center justify-between pt-2">
              {saved && (
                <span className="text-xs font-semibold text-success flex items-center gap-1">
                  <Check className="h-3.5 w-3.5" />
                  Settings saved successfully
                </span>
              )}
              <Button type="submit" size="sm" className="ml-auto text-xs">
                Save Changes
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
