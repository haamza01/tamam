"use client";

import { useTranslations } from "next-intl";

export default function ErrorPage({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  const t = useTranslations("common");

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <div className="max-w-md text-center">
        <h1 className="text-2xl font-semibold">{t("errorTitle")}</h1>
        <p className="mt-3 text-zinc-600 dark:text-zinc-400">{t("errorDescription")}</p>
        <button
          type="button"
          onClick={reset}
          className="mt-6 inline-flex rounded-lg bg-[#8a1538] px-4 py-2 text-sm text-white"
        >
          {t("errorAction")}
        </button>
      </div>
    </div>
  );
}
