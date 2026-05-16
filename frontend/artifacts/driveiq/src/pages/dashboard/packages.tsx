import { useState } from "react";
import { DashboardLayout } from "@/components/layout/dashboard-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Switch } from "@/components/ui/switch";
import { Badge } from "@/components/ui/badge";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { useToast } from "@/hooks/use-toast";
import { useListPackages, useCreatePackage, useUpdatePackage, useDeletePackage, getListPackagesQueryKey } from "@workspace/api-client-react";
import { useQueryClient } from "@tanstack/react-query";
import { Plus, Pencil, Trash2, Package } from "lucide-react";

const DEMO_SCHOOL_ID = 1;

export default function PackagesPage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState({ name: "", description: "", price: "", sessions: "", vehicleType: "Car", transmission: "Manual", hasPickup: false });

  const { data: packages, isLoading } = useListPackages({ schoolId: DEMO_SCHOOL_ID });
  const createPackage = useCreatePackage();
  const updatePackage = useUpdatePackage();
  const deletePackage = useDeletePackage();

  const resetForm = () => setForm({ name: "", description: "", price: "", sessions: "", vehicleType: "Car", transmission: "Manual", hasPickup: false });

  const handleOpen = (pkg?: typeof packages extends (infer T)[] | undefined ? T : never) => {
    if (pkg) {
      setEditId(pkg.id);
      setForm({ name: pkg.name, description: pkg.description || "", price: String(pkg.price), sessions: String(pkg.sessions), vehicleType: pkg.vehicleType, transmission: pkg.transmission, hasPickup: pkg.hasPickup });
    } else {
      setEditId(null);
      resetForm();
    }
    setOpen(true);
  };

  const handleSave = () => {
    const data = { ...form, price: parseFloat(form.price), sessions: parseInt(form.sessions), schoolId: DEMO_SCHOOL_ID };
    const onSuccess = () => {
      queryClient.invalidateQueries({ queryKey: getListPackagesQueryKey({ schoolId: DEMO_SCHOOL_ID }) });
      toast({ title: editId ? "Package updated!" : "Package created!" });
      setOpen(false);
    };
    const onError = () => toast({ title: "Save failed", variant: "destructive" });

    if (editId) {
      updatePackage.mutate({ id: editId, data }, { onSuccess, onError });
    } else {
      createPackage.mutate({ data }, { onSuccess, onError });
    }
  };

  const handleDelete = (id: number) => {
    deletePackage.mutate({ id }, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListPackagesQueryKey({ schoolId: DEMO_SCHOOL_ID }) });
        toast({ title: "Package deleted" });
      },
      onError: () => toast({ title: "Delete failed", variant: "destructive" }),
    });
  };

  return (
    <DashboardLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Packages</h1>
          <p className="text-muted-foreground text-sm mt-1">Manage your pricing packages</p>
        </div>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild>
            <Button onClick={() => handleOpen()} data-testid="button-add-package">
              <Plus className="h-4 w-4 mr-2" /> Add Package
            </Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader><DialogTitle>{editId ? "Edit" : "New"} Package</DialogTitle></DialogHeader>
            <div className="space-y-4 mt-2">
              <div>
                <Label>Package Name</Label>
                <Input value={form.name} onChange={e => setForm(p => ({ ...p, name: e.target.value }))} placeholder="e.g. Basic Car Course" data-testid="input-package-name" />
              </div>
              <div>
                <Label>Description</Label>
                <Textarea value={form.description} onChange={e => setForm(p => ({ ...p, description: e.target.value }))} placeholder="What's included..." rows={2} />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <Label>Price (₹)</Label>
                  <Input type="number" value={form.price} onChange={e => setForm(p => ({ ...p, price: e.target.value }))} placeholder="3500" data-testid="input-package-price" />
                </div>
                <div>
                  <Label>Sessions</Label>
                  <Input type="number" value={form.sessions} onChange={e => setForm(p => ({ ...p, sessions: e.target.value }))} placeholder="15" data-testid="input-package-sessions" />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <Label>Vehicle Type</Label>
                  <Select value={form.vehicleType} onValueChange={v => setForm(p => ({ ...p, vehicleType: v }))}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {["Car", "Bike", "Scooter", "Heavy Vehicle"].map(v => <SelectItem key={v} value={v}>{v}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label>Transmission</Label>
                  <Select value={form.transmission} onValueChange={v => setForm(p => ({ ...p, transmission: v }))}>
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value="Manual">Manual</SelectItem>
                      <SelectItem value="Automatic">Automatic</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="flex items-center justify-between">
                <Label>Includes Pickup</Label>
                <Switch checked={form.hasPickup} onCheckedChange={v => setForm(p => ({ ...p, hasPickup: v }))} data-testid="switch-package-pickup" />
              </div>
              <Button onClick={handleSave} disabled={createPackage.isPending || updatePackage.isPending} className="w-full" data-testid="button-save-package">
                {(createPackage.isPending || updatePackage.isPending) ? "Saving..." : "Save Package"}
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} className="h-40 rounded-xl" />)}
        </div>
      ) : !packages?.length ? (
        <div className="text-center py-16 text-muted-foreground">
          <Package className="h-10 w-10 mx-auto mb-3 opacity-30" />
          <p>No packages yet. Add your first package.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {packages.map((pkg) => (
            <div key={pkg.id} className="rounded-xl border bg-card p-5" data-testid={`card-package-${pkg.id}`}>
              <div className="flex items-start justify-between mb-3">
                <h3 className="font-semibold">{pkg.name}</h3>
                <div className="flex gap-1">
                  <Button size="icon" variant="ghost" className="h-7 w-7" onClick={() => handleOpen(pkg)} data-testid={`button-edit-package-${pkg.id}`}>
                    <Pencil className="h-3.5 w-3.5" />
                  </Button>
                  <Button size="icon" variant="ghost" className="h-7 w-7 text-destructive hover:text-destructive" onClick={() => handleDelete(pkg.id)} data-testid={`button-delete-package-${pkg.id}`}>
                    <Trash2 className="h-3.5 w-3.5" />
                  </Button>
                </div>
              </div>
              {pkg.description && <p className="text-sm text-muted-foreground mb-3">{pkg.description}</p>}
              <div className="text-2xl font-bold text-primary mb-3">₹{pkg.price.toLocaleString()}</div>
              <div className="flex flex-wrap gap-1.5">
                <Badge variant="secondary" className="text-xs">{pkg.sessions} sessions</Badge>
                <Badge variant="secondary" className="text-xs">{pkg.vehicleType}</Badge>
                <Badge variant="secondary" className="text-xs">{pkg.transmission}</Badge>
                {pkg.hasPickup && <Badge variant="outline" className="text-xs">Pickup</Badge>}
                {!pkg.active && <Badge variant="outline" className="text-xs text-muted-foreground">Inactive</Badge>}
              </div>
            </div>
          ))}
        </div>
      )}
    </DashboardLayout>
  );
}
