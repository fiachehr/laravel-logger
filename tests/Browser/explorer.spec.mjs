import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
const base = process.env.LOG_EXPLORER_TEST_URL || "http://127.0.0.1:18765";
async function login(page) {
  await page.goto("/smoke/login");
  await page.getByRole("button", { name: "Sign in to smoke fixture" }).click();
  await expect(page.locator("#rows tr")).toHaveCount(25);
}

test("requires a real host session for pages, JSON and assets; enforces CSRF on login", async ({
  request,
}) => {
  for (const path of [
    "/log-explorer",
    "/log-explorer/api/entries",
    "/log-explorer/assets/explorer-v1.js",
  ])
    expect((await request.get(path)).status()).toBe(401);
  expect((await request.post("/smoke/login")).status()).toBe(419);
});

test("filters, cursors, details, grouping, JSON, clipboard and keyboard tabs work", async ({
  page,
  context,
}) => {
  const errors = [];
  page.on("pageerror", (error) => errors.push(error.message));
  await context.grantPermissions(["clipboard-read", "clipboard-write"], {
    origin: base,
  });
  await login(page);
  await page.getByRole("button", { name: "Next window" }).click();
  await expect(page.locator("#page-label")).toHaveText("Page 2");
  await page.getByRole("button", { name: "Previous" }).click();
  await expect(page.locator("#page-label")).toHaveText("Page 1");
  await page
    .getByRole("combobox", { name: "Severity", exact: true })
    .selectOption("ERROR");
  await page.getByRole("button", { name: "Search", exact: true }).click();
  await expect(page.locator("#rows tr")).toHaveCount(22);
  await expect(page.locator("#rows .badge").first()).toHaveText("! ERROR");
  await page.locator(".open-event").first().click();
  await expect(page.getByRole("dialog")).toBeVisible();
  await page.getByRole("tab", { name: "Exception", exact: true }).click();
  await expect(page.locator("#detail-content")).toContainText("Origin:");
  await page
    .getByRole("button", { name: "Copy request ID", exact: true })
    .click();
  await expect(page.locator("#copy-feedback")).toHaveText(
    "Copied sanitized content.",
  );
  await page.getByRole("tab", { name: "JSON", exact: true }).click();
  await expect(page.locator("#detail-content")).toContainText("schema_version");
  await page
    .getByRole("tab", { name: "JSON", exact: true })
    .press("ArrowRight");
  await expect(
    page.getByRole("tab", { name: "Raw", exact: true }),
  ).toBeFocused();
  await expect(page.locator("#detail-content")).toContainText("schema_version");
  await page.getByRole("button", { name: "Show this request" }).click();
  await expect(page.getByRole("dialog")).not.toBeVisible();
  await expect(page.locator("input[name=request_id]")).not.toBeEmpty();
  await expect(page.locator("#rows tr")).toHaveCount(1);
  await expect(page.locator("body")).not.toContainText("SECRET_CANARY");
  await page.getByRole("button", { name: "Reset", exact: true }).click();
  await expect(page.locator("#rows tr")).toHaveCount(25);
  await page
    .getByRole("combobox", { name: "Order", exact: true })
    .selectOption("oldest");
  await page.getByRole("button", { name: "Search", exact: true }).click();
  await expect(page.locator(".open-event").first()).toHaveText(
    "checkout.completed",
  );
  await page
    .getByRole("searchbox", { name: "Search logs", exact: true })
    .fill("nothing matches [.*]");
  await page.getByRole("button", { name: "Search", exact: true }).click();
  await expect(page.locator("#empty")).toBeVisible();
  expect(errors).toEqual([]);
});

test("stored hostile text stays inert in table, highlights and all detail tabs", async ({
  page,
}) => {
  await login(page);
  const payload =
    '<img src=x onerror="window.EXPLOITED=true"><script>window.EXPLOITED=true</script>';
  const entry = {
    schema_version: "1.0",
    timestamp: "2026-11-17T12:00:00.000Z",
    level: "ERROR",
    service: payload,
    event: payload,
    message: payload,
    request_id: "a".repeat(32),
    context: { operation: payload },
    exception: {
      type: payload,
      message: payload,
      file: payload,
      line: 1,
      trace: [{ file: payload, line: 1, class: payload, method: payload }],
    },
  };
  await page.route("**/api/entries?*", (route) =>
    route.fulfill({
      json: {
        data: [{ entry, raw: JSON.stringify(entry) }],
        next_cursor: null,
        meta: { scanned_bytes: 512, malformed_entries: 0 },
      },
    }),
  );
  await page
    .getByRole("searchbox", { name: "Search logs", exact: true })
    .fill("<img");
  await page.getByRole("button", { name: "Search", exact: true }).click();
  await expect(page.locator("#rows mark").first()).toHaveText("<img");
  await page.locator(".open-event").click();
  for (const tab of ["Overview", "Exception", "Context", "JSON", "Raw"]) {
    await page.getByRole("tab", { name: tab, exact: true }).click();
    await expect(page.locator("#detail-content")).toContainText("<img");
    expect(
      await page.locator("#detail-content img, #detail-content script").count(),
    ).toBe(0);
  }
  expect(await page.evaluate(() => window.EXPLOITED)).toBeUndefined();
});

test("responsive layout, themes and accessibility pass in the real host", async ({
  page,
}) => {
  await login(page);
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.screenshot({
    path: "docs/screenshots/explorer-desktop.png",
    fullPage: false,
  });
  let a11y = await new AxeBuilder({ page }).analyze();
  expect(a11y.violations).toEqual([]);
  await page.getByRole("button", { name: "Switch color theme" }).click();
  await page.screenshot({
    path: "docs/screenshots/explorer-dark.png",
    fullPage: false,
  });
  a11y = await new AxeBuilder({ page }).analyze();
  expect(a11y.violations).toEqual([]);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({
    path: "docs/screenshots/explorer-mobile.png",
    fullPage: false,
  });
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  await page.locator(".open-event").first().click();
  await expect(page.getByRole("dialog")).toBeVisible();
  await page.screenshot({
    path: "docs/screenshots/explorer-detail.png",
    fullPage: false,
  });
  a11y = await new AxeBuilder({ page }).analyze();
  expect(a11y.violations).toEqual([]);
  await page.keyboard.press("Escape");
  await expect(page.getByRole("dialog")).not.toBeVisible();
});

test("permission and malformed-entry feedback is readable", async ({
  page,
}) => {
  await login(page);
  await page.route("**/api/entries?*", (route) =>
    route.fulfill({ status: 403, json: { message: "Forbidden" } }),
  );
  await page.locator("#refresh").click();
  await expect(page.locator("#feedback")).toContainText("not authorized");
  await expect(page.locator("#rows tr")).toHaveCount(0);
  await page.unroute("**/api/entries?*");
  await page.route("**/api/entries?*", (route) =>
    route.fulfill({
      json: {
        data: [],
        next_cursor: null,
        meta: { scanned_bytes: 1024, malformed_entries: 2, bounded: true },
      },
    }),
  );
  await page.locator("#refresh").click();
  await expect(page.locator("#feedback")).toContainText(
    "2 malformed fragments skipped",
  );
});
