import { z } from "zod";

export const siteIdSchema = z
  .string()
  .min(1)
  .optional()
  .describe("Connected site id. Uses the selected or default site if omitted.");

export const paginationSchema = {
  page: z.number().int().min(1).optional().describe("Page number (default 1)"),
  per_page: z.number().int().min(1).max(100).optional().describe("Results per page (max 100)"),
};

export const confirmSchema = z
  .boolean()
  .optional()
  .describe("Must be true to execute a destructive action after preview.");

export const explicitPublishSchema = z
  .boolean()
  .optional()
  .describe('Set true only when the user explicitly asked to publish. Otherwise the plugin forces draft.');
