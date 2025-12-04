<?php
/** @var \Fred\Domain\Auth\User $user */
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
?>

<article class="card card--hero">
    <div>
        <p class="eyebrow">Profile</p>
        <h1><?= htmlspecialchars($user->displayName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="lede">@<?= htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') ?></p>
        <div class="tags">
            <span class="tag">Community: <?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="tag">User ID: <?= $user->id ?></span>
        </div>
        <?php if (($currentUser?->id ?? null) === $user->id): ?>
            <div class="account__actions" style="margin-top: 0.75rem;">
                <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/settings/signature">Edit signature</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="status">
        <div class="status__item">
            <div class="status__label">Joined</div>
            <div class="status__value"><?= date('Y-m-d', $user->createdAt) ?></div>
        </div>
        <div class="status__item">
            <div class="status__label">Role</div>
            <div class="status__value"><?= htmlspecialchars($user->roleName, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </div>
</article>

<article class="card">
    <h2>Signature</h2>
    <?php if ($profile === null || trim($profile->signatureRaw) === ''): ?>
        <p class="muted">No signature set.</p>
    <?php else: ?>
        <div class="post-body">
            <?= $profile->signatureParsed !== '' ? $profile->signatureParsed : nl2br(htmlspecialchars($profile->signatureRaw, ENT_QUOTES, 'UTF-8')) ?>
        </div>
    <?php endif; ?>
</article>
