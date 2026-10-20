/** Lead lifecycle (DIQ-702), mirrors backend Inquiry::STATUSES. "pending" is shown as "New". */
export const LEAD_STATUSES = ["pending", "contacted", "follow_up", "interested", "converted", "lost"] as const;

export type LeadStatus = (typeof LEAD_STATUSES)[number];

const LABELS: Record<LeadStatus, string> = {
  pending: "New",
  contacted: "Contacted",
  follow_up: "Follow-up",
  interested: "Interested",
  converted: "Converted",
  lost: "Lost",
};

const COLORS: Record<LeadStatus, string> = {
  pending: "bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400",
  contacted: "bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400",
  follow_up: "bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400",
  interested: "bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400",
  converted: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400",
  lost: "bg-muted text-muted-foreground",
};

export function leadStatusLabel(status: string): string {
  return LABELS[status as LeadStatus] ?? status;
}

export function leadStatusColor(status: string): string {
  return COLORS[status as LeadStatus] ?? "bg-muted text-muted-foreground";
}
