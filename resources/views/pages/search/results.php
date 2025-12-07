<?php
/** @var \Fred\Domain\Community\Community $community */
/** @var array<int, \Fred\Domain\Community\Board> $boards */
/** @var array<int, array<string, mixed>> $threads */
/** @var array<int, array<string, mixed>> $posts */
/** @var array<int, string> $errors */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, int): string $e */
/** @var string $query */
/** @var \Fred\Domain\Community\Board|null $boardFilter */
/** @var \Fred\Domain\Auth\User|null $userFilter */
/** @var array<int, string> $usernames */
/** @var \Fred\Domain\Community\Community $community */
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Search in <?= $e($community->name) ?></th>
    </tr>
    <tr>
        <td colspan="2">
            <?= $renderPartial('partials/errors.php', ['errors' => $errors]) ?>
            <form method="get" action="/c/<?= $e($community->slug) ?>/search" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120"><label for="q">Query</label></td>
                        <td><input id="q" name="q" type="text" value="<?= $e($query ?? '') ?>" required></td>
                    </tr>
                    <tr>
                        <td><label for="board">Board</label></td>
                        <td>
                            <?php
                            $boardOptions = array_map(static fn ($board) => ['value' => $board->slug, 'label' => $board->name], $boards);
                            echo $renderPartial('partials/select.php', [
                                'name' => 'board',
                                'id' => 'board',
                                'placeholder' => 'All boards',
                                'options' => $boardOptions,
                                'selected' => $boardFilter?->slug ?? '',
                            ]);
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td><label for="user">User</label></td>
                        <td>
                            <?php
                            $userOptions = array_map(static fn ($username) => ['value' => $username, 'label' => $username], $usernames);
                            echo $renderPartial('partials/select.php', [
                                'name' => 'user',
                                'id' => 'user',
                                'placeholder' => 'All users',
                                'options' => $userOptions,
                                'selected' => $userFilter?->username ?? '',
                            ]);
                            ?>
                        </td>
                    </tr>
                </table>
                <button class="button" type="submit">Search</button>
            </form>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Thread matches</th>
    </tr>
    <?= $renderPartial('partials/search/result_list.php', [
        'items' => $threads,
        'emptyMessage' => 'No thread matches.',
        'type' => 'thread',
        'community' => $community,
    ]) ?>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Post matches</th>
    </tr>
    <?= $renderPartial('partials/search/result_list.php', [
        'items' => $posts,
        'emptyMessage' => 'No post matches.',
        'type' => 'post',
        'community' => $community,
    ]) ?>
</table>
