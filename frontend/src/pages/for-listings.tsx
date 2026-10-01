import { Link } from "wouter";
import { motion } from "framer-motion";
import { useQuery } from "@tanstack/react-query";
import { BadgeCheck, Bell, CheckCircle2, MapPin, MessageCircle, Search, Star, Timer } from "lucide-react";
import { PublicLayout } from "@/components/layout/public-layout";
import { Button } from "@/components/ui/button";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { listPlans } from "@/lib/ops-api";
import { PLAN_LABEL, PLAN_MATRIX, formatInr } from "@/lib/plan";

const fadeIn = { initial: { opacity: 0, y: 16 }, whileInView: { opacity: 1, y: 0 }, viewport: { once: true }, transition: { duration: 0.4 } };

type Audience = "school" | "trainer";

const COPY: Record<Audience, {
  title: string;
  lead: string;
  cta: string;
  benefits: { icon: typeof Search; title: string; text: string }[];
  plans: string[];
  faq: [string, string][];
}> = {
  school: {
    title: "Get more learners for your driving school",
    lead: "Learners across Pune search DriveIQ for schools near them. List your school free, answer enquiries on WhatsApp or phone, and let your reviews do the talking.",
    cta: "List my school free",
    benefits: [
      { icon: Search, title: "Found by nearby learners", text: "Show up when learners in your locality search, compare fees and filter by car, bike, timing or women trainers." },
      { icon: MessageCircle, title: "Enquiries straight to you", text: "Learners message you on WhatsApp or call. Every enquiry lands in your dashboard with alerts and reminders." },
      { icon: Star, title: "Reviews from real learners", text: "Only enrolled learners and real enquirers can review you, so good work shows." },
      { icon: BadgeCheck, title: "Verified badge", text: "We check your phone, business and location. Verified schools rank higher." },
    ],
    plans: ["basic", "featured", "premium"],
    faq: [
      ["Is it really free?", "Yes. The Basic listing, enquiries, reminders and reviews are free for good. Paid plans add more visibility and tools to run learners, trainers, cars and payments."],
      ["We found you already. What is a claim link?", "If we emailed you, we prepared your listing from public details. Open the link, confirm the code we send to your listing's email or phone, and it is yours. Nothing is shown to learners until you claim it."],
      ["How long does it take?", "About five minutes: confirm your email, add your phone, locality and map location, and you go live."],
      ["Can my staff use it?", "Yes. Invite managers from the Team page; they handle enquiries while you keep owner controls."],
      ["What about my learners' data?", "It stays yours. We follow India's DPDP rules: consent is recorded and learners can ask for their data."],
    ],
  },
  trainer: {
    title: "Teach more learners as an independent trainer",
    lead: "Not part of a school? Learners on DriveIQ can find independent trainers too. Create a free profile and get enquiries directly.",
    cta: "Create my free trainer profile",
    benefits: [
      { icon: MapPin, title: "Learners near you", text: "Appear in search for your area, with your vehicle, timings and languages." },
      { icon: MessageCircle, title: "Direct enquiries", text: "Learners contact you by WhatsApp or phone. No middleman, no commission." },
      { icon: Star, title: "Build your reputation", text: "Collect reviews from learners you actually trained." },
      { icon: Timer, title: "Simple to run", text: "Track enquiries and follow-ups from your phone." },
    ],
    plans: ["basic", "featured"],
    faq: [
      ["Is it free?", "Yes. The basic profile and enquiries are free. Featured adds a ranking boost and a highlighted card."],
      ["Do women learners see women trainers?", "Yes. Tick \"woman trainer\" when you sign up and learners who want a woman trainer can find you."],
      ["We found you already. What is a claim link?", "If we emailed you, we prepared your profile from public details. Open the link and confirm the code we send to prove it is you. It stays hidden until you do."],
      ["Can I join a school later?", "Not yet from the same account. Write to us and we will help you move."],
    ],
  },
};

