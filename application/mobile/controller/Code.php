<?php

namespace app\mobile\controller;
use think\Db;
use app\common\model\UserCode;
use app\common\model\Users;
use app\common\model\UserBeeAccount;
use app\common\logic\JssdkLogic;

class Code extends MobileBase
{
    /**
     * 获取二维码
     * 引用地址 ：
     *      /Mobile/code/create_code
     */
    public function create_code()
    {
    
        $openid = session('user.openid');
//        $logo=session('user.head_pic');
        if(I('test') == 1){
            //测试
            $openid = 'testopenid';
        }
        if (empty($openid)) {
            return "/public/images/error.jpg";
        }
    
        $model = new UserCode();
        $img = $model->where(['openid'=>$openid])->value('img');
        if($img){
            return $img;
        }

        $access_token = httpRequest(SITE_URL."/mobile/api/access_token");
   
        $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$access_token;
        $data['action_name'] = 'QR_LIMIT_STR_SCENE';
        $data['action_info']['scene']['scene_str'] = $openid;
        $data = json_encode($data);
        $res = httpRequest($url,'POST',$data);
        $result = json_decode($res,true);

        $result['openid'] = $openid;
        $result['img'] = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".$result['ticket'];



//        $rootPath = 'public/upload/';
//        $path = 'Qrcode/'.date("Y-m-d").'/';
//        $fileName = uniqid();
//        if (!is_dir($rootPath.$path))
//        {
//            mkdir($rootPath.$path,0777,true);
//        }
//        $originalUrl = $path.$fileName.'.png';
//
//        Vendor('phpqrcode.phpqrcode');
//        $object = new \QRcode();
//        $errorCorrectionLevel = 'L';    //容错级别
//        $matrixPointSize = 20;            //生成图片大小（这个值可以通过参数传进来判断）
//        $object->png($data,$rootPath.$originalUrl,$errorCorrectionLevel, $matrixPointSize, 2);
//
//        //判断是否生成带logo的二维码
//        if(file_exists($logo))
//        {
//            $QR = imagecreatefromstring(file_get_contents($rootPath.$originalUrl));        //目标图象连接资源。
//            $logo = imagecreatefromstring(file_get_contents($logo));    //源图象连接资源。
//
//            $QR_width = imagesx($QR);            //二维码图片宽度
//            $QR_height = imagesy($QR);            //二维码图片高度
//            $logo_width = imagesx($logo);        //logo图片宽度
//            $logo_height = imagesy($logo);        //logo图片高度
//            $logo_qr_width = $QR_width / 4;       //组合之后logo的宽度(占二维码的1/5)
//            $scale = $logo_width/$logo_qr_width;       //logo的宽度缩放比(本身宽度/组合后的宽度)
//            $logo_qr_height = $logo_height/$scale;  //组合之后logo的高度
//            $from_width = ($QR_width - $logo_qr_width) / 2;   //组合之后logo左上角所在坐标点
//
//            //重新组合图片并调整大小
//            //imagecopyresampled() 将一幅图像(源图象)中的一块正方形区域拷贝到另一个图像中
//            imagecopyresampled($QR, $logo, $from_width, $from_width, 0, 0, $logo_qr_width,$logo_qr_height, $logo_width, $logo_height);
//
//            //输出图片
//            imagepng($QR, $rootPath.$originalUrl);
//            imagedestroy($QR);
//            imagedestroy($logo);
//        }
//
//        $result['errcode'] = 0;
//        $result['errmsg'] = 'ok';
//        $result['data'] = $originalUrl;
//        return $result;

        $model = new UserCode();
        $model->openid = $openid;
        $model->url = $result['url'];
        $model->ticket = $result['ticket'];
        $model->img = $result['img'];

        $re = $model->save();

        return $result['img'];

    }

