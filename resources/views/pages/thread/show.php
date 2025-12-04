<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Community\Board $board */
/** @var \Fred\Domain\Community\Category $category */
/** @var \Fred\Domain\Forum\Thread $thread */
/** @var array<int, \Fred\Domain\Forum\Post> $posts */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
?>

<article class="card card--hero">
    <div>
        <p class="eyebrow">Thread</p>
        <h1><?= htmlspecialchars($thread->title, ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="tags">
            <span class="tag">Community: <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="tag">
                <a href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/b/<?= htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8') ?>">
                    Board: <?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?>
                </a>
            </span>
            <?php if ($thread->isSticky): ?><span class="tag">Sticky</span><?php endif; ?>
            <?php if ($thread->isAnnouncement): ?><span class="tag">Announcement</span><?php endif; ?>
            <?php if ($thread->isLocked): ?><span class="tag">Locked</span><?php endif; ?>
        </div>
        <?php if (($currentUser ?? null) !== null && $currentUser->isAuthenticated()): ?>
            <div class="account__actions" style="margin-top: 0.75rem;">
                <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/structure">Admin this community</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="status">
        <div class="status__item">
            <div class="status__label">Posts</div>
            <div class="status__value"><?= count($posts) ?></div>
        </div>
        <div class="status__item">
            <div class="status__label">Started</div>
            <div class="status__value"><?= date('Y-m-d H:i', $thread->createdAt) ?></div>
        </div>
    </div>
</article>

<article class="card">
    <h2>Posts</h2>
    <?php if ($posts === []): ?>
        <p class="muted">No replies yet.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($posts as $post): ?>
                <li class="card card--compact" style="margin-bottom: 0.75rem;">
                    <div class="nav__title"><?= htmlspecialchars($post->authorName, ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="nav__subtitle"><?= date('Y-m-d H:i', $post->createdAt) ?></div>
                    <div class="nav__subtitle"><?= nl2br(htmlspecialchars($post->bodyRaw, ENT_QUOTES, 'UTF-8')) ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>

<article class="card card--compact">
    <h2>Reply</h2>
    <?php if (($currentUser ?? null) === null || $currentUser->isGuest()): ?>
        <p class="muted">Sign in to reply.</p>
        <a class="button button--ghost" href="/login">Sign in</a>
    <?php elseif ($thread->isLocked || $board->isLocked): ?>
        <p class="muted">This thread is locked.</p>
    <?php else: ?>
        <form class="form" method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/t/<?= $thread->id ?>/reply" novalidate>
            <div class="field">
                <label for="reply_body">Message</label>
                <textarea id="reply_body" name="body" rows="4" required></textarea>
            </div>
            <button class="button" type="submit">Post reply</button>
        </form>
    <?php endif; ?>
</article>
