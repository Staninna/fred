<?php
/** @var array<int, \Fred\Domain\Forum\Post> $posts */
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
                    <div><strong><?= htmlspecialchars($post->authorName, ENT_QUOTES, 'UTF-8') ?></strong></div>
                    <div class="small"><?= date('Y-m-d H:i', $post->createdAt) ?></div>
                    <div class="small">Post #<?= $post->id ?></div>
                </td>
                <td class="body-cell">
                    <div class="post-body">
                        <?= $post->bodyParsed !== null
                            ? $post->bodyParsed
                            : nl2br(htmlspecialchars($post->bodyRaw, ENT_QUOTES, 'UTF-8')) ?>
                    </div>
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
