<?php
/** @var User $user */
/** @var Profile|null $profile */
/** @var Community $community */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */
/** @var array<int, string> $profileErrors */
/** @var array<int, string> $signatureErrors */
/** @var array<int, string> $avatarErrors */
/** @var array<string, string> $oldProfile */

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
        <td class="table-heading">Avatar</td>
        <td>
            <?php if (!empty($profile?->avatarPath ?? '')): ?>
                <img src="/uploads/<?= $e($profile->avatarPath) ?>" alt="Avatar" style="max-width: 120px; max-height: 120px;">
            <?php else: ?>
                <span class="muted">No avatar set.</span>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Joined</td>
        <td><?= date('Y-m-d', $user->createdAt) ?></td>
    </tr>
</table>

<?php if (($currentUser?->id ?? null) === $user->id): ?>
    <table class="section-table" cellpadding="0" cellspacing="0">
        <tr>
            <th>Edit your profile</th>
        </tr>
        <tr>
            <td>
                <?= $renderPartial('partials/errors.php', ['errors' => $profileErrors ?? []]) ?>
                <form method="post" action="/c/<?= $e($community->slug) ?>/settings/profile" novalidate>
                    <table class="form-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="120"><label for="bio">Bio</label></td>
                            <td><textarea id="bio" name="bio" rows="3"><?= $e($oldProfile['bio'] ?? $profile?->bio ?? '') ?></textarea></td>
                        </tr>
                        <tr>
                            <td><label for="location">Location</label></td>
                            <td><input id="location" name="location" type="text" value="<?= $e($oldProfile['location'] ?? $profile?->location ?? '') ?>"></td>
                        </tr>
                        <tr>
                            <td><label for="website">Website</label></td>
                            <td><input id="website" name="website" type="url" value="<?= $e($oldProfile['website'] ?? $profile?->website ?? '') ?>"></td>
                        </tr>
                    </table>
                    <button class="button" type="submit">Save profile</button>
                </form>
            </td>
        </tr>
    </table>

    <table class="section-table" cellpadding="0" cellspacing="0">
        <tr>
            <th>Edit signature</th>
        </tr>
        <tr>
            <td>
                <?= $renderPartial('partials/errors.php', ['errors' => $signatureErrors ?? []]) ?>
                <form method="post" action="/c/<?= $e($community->slug) ?>/settings/signature" novalidate>
                    <table class="form-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="120"><label for="signature">Signature</label></td>
                            <td><textarea id="signature" name="signature" rows="3"><?= $e($profile?->signatureRaw ?? '') ?></textarea></td>
                        </tr>
                    </table>
                    <button class="button" type="submit">Save signature</button>
                </form>
            </td>
        </tr>
    </table>

    <table class="section-table" cellpadding="0" cellspacing="0">
        <tr>
            <th>Avatar</th>
        </tr>
        <tr>
            <td>
                <?= $renderPartial('partials/errors.php', ['errors' => $avatarErrors ?? []]) ?>
                <form method="post" action="/c/<?= $e($community->slug) ?>/settings/avatar" enctype="multipart/form-data" novalidate>
                    <table class="form-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td width="120">Current avatar</td>
                            <td>
                                <?php if (!empty($profile?->avatarPath ?? '')): ?>
                                    <img src="/uploads/<?= $e($profile->avatarPath) ?>" alt="Avatar" style="max-width: 120px; max-height: 120px;">
                                <?php else: ?>
                                    <span class="muted">No avatar set.</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><label for="avatar">New avatar</label></td>
                            <td><input id="avatar" name="avatar" type="file" accept=".png,.jpg,.jpeg,.gif,.webp" required></td>
                        </tr>
                    </table>
                    <button class="button" type="submit">Upload avatar</button>
                </form>
            </td>
        </tr>
    </table>
<?php endif; ?>

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
