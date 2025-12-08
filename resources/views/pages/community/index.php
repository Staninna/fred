<?php
/** @var array<int, Community> $communities */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, ?int=): string $e */

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
        <td><?= $e($environment ?? '') ?></td>
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
                    <a href="/c/<?= $e($community->slug) ?>">
                        <?= $e($community->name) ?>
                    </a>
                </td>
                <td><?= $e($community->description) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<?php if (!empty($canCreateCommunity ?? false)): ?>
    <?php
    $messageIdPrefix = 'community-create';
    $messageTargets = [];
    if (!empty($errors)) {
        $messageTargets[] = $messageIdPrefix . '-errors';
    }
    if (!empty($success ?? '')) {
        $messageTargets[] = $messageIdPrefix . '-success';
    }
    $messageAria = $messageTargets === [] ? '' : ' aria-describedby="' . $e(implode(' ', $messageTargets)) . '"';
    ?>
    <table class="section-table" cellpadding="0" cellspacing="0">
        <tr>
            <th>Create community</th>
        </tr>
        <tr>
            <td>
                <?= $renderPartial('partials/errors.php', [
                    'errors' => $errors,
                    'success' => $success ?? null,
                    'idPrefix' => $messageIdPrefix,
                ]) ?>
                <form method="post" action="/communities" novalidate>
                    <?= $renderPartial('partials/csrf.php') ?>
                    <table class="form-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="140"><label for="name">Name</label></td>
                            <td><input id="name" name="name" type="text" value="<?= $e($old['name'] ?? '') ?>" required<?= $messageAria ?>></td>
                        </tr>
                        <tr>
                            <td><label for="slug">Slug</label></td>
                            <td><input id="slug" name="slug" type="text" value="<?= $e($old['slug'] ?? '') ?>" placeholder="auto-generated from name"<?= $messageAria ?>></td>
                        </tr>
                        <tr>
                            <td><label for="description">Description</label></td>
                            <td><input id="description" name="description" type="text" value="<?= $e($old['description'] ?? '') ?>"<?= $messageAria ?>></td>
                        </tr>
                    </table>
                    <button class="button" type="submit">Create</button>
                </form>
            </td>
        </tr>
    </table>
<?php endif; ?>
