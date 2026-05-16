import { Router } from "express";
import { db } from "@workspace/db";
import { packagesTable } from "@workspace/db";
import { eq, and } from "drizzle-orm";
import {
  ListPackagesQueryParams,
  CreatePackageBody,
  UpdatePackageParams,
  UpdatePackageBody,
  DeletePackageParams,
} from "@workspace/api-zod";

const router = Router();

router.get("/packages", async (req, res): Promise<void> => {
  const parsed = ListPackagesQueryParams.safeParse(req.query);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const conditions = [];
  if (parsed.data.schoolId !== undefined) {
    conditions.push(eq(packagesTable.schoolId, parsed.data.schoolId));
  }

  const packages = await db
    .select()
    .from(packagesTable)
    .where(conditions.length > 0 ? and(...conditions) : undefined);

  res.json(packages);
});

router.post("/packages", async (req, res): Promise<void> => {
  const parsed = CreatePackageBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const [pkg] = await db.insert(packagesTable).values(parsed.data).returning();
  res.status(201).json(pkg);
});

router.patch("/packages/:id", async (req, res): Promise<void> => {
  const paramsParsed = UpdatePackageParams.safeParse({ id: Number(req.params.id) });
  const bodyParsed = UpdatePackageBody.safeParse(req.body);
  if (!paramsParsed.success || !bodyParsed.success) {
    res.status(400).json({ error: "Invalid input" });
    return;
  }

  const [updated] = await db
    .update(packagesTable)
    .set(bodyParsed.data)
    .where(eq(packagesTable.id, paramsParsed.data.id))
    .returning();

  if (!updated) {
    res.status(404).json({ error: "Package not found" });
    return;
  }
  res.json(updated);
});

router.delete("/packages/:id", async (req, res): Promise<void> => {
  const parsed = DeletePackageParams.safeParse({ id: Number(req.params.id) });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  await db.delete(packagesTable).where(eq(packagesTable.id, parsed.data.id));
  res.status(204).send();
});

export default router;
