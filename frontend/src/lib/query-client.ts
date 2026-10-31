import { QueryClient } from "@tanstack/react-query";

/** Shared instance so session teardown (logout, 401) can clear cached server data. */
export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30000,
    },
  },
});
