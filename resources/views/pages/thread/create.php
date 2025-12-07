<?php
/** @var Community $community */
/** @var Board $board */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">New thread</th>
    </tr>
    <tr>
        <td class="table-heading">Community</td>
        <td><?= $e($community->name) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Board</td>
        <td><?= $e($board->name) ?> (ID: <?= $board->id ?>)</td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Create thread</th>
    </tr>
    <tr>
        <td>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/b/<?= $e($board->slug) ?>/thread" enctype="multipart/form-data" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="title">Title</label></td>
                        <td><input id="title" name="title" type="text" value="<?= $e($old['title'] ?? '') ?>" required></td>
                    </tr>
                </table>
                <?= $renderPartial('partials/thread/message_form.php', [
                    'action' => '/c/' . $community->slug . '/b/' . $board->slug . '/thread',
                    'submitLabel' => 'Post thread',
                    'textareaId' => 'body',
                    'textareaName' => 'body',
                    'textareaLabel' => 'Body',
                    'bodyValue' => $old['body'] ?? '',
                    'includeAttachment' => true,
                    'renderPartial' => $renderPartial,
                ]) ?>
                <a class="button" href="/c/<?= $e($community->slug) ?>/b/<?= $e($board->slug) ?>">Cancel</a>
            </form>
        </td>
    </tr>
</table>
