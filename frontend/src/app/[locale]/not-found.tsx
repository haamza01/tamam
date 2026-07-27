import { getTranslations } from "next-intl/server";

import { Link } from "@/i18n/navigation";

export default async function NotFoundPage() {
  const t = await getTranslations("common");

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <div className="max-w-md text-center">
        <h1 className="text-2xl font-semibold">{t("notFoundTitle")}</h1>
        <p className="mt-3 text-zinc-600 dark:text-zinc-400">{t("notFoundDescription")}</p>
        <Link
          href="/"
          className="mt-6 inline-flex rounded-lg bg-[#8a1538] px-4 py-2 text-sm text-white"
        >
          {t("notFoundAction")}
        </Link>
      </div>
    </div>
  );
}
