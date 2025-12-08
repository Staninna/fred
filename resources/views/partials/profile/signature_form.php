<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var array<int, string> $signatureErrors */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */
?>
<?= $renderPartial('partials/form_section_header.php', ['title' => 'Edit signature', 'errors' => $signatureErrors ?? [], 'infoText' => null]) ?>
            <form method="post" action="/c/<?= $e($community->slug) ?>/settings/signature" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
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
