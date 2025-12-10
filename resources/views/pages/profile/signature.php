<?php
/** @var Profile $profile */
/** @var Community $community */
/** @var array<int, string> $errors */
/** @var CurrentUser $currentUser */
/** @var callable $renderPartial */
/** @var callable $e */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Auth\Profile;
use Fred\Domain\Community\Community;

?>

<?= $renderPartial('partials/form_section_header.php', [
    'title' => 'Edit signature',
    'errors' => $errors,
    'infoText' => 'Use BBCode to format your signature. Keep it short and friendly.',
]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/signature" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="signature">Signature</label></td>
                        <td>
                            <?= $renderPartial('partials/bbcode_toolbar.php', ['targetId' => 'signature']) ?>
                            <textarea id="signature" name="signature" rows="5"><?= $e($profile->signatureRaw) ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="small" colspan="2">Supports [b], [i], [code], [quote], [url], and &gt;&gt;post links.</td>
                    </tr>
                </table>
                <button class="button" type="submit">Save signature</button>
                <a class="button" href="/c/<?= $e($community->slug) ?>/u/<?= $e($currentUser->username) ?>">Back to profile</a>
            </form>
        </td>
    </tr>
</table>
