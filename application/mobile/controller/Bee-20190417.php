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
        //是否有工蜂
        $is_workbee=0;
        //是否有侦查蜂
        $is_scoutbee=0;
        //蜂王数量
        $queen_num=0;
        $queen_num = M('user_bee')->where(['uid'=>$this->user_id, 'level'=>2,'die_status'=>0])->count();
        $bee = M('user_bee')->where(array('uid' => $this->user_id,'status'=>1,'die_status'=>0))->select();
        foreach($bee as $key => $value){
            if($value['level'] == 1){
                $young = count($key);
            }
            if($value['worker_bee']>0){
                $is_workbee=1;
            }
            if($value['scout_bee']){
                $is_scoutbee=1;
            }
            // if($value['level'] == 2){
            //     $level = count($key);
            // }
            // if($value['is_mating'] == 1){
            //     $mating = count($key);
            // }
        }

        $user_prop = M('user_bee_account')->where(array('uid' => $this->user_id))->find();
        $user_bee = M('user_bee')->where(array('uid' => $this->user_id, 'status' => 1,'die_status'=>0))->select(); //幼蜂
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
        if(empty($user_bee)){
//            $user_bee
        }
        $this->assign('notice', $notice);
        $this->assign('user_prop', $user_prop);
        $this->assign('user_bee', $user_bee);

         //增加头像
         $head_pic = session('user.head_pic');
         $this->assign('head_pic',$head_pic);
         $this->assign('myUid',session('user.user_id'));

         $this->assign('is_workbee',$is_workbee);
         $this->assign('is_scoutbee',$is_scoutbee);

         //距离丰收节还有几天
        $days=60-M('bee_flow')->where(['type'=>701,'uid' => $this->user_id])->count();
        $this->assign('day',$days);

        //防止重复提交
        $token=mt_rand(888,88888888);
        session('token',$token);
        $this->assign('token',$token);

        //查询该用户有没有派遣过侦查蜂
        $is_send=M('bee_flow')->where(['type'=>702,'uid'=>$this->user_id])->count();
        $this->assign('is_send',$is_send);
        //查询该用户有没有派遣过工蜂采蜜
        $is_work=M('bee_flow')->where(['type'=>701,'uid'=>$this->user_id])->count();
        $this->assign('is_work',$is_work);
        //查询第一次采蜜之后有没有酿蜜
        if(isset($is_work) && $is_work==1){
            $is_make=M('bee_flow')->where(['type'=>802,'uid'=>$this->user_id])->count();
            $this->assign('is_make',$is_make);
        }
        //获取额外抽奖次数
        $draw_num=M("users")->where(['user_id'=>$this->user_id])->field('draw_num')->find();
        $this->assign('draw_num',$draw_num['draw_num']);

        $this->assign('queen_num',$queen_num);


         
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
        $user=array();
        $my_user=array();
        $other_user=array();
        $user = M('users')->where('first_leader',$user_id)->select();
        $my_user = M('users')->where('user_id',$user_id)->find();
        $other_user = M('users')->where('user_id',$my_user['first_leader'])->select();
        $user=array_merge($user,$other_user);
        if ($user){
//            foreach ($user as $key=>$value){
//                $res=M('user_code')->where(['id'=>$value['user_id']])->find();
//                if(isset($res) && !empty($res)){
//                    $user[$key]['head_pic']=$res['img'];
//                }
//            }
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
                //同步积分字段   20190326  客户改需求
//                update_user($user_id,$money_nums,2);
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
//                update_user($first_leader,$money_nums,1);
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
//                update_user($user_id,$bee_num,2);

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
//                update_user($user_id,$this->config['eight_exchange_bee_milk']*$_POST['exchange_nums'],1);
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
//                update_user($user_id,$this->config['eight_exchange_bee_milk'],2);
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
//                update_user($userId,$prize_arr[$rid-1]['value'],1);
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
    //ajax看uid是否存在
    public function ajax_uid_find()
    {
        $user_id=I('uid');
        $self_id=session('user.user_id');
//        var_dump($self_id);die;
        if(empty($user_id) || $user_id<=0 || $user_id==$self_id){
            $this->ajaxReturn(0);
        }
        $where = "uid = '".$user_id."'";
        $file = Db::name('user_bee_account')->where($where)->find();
        if(empty($file)){
            $this->ajaxReturn(0);
        }else{
            $this->ajaxReturn(1);
        }

    }
    //ajax看余额是否充足
    public function ajax_user_money_find(){
        $user_id=I('uid');
        $user_money=I('user_money');
        $self_id=session('user.user_id');
        if(empty($user_id) || $user_id<=0 || empty($user_money) || $user_money<=0 || $user_id==$self_id){
            $this->ajaxReturn(0);
        }
        $where = "uid = '".$self_id."' and bee_milk>='".$user_money."'";
        $file = Db::name('user_bee_account')->where($where)->find();
//        $this->ajaxReturn($file);
        if(empty($file)){
            $this->ajaxReturn(0);
        }else{
            $this->ajaxReturn(1);
        }
    }
    //转账操作
    public function ajax_transfer_accounts(){
        //防止重复提交
        $token=I('token');
//        var_dump($_SESSION);
//        var_dump($token);die;
        if($token!=session('token')){
            header('location:'.U('Mobile/Bee/beeIndex'));
        }
        $user_id=I('uid');
        $self_id=session('user.user_id');
//        var_dump($self_id);die;
        $user_money=I('user_money');
        if(empty($user_id) || $user_id<=0 || empty($user_money) || $user_money<=0 || $user_id==$self_id){
            $this->ajaxReturn(0);
        }
        //开启事务
        Db::startTrans();
        if($user_money==1){
            $result1=M('user_bee_account')->where(['uid'=>$user_id])->setInc('bee_milk');
            $result2=M('user_bee_account')->where(['uid'=>$self_id])->setDec('bee_milk');
        }else{
            $result1=M('user_bee_account')->where(['uid'=>$user_id])->setInc('bee_milk',$user_money);
            $result2=M('user_bee_account')->where(['uid'=>$self_id])->setDec('bee_milk',$user_money);
        }
        //同时处理会员消费积分
//        $result5=update_user($user_id,$user_money,1);
//        $result6=update_user($self_id,$user_money,2);
        $data = array();
        $data['uid'] = $user_id;
        $data['type'] = 810;
        $data['inc_or_dec'] = 1;
        $data['num'] = $user_money;
        $data['create_time']=time();
        $data['note']='用户ID为'.$self_id.'向我转账蜂王浆'.$user_money.'个';
        $result3 = M('bee_flow')->insert($data);
        $data1 = array();
        $data1['uid'] = $self_id;
        $data1['type'] = 810;
        $data1['inc_or_dec'] = 2;
        $data1['num'] = $user_money;
        $data1['create_time']=time();
        $data1['note']='我向ID为'.$user_id.'的用户转账蜂王浆'.$user_money.'个';
        $result4 = M('bee_flow')->insert($data1);
//        echo $result1."~~~~~~~~~~~~~".$result2."~~~~~~~~~~~~~~~~~".$result3."~~~~~~~~~~~~~~~~~~~~".$result4;die;
        if($result1 && $result2 && $result3 && $result4){
            Db::commit();
            $this->ajaxReturn(1);
        }else{
            Db::rollback();
            $this->ajaxReturn(0);
        }
    }
    //转账记录
    public function ajax_transfer_accounts_log(){
        $self_id=session('user.user_id');
        $p=I('page',1);
        $count =  M('bee_flow')->where(['uid'=>$self_id])->count();
        $pagesize = C('PAGESIZE');  //每页显示数
        $page = new Page($count,$pagesize); // 实例化分页类 传入总记录数和每页显示的记录数
        $show = $page->show();  // 分页显示输出
        $this->assign('page',$show);    // 赋值分页输出
        $list=M('bee_flow')->where(['uid'=>$self_id,'inc_or_dec'=>2,'type'=>810])->order('create_time desc')->page($p, 20)->select();
        $startnum=($p-1)*20+1;
        foreach($list as $key=>$value){
            $list[$key]['ordernum']=$startnum;
            $startnum++;
        }
        $this->assign('list',$list);
//        var_dump($show);
//        var_dump($page);die;
        if(I('is_ajax')){
            $this->ajaxReturn($list);
        }else{
            return $this->fetch('bee/record');
        }

    }
    // 喂养工蜂
    public function bee_feed()
    {
        //查询一下有没有工蜂
        $bee_num=M('user_bee')->where(['uid'=>$this->user_id,'status'=>1,'die_status'=>0])->where('worker_bee','>','0')->where('depart_num','<','60')->count();
        if(!$bee_num){
            $data['msg']='你还没工蜂哦！';
            exit(json_encode($data));
        }
        //查一下喂养的次数是否超过了最大限制
        $start_time=time()-86400;
        $feed_where['create_time']=array('gt',strtotime(date('Y-m-d 00:00:00',time())));
        $feed_num=M('bee_flow')->where(['uid'=>$this->user_id,'type'=>811,'status'=>1])->where($feed_where)->count();
//        var_dump($feed_num);
        if($feed_num>$this->config['five_can_num']){
            $data['msg']='今天的喂养次数已经超过了最大限制！';
            exit(json_encode($data));
        }
        $feed_where['create_time']=array('gt',$start_time);
        //先喂养蜂王才能喂养工蜂
        $feed_time=M('bee_flow')->where(['uid'=>$this->user_id,'type'=>811,'status'=>1])->find();
        if(isset($feed_time)){
            $fw_num=M('bee_flow')->where(['uid'=>$this->user_id,'type'=>804,'status'=>1])->where($feed_where)->count();
            if($fw_num==0){
                $data['msg']='请先喂养蜂王！';
                exit(json_encode($data));
            }
        }else{
            $fw_num1=M('bee_flow')->where(['uid'=>$this->user_id,'type'=>804,'status'=>1])->where($feed_where)->count();
            if($fw_num1==0){
                $data['msg']='请先喂养蜂王！';
                exit(json_encode($data));
            }
        }
        //判定一次喂养之后有没有进行采蜜等操作
        $last_feed=M('bee_flow')->where(['uid'=>$this->user_id,'type'=>811,'status'=>1])->where($feed_where)->order('create_time desc')->find();
        if(isset($last_feed)){
            $gather_where['create_time']=array('gt',$last_feed['create_time']);
            $last_gather=M('bee_flow')->where(['uid'=>$this->user_id,'type'=>701,'status'=>1])->where($gather_where)->count();
            if($last_gather==0){
                $data['msg']='已经完成喂养，请采蜜过后再来喂养';
                exit(json_encode($data));
            }
        }

        $where['worker_bee']=array('neq','0');
        $bee = M('user_bee')->where(array('uid' => $this->user_id, 'status' => 1,'die_status'=>0))->where($where)->count();
        if($bee >= 1){
            //看看有没有之前喂养过的工蜂
            $bee_number=0;//需要喂养的蜂王的个数
            $bees = M('user_bee')->where(['uid'=>$this->user_id,'status'=>1,'die_status'=>0])->where('worker_bee','>','0')->where('depart_num','<','60')->select();
            foreach($bees as $key=>$value){
                $feed_times=M('bee_flow')->where(['uid'=>$value['uid'],'bid'=>$value['id'],'type'=>811])->where($feed_where)->order('create_time desc')->find();
                if(isset($feed_times)){
                    $honey_times=M('bee_flow')->where(['uid'=>$value['uid'],'bid'=>$value['id'],'type'=>701])->where('create_time','>',$feed_times['create_time'])->count();
                    if($honey_times!=0){
                        $bee_number++;
                    }
                }else{
                    $bee_number++;
                }
            }
            $bee_num=$bee_number;
            $level = M('user_bee')->where(array('uid' => $this->user_id,'level' => 1, 'status' => 1,'die_status'=>0))->find();
            $user_prop = M('user_bee_account')->where(array('uid' => $this->user_id))->find();

            if($user_prop['gooey'] < $this->config['seven_gooey_days']*$bee_num){
                $data['msg'] = '您的蜜糖不足'.$this->config['seven_gooey_days']*$bee_num.'克！';
                exit(json_encode($data));
            }
            if($user_prop['sun_value']<$this->config['seven_sun_days']*$bee_num){
                $data['msg'] = '您的阳光不足'.$this->config['seven_sun_days']*$bee_num;
                exit(json_encode($data));
            }
            if($user_prop['water']<$this->config['seven_water_days']*$bee_num){
                $data['msg'] = '您的露水不足'.$this->config['seven_water_days']*$bee_num.'滴！';
                exit(json_encode($data));
            }

            //事务
            Db::startTrans();
            //20190328 变更需求为喂养工蜂消耗阳光和露水
            $result3=M('user_bee_account')->where(['uid'=>$this->user_id])->setDec('sun_value',$this->config['seven_sun_days']*$bee_num);
            $result4=M('user_bee_account')->where(['uid'=>$this->user_id])->setDec('water',$this->config['seven_water_days']*$bee_num);

            $prop = [
                'gooey' => $user_prop['gooey'] - $this->config['seven_gooey_days']*$bee_num, //蜜糖
                'update_time' => time()
            ];
            $result1=M('user_bee_account')->where(array('uid' => $this->user_id))->save($prop);

            //道具日志
            $log = ['bid' => $level['id'],
                    'uid' => $this->user_id,
                    'type' => 811,
                    'inc_or_dec' => 2,
                    'num' => $this->config['seven_gooey_days']*$bee_num,
                    'create_time' => time(),
                    'note' => '喂养工蜂'];
//                $this->prop_log($log);
            $result2 = M('bee_flow')->insert($log);
//            $result4 = M('bee_flow')->insert($data1);
//            echo $result1."````".$result2;die;
            if($result1 && $result2 && $result3 && $result4){
                Db::commit();
                $data = ['status' => 1, 'msg' => '喂养成功，消耗'.$this->config['seven_gooey_days']*$bee_num.'克蜜糖和'.$this->config['seven_sun_days']*$bee_num.'阳光和'.$this->config['seven_water_days']*$bee_num.'露水'];
                exit(json_encode($data));
            }else{
                Db::rollback();
                $data = ['status' => 1, 'msg' => '喂养失败'];
                exit(json_encode($data));
            }
        }else{
            $data['msg'] = '您暂时没有工蜂！';
            exit(json_encode($data));
        }
    }
}