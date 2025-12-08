<?php
/** @var Community $community */
/** @var array<int, Category> $categories */
/** @var array<int, array<int, Board>> $boardsByCategory */
/** @var array<int, string> $errors */
/** @var callable(string, array): string $renderPartial */
/** @var callable(string, ?int=): string $e */
/** @var array<int, array{user_id:int, username:string, assigned_at:int}> $moderators */
/** @var array<int, string> $usernames */
/** @var string|null $success */

use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;

?>

<?php $boardTotal = array_sum(array_map('count', $boardsByCategory)); ?>
<?php
$messageIdPrefix = 'community-structure';
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
        <th colspan="2">Manage structure: <?= $e($community->name) ?></th>
    </tr>
    <tr>
        <td class="table-heading">Description</td>
        <td><?= $e($community->description) ?></td>
    </tr>
    <tr>
        <td class="table-heading">Note</td>
        <td>Use the forms below to add or edit categories and boards. For community details and theme overrides, visit the Settings tab.</td>
    </tr>
</table>

<?= $renderPartial('partials/errors.php', [
    'errors' => $errors,
    'success' => $success ?? null,
    'idPrefix' => $messageIdPrefix,
]) ?>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Reorder categories</th>
    </tr>
    <tr>
        <td colspan="2">
            <?php if ($categories === []): ?>
                <div class="muted">No categories to reorder.</div>
            <?php else: ?>
                <form method="post" action="/c/<?= $e($community->slug) ?>/admin/categories/reorder">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                    <table class="form-table" cellpadding="0" cellspacing="0">
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td width="200"><?= $e($category->name) ?></td>
                                <td>
                                    <input name="category_positions[<?= $category->id ?>]" type="number" value="<?= $category->position ?>" style="width:90px;"<?= $messageAria ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                    <button class="button" type="submit">Save category order</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Reorder boards</th>
    </tr>
    <tr>
        <td colspan="2">
            <?php if ($boardTotal === 0): ?>
                <div class="muted">No boards to reorder.</div>
            <?php else: ?>
                <form method="post" action="/c/<?= $e($community->slug) ?>/admin/boards/reorder">
                    <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                    <table class="form-table" cellpadding="0" cellspacing="0">
                        <?php foreach ($categories as $category): ?>
                            <?php $categoryBoards = $boardsByCategory[$category->id] ?? []; ?>
                            <?php if ($categoryBoards === []): continue; endif; ?>
                            <tr>
                                <td colspan="2" class="table-heading"><?= $e($category->name) ?></td>
                            </tr>
                            <?php foreach ($categoryBoards as $board): ?>
                                <tr>
                                    <td width="200"><?= $e($board->name) ?></td>
                                    <td><input name="board_positions[<?= $board->id ?>]" type="number" value="<?= $board->position ?>" style="width:90px;"<?= $messageAria ?>></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </table>
                    <button class="button" type="submit">Save board order</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Moderators</th>
    </tr>
    <tr>
        <td class="table-heading" width="200">Current moderators</td>
        <td>
            <?php if ($moderators === []): ?>
                <div class="muted">No moderators assigned.</div>
            <?php else: ?>
                <ul class="nav-list">
                    <?php foreach ($moderators as $moderator): ?>
                        <li>
                            <?= $e($moderator['username']) ?>
                            <span class="small muted">(since <?= date('Y-m-d', (int) $moderator['assigned_at']) ?>)</span>
                            <form class="inline-form" method="post" action="/c/<?= $e($community->slug) ?>/admin/moderators/<?= $moderator['user_id'] ?>/delete">
                                <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                                <button class="button" type="submit">Remove</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </td>
    </tr>
    <tr>
        <td class="table-heading">Add moderator</td>
        <td>
            <form method="post" action="/c/<?= $e($community->slug) ?>/admin/moderators" novalidate>
                <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                <label for="mod_username">Username</label>
                <?php
                $modUserOptions = array_map(static fn ($username) => ['value' => $username, 'label' => $username], $usernames);
                echo $renderPartial('partials/select.php', [
                    'name' => 'username',
                    'id' => 'mod_username',
                    'placeholder' => 'Select user',
                    'options' => $modUserOptions,
                    'selected' => '',
                    'ariaDescribedBy' => trim(implode(' ', $messageTargets)),
                ]);
                ?>
                <button class="button" type="submit">Assign</button>
                <div class="small muted">User will be given the Moderator role if they do not already have it.</div>
            </form>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Add category</th>
    </tr>
    <tr>
        <td>
            <form method="post" action="/c/<?= $e($community->slug) ?>/admin/categories" novalidate>
                <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                <table class="form-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="140"><label for="category_name">Name</label></td>
                        <td><input id="category_name" name="name" type="text" required></td>
                    </tr>
                    <tr>
                        <td><label for="category_position">Position</label></td>
                        <td><input id="category_position" name="position" type="number" value="0"></td>
                    </tr>
                </table>
                <button class="button" type="submit">Add category</button>
            </form>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Existing categories</th>
    </tr>
    <?php if ($categories === []): ?>
        <tr>
            <td class="muted">No categories yet.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($categories as $category): ?>
            <tr>
                <td>
                    <form method="post" action="/c/<?= $e($community->slug) ?>/admin/categories/<?= $category->id ?>" novalidate>
                        <input type="hidden" name="_token" value="<?= $e($csrfToken ?? '') ?>">
                        <table class="form-table" cellpadding="0" cellspacing="0">
                            <tr>
                                <td width="140">Name</td>
                                <td><input name="name" type="text" value="<?= $e($category->name) ?>" required></td>
                            </tr>
                            <tr>
                                <td>Position</td>
                                <td><input name="position" type="number" value="<?= $category->position ?>"></td>
                            </tr>
                        </table>
                        <button class="button" type="submit">Update</button>
                        <button class="button" type="submit" formaction="/c/<?= $e($community->slug) ?>/admin/categories/<?= $category->id ?>/delete">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th>Add board</th>
    </tr>
    <tr>
        <td>
            <?php if ($categories === []): ?>
                <div class="muted">Create a category first to add boards.</div>
            <?php else: ?>
                <?= $renderPartial('partials/admin/board_form.php', [
                    'action' => '/c/' . $community->slug . '/admin/boards',
                    'submitLabel' => 'Add board',
                    'board' => null,
                    'categories' => $categories,
                    'includeCategorySelect' => true,
                    'deleteAction' => null,
                ]) ?>
            <?php endif; ?>
        </td>
    </tr>
</table>

<table class="section-table" cellpadding="0" cellspacing="0">
    <tr>
        <th colspan="2">Boards</th>
    </tr>
    <?php if ($boardTotal === 0): ?>
        <tr>
            <td colspan="2" class="muted">No boards yet.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($categories as $category): ?>
            <?php $categoryBoards = $boardsByCategory[$category->id] ?? []; ?>
            <?php if ($categoryBoards === []): continue; endif; ?>
            <tr>
                <td colspan="2" class="table-heading">Category: <?= $e($category->name) ?></td>
            </tr>
            <?php foreach ($categoryBoards as $board): ?>
                <tr>
                    <td colspan="2">
                        <?= $renderPartial('partials/admin/board_form.php', [
                            'action' => '/c/' . $community->slug . '/admin/boards/' . $board->id,
                            'submitLabel' => 'Update',
                            'board' => $board,
                            'categories' => $categories,
                            'includeCategorySelect' => false,
                            'deleteAction' => '/c/' . $community->slug . '/admin/boards/' . $board->id . '/delete',
                        ]) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</table>
