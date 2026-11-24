import * as React from "react";
import { cva, type VariantProps } from "class-variance-authority";
import { cn } from "@/lib/utils";

const badgeVariants = cva(
  "inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-primary-50 text-primary-700 border-primary-200",
        ai:
          "border-ai-muted bg-ai-light text-ai-700",
        secondary:
          "border-transparent bg-slate-100 text-slate-700",
        outline:
          "text-text-secondary border-border bg-white",
        success:
          "border-success-100 bg-success-50 text-success-700",
        destructive:
          "border-error-100 bg-error-50 text-error-700",
        warning:
          "border-warning-100 bg-warning-50 text-warning-700",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
);

export interface BadgeProps
  extends React.HTMLAttributes<HTMLDivElement>,
    VariantProps<typeof badgeVariants> {}

function Badge({ className, variant, ...props }: BadgeProps) {
  return (
    <div className={cn(badgeVariants({ variant }), className)} {...props} />
  );
}

export { Badge, badgeVariants };
