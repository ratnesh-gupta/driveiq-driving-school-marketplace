import { Router } from "express";
import { db } from "@workspace/db";
import { localitiesTable } from "@workspace/db";
import { eq } from "drizzle-orm";
import {
  GetLocalityParams,
  CreateLocalityBody,
  GetLocalityBySlugParams,
} from "@workspace/api-zod";

const router = Router();

router.get("/localities", async (req, res): Promise<void> => {
  const localities = await db.select().from(localitiesTable).orderBy(localitiesTable.name);
  res.json(localities);
});

router.post("/localities", async (req, res): Promise<void> => {
  const parsed = CreateLocalityBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const [locality] = await db.insert(localitiesTable).values(parsed.data).returning();
  res.status(201).json(locality);
});

router.get("/localities/slug/:slug", async (req, res): Promise<void> => {
  const parsed = GetLocalityBySlugParams.safeParse(req.params);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const rows = await db.select().from(localitiesTable).where(eq(localitiesTable.slug, parsed.data.slug)).limit(1);
  if (rows.length === 0) {
    res.status(404).json({ error: "Locality not found" });
    return;
  }
  res.json(rows[0]);
});

router.get("/localities/:id", async (req, res): Promise<void> => {
  const parsed = GetLocalityParams.safeParse({ id: Number(req.params.id) });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const rows = await db.select().from(localitiesTable).where(eq(localitiesTable.id, parsed.data.id)).limit(1);
  if (rows.length === 0) {
    res.status(404).json({ error: "Locality not found" });
    return;
  }
  res.json(rows[0]);
});

export default router;
