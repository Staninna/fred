<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\CurrentUser;
use Fred\Application\Content\BbcodeParser;
use Fred\Domain\Auth\Profile;
use Fred\Domain\Community\Community;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
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
        private CommunityHelper $communityHelper,
        private UserRepository $users,
        private ProfileRepository $profiles,
        private BbcodeParser $parser,
    ) {
    }

    public function show(Request $request): Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $username = (string) ($request->params['username'] ?? '');
        $user = $this->users->findByUsername($username);
        if ($user === null) {
            return $this->notFound($request);
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
        $context = $this->resolveCommunityAndUser($request);
        if ($context instanceof Response) {
            return $context;
        }
        ['community' => $community, 'currentUser' => $currentUser] = $context;

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

        return $this->renderSignatureForm($request, $community, $currentUser, $profile, []);
    }

    public function updateSignature(Request $request): Response
    {
        $context = $this->resolveCommunityAndUser($request);
        if ($context instanceof Response) {
            return $context;
        }
        ['community' => $community, 'currentUser' => $currentUser] = $context;

        $signature = trim((string) ($request->body['signature'] ?? ''));
        $errors = [];

        if (\strlen($signature) > 2000) {
            $errors[] = 'Signature is too long.';
        }

        if ($errors !== []) {
            $profile = $this->profiles->findByUserId($currentUser->id ?? 0);

            return $this->renderSignatureForm($request, $community, $currentUser, $profile, $errors, 422);
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

    public function editProfile(Request $request): Response
    {
        $context = $this->resolveCommunityAndUser($request);
        if ($context instanceof Response) {
            return $context;
        }
        ['community' => $community, 'currentUser' => $currentUser] = $context;

        $profile = $this->profiles->findByUserId($currentUser->id ?? 0);

        return $this->renderProfileForm($request, $community, $currentUser, $profile, [], []);
    }

    public function updateProfile(Request $request): Response
    {
        $context = $this->resolveCommunityAndUser($request);
        if ($context instanceof Response) {
            return $context;
        }
        ['community' => $community, 'currentUser' => $currentUser] = $context;

        $bio = trim((string) ($request->body['bio'] ?? ''));
        $location = trim((string) ($request->body['location'] ?? ''));
        $website = trim((string) ($request->body['website'] ?? ''));

        $errors = [];
        if (\strlen($bio) > 1000) {
            $errors[] = 'Bio is too long (max 1000 characters).';
        }
        if (\strlen($location) > 120) {
            $errors[] = 'Location is too long (max 120 characters).';
        }
        if (\strlen($website) > 200) {
            $errors[] = 'Website URL is too long (max 200 characters).';
        }
        if ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $errors[] = 'Website must start with http:// or https://';
        }

        $old = [
            'bio' => $bio,
            'location' => $location,
            'website' => $website,
        ];

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

        if ($errors !== []) {
            return $this->renderProfileForm($request, $community, $currentUser, $profile, $errors, $old, 422);
        }

        $this->profiles->updateProfile(
            userId: $currentUser->id ?? 0,
            bio: $bio,
            location: $location,
            website: $website,
            avatarPath: $profile->avatarPath,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/u/' . $currentUser->username);
    }

    /**
     * @return array{community: Community, currentUser: CurrentUser}|Response
     */
    private function resolveCommunityAndUser(Request $request): array|Response
    {
        $community = $this->communityHelper->resolveCommunity($request->params['community'] ?? null);
        if ($community === null) {
            return $this->notFound($request);
        }

        $currentUser = $this->auth->currentUser();
        if ($currentUser->isGuest()) {
            return Response::redirect('/login');
        }

        return ['community' => $community, 'currentUser' => $currentUser];
    }

    private function renderSignatureForm(
        Request $request,
        Community $community,
        CurrentUser $currentUser,
        ?Profile $profile,
        array $errors,
        int $status = 200,
    ): Response {
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
            status: $status,
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

    private function renderProfileForm(
        Request $request,
        Community $community,
        CurrentUser $currentUser,
        ?Profile $profile,
        array $errors,
        array $old,
        int $status = 200,
    ): Response {
        $body = $this->view->render('pages/profile/edit.php', [
            'pageTitle' => 'Edit profile',
            'community' => $community,
            'profile' => $profile,
            'errors' => $errors,
            'old' => $old,
            'environment' => $this->config->environment,
            'currentUser' => $currentUser,
            'activePath' => $request->path,
        ]);

        return new Response(
            status: $status,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $body,
        );
    }
}
