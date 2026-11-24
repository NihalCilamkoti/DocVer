export default function Loading() {
  return (
    <div className="min-h-[50vh] flex flex-col items-center justify-center space-y-3">
      <div className="h-8 w-8 rounded-full border-2 border-primary-600 border-t-transparent animate-spin" />
      <span className="text-xs text-text-secondary font-medium">Loading DocVer workspace...</span>
    </div>
  );
}
