<?php
/** @var array<int, string> $errors */
/** @var callable(string, int): string $e */

if (empty($errors)) {
    return '';
}
?>

<div class="alert">
    <strong>There were issues:</strong>
    <ul class="nav-list">
        <?php foreach ($errors as $error): ?>
            <li><?= $e($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
