<?php
/** @var Community $community */
/** @var array<int, Board> $boards */
/** @var array<int, array<string, mixed>> $threads */
/** @var array<int, array<string, mixed>> $posts */
/** @var array<int, string> $errors */
/** @var callable $renderPartial */
/** @var callable $e */
/** @var string $query */
/** @var Board|null $boardFilter */
/** @var User|null $userFilter */
/** @var array<int, string> $usernames */
/** @var Community $community */
/** @var string|null $success */

use Fred\Domain\Auth\User;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;

$messageIdPrefix = 'search-form';
$messageTargets = [];

if (!empty($errors)) {
    $messageTargets[] = $messageIdPrefix . '-errors';
}

if (!empty($success ?? '')) {
    $messageTargets[] = $messageIdPrefix . '-success';
}
$messageAria = $messageTargets === [] ? '' : ' aria-describedby="' . $e(implode(' ', $messageTargets)) . '"';
?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Search in <?= $e($community->name) ?></th>
    </tr>
    <tr>
        <td colspan="2">
            <?= $renderPartial('partials/errors.php', [
                'errors' => $errors,
                'success' => $success ?? null,
                'idPrefix' => $messageIdPrefix,
            ]) ?>
            <form method="get" action="/c/<?= $e($community->slug) ?>/search" novalidate>
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="120"><label for="q">Query</label></td>
                        <td><input id="q" name="q" type="text" value="<?= $e($query) ?>" required<?= $messageAria ?>></td>
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
    'selected' => $boardFilter ? $boardFilter->slug : '',
    'ariaDescribedBy' => trim(implode(' ', $messageTargets)),
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
    'selected' => $userFilter ? $userFilter->username : '',
    'ariaDescribedBy' => trim(implode(' ', $messageTargets)),
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
