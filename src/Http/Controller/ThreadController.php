<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\BbcodeParser;
use Fred\Domain\Community\Board;
use Fred\Domain\Community\Category;
use Fred\Domain\Community\Community;
use Fred\Domain\Forum\Thread as ThreadModel;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\PostRepository;
use Fred\Infrastructure\Database\ThreadRepository;
use Fred\Infrastructure\View\ViewRenderer;

use function array_values;
use function ctype_digit;
use function trim;

final readonly class ThreadController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private CommunityRepository $communities,
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private ThreadRepository $threads,
        private PostRepository $posts,
        private BbcodeParser $parser,
    ) {
    }

    public function show(Request $request): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound();
        }

        $threadId = (int) ($request->params['thread'] ?? 0);
        $thread = $this->threads->findById($threadId);
        if ($thread === null || $thread->communityId !== $community->id) {
            return $this->notFound();
        }

        $board = $this->boards->findById($thread->boardId);
        if ($board === null || $board->communityId !== $community->id) {
            return $this->notFound();
        }

        $category = $this->categories->findById($board->categoryId);
        if ($category === null || $category->communityId !== $community->id) {
            return $this->notFound();
        }

        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);
        $boardsByCategory = $this->groupBoards($boards);
        $allCommunities = $this->communities->all();
        $posts = $this->posts->listByThreadId($thread->id);

        $body = $this->view->render('pages/thread/show.php', [
            'pageTitle' => $thread->title,
            'community' => $community,
            'board' => $board,
            'category' => $category,
            'thread' => $thread,
            'posts' => $posts,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'activePath' => $request->path,
            'navSections' => $this->navSections($community, $allCommunities, $boardsByCategory, $categories),
        ]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    public function create(Request $request): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound();
        }

        $boardSlug = (string) ($request->params['board'] ?? '');
        $board = $this->boards->findBySlug($community->id, $boardSlug);
        if ($board === null && ctype_digit($boardSlug)) {
            $board = $this->boards->findById((int) $boardSlug);

            if ($board !== null && $board->communityId !== $community->id) {
                $board = null;
            }
        }

        if ($board === null) {
            return $this->notFound();
        }

        if ($this->auth->currentUser()->isGuest()) {
            return Response::redirect('/login');
        }

        return $this->renderCreate($request, $community, $board, []);
    }

    public function store(Request $request): Response
    {
        $community = $this->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound();
        }

        $boardSlug = (string) ($request->params['board'] ?? '');
        $board = $this->boards->findBySlug($community->id, $boardSlug);
        if ($board === null && ctype_digit($boardSlug)) {
            $board = $this->boards->findById((int) $boardSlug);

            if ($board !== null && $board->communityId !== $community->id) {
                $board = null;
            }
        }

        if ($board === null) {
            return $this->notFound();
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

        if ($board->isLocked) {
            return $this->renderCreate($request, $community, $board, ['Board is locked.'], [
                'title' => $request->body['title'] ?? '',
                'body' => $request->body['body'] ?? '',
            ], 403);
        }

        $title = trim((string) ($request->body['title'] ?? ''));
        $bodyText = trim((string) ($request->body['body'] ?? ''));

        $errors = [];
        if ($title === '') {
            $errors[] = 'Title is required.';
        }

        if ($bodyText === '') {
            $errors[] = 'Body is required.';
        }

        if ($errors !== []) {
            return $this->renderCreate($request, $community, $board, $errors, [
                'title' => $title,
                'body' => $bodyText,
            ], 422);
        }

        $timestamp = time();
        $thread = $this->threads->create(
            communityId: $community->id,
            boardId: $board->id,
            title: $title,
            authorId: $currentUser->id ?? 0,
            isSticky: false,
            isLocked: false,
            isAnnouncement: false,
            timestamp: $timestamp,
        );

        $this->posts->create(
            communityId: $community->id,
            threadId: $thread->id,
            authorId: $currentUser->id ?? 0,
            bodyRaw: $bodyText,
            bodyParsed: $this->parser->parse($bodyText),
            signatureSnapshot: null,
            timestamp: $timestamp,
        );

        return Response::redirect('/c/' . $community->slug . '/t/' . $thread->id);
    }

    private function renderCreate(
        Request $request,
        Community $community,
        Board $board,
        array $errors,
        array $old = [],
        int $status = 200,
    ): Response {
        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);
        $boardsByCategory = $this->groupBoards($boards);
        $allCommunities = $this->communities->all();

        $body = $this->view->render('pages/thread/create.php', [
            'pageTitle' => 'New thread',
            'community' => $community,
            'board' => $board,
            'errors' => $errors,
            'old' => $old,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'activePath' => $request->path,
            'navSections' => $this->navSections($community, $allCommunities, $boardsByCategory, $categories),
        ]);

        return new Response(
            status: $status,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    private function resolveCommunity(?string $slug): ?Community
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return $this->communities->findBySlug($slug);
    }

    /**
     * @param Board[] $boards
     *
     * @return array<int, Board[]>
     */
    private function groupBoards(array $boards): array
    {
        $grouped = [];
        foreach ($boards as $board) {
            $grouped[$board->categoryId][] = $board;
        }

        foreach ($grouped as $categoryId => $items) {
            $grouped[$categoryId] = array_values($items);
        }

        return $grouped;
    }

    private function navSections(Community $current, array $communities, array $boardsByCategory, array $categories): array
    {
        $communityLinks = [];
        foreach ($communities as $community) {
            $communityLinks[] = [
                'label' => $community->name,
                'href' => '/c/' . $community->slug,
            ];
        }

        $boardLinks = [];
        foreach ($categories as $category) {
            $boardLinks[] = [
                'label' => $category->name,
                'href' => '#',
            ];

            foreach ($boardsByCategory[$category->id] ?? [] as $board) {
                $boardLinks[] = [
                    'label' => '↳ ' . $board->name,
                    'href' => '/c/' . $current->slug . '/b/' . $board->slug,
                ];
            }
        }

        return [
            [
                'title' => 'Communities',
                'items' => $communityLinks,
            ],
            [
                'title' => 'Boards',
                'items' => $boardLinks === [] ? [['label' => 'No boards yet', 'href' => '#']] : $boardLinks,
            ],
        ];
    }

    private function notFound(): Response
    {
        return new Response(
            status: 404,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: '<h1>Not Found</h1>',
        );
    }
}
