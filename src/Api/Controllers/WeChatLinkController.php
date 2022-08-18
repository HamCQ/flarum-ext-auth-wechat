<?php

/*
 * This file is part of nomiscz/flarum-ext-auth-wechat.
 *
 * Copyright (c) 2021 NomisCZ.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace NomisCZ\WeChatAuth\Api\Controllers;

use NomisCZ\OAuth2\Client\Provider\WeChat;
use NomisCZ\OAuth2\Client\Provider\WeChatResourceOwner;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Flarum\User\LoginProvider;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use NomisCZ\OAuth2\Client\Provider\WeChatOffical;

class WeChatLinkController implements RequestHandlerInterface
{
    /**
     * @var LoginProvider
     */
    protected $loginProvider;
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;
    /**
     * @var UrlGenerator
     */
    protected $url;
    /**
     * @param LoginProvider $loginProvider
     * @param SettingsRepositoryInterface $settings
     * @param UrlGenerator $url
     */
    public function __construct(LoginProvider $loginProvider, SettingsRepositoryInterface $settings, UrlGenerator $url)
    {
        $this->loginProvider = $loginProvider;
        $this->settings = $settings;
        $this->url = $url;
    }
    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = $request->getAttribute('actor');
        $actorLoginProviders = $actor->loginProviders()->where('provider', 'wechat')->first();

        if ($actorLoginProviders) {
            return $this->makeResponse('already_linked');
        }

        $redirectUri = $this->url->to('api')->route('auth.wechat.api.link');
        app('log')->debug( $redirectUri );
        app('log')->debug( $_SERVER['HTTP_USER_AGENT'] );

        if( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ){
            app('log')->debug("isMobile()");
            $provider = new WeChatOffical([
                'appid' => $this->settings->get('flarum-ext-auth-wechat.mp_app_id'),
                'secret' => $this->settings->get('flarum-ext-auth-wechat.mp_app_secret'),
                'redirect_uri' => $redirectUri,
            ]);
        }else{
            $provider = new WeChat([
                'appid' => $this->settings->get('flarum-ext-auth-wechat.app_id'),
                'secret' => $this->settings->get('flarum-ext-auth-wechat.app_secret'),
                'redirect_uri' => $redirectUri,
            ]);
        }
       

        $session = $request->getAttribute('session');
        $queryParams = $request->getQueryParams();
        $code = array_get($queryParams, 'code');

        if (!$code) {
            app('log')->debug("!code");

            $authUrl = $provider->getAuthorizationUrl();
            $session->put('oauth2state', $provider->getState());
            app('log')->debug($authUrl);
            return new RedirectResponse($authUrl . '#wechat_redirect');
        }

        $state = array_get($queryParams, 'state');

        if (!$state || $state !== $session->get('oauth2state')) {

            $session->remove('oauth2state');
            throw new Exception('Invalid state');
        }

        $token = $provider->getAccessToken('authorization_code', compact('code'));
        app('log')->debug($token);
        /** @var WeChatResourceOwner $user */
        $user = $provider->getResourceOwner($token);
        app('log')->debug($user->getUnionId());

        if ($this->checkLoginProvider($user->getUnionId())) {
            app('log')->debug("checkLoginProvider");
            return $this->makeResponse('already_used');
        }
        app('log')->debug("loginProviders");
        $created = $actor->loginProviders()->create([
            'provider' => 'wechat',
            'identifier' => $user->getUnionId()
        ]);

        return $this->makeResponse($created ? 'done' : 'error');
    }

    private function makeResponse($returnCode = 'done'): HtmlResponse
    {
        $content = "<script>window.close();window.opener.app.wechat.linkDone('{$returnCode}');</script>";

        return new HtmlResponse($content);
    }

    private function checkLoginProvider($identifier): bool
    {
        return $this->loginProvider->where([
            ['provider', 'wechat'],
            ['identifier', $identifier]
        ])->exists();
    }

    // private function isMobile() : bool{
    //     if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
    //         return true;
    //     }
    //     if (isset($_SERVER['HTTP_VIA'])) {
    //         // 找不到为flase,否则为true
    //         return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
    //     }
    //     if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) { 
    //         return true; 
    //     }
    // }

}
