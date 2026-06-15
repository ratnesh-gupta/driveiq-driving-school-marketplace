import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { MapPin, Phone, Clock, ExternalLink } from "lucide-react";
import type { RTOOffice } from "../types";

interface RtoOfficeCardProps {
  office: RTOOffice;
  isNearest?: boolean;
}

export function RtoOfficeCard({ office, isNearest = false }: RtoOfficeCardProps) {
  return (
    <Card className={isNearest ? "border-primary/50 ring-1 ring-primary/20" : ""}>
      <CardContent className="p-5 space-y-3">
        <div className="flex items-start justify-between gap-2">
          <div>
            <h4 className="font-semibold text-base">{office.name}</h4>
            {isNearest && (
              <Badge variant="secondary" className="mt-1 text-xs">
                Nearest RTO
              </Badge>
            )}
          </div>
        </div>

        <div className="space-y-2 text-sm text-muted-foreground">
          <div className="flex items-start gap-2">
            <MapPin className="h-4 w-4 mt-0.5 shrink-0" />
            <span>{office.address}</span>
          </div>
          {office.phone && (
            <div className="flex items-center gap-2">
              <Phone className="h-4 w-4 shrink-0" />
              <span>{office.phone}</span>
            </div>
          )}
          {office.timings && (
            <div className="flex items-center gap-2">
              <Clock className="h-4 w-4 shrink-0" />
              <span>{office.timings}</span>
            </div>
          )}
        </div>

        <div className="flex flex-wrap gap-1.5">
          {office.services.map((service) => (
            <Badge key={service} variant="outline" className="text-xs font-normal">
              {service}
            </Badge>
          ))}
        </div>

        {office.googleMapsUrl && (
          <Button variant="outline" size="sm" className="w-full gap-2" asChild>
            <a href={office.googleMapsUrl} target="_blank" rel="noopener noreferrer">
              <MapPin className="h-3.5 w-3.5" />
              Open in Google Maps
              <ExternalLink className="h-3 w-3 ml-auto" />
            </a>
          </Button>
        )}
      </CardContent>
    </Card>
  );
}
