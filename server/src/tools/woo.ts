import { z } from "zod";
import { confirmSchema, siteIdSchema } from "../schemas.js";
import { clientFor, wrap, type RegisterTools } from "./common.js";

export const registerWooTools: RegisterTools = (server, ctx) => {
  server.tool(
    "get_mail_status",
    "Mail() availability, disable_functions, SMTP plugins, Woo From address, and recent FluentSMTP log rows (no passwords).",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/mail/status" });
    }),
  );

  server.tool(
    "send_test_mail",
    "Send one wp_mail probe. Recipient must be admin_email, the Woo From/Reply-To address, or the same domain as the site. Requires confirm=true. Does not mail arbitrary customers.",
    {
      site: siteIdSchema,
      to: z
        .string()
        .email()
        .optional()
        .describe("Optional. Defaults to admin_email. Same-domain or Woo From/Reply-To only."),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/mail/test",
        method: "POST",
        body: { to: args.to, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "get_payment_status",
    "Read-only payment flags: WooPayments, Stripe, PayPal, Square, COD, BACS, cheque (enabled/title/test mode). Never returns API keys, webhook secrets, cards, or bank account numbers.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/payments/status" });
    }),
  );

  server.tool(
    "list_orders",
    "List WooCommerce orders (HPOS-safe). Returns id, status, total, billing email, payment method title — not card details.",
    {
      site: siteIdSchema,
      limit: z.number().int().min(1).max(50).optional(),
      status: z.string().optional().describe("Woo status slug, e.g. processing, cancelled, trash"),
      offset: z.number().int().min(0).optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/orders",
        query: {
          limit: args.limit,
          status: args.status,
          offset: args.offset,
        },
      });
    }),
  );

  server.tool(
    "get_order",
    "Get one WooCommerce order with line items and recent notes. No payment-method secrets or card data.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/orders/${args.id}` });
    }),
  );

  server.tool(
    "update_order",
    "Set a WooCommerce order status. Requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      status: z.enum(["pending", "on-hold", "processing", "completed", "cancelled", "trash"]),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/orders/${args.id}`,
        method: "POST",
        body: { status: args.status, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "list_products",
    "List WooCommerce products (name, sku, status, prices, stock). No payment secrets.",
    {
      site: siteIdSchema,
      limit: z.number().int().min(1).max(50).optional(),
      status: z.string().optional().describe("Product status, e.g. publish, draft"),
      search: z.string().optional(),
      offset: z.number().int().min(0).optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/products",
        query: {
          limit: args.limit,
          status: args.status,
          search: args.search,
          offset: args.offset,
        },
      });
    }),
  );

  server.tool(
    "get_product",
    "Get one WooCommerce product including description excerpt and image URLs.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/products/${args.id}` });
    }),
  );

  server.tool(
    "update_product",
    "Update product name, status, catalog visibility, featured, stock, or prices. Requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      name: z.string().min(1).max(200).optional(),
      status: z.enum(["draft", "pending", "private", "publish"]).optional(),
      catalog_visibility: z.enum(["visible", "catalog", "search", "hidden"]).optional(),
      featured: z.boolean().optional(),
      stock_status: z.enum(["instock", "outofstock", "onbackorder"]).optional(),
      manage_stock: z.boolean().optional(),
      stock_quantity: z.number().int().nullable().optional(),
      regular_price: z.string().optional().describe("Non-negative number as a string, e.g. 49.90"),
      sale_price: z.string().optional().describe("Non-negative number, or empty to clear"),
      sku: z.string().optional(),
      short_description: z.string().optional(),
      category_ids: z.array(z.number().int().positive()).optional(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/products/${args.id}`,
        method: "POST",
        body: {
          name: args.name,
          status: args.status,
          catalog_visibility: args.catalog_visibility,
          featured: args.featured,
          stock_status: args.stock_status,
          manage_stock: args.manage_stock,
          stock_quantity: args.stock_quantity,
          regular_price: args.regular_price,
          sale_price: args.sale_price,
          sku: args.sku,
          short_description: args.short_description,
          category_ids: args.category_ids,
          confirm: args.confirm === true,
        },
      });
    }),
  );

  server.tool(
    "list_coupons",
    "List WooCommerce coupons (code, amount, type, usage). Customer email restrictions are counted, not listed.",
    {
      site: siteIdSchema,
      limit: z.number().int().min(1).max(50).optional(),
      offset: z.number().int().min(0).optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/coupons",
        query: { limit: args.limit, offset: args.offset },
      });
    }),
  );

  server.tool(
    "get_coupon",
    "Get one WooCommerce coupon. Does not return restricted customer emails.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/coupons/${args.id}` });
    }),
  );

  server.tool(
    "list_shipping_zones",
    "List WooCommerce shipping zones, location codes, and method titles/costs. No carrier API keys.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/shipping-zones" });
    }),
  );

  server.tool(
    "create_coupon",
    "Create a WooCommerce coupon (code, amount, type). Does not attach customer emails. Requires confirm=true.",
    {
      site: siteIdSchema,
      code: z.string().min(3).max(40),
      amount: z.string().describe("Percent or fixed amount, e.g. 10 or 15.00"),
      discount_type: z.enum(["percent", "fixed_cart", "fixed_product"]).optional(),
      usage_limit: z.number().int().min(0).optional(),
      individual_use: z.boolean().optional(),
      free_shipping: z.boolean().optional(),
      minimum_amount: z.string().optional(),
      date_expires: z.string().optional().describe("YYYY-MM-DD"),
      enabled: z.boolean().optional(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/coupons",
        method: "POST",
        body: {
          code: args.code,
          amount: args.amount,
          discount_type: args.discount_type,
          usage_limit: args.usage_limit,
          individual_use: args.individual_use,
          free_shipping: args.free_shipping,
          minimum_amount: args.minimum_amount,
          date_expires: args.date_expires,
          enabled: args.enabled,
          confirm: args.confirm === true,
        },
      });
    }),
  );

  server.tool(
    "update_coupon",
    "Update a WooCommerce coupon. Requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      amount: z.string().optional(),
      discount_type: z.enum(["percent", "fixed_cart", "fixed_product"]).optional(),
      usage_limit: z.number().int().min(0).optional(),
      individual_use: z.boolean().optional(),
      free_shipping: z.boolean().optional(),
      minimum_amount: z.string().optional(),
      date_expires: z.string().optional(),
      enabled: z.boolean().optional(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/coupons/${args.id}`,
        method: "POST",
        body: {
          amount: args.amount,
          discount_type: args.discount_type,
          usage_limit: args.usage_limit,
          individual_use: args.individual_use,
          free_shipping: args.free_shipping,
          minimum_amount: args.minimum_amount,
          date_expires: args.date_expires,
          enabled: args.enabled,
          confirm: args.confirm === true,
        },
      });
    }),
  );

  server.tool(
    "list_customers",
    "List WooCommerce customers (id, name, email, order count, total spent). No passwords.",
    {
      site: siteIdSchema,
      limit: z.number().int().min(1).max(50).optional(),
      offset: z.number().int().min(0).optional(),
      search: z.string().optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/customers",
        query: { limit: args.limit, offset: args.offset, search: args.search },
      });
    }),
  );

  server.tool(
    "get_customer",
    "Get one WooCommerce customer by user id.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/customers/${args.id}` });
    }),
  );

  server.tool(
    "get_store_report",
    "Order count and revenue between two dates (YYYY-MM-DD). No card data.",
    {
      site: siteIdSchema,
      after: z.string().describe("Start date YYYY-MM-DD"),
      before: z.string().describe("End date YYYY-MM-DD"),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/reports/store",
        query: { after: args.after, before: args.before },
      });
    }),
  );

  server.tool(
    "list_low_stock",
    "List products that are out of stock or at/below a quantity threshold.",
    {
      site: siteIdSchema,
      threshold: z.number().int().min(0).max(1000).optional(),
      limit: z.number().int().min(1).max(50).optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/products/low-stock",
        query: { threshold: args.threshold, limit: args.limit },
      });
    }),
  );

  server.tool(
    "list_variations",
    "List variations of a variable WooCommerce product.",
    {
      site: siteIdSchema,
      id: z.number().int().positive().describe("Parent product id"),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/products/${args.id}/variations` });
    }),
  );

  server.tool(
    "update_variation",
    "Update a variation's stock or prices. Requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      status: z.enum(["publish", "private", "draft"]).optional(),
      stock_status: z.enum(["instock", "outofstock", "onbackorder"]).optional(),
      manage_stock: z.boolean().optional(),
      stock_quantity: z.number().int().nullable().optional(),
      regular_price: z.string().optional(),
      sale_price: z.string().optional(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/variations/${args.id}`,
        method: "POST",
        body: {
          status: args.status,
          stock_status: args.stock_status,
          manage_stock: args.manage_stock,
          stock_quantity: args.stock_quantity,
          regular_price: args.regular_price,
          sale_price: args.sale_price,
          confirm: args.confirm === true,
        },
      });
    }),
  );

  server.tool(
    "list_reviews",
    "List WooCommerce product reviews (author, rating, excerpt). Reviewer emails are omitted.",
    {
      site: siteIdSchema,
      limit: z.number().int().min(1).max(50).optional(),
      offset: z.number().int().min(0).optional(),
      status: z.string().optional().describe("approve, hold, trash, or all"),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/reviews",
        query: { limit: args.limit, offset: args.offset, status: args.status },
      });
    }),
  );

  server.tool(
    "moderate_review",
    "Approve, hold, or trash a product review. Requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      status: z.enum(["approve", "hold", "trash"]),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/reviews/${args.id}`,
        method: "POST",
        body: { status: args.status, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "add_order_note",
    "Add a private staff note on an order. Never emails the customer.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      note: z.string().min(1).max(1000),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/orders/${args.id}/note`,
        method: "POST",
        body: { note: args.note },
      });
    }),
  );

  server.tool(
    "create_refund",
    "Record a WooCommerce refund. Does not call Stripe/PayPal/WooPayments. Requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive().describe("Order id"),
      amount: z.string(),
      reason: z.string().max(200).optional(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/orders/${args.id}/refund`,
        method: "POST",
        body: { amount: args.amount, reason: args.reason, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "get_woo_emails",
    "List WooCommerce transactional emails and whether each is enabled. Does not send mail.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/woo-emails" });
    }),
  );

  server.tool(
    "update_woo_email",
    "Enable or disable one WooCommerce email type (new_order, customer_processing_order, …). Does not change recipients. Requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.string().min(1),
      enabled: z.boolean(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/woo-emails/${args.id}`,
        method: "POST",
        body: { enabled: args.enabled, confirm: args.confirm === true },
      });
    }),
  );

  server.tool(
    "list_tax_rates",
    "List WooCommerce tax rates (country, state, rate, name).",
    {
      site: siteIdSchema,
      limit: z.number().int().min(1).max(100).optional(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: "/tax-rates",
        query: { limit: args.limit },
      });
    }),
  );

  server.tool(
    "get_seo",
    "Read Yoast and Rank Math title, description, canonical, and robots for a post, page, or product.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: `/seo/${args.id}` });
    }),
  );

  server.tool(
    "update_seo",
    "Update Yoast / Rank Math title, description, or canonical. Requires confirm=true.",
    {
      site: siteIdSchema,
      id: z.number().int().positive(),
      yoast_title: z.string().optional(),
      yoast_description: z.string().optional(),
      yoast_canonical: z.string().optional(),
      yoast_noindex: z.boolean().optional(),
      rank_math_title: z.string().optional(),
      rank_math_description: z.string().optional(),
      rank_math_canonical: z.string().optional(),
      confirm: confirmSchema,
    },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({
        path: `/seo/${args.id}`,
        method: "POST",
        body: {
          yoast_title: args.yoast_title,
          yoast_description: args.yoast_description,
          yoast_canonical: args.yoast_canonical,
          yoast_noindex: args.yoast_noindex,
          rank_math_title: args.rank_math_title,
          rank_math_description: args.rank_math_description,
          rank_math_canonical: args.rank_math_canonical,
          confirm: args.confirm === true,
        },
      });
    }),
  );

  server.tool(
    "get_integrations_status",
    "Read-only scan of famous plugins: Woo, Stripe/PayPal/Square, Google Site Kit / Listings / Analytics, Meta pixel/catalog, Yoast/Rank Math. Public tracking IDs only — never OAuth tokens, licenses, or webhook secrets.",
    { site: siteIdSchema },
    wrap(async (args) => {
      const { client } = clientFor(ctx, args.site);
      return client.request({ path: "/integrations" });
    }),
  );
};
