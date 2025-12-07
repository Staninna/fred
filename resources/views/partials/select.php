<?php
/** @var string $name */
/** @var array<int, array{value:string, label:string}> $options */
/** @var string|null $selected */
/** @var string|null $placeholder */
/** @var string|null $id */
/** @var callable(string, int): string $e */

$id = $id ?? $name;
?>

<select name="<?= $e($name) ?>" id="<?= $e($id) ?>">
    <?php if (!empty($placeholder ?? '')): ?>
        <option value=""><?= $e($placeholder) ?></option>
    <?php endif; ?>
    <?php foreach ($options as $option): ?>
        <option value="<?= $e($option['value']) ?>"<?= ($selected ?? '') === $option['value'] ? ' selected' : '' ?>>
            <?= $e($option['label']) ?>
        </option>
    <?php endforeach; ?>
</select>
