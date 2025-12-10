<?php
/** @var callable $e */
/** @var callable $renderPartial */
/** @var string $action */
/** @var string $submitLabel */
/** @var string $textareaId */
/** @var string $textareaName */
/** @var string $textareaLabel */
/** @var string $bodyValue */
/** @var bool $includeAttachment */
/** @var int $page */
/** @var string|null $messagesDescribedBy */
/** @var string|null $mentionEndpoint */
?>

<?php
$messagesDescribedBy = trim($messagesDescribedBy ?? '');
$messageAria = $messagesDescribedBy === '' ? '' : ' aria-describedby="' . $e($messagesDescribedBy) . '"';
?>

<form method="post" action="<?= $e($action) ?>" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
    <input type="hidden" name="page" value="<?= $page ?? 1 ?>">
    <table class="form-table" cellpadding="0" cellspacing="0">
        <tr>
            <td width="120"><label for="<?= $e($textareaId) ?>"><?= $e($textareaLabel) ?></label></td>
            <td>
                <?= $renderPartial('partials/bbcode_toolbar.php', ['targetId' => $textareaId]) ?>
                <?php $mentionEndpoint = trim($mentionEndpoint ?? ''); ?>
                <textarea id="<?= $e($textareaId) ?>" name="<?= $e($textareaName) ?>" rows="4" required<?= $messageAria ?> <?= $mentionEndpoint !== '' ? 'data-mention-endpoint="' . $e($mentionEndpoint) . '"' : '' ?>><?= $e($bodyValue) ?></textarea>
            </td>
        </tr>
        <?php if (!empty($includeAttachment)): ?>
            <tr>
                <td><label for="attachment">Attachment</label></td>
                <td><input id="attachment" name="attachment" type="file" accept=".png,.jpg,.jpeg,.gif,.webp"<?= $messageAria ?>></td>
            </tr>
        <?php endif; ?>
    </table>
    <button class="button" type="submit"><?= $e($submitLabel) ?></button>
</form>
