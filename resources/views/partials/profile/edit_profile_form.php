<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var array<int, string> $profileErrors */
/** @var array<string, string> $oldProfile */
/** @var callable(string, int): string $e */
/** @var string|null $success */

$messageIdPrefix = 'profile-settings';
$messageTargets = [];
if (!empty($profileErrors ?? [])) {
    $messageTargets[] = $messageIdPrefix . '-errors';
}
if (!empty($success ?? '')) {
    $messageTargets[] = $messageIdPrefix . '-success';
}
$messageAria = $messageTargets === [] ? '' : ' aria-describedby="' . $e(implode(' ', $messageTargets)) . '"';
?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Edit your profile</th>
    </tr>
    <tr>
        <td>
            <?= $renderPartial('partials/errors.php', [
                'errors' => $profileErrors ?? [],
                'success' => $success ?? null,
                'idPrefix' => $messageIdPrefix,
                'renderPartial' => $renderPartial,
            ]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/profile" novalidate>
                <?= $renderPartial('partials/csrf.php', ['renderPartial' => $renderPartial]) ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120"><label for="bio">Bio</label></td>
                        <td><textarea id="bio" name="bio" rows="3"<?= $messageAria ?>><?= $e($oldProfile['bio'] ?? $profile?->bio ?? '') ?></textarea></td>
                    </tr>
                    <tr>
                        <td><label for="location">Location</label></td>
                        <td><input id="location" name="location" type="text" value="<?= $e($oldProfile['location'] ?? $profile?->location ?? '') ?>"<?= $messageAria ?>></td>
                    </tr>
                    <tr>
                        <td><label for="website">Website</label></td>
                        <td><input id="website" name="website" type="url" value="<?= $e($oldProfile['website'] ?? $profile?->website ?? '') ?>"<?= $messageAria ?>></td>
                    </tr>
                </table>
                <button class="button" type="submit">Save profile</button>
            </form>
        </td>
    </tr>
</table>
