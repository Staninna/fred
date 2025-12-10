<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Community;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityModeratorRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\ReportRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

use function preg_replace;
use function strlen;
use function strtolower;
use function trim;

final readonly class AdminController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private CommunityRepository $communities,
        private CommunityModeratorRepository $communityModerators,
        private UserRepository $users,
        private RoleRepository $roles,
        private ReportRepository $reports,
    ) {
    }

    /** @param string[] $errors */
    public function structure(Request $request, array $errors = []): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::structure');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $structure = $this->structureForCommunity($community);
        $moderators = $this->communityModerators->listByCommunity($community->id);
        $usernames = $this->users->listUsernames();

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Admin · ' . $community->name)
            ->set('community', $community)
            ->set('categories', $structure['categories'])
            ->set('boardsByCategory', $structure['boardsByCategory'])
            ->set('moderators', $moderators)
            ->set('usernames', $usernames)
            ->set('errors', $errors)
            ->set('currentCommunity', $community)
            ->set('navSections', $this->adminNav($community, 'structure'))
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view(
            $this->view,
            'pages/community/admin/structure.php',
            $ctx,
            status: $errors === [] ? 200 : 422,
        );
    }

    /**
     * @param string[] $errors
     * @param array<string, mixed> $old
     */
    public function settings(Request $request, array $errors = [], array $old = []): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::settings');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Settings · ' . $community->name)
            ->set('community', $community)
            ->set('errors', $errors)
            ->set('old', $old)
            ->set('saved', isset($request->query['saved']))
            ->set('currentCommunity', $community)
            ->set('navSections', $this->adminNav($community, 'settings'))
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view(
            $this->view,
            'pages/community/admin/settings.php',
            $ctx,
            status: $errors === [] ? 200 : 422,
        );
    }

    public function updateSettings(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::updateSettings');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $description = trim((string) ($request->body['description'] ?? ''));
        $customCss = trim((string) ($request->body['custom_css'] ?? ''));

        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        if (strlen($customCss) > 8000) {
            $errors[] = 'Community CSS is too long (max 8000 characters).';
        }

        if ($errors !== []) {
            return $this->settings($request, $errors, [
                'name' => $name,
                'description' => $description,
                'custom_css' => $customCss,
            ]);
        }

        $this->communities->update(
            id: $community->id,
            name: $name,
            description: $description,
            customCss: $customCss !== '' ? $customCss : null,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/admin/settings?saved=1');
    }

    public function createCategory(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::createCategory');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);

        if ($name === '') {
            return $this->structure($request, ['Category name is required.']);
        }

        $this->categories->create($community->id, $name, $position, time());

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function updateCategory(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::updateCategory');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $categoryId = (int) ($request->params['category'] ?? 0);
        $category = $this->categories->findById($categoryId);

        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound($request, 'Category not found or mismatch in AdminController::updateCategory: ' . $categoryId);
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);

        if ($name === '') {
            return $this->structure($request, ['Category name is required.']);
        }

        $this->categories->update($category->id, $name, $position, time());

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function reorderCategories(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::reorderCategories');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $positions = $request->body['category_positions'] ?? [];
        $categories = $this->categories->listByCommunityId($community->id);
        $now = time();

        foreach ($categories as $category) {
            $newPosition = isset($positions[$category->id]) ? (int) $positions[$category->id] : $category->position;
            $this->categories->update($category->id, $category->name, $newPosition, $now);
        }

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function deleteCategory(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::deleteCategory');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $categoryId = (int) ($request->params['category'] ?? 0);
        $category = $this->categories->findById($categoryId);

        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound($request, 'Category not found or mismatch in AdminController::deleteCategory: ' . $categoryId);
        }

        $this->categories->delete($category->id);

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function reorderBoards(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::reorderBoards');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $positions = $request->body['board_positions'] ?? [];
        $boards = $this->boards->listByCommunityId($community->id);
        $now = time();

        foreach ($boards as $board) {
            $newPosition = isset($positions[$board->id]) ? (int) $positions[$board->id] : $board->position;
            $this->boards->update(
                id: $board->id,
                slug: $board->slug,
                name: $board->name,
                description: $board->description,
                position: $newPosition,
                isLocked: $board->isLocked,
                customCss: $board->customCss,
                timestamp: $now,
            );
        }

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function createBoard(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::createBoard');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $categoryId = (int) ($request->body['category_id'] ?? 0);
        $category = $this->categories->findById($categoryId);

        if ($category === null || $category->communityId !== $community->id) {
            return $this->structure($request, ['Invalid category selected.']);
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $slugInput = trim((string) ($request->body['slug'] ?? ''));
        $slug = $slugInput === '' ? $this->slugify($name) : $this->slugify($slugInput);
        $description = trim((string) ($request->body['description'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);
        $isLocked = isset($request->body['is_locked']);
        $customCss = trim((string) ($request->body['custom_css'] ?? ''));

        if (strlen($customCss) > 25000) {
            return $this->structure($request, ['Board CSS is too long (max 25000 characters).']);
        }

        if ($name === '') {
            return $this->structure($request, ['Board name is required.']);
        }

        if ($slug === '') {
            return $this->structure($request, ['Board slug is required.']);
        }

        if ($this->boards->findBySlug($community->id, $slug) !== null) {
            return $this->structure($request, ['Board slug is already in use.']);
        }

        $this->boards->create(
            communityId: $community->id,
            categoryId: $category->id,
            slug: $slug,
            name: $name,
            description: $description,
            position: $position,
            isLocked: $isLocked,
            customCss: $customCss !== '' ? $customCss : null,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function updateBoard(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::updateBoard');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $boardId = (int) ($request->params['board'] ?? 0);
        $board = $this->boards->findById($boardId);

        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound($request, 'Board not found or mismatch in AdminController::updateBoard: ' . $boardId);
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $slugInput = trim((string) ($request->body['slug'] ?? ''));
        $slug = $slugInput === '' ? $this->slugify($name) : $this->slugify($slugInput);
        $description = trim((string) ($request->body['description'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);
        $isLocked = isset($request->body['is_locked']);
        $customCss = trim((string) ($request->body['custom_css'] ?? ''));

        if (strlen($customCss) > 25000) {
            return $this->structure($request, ['Board CSS is too long (max 25000 characters).']);
        }

        if ($name === '') {
            return $this->structure($request, ['Board name is required.']);
        }

        if ($slug === '') {
            return $this->structure($request, ['Board slug is required.']);
        }

        $existing = $this->boards->findBySlug($community->id, $slug);

        if ($existing !== null && $existing->id !== $board->id) {
            return $this->structure($request, ['Board slug is already in use.']);
        }

        $this->boards->update(
            id: $board->id,
            slug: $slug,
            name: $name,
            description: $description,
            position: $position,
            isLocked: $isLocked,
            customCss: $customCss !== '' ? $customCss : null,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function deleteBoard(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::deleteBoard');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $boardId = (int) ($request->params['board'] ?? 0);
        $board = $this->boards->findById($boardId);

        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound($request, 'Board not found or mismatch in AdminController::deleteBoard: ' . $boardId);
        }

        $this->boards->delete($board->id);

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function updateCommunityCss(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::updateCommunityCss');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $css = trim((string) ($request->body['custom_css'] ?? ''));

        if (strlen($css) > 8000) {
            return $this->structure($request, ['Community CSS is too long (max 8000 characters).']);
        }

        $this->communities->update(
            id: $community->id,
            name: $community->name,
            description: $community->description,
            customCss: $css !== '' ? $css : null,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function addModerator(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::addModerator');
        }

        $currentUser = $this->auth->currentUser();

        if (!$this->permissions->canModerate($currentUser, $community->id)) {
            return Response::forbidden();
        }

        if ($currentUser->role !== 'admin') {
            return Response::forbidden();
        }

        $username = trim((string) ($request->body['username'] ?? ''));
        $errors = [];

        if ($username === '') {
            $errors[] = 'Username is required.';
        }

        $this->roles->ensureDefaultRoles();

        $user = $username !== '' ? $this->users->findByUsername($username) : null;

        if ($user === null) {
            $errors[] = 'User not found.';
        }

        if ($user !== null && $user->roleSlug === 'guest') {
            $memberRole = $this->roles->findBySlug('member');

            if ($memberRole !== null) {
                $this->users->updateRole($user->id, $memberRole->id);
                $user = $this->users->findById($user->id) ?? $user;
                $this->auth->flushPermissionCache($user->id);
            }
        }

        if ($user !== null && $user->roleSlug === 'member') {
            $modRole = $this->roles->findBySlug('moderator');

            if ($modRole === null) {
                $errors[] = 'Moderator role is missing.';
            } else {
                $this->users->updateRole($user->id, $modRole->id);
                $user = $this->users->findById($user->id) ?? $user;
            }
        }

        if ($errors === [] && $user !== null) {
            $this->communityModerators->assign($community->id, $user->id, time());
            $this->auth->flushPermissionCache($user->id);

            return Response::redirect('/c/' . $community->slug . '/admin/structure');
        }

        return $this->structure($request, $errors);
    }

    public function removeModerator(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::removeModerator');
        }

        $currentUser = $this->auth->currentUser();

        if (!$this->permissions->canModerate($currentUser, $community->id)) {
            return Response::forbidden();
        }

        if ($currentUser->role !== 'admin') {
            return Response::forbidden();
        }

        $userId = (int) ($request->params['user'] ?? 0);

        if ($userId > 0) {
            $this->communityModerators->remove($community->id, $userId);
            $this->auth->flushPermissionCache($userId);
        }

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function reports(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::reports');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $status = (string) ($request->query['status'] ?? 'open');
        $statusFilter = $status === 'all' ? null : $status;

        $reports = $this->reports->listWithContext($community->id, $statusFilter);

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Reports · ' . $community->name)
            ->set('community', $community)
            ->set('reports', $reports)
            ->set('status', $status)
            ->set('currentCommunity', $community)
            ->set('navSections', $this->adminNav($community, 'reports'))
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view($this->view, 'pages/community/admin/reports.php', $ctx);
    }

    public function resolveReport(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::resolveReport');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $reportId = (int) ($request->params['report'] ?? 0);
        $report = $this->reports->findById($reportId);

        if ($report === null || $report->communityId !== $community->id) {
            return $this->notFound($request, 'Report not found or mismatch in AdminController::resolveReport: ' . $reportId);
        }

        $this->reports->updateStatus($reportId, 'closed', time());

        return Response::redirect('/c/' . $community->slug . '/admin/reports');
    }

    public function users(Request $request): Response
    {
        $community = $request->attribute('community');

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in AdminController::users');
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return Response::forbidden();
        }

        $query = trim((string) ($request->query['q'] ?? ''));
        $role = trim((string) ($request->query['role'] ?? ''));

        $users = $this->users->search($query, $role !== '' ? $role : null, 100);

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Users · ' . $community->name)
            ->set('community', $community)
            ->set('users', $users)
            ->set('query', $query)
            ->set('role', $role)
            ->set('currentCommunity', $community)
            ->set('navSections', $this->adminNav($community, 'users'))
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view($this->view, 'pages/community/admin/users.php', $ctx);
    }

    /** @return array{categories: \Fred\Domain\Community\Category[], boardsByCategory: array<int, \Fred\Domain\Community\Board[]>} */
    private function structureForCommunity(Community $community): array
    {
        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);

        return [
            'categories' => $categories,
            'boardsByCategory' => $this->groupBoards($boards),
        ];
    }

    /**
     * @param Board[] $boards
     * @return array<int, array<int, Board>>
     */
    private function groupBoards(array $boards): array
    {
        $grouped = [];

        foreach ($boards as $board) {
            $grouped[$board->categoryId][] = $board;
        }

        return $grouped;
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? '';

        return trim((string) $slug, '-');
    }

    private function notFound(Request $request, ?string $context = null): Response
    {
        return Response::notFound(
            view: $this->view,
            config: $this->config,
            auth: $this->auth,
            request: $request,
            context: $context,
        );
    }

    /** @return array<int, array{title: string, items: array<int, array{label: string, href: string}>}> */
    private function adminNav(Community $community, string $active): array
    {
        $links = [
            ['key' => 'structure', 'label' => 'Structure', 'href' => '/c/' . $community->slug . '/admin/structure'],
            ['key' => 'settings', 'label' => 'Settings', 'href' => '/c/' . $community->slug . '/admin/settings'],
            ['key' => 'users', 'label' => 'Users', 'href' => '/c/' . $community->slug . '/admin/users'],
            ['key' => 'reports', 'label' => 'Reports', 'href' => '/c/' . $community->slug . '/admin/reports'],
            ['key' => 'view', 'label' => 'View community', 'href' => '/c/' . $community->slug],
        ];

        $items = array_map(static function (array $link) use ($active): array {
            $label = $link['label'] . ($link['key'] === $active ? ' *' : '');

            return ['label' => $label, 'href' => $link['href']];
        }, $links);

        return [
            [
                'title' => 'Admin',
                'items' => $items,
            ],
        ];
    }

}
