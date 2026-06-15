import type { ReactNode } from "react";
import type { School } from "@/api-client/generated/api.schemas";

export interface ComparisonField {
  key: string;
  label: string;
  category: "basic" | "features" | "pricing" | "logistics" | "quality";
  render: (school: School) => ReactNode;
}
