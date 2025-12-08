<?php
/** @var Community $community */
/** @var array<int, User> $users */
/** @var string $query */
/** @var string $role */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, ?int=): string $e */

use Fred\Domain\Auth\User;
use Fred\Domain\Community\Community;

?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Users</th>
    </tr>
    <tr>
        <td class="table-heading" width="160">Filter</td>
        <td>
            <form class="inline-form" method="get" action="/c/<?= $e($community->slug) ?>/admin/users">
                <input type="text" name="q" value="<?= $e($query) ?>" placeholder="Search username or display name">
                <select name="role">
                    <?php
                    $options = [
                        '' => 'Any role',
                        'guest' => 'Guest',
                        'member' => 'Member',
                        'moderator' => 'Moderator',
                        'admin' => 'Admin',
                    ];

foreach ($options as $value => $label): ?>
                        <option value="<?= $e($value) ?>" <?= $value === $role ? 'selected' : '' ?>><?= $e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit">Search</button>
            </form>
        </td>
    </tr>
</table>

<?php if ($users === []): ?>
    <div class="notice">No users match this filter.</div>
<?php else: ?>
    <table class="section-table" cellpadding="0" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Username</th>
            <th>Display name</th>
            <th>Role</th>
            <th>Joined</th>
        </tr>
        <?php foreach ($users as $user): ?>
            <tr>
                <td>#<?= $user->id ?></td>
                <td><?= $e($user->username) ?></td>
                <td><?= $e($user->displayName) ?></td>
                <td><?= $e(ucfirst($user->roleSlug)) ?></td>
                <td><?= date('Y-m-d', $user->createdAt) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
