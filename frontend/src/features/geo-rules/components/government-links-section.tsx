import { motion } from "framer-motion";
import { Card, CardContent } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { ExternalLink, Globe, ShieldCheck } from "lucide-react";
import { useRegulationData } from "../hooks/use-regulation-data";
import type { GovernmentLink } from "../types";

const fadeIn = {
  initial: { opacity: 0, y: 20 } as const,
  animate: { opacity: 1, y: 0 } as const,
};

const stagger = {
  animate: { transition: { staggerChildren: 0.08 } },
};

const categoryLabels: Record<GovernmentLink["category"], string> = {
  sarathi: "Licensing",
  parivahan: "Transport",
  state: "State Govt",
  rto: "RTO",
  other: "Resource",
};

interface GovernmentLinksSectionProps {
  limit?: number;
}

export function GovernmentLinksSection({ limit }: GovernmentLinksSectionProps) {
  const { governmentLinks } = useRegulationData();

  if (governmentLinks.length === 0) return null;

  const links = limit ? governmentLinks.slice(0, limit) : governmentLinks;

  return (
    <motion.div
      className="space-y-4"
      initial="initial"
      animate="animate"
      variants={stagger}
    >
      <motion.div variants={fadeIn} className="flex items-center gap-2">
        <Globe className="h-5 w-5 text-primary" />
        <h3 className="text-xl font-bold">Official Government Resources</h3>
      </motion.div>

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {links.map((link) => (
          <motion.div key={link.url} variants={fadeIn}>
            <a
              href={link.url}
              target="_blank"
              rel="noopener noreferrer"
              className="block h-full"
            >
              <Card className="h-full transition-all hover:shadow-md hover:border-primary/30 group">
                <CardContent className="p-4 space-y-2">
                  <div className="flex items-start justify-between gap-2">
                    <h4 className="font-semibold text-sm group-hover:text-primary transition-colors">
                      {link.label}
                    </h4>
                    <ExternalLink className="h-4 w-4 text-muted-foreground shrink-0 group-hover:text-primary transition-colors" />
                  </div>
                  {link.description && (
                    <p className="text-xs text-muted-foreground leading-relaxed">
                      {link.description}
                    </p>
                  )}
                  <div className="flex items-center gap-2">
                    <Badge variant="outline" className="text-xs font-normal">
                      {categoryLabels[link.category]}
                    </Badge>
                    <div className="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                      <ShieldCheck className="h-3 w-3" />
                      <span>Official</span>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </a>
          </motion.div>
        ))}
      </div>
    </motion.div>
  );
}
