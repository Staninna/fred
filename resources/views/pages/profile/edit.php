<?php
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var \Fred\Domain\Community\Community $community */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var \Fred\Application\Auth\CurrentUser|null $currentUser */
/** @var callable(string, array): string $renderPartial */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Edit profile</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">Update your bio, location and website. These fields are plain text.</div>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/settings/profile" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="bio">Bio</label></td>
                        <td><textarea id="bio" name="bio" rows="4"><?= htmlspecialchars($old['bio'] ?? $profile?->bio ?? '', ENT_QUOTES, 'UTF-8') ?></textarea></td>
                    </tr>
                    <tr>
                        <td><label for="location">Location</label></td>
                        <td><input id="location" name="location" type="text" value="<?= htmlspecialchars($old['location'] ?? $profile?->location ?? '', ENT_QUOTES, 'UTF-8') ?>"></td>
                    </tr>
                    <tr>
                        <td><label for="website">Website</label></td>
                        <td><input id="website" name="website" type="text" value="<?= htmlspecialchars($old['website'] ?? $profile?->website ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="https://example.com"></td>
                    </tr>
                </table>
                <button class="button" type="submit">Save profile</button>
                <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/u/<?= htmlspecialchars($currentUser?->username ?? '', ENT_QUOTES, 'UTF-8') ?>">Back to profile</a>
            </form>
        </td>
    </tr>
</table>
