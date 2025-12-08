<?php
/** @var Community $community */
/** @var Board $board */
/** @var Category $category */
/** @var \Fred\Domain\Forum\Thread $thread */
/** @var array<int, Post> $posts */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, array): string $renderPartial */
/** @var bool $canModerate */
/** @var bool $canLockThread */
/** @var bool $canStickyThread */
/** @var bool $canMoveThread */
/** @var bool $canEditAnyPost */
/** @var bool $canDeleteAnyPost */
/** @var bool $canBanUsers */
/** @var array<int, Board> $allBoards */
/** @var bool $canModerate */
/** @var array<int, \Fred\Domain\Auth\Profile> $profilesByUserId */
/** @var callable(string, ?int=): string $e */
/** @var array<int, array<int, array{url:string, title:string, description:?string, image:?string, host:string}>> $linkPreviewsByPost */
/** @var array<int, string[]> $linkPreviewUrlsByPost */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Post;

?>

<?php if (!empty($reportNotice ?? null)): ?>
    <div class="notice"><?= $e($reportNotice) ?></div>
<?php endif; ?>
<?php if (!empty($reportError ?? null)): ?>
    <div class="notice"><?= $e($reportError) ?></div>
<?php endif; ?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2"><?= $e($thread->title) ?></th>
    </tr>
    <tr>
        <td class="table-heading">Location</td>
        <td>
            Community:
            <a href="/c/<?= $e($community->slug) ?>">
                <?= $e($community->name) ?>
            </a>
            · Board:
            <a href="/c/<?= $e($community->slug) ?>/b/<?= $e($board->slug) ?>">
                <?= $e($board->name) ?>
            </a>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Started</td>
        <td><?= date('Y-m-d H:i', $thread->createdAt) ?> · Posts: <?= $totalPosts ?? count($posts) ?> <?= $thread->isLocked ? '· Locked' : '' ?></td>
    </tr>
    <?php if (!empty($canModerate ?? false)): ?>
        <tr>
            <td class="table-heading">Admin</td>
            <td>
                <a class="button" href="/c/<?= $e($community->slug) ?>/admin/structure">Admin this community</a>
                <?php if (!empty($canBanUsers ?? false)): ?>
                    <a class="button" href="/c/<?= $e($community->slug) ?>/admin/bans">Manage bans</a>
                <?php endif; ?>
                <?php if (!empty($canMoveThread ?? false)): ?>
                    <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/move">
                        <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                        <label for="target_board" class="small">Move to:</label>
                        <?php
                        $boardOptions = array_map(
                            static fn ($boardOption) => ['value' => $boardOption->slug, 'label' => $boardOption->name],
                            $allBoards
                        );
                    echo $renderPartial('partials/select.php', [
                        'name' => 'target_board',
                        'id' => 'target_board',
                        'options' => $boardOptions,
                        'selected' => (string) $board->slug,
                        'class' => 'inline-select',
                    ]);
                    ?>
                        <button class="button" type="submit">Move</button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($canLockThread ?? false)): ?>
                    <?php if ($thread->isLocked): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/unlock">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Unlock</button>
                        </form>
                    <?php else: ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/lock">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Lock</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($canStickyThread ?? false)): ?>
                    <?php if ($thread->isSticky): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/unsticky">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Unsticky</button>
                        </form>
                    <?php else: ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/sticky">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Sticky</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!empty($canModerate ?? false)): ?>
                    <?php if ($thread->isAnnouncement): ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/unannounce">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Unannounce</button>
                        </form>
                    <?php else: ?>
                        <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/announce">
                            <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="button" type="submit">Mark as announcement</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </td>
        </tr>
    <?php endif; ?>
</table>

<div id="post-list">
    <?= $renderPartial('partials/thread/posts.php', [
        'posts' => $posts,
        'canEditAnyPost' => $canEditAnyPost ?? false,
        'canDeleteAnyPost' => $canDeleteAnyPost ?? false,
        'communitySlug' => $community->slug,
        'profilesByUserId' => $profilesByUserId ?? [],
        'attachmentsByPost' => $attachmentsByPost ?? [],
        'canReport' => !($currentUser?->isGuest() ?? true),
        'currentUserId' => $currentUser?->id,
        'page' => $pagination['page'] ?? 1,
        'board' => $board,
        'thread' => $thread,
        'canReact' => ($currentUser ?? null) !== null && !$currentUser->isGuest() && !$thread->isLocked && !$board->isLocked,
        'reactionsByPost' => $reactionsByPost ?? [],
        'reactionUsersByPost' => $reactionUsersByPost ?? [],
        'linkPreviewsByPost' => $linkPreviewsByPost ?? [],
        'linkPreviewUrlsByPost' => $linkPreviewUrlsByPost ?? [],
        'renderPartial' => $renderPartial,
        'emoticons' => $emoticons ?? [],
        'emoticonMap' => $emoticonMap ?? [],
        'emoticonVersion' => $emoticonVersion ?? '',
        'userReactions' => $userReactions ?? [],
    ]) ?>
</div>

