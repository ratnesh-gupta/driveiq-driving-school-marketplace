import { Router } from "express";
import { db } from "@workspace/db";
import { schoolsTable, localitiesTable } from "@workspace/db";
import { eq, ilike, and, gte, lte, sql } from "drizzle-orm";
import {
  ListSchoolsQueryParams,
  CreateSchoolBody,
  GetSchoolParams,
  UpdateSchoolParams,
  UpdateSchoolBody,
  DeleteSchoolParams,
  GetSchoolBySlugParams,
} from "@workspace/api-zod";

const router = Router();

router.get("/schools", async (req, res): Promise<void> => {
  const parsed = ListSchoolsQueryParams.safeParse(req.query);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const { locality, vehicleType, minRating, hasPickup, womenInstructor, weekendClasses, maxPrice, transmission, limit = 20, offset = 0 } = parsed.data;

  const conditions = [];
  if (locality) {
    const loc = await db.select().from(localitiesTable).where(eq(localitiesTable.slug, locality)).limit(1);
    if (loc.length > 0) {
      conditions.push(eq(schoolsTable.localityId, loc[0].id));
    }
  }
  if (minRating !== undefined) conditions.push(gte(schoolsTable.rating, minRating));
  if (hasPickup !== undefined) conditions.push(eq(schoolsTable.hasPickup, hasPickup));
  if (womenInstructor !== undefined) conditions.push(eq(schoolsTable.womenInstructor, womenInstructor));
  if (weekendClasses !== undefined) conditions.push(eq(schoolsTable.weekendClasses, weekendClasses));
  if (maxPrice !== undefined) conditions.push(lte(schoolsTable.priceFrom, maxPrice));

  const schools = await db
    .select({
      id: schoolsTable.id,
      name: schoolsTable.name,
      slug: schoolsTable.slug,
      localityId: schoolsTable.localityId,
      localityName: localitiesTable.name,
      address: schoolsTable.address,
      phone: schoolsTable.phone,
      whatsapp: schoolsTable.whatsapp,
      email: schoolsTable.email,
      description: schoolsTable.description,
      imageUrl: schoolsTable.imageUrl,
      rating: schoolsTable.rating,
      reviewCount: schoolsTable.reviewCount,
      verified: schoolsTable.verified,
      hasPickup: schoolsTable.hasPickup,
      womenInstructor: schoolsTable.womenInstructor,
      weekendClasses: schoolsTable.weekendClasses,
      vehicleTypes: schoolsTable.vehicleTypes,
      transmission: schoolsTable.transmission,
      priceFrom: schoolsTable.priceFrom,
      priceTo: schoolsTable.priceTo,
      timings: schoolsTable.timings,
      serviceAreas: schoolsTable.serviceAreas,
      createdAt: schoolsTable.createdAt,
    })
    .from(schoolsTable)
    .leftJoin(localitiesTable, eq(schoolsTable.localityId, localitiesTable.id))
    .where(conditions.length > 0 ? and(...conditions) : undefined)
    .limit(Number(limit))
    .offset(Number(offset));

  res.json(schools.map(s => ({ ...s, createdAt: s.createdAt.toISOString() })));
});

router.get("/schools/featured", async (req, res): Promise<void> => {
  const schools = await db
    .select({
      id: schoolsTable.id,
      name: schoolsTable.name,
      slug: schoolsTable.slug,
      localityId: schoolsTable.localityId,
      localityName: localitiesTable.name,
      address: schoolsTable.address,
      phone: schoolsTable.phone,
      whatsapp: schoolsTable.whatsapp,
      email: schoolsTable.email,
      description: schoolsTable.description,
      imageUrl: schoolsTable.imageUrl,
      rating: schoolsTable.rating,
      reviewCount: schoolsTable.reviewCount,
      verified: schoolsTable.verified,
      hasPickup: schoolsTable.hasPickup,
      womenInstructor: schoolsTable.womenInstructor,
      weekendClasses: schoolsTable.weekendClasses,
      vehicleTypes: schoolsTable.vehicleTypes,
      transmission: schoolsTable.transmission,
      priceFrom: schoolsTable.priceFrom,
      priceTo: schoolsTable.priceTo,
      timings: schoolsTable.timings,
      serviceAreas: schoolsTable.serviceAreas,
      createdAt: schoolsTable.createdAt,
    })
    .from(schoolsTable)
    .leftJoin(localitiesTable, eq(schoolsTable.localityId, localitiesTable.id))
    .where(eq(schoolsTable.verified, true))
    .orderBy(sql`${schoolsTable.rating} DESC`)
    .limit(6);

  res.json(schools.map(s => ({ ...s, createdAt: s.createdAt.toISOString() })));
});

