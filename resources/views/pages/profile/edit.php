<?php
/** @var Profile $profile */
/** @var Community $community */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var CurrentUser $currentUser */
/** @var callable $renderPartial */
/** @var callable $e */
/** @var string|null $success */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Auth\Profile;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\View\ViewHelper;

$messageIdPrefix = 'profile-edit';
$messageAria = ViewHelper::buildMessageAria(
    !empty($errors),
    !empty($success ?? ''),
    $messageIdPrefix,
);

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Edit profile</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">Update your bio, location and website. These fields are plain text.</div>
            <?= $renderPartial('partials/errors.php', [
                'errors' => $errors,
                'success' => $success ?? null,
                'idPrefix' => $messageIdPrefix,
            ]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/profile" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="bio">Bio</label></td>
                        <td><textarea id="bio" name="bio" rows="4"<?= $messageAria ?>><?= $e($old['bio'] ?? $profile->bio) ?></textarea></td>
                    </tr>
                    <tr>
                        <td><label for="location">Location</label></td>
                        <td><input id="location" name="location" type="text" value="<?= $e($old['location'] ?? $profile->location) ?>"<?= $messageAria ?>></td>
                    </tr>
                    <tr>
                        <td><label for="website">Website</label></td>
                        <td><input id="website" name="website" type="url" value="<?= $e($old['website'] ?? $profile->website) ?>"<?= $messageAria ?>></td>
                    </tr>
                </table>
                <button class="button" type="submit">Save profile</button>
                <a class="button" href="/c/<?= $e($community->slug) ?>/u/<?= $e($currentUser->username) ?>">Back to profile</a>
            </form>
        </td>
    </tr>
</table>
