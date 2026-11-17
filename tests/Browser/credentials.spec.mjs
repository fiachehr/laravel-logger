import { test, expect } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";
const credentialsBase =
  process.env.LOG_EXPLORER_CREDENTIALS_TEST_URL || "http://127.0.0.1:18767";
test.use({ baseURL: credentialsBase });
const password = "Synthetic-browser-password-ONLY";
async function login(page) {
  await page.goto("/log-explorer");
  await expect(page).toHaveURL(/\/log-explorer\/login$/);
  await page.getByLabel("Username", { exact: true }).fill("smoke-operator");
  await page.getByLabel("Password", { exact: true }).fill(password);
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page.locator("#rows tr")).toHaveCount(25);
}

test("built-in Blade login denies unauthenticated data and enforces CSRF without any host user", async ({
  page,
  request,
}) => {
  await page.goto("/log-explorer");
  await expect(page).toHaveURL(/\/log-explorer\/login$/);
  await expect(
    page.getByRole("heading", { name: "Sign in to your logs" }),
  ).toBeVisible();
  expect((await request.get("/log-explorer/api/entries")).status()).toBe(401);
  expect(
    (await request.get("/log-explorer/assets/explorer-v1.js")).status(),
  ).toBe(401);
  expect(
    (await request.get("/log-explorer/login/assets/login-v1.css")).status(),
  ).toBe(200);
  expect(
    (
      await request.post("/log-explorer/login", {
        form: { username: "smoke-operator", password },
      })
    ).status(),
  ).toBe(419);
  await login(page);
  await expect(
    page.getByRole("button", { name: "Sign out", exact: true }),
  ).toBeVisible();
  await page.locator(".open-event").first().click();
  await expect(page.getByRole("dialog")).toBeVisible();
  await page.keyboard.press("Escape");
  await page.getByRole("button", { name: "Sign out", exact: true }).click();
  await expect(page).toHaveURL(/\/log-explorer\/login$/);
  expect((await page.request.get("/log-explorer/api/entries")).status()).toBe(
    401,
  );
  await page.goto("/log-explorer");
  await expect(page).toHaveURL(/\/log-explorer\/login$/);
});

test("wrong credentials show a generic error without echoing the password", async ({
  page,
}) => {
  await page.goto("/log-explorer/login");
  await page.getByLabel("Username", { exact: true }).fill("smoke-operator");
  await page
    .getByLabel("Password", { exact: true })
    .fill("SYNTHETIC-SECRET-CANARY");
  await page.getByRole("button", { name: "Sign in", exact: true }).click();
  await expect(page.getByRole("alert")).toHaveText(
    "Invalid username or password.",
  );
  await expect(page.getByLabel("Password", { exact: true })).toHaveValue("");
  await expect(page.locator("body")).not.toContainText(
    "SYNTHETIC-SECRET-CANARY",
  );
});

test("login is accessible and responsive in light, dark and mobile layouts", async ({
  page,
}) => {
  await page.goto("/log-explorer/login");
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.emulateMedia({ colorScheme: "light" });
  await page.screenshot({ path: "docs/screenshots/explorer-login.png" });
  let result = await new AxeBuilder({ page }).analyze();
  expect(result.violations).toEqual([]);
  await page.emulateMedia({ colorScheme: "dark" });
  await page.screenshot({ path: "docs/screenshots/explorer-login-dark.png" });
  result = await new AxeBuilder({ page }).analyze();
  expect(result.violations).toEqual([]);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.screenshot({
    path: "docs/screenshots/explorer-login-mobile.png",
    fullPage: true,
  });
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true);
  await page.getByLabel("Username", { exact: true }).focus();
  await page.keyboard.press("Tab");
  await expect(page.getByLabel("Password", { exact: true })).toBeFocused();
  await page.keyboard.press("Tab");
  await expect(
    page.getByRole("button", { name: "Sign in", exact: true }),
  ).toBeFocused();
});

test("an expired independent session pauses polling and provides the correct sign-in link", async ({
  page,
}) => {
  await login(page);
  await page.route("**/api/entries?*", (route) =>
    route.fulfill({ status: 401, json: { message: "Unauthenticated" } }),
  );
  await page
    .getByRole("combobox", { name: "Auto refresh", exact: true })
    .selectOption("5");
  await page.locator("#refresh").click();
  await expect(page.locator("#feedback")).toContainText(
    "Your dashboard session expired",
  );
  await expect(
    page.getByRole("link", { name: "Sign in again", exact: true }),
  ).toBeVisible();
  await expect(
    page.getByRole("combobox", { name: "Auto refresh", exact: true }),
  ).toHaveValue("0");
});
