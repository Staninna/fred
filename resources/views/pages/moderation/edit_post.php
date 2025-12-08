<?php
/** @var \Fred\Domain\Forum\Post $post */
/** @var \Fred\Domain\Community\Community $community */
/** @var array<int, string> $errors */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
/** @var callable(string, ?int=): string $e */
/** @var callable(string, array): string $renderPartial */
/** @var string|null $success */

$messageIdPrefix = 'moderation-edit-post';
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
        <th>Edit post #<?= $post->id ?></th>
    </tr>
    <tr>
        <td>
            <?= $renderPartial('partials/errors.php', [
                'errors' => $errors,
                'success' => $success ?? null,
                'idPrefix' => $messageIdPrefix,
            ]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/p/<?= $post->id ?>/edit" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <input type="hidden" name="page" value="<?= (int) ($page ?? 1) ?>">
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120"><label for="body">Body</label></td>
                        <td>
                            <?= $renderPartial('partials/bbcode_toolbar.php', ['targetId' => 'body']) ?>
                            <textarea id="body" name="body" rows="6" required<?= $messageAria ?> data-mention-endpoint="/c/<?= $e($community->slug) ?>/mentions/suggest"><?= $e($post->bodyRaw) ?></textarea>
                        </td>
                    </tr>
                </table>
                <button class="button" type="submit">Save changes</button>
                <a class="button" href="/c/<?= $e($community->slug) ?>/t/<?= $post->threadId ?>">Cancel</a>
            </form>
        </td>
    </tr>
</table>
