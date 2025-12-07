<?php
/** @var User $user */
/** @var Profile|null $profile */
/** @var Community $community */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, int): string $e */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Auth\Profile;
use Fred\Domain\Auth\User;
use Fred\Domain\Community\Community;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Profile: <?= $e($user->displayName) ?> (@<?= $e($user->username) ?>)</th>
    </tr>
    <tr>
        <td class="table-heading">Community</td>
        <td><?= $e($community->name) ?> · User ID: <?= $user->id ?></td>
    </tr>
    <tr>
        <td class="table-heading">Role</td>
        <td><?= $e($user->roleName) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Joined</td>
        <td><?= date('Y-m-d', $user->createdAt) ?></td>
    </tr>
    <?php if (($currentUser?->id ?? null) === $user->id): ?>
        <tr>
            <td class="table-heading">Actions</td>
            <td>
                <a class="button" href="/c/<?= $e($community->slug) ?>/settings/profile">Edit profile</a>
                <a class="button" href="/c/<?= $e($community->slug) ?>/settings/signature">Edit signature</a>
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
                <div class="post-body"><?= nl2br($e($profile->bio)) ?></div>
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
        <td><?= $e($profile?->location ?? '') ?: 'Not set' ?></td>
    </tr>
    <tr>
        <td class="table-heading">Website</td>
        <td>
            <?php if (($profile?->website ?? '') === ''): ?>
                Not set
            <?php else: ?>
                <a href="<?= $e($profile->website) ?>" target="_blank" rel="noopener">
                    <?= $e($profile->website) ?>
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
                    <?= $profile->signatureParsed !== '' ? $profile->signatureParsed : nl2br($e($profile->signatureRaw)) ?>
                </div>
            <?php endif; ?>
        </td>
    </tr>
</table>
