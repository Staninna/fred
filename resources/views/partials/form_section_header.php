<?php
/** @var array<int, string> $errors */
/** @var callable $renderPartial */
/** @var callable $e */
/** @var string $title */
/** @var string|null $infoText */
/** @var string|null $success */
/** @var string|null $idPrefix */
?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th><?= $e($title) ?></th>
    </tr>
    <tr>
        <td>
            <?php if (!empty($infoText)): ?>
                <div class="info-line"><?= $e($infoText) ?></div>
            <?php endif; ?>
            <?= $renderPartial('partials/errors.php', [
                'errors' => $errors,
                'success' => $success ?? null,
                'idPrefix' => $idPrefix ?? 'form-section',
                'renderPartial' => $renderPartial,
            ]) ?>
