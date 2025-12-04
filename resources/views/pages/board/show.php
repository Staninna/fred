<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Community\Board $board */
/** @var \Fred\Domain\Community\Category $category */
?>

<article class="card card--hero">
    <div>
        <p class="eyebrow">Board</p>
        <h1><?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="lede"><?= htmlspecialchars($board->description, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="tags">
            <span class="tag">Community: <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="tag">Category: <?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></span>
            <?php if ($board->isLocked): ?>
                <span class="tag">Locked</span>
            <?php endif; ?>
        </div>
        <?php if (($currentUser ?? null) !== null && $currentUser->isAuthenticated()): ?>
            <div class="account__actions" style="margin-top: 0.75rem;">
                <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/structure">Admin this community</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="status">
        <div class="status__item">
            <div class="status__label">Threads</div>
            <div class="status__value">Coming soon</div>
        </div>
        <div class="status__item">
            <div class="status__label">Board ID</div>
            <div class="status__value"><?= $board->id ?></div>
        </div>
    </div>
</article>

<article class="card card--compact">
    <h2>Threads</h2>
    <p class="muted">Thread listing will appear here in the next stage.</p>
</article>
