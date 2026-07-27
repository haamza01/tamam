export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface PaginationQuery {
  page?: number;
  limit?: number;
}
