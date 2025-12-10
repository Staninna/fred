<?php
/** @var string $title */
/** @var int|null $colspan */
/** @var callable $e */
?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th<?php if (($colspan ?? 1) > 1): ?> colspan="<?= $colspan ?>"<?php endif; ?>><?= $e($title) ?></th>
    </tr>
