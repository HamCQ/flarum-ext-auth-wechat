
基于 https://github.com/NomisCZ/flarum-ext-auth-wechat 项目进行二次修改，支持移动端微信登录跳转，桌面端依旧支持二维码扫码登录  

需要额外配置公众号APP_ID, APP_SEC  

安装方式：  

composer require hamcq/flarum-ext-auth-wechat  

### 优化体验配置：  

扫码登录后，已绑定的用户可以直接登录，未绑定的用户会提示注册账号，需要前往后台-外观-编辑自定义页脚，添加如下代码，完善使用体验：  

```
<script>
window.onload = function () {
    if(window.location.href.indexOf("wechat_user") != -1){
        if(app.data.session.userId!=""){
         window.location.href ="https://forum.hamcq.cn"
        }
        var log=JSON.parse(decodeURIComponent(window.location.href.split("=")[1]));
        window.app.authenticationComplete(log);
        window.app.alerts.show({type: 'warning'}, '未查询到绑定信息，请注册账号即可完成绑定');
    }
};
</script>
```