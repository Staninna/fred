<?php
/** @var User $user */
/** @var Profile|null $profile */
/** @var Community $community */
/** @var CurrentUser|null $currentUser */
/** @var callable $renderPartial */
/** @var callable $e */
/** @var array<int, string> $profileErrors */
/** @var array<int, string> $signatureErrors */
/** @var array<int, string> $avatarErrors */
/** @var array<string, string> $oldProfile */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Auth\Profile;
use Fred\Domain\Auth\User;
use Fred\Domain\Community\Community;

?>

<?= $renderPartial('partials/profile/header.php', [
    'user' => $user,
    'community' => $community,
    'profile' => $profile,
    'renderPartial' => $renderPartial,
]) ?>

<?php if (($currentUser?->id ?? null) === $user->id): ?>
    <?= $renderPartial('partials/profile/edit_profile_form.php', [
        'community' => $community,
        'profile' => $profile,
        'profileErrors' => $profileErrors ?? [],
        'oldProfile' => $oldProfile ?? [],
        'e' => $e,
        'renderPartial' => $renderPartial,
    ]) ?>

    <?= $renderPartial('partials/profile/signature_form.php', [
        'community' => $community,
        'profile' => $profile,
        'signatureErrors' => $signatureErrors ?? [],
        'e' => $e,
        'renderPartial' => $renderPartial,
    ]) ?>

    <?= $renderPartial('partials/profile/avatar_form.php', [
        'community' => $community,
        'profile' => $profile,
        'avatarErrors' => $avatarErrors ?? [],
        'e' => $e,
        'renderPartial' => $renderPartial,
    ]) ?>
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

<?= $renderPartial('partials/profile/details.php', [
    'profile' => $profile,
    'e' => $e,
]) ?>

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
