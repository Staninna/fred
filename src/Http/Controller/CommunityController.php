<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\PermissionService;
use Fred\Domain\Community\Community;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;
use Fred\Support\Str;

final readonly class CommunityController extends Controller
{
    public function __construct(
        ViewRenderer $view,
        AppConfig $config,
        AuthService $auth,
        CommunityContext $communityContext,
        private PermissionService $permissions,
        private CommunityRepository $communities,
    ) {
        parent::__construct($view, $config, $auth, $communityContext);
    }

    /**
     * @param string[] $errors
     * @param array<string, mixed> $old
     */
    public function index(Request $request, array $errors = [], array $old = []): Response
    {
        $communities = $this->communities->all();
        $currentUser = $this->auth->currentUser();

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Communities')
            ->set('communities', $communities)
            ->set('errors', $errors)
            ->set('old', $old)
            ->set('navSections', $this->communityContext->navSections(null, [], [], $communities))
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

        $slug = $slug === '' ? Str::slugify($name) : Str::slugify($slug);

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
        $community = $request->context()->community;

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in CommunityController::show');
        }

        $structure = $this->communityContext->structureForCommunity($community);
        $allCommunities = $this->communities->all();
        $currentUser = $this->auth->currentUser();

        $ctx = ViewContext::make()
            ->set('pageTitle', $community->name)
            ->set('community', $community)
            ->set('categories', $structure['categories'])
            ->set('boardsByCategory', $structure['boardsByCategory'])
            ->set('currentCommunity', $community)
            ->set('canModerate', $this->permissions->canModerate($currentUser, $community->id))
            ->set('navSections', $this->communityContext->navSections(
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
        $community = $request->context()->community;

        if (!$community instanceof Community) {
            return $this->notFound($request, 'Community attribute missing in CommunityController::about');
        }

        $structure = $this->communityContext->structureForCommunity($community);

        $ctx = ViewContext::make()
            ->set('pageTitle', $community->name . ' · About')
            ->set('community', $community)
            ->set('categories', $structure['categories'])
            ->set('boardsByCategory', $structure['boardsByCategory'])
            ->set('currentCommunity', $community)
            ->set('navSections', $this->communityContext->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
                $this->communities->all(),
            ))
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view($this->view, 'pages/community/about.php', $ctx);
    }
}
