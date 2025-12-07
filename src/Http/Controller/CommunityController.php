<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\View\ViewRenderer;

use function trim;

final readonly class CommunityController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private PermissionService $permissions,
        private CommunityHelper $communityHelper,
        private CommunityRepository $communities,
    ) {
    }

    public function index(Request $request, array $errors = [], array $old = []): Response
    {
        $communities = $this->communities->all();

        $body = $this->view->render('pages/community/index.php', [
            'pageTitle' => 'Communities',
            'communities' => $communities,
            'errors' => $errors,
            'old' => $old,
            'activePath' => $request->path,
            'navSections' => $this->communityHelper->navSections(null, [], [], $communities),
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'canModerate' => $this->permissions->canModerate($this->auth->currentUser()),
            'canCreateCommunity' => $this->permissions->canCreateCommunity($this->auth->currentUser()),
            'currentCommunity' => null,
            'customCss' => '',
        ]);

        return new Response(
            status: $errors === [] ? 200 : 422,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    public function store(Request $request): Response
    {
        if (!$this->permissions->canCreateCommunity($this->auth->currentUser())) {
            return new Response(403, ['Content-Type' => 'text/plain'], 'Forbidden');
        }

        $name = trim((string) ($request->body['name'] ?? ''));
        $slug = trim((string) ($request->body['slug'] ?? ''));
        $description = trim((string) ($request->body['description'] ?? ''));

        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required.';
        }

        $slug = $slug === '' ? $this->communityHelper->slugify($name) : $this->communityHelper->slugify($slug);
        if ($slug === '') {
            $errors[] = 'Slug is required.';
        }

        if ($errors !== []) {
            return $this->index($request, $errors, [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);
        }

        if ($this->communities->findBySlug($slug) !== null) {
            return $this->index($request, ['Slug is already taken.'], [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);
        }

        $timestamp = time();
        $community = $this->communities->create($slug, $name, $description, null, $timestamp);

        return Response::redirect('/c/' . $community->slug);
    }

    public function show(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $structure = $this->communityHelper->structureForCommunity($community);
        $allCommunities = $this->communities->all();

        $body = $this->view->render('pages/community/show.php', [
            'pageTitle' => $community->name,
            'community' => $community,
            'categories' => $structure['categories'],
            'boardsByCategory' => $structure['boardsByCategory'],
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'currentCommunity' => $community,
            'canModerate' => $this->permissions->canModerate($this->auth->currentUser(), $community->id),
            'activePath' => $request->path,
            'navSections' => $this->communityHelper->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
                $allCommunities,
            ),
            'customCss' => trim((string) ($community->customCss ?? '')),
        ]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
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
