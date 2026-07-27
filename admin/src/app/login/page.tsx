export default function LoginPage() {
  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <div className="w-full max-w-md rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm">
        <p className="text-xs uppercase tracking-wide text-zinc-500">Foundation placeholder</p>
        <h1 className="mt-2 text-2xl font-semibold text-[#8a1538]">Tamam Admin Login</h1>
        <p className="mt-2 text-sm text-zinc-600">
          Authentication is not implemented yet. This page reserves the admin sign-in entry point
          for Phase 1.
        </p>

        <form className="mt-8 space-y-4">
          <label className="block text-sm">
            <span className="mb-1 block text-zinc-700">Email</span>
            <input
              type="email"
              disabled
              placeholder="admin@tamam.local"
              className="w-full rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-2"
            />
          </label>
          <label className="block text-sm">
            <span className="mb-1 block text-zinc-700">Password</span>
            <input
              type="password"
              disabled
              placeholder="••••••••"
              className="w-full rounded-lg border border-zinc-300 bg-zinc-50 px-3 py-2"
            />
          </label>
          <button
            type="button"
            disabled
            className="w-full rounded-lg bg-[#8a1538] px-4 py-2 text-sm text-white opacity-60"
          >
            Sign in (coming in Phase 1)
          </button>
        </form>
      </div>
    </div>
  );
}
