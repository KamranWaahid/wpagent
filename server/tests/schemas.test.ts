import { describe, expect, it } from "vitest";
import { confirmSchema, explicitPublishSchema, siteIdSchema } from "../src/schemas.js";
import { z } from "zod";

const builderSaveSchema = z.object({
  site: siteIdSchema,
  builder: z.enum(["elementor", "bricks", "beaver", "breakdance", "divi"]),
  title: z.string().optional(),
  explicit_publish: explicitPublishSchema,
  widget_id: z.string().min(1).optional(),
  settings: z.record(z.string(), z.unknown()).optional(),
});

const updatePageSchema = z.object({
  id: z.number().int().positive(),
  status: z.enum(["draft", "pending", "private", "publish", "future", "trash"]).optional(),
});

const createPostSchema = z.object({
  site: siteIdSchema,
  title: z.string().min(1),
  content: z.string().optional(),
  status: z.enum(["draft", "pending", "private", "publish", "future"]).optional(),
  explicit_publish: explicitPublishSchema,
});

const searchReplaceSchema = z.object({
  search: z.string().min(1),
  replace: z.string(),
  confirm: confirmSchema,
});

const updateCoreSchema = z.object({
  site: siteIdSchema,
  confirm: confirmSchema,
});

describe("MCP tool input validation", () => {
  it("requires a title for create_post", () => {
    const result = createPostSchema.safeParse({ title: "" });
    expect(result.success).toBe(false);
  });

  it("accepts draft create_post without explicit_publish", () => {
    const result = createPostSchema.parse({ title: "Hello", status: "draft" });
    expect(result.explicit_publish).toBeUndefined();
  });

  it("rejects unknown post statuses", () => {
    const result = createPostSchema.safeParse({ title: "Hello", status: "live" });
    expect(result.success).toBe(false);
  });

  it("treats confirm as optional and not true by default", () => {
    const parsed = searchReplaceSchema.parse({ search: "foo", replace: "bar" });
    expect(parsed.confirm).toBeUndefined();
  });

  it("requires search text", () => {
    expect(searchReplaceSchema.safeParse({ search: "", replace: "x" }).success).toBe(false);
  });

  it("allows omitting site id", () => {
    expect(siteIdSchema.parse(undefined)).toBeUndefined();
  });

  it("does not treat update_core as confirmed by default", () => {
    const parsed = updateCoreSchema.parse({});
    expect(parsed.confirm).toBeUndefined();
    expect(updateCoreSchema.parse({ confirm: true }).confirm).toBe(true);
  });

  it("requires a pdf source for read_pdf", () => {
    const schema = z.object({
      id: z.number().int().positive().optional(),
      path: z.string().optional(),
      url: z.string().url().optional(),
    });
    const empty = schema.parse({});
    expect(empty.id ?? empty.path ?? empty.url).toBeUndefined();
    expect(schema.parse({ path: "2026/09/brief.pdf" }).path).toBe("2026/09/brief.pdf");
  });

  it("install_plugin slug is not confirmed by default", () => {
    const schema = z.object({
      slug: z.string().min(1),
      confirm: confirmSchema,
    });
    expect(schema.parse({ slug: "akismet" }).confirm).toBeUndefined();
    expect(schema.parse({ slug: "akismet", confirm: true }).confirm).toBe(true);
  });

  it("rejects unknown page builders", () => {
    expect(builderSaveSchema.safeParse({ builder: "oxygen" }).success).toBe(false);
    expect(builderSaveSchema.parse({ builder: "elementor" }).explicit_publish).toBeUndefined();
  });

  it("accepts Elementor widget patches and page trash status", () => {
    const patched = builderSaveSchema.parse({
      builder: "elementor",
      widget_id: "chkout",
      settings: { field_label: "Prénom" },
    });
    expect(patched.widget_id).toBe("chkout");
    expect(updatePageSchema.parse({ id: 1686, status: "trash" }).status).toBe("trash");
    expect(updatePageSchema.safeParse({ id: 1686, status: "live" }).success).toBe(false);
  });
});