<?php $postsNeedingPreviews = array_keys(array_diff_key($linkPreviewUrlsByPost ?? [], $linkPreviewsByPost ?? [])); ?>
<?php if ($postsNeedingPreviews !== []): ?>
    <script>
    (() => {
        const postIds = <?= json_encode(array_values($postsNeedingPreviews)) ?>;
        if (!postIds.length) {
            return;
        }

        const containers = new Map();
        document.querySelectorAll('[data-preview-post]').forEach((el) => {
            const id = parseInt(el.dataset.previewPost, 10);
            if (!Number.isNaN(id)) {
                containers.set(id, el);
            }
        });

        if (!containers.size) {
            return;
        }

        const url = '/c/<?= $e($community->slug) ?>/t/<?= $thread->id ?>/previews?posts=' + postIds.join(',');
        const controller = new AbortController();
        const slowTimer = setTimeout(() => {
            containers.forEach((el) => {
                if (el.dataset.loaded === '1') {
                    return;
                }
                const notice = el.querySelector('[data-preview-notice]');
                if (notice) {
                    notice.textContent = 'Fetching link preview...';
                }
            });
        }, 4000);
        const timeoutTimer = setTimeout(() => controller.abort(), 8000);

        fetch(url, { headers: { 'Accept': 'application/json' }, signal: controller.signal })
            .then((resp) => resp.ok ? resp.json() : null)
            .then((data) => {
                if (!data || !Array.isArray(data.previews)) {
                    return;
                }

                data.previews.forEach((item) => {
                    const target = containers.get(item.postId);
                    if (!target) {
                        return;
                    }

                    const rendered = renderPreviewList(item.previews);
                    if (!rendered) {
                        target.remove();
                        return;
                    }

                    target.replaceChildren(rendered);
                    target.dataset.loaded = '1';
                });
            })
            .catch(() => {})
            .finally(() => {
                clearTimeout(slowTimer);
                clearTimeout(timeoutTimer);
                setTimeout(() => {
                    containers.forEach((el) => {
                        if (el.dataset.loaded === '1') {
                            return;
                        }
                        el.remove();
                    });
                }, 3000);
            });

        function renderPreviewList(items) {
            if (!Array.isArray(items) || !items.length) {
                return null;
            }

            const list = document.createElement('div');
            list.className = 'link-preview-list';

            items.forEach((preview) => {
                if (!preview || !preview.url) {
                    return;
                }

                const anchor = document.createElement('a');
                anchor.className = 'link-preview' + (preview.image ? '' : ' link-preview--no-thumb');
                anchor.href = preview.url;
                anchor.target = '_blank';
                anchor.rel = 'noopener';

                if (preview.image) {
                    const thumb = document.createElement('div');
                    thumb.className = 'link-preview__thumb';
                    const img = document.createElement('img');
                    img.src = preview.image;
                    img.alt = 'Preview image';
                    img.loading = 'lazy';
                    thumb.appendChild(img);
                    anchor.appendChild(thumb);
                }

                const body = document.createElement('div');
                body.className = 'link-preview__body';

                if (preview.host) {
                    const host = document.createElement('div');
                    host.className = 'link-preview__host small muted';
                    host.textContent = preview.host;
                    body.appendChild(host);
                }

                if (preview.title) {
                    const title = document.createElement('div');
                    title.className = 'link-preview__title';
                    title.textContent = preview.title;
                    body.appendChild(title);
                }

                if (preview.description) {
                    const desc = document.createElement('div');
                    desc.className = 'link-preview__desc small';
                    desc.textContent = preview.description;
                    body.appendChild(desc);
                }

                anchor.appendChild(body);
                list.appendChild(anchor);
            });

            return list.children.length ? list : null;
        }
    })();
    </script>
<?php endif; ?>

<?php if (!empty($pagination) && ($pagination['totalPages'] ?? 1) > 1): ?>
    <?= $renderPartial('partials/pagination.php', [
        'page' => (int) ($pagination['page'] ?? 1),
        'totalPages' => (int) ($pagination['totalPages'] ?? 1),
        'baseUrl' => '/c/' . $e($community->slug) . '/t/' . $thread->id,
        'isTable' => false,
    ]) ?>
<?php endif; ?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Reply</th>
    </tr>
    <tr>
        <td>
            <?php if (($currentUser ?? null) === null || $currentUser->isGuest()): ?>
                <div class="muted">Sign in to reply.</div>
                <a class="button" href="/login">Sign in</a>
            <?php elseif ($thread->isLocked || $board->isLocked): ?>
                <div class="muted">This thread is locked.</div>
            <?php else: ?>
                <?= $renderPartial('partials/thread/message_form.php', [
                    'action' => '/c/' . $community->slug . '/t/' . $thread->id . '/reply',
                    'submitLabel' => 'Post reply',
                    'textareaId' => 'reply_body',
                    'textareaName' => 'body',
                    'textareaLabel' => 'Message',
                    'bodyValue' => '',
                    'includeAttachment' => true,
                    'page' => $pagination['page'] ?? 1,
                    'mentionEndpoint' => '/c/' . $community->slug . '/mentions/suggest',
                    'renderPartial' => $renderPartial,
                ]) ?>
            <?php endif; ?>
        </td>
    </tr>
</table>
