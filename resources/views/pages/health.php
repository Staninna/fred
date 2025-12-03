<?php
/** @var string $environment */
/** @var string $baseUrl */
/** @var string|null $sessionId */
?>

<article class="card">
    <p class="eyebrow">Health check</p>
    <h1>System status</h1>
    <p class="lede">Router, view layer, configuration and session handler are active.</p>
    <dl class="meta">
        <div class="meta__row">
            <dt>Environment</dt>
            <dd><?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="meta__row">
            <dt>Base URL</dt>
            <dd><?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
        <div class="meta__row">
            <dt>Session</dt>
            <dd><?= htmlspecialchars($sessionId ?: 'not started', ENT_QUOTES, 'UTF-8') ?></dd>
        </div>
    </dl>
</article>