    public function next()
    {

        $EventKey = $_GET['shangji'];
        $FromUserName = $_GET['xiaji'];
        $event = $_GET['event'];
        if ($event == 'subscribe') {
            $n = -1 * (count($EventKey) - 9);
            $EventKey = substr($EventKey,$n);
        }
        if($EventKey==$FromUserName) return false;
        $this->register($EventKey);//上级
        $this->register($FromUserName);//下级

        //写表
        $user = new Users();
        $user_id = $user->where(['openid'=>$EventKey])->value('user_id');
        $flas = $user->where(['openid'=>$FromUserName])->save(['first_leader'=>$user_id]);
        if($flas){
            $this->config = tpCache('game'); //配置信息
            // 如果有微信公众号 则推送一条消息到微信
            $user = M('users')->where(['user_id'=>$user_id])->find();
            $oauth_users = M('users')->where(['openid'=>$FromUserName])->find();
            if($user)
            {
                $wx_user = M('wx_user')->find();
                $jssdk = new JssdkLogic($wx_user['appid'],$wx_user['appsecret']);
//                $wx_content = "您刚推荐的朋友“".$oauth_users['nickname']."”用户关注公众号,平台奖励".$this->config['nine_give_bee_milk']."滴蜂王浆,".$this->config['nine_random_sun']."克阳光值,".$this->config['nine_random_water']."滴露水";
                $wx_content = "您刚推荐的朋友“".$oauth_users['nickname']."”，加入了九九蜂王台，一起开启养蜂之旅吧！";
                $test= $jssdk->push_msg($user['openid'],$wx_content);

            }
            //添加奖励
            $this->add_bee($user_id);
        }else{
            return false;
        }
        

    }
    //查询users信息是否有注册
    public function register($openid)
    {
        $user = M('users')->where("openid",$openid)->find();
        if (empty($user)) {
            //通过  openid + access_token 获取 用户信息
            $access_token = httpRequest(SITE_URL."/mobile/api/access_token");
            $url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
            //注册
            $data = httpRequest($url);
            // $this->write_log($data);
            $data = json_decode($data,true);

            $user = new Users();
            $user->openid = $data['openid'];
            $user->nickname = $data['nickname'];
            $user->sex = $data['sex'];
            $user->head_pic = $data['headimgurl'];
            $user->save();

            $user_id = $user->user_id;

            //不存在则创建个第三方账号
            M('OauthUsers')->save(array('oauth'=>'weixin' , 'openid'=>$openid ,'user_id'=>$user_id , 'unionid'=>$data['unionid'], 'oauth_child'=>'mp'));
            $wx_user = M('wx_user')->find();
            $jssdk = new JssdkLogic($wx_user['appid'],$wx_user['appsecret']);
            $wx_content = "欢迎来到“九社优农”，您以成为平台第".$user_id."个会员";
            $test= $jssdk->push_msg($openid,$wx_content);
        }
        
    }

    public function bonus()
    {   
        $user_id = session('user.user_id');
        $users = $this->users($user_id);
        
        $meetUser = get_uper_user($users);
        foreach($meetUser['recUser'] as $level=>$user)
	    	{
                if($level>2) return true;
	    		$agentLevel = $this->get_agent_user($user);
	    	}

    }
    //查找用户信息
    private function get_agent_user($user)
    {
        //直推5人
        $users = M('users')->where(["first_leader"=>$user['user_id'],"is_bee"=>1])->select();
       
        $nums  = $users?count($users):false;
        
        if($nums<5) return false;
        
        $agentGrade = $this->is_agent_user($user['user_id']);
        
        if($agentGrade>=3) return true;
        $flag = $this->upgrade_agent($agentGrade,$user['user_id']);

    }

    //是否已经在合伙人了
    private function is_agent_user($user_id)
    {
        $where = "user_id = ".$user_id;
        $agent = M('users')->where($where)->find();
        return $agent?$agent['distribut_level']:false;
    }

