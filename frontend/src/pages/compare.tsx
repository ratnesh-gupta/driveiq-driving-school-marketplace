import { useMemo } from "react";
import { useSearch, useLocation, Link } from "wouter";
import { motion } from "framer-motion";
import { PublicLayout } from "@/components/layout/public-layout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import { Separator } from "@/components/ui/separator";
import {
  ArrowLeft,
  Search,
  Star,
  ShieldCheck,
  Columns3,
  Car,
  Trophy,
  Check,
  X,
  Send,
} from "lucide-react";
import { useComparisonSchools } from "@/features/comparison/hooks/use-comparison-schools";
import { useComparisonFields } from "@/features/comparison/hooks/use-comparison-fields";
import type { ComparisonField } from "@/features/comparison/types";
import type { School } from "@/api-client/generated/api.schemas";

const categoryLabels: Record<string, string> = {
  basic: "Basic Info",
  pricing: "Pricing",
  features: "Features & Services",
  logistics: "Logistics",
  quality: "School Quality",
};

const categoryOrder = ["basic", "pricing", "features", "logistics", "quality"];

function getBestSchoolId(schools: School[]): number | null {
  if (schools.length < 2) return null;
  let best = schools[0];
  for (const s of schools) {
    const score =
      s.rating * 2 +
      (s.verified ? 1 : 0) +
      (s.reviewCount > 10 ? 1 : 0) +
      (s.profileCompleteness > 70 ? 0.5 : 0);
    const bestScore =
      best.rating * 2 +
      (best.verified ? 1 : 0) +
      (best.reviewCount > 10 ? 1 : 0) +
      (best.profileCompleteness > 70 ? 0.5 : 0);
    if (score > bestScore) best = s;
  }
  return best.id;
}

function getMostAffordableId(schools: School[]): number | null {
  if (schools.length < 2) return null;
  let cheapest = schools[0];
  for (const s of schools) {
    if (s.priceFrom < cheapest.priceFrom) cheapest = s;
  }
  return cheapest.id;
}

