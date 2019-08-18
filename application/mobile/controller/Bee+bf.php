<?php

namespace app\mobile\controller; 
use app\common\logic\GoodsPromFactory;
use app\common\logic\SearchWordLogic;
use app\common\logic\GoodsLogic;
use app\common\model\SpecGoodsPrice;
use think\AjaxPage;
use think\Page;
use think\Verify;
use think\Db;
use think\Cookie;
use think\Exception;
use app\mobile\controller\Code;

class Bee extends MobileBase {

    public $user_id = 0;

     /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        $this->config = tpCache('game');
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
        }
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express' , 'bind_guide', 'bind_account',
        );
        $is_bind_account = tpCache('basic.is_bind_account');
        if (!$this->user_id && !in_array(ACTION_NAME, $nologin)) {
            if(strstr($_SERVER['HTTP_USER_AGENT'],'MicroMessenger') && $is_bind_account){
                header("location:" . U('Mobile/User/bind_guide'));//微信浏览器, 调到绑定账号引导页面
            }else{
                header("location:" . U('Mobile/User/login'));
            }
            exit;
        }

        $user = session('user');
        $user = M('users')->where("user_id", $user['user_id'])->find();
        session('user', $user);  //覆盖session 中的 user
        $this->user = $user;
        $this->user_id = $user['user_id'];
        $this->assign('user', $user); //存储用户信息
        $this->assign('user_id', $this->user_id);
    }
    // 游戏加载页面
    public function index(){

       

        return $this->fetch('/bee/greet');
    }

    // 游戏主页
    public function beeIndex(){
        $code = new Code();
        $test = $code->bonus();
        $level = 0;
        $mating = 0;

        $bee = M('user_bee')->where(array('uid' => $this->user_id,'status'=>1))->select();
        foreach($bee as $key => $value){
            if($value['level'] == 1){
                $young = count($key);
            }
            // if($value['level'] == 2){
            //     $level = count($key);
            // }
            // if($value['is_mating'] == 1){
            //     $mating = count($key);
            // }
        }

        $user_prop = M('user_bee_account')->where(array('uid' => $this->user_id))->find();
        $user_bee = M('user_bee')->where(array('uid' => $this->user_id, 'status' => 1))->select(); //幼蜂
        $user_prop['young'] = $young; // 幼蜂
        // $user_prop['level'] = $level; // 蜂王
        // $user_prop['mating'] = $mating;
//        dump($user_prop['bee_milk']);exit;

        //最新公告内容
        $notice = M('article')
                ->order('article_id desc')
                ->field('title, content')
                ->where('cat_id', 9)
                ->where('is_open', 1)
                ->where('publish_time', '<', time())
                ->find();
        if($notice){
            //转换html标签
            $notice['content'] = htmlspecialchars_decode($notice['content']);
        }
        $this->assign('notice', $notice);
        $this->assign('user_prop', $user_prop);
        $this->assign('user_bee', $user_bee);

         //增加头像
         $head_pic = session('user.head_pic');
         $this->assign('head_pic',$head_pic);
         $this->assign('myUid',session('user.user_id'));
         
        return $this->fetch('/bee/index');
    }

    //设置
    public function setUp(){
        
        return $this->fetch();
    }

    public function type(){
        $user_id = session('user.user_id');
        $user_music = M("users")->where('user_id',$user_id)->find();
        return json_encode(array("music_type"=>$user_music['music_type']));
    }

    public function music_type()
    {
        // dump($_POST['type']);die;
        $user_id = session('user.user_id');
        $user_music = M("users")->where('user_id',$user_id)->find();
        if($_POST['type']){
            
            $data = array(
                "music_type" => $_POST['type']
            );
            $res = M('users')->where('user_id',$user_id)->update($data);
            if($res)
            {
                $msg = "关闭音乐成功";
                
            }else{
                $msg = "关闭音乐失败!!!";
            }
            // dump($res);
            return json_encode(array('msg'=>$msg,'music_type'=>$_POST['type']));
        }else{
            if(empty($user_music['music_type'])){
                $user_music['music_type'] = 0;
            }
            return json_encode(array("music_type"=>$user_music['music_type']));
        }
        
    }
    
    private function user_find($user_id)
    {
    	$where = "uid = '".$user_id."'";
    	$file = Db::name('user_bee_account')->where($where)->find();
    	return $file;
    }

    /*
    * 用户信息
    */ 
    public function userInfo(){
        return $this->fetch('/bee/user');
    }

    /*
    * 好友
    */ 
    public function beeFriend(){
        $user_id = session('user.user_id');
        $user = M('users')->where('first_leader',$user_id)->select();
        if ($user){
            $this->assign("users",$user);
        }
        $this->assign("myUid",$user_id);
        return $this->fetch('/bee/friend');
    }

    /*
     * 赠送好友蜂王浆
     */
    public function give_money()
    {
        if ($_POST){
            $user_id = session('user.user_id');
            //赠送人id
            $first_leader = $_POST['user_id'];
            //赠送多少蜂王浆
            $money_nums = $_POST['money'];

            $user = M("user_bee_account")->where('uid',$user_id)->find();
            if(empty($user)){
                $msg = "蜂王浆数量不足!!!";
                return json(array('msg'=>$msg,'type'=>0));
            }
            if($user['bee_milk']>=$money_nums)
            {
                //同步积分字段
                update_user($user_id,$money_nums,2);
                //减少
                $data_user = array(
                    "bee_milk" => $user['bee_milk']-$money_nums,
                    "update_time" => time()
                );
                $js_bee = M("user_bee_account")->where('uid',$user_id)->update($data_user);
                //增加
                $leader_user = M("user_bee_account")->where('uid',$first_leader)->find();
                if (empty($leader_user))
                {
                    $data = array(
                        "uid" => $first_leader,
                        "bee_hive" => 0,
                        "water" => 0,
                        "sun_value" => 0,
                        "gooey" => 0,
                        "drone" => 0,
                        "bee_milk" =>$money_nums,
                        "status" =>1,
                        "update_time" =>time(),
                        "create_time" =>time()
                    );
                    $zj_bee = Db('user_bee_account')->insert($data);
                }else{
                    $leader_data = array(
                        "bee_milk" => $leader_user['bee_milk']+$money_nums,
                        "update_time" => time()
                    );
                    $zj_bee = M("user_bee_account")->where('uid',$first_leader)->update($leader_data);
                }
                update_user($first_leader,$money_nums,1);
                if ($js_bee && $zj_bee){
                    $this->add_bee_flow($user_id,"222","赠送用户".$first_leader.",".$money_nums."滴蜂王浆",2);
                    $this->add_bee_flow($user_id,"222","用户".$first_leader."获得".$user_id."赠送的".$money_nums."滴蜂王浆",1);
                    $msg = "赠送用户".$first_leader.",".$money_nums."滴蜂王浆成功.";
                    return json(array('msg'=>$msg,'type'=>1));
                }else{
                    $msg = "赠送失败!!!";
                    return json(array('msg'=>$msg,'type'=>0));
                }
            }else{
                $msg = "蜂王浆数量不足!!!";
                return json(array('msg'=>$msg,'type'=>0));
            }
        }
    }
     
    /*
    * 邀请好友
    */ 
    public function beeInvite(){
        //传递快速注册的地址和分享用户的手机
//        $user_id = session('user.user_id');
//
//        $mobile  = M('users')->where('user_id', $user_id)->value('mobile');
//        $path    = $_SERVER['HTTP_HOST'];
//        $url     = 'http://' . $path . '/Mobile/User/reg?mobile=' . $mobile;
//        $this->assign('url', $url);

        $code = new Code();
        $img = $code->create_code();
        $this->assign('urlimg',$img);
        return $this->fetch('/bee/invite');
    }

     /*
    * 兑换
    */ 
    public function beeExchange()
    {
        $this->config = tpCache('game'); //配置信息
        //兑换所需蜜糖
//        $bee_num = $_POST['bee_num'];

        if ($_POST['exchange_nums'] == null){
            $_POST['exchange_nums'] =1;
        }
        //状态类型 1：兑换雄峰，2：兑换蜂王浆，3：兑换蜜糖，4：兑换露水，5：兑换阳光值
        $type = $_POST['type'];
        //用户id
        $user_id = session('user.user_id');
        // 实例化User对象
        $bee_account = M("user_bee_account");
        $bee_milk = $bee_account->where('uid',$user_id)->find();
        // var_dump($bee_milk);
        switch ($type) {
            case 1:
                $bee_num = $this->config['three_drip_bee_milk'];
//                var_dump($bee_num);die;
                if ($bee_milk['bee_milk'] < $bee_num){
                    $msg = "对不起，您的蜂王浆数量不足".$bee_num."滴!!";
                    return json(array('msg'=>$msg,'type'=>0));
                    exit();
                }
                $milk = $bee_milk['bee_milk']-$bee_num;
                $data = array(
                    "bee_milk"=>$milk,
                    "drone" =>$bee_milk['drone']+1
                );
                $msg = "恭喜您，".$bee_num."滴蜂王浆兑换一只雄峰成功";
                $update = $bee_account->where('uid',$user_id)->setField($data);
                update_user($user_id,$bee_num,2);

                if ($update)
                {
                    //增加蜂王浆兑换雄峰日志记录
                    $this->add_bee_flow($user_id,201,"减少".$bee_num."滴蜂王浆",2);
                    $this->add_bee_flow($user_id,501,"增加1只雄峰",1);
                }
                break;
            case 2:
                //蜜糖所需总数
                $nums = $this->config['eight_one_gooey']*$_POST['exchange_nums'];
                if ($bee_milk['gooey'] < $nums){
                    $msg = "对不起，您的蜜糖数量不足".$nums."克!!";
                    return json(array('msg'=>$msg,'type'=>0));
                    exit();
                }
                $gooey = $bee_milk['gooey']-$nums;
                $data = array(
                    "gooey"=>$gooey,
                    "bee_milk" =>$bee_milk['bee_milk']+$_POST['exchange_nums']
                );
                $msg = "恭喜您，".$nums."克蜜糖兑换".$this->config['eight_exchange_bee_milk']*$_POST['exchange_nums']."滴蜂王浆成功";
                $update = $bee_account->where('uid',$user_id)->setField($data);
                update_user($user_id,$this->config['eight_exchange_bee_milk']*$_POST['exchange_nums'],1);
                if ($update)
                {
                    //增加蜂王浆兑换雄峰日志记录
                    $this->add_bee_flow($user_id,801,"减少".$nums."蜜糖",2);
                    $this->add_bee_flow($user_id,201,"增加".$this->config['eight_exchange_bee_milk']*$_POST['exchange_nums']."滴蜂王浆",1);
                }
                break;
            case 3:
                //蜜糖所需总数
                $nums = $_POST['exchange_nums'];
                $num = $nums/$this->config['eight_one_gooey'];
                if(is_float($num))
                {
                    $msg = "对不起，无法进行兑换,蜜糖数量必须是".$this->config['eight_one_gooey']."的倍数";
                    return json(array('msg'=>$msg,'type'=>0));
                    exit();
                }
                if ($bee_milk['bee_milk'] < $this->config['eight_exchange_bee_milk']){
                    $msg = "对不起，您的蜂王浆数量不足".$this->config['eight_exchange_bee_milk']."滴!!";
                    return json(array('msg'=>$msg,'type'=>0));
                    exit();
                }
                $data = array(
                    "bee_milk"=>$bee_milk['bee_milk']-$num,
                    "gooey" =>$bee_milk['gooey']+$nums
                );
//                $note = $nums."克蜜糖浆兑".$this->config['eight_exchange_bee_milk']*$_POST['exchange_nums']."滴蜂王浆成功";
                $msg = "恭喜您，".$num."滴蜂王浆兑".$this->config['eight_exchange_bee_milk']*$_POST['exchange_nums']."滴克蜜糖成功";
                $update = $bee_account->where('uid',$user_id)->setField($data);
                update_user($user_id,$this->config['eight_exchange_bee_milk'],2);
                if ($update)
                {
                    //增加蜂王浆兑换雄峰日志记录
                    $this->add_bee_flow($user_id,801,"减少".$nums."蜂王浆",2);
                    $this->add_bee_flow($user_id,201,"增加".$this->config['eight_one_gooey']*$_POST['exchange_nums']."滴蜜糖",1);
                }
                break;
            case 4:
                //蜜糖所需总数
                $nums = $this->config['eight_two_gooey']*$_POST['exchange_nums'];
                if ($bee_milk['gooey'] < $nums){
                    $msg = "对不起，您的蜜糖数量不足".$nums."克!!";
                    return json(array('msg'=>$msg,'type'=>0));
                    exit();
                }
                $gooey = $bee_milk['gooey']-$nums;
                $data = array(
                    "gooey"=>$gooey,
                    "water" =>$bee_milk['water']+$_POST['exchange_nums']
                );
//                $note = $nums."克蜜糖浆兑".$this->config['eight_exchange_water']*$_POST['exchange_nums']."露水成功";
                $msg = "恭喜您，".$nums."克蜜糖浆兑".$this->config['eight_exchange_water']*$_POST['exchange_nums']."露水成功";
                $update = $bee_account->where('uid',$user_id)->setField($data);
                if ($update)
                {
                    //增加蜂王浆兑换雄峰日志记录
                    $this->add_bee_flow($user_id,801,"减少".$nums."蜜糖",2);
                    $this->add_bee_flow($user_id,201,"增加".$this->config['eight_exchange_water']*$_POST['exchange_nums']."滴露水",1);
                }
                break;
            case 5:
                //蜜糖所需总数
                $nums = $this->config['eight_three_gooey']*$_POST['exchange_nums'];
                if ($bee_milk['gooey'] < $nums){
                    $msg = "对不起，您的蜜糖数量不足".$nums."克!!";
                    return json(array('msg'=>$msg,'type'=>0));
                    exit();
                }
                $gooey = $bee_milk['gooey']-$nums;
                $data = array(
                    "gooey"=>$gooey,
                    "sun_value" =>$bee_milk['sun_value']+$_POST['exchange_nums']
                );
//                $note = $nums."克蜜糖浆兑".$this->config['eight_exchange_sun']*$_POST['exchange_nums']."阳光值成功";
                $msg = "恭喜您，".$nums."克蜜糖浆兑".$this->config['eight_exchange_sun']*$_POST['exchange_nums']."阳光值成功";
                $update = $bee_account->where('uid',$user_id)->setField($data);
                if ($update)
                {
                    //增加蜂王浆兑换雄峰日志记录
                    $this->add_bee_flow($user_id,801,"减少".$nums."蜜糖",2);
                    $this->add_bee_flow($user_id,201,"增加".$this->config['eight_exchange_sun']*$_POST['exchange_nums']."阳光值",1);
                }
                break;
            default:
        }
        return json(array('msg'=>$msg,'type'=>1));
    }

    public function duihuan(){
        return $this->fetch('/bee/duihuan');
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

    /*
    * 点击转盘获取奖励
    * 
    */
    public function rotate(){
        $type = 601;
        $userId = session('user.user_id');
        $isPrize = 0;
        $prize_num = M('bee_flow')->where('uid', $userId)->where('type', $type)->whereTime('create_time', 'today')->count();
       
        $prize_arr = array();
        $isConfig  = true;

        //获取蜂王浆的设置
        for ($i=0; $i < 5; $i++) { 
            $prizeMilk = $this->config['prize_bee_milk'.($i+1)];
            $rate = $this->config['prize_rate'.($i+1)];

            if ((!$prizeMilk && $prizeMilk !== 0) || (!$rate && $rate !== 0)) {
                $isConfig = false;
                break;
            }

            array_push($prize_arr, array('id'=>$i+1,'value'=>$prizeMilk,'prize'=>$prizeMilk.'滴蜂王浆','v'=>$rate));
        }

        array_push($prize_arr, array('id'=>6,'value'=>0,'prize'=>'下次没准就能中哦','v'=>$this->config['prize_rate6']));
        
        foreach ($prize_arr as $key => $val) {
            $arr[$val['id']] = $val['v'];
        }
        
        //判断后台是否设置奖项及设置是否完整
        if (!$isConfig || count($arr) < 1) {
            return json(array('is_prize' => $isPrize,'msg' => "还没有设置奖项"));
        }

        //判断是否有抽奖机会
        if ($prize_num>=$this->config['prize_count']) {
            // 获取users表里面的抽奖记录数，如果当天抽奖次数用完，则查看users里面是否存在可抽奖数
            $drawNum = M('users')->field('user_id,draw_num')->where('user_id', $this->user_id)->find();
            if($drawNum['draw_num']<=0){
                $msg = "你没有抽奖机会了哦";
                return json(array('is_prize'=>$isPrize,'msg'=>$msg));
            }
            if($drawNum['draw_num']>=1){
                $drawRes = M('users')->where('user_id', $this->user_id)->setDec('draw_num', 1);
            }
        }

        $rid = $this->getRand($arr);   //根据概率获取奖项id

        $res['prize'] = $prize_arr[$rid - 1]['prize'];    //中奖项
        // unset($ptize_arr[$rid - 1]);    //将中奖项从数组中剔除,剩下的未中奖项
        // shuffle($prize_arr);    //打乱数组顺序

        // for ($i=0; $i < count($prize_arr); $i++) { 
        //     $pr[] = $prize_arr[$i]['prize'];
        // }

        $isPrize = 1;
        $result = array('is_prize' => $isPrize,'prize' => $res['prize'],'id' => $rid,'milk' => $prize_arr[$rid-1]['value']);
        $bool   = true;

        if ($userId) {
            $data = array(
                'uid'         => $userId,
                'type'        => $type,
                'inc_or_dec'  => 1,
                'num'         => $prize_arr[$rid-1]['value'],
                'status'      => 1,
                'create_time' => time(),
                'note'        => "转盘抽中".$prize_arr[$rid-1]['value']."滴蜂王浆"
            );

            $res = M('bee_flow')->insert($data);
            
            //抽到蜂王浆计算到用户总蜂王浆数里
            if ($res) {
                $userBeeMilk = M('user_bee_account')->where('uid',$userId)->field('bee_milk')->find();

                if (!$userBeeMilk['bee_milk'] && $userBeeMilk['bee_milk'] !== 0) {
                    M('user_bee_account')->insert(
                        ['uid'=>$userId, 'bee_milk'=>$prize_arr[$rid-1]['value'],
                        'create_time'=>time(),
                        'update_time'=>time()]
                    );   
                } else {
                    M('user_bee_account')->where('uid',$userId)->update(['bee_milk'=>($prize_arr[$rid-1]['value']+$userBeeMilk['bee_milk']), 'update_time'=>time()]);
                }
                update_user($userId,$prize_arr[$rid-1]['value'],1);
            } else {
                $bool = false;
            }
        } else {
            $bool = false;
        }

        if (!$bool){
            unset($result);
            $result['is_prize'] = 0;
            $result['msg'] = "抽奖失败";
        }

        return json($result);
    }

    /*
    * 获取奖项
    */
    private function getRand($proArr)
    {
        $result = '';
        $proSum = array_sum($proArr);   //概率数组的总概率精度
        
        //概率数组循环
        foreach ($proArr as $key => $proCur) {

            $randNum = mt_rand(1, $proSum);
            if ($randNum <= $proCur){
                $result = $key;
                break;
            } else {
                $proSum -= $proCur;
            }
        }

        unset($proArr);

        return $result;
    }

    /*
    * 获取奖项名称
    */
    public function getPrizeText(){
        $result = array();
        for ($i=0; $i < 5; $i++) { 
            $drop = $this->config['prize_bee_milk'.($i+1)];
            array_push($result, $drop."滴蜂王浆");
        }

        array_push($result, "下次没准就能中哦");

        return json($result);
    }

    /**
     * 公告列表(消息)
     */ 
    public function news(){
        $noticeList = M('article')
                ->order('article_id desc')
                ->field('title, content')
                ->where('is_open', 1)
                ->where('cat_id',9)
                ->where('publish_time', '<', time())
                ->limit(10)
                ->select();
        //循环转换html标签
        foreach($noticeList as $key => $notice){
            $noticeList[$key]['content'] = htmlspecialchars_decode($notice['content']);
        }
        $this->assign('noticeList', $noticeList);
        return $this->fetch();
    }
    
    /**
     * 投诉页面
     */
    public function complaint(){
        return $this->fetch();
    }

    /**
     * 投诉处理
     */
    public function complaint_info(){
        $content = I('content');
        if($content){
            $data = array();
            $data['complain_content'] = $content;
            $data['user_id'] = session('user.user_id');
            $data['user_mobile'] = session('user.mobile');
            $data['complain_time'] = strtotime(date('Y-m-d H:i:s'));
            $result = M('complain')->insert($data);
            if($result){
                $this->ajaxReturn(1);
            }else{
                $this->ajaxReturn(0);
            }
        }else{
            $this->ajaxReturn(2);
        }
    }
}