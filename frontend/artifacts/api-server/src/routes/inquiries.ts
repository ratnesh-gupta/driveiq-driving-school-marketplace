import { Router } from "express";
import { db } from "@workspace/db";
import { inquiriesTable, schoolsTable } from "@workspace/db";
import { eq, and, sql } from "drizzle-orm";
import {
  ListInquiriesQueryParams,
  CreateInquiryBody,
  UpdateInquiryParams,
  UpdateInquiryBody,
} from "@workspace/api-zod";

const router = Router();

router.get("/inquiries", async (req, res): Promise<void> => {
  const parsed = ListInquiriesQueryParams.safeParse(req.query);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const conditions = [];
  if (parsed.data.schoolId !== undefined) {
    conditions.push(eq(inquiriesTable.schoolId, parsed.data.schoolId));
  }
  if (parsed.data.status !== undefined) {
    conditions.push(eq(inquiriesTable.status, parsed.data.status));
  }

  const inquiries = await db
    .select({
      id: inquiriesTable.id,
      schoolId: inquiriesTable.schoolId,
      schoolName: schoolsTable.name,
      name: inquiriesTable.name,
      phone: inquiriesTable.phone,
      email: inquiriesTable.email,
      vehicleType: inquiriesTable.vehicleType,
      message: inquiriesTable.message,
      status: inquiriesTable.status,
      createdAt: inquiriesTable.createdAt,
    })
    .from(inquiriesTable)
    .leftJoin(schoolsTable, eq(inquiriesTable.schoolId, schoolsTable.id))
    .where(conditions.length > 0 ? and(...conditions) : undefined)
    .orderBy(sql`${inquiriesTable.createdAt} DESC`);

  res.json(inquiries.map(i => ({ ...i, createdAt: i.createdAt.toISOString() })));
});

router.post("/inquiries", async (req, res): Promise<void> => {
  const parsed = CreateInquiryBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const [inquiry] = await db.insert(inquiriesTable).values(parsed.data).returning();
  res.status(201).json({ ...inquiry, createdAt: inquiry.createdAt.toISOString() });
});

router.patch("/inquiries/:id", async (req, res): Promise<void> => {
  const paramsParsed = UpdateInquiryParams.safeParse({ id: Number(req.params.id) });
  const bodyParsed = UpdateInquiryBody.safeParse(req.body);
  if (!paramsParsed.success || !bodyParsed.success) {
    res.status(400).json({ error: "Invalid input" });
    return;
  }

  const [updated] = await db
    .update(inquiriesTable)
    .set(bodyParsed.data)
    .where(eq(inquiriesTable.id, paramsParsed.data.id))
    .returning();

  if (!updated) {
    res.status(404).json({ error: "Inquiry not found" });
    return;
  }
  res.json({ ...updated, createdAt: updated.createdAt.toISOString() });
});

export default router;
