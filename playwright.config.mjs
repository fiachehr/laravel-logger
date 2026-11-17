import { defineConfig } from "@playwright/test";
export default defineConfig({
  testDir: "tests/Browser",
  timeout: 30000,
  workers: 1,
  use: {
    baseURL: process.env.LOG_EXPLORER_TEST_URL || "http://127.0.0.1:18765",
    browserName: "chromium",
    trace: "retain-on-failure",
  },
  reporter: "list",
});
