<?php
/** @var Post $post */
/** @var Community $community */
/** @var array<int, string> $errors */
/** @var CurrentUser|null $currentUser */
/** @var callable $e */
/** @var callable $renderPartial */
/** @var string|null $success */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;

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
                <input type="hidden" name="page" value="<?= $page ?? 1 ?>">
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
