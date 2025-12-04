<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Content\BbcodeParser;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\CommunityRepository;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewRenderer;

use function trim;

final readonly class ProfileController
{
    public function __construct(
        private ViewRenderer $view,
        private AppConfig $config,
        private AuthService $auth,
        private CommunityRepository $communities,
        private UserRepository $users,
        private ProfileRepository $profiles,
        private BbcodeParser $parser,
    ) {
    }

    public function show(Request $request): Response
    {
        $community = $this->communities->findBySlug((string) ($request->params['community'] ?? ''));
        if ($community === null) {
            return $this->notFound();
        }

        $username = (string) ($request->params['username'] ?? '');
        $user = $this->users->findByUsername($username);
        if ($user === null) {
            return $this->notFound();
        }

        $profile = $this->profiles->findByUserId($user->id);

        $body = $this->view->render('pages/profile/show.php', [
            'pageTitle' => $user->displayName,
            'community' => $community,
            'user' => $user,
            'profile' => $profile,
            'environment' => $this->config->environment,
            'currentUser' => $this->auth->currentUser(),
            'activePath' => $request->path,
        ]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    public function editSignature(Request $request): Response
    {
        $community = $this->communities->findBySlug((string) ($request->params['community'] ?? ''));
        if ($community === null) {
            return $this->notFound();
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

        $profile = $this->profiles->findByUserId($currentUser->id ?? 0);
        if ($profile === null) {
            $profile = $this->profiles->create(
                userId: $currentUser->id ?? 0,
                bio: '',
                location: '',
                website: '',
                signatureRaw: '',
                signatureParsed: '',
                avatarPath: '',
                timestamp: time(),
            );
        }

        $body = $this->view->render('pages/profile/signature.php', [
            'pageTitle' => 'Edit signature',
            'community' => $community,
            'profile' => $profile,
            'errors' => [],
            'environment' => $this->config->environment,
            'currentUser' => $currentUser,
            'activePath' => $request->path,
        ]);

        return new Response(
            status: 200,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }

    public function updateSignature(Request $request): Response
    {
        $community = $this->communities->findBySlug((string) ($request->params['community'] ?? ''));
        if ($community === null) {
            return $this->notFound();
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

        $signature = trim((string) ($request->body['signature'] ?? ''));
        $errors = [];

        if (\strlen($signature) > 2000) {
            $errors[] = 'Signature is too long.';
        }

        if ($errors !== []) {
            $profile = $this->profiles->findByUserId($currentUser->id ?? 0);
            $body = $this->view->render('pages/profile/signature.php', [
                'pageTitle' => 'Edit signature',
                'community' => $community,
                'profile' => $profile,
                'errors' => $errors,
                'environment' => $this->config->environment,
                'currentUser' => $currentUser,
                'activePath' => $request->path,
            ]);

            return new Response(
                status: 422,
                headers: ['Content-Type' => 'text/html; charset=utf-8'],
                body: $body,
            );
        }

        $parsed = $signature === '' ? '' : $this->parser->parse($signature);
        $this->profiles->updateSignature(
            userId: $currentUser->id ?? 0,
            raw: $signature,
            parsed: $parsed,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/u/' . $currentUser->username);
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
