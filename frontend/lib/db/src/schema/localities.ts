import { pgTable, serial, text, integer } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const localitiesTable = pgTable("localities", {
  id: serial("id").primaryKey(),
  name: text("name").notNull(),
  slug: text("slug").notNull().unique(),
  description: text("description"),
  imageUrl: text("image_url"),
  schoolCount: integer("school_count").notNull().default(0),
});

export const insertLocalitySchema = createInsertSchema(localitiesTable).omit({ id: true });
export type InsertLocality = z.infer<typeof insertLocalitySchema>;
export type Locality = typeof localitiesTable.$inferSelect;
