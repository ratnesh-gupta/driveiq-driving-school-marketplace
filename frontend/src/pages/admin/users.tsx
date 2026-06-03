import { AdminLayout } from "@/components/layout/admin-layout";
import { Badge } from "@/components/ui/badge";
import { Users } from "lucide-react";

const MOCK_USERS = [
  { id: 1, name: "Rahul Sharma", email: "rahul@gmail.com", role: "user", joined: "2024-01-15" },
  { id: 2, name: "Skyline Driving Academy", email: "info@skylinedrive.in", role: "school", joined: "2023-11-01" },
  { id: 3, name: "Priya Deshpande", email: "priya@gmail.com", role: "user", joined: "2024-02-20" },
  { id: 4, name: "Admin User", email: "admin@driveiq.in", role: "admin", joined: "2023-10-01" },
];

export default function AdminUsersPage() {
  return (
    <AdminLayout>
      <div className="mb-6">
        <h1 className="text-2xl font-bold">User Management</h1>
        <p className="text-muted-foreground text-sm mt-1">Overview of platform users</p>
      </div>

      <div className="rounded-xl border bg-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b bg-muted/40">
              <tr>
                {["Name", "Email", "Role", "Joined"].map(h => (
                  <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">{h}</th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y">
              {MOCK_USERS.map((user) => (
                <tr key={user.id} className="hover:bg-muted/20 transition-colors" data-testid={`row-user-${user.id}`}>
                  <td className="px-4 py-3 font-medium">{user.name}</td>
                  <td className="px-4 py-3 text-muted-foreground">{user.email}</td>
                  <td className="px-4 py-3">
                    <Badge variant={user.role === "admin" ? "default" : user.role === "school" ? "secondary" : "outline"} className="text-xs capitalize">
                      {user.role}
                    </Badge>
                  </td>
                  <td className="px-4 py-3 text-muted-foreground">{new Date(user.joined).toLocaleDateString()}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      <div className="mt-8 rounded-xl border border-dashed p-8 text-center text-muted-foreground">
        <Users className="h-10 w-10 mx-auto mb-3 opacity-30" />
        <p className="font-medium">Full user management coming soon</p>
        <p className="text-sm mt-1">User authentication and role-based access control will be available in the next version.</p>
      </div>
    </AdminLayout>
  );
}
