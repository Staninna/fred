<?php
/** @var \Fred\Domain\Auth\User $user */
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Profile: <?= htmlspecialchars($user->displayName, ENT_QUOTES, 'UTF-8') ?> (@<?= htmlspecialchars($user->username, ENT_QUOTES, 'UTF-8') ?>)</th>
    </tr>
    <tr>
        <td class="table-heading">Community</td>
        <td><?= htmlspecialchars($community->name, ENT_QUOTES, 'UTF-8') ?> · User ID: <?= $user->id ?></td>
    </tr>
    <tr>
        <td class="table-heading">Role</td>
        <td><?= htmlspecialchars($user->roleName, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    <tr>
        <td class="table-heading">Joined</td>
        <td><?= date('Y-m-d', $user->createdAt) ?></td>
    </tr>
    <?php if (($currentUser?->id ?? null) === $user->id): ?>
        <tr>
            <td class="table-heading">Actions</td>
            <td>
                <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/settings/profile">Edit profile</a>
                <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/settings/signature">Edit signature</a>
            </td>
        </tr>
    <?php endif; ?>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Bio</th>
    </tr>
    <tr>
        <td>
            <?php if ($profile === null || trim($profile->bio) === ''): ?>
                <div class="muted">No bio set.</div>
            <?php else: ?>
                <div class="post-body"><?= nl2br(htmlspecialchars($profile->bio, ENT_QUOTES, 'UTF-8')) ?></div>
            <?php endif; ?>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Details</th>
    </tr>
    <tr>
        <td class="table-heading">Location</td>
        <td><?= htmlspecialchars($profile?->location ?? '', ENT_QUOTES, 'UTF-8') ?: 'Not set' ?></td>
    </tr>
    <tr>
        <td class="table-heading">Website</td>
        <td>
            <?php if (($profile?->website ?? '') === ''): ?>
                Not set
            <?php else: ?>
                <a href="<?= htmlspecialchars($profile->website, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
                    <?= htmlspecialchars($profile->website, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endif; ?>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Signature</th>
    </tr>
    <tr>
        <td>
            <?php if ($profile === null || trim($profile->signatureRaw) === ''): ?>
                <div class="muted">No signature set.</div>
            <?php else: ?>
                <div class="post-body">
                    <?= $profile->signatureParsed !== '' ? $profile->signatureParsed : nl2br(htmlspecialchars($profile->signatureRaw, ENT_QUOTES, 'UTF-8')) ?>
                </div>
            <?php endif; ?>
        </td>
    </tr>
</table>
