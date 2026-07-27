"use client";

import { useLocale } from "next-intl";

import { Link, usePathname } from "@/i18n/navigation";
import { routing, type AppLocale } from "@/i18n/routing";

export function LanguageSwitcher() {
  const locale = useLocale() as AppLocale;
  const pathname = usePathname();

  return (
    <div className="flex items-center gap-1 rounded-lg border border-zinc-300 p-1 text-sm dark:border-zinc-700">
      {routing.locales.map((nextLocale) => (
        <Link
          key={nextLocale}
          href={pathname}
          locale={nextLocale}
          className={`rounded-md px-2 py-1 ${
            locale === nextLocale
              ? "bg-[#8a1538] text-white"
              : "text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
          }`}
        >
          {nextLocale.toUpperCase()}
        </Link>
      ))}
    </div>
  );
}
