<?php
/** @var array<int, string> $errors */
/** @var callable $e */
/** @var string|null $success */
/** @var string|null $idPrefix */

$idPrefix = $idPrefix === null || $idPrefix === '' ? 'form' : $idPrefix;
$errorId = $idPrefix . '-errors';
$successId = $idPrefix . '-success';

$hasErrors = !empty($errors);
$hasSuccess = isset($success) && trim((string) $success) !== '';

if (!$hasErrors && !$hasSuccess) {
    return '';
}
?>

<?php if ($hasSuccess): ?>
    <div id="<?= $e($successId) ?>" class="notice" role="status" aria-live="polite">
        <?= $e((string) $success) ?>
    </div>
<?php endif; ?>

<?php if ($hasErrors): ?>
    <div id="<?= $e($errorId) ?>" class="alert" role="alert" aria-live="assertive">
        <strong>There were issues:</strong>
        <ul class="nav-list">
            <?php foreach ($errors as $error): ?>
                <li><?= $e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
