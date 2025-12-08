<?php
/** @var Profile|null $profile */
/** @var callable(string, ?int=): string $e */

use Fred\Domain\Auth\Profile;

?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Details</th>
    </tr>
    <tr>
        <td class="table-heading">Location</td>
        <td><?= $e($profile?->location ?? '') ?: 'Not set' ?></td>
    </tr>
    <tr>
        <td class="table-heading">Website</td>
        <td>
            <?php if (($profile?->website ?? '') === ''): ?>
                Not set
            <?php else: ?>
                <a href="<?= $e($profile->website) ?>" target="_blank" rel="noopener">
                    <?= $e($profile->website) ?>
                </a>
            <?php endif; ?>
        </td>
    </tr>
</table>
