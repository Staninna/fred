<?php
/** @var Profile|null $profile */
/** @var Community $community */
/** @var array<int, string> $errors */
/** @var CurrentUser|null $currentUser */
/** @var callable(string, array): string $renderPartial */

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
            <form method="post" action="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/settings/signature" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="signature">Signature</label></td>
                        <td>
                            <?= $renderPartial('partials/bbcode_toolbar.php', ['targetId' => 'signature']) ?>
                            <textarea id="signature" name="signature" rows="5"><?= htmlspecialchars($profile?->signatureRaw ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <td class="small" colspan="2">Supports [b], [i], [code], [quote], [url], and &gt;&gt;post links.</td>
                    </tr>
                </table>
                <button class="button" type="submit">Save signature</button>
                <a class="button" href="/c/<?= htmlspecialchars($community->slug, ENT_QUOTES, 'UTF-8') ?>/u/<?= htmlspecialchars($currentUser?->username ?? '', ENT_QUOTES, 'UTF-8') ?>">Back to profile</a>
            </form>
        </td>
    </tr>
</table>
