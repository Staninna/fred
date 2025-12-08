<?php /** @var callable(string, ?int=): string $e */ ?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Not found</th>
    </tr>
    <tr>
        <td>The route you tried (<?= $e($path ?? '') ?>) is not mapped. Head back to the home page to keep exploring.</td>
    </tr>
    <?php if (!empty($debugContext)): ?>
        <tr>
            <td style="color: #666; font-family: monospace; white-space: pre-wrap; background: #f5f5f5; padding: 10px;"><?= $e($debugContext, ENT_QUOTES) ?></td>
        </tr>
    <?php endif; ?>
    <tr>
        <td><a class="button" href="/">Return home</a></td>
    </tr>
</table>
