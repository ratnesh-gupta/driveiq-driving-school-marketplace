import { motion } from "framer-motion";
import { PublicLayout } from "@/components/layout/public-layout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import { Separator } from "@/components/ui/separator";
import {
  LocationSelector,
  DrivingRulesSection,
  NearestRtoSection,
  GovernmentLinksSection,
} from "@/features/geo-rules/components";
import { useRegulationData } from "@/features/geo-rules/hooks/use-regulation-data";
import { useLocationStore } from "@/features/geo-rules/stores/location-store";
import { BookOpen, Lightbulb, ArrowRight } from "lucide-react";
import { useLocation } from "wouter";

const fadeIn = {
  initial: { opacity: 0, y: 20 } as const,
  whileInView: { opacity: 1, y: 0 } as const,
  viewport: { once: true },
  transition: { duration: 0.5 },
};

export default function DrivingRulesPage() {
  const [, navigate] = useLocation();
  const { regulations } = useRegulationData();
  const { selectedCity, selectedArea } = useLocationStore();

  const cityName = selectedCity === "pune" ? "Pune" : selectedCity;

  return (
    <PublicLayout>
      {/* Hero */}
      <section className="bg-gradient-to-br from-primary/5 to-accent/10 border-b py-16">
        <div className="container mx-auto px-4 text-center max-w-3xl">
          <motion.div {...fadeIn}>
            <Badge variant="secondary" className="mb-4">
              <BookOpen className="h-3 w-3 mr-1" /> Knowledge Center
            </Badge>
            <h1 className="text-3xl md:text-4xl font-bold mb-3">
              Driving Rules & License Guidance
            </h1>
            <p className="text-lg text-muted-foreground leading-relaxed max-w-2xl mx-auto">
              Everything you need to know about getting your driving license in{" "}
              {cityName} — minimum age, documents, fees, RTO offices, and
              step-by-step process.
            </p>
          </motion.div>
        </div>
      </section>

      {/* Location Selector */}
      <section className="border-b bg-card">
        <div className="container mx-auto px-4 py-4">
          <LocationSelector compact className="justify-center" />
        </div>
      </section>

      {/* Main Content */}
      <section className="py-12">
        <div className="container mx-auto px-4">
          <div className="max-w-5xl mx-auto space-y-12">
            {/* Quick Summary */}
            {regulations && (
              <motion.div {...fadeIn}>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                  {[
                    {
                      label: "Min Age (Car)",
                      value: `${regulations.minimumAge.find((r) => r.vehicleType.includes("Four"))?.minimumAge ?? 18} yrs`,
                    },
                    {
                      label: "Min Age (Bike)",
                      value: `${regulations.minimumAge.find((r) => r.vehicleType.includes("With Gear"))?.minimumAge ?? 18} yrs`,
                    },
                    {
                      label: "LL Fee",
                      value: regulations.fees.find((f) => f.item.includes("Learner License App"))?.amount ?? "₹200",
                    },
                    {
                      label: "DL Fee",
                      value: regulations.fees.find((f) => f.item.includes("Permanent"))?.amount ?? "₹300",
                    },
                  ].map((stat) => (
                    <Card key={stat.label}>
                      <CardContent className="p-4 text-center">
                        <div className="text-2xl font-bold text-primary">{stat.value}</div>
                        <div className="text-xs text-muted-foreground mt-1">{stat.label}</div>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </motion.div>
            )}

            {/* Full Rules Section (non-condensed) */}
            <motion.div {...fadeIn}>
              <DrivingRulesSection />
            </motion.div>

            <Separator />

            {/* RTO Offices */}
            <motion.div {...fadeIn}>
              <NearestRtoSection />
            </motion.div>

            <Separator />

            {/* Government Links */}
            <motion.div {...fadeIn}>
              <GovernmentLinksSection />
            </motion.div>

            <Separator />

            {/* Tips */}
            {regulations?.tips && regulations.tips.length > 0 && (
              <motion.div {...fadeIn} className="space-y-4">
                <div className="flex items-center gap-2">
                  <Lightbulb className="h-5 w-5 text-primary" />
                  <h3 className="text-xl font-bold">Tips for License Applicants</h3>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {regulations.tips.map((tip, i) => (
                    <Card key={i}>
                      <CardContent className="p-4 flex gap-3">
                        <div className="w-7 h-7 rounded-full bg-primary/10 text-primary text-xs font-bold flex items-center justify-center shrink-0">
                          {i + 1}
                        </div>
                        <p className="text-sm text-muted-foreground leading-relaxed">
                          {tip}
                        </p>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </motion.div>
            )}

            <Separator />

            {/* CTA */}
            <motion.div {...fadeIn} className="text-center py-8">
              <h3 className="text-2xl font-bold mb-2">
                Ready to start learning?
              </h3>
              <p className="text-muted-foreground mb-6 max-w-md mx-auto">
                Find verified driving schools near you and start your driving
                journey with confidence.
              </p>
              <Button
                size="lg"
                onClick={() => navigate("/search")}
                className="gap-2"
              >
                Find Driving Schools
                <ArrowRight className="h-4 w-4" />
              </Button>
            </motion.div>
          </div>
        </div>
      </section>
    </PublicLayout>
  );
}
