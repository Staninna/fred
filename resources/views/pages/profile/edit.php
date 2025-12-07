<?php
/** @var Profile|null $profile */
/** @var Community $community */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Auth\Profile;
use Fred\Domain\Community\Community;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Edit profile</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">Update your bio, location and website. These fields are plain text.</div>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/profile" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="bio">Bio</label></td>
                        <td><textarea id="bio" name="bio" rows="4"><?= $e($old['bio'] ?? $profile?->bio ?? '') ?></textarea></td>
                    </tr>
                    <tr>
                        <td><label for="location">Location</label></td>
                        <td><input id="location" name="location" type="text" value="<?= $e($old['location'] ?? $profile?->location ?? '') ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="website">Website</label></td>
                        <td><input id="website" name="website" type="text" value="<?= $e($old['website'] ?? $profile?->website ?? '') ?>" placeholder="https://example.com"></td>
                    </tr>
                </table>
                <button class="button" type="submit">Save profile</button>
                <a class="button" href="/c/<?= $e($community->slug) ?>/u/<?= $e($currentUser?->username ?? '') ?>">Back to profile</a>
            </form>
        </td>
    </tr>
</table>
