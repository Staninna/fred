<?php
/** @var array<int, Post> $posts */
/** @var callable(string, int): string $e */
/** @var bool $canEditAnyPost */
/** @var bool $canDeleteAnyPost */
/** @var string $communitySlug */

use Fred\Domain\Forum\Post;
?>

<?php if ($posts === []): ?>
    <div class="notice">No replies yet.</div>
<?php else: ?>
    <table class="section-table post-table" cellpadding="0" cellspacing="0">
        <tr>
            <th colspan="2">Replies</th>
        </tr>
        <?php foreach ($posts as $post): ?>
            <tr id="post-<?= $post->id ?>">
                <td class="author-cell">
                    <div><strong><?= $e($post->authorName) ?></strong></div>
                    <div class="small"><?= date('Y-m-d H:i', $post->createdAt) ?></div>
                    <div class="small">Post #<?= $post->id ?></div>
                </td>
                <td class="body-cell">
                    <div class="post-body">
                        <?= $post->bodyParsed !== null
                            ? $post->bodyParsed
                            : nl2br($e($post->bodyRaw)) ?>
                    </div>
                    <?php if (!empty($canDeleteAnyPost ?? false)): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($communitySlug) ?>/p/<?= $post->id ?>/delete">
                            <button class="button" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($canEditAnyPost ?? false)): ?>
                        <a class="button" href="/c/<?= $e($communitySlug) ?>/p/<?= $post->id ?>/edit">Edit</a>
                    <?php endif; ?>
                    <?php if ($post->signatureSnapshot !== null && trim($post->signatureSnapshot) !== ''): ?>
                        <hr>
                        <div class="small">
                            <?= $post->signatureSnapshot ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
