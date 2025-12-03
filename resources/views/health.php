<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fred Health Check</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; color: #222; padding: 2rem; }
        .card { background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 1.5rem; max-width: 420px; box-shadow: 0 8px 16px rgba(0,0,0,0.04); }
        h1 { margin-top: 0; font-size: 1.4rem; }
        dl { margin: 0; }
        dt { font-weight: bold; }
        dd { margin: 0 0 0.75rem 0; }
    </style>
</head>
<body>
<div class="card">
    <h1>Fred health</h1>
    <p>This confirms the router, view layer, config and session handler are wired up.</p>
    <dl>
        <dt>Environment</dt>
        <dd><?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Base URL</dt>
        <dd><?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?></dd>
        <dt>Session</dt>
        <dd><?= htmlspecialchars($sessionId ?: 'not started', ENT_QUOTES, 'UTF-8') ?></dd>
    </dl>
</div>
</body>
</html>
