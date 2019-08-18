<?php

namespace app\mobile\controller; 
// use app\common\logic\GoodsPromFactory;
use app\common\logic\SearchWordLogic;
use app\common\logic\GoodsLogic;
use think\AjaxPage;
use think\Page;
use think\Db;
use think\Cookie;
class GooeyExpend extends MobileBase {

    public $user_id = 0;
    public $user = array();
    public $config = array();
    public $accTime = 60 ; //3600

    public function _initialize()
    {
        parent::_initialize();
        $this->config = tpCache('game'); //配置信息
        if (session('?user')) {
           $user = session('user');
           $user = M('users')->where("user_id", $user['user_id'])->find();
           session('user', $user);  //覆盖session 中的 user
           $this->user = $user;
           $this->user_id = $user['user_id'];
           $this->assign('user', $user); //存储用户信息
           $this->assign('user_id', $this->user_id);
        } else {
           header("location:" . U('User/login'));
           exit;
        }
    }

    
    /*
    * Type: 804喂养蜂王浆，805喂养蜜糖，806蜂箱消耗阳光值，807蜜蜂消耗露水
    * 使用蜂王浆每天喂养蜂王
    * 喂养蜂王浆的数量后台设置
    */
    public function feedDebby(){
        
        // 判断当天是否达到后台设置次数
        $t = time();
        $start_time = mktime(0,0,0,date('m'),date('d'),date('Y'));;  //当天开始时间
        // 获取bee_flow记录判断当天是否已经喂养过一次
        // $where1['uid'] = $this->user_id;
        // $where1['type'] = 804; // 标记
        // $where1['create_time'] = ['>', $start_time]; 
        // $flowData = M('bee_flow')->where($where1)->count('id');
        // if($flowData){
        //     return json(['code'=>'-1','msg'=>'蜂王喂食过了哦']);
        // }
       
        $five_can_num = $this->config['five_can_num']?$this->config['five_can_num']:6;
        $where2['uid'] = $this->user_id;
        $where2['type'] = 804; // 标记
        $where2['create_time'] = ['>', $start_time]; 
        $getData = M('bee_flow')->where($where2)->count('id');
        if($getData>=$five_can_num){
            return json(['code'=>'-1','msg'=>'今天已超出最大操作次数...']);
        }

        // 判断当前用户的蜂王浆是否足够
        $beeMilkNum = $this->config['seven_bee_milk_days']?$this->config['seven_bee_milk_days']:1;

        $beeMilk = M('user_bee_account')->field('uid,bee_milk')->where(['uid'=>$this->user_id])->find();
        if($beeMilk['bee_milk']<$beeMilkNum){
            return json(['code'=>'-1','msg'=>'你的蜂王浆不足哦']);
        }

        // 执行喂养操作
        $decRes = M('user_bee_account')->where(['uid'=>$this->user_id])->setDec('bee_milk', $beeMilkNum);
        $resU = M('users')->where(['user_id'=>$this->user_id])->setDec('pay_points', $beeMilkNum); // 蜂王浆users表字段
        if(!$decRes){
            return json(['code'=>'-1','msg'=>'喂养失败,稍后再试']);
        }
        // 插入喂养记录
        $logs = array(
            'uid' => $this->user_id,
            'type' => 804,
            'inc_or_dec' => 2,
            'num' => $beeMilkNum,
            'create_time' => time(),
            'note' => '蜂王喂食蜂王浆'.$beeMilkNum.'滴'
        );
        $res = M('bee_flow')->save($logs);
        if($decRes&&$res){
            return json(['code'=>200,'msg'=>'喂养蜂王成功']);
        }else{
            return json(['code'=>'-1','msg'=>'喂养蜂王失败']);
        }
    }

