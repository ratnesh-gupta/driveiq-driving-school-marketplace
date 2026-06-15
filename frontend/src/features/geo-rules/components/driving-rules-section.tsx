import { motion } from "framer-motion";
import { BookOpen, CheckCircle2 } from "lucide-react";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/components/ui/accordion";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { LegalDisclaimer } from "@/components/legal-disclaimer";
import { useRegulationData } from "../hooks/use-regulation-data";

const fadeIn = {
  initial: { opacity: 0, y: 20 } as const,
  animate: { opacity: 1, y: 0 } as const,
};

const stagger = {
  animate: { transition: { staggerChildren: 0.1 } },
};

interface DrivingRulesSectionProps {
  condensed?: boolean;
}

export function DrivingRulesSection({ condensed = false }: DrivingRulesSectionProps) {
  const { regulations } = useRegulationData();

  if (!regulations) return null;

  return (
    <motion.div
      className="space-y-6"
      initial="initial"
      animate="animate"
      variants={stagger}
    >
      <motion.div variants={fadeIn} className="flex items-center gap-2">
        <BookOpen className="h-5 w-5 text-primary" />
        <h3 className="text-xl font-bold">Driving Rules & Requirements</h3>
      </motion.div>

      {/* Age Requirements */}
      <motion.div variants={fadeIn} className="rounded-xl border bg-card overflow-hidden">
        <div className="p-4 border-b bg-muted/30">
          <h4 className="font-semibold">Minimum Age Requirements</h4>
        </div>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Vehicle Type</TableHead>
              <TableHead className="text-center">Min. Age</TableHead>
              <TableHead className="hidden sm:table-cell">Notes</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {regulations.minimumAge.map((rule) => (
              <TableRow key={rule.vehicleType}>
                <TableCell className="font-medium">{rule.vehicleType}</TableCell>
                <TableCell className="text-center">
                  <Badge variant="secondary">{rule.minimumAge} yrs</Badge>
                </TableCell>
                <TableCell className="hidden sm:table-cell text-muted-foreground text-sm">
                  {rule.notes || "—"}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </motion.div>

      {/* License Process */}
      {!condensed && (
        <motion.div variants={fadeIn}>
          <Accordion type="single" collapsible className="space-y-2">
            <h4 className="font-semibold mb-3">License Process (Step by Step)</h4>
            {regulations.licenseProcess.map((step) => (
              <AccordionItem
                key={step.step}
                value={`step-${step.step}`}
                className="rounded-lg border bg-card px-4"
              >
                <AccordionTrigger className="hover:no-underline">
                  <div className="flex items-center gap-3 text-left">
                    <div className="w-7 h-7 rounded-full bg-primary text-primary-foreground text-xs font-bold flex items-center justify-center shrink-0">
                      {step.step}
                    </div>
                    <div>
                      <span className="font-medium">{step.title}</span>
                      {step.duration && (
                        <span className="ml-2 text-xs text-muted-foreground">
                          ({step.duration})
                        </span>
                      )}
                    </div>
                  </div>
                </AccordionTrigger>
                <AccordionContent className="pl-10 text-muted-foreground">
                  {step.description}
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        </motion.div>
      )}

      {/* Documents Required */}
      {!condensed && (
        <motion.div variants={fadeIn}>
          <Accordion type="single" collapsible className="space-y-2">
            <h4 className="font-semibold mb-3">Documents Required</h4>
            {regulations.documentsRequired.map((docSet) => (
              <AccordionItem
                key={docSet.licenseType}
                value={docSet.licenseType}
                className="rounded-lg border bg-card px-4"
              >
                <AccordionTrigger className="hover:no-underline font-medium">
                  {docSet.licenseType}
                </AccordionTrigger>
                <AccordionContent>
                  <ul className="space-y-2">
                    {docSet.documents.map((doc) => (
                      <li key={doc} className="flex items-start gap-2 text-sm text-muted-foreground">
                        <CheckCircle2 className="h-4 w-4 mt-0.5 text-green-500 shrink-0" />
                        <span>{doc}</span>
                      </li>
                    ))}
                  </ul>
                </AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
        </motion.div>
      )}

      {/* Fee Schedule */}
      {!condensed && (
        <motion.div variants={fadeIn} className="rounded-xl border bg-card overflow-hidden">
          <div className="p-4 border-b bg-muted/30">
            <h4 className="font-semibold">Fee Schedule</h4>
          </div>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Service</TableHead>
                <TableHead className="text-right">Fee</TableHead>
                <TableHead className="hidden sm:table-cell">Notes</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {regulations.fees.map((fee) => (
                <TableRow key={fee.item}>
                  <TableCell className="font-medium">{fee.item}</TableCell>
                  <TableCell className="text-right font-semibold">{fee.amount}</TableCell>
                  <TableCell className="hidden sm:table-cell text-muted-foreground text-sm">
                    {fee.notes || "—"}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </motion.div>
      )}

      <motion.div variants={fadeIn}>
        <LegalDisclaimer className="mt-4" />
      </motion.div>
    </motion.div>
  );
}
