<?php
/** @var Community $community */
/** @var Board $board */
/** @var Category $category */
/** @var \Fred\Domain\Forum\Thread $thread */
/** @var array<int, Post> $posts */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, array): string $renderPartial */
/** @var bool $canModerate */
/** @var bool $canLockThread */
/** @var bool $canStickyThread */
/** @var bool $canMoveThread */
/** @var bool $canEditAnyPost */
/** @var bool $canDeleteAnyPost */
/** @var bool $canBanUsers */
/** @var array<int, Board> $allBoards */
/** @var bool $canModerate */
/** @var array<int, \Fred\Domain\Auth\Profile> $profilesByUserId */
/** @var callable(string, int): string $e */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;

?>

<?php if (!empty($reportNotice ?? null)): ?>
    <div class="notice"><?= $e($reportNotice) ?></div>
<?php endif; ?>
<?php if (!empty($reportError ?? null)): ?>
    <div class="notice"><?= $e($reportError) ?></div>
<?php endif; ?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2"><?= $e($thread->title) ?></th>
    </tr>
    <tr>
        <td class="table-heading">Location</td>
        <td>
            Community:
            <a href="/c/<?= $e($community->slug) ?>">
                <?= $e($community->name) ?>
            </a>
            · Board:
            <a href="/c/<?= $e($community->slug) ?>/b/<?= $e($board->slug) ?>">
                <?= $e($board->name) ?>
            </a>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Started</td>
        <td><?= date('Y-m-d H:i', $thread->createdAt) ?> · Posts: <?= $totalPosts ?? count($posts) ?> <?= $thread->isLocked ? '· Locked' : '' ?></td>
    </tr>
    <?php if (!empty($canModerate ?? false)): ?>
        <tr>
            <td class="table-heading">Admin</td>
            <td>
                <a class="button" href="/c/<?= $e($community->slug) ?>/admin/structure">Admin this community</a>
                <?php if (!empty($canBanUsers ?? false)): ?>
                    <a class="button" href="/c/<?= $e($community->slug) ?>/admin/bans">Manage bans</a>
                <?php endif; ?>
                <?php if (!empty($canMoveThread ?? false)): ?>
                    <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/move">
                        <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                        <label for="target_board" class="small">Move to:</label>
                        <?php
                        $boardOptions = array_map(
                            static fn ($boardOption) => ['value' => $boardOption->slug, 'label' => $boardOption->name],
                            $allBoards
                        );
                        echo $renderPartial('partials/select.php', [
                            'name' => 'target_board',
                            'id' => 'target_board',
                            'options' => $boardOptions,
                            'selected' => (string) $board->slug,
                            'class' => 'inline-select',
                        ]);
                        ?>
                        <button class="button" type="submit">Move</button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($canLockThread ?? false)): ?>
                    <?php if ($thread->isLocked): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/unlock">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Unlock</button>
                        </form>
                    <?php else: ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/lock">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Lock</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($canStickyThread ?? false)): ?>
                    <?php if ($thread->isSticky): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/unsticky">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Unsticky</button>
                        </form>
                    <?php else: ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/sticky">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Sticky</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($canModerate ?? false)): ?>
                    <?php if ($thread->isAnnouncement): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/unannounce">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Unannounce</button>
                        </form>
                    <?php else: ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/announce">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Mark as announcement</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endif; ?>
</table>

<div id="post-list">
    <?= $renderPartial('partials/thread/posts.php', [
        'posts' => $posts,
        'canEditAnyPost' => $canEditAnyPost ?? false,
        'canDeleteAnyPost' => $canDeleteAnyPost ?? false,
        'communitySlug' => $community->slug,
        'profilesByUserId' => $profilesByUserId ?? [],
        'attachmentsByPost' => $attachmentsByPost ?? [],
        'canReport' => !($currentUser?->isGuest() ?? true),
        'currentUserId' => $currentUser?->id,
        'page' => $pagination['page'] ?? 1,
    ]) ?>
</div>

<?php if (!empty($pagination) && ($pagination['totalPages'] ?? 1) > 1): ?>
    <?php
    $page = (int) ($pagination['page'] ?? 1);
    $totalPages = (int) ($pagination['totalPages'] ?? 1);
    $base = '/c/' . $e($community->slug) . '/t/' . $thread->id;
    ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a class="button" href="<?= $base ?>?page=<?= $page - 1 ?>">Prev</a>
        <?php else: ?>
            <span class="muted">Prev</span>
        <?php endif; ?>
        <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a class="button" href="<?= $base ?>?page=<?= $page + 1 ?>">Next</a>
        <?php else: ?>
            <span class="muted">Next</span>
        <?php endif; ?>
    </div>
<?php endif; ?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Reply</th>
    </tr>
    <tr>
        <td>
            <?php if (($currentUser ?? null) === null || $currentUser->isGuest()): ?>
                <div class="muted">Sign in to reply.</div>
                <a class="button" href="/login">Sign in</a>
            <?php elseif ($thread->isLocked || $board->isLocked): ?>
                <div class="muted">This thread is locked.</div>
            <?php else: ?>
                <?= $renderPartial('partials/thread/message_form.php', [
                    'action' => '/c/' . $community->slug . '/t/' . $thread->id . '/reply',
                    'submitLabel' => 'Post reply',
                    'textareaId' => 'reply_body',
                    'textareaName' => 'body',
                    'textareaLabel' => 'Message',
                    'bodyValue' => '',
                    'includeAttachment' => true,
                    'page' => $pagination['page'] ?? 1,
                    'renderPartial' => $renderPartial,
                ]) ?>
            <?php endif; ?>
        </td>
    </tr>
</table>
