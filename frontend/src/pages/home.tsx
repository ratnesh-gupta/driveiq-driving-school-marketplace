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
import { useListFeaturedSchools, useListLocalities, useGetStatsOverview } from "@/api-client";
import { Search, MapPin, Car, ShieldCheck, Users, Star, ArrowRight, CheckCircle2, Zap, MessageCircle } from "lucide-react";

const LOCALITIES = ["Baner", "Hinjewadi", "Wakad", "Hadapsar", "Kothrud", "Pimpri"];
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
  const { data: stats } = useGetStatsOverview();

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

        {/* Floating school card previews */}
        <motion.div
          className="absolute right-8 top-16 hidden xl:block"
          initial={{ opacity: 0, x: 40 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: 0.5, duration: 0.7 }}
        >
          <div className="bg-white/10 backdrop-blur-md rounded-2xl p-4 border border-white/20 w-56 shadow-2xl">
            <div className="flex items-center gap-3 mb-3">
              <div className="w-10 h-10 rounded-lg bg-primary/30 flex items-center justify-center">
                <Car className="h-5 w-5 text-white" />
              </div>
              <div>
                <div className="text-sm font-semibold">Skyline Driving</div>
                <div className="text-xs text-white/70">Baner, Pune</div>
              </div>
            </div>
            <div className="flex items-center gap-1 text-yellow-400 text-sm mb-1">
              <Star className="h-3.5 w-3.5 fill-current" />
              <span className="font-medium">4.8</span>
              <span className="text-white/50 text-xs ml-1">• 124 reviews</span>
            </div>
            <div className="text-xs text-white/70">Starting ₹3,500</div>
          </div>
        </motion.div>

        <motion.div
          className="absolute right-4 bottom-24 hidden xl:block"
          initial={{ opacity: 0, x: 40 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ delay: 0.7, duration: 0.7 }}
        >
          <div className="bg-white/10 backdrop-blur-md rounded-xl p-3 border border-white/20 w-48 shadow-xl">
            <div className="flex items-center gap-2 mb-2">
              <ShieldCheck className="h-4 w-4 text-green-400" />
              <span className="text-xs font-medium">Verified School</span>
            </div>
            <div className="text-sm font-semibold">PuneAuto Academy</div>
            <div className="text-xs text-white/60">Hinjewadi • Pickup available</div>
          </div>
        </motion.div>

        <div className="container mx-auto px-4 relative z-10 py-20">
          <motion.div className="max-w-2xl" initial="initial" animate="animate" variants={stagger}>
            <motion.div variants={fadeIn}>
              <Badge className="mb-6 bg-white/10 text-white border-white/20 hover:bg-white/20 px-3 py-1">
                <MapPin className="h-3 w-3 mr-1" /> Pune's #1 Driving School Platform
              </Badge>
            </motion.div>

            <motion.h1 className="text-4xl md:text-5xl lg:text-6xl font-bold leading-tight mb-6" variants={fadeIn}>
              Find Trusted Driving Schools{" "}
              <span className="text-transparent bg-clip-text bg-gradient-to-r from-blue-300 to-purple-300">Near You</span>
            </motion.h1>

            <motion.p className="text-xl text-white/75 mb-10 max-w-lg leading-relaxed" variants={fadeIn}>
              Compare pricing, instructors, pickup availability, and reviews across Pune's top driving schools.
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
                <Button onClick={handleSearch} size="lg" className="h-11 px-6 bg-white text-primary hover:bg-white/90 font-semibold" data-testid="button-search">
                  <Search className="h-4 w-4 mr-2" /> Search
                </Button>
              </div>
            </motion.div>

            <motion.div className="flex flex-wrap gap-3 mt-6" variants={fadeIn}>
              {LOCALITIES.slice(0, 4).map((loc) => (
                <button
                  key={loc}
                  onClick={() => setLocation(`/search?locality=${loc.toLowerCase()}`)}
                  className="text-sm bg-white/10 hover:bg-white/20 border border-white/20 rounded-full px-3 py-1 text-white/80 transition-colors"
                  data-testid={`button-quick-locality-${loc.toLowerCase()}`}
                >
                  {loc}
                </button>
              ))}
            </motion.div>
          </motion.div>
        </div>

        {/* Stats bar */}
        {stats && (
          <motion.div
            className="absolute bottom-0 left-0 right-0 bg-black/30 backdrop-blur-sm border-t border-white/10 py-4"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 1 }}
          >
            <div className="container mx-auto px-4 flex flex-wrap justify-center md:justify-around gap-6">
              {[
                { label: "Driving Schools", value: stats.totalSchools },
                { label: "Localities Covered", value: stats.totalLocalities },
                { label: "Verified Reviews", value: stats.totalReviews },
                { label: "Happy Learners", value: stats.totalInquiries },
              ].map((s) => (
                <div key={s.label} className="text-center">
                  <div className="text-2xl font-bold">{s.value.toLocaleString()}+</div>
                  <div className="text-xs text-white/60">{s.label}</div>
                </div>
              ))}
            </div>
          </motion.div>
        )}
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
              <p className="text-muted-foreground mt-1">Top-rated, verified schools across Pune</p>
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
            <h2 className="text-3xl font-bold">How DriveIQ Works</h2>
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
              { icon: Search, step: "01", title: "Search Nearby Schools", desc: "Enter your locality and vehicle type. DriveIQ finds verified driving schools near you instantly." },
              { icon: Star, step: "02", title: "Compare & Review", desc: "Read real reviews, compare pricing packages, check instructor details and pickup availability." },
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
            <h2 className="text-3xl font-bold">Why Trust DriveIQ</h2>
            <p className="text-muted-foreground mt-2">We verify every school so you don't have to</p>
          </motion.div>

          <motion.div
            className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-5xl mx-auto"
            initial="initial"
            whileInView="animate"
            viewport={{ once: true }}
            variants={stagger}
          >
            {[
              { icon: ShieldCheck, title: "Verified Schools", desc: "Every school is personally verified by our team before listing." },
              { icon: Users, title: "Women Instructors", desc: "Filter schools with certified women driving instructors." },
              { icon: MapPin, title: "12+ Localities", desc: "Comprehensive coverage across all major Pune localities." },
              { icon: Star, title: "Real Reviews", desc: "Honest, verified reviews from actual students and learners." },
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
            <p className="text-white/70 mb-8 max-w-md mx-auto">Join thousands of students who found their perfect driving school on DriveIQ.</p>
            <div className="flex flex-col sm:flex-row gap-4 justify-center">
              <Button size="lg" className="bg-white text-primary hover:bg-white/90 font-semibold" onClick={() => setLocation("/search")} data-testid="button-cta-search">
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