/** DIQ-1108: public pages that invite schools and trainers to list themselves. */
export default function ForListingsPage({ audience }: { audience: Audience }) {
  const c = COPY[audience];
  const plans = useQuery({ queryKey: ["plans"], queryFn: listPlans });
  const registerHref = `/auth/register?type=${audience}`;

  return (
    <PublicLayout>
      <section className="bg-gradient-to-br from-primary/10 via-background to-accent/10 py-16 md:py-24">
        <div className="container mx-auto px-4 max-w-4xl text-center">
          <motion.div {...fadeIn}>
            <h1 className="text-4xl md:text-5xl font-bold tracking-tight" data-testid="text-landing-title">{c.title}</h1>
            <p className="text-lg text-muted-foreground mt-4 max-w-2xl mx-auto">{c.lead}</p>
            <div className="mt-8 flex flex-wrap justify-center gap-3">
              <Link href={registerHref}><Button size="lg" data-testid="button-landing-signup">{c.cta}</Button></Link>
              <Link href={audience === "school" ? "/for-trainers" : "/for-schools"}>
                <Button size="lg" variant="outline">{audience === "school" ? "I'm an independent trainer" : "I run a driving school"}</Button>
              </Link>
            </div>
            <p className="text-xs text-muted-foreground mt-3">Free forever on Basic · 30-day trial of every tool · No card needed</p>
          </motion.div>
        </div>
      </section>

      <section className="py-16">
        <div className="container mx-auto px-4 max-w-5xl grid sm:grid-cols-2 gap-6">
          {c.benefits.map((b) => (
            <motion.div key={b.title} {...fadeIn} className="rounded-xl border bg-card p-6 flex gap-4">
              <div className="h-10 w-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0"><b.icon className="h-5 w-5" /></div>
              <div>
                <h2 className="font-semibold">{b.title}</h2>
                <p className="text-sm text-muted-foreground mt-1">{b.text}</p>
              </div>
            </motion.div>
          ))}
        </div>
      </section>

      <section className="py-16 bg-muted/30">
        <div className="container mx-auto px-4 max-w-4xl">
          <h2 className="text-2xl font-bold text-center mb-8">How it works</h2>
          <ol className="grid md:grid-cols-3 gap-6 text-sm">
            {[
              ["Sign up or claim", "Create your account, or open the claim link from our email."],
              ["Complete your profile", "Confirm your email, add phone, locality and your spot on the map. You go live by yourself."],
              ["Answer enquiries", "Get alerts for every new enquiry and a reminder if one is waiting."],
            ].map(([t, d], i) => (
              <li key={t} className="rounded-xl border bg-card p-5">
                <div className="text-primary font-bold">0{i + 1}</div>
                <div className="font-semibold mt-1">{t}</div>
                <p className="text-muted-foreground mt-1">{d}</p>
              </li>
            ))}
          </ol>
        </div>
      </section>

      <section className="py-16">
        <div className="container mx-auto px-4 max-w-5xl">
          <h2 className="text-2xl font-bold text-center mb-2">Plans</h2>
          <p className="text-center text-sm text-muted-foreground mb-8">Prices per month, before GST.</p>
          <div className={`grid gap-4 ${c.plans.length === 3 ? "md:grid-cols-3" : "md:grid-cols-2 max-w-3xl mx-auto"}`}>
            {PLAN_MATRIX.filter((p) => c.plans.includes(p.code)).map((p) => {
              const price = plans.data?.find((x) => x.code === p.code)?.priceMonthly;
              return (
                <div key={p.code} className={`rounded-xl border bg-card p-6 ${p.code === "featured" ? "ring-2 ring-primary/40" : ""}`} data-testid={`landing-plan-${p.code}`}>
                  <div className="font-semibold">{PLAN_LABEL[p.code]}</div>
                  <div className="text-2xl font-bold mt-1">{p.code === "basic" ? "Free" : price != null ? formatInr(price) : "—"}</div>
                  <ul className="mt-4 space-y-2 text-sm">
                    {p.highlights.map((h) => (
                      <li key={h} className="flex gap-2"><CheckCircle2 className="h-4 w-4 text-green-600 shrink-0 mt-0.5" /> {h}</li>
                    ))}
                  </ul>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      <section className="py-16 bg-muted/30">
        <div className="container mx-auto px-4 max-w-3xl">
          <h2 className="text-2xl font-bold text-center mb-6">Questions</h2>
          <Accordion type="single" collapsible className="rounded-xl border bg-card px-4">
            {c.faq.map(([q, a]) => (
              <AccordionItem key={q} value={q}>
                <AccordionTrigger className="text-left">{q}</AccordionTrigger>
                <AccordionContent className="text-muted-foreground">{a}</AccordionContent>
              </AccordionItem>
            ))}
          </Accordion>
          <div className="text-center mt-10">
            <Link href={registerHref}><Button size="lg">{c.cta}</Button></Link>
            <p className="text-xs text-muted-foreground mt-3 flex items-center justify-center gap-1">
              <Bell className="h-3.5 w-3.5" /> Questions first? <Link href="/contact" className="underline">Write to us</Link>
            </p>
          </div>
        </div>
      </section>
    </PublicLayout>
  );
}
