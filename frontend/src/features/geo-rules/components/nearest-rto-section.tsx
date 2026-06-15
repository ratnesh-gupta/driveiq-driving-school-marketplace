import { motion } from "framer-motion";
import { Building2 } from "lucide-react";
import { useRegulationData } from "../hooks/use-regulation-data";
import { useLocationStore } from "../stores/location-store";
import { RtoOfficeCard } from "./rto-office-card";

const fadeIn = {
  initial: { opacity: 0, y: 20 } as const,
  animate: { opacity: 1, y: 0 } as const,
  transition: { duration: 0.5 },
};

export function NearestRtoSection() {
  const { nearestRto, allRtos } = useRegulationData();
  const { selectedArea } = useLocationStore();

  if (allRtos.length === 0) return null;

  const otherRtos = nearestRto
    ? allRtos.filter((r) => r.slug !== nearestRto.slug)
    : allRtos;

  return (
    <motion.div {...fadeIn} className="space-y-4">
      <div className="flex items-center gap-2">
        <Building2 className="h-5 w-5 text-primary" />
        <h3 className="text-xl font-bold">
          {selectedArea ? "Nearest RTO Office" : "RTO Offices in Pune"}
        </h3>
      </div>

      {nearestRto && (
        <RtoOfficeCard office={nearestRto} isNearest />
      )}

      {otherRtos.length > 0 && (
        <div className="space-y-3">
          {nearestRto && (
            <h4 className="text-sm font-medium text-muted-foreground">
              Other RTO Offices
            </h4>
          )}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            {otherRtos.map((office) => (
              <RtoOfficeCard key={office.slug} office={office} />
            ))}
          </div>
        </div>
      )}
    </motion.div>
  );
}
