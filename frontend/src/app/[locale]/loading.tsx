import { getTranslations } from "next-intl/server";

export default async function LoadingPage() {
  const t = await getTranslations("common");

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <p className="text-sm text-zinc-600 dark:text-zinc-400">{t("loading")}</p>
    </div>
  );
}
