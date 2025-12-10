<?php
/** @var Community $community */
/** @var Profile|null $profile */
/** @var array<int, string> $profileErrors */
/** @var array<string, string> $oldProfile */
/** @var callable $e */
/** @var callable $renderPartial */
/** @var string|null $success */

use Fred\Domain\Auth\Profile;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\View\ViewHelper;

$messageIdPrefix = 'profile-settings';
$messageAria = ViewHelper::buildMessageAria(
    !empty($profileErrors),
    !empty($success ?? ''),
    $messageIdPrefix,
);
?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Edit your profile</th>
    </tr>
    <tr>
        <td>
            <?= $renderPartial('partials/errors.php', [
                'errors' => $profileErrors,
                'success' => $success ?? null,
                'idPrefix' => $messageIdPrefix,
                'renderPartial' => $renderPartial,
            ]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/profile" novalidate>
                <?= $renderPartial('partials/csrf.php', ['renderPartial' => $renderPartial]) ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120"><label for="bio">Bio</label></td>
                        <td><textarea id="bio" name="bio" rows="3"<?= $messageAria ?>><?= $e($oldProfile['bio'] ?? ($profile ? $profile->bio : '')) ?></textarea></td>
                    </tr>
                    <tr>
                        <td><label for="location">Location</label></td>
                        <td><input id="location" name="location" type="text" value="<?= $e($oldProfile['location'] ?? ($profile ? $profile->location : '')) ?>"<?= $messageAria ?>></td>
                    </tr>
                    <tr>
                        <td><label for="website">Website</label></td>
                        <td><input id="website" name="website" type="url" value="<?= $e($oldProfile['website'] ?? ($profile ? $profile->website : '')) ?>"<?= $messageAria ?>></td>
                    </tr>
                </table>
                <button class="button" type="submit">Save profile</button>
            </form>
        </td>
    </tr>
</table>
