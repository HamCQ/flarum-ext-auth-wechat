<?php

/*
 * This file is part of nomiscz/flarum-ext-auth-wechat.
 *
 * Copyright (c) 2021 NomisCZ.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace NomisCZ\WeChatAuth\Http\Controllers;

use Exception;
use NomisCZ\WeChatAuth\Http\Controllers\WXRespFactory;

use Flarum\Forum\Auth\Registration;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use NomisCZ\OAuth2\Client\Provider\WeChat;
use NomisCZ\OAuth2\Client\Provider\WeChatOffical;
use NomisCZ\OAuth2\Client\Provider\WeChatResourceOwner;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\RedirectResponse;

class WeChatAuthController implements RequestHandlerInterface
{
    /**
     * @var ResponseFactory
     */
    protected $response;
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;
    /**
     * @var UrlGenerator
     */
    protected $url;
    /**
     * @param ResponseFactory $response
     * @param SettingsRepositoryInterface $settings
     * @param UrlGenerator $url
     */
    public function __construct(WXRespFactory $response, SettingsRepositoryInterface $settings, UrlGenerator $url)
    {
        $this->response = $response;
        $this->settings = $settings;
        $this->url = $url;
    }
    /**
     * @param Request $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function handle(Request $request): ResponseInterface
    {
        $redirectUri = $this->url->to('forum')->route('auth.wechat');
        app('log')->debug( $redirectUri );
        app('log')->debug( $_SERVER['HTTP_USER_AGENT'] );
        $isMobile = false;
        //微信客户端内
        if( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ){
            $isMobile = true;
            app('log')->debug("wechatBrowser");
            $provider = new WeChatOffical([
                'appid' => $this->settings->get('flarum-ext-auth-wechat.mp_app_id'),
                'secret' => $this->settings->get('flarum-ext-auth-wechat.mp_app_secret'),
                'redirect_uri' => $redirectUri,
            ]);

        }else{
            if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
                $isMobile = true;
            }
            if (isset($_SERVER['HTTP_VIA'])) {
                $isMobile = stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
            }
            if (isset($_SERVER['HTTP_USER_AGENT'])){
                if(
                    strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== false||
                    strpos($_SERVER['HTTP_USER_AGENT'], 'Android') !== false||
                    strpos($_SERVER['HTTP_USER_AGENT'], 'Kindle') !== false||
                    strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mini') !== false||
                    strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mobi') !== false||
                    strpos($_SERVER['HTTP_USER_AGENT'], 'BlackBerry') !== false
                ){
                    $isMobile = true;
                }
            }
            if($isMobile){
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
        }

        $session = $request->getAttribute('session');
        $queryParams = $request->getQueryParams();
        $code = array_get($queryParams, 'code');

        if (!$code) {
            app('log')->debug("!code");
            $authUrl = $provider->getAuthorizationUrl();
            $session->put('oauth2state', $provider->getState());
            app('log')->debug($authUrl );
            return new RedirectResponse($authUrl . '#wechat_redirect');
            // return new RedirectResponse($authUrl . '&display=popup');
        }

        $state = array_get($queryParams, 'state');

        if (!$state || $state !== $session->get('oauth2state')) {

            $session->remove('oauth2state');
            throw new Exception('Invalid state');
        }

        $token = $provider->getAccessToken('authorization_code', compact('code'));
        /** @var WeChatResourceOwner $user */
        $user = $provider->getResourceOwner($token);

        if($isMobile){
            return $this->response->make(
                'wechat',
                $user->getUnionId(),
                function (Registration $registration) use ($user) {
                    $registration
                        ->suggestUsername($user->getNickname())
                        ->setPayload($user->toArray());
    
                    if ($user->getHeadImgUrl()) {
                        $registration->provideAvatar($user->getHeadImgUrl());
                    }
                }
            );
        }

        return $this->response->make(
            'wechat',
            $user->getUnionId(),
            function (Registration $registration) use ($user) {
                $registration
                    ->suggestUsername($user->getNickname())
                    ->setPayload($user->toArray());

                if ($user->getHeadImgUrl()) {
                    $registration->provideAvatar($user->getHeadImgUrl());
                }
            }
        );
    }
}
