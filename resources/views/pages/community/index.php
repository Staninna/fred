<?php
/** @var array<int, Community> $communities */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */

use Fred\Domain\Community\Community;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Communities</th>
    </tr>
    <tr>
        <td class="table-heading">Available</td>
        <td><?= count($communities) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Environment</td>
        <td><?= htmlspecialchars($environment ?? '', ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Name</th>
        <th>Description</th>
    </tr>
    <?php if ($communities === []): ?>
        <tr>
            <td colspan="2" class="muted">No communities yet. Create the first one to get started.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($communities as $community): ?>
            <tr>
                <td width="240">
                    <a href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                </td>
                <td><?= htmlspecialchars($community->description, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Create community</th>
    </tr>
    <tr>
        <td>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/communities" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="name">Name</label></td>
                        <td><input id="name" name="name" type="text" value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required></td>
                    </tr>
                    <tr>
                        <td><label for="slug">Slug</label></td>
                        <td><input id="slug" name="slug" type="text" value="<?= htmlspecialchars($old['slug'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="auto-generated from name"></td>
                    </tr>
                    <tr>
                        <td><label for="description">Description</label></td>
                        <td><input id="description" name="description" type="text" value="<?= htmlspecialchars($old['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"></td>
                    </tr>
                </table>
                <button class="button" type="submit">Create</button>
            </form>
        </td>
    </tr>
</table>
