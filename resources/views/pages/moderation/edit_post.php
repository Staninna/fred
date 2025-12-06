<?php
/** @var \Fred\Domain\Forum\Post $post */
/** @var \Fred\Domain\Community\Community $community */
/** @var array<int, string> $errors */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
/** @var callable(string, int): string $e */
/** @var callable(string, array): string $renderPartial */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Edit post #<?= $post->id ?></th>
    </tr>
    <tr>
        <td>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/p/<?= $post->id ?>/edit" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120"><label for="body">Body</label></td>
                        <td>
                            <?= $renderPartial('partials/bbcode_toolbar.php', ['targetId' => 'body']) ?>
                            <textarea id="body" name="body" rows="6" required><?= $e($post->bodyRaw) ?></textarea>
                        </td>
                    </tr>
                </table>
                <button class="button" type="submit">Save changes</button>
                <a class="button" href="/c/<?= $e($community->slug) ?>/t/<?= $post->threadId ?>">Cancel</a>
            </form>
        </td>
    </tr>
</table>
