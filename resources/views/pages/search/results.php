<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var array<int, \Fred\Domain\Community\Board> $boards */
/** @var array<int, array<string, mixed>> $threads */
/** @var array<int, array<string, mixed>> $posts */
/** @var array<int, string> $errors */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */
/** @var string $query */
/** @var \Fred\Domain\Community\Board|null $boardFilter */
/** @var \Fred\Domain\Auth\User|null $userFilter */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Search in <?= $e($community->name) ?></th>
    </tr>
    <tr>
        <td colspan="2">
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="get" action="/c/<?= $e($community->slug) ?>/search" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120"><label for="q">Query</label></td>
                        <td><input id="q" name="q" type="text" value="<?= $e($query ?? '') ?>" required></td>
                    </tr>
                    <tr>
                        <td><label for="board">Board</label></td>
                        <td>
                            <select id="board" name="board">
                                <option value="">All boards</option>
                                <?php foreach ($boards as $board): ?>
                                    <option value="<?= $e($board->slug) ?>"<?= ($boardFilter?->id === $board->id) ? ' selected' : '' ?>>
                                        <?= $e($board->name) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="user">User</label></td>
                        <td>
                            <input id="user" name="user" type="text" value="<?= $e($userFilter?->username ?? '') ?>" placeholder="Filter by username (optional)">
                        </td>
                    </tr>
                </table>
                <button class="button" type="submit">Search</button>
            </form>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Thread matches</th>
    </tr>
    <?php if (($query ?? '') === '' || $threads === []): ?>
        <tr><td class="muted">No thread matches.</td></tr>
    <?php else: ?>
        <?php foreach ($threads as $row): ?>
            <tr>
                <td>
                    <div><a href="/c/<?= $e($community->slug) ?>/t/<?= (int) $row['thread_id'] ?>"><?= $e($row['title']) ?></a></div>
                    <div class="small">
                        Board: <a href="/c/<?= $e($community->slug) ?>/b/<?= $e($row['board_slug']) ?>"><?= $e($row['board_name']) ?></a>
                        · Author: <?= $e($row['author_name']) ?>
                        · <?= date('Y-m-d H:i', (int) $row['created_at']) ?>
                    </div>
                    <?php if (!empty($row['snippet'] ?? '')): ?>
                        <div class="small muted"><?= $e($row['snippet']) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Post matches</th>
    </tr>
    <?php if (($query ?? '') === '' || $posts === []): ?>
        <tr><td class="muted">No post matches.</td></tr>
    <?php else: ?>
        <?php foreach ($posts as $row): ?>
            <tr>
                <td>
                    <div>
                        <a href="/c/<?= $e($community->slug) ?>/t/<?= (int) $row['thread_id'] ?>#post-<?= (int) $row['post_id'] ?>">
                            <?= $e($row['thread_title']) ?> · Post #<?= (int) $row['post_id'] ?>
                        </a>
                    </div>
                    <div class="small">
                        Board: <a href="/c/<?= $e($community->slug) ?>/b/<?= $e($row['board_slug']) ?>"><?= $e($row['board_name']) ?></a>
                        · Author: <?= $e($row['author_name']) ?>
                        · <?= date('Y-m-d H:i', (int) $row['created_at']) ?>
                    </div>
                    <?php if (!empty($row['snippet'] ?? '')): ?>
                        <div class="small muted"><?= $e($row['snippet']) ?></div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
