<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityModeratorRepository;
use Fred\Infrastructure\Database\RoleRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewRenderer;

use function trim;

final readonly class AdminController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CommunityHelper $communityHelper,
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private CommunityModeratorRepository $communityModerators,
        private UserRepository $users,
        private RoleRepository $roles,
    ) {
    }

    public function structure(Request $request, array $errors = []): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $structure = $this->communityHelper->structureForCommunity($community);
        $moderators = $this->communityModerators->listByCommunity($community->id);
        $usernames = $this->users->listUsernames();

        $body = $this->view->render('pages/community/admin/structure.php', [
            'pageTitle' => 'Admin · ' . $community->name,
            'community' => $community,
            'categories' => $structure['categories'],
            'boardsByCategory' => $structure['boardsByCategory'],
            'moderators' => $moderators,
            'usernames' => $usernames,
            'errors' => $errors,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'currentCommunity' => $community,
            'activePath' => $request->path,
            'navSections' => [
                [
                    'title' => 'Admin',
                    'items' => [
                        ['label' => 'Structure', 'href' => '/c/' . $community->slug . '/admin/structure'],
                        ['label' => 'View community', 'href' => '/c/' . $community->slug],
                    ],
                ],
            ],
        ]);

        return new Response(
            status: $errors === [] ? 200 : 422,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    public function createCategory(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
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
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $categoryId = (int) ($request->params['category'] ?? 0);
        $category = $this->categories->findById($categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);

        if ($name === '') {
            return $this->structure($request, ['Category name is required.']);
        }

        $this->categories->update($category->id, $name, $position, time());

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function deleteCategory(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $categoryId = (int) ($request->params['category'] ?? 0);
        $category = $this->categories->findById($categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $this->categories->delete($category->id);

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function createBoard(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $categoryId = (int) ($request->body['category_id'] ?? 0);
        $category = $this->categories->findById($categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->structure($request, ['Invalid category selected.']);
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $slugInput = trim((string) ($request->body['slug'] ?? ''));
        $slug = $slugInput === '' ? $this->communityHelper->slugify($name) : $this->communityHelper->slugify($slugInput);
        $description = trim((string) ($request->body['description'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);
        $isLocked = isset($request->body['is_locked']);

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
            customCss: null,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function updateBoard(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $boardId = (int) ($request->params['board'] ?? 0);
        $board = $this->boards->findById($boardId);
        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $slugInput = trim((string) ($request->body['slug'] ?? ''));
        $slug = $slugInput === '' ? $this->communityHelper->slugify($name) : $this->communityHelper->slugify($slugInput);
        $description = trim((string) ($request->body['description'] ?? ''));
        $position = (int) ($request->body['position'] ?? 0);
        $isLocked = isset($request->body['is_locked']);

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
            customCss: $board->customCss,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function deleteBoard(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        if (!$this->permissions->canModerate($this->auth->currentUser(), $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $boardId = (int) ($request->params['board'] ?? 0);
        $board = $this->boards->findById($boardId);
        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound($request);
        }

        $this->boards->delete($board->id);

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    public function addModerator(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();
        if (!$this->permissions->canModerate($currentUser, $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        if ($currentUser->role !== 'admin') {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
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

            return Response::redirect('/c/' . $community->slug . '/admin/structure');
        }

        return $this->structure($request, $errors);
    }

    public function removeModerator(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();
        if (!$this->permissions->canModerate($currentUser, $community->id)) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        if ($currentUser->role !== 'admin') {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $userId = (int) ($request->params['user'] ?? 0);
        if ($userId > 0) {
            $this->communityModerators->remove($community->id, $userId);
        }

        return Response::redirect('/c/' . $community->slug . '/admin/structure');
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            config: $this->config,
            auth: $this->auth,
            request: $request,
            navSections: $this->communityHelper->navForCommunity(),
        );
    }

}
