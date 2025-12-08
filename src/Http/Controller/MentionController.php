<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\MentionNotificationRepository;
use Fred\Infrastructure\Database\UserRepository;
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
        private CommunityHelper $communityHelper,
        private MentionNotificationRepository $mentions,
        private UserRepository $users,
    ) {
    }

    public function inbox(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

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

        $structure = $this->communityHelper->structureForCommunity($community);

        $body = $this->view->render('pages/mentions/index.php', [
            'pageTitle' => 'Mentions',
            'community' => $community,
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
            'totalCount' => $total,
            'pagination' => [
                'page' => $page,
                'totalPages' => $totalPages,
            ],
            'postsPerPage' => self::THREAD_POSTS_PER_PAGE,
            'mentionUnreadCount' => $unreadCount,
            'environment' => $this->config->environment,
            'currentUser' => $currentUser,
            'currentCommunity' => $community,
            'activePath' => $request->path,
            'navSections' => $this->communityHelper->navSections(
                $community,
                $structure['categories'],
                $structure['boardsByCategory'],
            ),
            'customCss' => trim((string) ($community->customCss ?? '')),
        ]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    public function markRead(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

        $this->mentions->markAllRead($currentUser->id ?? 0, $community->id);

        $pageSuffix = isset($request->body['page']) ? '?page=' . max(1, (int) $request->body['page']) : '';

        return Response::redirect('/c/' . $community->slug . '/mentions' . $pageSuffix);
    }

    public function markOneRead(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

        $mentionId = (int) ($request->params['mention'] ?? 0);
        if ($mentionId > 0) {
            $this->mentions->markOneRead($mentionId, $currentUser->id ?? 0, $community->id);
        }

        $pageSuffix = isset($request->body['page']) ? '?page=' . max(1, (int) $request->body['page']) : '';

        return Response::redirect('/c/' . $community->slug . '/mentions' . $pageSuffix);
    }

    public function suggest(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return new Response(
                status: 401,
                headers: ['Content-Type' => 'application/json; charset=utf-8'],
                body: '[]',
            );
        }

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
