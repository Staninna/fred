<?php
/** @var string $name */
/** @var array<int, array{value:string, label:string}> $options */
/** @var string|null $selected */
/** @var string|null $placeholder */
/** @var string|null $id */
/** @var callable(string, int): string $e */
/** @var string|null $class */
/** @var bool|null $required */

$id = $id ?? $name;
$classAttr = isset($class) && $class !== '' ? ' class="' . $e($class) . '"' : '';
$requiredAttr = !empty($required) ? ' required' : '';
?>

<select name="<?= $e($name) ?>" id="<?= $e($id) ?>"<?= $classAttr ?><?= $requiredAttr ?>>
    <?php if (!empty($placeholder ?? '')): ?>
        <option value=""><?= $e($placeholder) ?></option>
    <?php endif; ?>
    <?php foreach ($options as $option): ?>
        <option value="<?= $e($option['value']) ?>"<?= ($selected ?? '') === $option['value'] ? ' selected' : '' ?>>
            <?= $e($option['label']) ?>
        </option>
    <?php endforeach; ?>
</select>
