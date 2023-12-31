<?php

namespace App\Security;

use App\Repository\ParticipantRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private $participantRepo;

    public function __construct(private UrlGeneratorInterface $urlGenerator, ParticipantRepository $participantRepository)
    {
        $this->participantRepo = $participantRepository;
    }

    public function authenticate(Request $request): Passport
    {

        $email = $request->request->get('email', '');
        $password = $request->request->get('password');

        $participant = $this->participantRepo->findOneBy(['email' => $email]);

        if($participant != null) {
            $profil = $participant->getBackdrop();
            $request->getSession()->set('participant', $profil);
        }
        $request->getSession()->set(Security::LAST_USERNAME, $email);


        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new RememberMeBadge(),
                new CsrfTokenBadge('authenticate', $request->request->get('_csrf_token')),

            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $email = $request->request->get('email', '');

        $participant = $this->participantRepo->findOneBy(['email' => $email]);

        if($participant->isActif()){
            return new RedirectResponse($this->urlGenerator->generate('sortie_index'));
        }else{
            //feedback user
            #this->addFlash('error', 'Votre compte est désactivé veuillez contacter l\'administrateur');
            $request->getSession()->getFlashBag()->add('message', 'Votre compte est désactivé veuillez contacter l\'administrateur');

        }
        return new RedirectResponse($this->urlGenerator->generate('app_logout'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
