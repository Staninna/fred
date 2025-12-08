<?php
/** @var \Fred\Domain\Auth\Profile|null $profile */
/** @var callable(string, int): string $e */
?>
<?php if (!empty($profile?->avatarPath ?? '')): ?>
    <img src="/uploads/<?= $e($profile->avatarPath) ?>" alt="Avatar" style="max-width: 120px; max-height: 120px;">
<?php else: ?>
    <span class="muted">No avatar set.</span>
<?php endif; ?>
