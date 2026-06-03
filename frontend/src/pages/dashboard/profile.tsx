import { useState, useEffect } from "react";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { useToast } from "@/hooks/use-toast";
import { useGetSchool, useUpdateSchool, getGetSchoolQueryKey } from "@/api-client";
import { useQueryClient } from "@tanstack/react-query";
import { useSchoolId } from "@/hooks/use-school-id";
import { Save, ShieldCheck } from "lucide-react";

export default function ProfilePage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const schoolId = useSchoolId();
  const { data: school, isLoading } = useGetSchool(schoolId!, { query: { queryKey: getGetSchoolQueryKey(schoolId!), enabled: !!schoolId } });
  const updateSchool = useUpdateSchool();

  const [form, setForm] = useState({ name: "", phone: "", whatsapp: "", email: "", description: "", timings: "", hasPickup: false, womenInstructor: false, weekendClasses: false });

  useEffect(() => {
    if (school) {
      setForm({
        name: school.name || "",
        phone: school.phone || "",
        whatsapp: school.whatsapp || "",
        email: school.email || "",
        description: school.description || "",
        timings: school.timings || "",
        hasPickup: school.hasPickup,
        womenInstructor: school.womenInstructor,
        weekendClasses: school.weekendClasses,
      });
    }
  }, [school]);

  const handleSave = () => {
    updateSchool.mutate(
      { id: schoolId!, data: form },
      {
        onSuccess: () => {
          queryClient.invalidateQueries({ queryKey: getGetSchoolQueryKey(schoolId!) });
          toast({ title: "Profile updated!" });
        },
        onError: () => toast({ title: "Update failed", variant: "destructive" }),
      }
    );
  };

  return (
    <DashboardLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">School Profile</h1>
        <p className="text-muted-foreground text-sm mt-1">Manage your school's public profile</p>
      </div>

      {isLoading ? (
        <div className="space-y-4 max-w-2xl"><Skeleton className="h-12" /><Skeleton className="h-12" /><Skeleton className="h-32" /></div>
      ) : (
        <div className="max-w-2xl space-y-6">
          {school?.verified && (
            <div className="flex items-center gap-2 p-3 rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 text-sm">
              <ShieldCheck className="h-4 w-4" />
              Your school is verified on DriveIQ
            </div>
          )}

          <div className="rounded-xl border bg-card p-6 space-y-5">
            <h2 className="font-semibold text-lg">Basic Information</h2>
            <div>
              <Label>School Name</Label>
              <Input value={form.name} onChange={e => setForm(p => ({ ...p, name: e.target.value }))} data-testid="input-school-name" />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Phone</Label>
                <Input value={form.phone} onChange={e => setForm(p => ({ ...p, phone: e.target.value }))} data-testid="input-school-phone" />
              </div>
              <div>
                <Label>WhatsApp</Label>
                <Input value={form.whatsapp} onChange={e => setForm(p => ({ ...p, whatsapp: e.target.value }))} data-testid="input-school-whatsapp" />
              </div>
            </div>
            <div>
              <Label>Email</Label>
              <Input type="email" value={form.email} onChange={e => setForm(p => ({ ...p, email: e.target.value }))} data-testid="input-school-email" />
            </div>
            <div>
              <Label>Description</Label>
              <Textarea value={form.description} onChange={e => setForm(p => ({ ...p, description: e.target.value }))} rows={4} data-testid="textarea-school-description" />
            </div>
            <div>
              <Label>Timings</Label>
              <Input value={form.timings} onChange={e => setForm(p => ({ ...p, timings: e.target.value }))} placeholder="e.g. Mon–Sat 7am–7pm" data-testid="input-school-timings" />
            </div>
          </div>

          <div className="rounded-xl border bg-card p-6 space-y-4">
            <h2 className="font-semibold text-lg">Features & Availability</h2>
            {[
              { label: "Pickup & Drop Service", key: "hasPickup", id: "hasPickup" },
              { label: "Women Instructor Available", key: "womenInstructor", id: "womenInstructor" },
              { label: "Weekend Classes", key: "weekendClasses", id: "weekendClasses" },
            ].map((item) => (
              <div key={item.key} className="flex items-center justify-between py-1">
                <Label htmlFor={item.id} className="font-normal cursor-pointer">{item.label}</Label>
                <Switch
                  id={item.id}
                  checked={form[item.key as keyof typeof form] as boolean}
                  onCheckedChange={(v) => setForm(p => ({ ...p, [item.key]: v }))}
                  data-testid={`switch-${item.key}`}
                />
              </div>
            ))}
          </div>

          <Button onClick={handleSave} disabled={updateSchool.isPending} className="gap-2" data-testid="button-save-profile">
            <Save className="h-4 w-4" />
            {updateSchool.isPending ? "Saving..." : "Save Changes"}
          </Button>
        </div>
      )}
    </DashboardLayout>
  );
}
