<?php
/** @var Profile|null $profile */
/** @var Community $community */
/** @var CurrentUser|null $currentUser */
/** @var array<int, string> $errors */
/** @var callable $renderPartial */
/** @var callable $e */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Auth\Profile;
use Fred\Domain\Community\Community;

?>

<?= $renderPartial('partials/form_section_header.php', [
    'title' => 'Edit avatar',
    'errors' => $errors,
    'infoText' => 'Upload an image (png, jpg, gif, webp). Max 500 KB.',
]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/avatar" enctype="multipart/form-data" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140">Current avatar</td>
                        <td>
                            <?= $renderPartial('partials/avatar_display.php', ['profile' => $profile]) ?>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="avatar">New avatar</label></td>
                        <td><input id="avatar" name="avatar" type="file" accept=".png,.jpg,.jpeg,.gif,.webp" required></td>
                    </tr>
                </table>
                <button class="button" type="submit">Upload avatar</button>
                <a class="button" href="/c/<?= $e($community->slug) ?>/u/<?= $e($currentUser?->username ?? '') ?>">Back to profile</a>
            </form>
        </td>
    </tr>
</table>
