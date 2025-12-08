<?php
/** @var Community $community */
/** @var Board $board */
/** @var Category $category */
/** @var array<int, \Fred\Domain\Forum\Thread> $threads */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, int): string $e */
/** @var callable(string, array): string $renderPartial */
/** @var bool $canModerate */
/** @var bool $canCreateThread */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;

?>

<?= $renderPartial('partials/info_table.php', [
    'title' => 'Board: ' . $board->name,
    'fields' => [
        'Description' => $e($board->description),
        'Location' => 'Community: ' . $e($community->name) . ' · Category: ' . $e($category->name) . ' · Slug: ' . $e($board->slug),
        'Status' => ($board->isLocked ? 'Locked' : 'Open') . ' · Threads: ' . ($totalThreads ?? count($threads)) . ' · Board ID: ' . $board->id,
    ],
]) ?>
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
    <?php if (!empty($pagination) && ($pagination['totalPages'] ?? 1) > 1): ?>
        <?= $renderPartial('partials/pagination.php', [
            'page' => (int) ($pagination['page'] ?? 1),
            'totalPages' => (int) ($pagination['totalPages'] ?? 1),
            'baseUrl' => '/c/' . $e($community->slug) . '/b/' . $e($board->slug),
            'isTable' => true,
        ]) ?>
    <?php endif; ?>
    <tr>
        <td colspan="2">
            <?php if ($board->isLocked): ?>
                <span class="muted">Board locked.</span>
            <?php elseif (($currentUser ?? null) !== null && $currentUser->isAuthenticated() && !empty($canCreateThread ?? false)): ?>
                <a class="button" href="/c/<?= $e($community->slug) ?>/b/<?= $e($board->slug) ?>/thread/new">New thread</a>
            <?php elseif (($currentUser ?? null) !== null && $currentUser->isAuthenticated()): ?>
                <span class="muted">You do not have permission to create threads.</span>
            <?php else: ?>
                <a class="button" href="/login">Sign in to post</a>
            <?php endif; ?>
        </td>
    </tr>
</table>
