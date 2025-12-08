<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Domain\Community\Community;
use Fred\Http\Navigation\CommunityContext;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\BoardRepository;
use Fred\Infrastructure\Database\CategoryRepository;
use Fred\Infrastructure\Database\MentionNotificationRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewContext;
use Fred\Infrastructure\View\ViewRenderer;

use function ceil;
use function json_encode;
use function max;
use function trim;

final readonly class MentionController
{
    private const int THREAD_POSTS_PER_PAGE = 25;
    private const int INBOX_PER_PAGE = 20;

    public function __construct(
        private AuthService $auth,
        private AppConfig $config,
        private ViewRenderer $view,
        private CommunityContext $communityContext,
        private MentionNotificationRepository $mentions,
        private UserRepository $users,
        private BoardRepository $boards,
        private CategoryRepository $categories,
    ) {
    }

    public function inbox(Request $request): Response
    {
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();

        $page = (int) ($request->query['page'] ?? 1);
        $page = $page < 1 ? 1 : $page;

        $total = $this->mentions->countForUser($currentUser->id ?? 0, $community->id);
        $totalPages = $total === 0 ? 1 : (int) ceil($total / self::INBOX_PER_PAGE);
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::INBOX_PER_PAGE;
        $notifications = $this->mentions->listForUser(
            userId: $currentUser->id ?? 0,
            communityId: $community->id,
            limit: self::INBOX_PER_PAGE,
            offset: $offset,
        );
        $unreadCount = $this->mentions->countUnread($currentUser->id ?? 0, $community->id);

        $structure = $this->structureForCommunity($community);

        $ctx = ViewContext::make()
            ->set('pageTitle', 'Mentions')
            ->set('community', $community)
            ->set('notifications', $notifications)
            ->set('unreadCount', $unreadCount)
            ->set('totalCount', $total)
            ->set('pagination', [
                'page' => $page,
                'totalPages' => $totalPages,
            ])
            ->set('postsPerPage', self::THREAD_POSTS_PER_PAGE)
            ->set('mentionUnreadCount', $unreadCount)
            ->set('currentCommunity', $community)
            ->set('navSections', $this->communityContext->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ))
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view($this->view, 'pages/mentions/index.php', $ctx);
    }

    public function markRead(Request $request): Response
    {
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();

        $this->mentions->markAllRead($currentUser->id ?? 0, $community->id);

        $pageSuffix = isset($request->body['page']) ? '?page=' . max(1, (int) $request->body['page']) : '';

        return Response::redirect('/c/' . $community->slug . '/mentions' . $pageSuffix);
    }

    public function markOneRead(Request $request): Response
    {
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();

        $mentionId = (int) ($request->params['mention'] ?? 0);
        if ($mentionId > 0) {
            $this->mentions->markOneRead($mentionId, $currentUser->id ?? 0, $community->id);
        }

        $pageSuffix = isset($request->body['page']) ? '?page=' . max(1, (int) $request->body['page']) : '';

        return Response::redirect('/c/' . $community->slug . '/mentions' . $pageSuffix);
    }

    public function suggest(Request $request): Response
    {
        $community = $request->attribute('community');
        if (!$community instanceof Community) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();

        $term = trim((string) ($request->query['q'] ?? ''));
        if ($term === '' || \strlen($term) < 2) {
            return new Response(200, ['Content-Type' => 'application/json; charset=utf-8'], '[]');
        }

        $results = $this->users->search($term, null, 8, 0);
        $payload = array_map(static function ($user) {
            return [
                'username' => $user->username,
                'display_name' => $user->displayName,
            ];
        }, $results);

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = '[]';
        }

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
            body: $encoded,
        );
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

    private function structureForCommunity(Community $community): array
    {
        $categories = $this->categories->listByCommunityId($community->id);
        $boards = $this->boards->listByCommunityId($community->id);

        return [
            'categories' => $categories,
            'boardsByCategory' => $this->groupBoards($boards),
        ];
    }

    /** @param \Fred\Domain\Community\Board[] $boards @return array<int, \Fred\Domain\Community\Board[]> */
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
}
