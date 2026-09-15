<?php

declare(strict_types=1);

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * @see https://symfony.com/doc/current/security/custom_authenticator.html
 */
class JwtAuthenticator extends AbstractAuthenticator
{

    public function __construct(
        #[Autowire('%env(JWT_SECRET)%')]
        private readonly string $jwtSecret,
    ) {

    }
    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        return str_starts_with($request->getPathInfo(), '/devices/');
    }

    public function authenticate(Request $request): Passport
    {
        $authorization = $request->headers->get('Authorization');
        if (!is_string($authorization) || !preg_match('/^Bearer\s+(.*?)$/', trim($authorization), $matches)) {
            throw new CustomUserMessageAuthenticationException('Un token Bearer est obligatoire');
        }

        try {
            $payload = JWT::decode($matches[1], new Key($this->jwtSecret, 'HS256'));
        } catch (\Throwable) {
            throw new CustomUserMessageAuthenticationException('Le token est invalide ou expiré');
        }

        if (!isset($payload->sub) || !is_string($payload->sub) || $payload->sub === '') {
            throw new CustomUserMessageAuthenticationException('Le token est invalide');
        }

        return new SelfValidatingPassport(
            new UserBadge(
                $payload->sub,
                static fn (string $identifier): UserInterface =>
                new InMemoryUser(
                    $identifier,
                    null,
                    ['ROLE_API']
                )
            )
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            // you may want to customize or obfuscate the message first
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    // public function start(Request $request, ?AuthenticationException $authException = null): Response
    // {
    //     /*
    //      * If you would like this class to control what happens when an anonymous user accesses a
    //      * protected page (e.g. redirect to /login), uncomment this method and make this class
    //      * implement Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface.
    //      *
    //      * For more details, see https://symfony.com/doc/current/security/experimental_authenticators.html#configuring-the-authentication-entry-point
    //      */
    // }
}
