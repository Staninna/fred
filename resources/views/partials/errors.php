<?php
/** @var array<int, string> $errors */

if (empty($errors)) {
    return '';
}
?>

<div class="alert">
    <ul class="list">
        <?php foreach ($errors as $error): ?>
            <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
    </ul>
</div>
