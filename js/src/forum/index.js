import { extend } from 'flarum/extend';
import app from 'flarum/app';

import SettingsPage from 'flarum/components/SettingsPage';
import WeChatApplication from './components/WeChatApplication';
import UnlinkModal from "./components/UnlinkModal";
import LinkModal from "./components/LinkModal";

import LogInButtons from 'flarum/components/LogInButtons';
import LogInButton from 'flarum/components/LogInButton';
import Button from 'flarum/components/Button';

app.initializers.add('hamzone-auth-wechat', () => {

    extend(SettingsPage.prototype, 'accountItems', (items) => {
        const {
            data: {
                attributes: {
                    WeChatAuth: {
                        isLinked = false
                    },
                },
            },
        } = app.session.user;

        items.add(`linkWeChatAuth`,
            <Button className={`Button WeChatAuthButton--${isLinked ? 'danger' : 'success'}`} icon={'fab fa-weixin'}
                path={`/auth/${name}`} onclick={() => app.modal.show(isLinked ? UnlinkModal : LinkModal)}>
                {app.translator.trans(`hamzone-auth-wechat.forum.buttons.${isLinked ? 'unlink' : 'link'}`)}
            </Button>
        );
    });

    extend(LogInButtons.prototype, 'items', (items) => {
        items.add('WeChatAuth',
            <LogInButton
                className={`Button LogInButton--WeChatAuth`}
                icon={'fab fa-weixin'}
                path={'/auth/wechat'}>
                {app.translator.trans('hamzone-auth-wechat.forum.buttons.login')}
            </LogInButton>
        );
    });
});

app.wechat = new WeChatApplication();