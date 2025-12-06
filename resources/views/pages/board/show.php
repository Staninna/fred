<?php
/** @var Community $community */
/** @var Board $board */
/** @var Category $category */
/** @var array<int, \Fred\Domain\Forum\Thread> $threads */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, int): string $e */
/** @var bool $canModerate */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Board: <?= $e($board->name) ?></th>
    </tr>
    <tr>
        <td class="table-heading">Description</td>
        <td><?= $e($board->description) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Location</td>
        <td>
            Community: <?= $e($community->name) ?> ·
            Category: <?= $e($category->name) ?> ·
            Slug: <?= $e($board->slug) ?>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Status</td>
        <td><?= $board->isLocked ? 'Locked' : 'Open' ?> · Threads: <?= count($threads) ?> · Board ID: <?= $board->id ?></td>
    </tr>
    <?php if (!empty($canModerate ?? false)): ?>
        <tr>
            <td class="table-heading">Admin</td>
            <td><a class="button" href="/c/<?= $e($community->slug) ?>/admin/structure">Admin this community</a></td>
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
                    <a href="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>">
                        <?= $e($thread->title) ?>
                    </a>
                </td>
                <td>
                    Started by <?= $e($thread->authorName) ?> ·
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
                <a class="button" href="/c/<?= $e($community->slug) ?>/b/<?= $e($board->slug) ?>/thread/new">New thread</a>
            <?php else: ?>
                <a class="button" href="/login">Sign in to post</a>
            <?php endif; ?>
        </td>
    </tr>
</table>
