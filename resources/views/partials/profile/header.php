<?php
/** @var User $user */
/** @var Profile|null $profile */
/** @var Community $community */
/** @var callable(string, ?int=): string $e */
/** @var callable(string, array): string $renderPartial */

use Fred\Domain\Auth\Profile;
use Fred\Domain\Auth\User;
use Fred\Domain\Community\Community;

?>
<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Profile: <?= $e($user->displayName) ?> (@<?= $e($user->username) ?>)</th>
    </tr>
    <tr>
        <td class="table-heading">Community</td>
        <td><?= $e($community->name) ?> · User ID: <?= $user->id ?></td>
    </tr>
    <tr>
        <td class="table-heading">Role</td>
        <td><?= $e($user->roleName) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Avatar</td>
        <td>
            <?= $renderPartial('partials/avatar_display.php', ['profile' => $profile, 'renderPartial' => $renderPartial]) ?>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Joined</td>
        <td><?= date('Y-m-d', $user->createdAt) ?></td>
    </tr>
</table>
