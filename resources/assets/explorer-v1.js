"use strict";
(() => {
  const $ = (s) => document.querySelector(s);
  const form = $("#filters");
  const rows = $("#rows");
  const feedback = $("#feedback");
  const detail = $("#detail");
  const panel = $("#detail-content");
  let data = [],
    cursors = [null],
    page = 0,
    nextCursor = null,
    selected = null,
    activeTab = "overview",
    controller,
    timer,
    backoff = 1;
  const text = (tag, value, cls) => {
    const n = document.createElement(tag);
    n.textContent = String(value ?? "—");
    if (cls) n.className = cls;
    return n;
  };
  const str = (value) => (typeof value === "string" ? value : "");
  function highlight(node, value) {
    const source = String(value ?? "—"),
      needle = form.elements.q.value;
    if (!needle) {
      node.textContent = source;
      return;
    }
    let start = 0,
      index;
    while (
      (index = source
        .toLocaleLowerCase()
        .indexOf(needle.toLocaleLowerCase(), start)) !== -1
    ) {
      node.append(
        document.createTextNode(source.slice(start, index)),
        text("mark", source.slice(index, index + needle.length)),
      );
      start = index + needle.length;
    }
    node.append(document.createTextNode(source.slice(start)));
  }
  const badge = (level) =>
    text(
      "span",
      `${["ERROR", "CRITICAL", "ALERT", "EMERGENCY"].includes(level) ? "!" : "•"} ${level}`,
      `badge level-${/^[A-Z]+$/.test(level) ? level.toLowerCase() : "debug"}`,
    );
  function renderRows() {
    rows.replaceChildren();
    for (const item of data) {
      const e = item.entry,
        tr = document.createElement("tr"),
        date = new Date(e.timestamp),
        zone = $("#timezone").value === "utc" ? "UTC" : undefined;
      const time = text(
        "td",
        Number.isNaN(date.getTime())
          ? "Invalid date"
          : date.toLocaleTimeString("en-GB", { timeZone: zone, hour12: false }),
        "mono",
      );
      time.append(
        text(
          "span",
          Number.isNaN(date.getTime())
            ? ""
            : date.toLocaleDateString("en-CA", { timeZone: zone }),
          "time-date",
        ),
      );
      const level = document.createElement("td");
      level.append(badge(str(e.level)));
      const service = text("td", e.service, "mono"),
        event = document.createElement("td");
      const open = text("button", "", "open-event");
      open.type = "button";
      highlight(open, e.event || "Open event");
      open.addEventListener("click", () => openDetail(item));
      const message = text("span", "", "event-message");
      highlight(message, e.message);
      event.append(open, message);
      const status = text("td", e.context?.status_code, "mono"),
        request = document.createElement("td");
      if (str(e.request_id)) {
        const b = text(
          "button",
          e.request_id.slice(0, 10) + "…",
          "request-link",
        );
        b.title = e.request_id;
        b.setAttribute(
          "aria-label",
          `${b.textContent}: filter request ${e.request_id}`,
        );
        b.addEventListener("click", () => group(e.request_id));
        request.append(b);
      } else request.textContent = "—";
      const exception = text("td", e.exception?.type || "—", "mono");
      if (e.exception?.file)
        exception.append(
          text(
            "span",
            `${e.exception.file}:${e.exception.line ?? "?"}`,
            "origin",
          ),
        );
      tr.append(time, level, service, event, status, request, exception);
      rows.append(tr);
    }
    $("#empty").hidden = data.length > 0;
  }
  function filters() {
    const params = new URLSearchParams();
    for (const [key, value] of new FormData(form))
      if (String(value).trim()) {
        params.set(
          key,
          ["from", "until"].includes(key)
            ? new Date(`${value}Z`).toISOString()
            : String(value).trim(),
        );
      }
    return params;
  }
  function schedule() {
    clearTimeout(timer);
    const seconds = Number($("#poll").value);
    if (seconds && !document.hidden && page === 0 && !detail.open)
      timer = setTimeout(
        () => load(true),
        Math.min(60000, seconds * 1000 * backoff),
      );
  }
  async function load(reset = false) {
    if (reset) {
      page = 0;
      cursors = [null];
    }
    clearTimeout(timer);
    controller?.abort();
    controller = new AbortController();
    const current = controller;
    const params = filters();
    if (location.search) history.replaceState(null, "", location.pathname);
    if (cursors[page]) params.set("cursor", cursors[page]);
    rows.setAttribute("aria-busy", "true");
    feedback.textContent = "Reading a bounded search window…";
    feedback.classList.remove("error");
    $("#next").disabled = true;
    $("#previous").disabled = true;
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]');
      if (!csrf || !csrf.content)
        throw new Error("Dashboard session is unavailable. Reload the page.");
      const response = await fetch(document.body.dataset.endpoint, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrf.content,
        },
        body: JSON.stringify(Object.fromEntries(params.entries())),
        signal: current.signal,
        credentials: "same-origin",
        cache: "no-store",
      });
      if (response.status === 401 || response.status === 403) {
        $("#poll").value = "0";
        if (response.status === 401 && $("#sign-in-again")) {
          $("#sign-in-again").hidden = false;
          if ($("#dashboard-sign-out")) $("#dashboard-sign-out").hidden = true;
        }
      }
      if (!response.ok)
        throw new Error(
          {
            401: document.body.dataset.login
              ? "Your dashboard session expired. Sign in again."
              : "Sign in to the host application to view logs.",
            403: "Your account is not authorized to view logs.",
            404: "The dashboard is disabled.",
            419: "Reload the page and search again.",
            422: "Invalid filters or expired cursor. Reset the search.",
            429: "Rate limit reached. Pause and try again.",
            503: "Log storage is unavailable. Run log-explorer:doctor.",
          }[response.status] || "Unable to load logs. Try again.",
        );
      const result = await response.json();
      if (current !== controller) return;
      data = result.data;
      nextCursor = result.next_cursor;
      renderRows();
      $("#page-label").textContent = `Page ${page + 1}`;
      feedback.textContent = `${data.length} events in this window${result.meta.bounded ? " · Scan limit reached; continue with Next window." : ""}${result.meta.malformed_entries ? ` · ${result.meta.malformed_entries} malformed fragments skipped.` : ""}${result.meta.rotated_files ? " · Rotated files skipped; refresh for a new snapshot." : ""}`;
      $("#scan-meta").textContent =
        `${(result.meta.scanned_bytes / 1024).toFixed(1)} KiB scanned · ${$("#timezone").value === "utc" ? "UTC" : "local time"} · sanitized data`;
      backoff = 1;
    } catch (error) {
      if (error.name === "AbortError") return;
      data = [];
      rows.replaceChildren();
      $("#empty").hidden = true;
      nextCursor = null;
      feedback.textContent = error.message || "Unable to load logs.";
      feedback.classList.add("error");
      backoff = Math.min(12, backoff * 2);
    } finally {
      if (current === controller) {
        rows.removeAttribute("aria-busy");
        $("#next").disabled = !nextCursor;
        $("#previous").disabled = page === 0;
        schedule();
      }
    }
  }
  function group(id) {
    form.elements.request_id.value = id;
    if (detail.open) detail.close();
    form.querySelector("details").open = true;
    load(true);
  }
  function json(value) {
    const pre = document.createElement("pre");
    const formatted = JSON.stringify(value, null, 2) ?? "null";
    const regex =
      /("(?:\\.|[^"\\])*"\s*:)|("(?:\\.|[^"\\])*")|\b(-?\d+(?:\.\d+)?|true|false|null)\b/g;
    let start = 0;
    for (const match of formatted.matchAll(regex)) {
      pre.append(
        document.createTextNode(formatted.slice(start, match.index)),
        text(
          "span",
          match[0],
          match[1] ? "json-key" : match[2] ? "json-string" : "json-number",
        ),
      );
      start = match.index + match[0].length;
    }
    pre.append(document.createTextNode(formatted.slice(start)));
    return pre;
  }
  function tree(value, depth = 0) {
    if (!value || typeof value !== "object" || depth > 6) return json(value);
    const root = text("div", "", "tree");
    for (const [key, item] of Object.entries(value)) {
      const d = document.createElement("details");
      d.open = depth < 1;
      d.append(text("summary", key), tree(item, depth + 1));
      root.append(d);
    }
    return root;
  }
  function renderDetail() {
    if (!selected) return;
    const e = selected.entry;
    panel.replaceChildren();
    panel.setAttribute("aria-labelledby", `tab-${activeTab}`);
    for (const tab of $("#tabs").children) {
      tab.setAttribute("aria-selected", String(tab.dataset.tab === activeTab));
      tab.tabIndex = tab.dataset.tab === activeTab ? 0 : -1;
    }
    if (activeTab === "raw") panel.append(text("pre", selected.raw));
    else if (activeTab === "json") panel.append(json(e));
    else if (activeTab === "context") panel.append(tree(e.context || {}));
    else if (activeTab === "exception") {
      if (!e.exception)
        panel.append(text("p", "No exception attached to this event."));
      else {
        panel.append(
          text("h3", e.exception.type),
          text("p", e.exception.message),
          text("pre", `Origin: ${e.exception.file}:${e.exception.line}`),
        );
        const frames = document.createElement("details");
        frames.open = true;
        frames.append(
          text("summary", "Sanitized stack trace (arguments excluded)"),
        );
        for (const [i, frame] of (Array.isArray(e.exception.trace)
          ? e.exception.trace
          : []
        ).entries())
          frames.append(
            text(
              "div",
              `#${i} ${frame.file || "[internal]"}:${frame.line || "?"} ${frame.class || ""}${frame.method ? "::" + frame.method : ""}`,
              "trace-frame",
            ),
          );
        panel.append(frames);
        if (e.exception.application_frame)
          panel.append(
            text("h3", "First inferred application frame"),
            json(e.exception.application_frame),
          );
        if (e.exception.previous) {
          const p = document.createElement("details");
          p.append(
            text("summary", "Previous exception chain"),
            json(e.exception.previous),
          );
          panel.append(p);
        }
      }
    } else {
      panel.append(
        badge(str(e.level)),
        text("h3", e.event),
        text("p", e.message),
      );
      const grid = text("div", "", "overview-grid");
      for (const [key, value] of Object.entries({
        timestamp: e.timestamp,
        service: e.service,
        environment: e.environment,
        request: e.request_id,
        route: e.context?.route,
        status: e.context?.status_code,
        duration_ms: e.context?.duration_ms,
        schema: e.schema_version,
      })) {
        const item = text("div", "", "kv");
        item.append(text("strong", key), text("span", value, "mono"));
        grid.append(item);
      }
      panel.append(grid);
      if (e.diagnostic)
        panel.append(text("h3", "Diagnostic summary"), tree(e.diagnostic));
      panel.append(
        text(
          "p",
          "Use “Show this request” to follow events with the same correlation ID. This is request grouping, not a distributed trace.",
        ),
      );
    }
  }
  function openDetail(item) {
    selected = item;
    activeTab = "overview";
    $("#detail-title").textContent = item.entry.event || "Event details";
    $("#copy-feedback").textContent = "";
    $("#copy-id").disabled = $("#group-id").disabled = !str(
      item.entry.request_id,
    );
    renderDetail();
    detail.showModal();
    clearTimeout(timer);
  }
  async function copy(value) {
    try {
      await navigator.clipboard.writeText(value);
      $("#copy-feedback").textContent = "Copied sanitized content.";
    } catch {
      $("#copy-feedback").textContent =
        "Clipboard unavailable. Select and copy the displayed text.";
    }
  }
  $("#tabs").addEventListener("click", (event) => {
    if (event.target.dataset.tab) {
      activeTab = event.target.dataset.tab;
      renderDetail();
    }
  });
  $("#tabs").addEventListener("keydown", (event) => {
    if (!["ArrowLeft", "ArrowRight", "Home", "End"].includes(event.key)) return;
    event.preventDefault();
    const tabs = [...$("#tabs").children];
    let i = tabs.indexOf(document.activeElement);
    i =
      event.key === "Home"
        ? 0
        : event.key === "End"
          ? tabs.length - 1
          : (i + (event.key === "ArrowRight" ? 1 : -1) + tabs.length) %
            tabs.length;
    activeTab = tabs[i].dataset.tab;
    renderDetail();
    tabs[i].focus();
  });
  $("#close-detail").addEventListener("click", () => detail.close());
  detail.addEventListener("close", schedule);
  $("#copy-id").addEventListener("click", () =>
    copy(str(selected?.entry.request_id)),
  );
  $("#copy-json").addEventListener("click", () =>
    copy(JSON.stringify(selected?.entry, null, 2)),
  );
  $("#group-id").addEventListener("click", () =>
    group(selected.entry.request_id),
  );
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    load(true);
  });
  $("#refresh").addEventListener("click", () => load(true));
  $("#clear-filters").addEventListener("click", () => {
    form.reset();
    load(true);
  });
  $("#next").addEventListener("click", () => {
    if (nextCursor) {
      cursors[++page] = nextCursor;
      load();
    }
  });
  $("#previous").addEventListener("click", () => {
    if (page > 0) {
      page--;
      load();
    }
  });
  $("#timezone").addEventListener("change", renderRows);
  $("#poll").addEventListener("change", schedule);
  document.addEventListener("visibilitychange", schedule);
  function theme(value) {
    document.body.dataset.theme = value;
    $("#theme").setAttribute("aria-pressed", String(value === "dark"));
  }
  try {
    theme(
      localStorage.getItem("log-explorer-theme") ||
        (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light"),
    );
  } catch {
    theme("light");
  }
  $("#theme").addEventListener("click", () => {
    const value = document.body.dataset.theme === "dark" ? "light" : "dark";
    theme(value);
    try {
      localStorage.setItem("log-explorer-theme", value);
    } catch {}
  });
  const restored = new URLSearchParams(location.search);
  if (location.search) history.replaceState(null, "", location.pathname);
  for (const [key, value] of restored)
    if (form.elements.namedItem(key) && key !== "cursor")
      form.elements.namedItem(key).value = ["from", "until"].includes(key)
        ? value.replace(/\.\d{3}Z$/, "")
        : value;
  load();
})();
