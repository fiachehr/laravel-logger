<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Sign in · Log Explorer</title>
    <link rel="stylesheet" href="{{ route('log-explorer.login.style') }}">
</head>
<body>
<main class="login-shell">
    <section class="login-story" aria-labelledby="product-title">
        <a class="brand" href="{{ route('log-explorer.index') }}"><span aria-hidden="true">≋</span> LOG EXPLORER</a>
        <p class="eyebrow">PRIVATE OBSERVABILITY</p>
        <h1 id="product-title">The details<br>behind the event.</h1>
        <p class="description">A focused workspace for your application's structured logs, exceptions, and connected requests.</p>
        <div class="sample" aria-hidden="true"><p><span class="sample-tag">INFO</span><span>application.ready</span><span class="sample-dot">●</span></p><p><span class="sample-tag warning">WARN</span><span>checkout.retry</span><span class="sample-dot">●</span></p><p><span class="sample-tag error">ERROR</span><span>exception.reported</span><span class="sample-dot">●</span></p></div>
        <p class="privacy-note">Read-only explorer. No external telemetry.</p>
    </section>
    <section class="login-card" aria-labelledby="sign-in-title">
        <p class="eyebrow">OPERATOR ACCESS</p>
        <h2 id="sign-in-title">Sign in to your logs</h2>
        <p class="form-description">Use the separate dashboard credentials configured by your application operator.</p>
        @if(session('log_explorer_error'))
            <p class="login-error" id="login-error" role="alert">Invalid username or password.</p>
        @endif
        <form action="{{ route('log-explorer.login.submit') }}" method="POST">
            @csrf
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" maxlength="120" required @if(session('log_explorer_error')) aria-describedby="login-error" @endif>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" maxlength="1024" required>
            <button type="submit">Sign in <span aria-hidden="true">↗</span></button>
        </form>
        <p class="access-note">This login is separate from your application's user accounts. Contact the operator if you need access.</p>
    </section>
</main>
</body>
</html>
