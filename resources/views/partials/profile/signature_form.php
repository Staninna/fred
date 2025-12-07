<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var array<int, string> $signatureErrors */
/** @var callable(string, int): string $e */
?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Edit signature</th>
    </tr>
    <tr>
        <td>
            <?= $renderPartial('partials/errors.php', ['errors' => $signatureErrors ?? []]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/signature" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120"><label for="signature">Signature</label></td>
                        <td><textarea id="signature" name="signature" rows="3"><?= $e($profile?->signatureRaw ?? '') ?></textarea></td>
                    </tr>
                </table>
                <button class="button" type="submit">Save signature</button>
            </form>
        </td>
    </tr>
</table>
