import { setRequestLocale } from "next-intl/server";

import { HomeShell } from "@/components/HomeShell";
import { type AppLocale } from "@/i18n/routing";

export default async function HomePage({
  params,
}: {
  params: Promise<{ locale: string }>;
}) {
  const { locale } = await params;
  setRequestLocale(locale as AppLocale);

  return <HomeShell />;
}
