<?php
/** @var array<int, Post> $posts */
/** @var callable(string, int): string $e */
/** @var bool $canEditAnyPost */
/** @var bool $canDeleteAnyPost */
/** @var string $communitySlug */
/** @var array<int, array<int, \Fred\Domain\Forum\Attachment>> $attachmentsByPost */
/** @var array<int, \Fred\Domain\Auth\Profile> $profilesByUserId */
/** @var bool $canReport */
/** @var int|null $currentUserId */
/** @var int $page */

use Fred\Domain\Forum\Post;
use Fred\Domain\Forum\Attachment;
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
                    <?php $profile = $profilesByUserId[$post->authorId] ?? null; ?>
                    <?php if (!empty($profile?->avatarPath ?? '')): ?>
                        <div class="author-avatar">
                            <img src="/uploads/<?= $e($profile->avatarPath) ?>" alt="<?= $e($post->authorName) ?> avatar" style="max-width: 64px; max-height: 64px;">
                        </div>
                    <?php endif; ?>
                    <div><strong><a href="/c/<?= $e($communitySlug) ?>/u/<?= $e($post->authorUsername) ?>"><?= $e($post->authorName) ?></a></strong></div>
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
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <input type="hidden" name="page" value="<?= (int) ($page ?? 1) ?>">
                            <button class="button" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                    <?php if (!empty($canEditAnyPost ?? false)): ?>
                        <a class="button" href="/c/<?= $e($communitySlug) ?>/p/<?= $post->id ?>/edit?page=<?= (int) ($page ?? 1) ?>">Edit</a>
                    <?php endif; ?>
                    <?php if (!empty($canReport ?? false) && ($currentUserId ?? null) !== $post->authorId): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($communitySlug) ?>/p/<?= $post->id ?>/report">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <input type="hidden" name="page" value="<?= (int) ($page ?? 1) ?>">
                            <label class="small" for="report_reason_<?= $post->id ?>">Report reason</label>
                            <input id="report_reason_<?= $post->id ?>" name="reason" type="text" maxlength="200" required placeholder="Spam, abuse...">
                            <button class="button" type="submit">Report</button>
                        </form>
                    <?php endif; ?>
                    <?php foreach ($attachmentsByPost[$post->id] ?? [] as $attachment): ?>
                        <div class="attachment">
                            <div class="small muted">Attachment: <?= $e($attachment->originalName) ?></div>
                            <img src="/uploads/<?= $e($attachment->path) ?>" alt="<?= $e($attachment->originalName) ?>" style="max-width: 360px; display: block; margin-top: 4px;">
                        </div>
                    <?php endforeach; ?>
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
