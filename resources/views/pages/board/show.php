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

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Board: <?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?></th>
    </tr>
    <tr>
        <td class="table-heading">Description</td>
        <td><?= htmlspecialchars($board->description, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td class="table-heading">Location</td>
        <td>
            Community: <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?> ·
            Category: <?= htmlspecialchars($category->name, ENT_QUOTES, 'UTF-8') ?> ·
            Slug: <?= htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Status</td>
        <td><?= $board->isLocked ? 'Locked' : 'Open' ?> · Threads: <?= count($threads) ?> · Board ID: <?= $board->id ?></td>
    </tr>
    <?php if (($currentUser ?? null) !== null && $currentUser->isAuthenticated()): ?>
        <tr>
            <td class="table-heading">Admin</td>
            <td><a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/structure">Admin this community</a></td>
        </tr>
    <?php endif; ?>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Threads</th>
        <th>Details</th>
    </tr>
    <?php if ($threads === []): ?>
        <tr>
            <td colspan="2" class="muted">No threads yet.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($threads as $thread): ?>
            <tr>
                <td width="320">
                    <?php if ($thread->isSticky): ?>[Sticky] <?php endif; ?>
                    <?php if ($thread->isAnnouncement): ?>[Announcement] <?php endif; ?>
                    <a href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/t/<?= $thread->id ?>">
                        <?= htmlspecialchars($thread->title, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </td>
                <td>
                    Started by <?= htmlspecialchars($thread->authorName, ENT_QUOTES, 'UTF-8') ?> ·
                    <?= date('Y-m-d H:i', $thread->createdAt) ?>
                    <?= $thread->isLocked ? ' · Locked' : '' ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    <tr>
        <td colspan="2">
            <?php if ($board->isLocked): ?>
                <span class="muted">Board locked.</span>
            <?php elseif (($currentUser ?? null) !== null && $currentUser->isAuthenticated()): ?>
                <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/b/<?= htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8') ?>/thread/new">New thread</a>
            <?php else: ?>
                <a class="button" href="/login">Sign in to post</a>
            <?php endif; ?>
        </td>
    </tr>
</table>