export default function ComparePage() {
  const searchString = useSearch();
  const [, navigate] = useLocation();

  const schoolIds = useMemo(() => {
    const params = new URLSearchParams(searchString);
    const idsStr = params.get("schools") || "";
    return idsStr
      .split(",")
      .map(Number)
      .filter((n) => n > 0);
  }, [searchString]);

  const { schools, isLoading } = useComparisonSchools(schoolIds);
  const fields = useComparisonFields();

  const bestId = useMemo(() => getBestSchoolId(schools), [schools]);
  const cheapestId = useMemo(() => getMostAffordableId(schools), [schools]);

  const groupedFields = useMemo(() => {
    const groups: Record<string, typeof fields> = {};
    for (const field of fields) {
      if (!groups[field.category]) groups[field.category] = [];
      groups[field.category].push(field);
    }
    return groups;
  }, [fields]);

  if (schoolIds.length < 2 && !isLoading) {
    return (
      <PublicLayout>
        <div className="container mx-auto px-4 py-20 text-center">
          <Columns3 className="h-12 w-12 mx-auto mb-4 text-muted-foreground/30" />
          <h1 className="text-2xl font-bold mb-2">Compare Driving Schools</h1>
          <p className="text-muted-foreground mb-6">
            Select at least 2 schools from the search page to compare them side
            by side.
          </p>
          <Button onClick={() => navigate("/search")} className="gap-2">
            <Search className="h-4 w-4" /> Browse Schools
          </Button>
        </div>
      </PublicLayout>
    );
  }

  return (
    <PublicLayout>
      {/* Hero */}
      <section className="bg-gradient-to-br from-primary/5 to-accent/10 border-b py-8">
        <div className="container mx-auto px-4">
          <Link
            href="/search"
            className="text-muted-foreground hover:text-foreground text-sm flex items-center gap-1 mb-4 w-fit"
          >
            <ArrowLeft className="h-4 w-4" /> Back to Search
          </Link>
          <h1 className="text-2xl md:text-3xl font-bold">
            Compare {schools.length} Driving Schools
          </h1>
          <p className="text-muted-foreground mt-1">
            Side-by-side comparison to help you choose the right school
          </p>
        </div>
      </section>

      <section className="py-8">
        <div className="container mx-auto px-4">
          {isLoading ? (
            <div className="space-y-4 max-w-4xl mx-auto">
              <div className="grid grid-cols-2 gap-4">
                <Skeleton className="h-48 rounded-xl" />
                <Skeleton className="h-48 rounded-xl" />
              </div>
              <Skeleton className="h-64 w-full rounded-xl" />
            </div>
          ) : (
            <div className="max-w-5xl mx-auto space-y-8">
              {/* School Header Cards */}
              <motion.div
                className={`grid gap-4 ${schools.length === 2 ? "grid-cols-1 sm:grid-cols-2" : schools.length === 3 ? "grid-cols-1 sm:grid-cols-3" : "grid-cols-2 lg:grid-cols-4"}`}
                initial="initial"
                animate="animate"
                variants={{
                  animate: { transition: { staggerChildren: 0.1 } },
                }}
              >
                {schools.map((school) => (
                  <motion.div
                    key={school.id}
                    initial={{ opacity: 0, y: 16 }}
                    animate={{ opacity: 1, y: 0 }}
                  >
                    <SchoolHeaderCard
                      school={school}
                      isBest={school.id === bestId}
                      isCheapest={school.id === cheapestId}
                    />
                  </motion.div>
                ))}
              </motion.div>

              {/* Comparison Table — Desktop */}
              <div className="hidden md:block">
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: 0.2 }}
                >
                  {categoryOrder.map((category) => {
                    const catFields = groupedFields[category];
                    if (!catFields) return null;
                    return (
                      <DesktopCategory
                        key={category}
                        label={categoryLabels[category]}
                        fields={catFields}
                        schools={schools}
                      />
                    );
                  })}
                </motion.div>
              </div>

              {/* Comparison Cards — Mobile */}
              <div className="md:hidden space-y-4">
                {categoryOrder.map((category) => {
                  const catFields = groupedFields[category];
                  if (!catFields) return null;
                  return (
                    <MobileCategory
                      key={category}
                      label={categoryLabels[category]}
                      fields={catFields}
                      schools={schools}
                    />
                  );
                })}
              </div>

              {/* CTA Row */}
              <div
                className={`grid gap-4 ${schools.length === 2 ? "grid-cols-1 sm:grid-cols-2" : schools.length === 3 ? "grid-cols-1 sm:grid-cols-3" : "grid-cols-2 lg:grid-cols-4"}`}
              >
                {schools.map((school) => (
                  <Button
                    key={school.id}
                    onClick={() => navigate(`/school/${school.slug}`)}
                    className="gap-2"
                  >
                    <Send className="h-4 w-4" />
                    Inquire {school.name.split(" ")[0]}
                  </Button>
                ))}
              </div>

              {/* Disclaimer */}
              <p className="text-xs text-muted-foreground text-center pt-4">
                Pricing and features are as reported by driving schools. Verify
                details directly with the school before enrolling.
              </p>
            </div>
          )}
        </div>
      </section>
    </PublicLayout>
  );
}

