<?php
/** @var Community $community */
/** @var Profile|null $profile */
/** @var array<int, string> $signatureErrors */
/** @var callable $renderPartial */
/** @var callable $e */
/** @var string|null $success */

use Fred\Domain\Auth\Profile;
use Fred\Domain\Community\Community;
use Fred\Infrastructure\View\ViewHelper;

$messageIdPrefix = 'signature-settings';
$messageAria = ViewHelper::buildMessageAria(
    !empty($signatureErrors),
    !empty($success ?? ''),
    $messageIdPrefix,
);
?>
<?= $renderPartial('partials/form_section_header.php', [
    'title' => 'Edit signature',
    'errors' => $signatureErrors,
    'success' => $success ?? null,
    'idPrefix' => $messageIdPrefix,
    'infoText' => null,
    'renderPartial' => $renderPartial,
]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/signature" novalidate>
                <?= $renderPartial('partials/csrf.php', ['renderPartial' => $renderPartial]) ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120"><label for="signature">Signature</label></td>
                        <td><textarea id="signature" name="signature" rows="3"<?= $messageAria ?>><?= $e($profile ? $profile->signatureRaw : '') ?></textarea></td>
                    </tr>
                </table>
                <button class="button" type="submit">Save signature</button>
            </form>
        </td>
    </tr>
</table>
