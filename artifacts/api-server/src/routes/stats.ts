import { Router } from "express";
import { db } from "@workspace/db";
import { schoolsTable, localitiesTable, reviewsTable, inquiriesTable } from "@workspace/db";
import { eq, count, avg, sql } from "drizzle-orm";
import { GetSchoolStatsParams } from "@workspace/api-zod";

const router = Router();

router.get("/stats/overview", async (req, res): Promise<void> => {
  const [schoolStats] = await db
    .select({
      totalSchools: count(),
      verifiedSchools: sql<number>`cast(sum(case when ${schoolsTable.verified} then 1 else 0 end) as int)`,
      avgRating: avg(schoolsTable.rating),
    })
    .from(schoolsTable);

  const [localityStats] = await db
    .select({ totalLocalities: count() })
    .from(localitiesTable);

  const [reviewStats] = await db
    .select({ totalReviews: count() })
    .from(reviewsTable);

  const [inquiryStats] = await db
    .select({ totalInquiries: count() })
    .from(inquiriesTable);

  res.json({
    totalSchools: Number(schoolStats.totalSchools),
    totalLocalities: Number(localityStats.totalLocalities),
    totalReviews: Number(reviewStats.totalReviews),
    totalInquiries: Number(inquiryStats.totalInquiries),
    verifiedSchools: Number(schoolStats.verifiedSchools ?? 0),
    avgRating: Number(schoolStats.avgRating ?? 0),
  });
});

router.get("/stats/school/:schoolId", async (req, res): Promise<void> => {
  const parsed = GetSchoolStatsParams.safeParse({ schoolId: Number(req.params.schoolId) });
  if (!parsed.success) {
    res.status(400).json({ error: parsed.error.message });
    return;
  }

  const { schoolId } = parsed.data;

  const [inquiryStats] = await db
    .select({
      totalInquiries: count(),
      pendingInquiries: sql<number>`cast(sum(case when ${inquiriesTable.status} = 'pending' then 1 else 0 end) as int)`,
      thisMonthInquiries: sql<number>`cast(sum(case when ${inquiriesTable.createdAt} >= date_trunc('month', now()) then 1 else 0 end) as int)`,
    })
    .from(inquiriesTable)
    .where(eq(inquiriesTable.schoolId, schoolId));

  const [reviewStats] = await db
    .select({
      totalReviews: count(),
      avgRating: avg(reviewsTable.rating),
    })
    .from(reviewsTable)
    .where(eq(reviewsTable.schoolId, schoolId));

  res.json({
    schoolId,
    totalInquiries: Number(inquiryStats.totalInquiries),
    pendingInquiries: Number(inquiryStats.pendingInquiries ?? 0),
    thisMonthInquiries: Number(inquiryStats.thisMonthInquiries ?? 0),
    totalReviews: Number(reviewStats.totalReviews),
    avgRating: Number(reviewStats.avgRating ?? 0),
  });
});

export default router;