/* ── School Header Card ── */
function SchoolHeaderCard({
  school,
  isBest,
  isCheapest,
}: {
  school: School;
  isBest: boolean;
  isCheapest: boolean;
}) {
  return (
    <Link href={`/school/${school.slug}`}>
      <Card
        className={`h-full overflow-hidden transition-all hover:shadow-md group cursor-pointer ${
          isBest ? "ring-2 ring-primary/40" : ""
        }`}
      >
        {/* Image */}
        <div className="aspect-[16/9] w-full relative bg-muted">
          {school.imageUrl ? (
            <img
              src={school.imageUrl}
              alt={school.name}
              className="w-full h-full object-cover transition-transform group-hover:scale-105"
            />
          ) : (
            <div className="w-full h-full flex items-center justify-center text-muted-foreground">
              <Car className="h-10 w-10 opacity-20" />
            </div>
          )}
          {/* Badges */}
          <div className="absolute top-2 left-2 flex flex-wrap gap-1.5">
            {isBest && (
              <Badge className="bg-primary text-primary-foreground border-transparent text-xs gap-1">
                <Trophy className="h-3 w-3" /> Best Rated
              </Badge>
            )}
            {isCheapest && (
              <Badge className="bg-green-500 text-white border-transparent text-xs">
                Most Affordable
              </Badge>
            )}
          </div>
          {school.verified && (
            <div className="absolute top-2 right-2">
              <Badge className="bg-green-500 hover:bg-green-600 text-white border-transparent text-xs gap-1">
                <ShieldCheck className="h-3 w-3" /> Verified
              </Badge>
            </div>
          )}
        </div>

        <CardContent className="p-4 space-y-2">
          <h3 className="font-bold text-base leading-tight line-clamp-1 group-hover:text-primary transition-colors">
            {school.name}
          </h3>
          <div className="flex items-center gap-3 text-sm">
            <div className="flex items-center gap-1">
              <Star className="h-3.5 w-3.5 fill-yellow-400 text-yellow-400" />
              <span className="font-semibold">{school.rating.toFixed(1)}</span>
              <span className="text-muted-foreground">
                ({school.reviewCount})
              </span>
            </div>
          </div>
          <div className="flex items-baseline gap-1">
            <span className="text-lg font-bold">
              ₹{school.priceFrom.toLocaleString()}
            </span>
            <span className="text-xs text-muted-foreground">starting</span>
          </div>
          <div className="text-xs text-muted-foreground">
            {school.localityName || "Pune"}
          </div>
        </CardContent>
      </Card>
    </Link>
  );
}

/* ── Desktop Category Table ── */
function DesktopCategory({
  label,
  fields,
  schools,
}: {
  label: string;
  fields: ComparisonField[];
  schools: School[];
}) {
  return (
    <div className="mb-6">
      <div className="bg-muted/50 rounded-t-lg px-4 py-2.5 border border-b-0">
        <span className="font-semibold text-sm">{label}</span>
      </div>
      <div className="border rounded-b-lg overflow-hidden">
        {fields.map((field, i) => (
          <div
            key={field.key}
            className={`grid items-center gap-4 px-4 py-3 ${
              i !== fields.length - 1 ? "border-b" : ""
            } ${i % 2 === 0 ? "bg-background" : "bg-muted/20"}`}
            style={{
              gridTemplateColumns: `180px repeat(${schools.length}, 1fr)`,
            }}
          >
            <div className="text-sm font-medium text-muted-foreground">
              {field.label}
            </div>
            {schools.map((school) => (
              <div
                key={school.id}
                className="text-sm text-center flex items-center justify-center"
              >
                {field.render(school)}
              </div>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}

/* ── Mobile Category Cards ── */
function MobileCategory({
  label,
  fields,
  schools,
}: {
  label: string;
  fields: ComparisonField[];
  schools: School[];
}) {
  return (
    <Card>
      <CardContent className="p-0">
        <div className="bg-muted/50 px-4 py-2.5 border-b">
          <span className="font-semibold text-sm">{label}</span>
        </div>
        {fields.map((field, i) => (
          <div
            key={field.key}
            className={`px-4 py-3 ${i !== fields.length - 1 ? "border-b" : ""}`}
          >
            <div className="text-xs font-medium text-muted-foreground mb-2">
              {field.label}
            </div>
            <div className="grid grid-cols-2 gap-3">
              {schools.slice(0, 2).map((school) => (
                <div key={school.id} className="text-center">
                  <div className="text-[10px] text-muted-foreground/70 truncate mb-0.5">
                    {school.name.split(" ").slice(0, 2).join(" ")}
                  </div>
                  <div className="text-sm flex items-center justify-center">
                    {field.render(school)}
                  </div>
                </div>
              ))}
            </div>
            {schools.length > 2 && (
              <div className="grid grid-cols-2 gap-3 mt-2 pt-2 border-t border-dashed">
                {schools.slice(2).map((school) => (
                  <div key={school.id} className="text-center">
                    <div className="text-[10px] text-muted-foreground/70 truncate mb-0.5">
                      {school.name.split(" ").slice(0, 2).join(" ")}
                    </div>
                    <div className="text-sm flex items-center justify-center">
                      {field.render(school)}
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        ))}
      </CardContent>
    </Card>
  );
}
