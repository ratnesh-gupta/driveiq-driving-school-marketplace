import { Router } from "express";
import { db } from "@workspace/db";
import { reviewsTable, schoolsTable } from "@workspace/db";
import { eq, and, sql } from "drizzle-orm";
import {
  ListReviewsQueryParams,
  CreateReviewBody,
  UpdateReviewParams,
  UpdateReviewBody,
  DeleteReviewParams,
} from "@workspace/api-zod";

const router = Router();

router.get("/reviews", async (req, res): Promise<void> => {
  const parsed = ListReviewsQueryParams.safeParse(req.query);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const conditions = [];
  if (parsed.data.schoolId !== undefined) {
    conditions.push(eq(reviewsTable.schoolId, parsed.data.schoolId));
  }

  const reviews = await db
    .select({
      id: reviewsTable.id,
      schoolId: reviewsTable.schoolId,
      schoolName: schoolsTable.name,
      authorName: reviewsTable.authorName,
      rating: reviewsTable.rating,
      content: reviewsTable.content,
      approved: reviewsTable.approved,
      createdAt: reviewsTable.createdAt,
    })
    .from(reviewsTable)
    .leftJoin(schoolsTable, eq(reviewsTable.schoolId, schoolsTable.id))
    .where(conditions.length > 0 ? and(...conditions) : undefined)
    .orderBy(sql`${reviewsTable.createdAt} DESC`);

  res.json(reviews.map(r => ({ ...r, createdAt: r.createdAt.toISOString() })));
});

router.post("/reviews", async (req, res): Promise<void> => {
  const parsed = CreateReviewBody.safeParse(req.body);
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  const [review] = await db.insert(reviewsTable).values(parsed.data).returning();
  res.status(201).json({ ...review, createdAt: review.createdAt.toISOString() });
});

router.patch("/reviews/:id", async (req, res): Promise<void> => {
  const paramsParsed = UpdateReviewParams.safeParse({ id: Number(req.params.id) });
  const bodyParsed = UpdateReviewBody.safeParse(req.body);
  if (!paramsParsed.success || !bodyParsed.success) {
    res.status(400).json({ error: "Invalid input" });
    return;
  }

  const [updated] = await db
    .update(reviewsTable)
    .set(bodyParsed.data)
    .where(eq(reviewsTable.id, paramsParsed.data.id))
    .returning();

  if (!updated) {
    res.status(404).json({ error: "Review not found" });
    return;
  }
  res.json({ ...updated, createdAt: updated.createdAt.toISOString() });
});

router.delete("/reviews/:id", async (req, res): Promise<void> => {
  const parsed = DeleteReviewParams.safeParse({ id: Number(req.params.id) });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }
  await db.delete(reviewsTable).where(eq(reviewsTable.id, parsed.data.id));
  res.status(204).send();
});

export default router;
