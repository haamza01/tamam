"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

const navItems = [
  { href: "/", label: "Dashboard" },
  { href: "/login", label: "Login" },
];

export function AdminShell({
  children,
  title,
  description,
}: {
  children: React.ReactNode;
  title: string;
  description: string;
}) {
  const pathname = usePathname();

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[240px_1fr]">
      <aside className="border-b border-zinc-200 bg-white lg:border-b-0 lg:border-r">
        <div className="px-5 py-6">
          <p className="text-lg font-semibold text-[#8a1538]">Tamam Admin</p>
          <p className="text-xs text-zinc-500">Foundation placeholder</p>
        </div>
        <nav className="px-3 pb-6">
          {navItems.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className={`block rounded-lg px-3 py-2 text-sm ${
                pathname === item.href
                  ? "bg-[#8a1538] text-white"
                  : "text-zinc-700 hover:bg-zinc-100"
              }`}
            >
              {item.label}
            </Link>
          ))}
        </nav>
      </aside>

      <div className="flex min-h-screen flex-col">
        <header className="border-b border-zinc-200 bg-white px-6 py-4">
          <p className="text-xs uppercase tracking-wide text-zinc-500">Phase 0D shell</p>
          <h1 className="text-2xl font-semibold">{title}</h1>
          <p className="text-sm text-zinc-600">{description}</p>
        </header>
        <main className="flex-1 px-6 py-8">{children}</main>
      </div>
    </div>
  );
}
