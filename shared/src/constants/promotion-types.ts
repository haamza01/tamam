export const LAUNCH_PROMOTION_TYPES = ["featured"] as const;

export type LaunchPromotionType = (typeof LAUNCH_PROMOTION_TYPES)[number];

export const FUTURE_PROMOTION_TYPES = [
  "homepage",
  "top_of_category",
  "top_of_search",
  "urgent",
  "highlight",
  "auto_bump",
] as const;

export type FuturePromotionType = (typeof FUTURE_PROMOTION_TYPES)[number];
