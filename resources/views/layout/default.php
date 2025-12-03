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
        <div class="masthead__title">Fred</div>
        <div class="masthead__meta">Nostalgic forum engine · <?= htmlspecialchars($environment ?? 'local', ENT_QUOTES, 'UTF-8') ?></div>
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
