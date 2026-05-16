import { useState } from "react";
import { useParams } from "wouter";
import { motion } from "framer-motion";
import { PublicLayout } from "@/components/layout/public-layout";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Skeleton } from "@/components/ui/skeleton";
import { Separator } from "@/components/ui/separator";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { useToast } from "@/hooks/use-toast";
import {
  useGetSchoolBySlug,
  useListReviews,
  useListPackages,
  useCreateInquiry,
  useCreateReview,
  getListReviewsQueryKey,
} from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import {
  Star, MapPin, Phone, Mail, ShieldCheck, Car, CheckCircle2,
  Clock, MessageCircle, Users, ArrowLeft, Send
} from "lucide-react";
import { Link } from "wouter";

function StarRating({ rating }: { rating: number }) {
  return (
    <div className="flex gap-0.5">
      {Array.from({ length: 5 }).map((_, i) => (
        <Star key={i} className={`h-4 w-4 ${i < rating ? "fill-yellow-400 text-yellow-400" : "text-muted-foreground/30"}`} />
      ))}
    </div>
  );
}

export default function SchoolDetailPage() {
  const params = useParams<{ slug: string }>();
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [inquiryOpen, setInquiryOpen] = useState(false);
  const [reviewOpen, setReviewOpen] = useState(false);

  const { data: school, isLoading } = useGetSchoolBySlug(params.slug);
  const { data: reviews } = useListReviews(
    { schoolId: school?.id },
    { query: { enabled: !!school?.id, queryKey: getListReviewsQueryKey({ schoolId: school?.id }) } }
  );
  const { data: packages } = useListPackages(
    { schoolId: school?.id },
    { query: { enabled: !!school?.id } }
  );

  const createInquiry = useCreateInquiry();
  const createReview = useCreateReview();

  const [inquiryForm, setInquiryForm] = useState({ name: "", phone: "", email: "", vehicleType: "Car", message: "" });
  const [reviewForm, setReviewForm] = useState({ authorName: "", rating: 5, content: "" });

  const handleInquiry = async () => {
    if (!school) return;
    createInquiry.mutate(
      { data: { ...inquiryForm, schoolId: school.id } },
      {
        onSuccess: () => {
          toast({ title: "Inquiry sent!", description: "The school will contact you shortly." });
          setInquiryOpen(false);
          setInquiryForm({ name: "", phone: "", email: "", vehicleType: "Car", message: "" });
        },
        onError: () => toast({ title: "Failed to send inquiry", variant: "destructive" }),
      }
    );
  };

  const handleReview = async () => {
    if (!school) return;
    createReview.mutate(
      { data: { ...reviewForm, schoolId: school.id } },
      {
        onSuccess: () => {
          toast({ title: "Review submitted!", description: "Your review is pending approval." });
          setReviewOpen(false);
          queryClient.invalidateQueries({ queryKey: getListReviewsQueryKey({ schoolId: school.id }) });
        },
        onError: () => toast({ title: "Failed to submit review", variant: "destructive" }),
      }
    );
  };

  if (isLoading) {
    return (
      <PublicLayout>
        <div className="container mx-auto px-4 py-8 max-w-6xl">
          <Skeleton className="h-72 w-full rounded-2xl mb-8" />
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div className="lg:col-span-2 space-y-6">
              <Skeleton className="h-48 rounded-xl" />
              <Skeleton className="h-64 rounded-xl" />
            </div>
            <Skeleton className="h-80 rounded-xl" />
          </div>
        </div>
      </PublicLayout>
    );
  }

  if (!school) {
    return (
      <PublicLayout>
        <div className="container mx-auto px-4 py-20 text-center">
          <p className="text-muted-foreground">School not found.</p>
          <Link href="/search"><Button className="mt-4">Back to Search</Button></Link>
        </div>
      </PublicLayout>
    );
  }

  const approvedReviews = (reviews || []).filter(r => r.approved);

  return (
    <PublicLayout>
      {/* Hero banner */}
      <div className="relative h-64 md:h-80 bg-gradient-to-br from-primary/80 to-[hsl(258,60%,35%)] overflow-hidden">
        {school.imageUrl && (
          <img src={school.imageUrl} alt={school.name} className="absolute inset-0 w-full h-full object-cover mix-blend-overlay opacity-40" />
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
        <div className="container mx-auto px-4 h-full flex flex-col justify-end pb-8 relative z-10">
          <Link href="/search" className="text-white/70 hover:text-white text-sm flex items-center gap-1 mb-4 w-fit">
            <ArrowLeft className="h-4 w-4" /> Back to Search
          </Link>
          <div className="flex items-end gap-4 flex-wrap">
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-2 flex-wrap">
                {school.verified && (
                  <Badge className="bg-green-500 text-white border-0">
                    <ShieldCheck className="h-3 w-3 mr-1" /> Verified
                  </Badge>
                )}
                {school.hasPickup && <Badge variant="secondary" className="bg-white/20 text-white border-0">Pickup Available</Badge>}
                {school.womenInstructor && <Badge variant="secondary" className="bg-purple-500/80 text-white border-0">Women Instructor</Badge>}
              </div>
              <h1 className="text-3xl md:text-4xl font-bold text-white">{school.name}</h1>
              <div className="flex items-center gap-2 text-white/80 mt-2">
                <MapPin className="h-4 w-4" />
                <span>{school.address}</span>
              </div>
            </div>
            <div className="text-right">
              <div className="flex items-center gap-2 bg-white/20 backdrop-blur-sm rounded-xl px-4 py-2">
                <Star className="h-5 w-5 fill-yellow-400 text-yellow-400" />
                <span className="text-white font-bold text-xl">{school.rating.toFixed(1)}</span>
                <span className="text-white/70 text-sm">({school.reviewCount})</span>
              </div>
              <div className="text-white/80 text-sm mt-2">₹{school.priceFrom.toLocaleString()} – ₹{school.priceTo.toLocaleString()}</div>
            </div>
          </div>
        </div>
      </div>

      <div className="container mx-auto px-4 py-8 max-w-6xl">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Main content */}
          <div className="lg:col-span-2 space-y-8">
            {/* About */}
            {school.description && (
              <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} className="rounded-xl border bg-card p-6">
                <h2 className="font-bold text-lg mb-3">About This School</h2>
                <p className="text-muted-foreground leading-relaxed">{school.description}</p>
              </motion.div>
            )}

            {/* Details */}
            <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0, transition: { delay: 0.1 } }} className="rounded-xl border bg-card p-6">
              <h2 className="font-bold text-lg mb-4">School Details</h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {[
                  { icon: Car, label: "Vehicle Types", value: school.vehicleTypes.join(", ") || "—" },
                  { icon: CheckCircle2, label: "Transmission", value: school.transmission.join(", ") || "—" },
                  { icon: Clock, label: "Timings", value: school.timings || "Mon–Sat, 7am–7pm" },
                  { icon: MapPin, label: "Service Areas", value: school.serviceAreas.join(", ") || school.localityName || "—" },
                ].map((item) => (
                  <div key={item.label} className="flex gap-3 p-3 rounded-lg bg-muted/30">
                    <item.icon className="h-5 w-5 text-primary flex-shrink-0 mt-0.5" />
                    <div>
                      <div className="text-xs text-muted-foreground">{item.label}</div>
                      <div className="text-sm font-medium">{item.value}</div>
                    </div>
                  </div>
                ))}
              </div>
            </motion.div>

            {/* Packages */}
            {packages && packages.length > 0 && (
              <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0, transition: { delay: 0.15 } }} className="rounded-xl border bg-card p-6">
                <h2 className="font-bold text-lg mb-4">Pricing Packages</h2>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  {packages.filter(p => p.active).map((pkg) => (
                    <div key={pkg.id} className="rounded-lg border p-4 hover:border-primary/50 transition-colors" data-testid={`card-package-${pkg.id}`}>
                      <div className="flex items-start justify-between mb-2">
                        <h3 className="font-semibold">{pkg.name}</h3>
                        <span className="text-primary font-bold text-lg">₹{pkg.price.toLocaleString()}</span>
                      </div>
                      {pkg.description && <p className="text-sm text-muted-foreground mb-3">{pkg.description}</p>}
                      <div className="flex flex-wrap gap-1.5">
                        <Badge variant="secondary" className="text-xs">{pkg.sessions} sessions</Badge>
                        <Badge variant="secondary" className="text-xs">{pkg.vehicleType}</Badge>
                        <Badge variant="secondary" className="text-xs">{pkg.transmission}</Badge>
                        {pkg.hasPickup && <Badge variant="outline" className="text-xs">Pickup</Badge>}
                      </div>
                    </div>
                  ))}
                </div>
              </motion.div>
            )}

            {/* Reviews */}
            <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0, transition: { delay: 0.2 } }} className="rounded-xl border bg-card p-6">
              <div className="flex items-center justify-between mb-4">
                <h2 className="font-bold text-lg">Reviews ({approvedReviews.length})</h2>
                <Dialog open={reviewOpen} onOpenChange={setReviewOpen}>
                  <DialogTrigger asChild>
                    <Button variant="outline" size="sm" data-testid="button-write-review">Write a Review</Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader><DialogTitle>Write a Review</DialogTitle></DialogHeader>
                    <div className="space-y-4 mt-2">
                      <div>
                        <Label>Your Name</Label>
                        <Input value={reviewForm.authorName} onChange={e => setReviewForm(p => ({ ...p, authorName: e.target.value }))} placeholder="Enter your name" data-testid="input-reviewer-name" />
                      </div>
                      <div>
                        <Label>Rating</Label>
                        <div className="flex gap-1 mt-1">
                          {[1, 2, 3, 4, 5].map((n) => (
                            <button key={n} onClick={() => setReviewForm(p => ({ ...p, rating: n }))}>
                              <Star className={`h-7 w-7 transition-colors ${n <= reviewForm.rating ? "fill-yellow-400 text-yellow-400" : "text-muted-foreground/30"}`} />
                            </button>
                          ))}
                        </div>
                      </div>
                      <div>
                        <Label>Review</Label>
                        <Textarea value={reviewForm.content} onChange={e => setReviewForm(p => ({ ...p, content: e.target.value }))} placeholder="Share your experience..." rows={4} data-testid="textarea-review-content" />
                      </div>
                      <Button onClick={handleReview} disabled={createReview.isPending} className="w-full" data-testid="button-submit-review">
                        {createReview.isPending ? "Submitting..." : "Submit Review"}
                      </Button>
                    </div>
                  </DialogContent>
                </Dialog>
              </div>

              {approvedReviews.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground text-sm">
                  No reviews yet. Be the first to review this school!
                </div>
              ) : (
                <div className="space-y-4">
                  {approvedReviews.map((review) => (
                    <div key={review.id} className="border-b pb-4 last:border-0 last:pb-0" data-testid={`review-${review.id}`}>
                      <div className="flex items-center justify-between mb-1">
                        <span className="font-medium text-sm">{review.authorName}</span>
                        <span className="text-xs text-muted-foreground">{new Date(review.createdAt).toLocaleDateString()}</span>
                      </div>
                      <StarRating rating={review.rating} />
                      <p className="text-sm text-muted-foreground mt-2 leading-relaxed">{review.content}</p>
                    </div>
                  ))}
                </div>
              )}
            </motion.div>

            {/* FAQ */}
            <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0, transition: { delay: 0.25 } }} className="rounded-xl border bg-card p-6">
              <h2 className="font-bold text-lg mb-4">Frequently Asked Questions</h2>
              <Accordion type="single" collapsible>
                {[
                  { q: "How many sessions does a standard course include?", a: "Standard courses typically include 15–30 sessions depending on the package you choose. Check the pricing packages above for specific details." },
                  { q: "Do you offer pickup and drop service?", a: school.hasPickup ? "Yes! This school offers pickup and drop services. Contact them to know the coverage areas." : "This school currently does not offer pickup/drop services. You'll need to visit their centre directly." },
                  { q: "Is the driving test included?", a: "Most packages include assistance with booking your RTO driving test. Please confirm with the school during your inquiry." },
                  { q: "What documents are required for enrollment?", a: "You'll typically need your Aadhaar card, passport-size photos, and a learner's licence (the school can help you obtain one)." },
                ].map((faq, i) => (
                  <AccordionItem key={i} value={`faq-${i}`}>
                    <AccordionTrigger className="text-sm font-medium text-left">{faq.q}</AccordionTrigger>
                    <AccordionContent className="text-sm text-muted-foreground">{faq.a}</AccordionContent>
                  </AccordionItem>
                ))}
              </Accordion>
            </motion.div>
          </div>

          {/* Sticky inquiry card */}
          <div className="lg:col-span-1">
            <div className="sticky top-24 space-y-4">
              <motion.div initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} className="rounded-xl border bg-card p-6 shadow-md">
                <div className="flex items-center gap-1 mb-1">
                  <Star className="h-5 w-5 fill-yellow-400 text-yellow-400" />
                  <span className="font-bold text-xl">{school.rating.toFixed(1)}</span>
                  <span className="text-muted-foreground text-sm">· {school.reviewCount} reviews</span>
                </div>
                <div className="text-2xl font-bold mb-1">₹{school.priceFrom.toLocaleString()}<span className="text-base font-normal text-muted-foreground"> / course</span></div>
                <Separator className="my-4" />

                <Dialog open={inquiryOpen} onOpenChange={setInquiryOpen}>
                  <DialogTrigger asChild>
                    <Button className="w-full" size="lg" data-testid="button-send-inquiry">
                      <Send className="h-4 w-4 mr-2" /> Send Inquiry
                    </Button>
                  </DialogTrigger>
                  <DialogContent>
                    <DialogHeader><DialogTitle>Send Inquiry to {school.name}</DialogTitle></DialogHeader>
                    <div className="space-y-4 mt-2">
                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <Label>Your Name</Label>
                          <Input value={inquiryForm.name} onChange={e => setInquiryForm(p => ({ ...p, name: e.target.value }))} placeholder="Full name" data-testid="input-inquiry-name" />
                        </div>
                        <div>
                          <Label>Phone</Label>
                          <Input value={inquiryForm.phone} onChange={e => setInquiryForm(p => ({ ...p, phone: e.target.value }))} placeholder="+91 XXXXX XXXXX" data-testid="input-inquiry-phone" />
                        </div>
                      </div>
                      <div>
                        <Label>Email (optional)</Label>
                        <Input value={inquiryForm.email} onChange={e => setInquiryForm(p => ({ ...p, email: e.target.value }))} placeholder="your@email.com" data-testid="input-inquiry-email" />
                      </div>
                      <div>
                        <Label>Vehicle Type</Label>
                        <Select value={inquiryForm.vehicleType} onValueChange={v => setInquiryForm(p => ({ ...p, vehicleType: v }))}>
                          <SelectTrigger><SelectValue /></SelectTrigger>
                          <SelectContent>
                            {["Car", "Bike", "Scooter", "Heavy Vehicle"].map(v => <SelectItem key={v} value={v}>{v}</SelectItem>)}
                          </SelectContent>
                        </Select>
                      </div>
                      <div>
                        <Label>Message (optional)</Label>
                        <Textarea value={inquiryForm.message} onChange={e => setInquiryForm(p => ({ ...p, message: e.target.value }))} placeholder="Any specific requirements..." rows={3} data-testid="textarea-inquiry-message" />
                      </div>
                      <Button onClick={handleInquiry} disabled={createInquiry.isPending} className="w-full" data-testid="button-submit-inquiry">
                        {createInquiry.isPending ? "Sending..." : "Send Inquiry"}
                      </Button>
                    </div>
                  </DialogContent>
                </Dialog>

                {school.whatsapp && (
                  <a
                    href={`https://wa.me/${school.whatsapp.replace(/\D/g, "")}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="block mt-3"
                    data-testid="link-whatsapp"
                  >
                    <Button variant="outline" className="w-full border-green-500 text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20" size="lg">
                      <MessageCircle className="h-4 w-4 mr-2" /> WhatsApp
                    </Button>
                  </a>
                )}

                <Separator className="my-4" />
                <div className="space-y-2">
                  {school.phone && (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                      <Phone className="h-4 w-4" />
                      <span>{school.phone}</span>
                    </div>
                  )}
                  {school.email && (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                      <Mail className="h-4 w-4" />
                      <span>{school.email}</span>
                    </div>
                  )}
                  {school.timings && (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                      <Clock className="h-4 w-4" />
                      <span>{school.timings}</span>
                    </div>
                  )}
                </div>
              </motion.div>
            </div>
          </div>
        </div>
      </div>
    </PublicLayout>
  );
}
