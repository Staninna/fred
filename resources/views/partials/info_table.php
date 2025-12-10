<?php
/** @var string $title */
/** @var array<string, string> $fields */
/** @var callable $e */
?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2"><?= $e($title) ?></th>
    </tr>
    <?php foreach ($fields as $label => $value): ?>
        <tr>
            <td class="table-heading"><?= $e($label) ?></td>
            <td><?= $value ?></td>
        </tr>
    <?php endforeach; ?>
