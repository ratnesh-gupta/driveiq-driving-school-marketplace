import { School } from "@/api-client";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Star, MapPin, Car, ShieldCheck, Columns3, Check } from "lucide-react";
import { Link } from "wouter";
import { useComparisonStore } from "@/features/comparison/stores/comparison-store";

interface SchoolCardProps {
  school: School;
  showCompare?: boolean;
}

export function SchoolCard({ school, showCompare = false }: SchoolCardProps) {
  const rating = typeof school.rating === "number" ? school.rating : 0;
  const vehicleTypes = Array.isArray(school.vehicleTypes) ? school.vehicleTypes : [];
  const reviewCount = typeof school.reviewCount === "number" ? school.reviewCount : 0;
  const priceFrom = typeof school.priceFrom === "number" ? school.priceFrom : 0;

  const { addSchool, removeSchool, isInComparison, canAdd } = useComparisonStore();
  const inComparison = isInComparison(school.id);

  const handleCompareToggle = () => {
    if (inComparison) {
      removeSchool(school.id);
    } else {
      addSchool(school.id);
    }
  };

  return (
    <div className="flex flex-col h-full">
      <Link href={`/school/${school.slug}`} className="flex-1 min-h-0">
        <Card className="group cursor-pointer overflow-hidden transition-all hover:shadow-md h-full flex flex-col">
          {/* Fixed-ratio image container */}
          <div className="aspect-[4/3] w-full relative bg-muted overflow-hidden">
            {school.imageUrl ? (
              <img
                src={school.imageUrl}
                alt={school.name}
                className="absolute inset-0 w-full h-full object-cover transition-transform group-hover:scale-105"
              />
            ) : (
              <div className="absolute inset-0 flex items-center justify-center bg-muted text-muted-foreground">
                <Car className="h-12 w-12 opacity-20" />
              </div>
            )}
            <div className="absolute top-2 right-2 flex flex-col gap-2">
              {school.verified && (
                <Badge className="bg-green-500 hover:bg-green-600 text-white border-transparent">
                  <ShieldCheck className="h-3 w-3 mr-1" /> Verified
                </Badge>
              )}
            </div>
          </div>

          {/* Content — fixed structure with consistent spacing */}
          <CardContent className="p-5 flex flex-col flex-1">
            {/* Row 1: Name + Rating — fixed height with line clamp */}
            <div className="flex justify-between items-start gap-2 mb-2">
              <h3 className="font-bold text-base leading-snug line-clamp-1 flex-1 group-hover:text-primary transition-colors">
                {school.name}
              </h3>
              <div className="flex items-center gap-1 bg-primary/10 text-primary px-2 py-0.5 rounded text-sm font-medium shrink-0">
                <Star className="h-3.5 w-3.5 fill-primary" />
                <span>{rating.toFixed(1)}</span>
              </div>
            </div>

            {/* Row 2: Location + Reviews */}
            <div className="flex items-center text-muted-foreground text-sm mb-3">
              <MapPin className="h-3.5 w-3.5 mr-1 shrink-0" />
              <span className="truncate">{school.localityName || "Pune"}</span>
              <span className="mx-1.5 shrink-0">·</span>
              <span className="shrink-0">{reviewCount} {reviewCount === 1 ? "review" : "reviews"}</span>
            </div>

            {/* Row 3: Badges — fixed height area (min-h so cards without badges still align) */}
            <div className="flex flex-wrap gap-1.5 min-h-[28px] mb-3">
              {vehicleTypes.slice(0, 2).map((v) => (
                <Badge key={v} variant="secondary" className="text-xs font-normal capitalize">
                  {v}
                </Badge>
              ))}
              {school.hasPickup && (
                <Badge variant="outline" className="text-xs font-normal">Pickup</Badge>
              )}
              {school.womenInstructor && (
                <Badge variant="outline" className="text-xs font-normal">Women Instructor</Badge>
              )}
            </div>

            {/* Row 4: Price — pinned to bottom */}
            <div className="pt-3 border-t mt-auto">
              <span className="text-xs text-muted-foreground">Starting from</span>
              <div className="font-bold text-lg leading-tight">₹{priceFrom.toLocaleString()}</div>
            </div>
          </CardContent>
        </Card>
      </Link>

      {showCompare && (
        <Button
          variant={inComparison ? "secondary" : "outline"}
          size="sm"
          onClick={handleCompareToggle}
          disabled={!inComparison && !canAdd()}
          className="mt-2 w-full gap-1.5 text-xs"
          data-testid={`compare-toggle-${school.id}`}
        >
          {inComparison ? (
            <>
              <Check className="h-3.5 w-3.5" />
              Added to Compare
            </>
          ) : (
            <>
              <Columns3 className="h-3.5 w-3.5" />
              Add to Compare
            </>
          )}
        </Button>
      )}
    </div>
  );
}
