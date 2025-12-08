<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\View\ViewContext;
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
        $currentUser = $this->auth->currentUser();

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Communities')
            ->set('communities', $communities)
            ->set('errors', $errors)
            ->set('old', $old)
            ->set('navSections', $this->communityHelper->navSections(null, [], [], $communities))
            ->set('canModerate', $this->permissions->canModerate($currentUser))
            ->set('canCreateCommunity', $this->permissions->canCreateCommunity($currentUser));

        return Response::view(
            $this->view,
            'pages/community/index.php',
            $ctx,
            status: $errors === [] ? 200 : 422,
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
        $currentUser = $this->auth->currentUser();

        $ctx = ViewContext::make()
            ->set('pageTitle', $community->name)
            ->set('community', $community)
            ->set('categories', $structure['categories'])
            ->set('boardsByCategory', $structure['boardsByCategory'])
            ->set('currentCommunity', $community)
            ->set('canModerate', $this->permissions->canModerate($currentUser, $community->id))
            ->set('navSections', $this->communityHelper->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
                $allCommunities,
            ))
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view($this->view, 'pages/community/show.php', $ctx);
    }

    public function about(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $structure = $this->communityHelper->structureForCommunity($community);

        $ctx = ViewContext::make()
            ->set('pageTitle', $community->name . ' · About')
            ->set('community', $community)
            ->set('categories', $structure['categories'])
            ->set('boardsByCategory', $structure['boardsByCategory'])
            ->set('currentCommunity', $community)
            ->set('navSections', $this->communityHelper->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
                $this->communities->all(),
            ))
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view($this->view, 'pages/community/about.php', $ctx);
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            request: $request,
        );
    }
}
