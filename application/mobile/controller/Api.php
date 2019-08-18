<?php

namespace app\mobile\controller;
use think\Db;

class Api extends MobileBase
{
    /**
     * 获取 access_token 的 api 接口
     */
    public function access_token(){
        //判断是否过了缓存期
        $wx_user = M('wx_user')->find();
        $expire_time = $wx_user['web_expires'];
        
        if($expire_time > time()){
           return $wx_user['web_access_token'];
        }
        
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$wx_user[appid]}&secret={$wx_user[appsecret]}";
        $return = httpRequest($url,'GET');
        $return = json_decode($return,1);
        
        $web_expires = time() + 7140; // 提前60秒过期
        M('wx_user')->where(array('id'=>$wx_user['id']))->save(array('web_access_token'=>$return['access_token'],'web_expires'=>$web_expires));
        return $return['access_token'];
    }  


}