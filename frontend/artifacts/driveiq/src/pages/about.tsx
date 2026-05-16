import { motion } from "framer-motion";
import { PublicLayout } from "@/components/layout/public-layout";
import { ShieldCheck, Users, MapPin, Star, Target, Zap, Heart } from "lucide-react";

const fadeIn = { initial: { opacity: 0, y: 20 }, whileInView: { opacity: 1, y: 0 }, viewport: { once: true }, transition: { duration: 0.5 } };

export default function AboutPage() {
  return (
    <PublicLayout>
      <section className="bg-gradient-to-br from-primary/5 to-accent/10 py-20">
        <div className="container mx-auto px-4 text-center max-w-3xl">
          <motion.div {...fadeIn}>
            <h1 className="text-4xl md:text-5xl font-bold mb-4">About DriveIQ</h1>
            <p className="text-xl text-muted-foreground leading-relaxed">
              We're building the most trusted driving school discovery platform in India — starting right here in Pune.
            </p>
          </motion.div>
        </div>
      </section>

      <section className="py-16">
        <div className="container mx-auto px-4 max-w-4xl">
          <motion.div {...fadeIn} className="grid grid-cols-1 md:grid-cols-2 gap-12 items-center mb-20">
            <div>
              <h2 className="text-3xl font-bold mb-4">Our Mission</h2>
              <p className="text-muted-foreground leading-relaxed mb-4">
                Learning to drive is a rite of passage. But finding the right school — one that's trustworthy, affordable, and convenient — has always been a frustrating experience of word-of-mouth and guesswork.
              </p>
              <p className="text-muted-foreground leading-relaxed">
                DriveIQ changes that. We verify every school, aggregate honest reviews, and make it easy to compare, connect, and enroll — all from your phone.
              </p>
            </div>
            <div className="grid grid-cols-2 gap-4">
              {[
                { icon: Target, label: "Our Goal", desc: "Make learning to drive accessible and transparent for everyone." },
                { icon: ShieldCheck, label: "Verified Only", desc: "Every school is personally verified before it appears on DriveIQ." },
                { icon: Heart, label: "Community First", desc: "We're built for learners, by people who care about safety." },
                { icon: Zap, label: "Fast & Local", desc: "Hyper-local search so you find schools that actually serve your area." },
              ].map((item) => (
                <div key={item.label} className="rounded-xl border bg-card p-4">
                  <item.icon className="h-6 w-6 text-primary mb-2" />
                  <div className="font-semibold text-sm mb-1">{item.label}</div>
                  <div className="text-xs text-muted-foreground">{item.desc}</div>
                </div>
              ))}
            </div>
          </motion.div>

          <motion.div {...fadeIn} className="text-center mb-12">
            <h2 className="text-3xl font-bold mb-4">Why DriveIQ?</h2>
            <p className="text-muted-foreground max-w-xl mx-auto">We're not a classifieds site or a directory. We're a curated, geo-intelligent platform built around trust.</p>
          </motion.div>

          <motion.div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6" {...fadeIn}>
            {[
              { icon: ShieldCheck, title: "Verified Schools", desc: "All schools go through our manual verification process before listing." },
              { icon: Users, title: "Women Instructors", desc: "We flag schools with certified women instructors for learners who prefer them." },
              { icon: MapPin, title: "Geo-Aware", desc: "Find schools closest to you across 12+ localities in Pune." },
              { icon: Star, title: "Real Reviews", desc: "Authentic student reviews — no fake ratings, no paid placements." },
            ].map((item) => (
              <div key={item.title} className="text-center p-6 rounded-xl border bg-card hover:shadow-sm transition-all">
                <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center mx-auto mb-4">
                  <item.icon className="h-6 w-6 text-primary" />
                </div>
                <h3 className="font-semibold mb-2">{item.title}</h3>
                <p className="text-sm text-muted-foreground leading-relaxed">{item.desc}</p>
              </div>
            ))}
          </motion.div>
        </div>
      </section>

      <section className="py-16 bg-muted/30">
        <div className="container mx-auto px-4 text-center max-w-2xl">
          <motion.div {...fadeIn}>
            <h2 className="text-3xl font-bold mb-4">We're just getting started</h2>
            <p className="text-muted-foreground leading-relaxed">
              DriveIQ launched in Pune but we're building the infrastructure for all of India. If you run a driving school and want to reach more students, <a href="/auth/register" className="text-primary font-medium hover:underline">partner with us today</a>.
            </p>
          </motion.div>
        </div>
      </section>
    </PublicLayout>
  );
}
