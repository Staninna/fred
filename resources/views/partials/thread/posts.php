<?php
/** @var array<int, Post> $posts */
/** @var callable $e */
/** @var bool $canEditAnyPost */
/** @var bool $canDeleteAnyPost */
/** @var string $communitySlug */
/** @var array<int, array<int, Attachment>> $attachmentsByPost */
/** @var array<int, Profile> $profilesByUserId */
/** @var bool $canReport */
/** @var int|null $currentUserId */
/** @var int $page */
/** @var bool $canReact */
/** @var array<int, array<string, int>> $reactionsByPost */
/** @var array<int, array{code: string, filename: string, url: string}> $emoticons */
/** @var array<string, string> $emoticonMap */
/** @var string $emoticonVersion */
/** @var array<int, string> $userReactions */
/** @var array<int, array<string, array{names: string[], extra: int}>> $reactionUsersByPost */
/** @var array<int, array<int, array{url:string, title:string, description:?string, image:?string, host:string}>> $linkPreviewsByPost */
/** @var array<int, string[]> $linkPreviewUrlsByPost */
/** @var callable $renderPartial */

use Fred\Domain\Auth\Profile;
use Fred\Domain\Forum\Attachment;
use Fred\Domain\Forum\Post;

?>

<?php
$emoticonVersion = $emoticonVersion;
$resolvedEmoticons = [];
$resolveEmoticonUrl = static function (string $code) use (&$resolvedEmoticons, $emoticonMap, $emoticonVersion): string {
    $normalized = strtolower($code);

    if (isset($resolvedEmoticons[$normalized])) {
        return $resolvedEmoticons[$normalized];
    }

    if (isset($emoticonMap[$normalized])) {
        return $resolvedEmoticons[$normalized] = $emoticonMap[$normalized];
    }

    return $resolvedEmoticons[$normalized] = '/emoticons/' . rawurlencode($normalized) . '.png' . $emoticonVersion;
};
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
                    <?php if ($profile && $profile->avatarPath): ?>
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
                    <?php if ($canDeleteAnyPost): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($communitySlug) ?>/p/<?= $post->id ?>/delete">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <input type="hidden" name="page" value="<?= $page ?>">
                            <button class="button" type="submit">Delete</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($canEditAnyPost): ?>
                        <a class="button" href="/c/<?= $e($communitySlug) ?>/p/<?= $post->id ?>/edit?page=<?= $page ?>">Edit</a>
                    <?php endif; ?>
                    <?php if ($canReport && ($currentUserId ?? null) !== $post->authorId): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($communitySlug) ?>/p/<?= $post->id ?>/report">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <input type="hidden" name="page" value="<?= $page ?>">
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
                    <?php $postReactions = $reactionsByPost[$post->id] ?? []; ?>
                    <?php $userReaction = $userReactions[$post->id] ?? null; ?>
                    <?php $reactionUsers = $reactionUsersByPost[$post->id] ?? []; ?>
                    <?php if ($postReactions !== []): ?>
                        <div class="reactions">
                            <?php foreach ($postReactions as $reactionCode => $count): ?>
                                <?php $reactionUrl = $resolveEmoticonUrl($reactionCode); ?>
                                <?php $who = $reactionUsers[$reactionCode] ?? ['names' => [], 'extra' => 0]; ?>
                                <?php $tooltip = $who['names'] === [] ? '' : implode(', ', $who['names']); ?>
                                <?php if ($who['extra'] > 0) {
                                    $tooltip .= ' +' . (int) $who['extra'] . ' more';
                                } ?>
                                <form class="inline-form" method="post" action="/c/<?= $e($communitySlug) ?>/p/<?= $post->id ?>/react">
                                    <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                                    <input type="hidden" name="page" value="<?= $page ?>">
                                    <?php if ($userReaction !== null && $userReaction === $reactionCode): ?>
                                        <input type="hidden" name="remove" value="1">
                                    <?php endif; ?>
                                    <button
                                        class="reaction-chip<?= $userReaction === $reactionCode ? ' active' : '' ?>"
                                        type="submit"
                                        name="emoticon"
                                        value="<?= $e($reactionCode) ?>"
                                        data-tooltip="<?= $e($tooltip) ?>"
                                        aria-label="<?= $e($tooltip !== '' ? $tooltip : 'Reactions') ?>"
                                    >
                                        <img
                                            src="<?= $e($reactionUrl) ?>"
                                            alt="<?= $e($reactionCode) ?>"
                                            width="18"
                                            height="18"
                                            onerror="this.src='/emoticons/<?= $e(rawurlencode($reactionCode)) ?>.gif<?= $e($emoticonVersion) ?>'"
                                        >
                                        <span class="small">x<?= (int) $count ?></span>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <?php $previews = $linkPreviewsByPost[$post->id] ?? []; ?>
                    <?php if ($previews !== []): ?>
                        <?= $renderPartial('partials/link_preview.php', ['previews' => $previews, 'e' => $e]) ?>
                    <?php elseif (!empty($linkPreviewUrlsByPost[$post->id] ?? [])): ?>
                        <div class="link-preview-slot" data-preview-post="<?= $post->id ?>">
                            <div class="small muted" data-preview-notice>Fetching link preview...</div>
                        </div>
                    <?php endif; ?>
                    <?php if ($canReact): ?>
                        <details class="reaction-picker">
                            <summary class="small">Add reaction</summary>
                            <form class="reaction-form" method="post" action="/c/<?= $e($communitySlug) ?>/p/<?= $post->id ?>/react">
                                <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                                <input type="hidden" name="page" value="<?= $page ?>">
                                <div class="reaction-grid">
                                    <?php foreach ($emoticons as $emoticon): ?>
                                        <button class="reaction-btn<?= ($userReaction === $emoticon['code']) ? ' active' : '' ?>" type="submit" name="emoticon" value="<?= $e($emoticon['code']) ?>">
                                            <img src="<?= $e($emoticon['url']) ?>" alt="<?= $e($emoticon['code']) ?>" width="22" height="22">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </form>
                        </details>
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
