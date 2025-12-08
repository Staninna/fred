<?php
/** @var string $environment */
/** @var string $baseUrl */
/** @var callable(string, ?int=): string $e */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Fred forum engine</th>
    </tr>
    <tr>
        <td colspan="2">
            A compact, nostalgic-first forum stack. Routing, config, migrations and sessions are alive; next up is wiring real data.
        </td>
    </tr>
    <tr>
        <td class="table-heading">Environment</td>
        <td><?= $e($environment) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Base URL</td>
        <td><?= $e($baseUrl) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Status</td>
        <td>Routing · SQLite sessions · Migrations ready</td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Layout shell</th>
    </tr>
    <tr>
        <td colspan="2">
            <ul class="nav-list">
                <li>Table-based layout with a left navigation column and content pane.</li>
                <li>Underlined links, flat colors, and classic blue headers.</li>
                <li>Navigation partial lists communities and boards.</li>
            </ul>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Files</td>
        <td><code>resources/views/layout/default.php</code> · <code>resources/views/partials/nav.php</code></td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Next up</th>
    </tr>
    <tr>
        <td>
            <ul class="nav-list">
                <li>Authentication and roles.</li>
                <li>BBCode rendering and signatures.</li>
                <li>Moderation and structure tools.</li>
            </ul>
        </td>
    </tr>
</table>
