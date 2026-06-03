import { useParams } from "wouter";
import { motion } from "framer-motion";
import { PublicLayout } from "@/components/layout/public-layout";
import { SchoolCard } from "@/components/school-card";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { useGetLocalityBySlug, useListSchools } from "@/api-client";
import { MapPin, ArrowLeft } from "lucide-react";
import { Link } from "wouter";

export default function LocalityDetailPage() {
  const params = useParams<{ slug: string }>();
  const { data: locality, isLoading: localityLoading } = useGetLocalityBySlug(params.slug);
  const { data: schools, isLoading: schoolsLoading } = useListSchools({ locality: params.slug });

  return (
    <PublicLayout>
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
                <Badge variant="secondary">{locality?.schoolCount || schools?.length || 0} Schools</Badge>
              </div>
              <h1 className="text-3xl md:text-4xl font-bold">Driving Schools in {locality?.name || params.slug}</h1>
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
                <SchoolCard school={school} />
              </motion.div>
            ))}
          </motion.div>
        )}
      </div>
    </PublicLayout>
  );
}
