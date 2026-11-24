"use client";

import * as React from "react";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { SlidersHorizontal, ShieldAlert } from "lucide-react";
import { formatBytes } from "@/lib/utils";

interface ComparisonConfigModalProps {
  isOpen: boolean;
  onClose: () => void;
  maxFileSize: number;
  maxPageLimit: number;
  onSave: (maxFileSize: number, maxPageLimit: number) => void;
}

export function ComparisonConfigModal({
  isOpen,
  onClose,
  maxFileSize,
  maxPageLimit,
  onSave,
}: ComparisonConfigModalProps) {
  const [size, setSize] = React.useState(maxFileSize / (1024 * 1024));
  const [pages, setPages] = React.useState(maxPageLimit);

  const handleSave = () => {
    onSave(size * 1024 * 1024, pages);
    onClose();
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <div className="flex items-center space-x-2 mb-1">
            <SlidersHorizontal className="h-5 w-5 text-primary-600" />
            <DialogTitle className="text-base font-bold text-text-main">
              Upload Validation Limits
            </DialogTitle>
          </div>
          <DialogDescription className="text-xs text-text-secondary">
            Configure client-side file inspection and pagination thresholds
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="space-y-2">
            <Label htmlFor="maxSize" className="text-xs font-semibold">
              Max File Size Threshold: {size} MB
            </Label>
            <input
              type="range"
              id="maxSize"
              min="5"
              max="150"
              step="5"
              value={size}
              onChange={(e) => setSize(Number(e.target.value))}
              className="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer"
            />
            <div className="flex justify-between text-[10px] text-text-secondary font-mono">
              <span>5 MB</span>
              <span>50 MB (Default)</span>
              <span>150 MB</span>
            </div>
          </div>

          <div className="space-y-2">
            <Label htmlFor="maxPages" className="text-xs font-semibold">
              Max Page Limit: {pages} pages
            </Label>
            <input
              type="range"
              id="maxPages"
              min="50"
              max="1500"
              step="50"
              value={pages}
              onChange={(e) => setPages(Number(e.target.value))}
              className="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer"
            />
            <div className="flex justify-between text-[10px] text-text-secondary font-mono">
              <span>50 pages</span>
              <span>500 pages (Default)</span>
              <span>1500 pages</span>
            </div>
          </div>

          <div className="rounded-lg bg-amber-50 p-3 border border-amber-200 flex items-start space-x-2 text-[11px] text-amber-900 leading-relaxed">
            <ShieldAlert className="h-4 w-4 text-amber-700 shrink-0 mt-0.5" />
            <span>
              <strong>Security Architecture Note:</strong> Client-side limits guard bandwidth and user experience. Full malware scanning, MIME cryptographic verification, and memory safety checks are enforced on backend Laravel workers.
            </span>
          </div>
        </div>

        <DialogFooter className="gap-2 sm:gap-0">
          <Button variant="outline" size="sm" onClick={onClose} className="text-xs">
            Cancel
          </Button>
          <Button size="sm" onClick={handleSave} className="text-xs font-semibold">
            Apply Limits
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