    /*
    * Type: 804喂养蜂王浆，805喂养蜜糖，806蜂箱消耗阳光值，807蜜蜂消耗露水
    * 工蜂每天喂食50克蜜糖
    * 喂养数量后台设置
    */
    public function feedGooey(){

        // 获取bee_flow记录判断当天是否已经喂养过一次
        $flowData = M('bee_flow')->where(['uid'=>$this->user_id, 'type'=>805])->whereTime('create_time', 'today')->find();
        if($flowData){
            return json(['code'=>'-1','msg'=>'工蜂今天喂食过了哦']);
        }

        // 判断当天是否达到后台设置次数
        $t = time();
        $start_time = mktime(0,0,0,date('m'),date('d'),date('Y'));;  //当天开始时间
        $five_can_num = $this->config['five_can_num']?$this->config['five_can_num']:6;
        $where2['uid'] = $this->user_id;
        $where2['type'] = 805; // 标记
        $where2['create_time'] = ['>', $start_time]; 
        $getData = M('bee_flow')->where($where2)->count('id');
        if($getData>=$five_can_num){
            return json(['code'=>'-1','msg'=>'今天已超出最大操作次数...']);
        }
        // 判断当前用户的蜜糖是否足够
        $beeGooeyNum = $this->config['seven_gooey_days']?$this->config['seven_gooey_days']:1;

        $beeGooey = M('user_bee_account')->field('uid,gooey')->where(['uid'=>$this->user_id])->find();
        if($beeGooey['gooey']<$beeGooeyNum){
            return json(['code'=>'-1','msg'=>'你的蜜糖不足哦']);
        }



        // 执行喂养操作
        $decRes = M('user_bee_account')->where(['uid'=>$this->user_id])->setDec('gooey', $beeGooeyNum);
        if(!$decRes){
            return json(['code'=>'-1','msg'=>'喂养失败,稍后再试']);
        }
        // 插入喂养记录
        $logs = array(
            'uid' => $v['uid'],
            'type' => 805,
            'inc_or_dec' => 2,
            'num' => $beeGooeyNum,
            'create_time' => time(),
            'note' => '工蜂喂食蜜糖'.$beeGooeyNum.'克'
        );
        $res = M('bee_flow')->save($logs);
        if($decRes&&$res){
            return json(['code'=>200,'msg'=>'喂养工蜂成功']);
        }else{
            return json(['code'=>'-1','msg'=>'喂养工蜂失败']);
        }
    }

    /*
    * Type: 804喂养蜂王浆，805喂养蜜糖，806蜂箱消耗阳光值，807蜜蜂消耗露水
    * 蜂箱每天消耗10克阳光值
    * 喂养数量后台设置
    */
    public function feedSun(){

        // 获取bee_flow记录判断当天是否已经消耗过一次阳光值
        $flowData = M('bee_flow')->where(['uid'=>$this->user_id, 'type'=>806])->whereTime('create_time', 'today')->find();
        if($flowData){
            return json(['code'=>'-1','msg'=>'蜂箱今天已消耗过阳光值']);
        }
        // 判断当前用户的蜂王浆是否足够
        $beeSumNum = $this->config['seven_sun_days']?$this->config['seven_sun_days']:1;

        $beeSun = M('user_bee_account')->field('uid,sun_value')->where(['uid'=>$this->user_id])->find();
        if($beeSun['sun_value']<$beeSumNum){
            return json(['code'=>'-1','msg'=>'你的阳光值不足哦']);
        }

        // 执行消耗操作
        $decRes = M('user_bee_account')->where(['uid'=>$this->user_id])->setDec('sun_value', $beeSumNum);
        if(!$decRes){
            return json(['code'=>'-1','msg'=>'消耗阳光值失败,稍后再试']);
        }
        // 插入消耗记录
        $logs = array(
            'uid' => $v['uid'],
            'type' => 806,
            'inc_or_dec' => 2,
            'num' => $beeSumNum,
            'create_time' => time(),
            'note' => '蜂箱消耗阳光值'.$beeSumNum.'克'
        );
        $res = M('bee_flow')->save($logs);
        if($decRes&&$res){
            return json(['code'=>200,'msg'=>'蜂箱消耗阳光值成功']);
        }else{
            return json(['code'=>'-1','msg'=>'蜂箱消耗阳光值失败']);
        }
    }


