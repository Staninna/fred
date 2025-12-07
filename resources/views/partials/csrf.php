<?php /** @var callable(string, int): string $e */ ?>
<input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
