<?php
/** @var array<int, array{ id:int, user_id:int, username:string, reason:string, expires_at:int|null, created_at:int }> $bans */
/** @var array<int, string> $errors */
/** @var array<string, string> $old */
/** @var callable(string, int): string $e */
/** @var callable(string, array): string $renderPartial */
/** @var array<int, string> $usernames */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="3">Create ban</th>
    </tr>
    <tr>
        <td colspan="3">
            <?= $renderPartial('partials/errors.php', ['errors' => $errors ?? []]) ?>
            <form method="post" action="<?= $e($_SERVER['REQUEST_URI'] ?? '') ?>" novalidate>
                <?= $renderPartial('partials/csrf.php') ?>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="username">Username</label></td>
                        <td>
                            <?php
                            $banUserOptions = array_map(static fn ($username) => ['value' => $username, 'label' => $username], $usernames);
                            echo $renderPartial('partials/select.php', [
                                'name' => 'username',
                                'id' => 'username',
                                'placeholder' => 'Select user',
                                'options' => $banUserOptions,
                                'selected' => $old['username'] ?? '',
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="reason">Reason</label></td>
                        <td><input id="reason" name="reason" type="text" value="<?= $e($old['reason'] ?? '') ?>" required></td>
                    </tr>
                    <?php
                    $expiresValue = '';
                    if (isset($old['expires_at']) && trim((string) $old['expires_at']) !== '') {
                        $parsed = strtotime((string) $old['expires_at']);
                        if ($parsed !== false) {
                            $expiresValue = date('Y-m-d\TH:i', $parsed);
                        }
                    }
                    ?>
                    <tr>
                        <td><label for="expires_at">Expires at</label></td>
                        <td>
                            <input
                                id="expires_at"
                                name="expires_at"
                                type="datetime-local"
                                value="<?= $e($expiresValue) ?>"
                                placeholder="Pick date/time or leave blank for permanent"
                            >
                        </td>
                    </tr>
                </table>
                <button class="button" type="submit">Ban user</button>
            </form>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>ID</th>
        <th>User</th>
        <th>Reason</th>
        <th>Expires</th>
        <th>Created</th>
        <th>Actions</th>
    </tr>
    <?php if ($bans === []): ?>
        <tr><td colspan="6" class="muted">No bans.</td></tr>
    <?php else: ?>
        <?php foreach ($bans as $ban): ?>
            <tr>
                <td><?= $ban['id'] ?></td>
                <td><?= $e($ban['username']) ?> (ID: <?= $ban['user_id'] ?>)</td>
                <td><?= $e($ban['reason']) ?></td>
                <td><?= $ban['expires_at'] !== null ? date('Y-m-d H:i', (int) $ban['expires_at']) : 'Permanent' ?></td>
                <td><?= date('Y-m-d H:i', (int) $ban['created_at']) ?></td>
                <td>
                    <form class="inline-form" method="post" action="/c/<?= $e($_GET['community'] ?? '') ?>/admin/bans/<?= $ban['id'] ?>/delete">
                        <?= $renderPartial('partials/csrf.php') ?>
                        <button class="button" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
