<?php
/** @var Community $community */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var bool $saved */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */

use Fred\Domain\Community\Community;

$nameValue = $old['name'] ?? $community->name;
$descriptionValue = $old['description'] ?? $community->description;
$cssValue = $old['custom_css'] ?? ($community->customCss ?? '');

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Community settings</th>
    </tr>
    <tr>
        <td class="table-heading">Community</td>
        <td><?= $e($community->name) ?> (slug: <?= $e($community->slug) ?>)</td>
    </tr>
    <tr>
        <td class="table-heading">Status</td>
        <td><?= $saved ? '<span class="notice">Saved</span>' : 'Edit details below.' ?></td>
    </tr>
</table>

<?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>

<form method="post" action="/c/<?= $e($community->slug) ?>/admin/settings" novalidate>
    <?= $renderPartial('partials/csrf.php') ?>
    <table class="section-table" cellpadding="0" cellspacing="0">
        <tr>
            <th colspan="2">Basics</th>
        </tr>
        <tr>
            <td width="160"><label for="community_name">Name</label></td>
            <td><input id="community_name" name="name" type="text" value="<?= $e($nameValue) ?>" required></td>
        </tr>
        <tr>
            <td><label for="community_description">Description</label></td>
            <td><textarea id="community_description" name="description" rows="3" style="width: 100%;"><?= $e($descriptionValue) ?></textarea></td>
        </tr>
    </table>

    <table class="section-table" cellpadding="0" cellspacing="0">
        <tr>
            <th colspan="2">Theme (CSS)</th>
        </tr>
        <tr>
            <td colspan="2">
                <div class="small muted">Custom CSS is injected after the base theme. Max 8000 characters.</div>
                <textarea name="custom_css" rows="8" style="width: 100%;"><?= $e($cssValue) ?></textarea>
                <div style="margin-top:6px;">
                    <button class="button" type="submit">Save settings</button>
                </div>
            </td>
        </tr>
    </table>
</form>
