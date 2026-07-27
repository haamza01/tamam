"use client";

import { useTranslations } from "next-intl";

import { LanguageSwitcher } from "@/components/LanguageSwitcher";
import { ThemeToggle } from "@/components/ThemeToggle";

export function HomeShell() {
  const t = useTranslations();

  const categories = [
    "cars",
    "realEstate",
    "jobs",
    "services",
    "electronics",
  ] as const;

  return (
    <div className="min-h-screen bg-background text-foreground">
      <header className="border-b border-zinc-200/80 dark:border-zinc-800">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4">
          <div>
            <p className="text-xl font-semibold text-[#8a1538]">{t("common.brand")}</p>
            <p className="text-sm text-zinc-600 dark:text-zinc-400">{t("common.tagline")}</p>
          </div>
          <div className="flex items-center gap-3">
            <LanguageSwitcher />
            <ThemeToggle />
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-4 py-10">
        <section className="rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
          <p className="mb-2 text-xs uppercase tracking-wide text-zinc-500">
            {t("common.foundationNotice")}
          </p>
          <h1 className="mb-3 text-3xl font-semibold">{t("common.brand")}</h1>
          <p className="mb-8 max-w-2xl text-zinc-600 dark:text-zinc-400">{t("home.intro")}</p>

          <label className="block">
            <span className="sr-only">{t("common.searchPlaceholder")}</span>
            <input
              type="search"
              disabled
              placeholder={t("common.searchPlaceholder")}
              className="w-full rounded-xl border border-zinc-300 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-900"
            />
          </label>
        </section>

        <section className="mt-10">
          <h2 className="mb-4 text-xl font-semibold">{t("common.categoriesTitle")}</h2>
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            {categories.map((category) => (
              <div
                key={category}
                className="rounded-xl border border-dashed border-zinc-300 px-4 py-6 text-center text-sm dark:border-zinc-700"
              >
                {t(`categories.${category}`)}
              </div>
            ))}
          </div>
          <p className="mt-3 text-sm text-zinc-500">{t("common.categoriesPlaceholder")}</p>
        </section>

        <section className="mt-10">
          <h2 className="mb-4 text-xl font-semibold">{t("common.featuredTitle")}</h2>
          <div className="grid gap-4 md:grid-cols-3">
            {[1, 2, 3].map((item) => (
              <div
                key={item}
                className="rounded-xl border border-dashed border-zinc-300 p-6 dark:border-zinc-700"
              >
                <div className="mb-4 h-32 rounded-lg bg-zinc-100 dark:bg-zinc-900" />
                <div className="h-4 w-2/3 rounded bg-zinc-200 dark:bg-zinc-800" />
                <div className="mt-2 h-4 w-1/3 rounded bg-zinc-100 dark:bg-zinc-900" />
              </div>
            ))}
          </div>
          <p className="mt-3 text-sm text-zinc-500">{t("common.featuredPlaceholder")}</p>
        </section>
      </main>
    </div>
  );
}
