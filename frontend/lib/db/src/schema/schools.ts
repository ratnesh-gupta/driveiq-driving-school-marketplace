import { pgTable, serial, text, integer, boolean, real, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const schoolsTable = pgTable("schools", {
  id: serial("id").primaryKey(),
  name: text("name").notNull(),
  slug: text("slug").notNull().unique(),
  localityId: integer("locality_id").notNull(),
  address: text("address").notNull(),
  phone: text("phone").notNull(),
  whatsapp: text("whatsapp"),
  email: text("email"),
  description: text("description"),
  imageUrl: text("image_url"),
  rating: real("rating").notNull().default(0),
  reviewCount: integer("review_count").notNull().default(0),
  verified: boolean("verified").notNull().default(false),
  hasPickup: boolean("has_pickup").notNull().default(false),
  womenInstructor: boolean("women_instructor").notNull().default(false),
  weekendClasses: boolean("weekend_classes").notNull().default(false),
  vehicleTypes: text("vehicle_types").array().notNull().default([]),
  transmission: text("transmission").array().notNull().default([]),
  priceFrom: real("price_from").notNull().default(0),
  priceTo: real("price_to").notNull().default(0),
  timings: text("timings"),
  serviceAreas: text("service_areas").array().notNull().default([]),
  createdAt: timestamp("created_at").notNull().defaultNow(),
});

export const insertSchoolSchema = createInsertSchema(schoolsTable).omit({ id: true, createdAt: true });
export type InsertSchool = z.infer<typeof insertSchoolSchema>;
export type School = typeof schoolsTable.$inferSelect;
