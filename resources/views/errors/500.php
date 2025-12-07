<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Server error</th>
    </tr>
    <tr>
        <td>We hit an unexpected issue while handling your request. Please retry or head back to safety.</td>
    </tr>
    <?php if (!empty($errorMessage ?? '') && ($environment ?? '') !== 'production'): ?>
        <tr>
            <td class="table-heading">Message</td>
        </tr>
        <tr>
            <td><code><?= $e($errorMessage) ?></code></td>
        </tr>
    <?php endif; ?>
    <?php if (!empty($errorTrace ?? '') && ($environment ?? '') !== 'production'): ?>
        <tr>
            <td class="table-heading">Trace</td>
        </tr>
        <tr>
            <td><pre style="white-space: pre-wrap; font-size: 12px;"><?= $e($errorTrace) ?></pre></td>
        </tr>
    <?php endif; ?>
    <tr>
        <td><a class="button" href="/">Back to home</a></td>
    </tr>
</table>
