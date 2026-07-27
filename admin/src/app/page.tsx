import { AdminShell } from "@/components/AdminShell";

export default function DashboardPage() {
  return (
    <AdminShell
      title="Dashboard"
      description="Administration dashboard foundation placeholder. Authentication and RBAC will be added in Phase 1."
    >
      <div className="rounded-2xl border border-dashed border-zinc-300 bg-white p-10 text-center">
        <h2 className="text-lg font-medium">Empty dashboard state</h2>
        <p className="mt-2 text-sm text-zinc-600">
          KPIs, moderation queues, and management tools will appear here in later phases.
        </p>
      </div>
    </AdminShell>
  );
}
