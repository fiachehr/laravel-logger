<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Log Explorer</title>
    <link rel="stylesheet" href="{{ route('log-explorer.asset', ['asset' => 'explorer-v1.css']) }}">
    <script src="{{ route('log-explorer.asset', ['asset' => 'explorer-v1.js']) }}" defer></script>
</head>
<body data-endpoint="{{ route('log-explorer.entries') }}" @if($credentialsMode ?? false) data-login="{{ route('log-explorer.login') }}" @endif>
<a class="skip" href="#entries">Skip to log entries</a>
<header class="topbar">
    <a class="brand" href="{{ route('log-explorer.index') }}"><span class="brand-mark" aria-hidden="true">≋</span> LOG<span class="brand-light">EXPLORER</span><span class="version" aria-hidden="true">01</span></a>
    <div class="top-actions"><span class="private-label"><span aria-hidden="true">◆</span> PRIVATE WORKSPACE</span><button id="theme" class="quiet" aria-label="Switch color theme">◐ Theme</button>@if($credentialsMode ?? false)<a id="sign-in-again" class="quiet" href="{{ route('log-explorer.login') }}" hidden>Sign in again</a><form id="dashboard-sign-out" action="{{ route('log-explorer.logout') }}" method="POST">@csrf<button type="submit" class="quiet">Sign out</button></form>@endif</div>
</header>
<main>
    <section class="intro" aria-labelledby="page-title"><div><p class="eyebrow">OBSERVABILITY / EVENT STREAM</p><h1 id="page-title">A clearer view.<br><span>One event at a time.</span></h1><p class="subtitle">Structured logs, connected requests, and the details that matter.</p></div><div class="stream-note"><span class="pulse" aria-hidden="true"></span> BOUNDED FILE SEARCH<br><small>Read-only · JSONL · No telemetry</small></div></section>
    <form id="filters" class="filter-panel" role="search" aria-label="Filter logs">
        <div class="filter-primary"><label class="search-label"><span class="sr-only">Search logs</span><span aria-hidden="true">⌕</span><input name="q" id="search" type="search" placeholder="Search the event stream…" maxlength="120" autocomplete="off"></label>
            <label><span class="sr-only">Severity</span><select name="level" aria-label="Severity"><option value="">All severities</option><option>DEBUG</option><option>INFO</option><option>NOTICE</option><option>WARNING</option><option>ERROR</option><option>CRITICAL</option><option>ALERT</option><option>EMERGENCY</option></select></label>
            <label><span class="sr-only">Order</span><select name="order" aria-label="Order"><option value="newest">Newest first</option><option value="oldest">Oldest first</option></select></label>
            <button class="primary" type="submit">Search <span aria-hidden="true">↗</span></button><button type="button" id="clear-filters" class="quiet">Reset</button></div>
        <details class="advanced"><summary>More filters <span>Service, event, request, route & time</span></summary><div class="filter-grid">
            <label>Service<input name="service" maxlength="120" placeholder="authentication"></label><label>Event<input name="event" maxlength="120" placeholder="application.log"></label>
            <label>Request ID<input name="request_id" maxlength="64" placeholder="Exact correlation ID"></label><label>Exception class<input name="exception" maxlength="120" placeholder="RuntimeException"></label>
            <label>Route name<input name="route" maxlength="120" placeholder="orders.show"></label><label>HTTP status<input name="status" type="number" min="100" max="599" placeholder="500"></label>
            <label>From (UTC)<input name="from" type="datetime-local" step="1"></label><label>Until (UTC)<input name="until" type="datetime-local" step="1"></label>
        </div></details>
    </form>
    <section class="event-panel" aria-labelledby="stream-title">
        <div class="panel-bar"><div><h2 id="stream-title">Event stream</h2><span id="page-label" class="counter">Page 1</span></div><div class="stream-controls"><label><span class="sr-only">Timezone</span><select id="timezone" aria-label="Timezone"><option value="utc">UTC</option><option value="local">Local time</option></select></label><label><span class="sr-only">Auto refresh</span><select id="poll" aria-label="Auto refresh"><option value="0">Auto refresh off</option><option value="5">Every 5 seconds</option><option value="15">Every 15 seconds</option></select></label><button id="refresh" class="quiet">↻ Refresh</button></div></div>
        <p id="feedback" class="feedback" role="status" aria-live="polite">Loading event stream…</p>
        <div class="table-scroll" tabindex="0" aria-label="Log table, scroll horizontally for more columns"><table id="entries"><caption class="sr-only">Sanitized log entries. Open an event to inspect details.</caption><thead><tr><th scope="col">TIME</th><th scope="col">LEVEL</th><th scope="col">SERVICE</th><th scope="col" class="message-heading">EVENT / MESSAGE</th><th scope="col">HTTP</th><th scope="col">REQUEST</th><th scope="col">EXCEPTION / ORIGIN</th></tr></thead><tbody id="rows"></tbody></table></div>
        <div id="empty" class="empty" hidden><span aria-hidden="true">⌕</span><h3>No matching events</h3><p>Try broader filters or continue into the next search window.</p></div>
        <footer class="pagination"><span id="scan-meta">Waiting for storage</span><nav aria-label="Pagination"><button id="previous" class="quiet" disabled>← Previous</button><button id="next" class="quiet" disabled>Next window →</button></nav></footer>
    </section>
    <p class="footnote">Only events routed to the structured channel appear here. Correlation connects events sharing a request ID; it is not distributed tracing.</p>
</main>
<dialog id="detail" aria-labelledby="detail-title"><div class="drawer-header"><div><p class="eyebrow">EVENT INSPECTOR</p><h2 id="detail-title">Event details</h2></div><button id="close-detail" class="quiet" aria-label="Close event details">✕</button></div><div class="detail-toolbar"><button id="copy-id" class="quiet">Copy request ID</button><button id="group-id" class="quiet">Show this request ↗</button><button id="copy-json" class="quiet">Copy sanitized JSON</button></div><div role="tablist" aria-label="Event detail views" id="tabs"><button role="tab" id="tab-overview" aria-controls="detail-content" aria-selected="true" data-tab="overview">Overview</button><button role="tab" id="tab-exception" aria-controls="detail-content" aria-selected="false" tabindex="-1" data-tab="exception">Exception</button><button role="tab" id="tab-context" aria-controls="detail-content" aria-selected="false" tabindex="-1" data-tab="context">Context</button><button role="tab" id="tab-json" aria-controls="detail-content" aria-selected="false" tabindex="-1" data-tab="json">JSON</button><button role="tab" id="tab-raw" aria-controls="detail-content" aria-selected="false" tabindex="-1" data-tab="raw">Raw</button></div><div id="detail-content" role="tabpanel" aria-labelledby="tab-overview" tabindex="0"></div><p id="copy-feedback" class="copy-feedback" role="status" aria-live="polite"></p></dialog>
</body>
</html>
