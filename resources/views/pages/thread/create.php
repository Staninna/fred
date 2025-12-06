<?php
/** @var Community $community */
/** @var Board $board */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">New thread</th>
    </tr>
    <tr>
        <td class="table-heading">Community</td>
        <td><?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td class="table-heading">Board</td>
        <td><?= htmlspecialchars($board->name, ENT_QUOTES, 'UTF-8') ?> (ID: <?= $board->id ?>)</td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Create thread</th>
    </tr>
    <tr>
        <td>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/b/<?= htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8') ?>/thread" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="title">Title</label></td>
                        <td><input id="title" name="title" type="text" value="<?= htmlspecialchars($old['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></td>
                    </tr>
                    <tr>
                        <td><label for="body">Body</label></td>
                        <td>
                            <?= $renderPartial('partials/bbcode_toolbar.php', ['targetId' => 'body']) ?>
                            <textarea id="body" name="body" rows="6" required><?= htmlspecialchars($old['body'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </td>
                    </tr>
                </table>
                <button class="button" type="submit">Post thread</button>
                <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/b/<?= htmlspecialchars($board->slug, ENT_QUOTES, 'UTF-8') ?>">Cancel</a>
            </form>
        </td>
    </tr>
</table>
