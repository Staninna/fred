<?php
/** @var Profile|null $profile */
/** @var Community $community */
/** @var array<int, string> $errors */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */

use Fred\Application\Auth\CurrentUser;
use Fred\Domain\Auth\Profile;
use Fred\Domain\Community\Community;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Edit signature</th>
    </tr>
    <tr>
        <td>
            <div class="info-line">Use BBCode to format your signature. Keep it short and friendly.</div>
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/signature" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="signature">Signature</label></td>
                        <td>
                            <?= $renderPartial('partials/bbcode_toolbar.php', ['targetId' => 'signature']) ?>
                            <textarea id="signature" name="signature" rows="5"><?= $e($profile?->signatureRaw ?? '') ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="small" colspan="2">Supports [b], [i], [code], [quote], [url], and &gt;&gt;post links.</td>
                    </tr>
                </table>
                <button class="button" type="submit">Save signature</button>
                <a class="button" href="/c/<?= $e($community->slug) ?>/u/<?= $e($currentUser?->username ?? '') ?>">Back to profile</a>
            </form>
        </td>
    </tr>
</table>
