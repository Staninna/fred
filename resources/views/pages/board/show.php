<?php
/** @var Community $community */
/** @var Board $board */
/** @var Category $category */
/** @var array<int, \Fred\Domain\Forum\Thread> $threads */
/** @var CurrentUser|null $currentUser */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;

?>

<article class="card card--hero">
    <div>
        <p class="eyebrow">Board</p>
        <h1><?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="lede"><?= htmlspecialchars($board->description, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="tags">
            <span class="tag">Community: <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="tag">Category: <?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="tag">Slug: <?= htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8') ?></span>
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
            <div class="status__value"><?= count($threads) ?></div>
        </div>
        <div class="status__item">
            <div class="status__label">Board ID</div>
            <div class="status__value"><?= $board->id ?></div>
        </div>
    </div>
</article>

<article class="card">
    <header class="card__header">
        <div>
            <p class="eyebrow">Threads</p>
            <h2><?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?></h2>
        </div>
        <?php if ($board->isLocked): ?>
            <span class="tag">Locked</span>
        <?php elseif (($currentUser ?? null) !== null && $currentUser->isAuthenticated()): ?>
            <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/b/<?= htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8') ?>/thread/new">New thread</a>
        <?php else: ?>
            <a class="button button--ghost" href="/login">Sign in to post</a>
        <?php endif; ?>
    </header>

    <?php if ($threads === []): ?>
        <p class="muted">No threads yet.</p>
    <?php else: ?>
        <ul class="list">
            <?php foreach ($threads as $thread): ?>
                <li>
                    <div class="nav__title">
                        <?php if ($thread->isSticky): ?><span class="tag">Sticky</span> <?php endif; ?>
                        <?php if ($thread->isAnnouncement): ?><span class="tag">Announcement</span> <?php endif; ?>
                        <a class="nav__link" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/t/<?= $thread->id ?>">
                            <?= htmlspecialchars($thread->title, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </div>
                    <div class="nav__subtitle">
                        Started by <?= htmlspecialchars($thread->authorName, ENT_QUOTES, 'UTF-8') ?> ·
                        <?= date('Y-m-d H:i', $thread->createdAt) ?>
                        <?= $thread->isLocked ? ' · Locked' : '' ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</article>
