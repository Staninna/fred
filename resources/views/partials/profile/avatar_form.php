<?php
/** @var Community $community */
/** @var Profile|null $profile */
/** @var array<int, string> $avatarErrors */
/** @var callable $e */
/** @var callable $renderPartial */
/** @var string|null $success */

use Fred\Domain\Auth\Profile;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\View\ViewHelper;

$messageIdPrefix = 'avatar-settings';
$messageAria = ViewHelper::buildMessageAria(
    !empty($avatarErrors),
    !empty($success ?? ''),
    $messageIdPrefix,
);
?>
<?= $renderPartial('partials/form_section_header.php', [
    'title' => 'Avatar',
    'errors' => $avatarErrors,
    'success' => $success ?? null,
    'idPrefix' => $messageIdPrefix,
    'infoText' => null,
    'renderPartial' => $renderPartial,
]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/avatar" enctype="multipart/form-data" novalidate>
                <?= $renderPartial('partials/csrf.php', ['renderPartial' => $renderPartial]) ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120">Current avatar</td>
                        <td>
                            <?= $renderPartial('partials/avatar_display.php', ['profile' => $profile, 'renderPartial' => $renderPartial]) ?>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="avatar">New avatar</label></td>
                        <td><input id="avatar" name="avatar" type="file" accept=".png,.jpg,.jpeg,.gif,.webp" required<?= $messageAria ?>></td>
                    </tr>
                </table>
                <button class="button" type="submit">Upload avatar</button>
            </form>
        </td>
    </tr>
</table>
