import { useMemo } from "react";
import { Star, Check, X } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { createElement } from "react";
import type { ComparisonField } from "../types";
import type { School } from "@/api-client/generated/api.schemas";

function boolIcon(val: boolean) {
  return val
    ? createElement(Check, { className: "h-4 w-4 text-green-500 mx-auto" })
    : createElement(X, { className: "h-4 w-4 text-muted-foreground/40 mx-auto" });
}

function arrayBadges(arr: string[] | undefined) {
  if (!arr?.length) return createElement("span", { className: "text-muted-foreground text-sm" }, "—");
  return createElement(
    "div",
    { className: "flex flex-wrap gap-1" },
    arr.map((item) =>
      createElement(Badge, { key: item, variant: "secondary", className: "text-xs font-normal" }, item)
    )
  );
}

export function useComparisonFields(): ComparisonField[] {
  return useMemo(
    () => [
      {
        key: "rating",
        label: "Rating",
        category: "basic",
        render: (s: School) =>
          createElement(
            "div",
            { className: "flex items-center gap-1 justify-center" },
            createElement(Star, { className: "h-4 w-4 fill-yellow-400 text-yellow-400" }),
            createElement("span", { className: "font-semibold" }, s.rating.toFixed(1))
          ),
      },
      {
        key: "reviewCount",
        label: "Reviews",
        category: "basic",
        render: (s: School) => `${s.reviewCount} reviews`,
      },
      {
        key: "price",
        label: "Price Range",
        category: "pricing",
        render: (s: School) =>
          createElement(
            "span",
            { className: "font-semibold" },
            `₹${s.priceFrom.toLocaleString()} – ₹${s.priceTo.toLocaleString()}`
          ),
      },
      {
        key: "vehicleTypes",
        label: "Vehicle Types",
        category: "basic",
        render: (s: School) => arrayBadges(s.vehicleTypes),
      },
      {
        key: "transmission",
        label: "Transmission",
        category: "basic",
        render: (s: School) => arrayBadges(s.transmission),
      },
      {
        key: "hasPickup",
        label: "Pickup & Drop",
        category: "features",
        render: (s: School) => boolIcon(s.hasPickup),
      },
      {
        key: "pickupRadiusKm",
        label: "Pickup Radius",
        category: "features",
        render: (s: School) =>
          s.pickupRadiusKm ? `${s.pickupRadiusKm} km` : "—",
      },
      {
        key: "womenInstructor",
        label: "Women Instructor",
        category: "features",
        render: (s: School) => boolIcon(s.womenInstructor),
      },
      {
        key: "weekendClasses",
        label: "Weekend Classes",
        category: "features",
        render: (s: School) => boolIcon(s.weekendClasses),
      },
      {
        key: "simulatorTraining",
        label: "Simulator Training",
        category: "features",
        render: (s: School) => boolIcon(s.simulatorTraining),
      },
      {
        key: "acVehicle",
        label: "AC Vehicle",
        category: "features",
        render: (s: School) => boolIcon(s.acVehicle),
      },
      {
        key: "rtoAssistance",
        label: "RTO Assistance",
        category: "features",
        render: (s: School) => boolIcon(s.rtoAssistance),
      },
      {
        key: "languages",
        label: "Languages",
        category: "logistics",
        render: (s: School) => arrayBadges(s.languages),
      },
      {
        key: "acceptedPayments",
        label: "Payments Accepted",
        category: "logistics",
        render: (s: School) => arrayBadges(s.acceptedPayments),
      },
      {
        key: "establishedYear",
        label: "Established",
        category: "quality",
        render: (s: School) => s.establishedYear ?? "—",
      },
      {
        key: "totalVehicles",
        label: "Total Vehicles",
        category: "quality",
        render: (s: School) =>
          s.totalVehicles != null ? String(s.totalVehicles) : "—",
      },
      {
        key: "totalInstructors",
        label: "Total Instructors",
        category: "quality",
        render: (s: School) =>
          s.totalInstructors != null ? String(s.totalInstructors) : "—",
      },
      {
        key: "verified",
        label: "Verified",
        category: "quality",
        render: (s: School) => boolIcon(s.verified),
      },
    ],
    []
  );
}
