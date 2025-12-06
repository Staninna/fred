<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Community\Board $board */
/** @var \Fred\Domain\Community\Category $category */
/** @var \Fred\Domain\Forum\Thread $thread */
/** @var array<int, \Fred\Domain\Forum\Post> $posts */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2"><?= htmlspecialchars($thread->title, ENT_QUOTES, 'UTF-8') ?></th>
    </tr>
    <tr>
        <td class="table-heading">Location</td>
        <td>
            Community:
            <a href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?>
            </a>
            · Board:
            <a href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/b/<?= htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Started</td>
        <td><?= date('Y-m-d H:i', $thread->createdAt) ?> · Posts: <?= count($posts) ?> <?= $thread->isLocked ? '· Locked' : '' ?></td>
    </tr>
    <?php if (($currentUser ?? null) !== null && $currentUser->isAuthenticated()): ?>
        <tr>
            <td class="table-heading">Admin</td>
            <td><a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/admin/structure">Admin this community</a></td>
        </tr>
    <?php endif; ?>
</table>

<div id="post-list">
    <?= $renderPartial('partials/thread/posts.php', ['posts' => $posts]) ?>
</div>

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
                <form method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/t/<?= $thread->id ?>/reply" novalidate>
                    <table class="form-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="120"><label for="reply_body">Message</label></td>
                            <td>
                                <?= $renderPartial('partials/bbcode_toolbar.php', ['targetId' => 'reply_body']) ?>
                                <textarea id="reply_body" name="body" rows="4" required></textarea>
                            </td>
                        </tr>
                    </table>
                    <button class="button" type="submit">Post reply</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
</table>
