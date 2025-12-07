<?php /** @var callable(string, int): string $e */ ?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Not found</th>
    </tr>
    <tr>
        <td>The route you tried (<?= $e($path ?? '') ?>) is not mapped. Head back to the home page to keep exploring.</td>
    </tr>
    <tr>
        <td><a class="button" href="/">Return home</a></td>
    </tr>
</table>