router.get("/schools/slug/:slug", async (req, res): Promise<void> => {
  const parsed = GetSchoolBySlugParams.safeParse(req.params);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const rows = await db
    .select({
      id: schoolsTable.id,
      name: schoolsTable.name,
      slug: schoolsTable.slug,
      localityId: schoolsTable.localityId,
      localityName: localitiesTable.name,
      address: schoolsTable.address,
      phone: schoolsTable.phone,
      whatsapp: schoolsTable.whatsapp,
      email: schoolsTable.email,
      description: schoolsTable.description,
      imageUrl: schoolsTable.imageUrl,
      rating: schoolsTable.rating,
      reviewCount: schoolsTable.reviewCount,
      verified: schoolsTable.verified,
      hasPickup: schoolsTable.hasPickup,
      womenInstructor: schoolsTable.womenInstructor,
      weekendClasses: schoolsTable.weekendClasses,
      vehicleTypes: schoolsTable.vehicleTypes,
      transmission: schoolsTable.transmission,
      priceFrom: schoolsTable.priceFrom,
      priceTo: schoolsTable.priceTo,
      timings: schoolsTable.timings,
      serviceAreas: schoolsTable.serviceAreas,
      createdAt: schoolsTable.createdAt,
    })
    .from(schoolsTable)
    .leftJoin(localitiesTable, eq(schoolsTable.localityId, localitiesTable.id))
    .where(eq(schoolsTable.slug, parsed.data.slug))
    .limit(1);

  if (rows.length === 0) {
    res.status(404).json({ error: "School not found" });
    return;
  }
  const s = rows[0];
  res.json({ ...s, createdAt: s.createdAt.toISOString() });
});

router.get("/schools/:id", async (req, res): Promise<void> => {
  const parsed = GetSchoolParams.safeParse({ id: Number(req.params.id) });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const rows = await db
    .select({
      id: schoolsTable.id,
      name: schoolsTable.name,
      slug: schoolsTable.slug,
      localityId: schoolsTable.localityId,
      localityName: localitiesTable.name,
      address: schoolsTable.address,
      phone: schoolsTable.phone,
      whatsapp: schoolsTable.whatsapp,
      email: schoolsTable.email,
      description: schoolsTable.description,
      imageUrl: schoolsTable.imageUrl,
      rating: schoolsTable.rating,
      reviewCount: schoolsTable.reviewCount,
      verified: schoolsTable.verified,
      hasPickup: schoolsTable.hasPickup,
      womenInstructor: schoolsTable.womenInstructor,
      weekendClasses: schoolsTable.weekendClasses,
      vehicleTypes: schoolsTable.vehicleTypes,
      transmission: schoolsTable.transmission,
      priceFrom: schoolsTable.priceFrom,
      priceTo: schoolsTable.priceTo,
      timings: schoolsTable.timings,
      serviceAreas: schoolsTable.serviceAreas,
      createdAt: schoolsTable.createdAt,
    })
    .from(schoolsTable)
    .leftJoin(localitiesTable, eq(schoolsTable.localityId, localitiesTable.id))
    .where(eq(schoolsTable.id, parsed.data.id))
    .limit(1);

  if (rows.length === 0) {
    res.status(404).json({ error: "School not found" });
    return;
  }
  const s = rows[0];
  res.json({ ...s, createdAt: s.createdAt.toISOString() });
});

router.post("/schools", async (req, res): Promise<void> => {
  const parsed = CreateSchoolBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const [school] = await db.insert(schoolsTable).values(parsed.data).returning();
  res.status(201).json({ ...school, createdAt: school.createdAt.toISOString() });
});

router.patch("/schools/:id", async (req, res): Promise<void> => {
  const paramsParsed = UpdateSchoolParams.safeParse({ id: Number(req.params.id) });
  const bodyParsed = UpdateSchoolBody.safeParse(req.body);
  if (!paramsParsed.success || !bodyParsed.success) {
    res.status(400).json({ error: "Invalid input" });
    return;
  }

  const [updated] = await db
    .update(schoolsTable)
    .set(bodyParsed.data)
    .where(eq(schoolsTable.id, paramsParsed.data.id))
    .returning();

  if (!updated) {
    res.status(404).json({ error: "School not found" });
    return;
  }
  res.json({ ...updated, createdAt: updated.createdAt.toISOString() });
});

router.delete("/schools/:id", async (req, res): Promise<void> => {
  const parsed = DeleteSchoolParams.safeParse({ id: Number(req.params.id) });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  await db.delete(schoolsTable).where(eq(schoolsTable.id, parsed.data.id));
  res.status(204).send();
});

export default router;
