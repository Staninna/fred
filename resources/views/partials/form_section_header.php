<?php
/** @var array<int, string> $errors */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */
/** @var string $title */
/** @var string|null $infoText */
/** @var string|null $success */
/** @var string|null $idPrefix */
?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th><?= $e($title ?? 'Form') ?></th>
    </tr>
    <tr>
        <td>
            <?php if (!empty($infoText)): ?>
                <div class="info-line"><?= $e($infoText) ?></div>
            <?php endif; ?>
            <?= $renderPartial('partials/errors.php', [
                'errors' => $errors ?? [],
                'success' => $success ?? null,
                'idPrefix' => $idPrefix ?? 'form-section',
            ]) ?>