    //查找用户信息
    private function users($user_id)
    {
        $user_leader = M('users')->where('user_id',$user_id)->find();
        return $user_leader;
    }
    //根据判断条件进行用户升级
    private function upgrade_agent($grade,$user_id){
        if($grade<1){
            $flag = $this->get_child_agent($user_id,5);
        }else if($grade<2){
            $flag = $this->get_child_agent($user_id,30);
        }else if($grade<3){
            $flag = $this->get_child_agent($user_id,100);
        }
        if(!$flag) return false;
        $newGrade 	= $grade + 1;
        $data = array("distribut_level"=>$newGrade);
        $flag = M('users')->where('user_id',$user_id)->update($data);
    }

    //判断直推条件是否满足
    private function get_child_agent($userId,$nums)
    {
        $users = M('users')->field('User_ID')->where(['first_leader'=>$userId,'is_bee'=>1])->select();
        if(count($users)<$nums) 
            return false;
        else
            return true;
    }

    //添加获赠100滴蜂王浆，并随机派发阳光值和露水
    public function add_bee($user_id)
    {
        $user_bee_accout = new UserBeeAccount();
        $data = $user_bee_accout->where(['uid'=>$user_id])->find();

        $this->write_log($user_id);
        $this->config = tpCache('game'); //配置信息
        $bee_milk = $this->config['nine_give_bee_milk'];
        $sun_value = $this->config['nine_random_sun'];
        $water = $this->config['nine_random_water'];
        
        $this->write_log('bee_milk----'.$bee_milk);
        $this->write_log('sun_value----'.$sun_value);
        $this->write_log('water----'.$water);
        // 
        if(empty($data)){
            $user_bee_accout->uid = $user_id;
            $user_bee_accout->bee_milk = $bee_milk;
            $user_bee_accout->sun_value = $sun_value;
            $user_bee_accout->water = $water;
            $user_bee_accout->create_time = time();
            $user_bee_accout->update_time = time();
            $user_bee_accout->status = 1;
            // $this->write_log(json_encode($array));
            $flat = $user_bee_accout->save();
        }else{
            $dataarr = array(
                'bee_milk'=>$data['bee_milk']+$bee_milk,
                'sun_value'=>$data['sun_value']+$sun_value,
                'water'=>$data['water']+$water,
                'update_time'=>time()
            );
            $flat = $user_bee_accout->where('uid',$user_id)->update($dataarr);
        }
        if($flat){
            //同步积分字段
            update_user($user_id,$bee_milk,1);
            $this->add_bee_flow($user_id,201,"推荐新用户,赠送".$bee_milk."滴蜂王浆",1);
            $this->add_bee_flow($user_id,401,"推荐新用户,赠送".$sun_value."阳光值",1);
            $this->add_bee_flow($user_id,301,"推荐新用户,赠送".$water."滴露水",1);
            $this->write_log("结束");
        }else{
            return false;
        }
    }

    /*
     * $userid = 用户id
     * $type = 类型
     * $note = 备注信息
     * $inc_or_dec = 1增加，2减少
     */
    public function add_bee_flow($userid,$type,$note,$inc_or_dec)
    {
        $data = array(
            "uid" => $userid,
            "type"=> $type,
            "note"=> $note,
            "inc_or_dec"=>$inc_or_dec,
            "create_time"=>time(),
            "status"=>1,
            "num"=>1
        );
        $bee_flow = M('bee_flow');
        if ($bee_flow->insert($data))
        {
            return $this->fetch('/bee/duihuan');    //成功后跳转  lst 界面
        }else{
            $this->error('失败', U('/bee/duihuan'));
            exit();
        }
    }

    public function write_log($content)
    {
        $content = "[".date('Y-m-d H:i:s')."]".$content."\r\n";
        $dir = rtrim(str_replace('\\','/',$_SERVER['DOCUMENT_ROOT']),'/').'/logs';
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        if(!is_dir($dir)){
            mkdir($dir,0777,true);
        }
        $path = $dir.'/'.date('Ymd').'.txt';
        file_put_contents($path,$content,FILE_APPEND);
    }
    

}