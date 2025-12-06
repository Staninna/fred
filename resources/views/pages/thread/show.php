<?php
/** @var Community $community */
/** @var Board $board */
/** @var Category $category */
/** @var \Fred\Domain\Forum\Thread $thread */
/** @var array<int, Post> $posts */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, array): string $renderPartial */
/** @var bool $canModerate */
/** @var array<int, \Fred\Domain\Community\Board> $allBoards */
/** @var callable(string, int): string $e */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;

?>

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
        <td><?= date('Y-m-d H:i', $thread->createdAt) ?> · Posts: <?= count($posts) ?> <?= $thread->isLocked ? '· Locked' : '' ?></td>
    </tr>
    <?php if (($currentUser ?? null) !== null && $currentUser->isAuthenticated()): ?>
        <tr>
            <td class="table-heading">Admin</td>
            <td>
                <a class="button" href="/c/<?= $e($community->slug) ?>/admin/structure">Admin this community</a>
                <?php if ($canModerate): ?>
                    <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/move">
                        <label for="target_board" class="small">Move to:</label>
                        <select name="target_board" id="target_board">
                            <?php foreach ($allBoards as $boardOption): ?>
                                <option value="<?= $e($boardOption->slug) ?>"<?= $boardOption->id === $board->id ? ' selected' : '' ?>>
                                    <?= $e($boardOption->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="button" type="submit">Move</button>
                    </form>
                    <?php if ($thread->isLocked): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/unlock">
                            <button class="button" type="submit">Unlock</button>
                        </form>
                    <?php else: ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/lock">
                            <button class="button" type="submit">Lock</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($thread->isSticky): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/unsticky">
                            <button class="button" type="submit">Unsticky</button>
                        </form>
                    <?php else: ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/sticky">
                            <button class="button" type="submit">Sticky</button>
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
        'canModerate' => $canModerate ?? false,
        'communitySlug' => $community->slug,
    ]) ?>
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
                <form method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/reply" novalidate>
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
