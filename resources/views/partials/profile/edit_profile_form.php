<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var array<int, string> $profileErrors */
/** @var array<string, string> $oldProfile */
/** @var callable(string, int): string $e */
?>
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
