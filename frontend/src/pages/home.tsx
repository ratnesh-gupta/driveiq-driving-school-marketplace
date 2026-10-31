import { useState } from "react";
import { useLocation } from "wouter";
import { motion } from "framer-motion";
import { PublicLayout } from "@/components/layout/public-layout";
import { SchoolCard } from "@/components/school-card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { useListFeaturedSchools, useListLocalities } from "@/api-client";
import { Search, MapPin, Car, ShieldCheck, Users, Star, ArrowRight, CheckCircle2, Zap, MessageCircle, BookOpen, Navigation } from "lucide-react";

const LOCALITIES = [
  { name: "Baner", slug: "baner" },
  { name: "Hinjewadi", slug: "hinjewadi" },
  { name: "Wakad", slug: "wakad" },
  { name: "Pimpri-Chinchwad", slug: "pimpri-chinchwad" },
];
const VEHICLE_TYPES = ["Car", "Bike", "Scooter", "Heavy Vehicle"];

const fadeIn = {
  initial: { opacity: 0, y: 24 },
  animate: { opacity: 1, y: 0 },
  transition: { duration: 0.5 },
};

const stagger = {
  animate: { transition: { staggerChildren: 0.1 } },
};

export default function HomePage() {
  const [searchLocality, setSearchLocality] = useState("");
  const [vehicleType, setVehicleType] = useState("");
  const [, setLocation] = useLocation();

  const { data: featuredSchools, isLoading: loadingSchools } = useListFeaturedSchools();
  const { data: localities, isLoading: loadingLocalities } = useListLocalities();

  const handleSearch = () => {
    const params = new URLSearchParams();
    if (searchLocality) params.set("locality", searchLocality.toLowerCase());
    if (vehicleType) params.set("vehicleType", vehicleType);
    setLocation(`/search?${params.toString()}`);
  };

  return (
    <PublicLayout>
      {/* Hero */}
      <section className="relative bg-gradient-to-br from-[hsl(221,83%,12%)] via-[hsl(221,83%,18%)] to-[hsl(258,60%,22%)] text-white overflow-hidden min-h-[600px] flex items-center">
        {/* Background grid */}
        <div className="absolute inset-0 opacity-10" style={{
          backgroundImage: 'linear-gradient(rgba(255,255,255,0.1) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.1) 1px, transparent 1px)',
          backgroundSize: '40px 40px'
        }} />
        <div className="absolute inset-0 bg-gradient-to-t from-[hsl(221,83%,8%)/60] to-transparent" />

        <div className="container mx-auto px-4 relative z-10 py-20">
          <motion.div className="max-w-2xl" initial="initial" animate="animate" variants={stagger}>
            <motion.div variants={fadeIn}>
              <Badge className="mb-6 bg-white/10 text-white border-white/20 hover:bg-white/20 px-3 py-1">
                <MapPin className="h-3 w-3 mr-1" /> Pune's driving school marketplace
              </Badge>
            </motion.div>

            <motion.h1 className="text-4xl md:text-5xl lg:text-6xl font-bold leading-tight mb-6" variants={fadeIn}>
              Find Driving Schools{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-300 to-purple-300">Near You</span>
            </motion.h1>

            <motion.p className="text-xl text-white/75 mb-10 max-w-lg leading-relaxed" variants={fadeIn}>
              Compare pricing, instructors, pickup availability and reviews from driving schools in Pune.
            </motion.p>

            {/* Search bar */}
            <motion.div className="bg-white/10 backdrop-blur-md rounded-2xl p-3 border border-white/20 shadow-2xl" variants={fadeIn}>
              <div className="flex flex-col md:flex-row gap-3">
                <div className="relative flex-1">
                  <MapPin className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-white/50" />
                  <Input
                    placeholder="Search locality (e.g. Baner, Wakad)"
                    value={searchLocality}
                    onChange={(e) => setSearchLocality(e.target.value)}
                    onKeyDown={(e) => e.key === "Enter" && handleSearch()}
                    className="pl-9 bg-white/10 border-white/20 text-white placeholder:text-white/50 focus-visible:ring-white/30 h-11"
                    data-testid="input-search-locality"
                  />
                </div>
                <Select value={vehicleType} onValueChange={setVehicleType}>
                  <SelectTrigger className="bg-white/10 border-white/20 text-white h-11 w-full md:w-44 [&>svg]:text-white/50">
                    <Car className="h-4 w-4 mr-2 text-white/50" />
                    <SelectValue placeholder="Vehicle Type" />
                  </SelectTrigger>
                  <SelectContent>
                    {VEHICLE_TYPES.map(v => <SelectItem key={v} value={v}>{v}</SelectItem>)}
                  </SelectContent>
                </Select>
                <Button onClick={handleSearch} size="lg" variant="inverse" className="h-11 px-6" data-testid="button-search">
                  <Search className="h-4 w-4 mr-2" /> Search
                </Button>
                <Button
                  onClick={() => setLocation("/search?near=1")}
                  size="lg"
                  variant="outline"
                  className="h-11 px-5 border-white/40 text-white"
                  data-testid="button-near-me"
                >
                  <Navigation className="h-4 w-4 mr-2" /> Near me
                </Button>
              </div>
            </motion.div>

            <motion.div className="flex flex-wrap gap-3 mt-6" variants={fadeIn}>
              {LOCALITIES.map((loc) => (
                <button
                  key={loc.slug}
                  onClick={() => setLocation(`/search?locality=${loc.slug}`)}
                  className="text-sm bg-white/10 hover:bg-white/20 border border-white/20 rounded-full px-4 py-2 min-h-10 text-white/90 transition-colors"
                  data-testid={`button-quick-locality-${loc.slug}`}
                >
                  {loc.name}
                </button>
              ))}
            </motion.div>
          </motion.div>
        </div>

      </section>

      {/* Popular Localities */}
      <section className="py-16 bg-background">
        <div className="container mx-auto px-4">
          <motion.div
            className="flex items-end justify-between mb-10"
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            <div>
              <h2 className="text-3xl font-bold">Popular Localities</h2>
              <p className="text-muted-foreground mt-1">Find schools in your neighbourhood</p>
            </div>
            <Button variant="ghost" onClick={() => setLocation("/search")} className="hidden md:flex gap-1">
              View all <ArrowRight className="h-4 w-4" />
            </Button>
          </motion.div>

          {loadingLocalities ? (
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
              {Array.from({ length: 6 }).map((_, i) => (
                <Skeleton key={i} className="h-28 rounded-xl" />
              ))}
            </div>
          ) : (
            <motion.div
              className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4"
              initial="initial"
              whileInView="animate"
              viewport={{ once: true }}
              variants={stagger}
            >
              {(localities || []).slice(0, 6).map((loc) => (
                <motion.button
                  key={loc.id}
                  variants={fadeIn}
                  whileHover={{ y: -4, transition: { duration: 0.2 } }}
                  onClick={() => setLocation(`/locality/${loc.slug}`)}
                  className="group rounded-xl border bg-card p-5 text-left hover:border-primary hover:shadow-md transition-all"
                  data-testid={`button-locality-${loc.slug}`}
                >
                  <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center mb-3 group-hover:bg-primary/20 transition-colors">
                    <MapPin className="h-5 w-5 text-primary" />
                  </div>
                  <div className="font-semibold">{loc.name}</div>
                  <div className="text-sm text-muted-foreground mt-0.5">{loc.schoolCount} schools</div>
                </motion.button>
              ))}
            </motion.div>
          )}
        </div>
      </section>

      {/* Featured Schools */}
      <section className="py-16 bg-muted/30">
        <div className="container mx-auto px-4">
          <motion.div
            className="flex items-end justify-between mb-10"
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            <div>
              <h2 className="text-3xl font-bold">Featured Driving Schools</h2>
              <p className="text-muted-foreground mt-1">Schools listed on DriveQ in Pune</p>
            </div>
            <Button variant="ghost" onClick={() => setLocation("/search")} className="hidden md:flex gap-1">
              View all <ArrowRight className="h-4 w-4" />
            </Button>
          </motion.div>

          {loadingSchools ? (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {Array.from({ length: 3 }).map((_, i) => (
                <Skeleton key={i} className="h-72 rounded-xl" />
              ))}
            </div>
          ) : (
            <motion.div
              className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
              initial="initial"
              whileInView="animate"
              viewport={{ once: true }}
              variants={stagger}
            >
              {(featuredSchools || []).map((school, index) => (
                <motion.div
                  key={school.id ?? school.slug ?? `featured-school-${index}`}
                  variants={fadeIn}
                  whileHover={{ y: -4, transition: { duration: 0.2 } }}
                >
                  <SchoolCard school={school} />
                </motion.div>
              ))}
            </motion.div>
          )}

          <div className="text-center mt-8">
            <Button size="lg" onClick={() => setLocation("/search")} data-testid="button-view-all-schools">
              Explore All Schools <ArrowRight className="h-4 w-4 ml-2" />
            </Button>
          </div>
        </div>
      </section>

      {/* How It Works */}
      <section className="py-16 bg-background">
        <div className="container mx-auto px-4">
          <motion.div
            className="text-center mb-12"
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            <h2 className="text-3xl font-bold">How DriveQ Works</h2>
            <p className="text-muted-foreground mt-2 max-w-xl mx-auto">Find your perfect driving school in three simple steps</p>
          </motion.div>

          <motion.div
            className="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto"
            initial="initial"
            whileInView="animate"
            viewport={{ once: true }}
            variants={stagger}
          >
            {[
              { icon: Search, step: "01", title: "Search Nearby Schools", desc: "Enter your locality and vehicle type, or use your location, to see schools nearby." },
              { icon: Star, step: "02", title: "Compare & Review", desc: "Read reviews from learners who enquired or enrolled, compare pricing packages, and check instructors and pickup." },
              { icon: MessageCircle, step: "03", title: "Connect Instantly", desc: "Submit an inquiry or reach out on WhatsApp. Get started with your preferred driving school." },
            ].map((step) => (
              <motion.div
                key={step.step}
                variants={fadeIn}
                className="relative text-center p-8 rounded-2xl border bg-card hover:shadow-md transition-all"
              >
                <div className="absolute -top-4 left-1/2 -translate-x-1/2">
                  <div className="w-8 h-8 rounded-full bg-primary text-primary-foreground text-xs font-bold flex items-center justify-center shadow-lg">
                    {step.step}
                  </div>
                </div>
                <div className="w-14 h-14 rounded-2xl bg-primary/10 flex items-center justify-center mx-auto mb-4 mt-2">
                  <step.icon className="h-7 w-7 text-primary" />
                </div>
                <h3 className="font-bold text-lg mb-2">{step.title}</h3>
                <p className="text-muted-foreground text-sm leading-relaxed">{step.desc}</p>
              </motion.div>
            ))}
          </motion.div>
        </div>
      </section>

      {/* Driving Rules CTA */}
      <section className="py-16 bg-muted/30">
        <div className="container mx-auto px-4">
          <motion.div
            className="max-w-4xl mx-auto rounded-2xl border bg-card p-8 md:p-12 flex flex-col md:flex-row items-center gap-8"
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            <div className="w-16 h-16 rounded-2xl bg-primary/10 flex items-center justify-center shrink-0">
              <BookOpen className="h-8 w-8 text-primary" />
            </div>
            <div className="flex-1 text-center md:text-left">
              <h2 className="text-2xl font-bold mb-2">Driving Rules & License Guidance</h2>
              <p className="text-muted-foreground">
                Everything you need — minimum age requirements, RTO offices, documents, fees, and a step-by-step license process for Pune.
              </p>
            </div>
            <Button size="lg" onClick={() => setLocation("/driving-rules")} className="shrink-0 gap-2">
              View Full Guide <ArrowRight className="h-4 w-4" />
            </Button>
          </motion.div>
        </div>
      </section>

      {/* Trust & Safety */}
      <section className="py-16 bg-gradient-to-br from-primary/5 to-accent/10">
        <div className="container mx-auto px-4">
          <motion.div
            className="text-center mb-12"
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            <h2 className="text-3xl font-bold">Why Trust DriveQ</h2>
            <p className="text-muted-foreground mt-2">What we check, and what we do not claim</p>
          </motion.div>

          <motion.div
            className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto"
            initial="initial"
            whileInView="animate"
            viewport={{ once: true }}
            variants={stagger}
          >
            {[
              { icon: ShieldCheck, title: "Checked Listings", desc: "Schools marked Verified have had their phone, business and location checked." },
              { icon: Users, title: "Women Instructors", desc: "Filter for schools that offer women instructors." },
              { icon: MapPin, title: "Pune Localities", desc: "Browse schools by locality across Pune." },
              { icon: Star, title: "Gated Reviews", desc: "Only learners who enquired with or enrolled at a school can review it." },
            ].map((item) => (
              <motion.div
                key={item.title}
                variants={fadeIn}
                className="flex flex-col items-center text-center p-6 rounded-xl border bg-card hover:shadow-sm transition-all"
              >
                <div className="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center mb-4">
                  <item.icon className="h-6 w-6 text-primary" />
                </div>
                <h3 className="font-semibold mb-1">{item.title}</h3>
                <p className="text-sm text-muted-foreground leading-relaxed">{item.desc}</p>
              </motion.div>
            ))}
          </motion.div>
        </div>
      </section>

      {/* CTA Banner */}
      <section className="py-16 bg-gradient-to-r from-[hsl(221,83%,18%)] to-[hsl(258,60%,25%)] text-white">
        <div className="container mx-auto px-4 text-center">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            <Zap className="h-10 w-10 mx-auto mb-4 text-yellow-400" />
            <h2 className="text-3xl font-bold mb-3">Ready to start learning?</h2>
            <p className="text-white/70 mb-8 max-w-md mx-auto">Compare driving schools in Pune and ask for a callback.</p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button size="lg" variant="inverse" onClick={() => setLocation("/search")} data-testid="button-cta-search">
                Search Schools Now
              </Button>
              <Button size="lg" variant="outline" className="border-white/30 text-white hover:bg-white/10" onClick={() => setLocation("/auth/register")}>
                List Your School
              </Button>
            </div>
          </motion.div>
        </div>
      </section>
    </PublicLayout>
  );
}