    /*
    * Type: 804喂养蜂王浆，805喂养蜜糖，806蜂箱消耗阳光值，807蜜蜂消耗露水
    * 蜜蜂每天消耗10滴露水
    * 喂养数量后台设置
    */
    public function feedWater(){

        // 获取bee_flow记录判断当天是否已经消耗过一次阳光值
        $flowData = M('bee_flow')->where(['uid'=>$this->user_id, 'type'=>807])->whereTime('create_time', 'today')->find();
        if($flowData){
            return json(['code'=>'-1','msg'=>'蜜蜂今天已消耗过露水']);
        }
        // 判断当前用户的蜂王浆是否足够
        $beeWaterNum = $this->config['seven_sun_days']?$this->config['seven_sun_days']:1;

        $beeWater = M('user_bee_account')->field('uid,water')->where(['uid'=>$this->user_id])->find();
        if($beeWater['water']<$beeWaterNum){
            return json(['code'=>'-1','msg'=>'你的露水数量不足哦']);
        }

        // 执行消耗操作
        $decRes = M('user_bee_account')->where(['uid'=>$this->user_id])->setDec('water', $beeSumNum);
        if(!$decRes){
            return json(['code'=>'-1','msg'=>'消耗露水失败,稍后再试']);
        }
        // 插入消耗记录
        $logs = array(
            'uid' => $v['uid'],
            'type' => 807,
            'inc_or_dec' => 2,
            'num' => $beeWaterNum,
            'create_time' => time(),
            'note' => '蜜蜂消耗露水'.$beeWaterNum.'克'
        );
        $res = M('bee_flow')->save($logs);
        if($decRes&&$res){
            return json(['code'=>200,'msg'=>'蜜蜂消耗露水成功']);
        }else{
            return json(['code'=>'-1','msg'=>'蜜蜂消耗露水失败']);
        }
    }

    /*
    * 打扫操作60分钟内只能操作一次 808打扫，809守卫
    */
    public function actionSweep(){

        $where1['uid'] = $this->user_id;
        $where1['is_oviposition'] = 2; // 已孵化
        $where1['depart_num'] = ['<', 60]; // 采蜜次数少于60的
        // 获取一条满足记录
        $data = M('user_bee')->where($where1)->find();
        if(!$data){
           return json(['code'=>'-1','msg'=>'暂不需要打扫']);
        }

        // 查询60分钟内是否打扫过
        $where['uid'] = $this->user_id;
        $where['type'] = 808;
        $checkOne = M('bee_flow')->field('uid,create_time')->where($where)->order('create_time desc')->find();
        $checkTime = $checkOne['create_time']+$this->accTime; // +1小时
        if(time()<$checkTime){
            return json(['code'=>'-1','msg'=>'已打扫过...']);
        }

        $checkData = array(
            'uid' => $this->user_id,
            'type' => 808,
            'create_time' => time(),
            'note' => '打扫一次'
        );
        $res = $this->insert_log($checkData);      
        if($res){
            return json(['code'=>200,'msg'=>'打扫成功']);
        }else{
            return json(['code'=>'-1','msg'=>'打扫失败,稍后再试']);
        }
    }

    /*
    * 守卫操作60分钟内只能操作一次 808打扫，809守卫
    */
    public function actionGuard(){

        $where1['uid'] = $this->user_id;
        $where1['is_oviposition'] = 2; // 已孵化
        $where1['depart_num'] = ['<', 60]; // 采蜜次数少于60的
        // 获取一条满足记录
        $data = M('user_bee')->where($where1)->find();
        if(!$data){
           return json(['code'=>'-1','msg'=>'暂不需要守卫']);
        }

        // 查询60分钟内是否守卫过
        $where['uid'] = $this->user_id;
        $where['type'] = 809;
        $checkOne = M('bee_flow')->field('uid,create_time')->where($where)->order('create_time desc')->find();
        $checkTime = $checkOne['create_time']+$this->accTime; // +1小时
        if(time()<$checkTime){
            return json(['code'=>'-1','msg'=>'正在守卫...']);
        }

        $checkData = array(
            'uid' => $this->user_id,
            'type' => 809,
            'create_time' => time(),
            'note' => '守卫一次'
        );
        $res = $this->insert_log($checkData);      
        if($res){
            return json(['code'=>200,'msg'=>'守卫成功']);
        }else{
            return json(['code'=>'-1','msg'=>'守卫失败,稍后再试']);
        }
    }
    


    
    // 道具日志流水
    public function insert_log($data)
    {
        $res = M('bee_flow')->save($data);
        return $res;
    }

    // 返回客户端数据信息
    public function msg($code, $msg, $data=''){

        $res = [
            'data'=>$data,
            'code'=>1,
            'msg'=>'操作完成'
        ];
        return json($res);
    }

}