<?php

declare(strict_types=1);

namespace Fred\Http\Controller;

use Fred\Application\Auth\AuthService;
use Fred\Application\Auth\CurrentUser;
use Fred\Application\Content\BbcodeParser;
use Fred\Application\Content\UploadService;
use Fred\Domain\Auth\Profile;
use Fred\Domain\Auth\User;
use Fred\Domain\Community\Community;
use Fred\Http\Request;
use Fred\Http\Response;
use Fred\Infrastructure\Config\AppConfig;
use Fred\Infrastructure\Database\ProfileRepository;
use Fred\Infrastructure\Database\UserRepository;
use Fred\Infrastructure\View\ViewContext;
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
        private UploadService $uploads,
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

        $profile = $this->profiles->ensureExists($user->id, $community->id);

        return $this->renderProfilePage($request, $community, $user, $profile);
    }

    public function editSignature(Request $request): Response
    {
        $context = $this->resolveCommunityAndUser($request);
        if ($context instanceof Response) {
            return $context;
        }
        ['community' => $community, 'currentUser' => $currentUser] = $context;
        $user = $this->users->findById($currentUser->id ?? 0);
        if ($user === null) {
            return $this->notFound($request);
        }

        return Response::redirect('/c/' . $community->slug . '/u/' . $currentUser->username);
    }

    public function updateSignature(Request $request): Response
    {
        $context = $this->resolveCommunityAndUser($request);
        if ($context instanceof Response) {
            return $context;
        }
        ['community' => $community, 'currentUser' => $currentUser] = $context;
        $user = $this->users->findById($currentUser->id ?? 0);
        if ($user === null) {
            return $this->notFound($request);
        }

        $signature = trim((string) ($request->body['signature'] ?? ''));
        $errors = [];

        if (\strlen($signature) > 2000) {
            $errors[] = 'Signature is too long.';
        }

        if ($errors !== []) {
            $profile = $this->profiles->ensureExists($currentUser->id ?? 0, $community->id);

            return $this->renderProfilePage($request, $community, $user, $profile, [], $errors, []);
        }

        $parsed = $signature === '' ? '' : $this->parser->parse($signature, $community->slug);
        $this->profiles->updateSignature(
            userId: $currentUser->id ?? 0,
            communityId: $community->id,
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

        return Response::redirect('/c/' . $community->slug . '/u/' . $currentUser->username);
    }

    public function editAvatar(Request $request): Response
    {
        $context = $this->resolveCommunityAndUser($request);
        if ($context instanceof Response) {
            return $context;
        }
        ['community' => $community, 'currentUser' => $currentUser] = $context;

        return Response::redirect('/c/' . $community->slug . '/u/' . $currentUser->username);
    }

    public function updateAvatar(Request $request): Response
    {
        $context = $this->resolveCommunityAndUser($request);
        if ($context instanceof Response) {
            return $context;
        }
        ['community' => $community, 'currentUser' => $currentUser] = $context;
        $user = $this->users->findById($currentUser->id ?? 0);
        if ($user === null) {
            return $this->notFound($request);
        }

        $profile = $this->profiles->ensureExists($currentUser->id ?? 0, $community->id);
        $file = $request->files['avatar'] ?? null;
        if (!\is_array($file) || ($file['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
            return $this->renderProfilePage($request, $community, $user, $profile, [], [], ['Please choose a file.']);
        }

        try {
            $path = $this->uploads->saveAvatar($file);
        } catch (\Throwable $exception) {
            return $this->renderProfilePage($request, $community, $user, $profile, [], [], [$exception->getMessage()]);
        }

        $this->profiles->updateProfile(
            userId: $currentUser->id ?? 0,
            communityId: $community->id,
            bio: $profile->bio,
            location: $profile->location,
            website: $profile->website,
            avatarPath: $path,
            timestamp: time(),
        );

        return Response::redirect('/c/' . $community->slug . '/u/' . $currentUser->username);
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

        $profile = $this->profiles->findByUserAndCommunity($currentUser->id ?? 0, $community->id);
        if ($profile === null) {
            $profile = $this->profiles->create(
                userId: $currentUser->id ?? 0,
                communityId: $community->id,
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
            return $this->renderProfilePage($request, $community, $user, $profile, $errors, [], [], $old);
        }

        $this->profiles->updateProfile(
            userId: $currentUser->id ?? 0,
            communityId: $community->id,
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

    private function renderProfilePage(
        Request $request,
        Community $community,
        User $user,
        Profile $profile,
        array $profileErrors = [],
        array $signatureErrors = [],
        array $avatarErrors = [],
        array $oldProfile = [],
    ): Response {
        $status = ($profileErrors !== [] || $signatureErrors !== [] || $avatarErrors !== []) ? 422 : 200;

        $ctx = ViewContext::make()
            ->set('pageTitle', $user->displayName)
            ->set('community', $community)
            ->set('user', $user)
            ->set('profile', $profile)
            ->set('profileErrors', $profileErrors)
            ->set('signatureErrors', $signatureErrors)
            ->set('avatarErrors', $avatarErrors)
            ->set('oldProfile', $oldProfile)
            ->set('currentCommunity', $community)
            ->set('customCss', trim((string) ($community->customCss ?? '')));

        return Response::view($this->view, 'pages/profile/show.php', $ctx, status: $status);
    }

    private function notFound(Request $request): Response
    {
        return Response::notFound(
            view: $this->view,
            request: $request,
        );
    }
}
