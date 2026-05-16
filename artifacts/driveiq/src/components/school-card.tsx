import { School } from "@workspace/api-client-react";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Star, MapPin, Car, ShieldCheck } from "lucide-react";
import { Link } from "wouter";

export function SchoolCard({ school }: { school: School }) {
  return (
    <Link href={`/school/${school.slug}`}>
      <Card className="group cursor-pointer overflow-hidden transition-all hover:shadow-md h-full flex flex-col">
        <div className="aspect-[4/3] w-full relative bg-muted">
          {school.imageUrl ? (
            <img src={school.imageUrl} alt={school.name} className="w-full h-full object-cover transition-transform group-hover:scale-105" />
          ) : (
            <div className="w-full h-full flex items-center justify-center bg-muted text-muted-foreground">
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
        <CardContent className="p-5 flex flex-col flex-1">
          <div className="flex justify-between items-start mb-2">
            <h3 className="font-bold text-lg leading-tight line-clamp-1 group-hover:text-primary transition-colors">{school.name}</h3>
            <div className="flex items-center gap-1 bg-primary/10 text-primary px-2 py-0.5 rounded text-sm font-medium">
              <Star className="h-3.5 w-3.5 fill-primary" />
              <span>{school.rating.toFixed(1)}</span>
            </div>
          </div>
          
          <div className="flex items-center text-muted-foreground text-sm mb-4">
            <MapPin className="h-3.5 w-3.5 mr-1" />
            <span className="line-clamp-1">{school.localityName || 'Pune'}</span>
            <span className="mx-2">•</span>
            <span>{school.reviewCount} reviews</span>
          </div>

          <div className="flex flex-wrap gap-1.5 mb-4 mt-auto">
            {school.vehicleTypes.map(v => (
              <Badge key={v} variant="secondary" className="text-xs font-normal">
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

          <div className="flex items-end justify-between pt-4 border-t mt-auto">
            <div className="flex flex-col">
              <span className="text-xs text-muted-foreground">Starting from</span>
              <span className="font-bold text-lg">₹{school.priceFrom}</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </Link>
  );
}
