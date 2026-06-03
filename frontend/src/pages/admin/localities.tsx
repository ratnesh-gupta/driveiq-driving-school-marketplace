import { useState } from "react";
import { AdminLayout } from "@/components/layout/admin-layout";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog";
import { Skeleton } from "@/components/ui/skeleton";
import { useToast } from "@/hooks/use-toast";
import { useListLocalities, useCreateLocality, getListLocalitiesQueryKey } from "@/api-client";
import { useQueryClient } from "@tanstack/react-query";
import { Plus, MapPin, Building2 } from "lucide-react";

export default function AdminLocalitiesPage() {
  const { toast } = useToast();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({ name: "", slug: "", description: "" });

  const { data: localities, isLoading } = useListLocalities();
  const createLocality = useCreateLocality();

  const handleCreate = () => {
    createLocality.mutate({ data: form }, {
      onSuccess: () => {
        queryClient.invalidateQueries({ queryKey: getListLocalitiesQueryKey() });
        toast({ title: "Locality added!" });
        setOpen(false);
        setForm({ name: "", slug: "", description: "" });
      },
      onError: () => toast({ title: "Failed to add locality", variant: "destructive" }),
    });
  };

  return (
    <AdminLayout>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold">Locality Management</h1>
          <p className="text-muted-foreground text-sm mt-1">Manage geographic coverage areas</p>
        </div>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger asChild>
            <Button data-testid="button-add-locality"><Plus className="h-4 w-4 mr-2" /> Add Locality</Button>
          </DialogTrigger>
          <DialogContent>
            <DialogHeader><DialogTitle>Add New Locality</DialogTitle></DialogHeader>
            <div className="space-y-4 mt-2">
              <div>
                <Label>Name</Label>
                <Input value={form.name} onChange={e => setForm(p => ({ ...p, name: e.target.value, slug: e.target.value.toLowerCase().replace(/\s+/g, "-") }))} placeholder="e.g. Aundh" data-testid="input-locality-name" />
              </div>
              <div>
                <Label>Slug</Label>
                <Input value={form.slug} onChange={e => setForm(p => ({ ...p, slug: e.target.value }))} placeholder="aundh" data-testid="input-locality-slug" />
              </div>
              <div>
                <Label>Description</Label>
                <Textarea value={form.description} onChange={e => setForm(p => ({ ...p, description: e.target.value }))} placeholder="Brief description of this locality..." rows={2} />
              </div>
              <Button onClick={handleCreate} disabled={createLocality.isPending} className="w-full" data-testid="button-save-locality">
                {createLocality.isPending ? "Adding..." : "Add Locality"}
              </Button>
            </div>
          </DialogContent>
        </Dialog>
      </div>

      {isLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {Array.from({ length: 6 }).map((_, i) => <Skeleton key={i} className="h-28 rounded-xl" />)}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {(localities || []).map((loc) => (
            <div key={loc.id} className="rounded-xl border bg-card p-5" data-testid={`card-locality-${loc.id}`}>
              <div className="flex items-center gap-3 mb-2">
                <div className="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center">
                  <MapPin className="h-4 w-4 text-primary" />
                </div>
                <div>
                  <div className="font-semibold">{loc.name}</div>
                  <div className="text-xs text-muted-foreground">/{loc.slug}</div>
                </div>
              </div>
              {loc.description && <p className="text-sm text-muted-foreground mb-2 line-clamp-2">{loc.description}</p>}
              <div className="flex items-center gap-1 text-sm text-muted-foreground">
                <Building2 className="h-3.5 w-3.5" />
                {loc.schoolCount} schools
              </div>
            </div>
          ))}
        </div>
      )}
    </AdminLayout>
  );
}
