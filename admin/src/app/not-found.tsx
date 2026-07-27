import Link from "next/link";

export default function NotFoundPage() {
  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <div className="max-w-md text-center">
        <h1 className="text-2xl font-semibold">Page not found</h1>
        <p className="mt-3 text-zinc-600">The requested admin page does not exist.</p>
        <Link
          href="/"
          className="mt-6 inline-flex rounded-lg bg-[#8a1538] px-4 py-2 text-sm text-white"
        >
          Back to dashboard
        </Link>
      </div>
    </div>
  );
}
