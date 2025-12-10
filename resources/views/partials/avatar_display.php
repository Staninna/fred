<?php
/** @var Profile|null $profile */
/** @var callable $e */

use Fred\Domain\Auth\Profile;

?>
<?php if (!empty($profile?->avatarPath ?? '')): ?>
    <img src="/uploads/<?= $e($profile->avatarPath) ?>" alt="Avatar" style="max-width: 120px; max-height: 120px;">
<?php else: ?>
    <span class="muted">No avatar set.</span>
<?php endif; ?>
