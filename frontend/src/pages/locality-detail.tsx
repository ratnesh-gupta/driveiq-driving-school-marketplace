import { useParams } from "wouter";
import { motion } from "framer-motion";
import { PublicLayout } from "@/components/layout/public-layout";
import { SchoolCard } from "@/components/school-card";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { useGetLocalityBySlug, useListSchools } from "@/api-client";
import { MapPin, ArrowLeft, BookOpen, ArrowRight } from "lucide-react";
import { Link, useLocation } from "wouter";
import { CompareBar } from "@/features/comparison/components/compare-bar";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { JsonLd, SITE_ORIGIN, breadcrumbList } from "@/components/seo/json-ld";
import type { School } from "@/api-client/generated/api.schemas";

/**
 * Locality FAQ built only from the schools listed on the page, so the visible
 * answers and the FAQPage structured data (DIQ-504) never claim more than we show.
 */
function localityFaqs(localityName: string, total: number, schools: School[]) {
  const prices = schools.map((s) => s.priceFrom).filter((p) => p > 0);
  const women = schools.filter((s) => s.womenInstructor).length;
  const pickup = schools.filter((s) => s.hasPickup).length;
  const verified = schools.filter((s) => s.verified).length;
  const faqs = [
    {
      q: `How many driving schools are listed in ${localityName}?`,
      a: `${total} driving school${total === 1 ? " is" : "s are"} listed in ${localityName} on DriveQ${verified ? `, ${verified} of them verified` : ""}.`,
    },
  ];
  if (prices.length) {
    faqs.push({
      q: `How much do driving lessons cost in ${localityName}?`,
      a: `Courses listed here start from ₹${Math.min(...prices).toLocaleString("en-IN")}${prices.length > 1 ? ` (up to ₹${Math.max(...prices).toLocaleString("en-IN")} for the starting package)` : ""}. Compare packages on each school's page.`,
    });
  }
  faqs.push({
    q: `Are there driving schools with women instructors in ${localityName}?`,
    a: women
      ? `Yes — ${women} of the schools listed here have a woman instructor. Use the "Women instructor" filter on search.`
      : `None of the schools listed here currently mention a woman instructor. Try nearby localities with the "Women instructor" filter on search.`,
  });
  faqs.push({
    q: `Do driving schools in ${localityName} offer pickup and drop?`,
    a: pickup
      ? `${pickup} of the schools listed here offer pickup and drop.`
      : `None of the schools listed here currently offer pickup and drop.`,
  });
  return faqs;
}

export default function LocalityDetailPage() {
  const params = useParams<{ slug: string }>();
  const [, navigate] = useLocation();
  const { data: locality, isLoading: localityLoading } = useGetLocalityBySlug(params.slug);
  const { data: schools, isLoading: schoolsLoading } = useListSchools({ locality: params.slug });

  const localityName = locality?.name || params.slug.charAt(0).toUpperCase() + params.slug.slice(1);
  const total = locality?.schoolCount || schools?.length || 0;
  const faqs = schools?.length ? localityFaqs(localityName, total, schools) : [];
  const pagePath = `/locality/${params.slug}`;

  return (
    <PublicLayout>
      <JsonLd data={breadcrumbList([
        { name: "Home", path: "/" },
        { name: "Driving schools", path: "/search" },
        { name: localityName, path: pagePath },
      ])} />
      {!!schools?.length && (
        <JsonLd data={{
          "@context": "https://schema.org",
          "@type": "ItemList",
          name: `Driving Schools in ${localityName}`,
          itemListElement: schools.map((school, i) => ({
            "@type": "ListItem",
            position: i + 1,
            url: `${SITE_ORIGIN}/school/${school.slug}`,
            name: school.name,
          })),
        }} />
      )}
      {faqs.length > 0 && (
        <JsonLd data={{
          "@context": "https://schema.org",
          "@type": "FAQPage",
          mainEntity: faqs.map((f) => ({ "@type": "Question", name: f.q, acceptedAnswer: { "@type": "Answer", text: f.a } })),
        }} />
      )}
      <div className="bg-gradient-to-br from-primary/10 to-accent/10 border-b py-12">
        <div className="container mx-auto px-4">
          <Link href="/search" className="text-muted-foreground hover:text-foreground text-sm flex items-center gap-1 mb-4 w-fit">
            <ArrowLeft className="h-4 w-4" /> Back to Search
          </Link>
          {localityLoading ? (
            <Skeleton className="h-12 w-64" />
          ) : (
            <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
              <div className="flex items-center gap-3 mb-2">
                <div className="w-10 h-10 rounded-xl bg-primary/15 flex items-center justify-center">
                  <MapPin className="h-5 w-5 text-primary" />
                </div>
                <Badge variant="secondary">{total} Schools</Badge>
              </div>
              <h1 className="text-3xl md:text-4xl font-bold">Driving Schools in {localityName}</h1>
              {locality?.description && (
                <p className="text-muted-foreground mt-2 max-w-xl">{locality.description}</p>
              )}
            </motion.div>
          )}
        </div>
      </div>

      <div className="container mx-auto px-4 py-10">
        {schoolsLoading ? (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-72 rounded-xl" />)}
          </div>
        ) : !schools?.length ? (
          <div className="text-center py-16 text-muted-foreground">
            <MapPin className="h-12 w-12 mx-auto mb-4 opacity-20" />
            <p>No schools found in this locality yet.</p>
          </div>
        ) : (
          <motion.div
            className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
            initial="initial"
            animate="animate"
            variants={{ animate: { transition: { staggerChildren: 0.08 } } }}
          >
            {schools.map((school) => (
              <motion.div key={school.id} initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} data-testid={`card-school-${school.id}`}>
                <SchoolCard school={school} showCompare />
              </motion.div>
            ))}
          </motion.div>
        )}

        {faqs.length > 0 && (
          <div className="mt-10 rounded-xl border bg-card p-6">
            <h2 className="font-bold text-lg mb-4">Driving schools in {localityName}: FAQs</h2>
            <Accordion type="single" collapsible>
              {faqs.map((faq, i) => (
                <AccordionItem key={i} value={`locality-faq-${i}`}>
                  <AccordionTrigger className="text-sm font-medium text-left">{faq.q}</AccordionTrigger>
                  <AccordionContent className="text-sm text-muted-foreground">{faq.a}</AccordionContent>
                </AccordionItem>
              ))}
            </Accordion>
          </div>
        )}

        {/* Compact CTA to driving rules page */}
        <motion.div
          className="mt-10 rounded-xl border bg-card p-6 flex flex-col sm:flex-row items-center gap-4"
          initial={{ opacity: 0, y: 20 }}
          whileInView={{ opacity: 1, y: 0 }}
          viewport={{ once: true }}
          transition={{ duration: 0.5 }}
        >
          <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
            <BookOpen className="h-6 w-6 text-primary" />
          </div>
          <div className="flex-1 text-center sm:text-left">
            <h3 className="font-semibold">Driving Rules & License Guide for {localityName}</h3>
            <p className="text-sm text-muted-foreground mt-0.5">
              RTO offices, age requirements, documents needed, fees & step-by-step process
            </p>
          </div>
          <Button variant="outline" onClick={() => navigate("/driving-rules")} className="shrink-0 gap-2">
            View Guide <ArrowRight className="h-4 w-4" />
          </Button>
        </motion.div>
      </div>
      <CompareBar />
    </PublicLayout>
  );
}
