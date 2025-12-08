<?php
/** @var Community $community */
/** @var CurrentUser $currentUser */
/** @var MentionNotification[] $notifications */
/** @var int $unreadCount */
/** @var int $totalCount */
/** @var array{page:int,totalPages:int} $pagination */
/** @var int $postsPerPage */
/** @var callable(string, ?int=): string $e */
/** @var callable(string, array): string $renderPartial */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\MentionNotification;
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Mentions</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">
                Unread: <?= (int) $unreadCount ?> · Total: <?= (int) $totalCount ?>
            </div>
            <?php if ($unreadCount > 0): ?>
                <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/mentions/read">
                    <?= $renderPartial('partials/csrf.php') ?>
                    <input type="hidden" name="page" value="<?= (int) ($pagination['page'] ?? 1) ?>">
                    <button class="button" type="submit">Mark all read</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
</table>

<?php if ($notifications === []): ?>
    <div class="notice">No mention alerts yet.</div>
<?php else: ?>
    <table class="section-table" cellpadding="0" cellspacing="0">
        <tr>
            <th colspan="3">Inbox</th>
        </tr>
        <?php foreach ($notifications as $notification): ?>
            <?php
            $page = (int) ceil(($notification->postPosition ?? 1) / ($postsPerPage ?? 25));
            $threadUrl = '/c/' . $community->slug . '/t/' . $notification->threadId;
            if ($page > 1) {
                $threadUrl .= '?page=' . $page;
            }
            $threadUrl .= '#post-' . $notification->postId;
            $excerpt = trim($notification->postBodyRaw);
            if (\strlen($excerpt) > 200) {
                $excerpt = substr($excerpt, 0, 200) . '...';
            }
            ?>
            <tr>
                <td width="180">
                    <div><strong>From:</strong> <a href="/c/<?= $e($community->slug) ?>/u/<?= $e($notification->mentionedByUsername) ?>"><?= $e($notification->mentionedByName) ?></a></div>
                    <div class="small muted">When: <?= date('Y-m-d H:i', $notification->createdAt) ?></div>
                    <div class="small muted">Status: <?= $notification->readAt === null ? 'Unread' : 'Read ' . date('Y-m-d H:i', $notification->readAt) ?></div>
                </td>
                <td>
                    <div><a href="<?= $e($threadUrl) ?>"><?= $e($notification->threadTitle) ?></a></div>
                    <div class="small muted">Post #<?= (int) $notification->postId ?> · Page <?= $page ?></div>
                    <div class="small"><?= nl2br($e($excerpt)) ?></div>
                </td>
                <td width="140" class="small" style="text-align: right;">
                    <a class="button" href="<?= $e($threadUrl) ?>">Open</a>
                    <?php if ($notification->readAt === null): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/mentions/<?= $notification->id ?>/read" style="margin-left: 4px;">
                            <?= $renderPartial('partials/csrf.php') ?>
                            <input type="hidden" name="page" value="<?= (int) ($pagination['page'] ?? 1) ?>">
                            <button class="button" type="submit" title="Mark as read">✓</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    <?php if (($pagination['totalPages'] ?? 1) > 1): ?>
        <?= $renderPartial('partials/pagination.php', [
            'page' => (int) ($pagination['page'] ?? 1),
            'totalPages' => (int) ($pagination['totalPages'] ?? 1),
            'baseUrl' => '/c/' . $e($community->slug) . '/mentions',
            'isTable' => false,
        ]) ?>
    <?php endif; ?>
<?php endif; ?>
