import { Router, type IRouter } from "express";
import healthRouter from "./health";
import schoolsRouter from "./schools";
import localitiesRouter from "./localities";
import reviewsRouter from "./reviews";
import inquiriesRouter from "./inquiries";
import packagesRouter from "./packages";
import statsRouter from "./stats";

const router: IRouter = Router();

router.use(healthRouter);
router.use(schoolsRouter);
router.use(localitiesRouter);
router.use(reviewsRouter);
router.use(inquiriesRouter);
router.use(packagesRouter);
router.use(statsRouter);

export default router;
