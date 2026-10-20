import { useQuery } from "@tanstack/react-query";
import { Badge } from "@/components/ui/badge";
import { fetchPublicTrainers } from "@/lib/ops-api";
import { UserRound } from "lucide-react";

/**
 * "Meet our trainers" (DIQ-911). Only trainers the school marked public and
 * active are returned; no phone or email is ever shown here.
 */
export function TrainersSection({ slug }: { slug: string }) {
  const { data } = useQuery({ queryKey: ["public-trainers", slug], queryFn: () => fetchPublicTrainers(slug), staleTime: 5 * 60_000 });
  if (!data?.length) return null;

  return (
    <section className="rounded-xl border bg-card p-6" data-testid="section-trainers">
      <h2 className="font-bold text-lg mb-4">Meet our trainers</h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {data.map((t) => (
          <div key={t.id} className="flex gap-3 rounded-lg border p-4" data-testid={`card-trainer-${t.id}`}>
            {t.photoUrl ? (
              <img src={t.photoUrl} alt="" className="h-12 w-12 rounded-full object-cover shrink-0" loading="lazy" />
            ) : (
              <div className="h-12 w-12 rounded-full bg-muted flex items-center justify-center shrink-0">
                <UserRound className="h-6 w-6 text-muted-foreground" />
              </div>
            )}
            <div className="min-w-0 space-y-1.5">
              <div className="font-semibold">{t.name}</div>
              <div className="text-xs text-muted-foreground">
                {[t.yearsExperience ? `${t.yearsExperience} yrs experience` : null, t.languages.join(", ") || null].filter(Boolean).join(" · ")}
              </div>
              {t.bio && <p className="text-sm text-muted-foreground line-clamp-3">{t.bio}</p>}
              <div className="flex flex-wrap gap-1.5">
                {t.womenInstructor && <Badge variant="outline" className="text-xs">Woman trainer</Badge>}
                {t.skills.slice(0, 4).map((s) => <Badge key={s} variant="secondary" className="text-xs">{s}</Badge>)}
              </div>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
}
