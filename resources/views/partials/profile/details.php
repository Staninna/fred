<?php
/** @var Profile|null $profile */
/** @var callable $e */

use Fred\Domain\Auth\Profile;

?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Details</th>
    </tr>
    <tr>
        <td class="table-heading">Location</td>
        <td><?= ($profile && $profile->location !== '') ? $e($profile->location) : 'Not set' ?></td>
    </tr>
    <tr>
        <td class="table-heading">Website</td>
        <td>
            <?php if ($profile && $profile->website !== ''): ?>
                <a href="<?= $e($profile->website) ?>" target="_blank" rel="noopener">
                    <?= $e($profile->website) ?>
                </a>
            <?php else: ?>
                Not set
            <?php endif; ?>
        </td>
    </tr>
</table>
