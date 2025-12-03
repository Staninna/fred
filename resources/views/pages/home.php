<?php
/** @var string $environment */
/** @var string $baseUrl */
?>

<section class="card card--hero">
    <div>
        <p class="eyebrow">Welcome</p>
        <h1>Fred forum engine</h1>
        <p class="lede">A compact, nostalgic-first forum stack. Routing, config, migrations and sessions are alive; next up is wiring real data.</p>
        <div class="badges">
            <span class="badge">Environment: <?= htmlspecialchars($environment, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="badge">Base URL: <?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
    <div class="status">
        <div class="status__item">
            <div class="status__label">Routing</div>
            <div class="status__value">Online</div>
        </div>
        <div class="status__item">
            <div class="status__label">Sessions</div>
            <div class="status__value">SQLite handler</div>
        </div>
        <div class="status__item">
            <div class="status__label">Migrations</div>
            <div class="status__value">Ready</div>
        </div>
    </div>
</section>

<section class="grid">
    <article class="card">
        <h2>Layout shell</h2>
        <p>Left navigation anchors communities and boards, while the content column stays roomy for threads, posts and admin tools.</p>
        <ul class="list">
            <li>Responsive two-column layout with soft gradient background.</li>
            <li>Navigation partial for community and board lists.</li>
            <li>Default typography and badges for quick status.</li>
        </ul>
    </article>
    <article class="card">
        <h2>View layer</h2>
        <p>Views render through a shared layout with partial helpers. Pages choose templates and pass data without global helpers.</p>
        <ul class="list">
            <li>Layout at <code>resources/views/layout/default.php</code>.</li>
            <li>Partials via <code>$renderPartial()</code> in views.</li>
            <li>Errors get dedicated views for 404 and 500.</li>
        </ul>
    </article>
    <article class="card">
        <h2>Next up</h2>
        <p>Stage 3 brings authentication: users, roles, and permissions. The layout already has room for account controls.</p>
        <div class="tags">
            <span class="tag">Auth</span>
            <span class="tag">BBCode</span>
            <span class="tag">Moderation</span>
        </div>
    </article>
</section>
