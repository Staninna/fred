<?php /** @var callable(string, array): string $renderPartial */ ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Fred', ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/css/base.css">
</head>
<body>
<div class="page">
    <header class="masthead">
        <div class="masthead__brand">
            <div class="masthead__title">Fred</div>
            <div class="masthead__meta">Nostalgic forum engine · <?= htmlspecialchars($environment ?? 'local', ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="masthead__account">
            <?php if (isset($currentUser) && $currentUser->isAuthenticated()): ?>
                <div class="account">
                    <div>
                        <div class="account__label">Signed in</div>
                        <div class="account__name"><?= htmlspecialchars($currentUser->displayName, ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="account__role"><?= htmlspecialchars($currentUser->roleName, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <form method="post" action="/logout">
                        <button class="button button--ghost" type="submit">Sign out</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="account account--guest">
                    <div>
                        <div class="account__label">Guest</div>
                        <div class="account__name">Not signed in</div>
                    </div>
                    <div class="account__actions">
                        <a class="button button--ghost" href="/login">Sign in</a>
                        <a class="button" href="/register">Register</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </header>
    <div class="layout">
        <aside class="sidebar">
            <?= $renderPartial('partials/nav.php', [
                'navSections' => $navSections ?? null,
                'activePath' => $activePath ?? null,
            ]) ?>
        </aside>
        <main class="content" aria-live="polite">
            <?= $content ?? '' ?>
        </main>
    </div>
</div>
</body>
</html>
