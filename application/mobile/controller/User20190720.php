<<<<<<< HEAD
<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * 2015-11-21
 */
namespace app\mobile\controller;

use app\common\logic\CartLogic;
use app\common\logic\Message;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\model\MenuCfg;
use app\common\model\UserAddress;
use app\common\model\Users as UserModel;
use app\common\model\UserMessage;
use app\common\util\TpshopException;
use app\common\model\UserWaitEarnings as Earnings;
use think\Cache;
use think\Page;
use think\Request;
use think\Verify;
use think\Loader;
use think\db;
use think\Image;
use think\Session;

class User extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $User = new UserModel();
            $session_user = session('user');
            $this->user = $User->where('user_id', $session_user['user_id'])->find();
            if(!empty($this->user->auth_users)){
                $session_user = array_merge($this->user->toArray(), $this->user->auth_users[0]);
                session('user', $session_user);  //覆盖session 中的 user
            }
            $this->user_id = $this->user['user_id'];
            $this->assign('user', $this->user); //存储用户信息0
        }
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express' , 'bind_guide', 'bind_account','bind_reg','getPhoneVerify','record_again'
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

        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $Earnings = new Earnings;
        $wait_earnings = $Earnings->where('user_id',$this->user_id)->find();

        //不在同一天清空待收益
        if ($wait_earnings) {
            $today = intval(date('Ymd'));
            $time = intval(date('Ymd',strtotime($wait_earnings['update_time'])));

            if ($today != $time) {
                $wait_earnings->money = 0;
                $wait_earnings->obj = null;

                $wait_earnings->save();
            }
        }

        $this->assign('order_status_coment', $order_status_coment);
    }


    //     //推荐
    //     public function recommend($share_user)
    //     {
    //         //获取上级id
           
    //       //   $recommend_id=19945;
    //         $recommend_id=$share_user;
    
    //         $user_id=$this->user_id;
    //         //判断自己是否已经有直属上级
    //         $myInfo=Db::name('users')->where('user_id','=',$user_id)->find();
    //         if($myInfo['first_leader']){
    //             $this->error("已经有上级不能在被推荐");
    //         }
    //         $time=time();
    //         // $firstUpdate=Db::name('users')->update(['user_id'=>$user_id,'first_leader'=>$recommend_id]);
    
    //         //推荐成功 统计上级的直属下级数量 更新上级的身份  经理还是总监
    //         $upPopCount=Db::name('users')->where('first_leader','=',$recommend_id)->count(); 
        
    //       $manager_ind_sum=$this->popUpdateCondition(1);  //升级经理的条件
    //       $chief_ind_sum=$this->popUpdateCondition(2);    //升级总监的条件
    //       $ceo_ind_sum=$this->popUpdateCondition(3);
    //       $partner_ind_sum=$this->popUpdateCondition(4);
  
    //         if($upPopCount>=$manager_ind_sum&&$upPopCount<$chief_ind_sum){
    //             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>1]);
    //         }
    //         if($upPopCount>=$chief_ind_sum&&$upPopCount<$ceo_ind_sum){
    //             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>2]);
    //         }
    //         if($upPopCount>=$ceo_ind_sum&&$upPopCount<$partner_ind_sum){
    //             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>3]);
    //         }
    //         if($upPopCount>=$partner_ind_sum){
    //             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>4]);
    //         }
    
    
    //         // if($firstUpdate){
    //                               // return $this->success("直属上级推荐成功");
    //             $recommendInfo=Db::name('users')->where('user_id',$recommend_id)->find();
    //                 if($recommendInfo['agent_level']){
    //                     //如果上级是代理身份,就给上级奖励   //并减少对应的推广额度
    
    //                     //这里有个条件前提   周数和业绩   不同要求不一样
    //                         $pop_commission=Db::name('config')->where('name','=','pop_commission')->value('value');
    //                         $pop_money=Db::name('config')->where('name','=','pop_money')->value('value');
    //                         $addmoney=$pop_money*$pop_commission/100;
    //                         $user_money=$recommendInfo['user_money']+$addmoney;
    //                         Db::name('users')->update(['user_id'=>$recommend_id,'user_money'=>$user_money]);
    //                         Db::name('account_log')->insert(['user_id'=>$recommend_id,'user_money'=>$addmoney,'pay_points'=>0,'change_time'=>$time,'desc'=>'邀请1个新会员奖励50','type'=>2]);
                        
    //                         $whereStr['user_id']=['=',$recommendInfo['user_id']];
    //                         $whereStr['period']=['=',$recommendInfo['default_period']];
    //                         $periodInfo=Db::name('pop_period')->where($whereStr)->find();
    //                         if($periodInfo['begin_time']){  //如果时间已经开始再操作下面
    //                             if($periodInfo['poped_per_num']<$periodInfo['person_num']){ //还有位置就操作
    //                                 Db::name('pop_period')->where($whereStr)->setInc('poped_per_num');
    //                             }else{ //没有位置就跳到上一级    如果没有上一级就修改用户表    
    //                                 $upPeriod=$recommendInfo['default_period']+1; 
    //                                 $upPeriodInfo=Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->find();
    //                                 if($upPeriodInfo){ //如果有上级
    //                                     //还有下一期的话 分情况   一周内 和一周外
    //                                     if(($periodInfo['begin_time']+3600*24*7)>$time){
    //                                             Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['day_release'=>1]);
    //                                     }else{
    //                                             Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['week_release'=>1]);
    //                                     }
    //                                     // Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['poped_per_num'=>1,'begin_time'=>$time]);
    //                                     // Db::name('users')->where('user_id','=',$recommendInfo['user_id'])->update(['default_period'=>$upPeriod]);
    //                                 }else{
    //                                     Db::name('users')->where('user_id','=',$recommendInfo['user_id'])->update(['agent_level'=>0,'default_period'=>0,'add_agent_time'=>0]);
    //                                 }
    
    //                             }
                               
    //                         }
                           
    //                 }                
    
    //         // }
    //     }
  
    //     //会员升级条件     //符合推广人数   就升级 如：经理
    //     public function popUpdateCondition($levelNum)
    //     {
    //       $ind_goods_sum=Db::name('agent_level')->where('level','=',$levelNum)->value("ind_goods_sum");
    //       return $ind_goods_sum;
    //     }
  
      //每日定时释放推广名额
      public function day_release_handle()
      {
          $dayList=Db::name('pop_period')->where('day_release','=',1)->select();
          $time=time();
          foreach($dayList as $dk=>$dv){
              Db::name('users')->where('user_id','=',$dv['user_id'])->setInc('default_period');
              Db::name('pop_period')->where('user_id','=',$dv['user_id'])->where('period','=',$dv['period'])->update(['begin_time'=>$time,'day_release'=>0]);
          }
      }
  
      //每周定时释放推广名额
      public function week_release_handle()
      {
          $dayList=Db::name('pop_period')->where('week_release','=',1)->select();
          $time=time();
          foreach($dayList as $dk=>$dv){
              Db::name('users')->where('user_id','=',$dv['user_id'])->setInc('default_period');
              Db::name('pop_period')->where('user_id','=',$dv['user_id'])->where('period','=',$dv['period'])->update(['begin_time'=>$time,'week_release'=>0]);
          }
      }
  
    

       //每月定时发放极差奖领导奖  优化方法
       public function team_bonus(){
            $allUserPerformace=Db::name('users')->alias('u')->join('agent_performance ap','u.user_id=ap.user_id',LEFT)->field('u.leader_level,u.user_id,u.mobile,u.nickname,,u.distribut_money,ap.ind_per,ap.agent_per')->where('leader_level','<>','0')->select();
            $time=time();
            if($allUserPerformace){
                $accountLogModel=Db::name('account_log');
                foreach($allUserPerformace as $ak=>$av){
                    $one_agent_level=Db::name('agent_level')->where('level','=',$av['leader_level'])->find();
                    if($av['agent_per']>=$one_agent_level['describe']){
                        $bonus=$av['agent_per']*$one_agent_level['retio']/100;
                        $addDistribut=$av['distribut_money']+$bonus;
                        if($av['leader_level']==4){
                            $accountLogModel->insert(['user_id'=>$av['user_id'],'user_money'=>$addDistribut,'pay_points'=>0,'change_time'=>$time,'desc'=>'奖励豪车','type'=>6]);
                        }else{
                            $bonus=$av['agent_per']*$one_agent_level['ratio']/100;
                            $addDistribut=$av['distribut_money']+$bonus;
                            Db::name('users')->where('user_id','=',$av['user_id'])->update(['distribut_money'=>$addDistribut]);
                            $accountLogModel->insert(['user_id'=>$av['user_id'],'user_money'=>$addDistribut,'pay_points'=>0,'change_time'=>$time,'desc'=>'级差奖领导奖','type'=>5]);
                        }
                    }
                }
            }
       }


      

    public function index()
    {
        $user_id = $this->user_id;
        $agent_level = M('agent_level')->field('level,level_name')->select();
        // dump($agent_level);
        if($agent_level){
            foreach($agent_level as $v){
                $agnet_name[$v['level']] = $v['level_name'];
            }
            // dump($agnet_name);
            $this->assign('agnet_name', $agnet_name);
        }

        $MenuCfg = new MenuCfg();
        $menu_list = $MenuCfg->where('is_show', 1)->order('menu_id asc')->select();

        $app_config = Db::name('config')->where(['inc_type'=>'shop_info','name'=>['in','android_app_url,ios_app_url']])->field('name,value')->select();
        if($app_config){
            foreach($app_config as $v){
                if($v['value']){
                    $app[$v['name']] = $v['value'];
                }
            }
            if(isset($app)){
                $this->assign('app', $app);
            }
        }
        //用户余额
        $user_money = Db::name('users')->where(['user_id'=>$user_id])->field('user_money,user_id')->find();

        //推广人数
        $pop_periods = Db::name('pop_period')->where(['user_id'=>$user_id])->select();
        $person_num = 0;
        $poped_per_num = 0;
        foreach($pop_periods as $key => $veal){
            $person_num += $veal['person_num'];
            $poped_per_num += $veal['poped_per_num'];
        }
        //总佣金
        $distribut_money = Db::name('account_log')->where(['user_id'=>$user_id,'type'=>['in',[2,3,4,5]]])->sum(user_money);
        $comm2 = Db::name('commission_log')->where(['user_id' => $user_id])->order('id','desc')->sum('money');

        //今日佣金
        $comm = $this->today_commission();

        $is_vip = $this->user['end_time'] > time()?1:0;
        $this->user['today_comm'] = $comm;
        $this->assign('person_num', $person_num-$poped_per_num);
        $this->assign('user_money', $user_money);
        $this->assign('menu_list', $menu_list);
        $this->assign('distribut_money', $distribut_money+$comm2);
        $this->assign('comm', $comm);
        $this->assign('is_vip', $is_vip);
        $this->assign('mobile_validated', $this->user['mobile'] ? 0 : 1);
        
        return $this->fetch();
    }

    //获取今天佣金
    public function today_commission()
    {
        $user_id = $this->user_id;
        $where['to_user_id'] = $user_id;
        $where['type'] = ['in',[1,2,3]];
        $day_account_log = Db::name('account_log')->where(['user_id'=>$user_id,'type'=>['in',[2,3,4,5]]])->whereTime('change_time','today')->sum(user_money);
        // $comm = Db::name('distrbut_commission_log')->where($where)->order('log_id','desc')->whereTime('create_time','today')->sum('money');
        // $vip  = Db::name('vip_commission_log')->where(['to_user_id' =>$user_id ])->order('log_id','desc')->whereTime('create_time','today')->sum('money');
        //邀请奖励
        $comm2 = Db::name('commission_log')->where(['user_id' => $user_id])->order('id','desc')->whereTime('addtime','today')->sum('money');

        $money = $comm2 + $day_account_log;
        return $money;
    }

    /**
     * 生成会员卡信息
     * @param $user_id
     * @return int 1 表示成功 ； 0 表示失败
     */
    public static function info($user_id){
        $company_name = '健康商城';
        $image = '';//图片路径
        $card_no = 'JKSC'.time().$user_id;
        $photoshop = new Photoshop();
        $path = $photoshop->getPosterPhoto($user_id,$card_no);
        $data = [
            'user_id'=>$user_id,
            'card_no'=>$card_no,
            'card_image'=>$path,
            'use_count'=>0,
            'total_count'=>0,
            'not_use_count'=>0,
            'create_time'=>time(),
        ];

        $result = Db::name('user_card')->insert($data);
        if($result){
            return 1;
        }
        return 0;
    }

    

    //上传凭证
    /**
     * 上传支付凭证
     * @param Request $request
     * @return mixed
     */
    public function uploadfile(Request $request)
    {
        $pay_way = Db::name('config')->where('inc_type','pay_setting')->select();
        $pay_way[0]['name'] = '支付宝';
        $pay_way[1]['name'] = '微信';
        $pay_way[0]['img'] = $pay_way[2]['value'];
        $pay_way[1]['img'] = $pay_way[3]['value'];

        $package = null;
//        $package = Db::name('package')->order('pack_time asc')->select();
        $this->assign('pay',$pay_way);
        $this->assign('package',$package);//dump($package);die;
        return $this->fetch();
    }

    //待收益
    public function wait_earnings()
    {
        $user_id = $this->user_id;
        $where['user_id'] = $user_id;

        $Earnings = new Earnings;
        $earnings_info = $Earnings->where($where)->find();
        $obj = $earnings_info->obj;
        $obj = json_decode($obj,true);

        $user = M('users')->where('user_id',$user_id)->field('user_id,first_leader,distribut_level,is_distribut,bonus_products_id,is_lock')->find();
        //冻结账号没有待收益
        if ($user['is_lock'] == 1) {
            $lists = array();
        } else {
            $order_id = $obj ? array_column($obj,'o') : array();
            $user_comm = $this->self_wait($user,$order_id);//自己的待收益
            $lower_comm = $this->lower_wait($user,$order_id);//下级的待收益

            $total_money = $user_comm['total_money'] + $lower_comm['total_money'];
            $list = array();

            if ($user_comm['data'] && $lower_comm['data']) {
                $list = array_merge($user_comm['data'],$lower_comm['data']);
            } elseif ($user_comm['data']) {
                $list = $user_comm['data'];
            } elseif ($lower_comm['data']) {
                $list = $lower_comm['data'];
            }

            //是否有待收益
            if ($list) {
                foreach ($list as $key => $value) {
                    $result[] = ['o'=>$value['order_id'],'g'=>$value['goods_id'],'m'=>$value['money']];
                    $money[] = $value['money'];
                }

                $total_money = ($total_money = array_sum($money)) ? $total_money : array_sum($money);//重新统计
                if ($order_id) {
                    $result = array_merge($obj,$result);
                    $total_money += $earnings_info->money;
                }

                $obj = json_encode($result);
                $Earnings = $Earnings->where($where)->find() ?: $Earnings;

                $Earnings->user_id = $user_id;
                $Earnings->money = $total_money;
                $Earnings->obj = $obj;

                $Earnings->save();

                $earnings_info = $Earnings->where($where)->find();
                $obj = $earnings_info->obj;
                $obj = json_decode($obj,true);
            }
        }

        $lists = array();

        if ($obj) {
            $goods_ids = array_column($obj,'g');
            $order_ids = array_column($obj,'o');
            $goods = M('order_goods')->whereIn('goods_id',$goods_ids)->whereIn('order_id',$order_ids)->field('order_id,goods_id,goods_num,goods_name,goods_price')->select();
            $images = M('goods')->whereIn('goods_id',$goods_ids)->column('goods_id,original_img');

            foreach ($goods as $k => $v1) {
                foreach ($obj as $k2 => $v2) {
                    if ($v2['g'] == $v1['goods_id']) {
                        $money = $v2['m'];
                    }
                }
                $lists[] = ['order_id'=>$v1['order_id'],'goods_id'=>$v1['goods_id'],'goods_num'=>$v1['goods_num'],'money'=>$money,'images'=>$images[$v1['goods_id']],'goods_name'=>$v1['goods_name']];
            }
        }

        $this->assign('list',$lists);
        return $this->fetch();
    }

    //自己购买商品待收益
    public function self_wait($user,$old_order_id)
    {
        $user_id = $user['user_id'];
        $total_money = 0;
        $num = 0;
        $result = array();
        $order_ids = M('order_divide')->where('user_id',$user_id)->column('order_id');

        $all_order_goods = M('order_goods')->whereIn('order_id',function($query) use ($user_id,$order_ids,$old_order_id){
            $query->name('order')->where('user_id',$user_id)->where('order_id','not in',$order_ids)->where('order_id','not in',$old_order_id)->field('order_id');
        })->where('is_send','<>',3)->column('rec_id,goods_id');

        $repeat_ids = $this->repeat_buy($user['distribut_level'],$all_order_goods); //重复购买商品id
        $all_ids = $repeat_ids['all_ids'];
        $goods_ids = $repeat_ids['goods_ids'];

        $order_goods = M('order_goods')->whereIn('rec_id',$goods_ids)->column('goods_id,order_id,goods_name,goods_num,prize_ratio,is_team_prize');

        $comm = self::get_comm_setting(true,$all_ids); //获取返佣设置

        foreach ($comm as $k3 => $v3) {
            $money = 0;
            $preferential = $comm[$k3]['preferential'];
            if (!$preferential) {
                continue;
            }
            $money = $preferential[$user['distribut_level']];
            $total_money += $money;

            $result[$num]['goods_id'] = $order_goods[$k3]['goods_id'];
            $result[$num]['order_id'] = $order_goods[$k3]['order_id'];
            $result[$num]['goods_name'] = $order_goods[$k3]['goods_name'];
            $result[$num]['goods_num'] = $order_goods[$k3]['goods_num'];
            $result[$num]['money'] = $money;
            $num ++;
        }

        $list = array('total_money'=>$total_money,'data'=>$result);

        return $list;
    }

    //获取返佣设置
    public static function get_comm_setting($is_repeat,$goods_id)
    {
        $num = 0;
        $result = array();
        if ($goods_id) {
            $comm_ids = M('goods')->whereIn('goods_id',$goods_id)->column('goods_id,goods_prize');
            foreach ($comm_ids as $k => $v) {
                if (!$v) {
                    unset($comm_ids[$k]);
                    continue;
                }
                $ids = json_decode($v,true);

                if($is_repeat){
                    $fields = 'level,preferential,self_buying as basic,self_poor_prize as poor_prize,self_reword as first_layer,self_reword2 as second_layer';
                } else {
                    $fields = 'level,reward as basic,poor_prize,same_reword as first_layer,same_reword2 as second_layer';
                }

                $comm = M('goods_commission')->where('id','in',$ids)->column($fields);
                if (!$comm) {
                    continue;
                }
                $result[$k]['goods_id'] = 0;
                $result[$k]['basic'] = array();
                $result[$k]['poor_prize'] = array();
                $result[$k]['first_layer'] = array();
                $result[$k]['second_layer'] = array();
                $result[$k]['preferential'] = array();
                $result[$k]['goods_id'] = $k;

                foreach($comm as $key => $value){
                    $result[$k]['basic'][$key] = $value['basic'];
                    $result[$k]['poor_prize'][$key] = $value['poor_prize'];
                    $result[$k]['first_layer'][$key] = $value['first_layer'];
                    $result[$k]['second_layer'][$key] = $value['second_layer'];
                    $result[$k]['preferential'][$key] = $is_repeat ? $value['preferential'] : 0;
                }

                unset($comm_ids[$k]);
            }
        }

        return $result;
    }

    //重复购买商品id
    public function repeat_buy($user_level,$all_order_goods)
    {
        // $order_goods_count = array_count_values($all_order_goods); //统计键值
        $result = array('goods_ids'=>array(),'all_ids'=>array(),'first'=>array());

        foreach ($all_order_goods as $k1 => $v1) {
            if ($user_level === false) {
                $user_level = Db::name('users')->alias('users')
                    ->join('order order','order.user_id = users.user_id')
                    ->join('order_goods goods','order.order_id = goods.order_id')
                    ->where('goods.rec_id',$k1)
                    ->value('distribut_level');
            }

            if ($user_level > 0) {
                $result['goods_ids'][] = $k1;   //重复购买商品id
                $result['all_ids'][] = $v1;
                // foreach ($all_order_goods as $k2 => $v2) {
                //     if ($v2 == $k1) {
                //         $result['all_ids'][] = $k2;
                //         unset($all_order_goods[$k2]);
                //     }
                // }
            } else {
                $result['first'][] = $k1;
            }
            unset($order_goods_count[$k1]);
        }
        return $result;
    }

    //下级购买待收益
    public function lower_wait($user,$old_order_id)
    {
        $user_id = $user['user_id'];
        //$lower_ids = $this->lower_id($user_id);  //获取下级id列表
        $lower_ids = get_all_lower($user_id);  //获取下级id列表

        //获取已返佣的订单
        $order_id = Db::name('order')->alias('order')
            ->join('order_divide divide','order.order_id = divide.order_id')
            ->where('divide.user_id','in',$lower_ids)
            ->column('order.order_id');

        $wait_goods_ids = Db::name('order')->where('order_id','not in',$order_id)->where('order_id','not in',$old_order_id)->where('user_id','in',$lower_ids)->where('pay_status',1)->field('order_id,user_id')->order('add_time','desc')->limit(5)->select();

        if (!$wait_goods_ids) {
            return false;
        }

        $user_ids = array_column($wait_goods_ids,'user_id');
        $order_ids = array_column($wait_goods_ids,'order_id');

        foreach ($user_ids as $key => $value) {
            $leader_list[$value] = get_parents_ids($value);//获取上级id
        }

        $goods_ids = M('order_goods')->whereIn('order_id',$order_ids)->where('is_send','<>',3)->column('rec_id,goods_id');
        //$leader_list = M('users')->whereIn('user_id',$lower_ids)->column('user_id,parents,first_leader,distribut_level,is_distribut,bonus_products_id,is_lock');
        //$leader_list[$user_id] = $user;
        //ksort($leader_list);
        //$order_divide = M('order_divide')->where('user_id','in',$lower_ids)->column('order_id');  //获取已返佣的订单
        // //获取还没返佣的订单

        //$goods_ids = M('order_goods')->whereIn('order_id',function($query) use ($order_divide,$lower_ids,$old_order_id) {
        //    $query->name('order')->where('order_id','not in',$order_divide)->where('user_id','in',$lower_ids)->where('order_id','not in',$old_order_id)->field('order_id');
        //})->where('is_send','<>',3)->column('rec_id,goods_id');

        $repeat_ids = $this->repeat_buy(false,$goods_ids); //是否重复购买
        $second_ids = $repeat_ids['goods_ids'];
        $first_ids = $repeat_ids['first'];

        //获取商品返佣设置
        $comm1 = self::get_comm_setting(false,$first_ids);
        $comm2 = self::get_comm_setting(true,$second_ids);
        $rec_ids = array_flip($goods_ids);//交换数组的键和值

        //计算佣金
        $result = $this->calculate_commission($comm1,$rec_ids,$leader_list,['total_money'=>0,'data'=>[]],$user);
        $result2 = $this->calculate_commission($comm2,$rec_ids,$leader_list,$result,$user);

        return $result2;
    }

    //计算佣金
    public function calculate_commission($comm,$rec_ids,$leader_list,$result = '',$user)
    {
        $total_money = $result['total_money'];
        $list = $result['data'];
        //是否有返佣设置
        if ($comm) {
            $user_id = $user['user_id'];
            $user_level = intval($user['distribut_level']);
            $bonus_products_id = $user['bonus_products_id'];

            $goods_ids = array_column($comm, 'goods_id');
            $goods = M('order_goods')->whereIn('rec_id',$rec_ids)->where('goods_id','in',$goods_ids)->where('is_send','<>',3)->field('order_id,goods_id,goods_name,goods_num,goods_price,is_team_prize,prize_ratio')->select();
            if (!$goods) {
                return $result;
            }
            $order_ids = array_column($goods, 'order_id');
            $order = M('order')->whereIn('order_id',$order_ids)->column('order_id,user_id');
            //是否有团队奖励
            if ($bonus_products_id > 0) {
                $prize_ratio = M('goods')->where('goods_id',$bonus_products_id)->value('prize_ratio');
            }
            foreach ($goods as $k1 => $v1) {
                $order = M('order')->where('order_id',$v1['order_id'])->field('order_id,user_id')->find();
                //$parents = $leader_list[$order['user_id']]['parents'];
                //$parents_id = $parents ? explode(',',$parents) : 0;
                $parent_id = $leader_list[$order[$v1['order_id']]]['first_leader'];
                //$is_exist = in_array($parent_id,$parents_id);
                //if (!$is_exist) {
                //    array_unshift($parents_id,$leader_list[$parent_id]);
                //}
                //krsort($parents_id);
                $parents_id = array_filter($parents_id);  //去除0

                $num = count($list);
                if ($count == count($list,1)) {
                    $num = $num ? 1 : 0;
                }
                //团队奖励
                if ($prize_ratio > 0) {
                    $team_money = $v1['goods_price'] * $v1['goods_num'] * ($prize_ratio / 100);
                    $money = round($money,2);
                    if ($money) {
                        $total_money += $team_money;

                        $list[$num]['goods_id'] = $v1['goods_id'];
                        $list[$num]['order_id'] = $v1['order_id'];
                        $list[$num]['goods_name'] = $v1['goods_name'];
                        $list[$num]['goods_num'] = $v1['goods_num'];
                        $list[$num]['money'] = $team_money;
                        $num ++;
                    }
                }

                if (!$parents_id) {
                    continue;
                }

                $basic_reward = $comm[$v1['goods_id']]['basic'];  //直推奖励
                $poor_prize = $comm[$v1['goods_id']]['poor_prize'];//极差奖励
                $first_layer = $comm[$v1['goods_id']]['first_layer'];//同级一层奖励
                $second_layer = $comm[$v1['goods_id']]['second_layer'];//同级二层奖励

                $is_me = false;
                $layer = 0;
                $level = 0;
                $is_prize = false;

                foreach ($parents_id as $k2 => $v2) {
                    $money = 0;
                    if (!isset($leader_list[$v2])) {
                        continue;
                    }
                    if ($user_level < $leader_list[$v2]['distribut_level']) {
                        break;
                    }
                    if ($is_me) {
                        break;
                    }

                    if ($user_id == $v2) {
                        $is_me = true;
                    }
                    //账号冻结了没有奖励
                    if ($leader_list[$v2]['is_lock'] == 1) {
                        continue;
                    }
                    //不是分销商不奖励
                    if ($leader_list[$v2]['is_distribut'] != 1) {
                        continue;
                    }

                    //平级奖
                    if ($level == $leader_list[$v2]['distribut_level']) {
                        $level = $leader_list[$v2]['distribut_level'];
                        $layer ++;
                        //超过设定层数没有奖励
                        if ($layer > 2) {
                            continue;
                        }
                        if (!$is_prize) {
                            $money = $basic_reward ? floatval($basic_reward[$leader_list[$v2]['distribut_level']]) : 0;
                            $is_prize = true;
                        }
                        //不是自己没有奖金
                        if ($v2 != $user_id) {
                            $money = 0;
                            continue;
                        }
                        //同级层数
                        switch($layer){
                            case 1:
                                $money += floatval($first_layer[$leader_list[$v2]['distribut_level']] * $v1['goods_num']);
                                break;
                            case 2:
                                $money += floatval($second_layer[$leader_list[$v2]['distribut_level']] * $v1['goods_num']);
                                break;
                            default:
                                break;
                        }
                    }

                    //极差奖
                    if ($level < $leader_list[$v2]['distribut_level']) {
                        $layer = 0;
                        //基本奖励已奖励的不再奖励
                        if (!$is_prize && ($v2 == $user_id)) {
                            $money = $basic_reward ? floatval($basic_reward[$leader_list[$v2]['distribut_level']]) : 0;
                            $is_prize = true;
                        }

                        reset($poor_prize);  //重置数组指针

                        //计算极差奖金
                        while(list($pk,$pv) = each($poor_prize)){
                            if ($level >= $pk) {
                                continue;
                            }
                            if (($pk <= $leader_list[$pv]['distribut_level']) && ($v2 == $user_id)) {
                                $pk = $pv ? floatval($pv) : 0;
                                $money += $pv * $v1['goods_num'];

                                continue;
                            }
                            break;
                        }


                        $level = $leader_list[$v2]['distribut_level'];
                    }
                    $money = floatval($money);
                    if (!$money) {
                        continue;
                    }
                    $total_money += $money;

                    $list[$num]['goods_id'] = $v1['goods_id'];
                    $list[$num]['order_id'] = $v1['order_id'];
                    $list[$num]['goods_name'] = $v1['goods_name'];
                    $list[$num]['goods_num'] = $v1['goods_num'];
                    $list[$num]['money'] = $money;
                    $num ++;
                }
            }
        }

        $result['total_money'] = $total_money;
        $result['data'] = $list;

        return $result;
    }

    //  /**
    //  * 我的佣金
    //  * @author Rock
    //  */
    // public function mycommission(){
    //     $user_id = $this->user_id;
    //     // echo $user_id;exit;
    //     # 登录签到佣金
    //     $data['sign_money'] = Db::name('commission_log')->where(['user_id'=>$user_id,'identification'=>1])->sum('money');
    //     $data['invite_money'] = Db::name('commission_log')->where(['user_id'=>$user_id,'identification'=>2])->sum('money');
    //     $data['distribution_rebate'] = Db::name('order_divide')->where(['user_id'=>$user_id])->sum('money');


    //     $this->assign('data',$data);
    //     return $this->fetch();
    // }

    // /**
    //  * 佣金明细
    //  * @param int t 明细类型[1=>登录签到，2=>邀请注册，3=>分销返利]
    //  *
    //  */
    // public function commission_log(){
    //     $t = intval(input('get.t'));
    //     if(!$t){
    //         $this->error('参数错误！');
    //     }
    //     $user_id = $this->user_id;

    //     switch($t){
    //         case '1':
    //             # 签到登录
    //             $log = Db::query("select `money`,`date`,`num` from `tp_commission_log` where `user_id` = '$user_id' and `identification` = 1 order by `date` desc limit 50");
    //             // dump($log);exit;
    //             break;
    //         case '2':
    //             # 邀请注册
    //             $log = Db::query("select a.`add_user_id`,b.`mobile`,b.`nickname`,`money`,`addtime` from `tp_commission_log` as a left join `tp_users` as b on a.`add_user_id` = b.`user_id` where a.`user_id` = '$user_id' and a.`identification` = 2 order by a.`addtime` desc limit 50");
    //             break;
    //         case '3':
    //             #分销返利
    //             $log = DB::query("select `add_time`, `money` from `tp_order_divide` where `user_id` = '$user_id' order by `add_time` desc limit 50");
    //             break;

    //         default:
    //             return $this->error('无效的参数，请重试！');
    //     }


    //     $this->assign('log',$log);
    //     $this->assign('t',$t);
    //     return $this->fetch();
    // }

    /**
     * 所有下级id
     */
    public function lower_id($user_id)
    {
        $d_info = Db::query("select `user_id`, `first_leader`,`parents` from `tp_users` where 'first_leader' = $user_id or parents like '%,$user_id,%'");
        $ids = array();
        if($d_info){
            $ids = array_column($d_info,'user_id');
        }

        return $ids;
    }

    /**
     * 我的分销
     */
    public function team_list()
    {
        $user_id = $this->user_id;

        $leader_id = M('users')->where('user_id',$user_id)->field('nickname,first_leader,user_id')->find();
        $leader = M('users')->where(['user_id'=>$leader_id['first_leader']])->field('nickname,user_id')->find();

        $team_count = Db::query("SELECT count(*) as count FROM tp_parents_cache where find_in_set('$user_id',`parents`)");
        //个人业绩  团队业绩
        $Ad  = M('agent_performance');

        $performance = $Ad->where(['user_id' => $user_id])->find();
        $performance = $performance['ind_per']+$performance['agent_per'];
        if(empty($performance)){
            $performance = 0;
        }
        $performance = bcadd($performance,'0.00',2);
        $bonus = Db::name('account_log')->where(['user_id'=>$user_id,'type'=>['in','2,3,4,5']])->sum('user_money'); 
        $bonus = bcadd($bonus,'0.00',2);

        $this->assign('performance',$performance);
        $this->assign('bonus',$bonus);
        $this->assign('team_count',$team_count[0]['count'] ? $team_count[0]['count'] : 0);
        $this->assign('leader',$leader);
        $this->assign('user_id',$user_id);
        $this->assign('leader_id',$leader_id['first_leader']);

        return $this->fetch();
    }

    /**
     * 明细记录
     */
    public function mixi(){
        $user_id = $this->user_id;
        $account_log = M('account_log')->where(['user_id'=>$user_id,'type'=>2])->select();
        $this->assign('account_log',$account_log);
        return $this->fetch();
    }


    /**
     * 团队列表
     */
    public function group(){
        $user_id = $this->user_id;
        $user = M('users')->where(['user_id'=>$user_id])->field('user_id,nickname,mobile,distribut_level,distribut_money,head_pic')->find();
        $get_all_lower = get_all_lower($user_id);
        foreach($get_all_lower as $key => $vale){
            // dump($vale);
            $get_all_lower[$key] = M('users')->where(['user_id'=>$vale])->field('user_id,nickname,mobile')->find();
            // dump($user);
        }
        $this->assign('nickname',$user['nickname']);
        $this->assign('user_id',$user_id);
        $this->assign('get_all_lower',$get_all_lower);

        return $this->fetch();
    }

    /**
     * 团队订单
     */
    public function order(){
        $user_id = $_GET['id'];
        $orders = Db::name('order')->where(['user_id'=>$user_id])->field('order_sn,consignee,add_time')->select();
        $this->assign('orders',$orders);
        // dump($orders);die;
        return $this->fetch();
    }

    /**
     * 推广名额
     */
    public function tuiguang(){
        $user_id = $this->user_id;
        $pop_period = Db::name('pop_period')->where(['user_id'=>$user_id])->select();
        $users_period = [];
        foreach($pop_period as $key => $veal)
        {
            $pop_period[$key]['nums'] = $veal['person_num'] - $veal['poped_per_num'];
            $users_period[] = Db::name('account_log')->where(['user_id'=>$user_id,'type'=>2,'change_time'=>['>=',$pop_period['begin_time']]])->select();
            // $pop_period[$key]['users_period'] = $users_period;
        }
        // dump($users_period);die;
        $this->assign('pop_period',$pop_period);
        $this->assign('users_period',$users_period);
        return $this->fetch();
    }


    // 转账记录
    public function transfer(){
        return $this->fetch();
    }

    // 余额转账
    public function balance(){

        return $this->fetch();
    }

    // 余额转账详情
    public function remainingsum(){
        $user_id=input('user_id');
        $my_user_id=$this->user_id;
        $endUser=Db::name('users')->field('user_id,head_pic,mobile,nickname')->where('user_id','=',$user_id)->find();
        $myInfo=Db::name('users')->where("user_id",'=',$my_user_id)->field('user_money')->find();
        $this->assign([
            'endUser'=>$endUser,
            'myInfo'=>$myInfo,
        ]);
        return $this->fetch();
    }

    //处理转账
    public function exchange_money_handle()
    {
        $time=time();
        $data=input('post.');
        if(!$data['end_user_id']){
            $this->ajaxReturn(['status' => -1, 'msg' => '转入人不能为空']);
        }
        if(!$data['exchange_money']){
            $this->ajaxReturn(['status' => -1, 'msg' => '转出金额不能为空']);
        }
        $data1['user_id']=$this->user_id;
        $data1['out_user_id']=$this->user_id;
        $data1['in_user_id']=$data['end_user_id'];
        $data1['exchange_money']='-'.$data['exchange_money'];
        $data1['description']=$data['description'];
        $data1['create_time']=$time;
        $data1['type']=2;

        $data2['user_id']=$data['end_user_id'];
        $data2['out_user_id']=$this->user_id;
        $data2['in_user_id']=$data['end_user_id'];
        $data2['exchange_money']=$data['exchange_money'];
        $data2['description']=$data['description'];
        $data2['create_time']=$time;
        $data2['type']=1;

        Db::startTrans();
        try{
            $myUser=Db::name('users')->where('user_id','=',$data1['user_id'])->find();
            $minusMoney=$myUser['user_money']-$data['exchange_money'];
            Db::name('users')->where('user_id','=',$data1['user_id'])->update(['user_money'=>$minusMoney]);

            $otherUser=Db::name('users')->where('user_id','=',$data['end_user_id'])->find();
            $addMoney=$otherUser['user_money']+$data['exchange_money'];
            Db::name('users')->where('user_id','=',$data['end_user_id'])->update(['user_money'=>$addMoney]);

            $res1 = Db::name('exchange_money')->insert($data1);
            $res2 = Db::name('exchange_money')->insert($data2);
            Db::commit();    
            if($res1&&$res2){
                $this->ajaxReturn(['status' => 1, 'msg' => '转账成功']);
            }
        } catch (\Exception $e) {
            Db::rollback();
            $this->ajaxReturn(['status' =>-1, 'msg' => '操作失败']);
        }
    

    }

    /** 模糊查询
     * @param: array  $search_data    搜索关键字
     */
    public function ajax_search()
    {
        $res['code'] = 0;
        $search_data = I('post.key');
        $conn = '';
        if (!empty($search_data)) {
            $key['mobile'] = array('like', '%' . $search_data . '%');
            $conn = M('users')->field('user_id,nickname,head_pic')->where($key)->select();//查询数据
        }
        $conn[0]['head_pic'] = SITE_URL.$conn[0]['head_pic'];
        if ($conn) {
            $res['code'] = 1;
            $res['data'] = $conn;
            $res['msg'] = '成功';
        } else {
            $res['msg'] = '失败';
        }
        echo json_encode($res);
    }


    /**
     * 我的VIP
     */
    public function team_vip_list()
    {
        $user_id = $this->user_id;
         
        $leader_id = M('users')->where('user_id',$user_id)->value('first_leader');
        $leader = M('users')->where('user_id',$leader_id)->field('nickname,mobile,head_pic')->find();
        $first = M('users')->where(['first_leader' =>$user_id,'end_time' => ['neq' ,0]])->column('user_id');
        $second = $first ? M('users')->where(['first_leader'=>['in',$first],'end_time' => ['neq',0]])->column('user_id') : [];
        $third = $second ? M('users')->where(['first_leader'=>['in',$second],'end_time' => ['neq',0]])->column('user_id') : [];

        $first_count = count($first);
        $second_count = count($second);
        $third_count = count($third);

        $team_count = Db::query("SELECT count(*) as count FROM tp_parents_vip_cache where find_in_set('$user_id',`parents`)");
        //$team_count = Db::query("SELECT count(*) as count FROM tp_users where find_in_set('$user_id',`parents`)");

        $this->assign('first_count',$first_count);
        $this->assign('second_count',$second_count);
        $this->assign('third_count',$third_count);
        $this->assign('team_count',$team_count[0]['count'] ? $team_count[0]['count'] : 0);
        $this->assign('leader',$leader);
        // $this->assign('count',$count);
        // $this->assign('team',$team_list);
        return $this->fetch();
    }
    /**
     * 三级分销
     */
    public function three_level()
    {
        $user_id = $this->user_id;
        $leader_ids = I('ids',[]);
        $type = I('type',1);
        $page = I('page',1);
       
        $where = array();
       
        switch($type){
            //一级
            case 1:
                $where['first_leader'] = $user_id;
                break;
            //二级
            case 2:
                $first = M('users')->where('first_leader',$user_id)->column('user_id');
                $where['first_leader'] = $first ? ['in',$first] : array();
                break;
            //三级
            case 3:
                $first = M('users')->where('first_leader',$user_id)->column('user_id');
                $second = $first ? M('users')->where(['first_leader'=>['in',$first]])->column('user_id') : [];
                $where['first_leader'] = $second ? ['in',$second] : array();
                break;
            default: break;
        }

        $team_list = array();
        if ($where['first_leader']) {
            //获取对应下级id的数据
            $team_list = Db::name('users')->where($where)->field('user_id,nickname,mobile,distribut_level,distribut_money,head_pic')->page($page,15)->select();
        }

        $level = M('agent_level')->column('level,level_name');

        foreach($team_list as $k1 => $v1){
            $team_list[$k1]['level_name'] = $level[$v1['distribut_level']];
        }

        return json($team_list);
    }


    /**
     * 三级分销VIP
     */
    public function three_vip_level()
    {
        $user_id = $this->user_id;
        $leader_ids = I('ids',[]);
        $type = I('type',1);
        $page = I('page',1);
        $where = array();
        $where['end_time']     = ['neq',0];
        switch($type){
            //一级
            case 1:
                $where['first_leader'] = $user_id;
                break;
            //二级
            case 2:
                $first = M('users')->where(['first_leader' => $user_id , 'end_time' => ['neq' ,0]])->column('user_id');
                $where['first_leader'] = $first ? ['in',$first] : array();
                break;
            //三级
            case 3:
                $first = M('users')->where(['first_leader' => $user_id , 'end_time' => ['neq' ,0]])->column('user_id');
                $second = $first ? M('users')->where(['first_leader'=>['in',$first],'end_time' => ['neq',0]])->column('user_id') : [];
                $where['first_leader'] = $second ? ['in',$second] : array();
                break;
            default: break;
        }

        $team_list = array();
        if ($where['first_leader']) {
            //获取对应下级id的数据
            $team_list = Db::name('users')->where($where)->field('user_id,nickname,mobile,distribut_level,distribut_money_vip,head_pic')->page($page,15)->select();
        }

        $level = M('agent_level')->column('level,level_name');

        foreach($team_list as $k1 => $v1){
            $team_list[$k1]['level_name'] = $level[$v1['distribut_level']];
        }

        return json($team_list);
    }

    /**
     * 下级购买记录
     */
    public function purchase_log()
    {
        $id = input('id/d');

        $log = Db::name('order')->alias('order')
            ->distinct(true)
            ->join('order_goods goods','order.order_id = goods.order_id')
            ->where('order.pay_status',1)
            ->where('order.user_id',$id)
            ->order('order.pay_time','desc')
            ->field('goods.rec_id,order.pay_time,goods.goods_price,goods.goods_num')
            ->limit(50)
            ->select();

        $user_info = Db::name('users')->where('user_id',$id)->field('nickname,mobile,head_pic')->find();

        $this->assign('info',$user_info);
        $this->assign('log',$log);
        return $this->fetch();
    }

     /**
     * 下级购买记录
     */
    public function vip_purchase_log()
    {
        $id = input('id/d');

        $log = Db::name('buy_vip')
            ->distinct(true)
            ->where(['user_id' => $id ,'pay_status' => 1])
            ->order('order_id desc')
            ->field('account,ctime')
            ->limit(50)
            ->select();

        $user_info = Db::name('users')->where('user_id',$id)->field('nickname,mobile,head_pic')->find();

        $this->assign('info',$user_info);
        $this->assign('log',$log);
        return $this->fetch();
    }

    /**
     *邀请用户
     */
    public function invite_user(){
        $user_id = $this->user_id;
        $sql = "select a.*,b.head_pic,b.nickname,b.mobile from `tp_commission_log` as a left join `tp_users` as b on a.add_user_id = b.user_id where a.identification = 2 and a.user_id = '$user_id' order by addtime desc limit 50";
        $log = Db::query($sql);
        $this->assign('log',$log);
        return $this->fetch();
    }

    /**
     *登录签到
     */
    public function sign_list(){
        $user_id = $this->user_id;
        $log = Db::query("select `money`,`date`,`num` from `tp_commission_log` where `user_id` = '$user_id' and `identification` = 1 order by `date` desc limit 50");
        $this->assign('log',$log);
        return $this->fetch();
    }

    /**
     *分销返利
     */
    public function distribution_rebate(){
        $user_id = $this->user_id;
        $log = DB::query("select `add_time`, `money` from `tp_order_divide` where `user_id` = '$user_id' order by `add_time` desc limit 50");
        $this->assign('log',$log);
        return $this->fetch();
    }


    public function sharePoster(){

        $user_id = $this->user_id;
        $share_error = 0;

        $filename = $user_id.'-qrcode.png';
        $save_dir = ROOT_PATH.'public/shareposter/';
        $my_poster = $save_dir.$user_id.'-share.png';
        $my_poster_src = '/public/shareposter/'.$user_id.'-share.png';
        if( !file_exists($my_poster) ){
            $shareposter = Db::name('users')->where('user_id',$user_id)->value('shareposter');
            if($shareposter != 1){
                $imgUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/index.php?dfc5b='.$this->user_id;
                vendor('phpqrcode.phpqrcode');
                \QRcode::png($imgUrl, $save_dir.$filename, QR_ECLEVEL_M);
                $image_path =  ROOT_PATH.'public/shareposter/load/qr_backgroup.png';
                if(!file_exists($image_path)){
                    $share_error = 1;
                }
                # 分享海报
                if(!file_exists($my_poster) && !$share_error){
                    # 海报配置
                    $conf = Db::name('config')->where(['inc_type' => 'shareposter', 'name' => 'shareposter'])->find();
                    if($conf){
                        $config = json_decode($conf['value'],true);

                        $image_w = $config['w'] ? $config['w'] : 75;
                        $image_h = $config['h'] ? $config['h'] : 75;
                        $image_x = $config['x'] ? $config['x'] : 0;
                        $image_y = $config['y'] ? $config['y'] : 0;

                        # 根据设置的尺寸，生成缓存二维码
                        $qr_image = \think\Image::open($save_dir.$filename);
                        $qrcode_temp_path = $save_dir.$user_id.'-poster.png';
                        $qr_image->thumb($image_w,$image_h,\think\Image::THUMB_SOUTHEAST)->save($qrcode_temp_path);
                        
                        if($image_x > 0 || $image_y > 0){
                            $water = [$image_x, $image_y];
                        }else{
                            $water = 5;
                        }
                        
                        # 图片合成
                        $image = \think\Image::open($image_path);
                        $image->water($qrcode_temp_path, $water)->save($my_poster);
                        @unlink($qrcode_temp_path);
                        @unlink($save_dir.$filename);

                    }else{
                        $share_error = 1;
                    }


                }
            }
        }
        
        $this->assign('my_poster_src', $my_poster_src);
        return $this->fetch('sharePoster');
    }

    /**
     * 我的分享
     * @author Rock
     * @date 2019/03/23
     */
    public function sharePoster2(){

        $user_id = $this->user_id;
        $share_error = 0;
        $this->Auto_Refresh_Access_Token();
        $filename = 'qrcode.png';
        $save_dir = ROOT_PATH.'public/shareposter/user/'.$user_id.'/';
        $my_poster = $save_dir.'poster.png';
        $my_poster_src = '/public/shareposter/user/'.$user_id.'/poster.png';

        $shareposter = Db::name('users')->field('shareposter')->where('user_id',$user_id)->find();
        $shareposter = $shareposter['shareposter'];

        if($shareposter){
            $shareposter = json_decode($shareposter,true);
            $ticket = $shareposter['ticket'];
            $expire_seconds = $shareposter['expire_seconds'];
            if($expire_seconds < time()){
                Db::execute("update `tp_users` set `shareposter` = '' where `user_id` = '$user_id'");

                # 删除已存在的二维码文件
                unlink($save_dir.$filename);
                $this->redirect('sharePoster');
            }
        }else{
            $conf = Db::name('wx_user')->field('web_expires,web_access_token')->where('wait_access',1)->find();

            $token = $conf['web_access_token'];
            $param = [
                'expire_seconds' => 2592000,
                'action_name' => 'QR_STR_SCENE',
                'action_info' => [
                    'scene' => [
                        'scene_str' => 'sharePoster',
                    ],
                ],
            ];

            $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$token";
            $res = httpRequest($url,'POST',json_encode($param));
            $res = json_decode($res,true);

            if($res['ticket']){
                $ticket = $res['ticket'];
                $expire_seconds = time() + $res['expire_seconds'] - 200;
                $update = json_encode(['ticket'=>$ticket,'expire_seconds'=>$expire_seconds]);
                Db::execute("update `tp_users` set `shareposter` = '$update' where `user_id` = '$user_id'");
            }else{
                $share_error = 1;
            }
        }
        $imgUrl = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".UrlEncode($ticket);


        # 临时二维码
        if(!file_exists($save_dir.$filename)){
            $this->getImage($imgUrl,$save_dir,$filename);
        }
        $image_path =  ROOT_PATH.'public/shareposter/load/qr_backgroup.png';
        if(!file_exists($image_path)){
            $share_error = 1;
        }

        # 分享海报
        if(!file_exists($my_poster) && !$share_error){
            # 海报配置
            $conf = Db::name('config')->where(['inc_type' => 'shareposter', 'name' => 'shareposter'])->find();
            if($conf){
                $config = json_decode($conf['value'],true);

                $image_w = $config['w'] ? $config['w'] : 75;
                $image_h = $config['h'] ? $config['h'] : 75;
                $image_x = $config['x'] ? $config['x'] : 0;
                $image_y = $config['y'] ? $config['y'] : 0;

                # 根据设置的尺寸，生成缓存二维码
                $qr_image = \think\Image::open($save_dir.$filename);
                $qrcode_temp_path = $save_dir.'qrcode_temp.png';
                $qr_image->thumb($image_w,$image_h,\think\Image::THUMB_SOUTHEAST)->save($qrcode_temp_path);

                if($image_x > 0 || $image_y > 0){
                    $water = [$image_x, $image_y];
                }else{
                    $water = 5;
                }

                # 图片合成
                $image = \think\Image::open($image_path);
                $image->water($qrcode_temp_path, $water)->save($my_poster);
                @unlink($qrcode_temp_path);
                @unlink($save_dir.$filename);

            }else{
                $share_error = 1;
            }


        }

        $this->assign('my_poster_src', $my_poster_src);
        return $this->fetch();
    }

    function getImage($url,$save_dir='',$filename='',$type=0){
        if(trim($url)==''){
            return array('file_name'=>'','save_path'=>'','error'=>1);
        }
        if(trim($save_dir)==''){
            $save_dir='./';
        }
        if(trim($filename)==''){//保存文件名
            $ext=strrchr($url,'.');
            if($ext!='.gif'&&$ext!='.jpg'){
                return array('file_name'=>'','save_path'=>'','error'=>3);
            }
            $filename=time().$ext;
        }
        if(0!==strrpos($save_dir,'/')){
            $save_dir.='/';
        }
        //创建保存目录
        if(!file_exists($save_dir)&&!mkdir($save_dir,0777,true)){
            return array('file_name'=>'','save_path'=>'','error'=>5);
        }
        //获取远程文件所采用的方法
        if($type){
            $ch=curl_init();
            $timeout=5;
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
            $img=curl_exec($ch);
            curl_close($ch);
        }else{
            ob_start();
            readfile($url);
            $img=ob_get_contents();
            ob_end_clean();
        }
        //$size=strlen($img);
        //文件大小
        $fp2=@fopen($save_dir.$filename,'a');
        fwrite($fp2,$img);
        fclose($fp2);
        unset($img,$url);
        return array('file_name'=>$filename,'save_path'=>$save_dir.$filename,'error'=>0);
    }


    public function poster_qr($qr_code_file,$qr_code_path)
    {
        ob_end_clean();
        vendor('topthink.think-image.src.Image');

        error_reporting(E_ERROR);

        define('IMGROOT_PATH', str_replace("\\","/",realpath(dirname(dirname(__FILE__)).'/../../'))); //图片根目录（绝对路径）

        $back_img = IMGROOT_PATH.tpCache('background.background'); //获取背景图

        if (!is_file($back_img) || !is_file($qr_code_file)) {
            return $this->fetch('sharePoster');
        }

        $back_info = getimagesize($back_img);    //获取图片信息
        $im = checkPosterImagesType($back_info,$back_img);

        $back_width = imagesx($im);    //背景图宽
        $back_height = imagesy($im);   //背景图高
        $canvas = imagecreatetruecolor($back_width,$back_height);  //创建画布

        imagecopyresized($canvas,$im,0,0,0,0,$back_width,$back_height,$back_width,$back_height);   //缩放
        $new_QR = $qr_code_path.createImagesName().".png";    //获得缩小后新的二维码路径

        inputPosterImages($back_info,$canvas,$new_QR);  //输出到png即为一个缩放后的文件

        $QR = imagecreatefromstring(file_get_contents($qr_code_file));
        $background_img = imagecreatefromstring(file_get_contents($new_QR));
        imagecopyresampled($background_img,$QR,$back_width-130,$back_height-150,0,0,110,110,430,430);  //合成图片
        $result_png = createImagesName().".png";
        $file = $qr_code_path.$result_png;
        imagepng ($background_img,$file);  //输出合成海报图片

        $final_poster = imagecreatefromstring(file_get_contents($file)); //获得该图片资源显示图片

        header("Content-type: image/png");
        imagepng($final_poster);
        imagedestroy($final_poster);

        $new_QR && unlink($new_QR);
        // $qr_code_file && unlink($qr_code_file);
        $file && unlink($file);
        exit();
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        setcookie('uname','',time()-3600,'/');
        setcookie('cn','',time()-3600,'/');
        setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
        exit();
    }

    /*
     * 账户资金
     */
    public function account()
    {
//        $user = session('user');
        $user = $this->user;
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];
        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }

    public function account_list()
    {
        // // $usersLogic = new UsersLogic;
        // // $result = $usersLogic->account($this->user_id, $type);

        // if ($_GET['is_ajax']) {
        // 	return $this->fetch('ajax_account_list');
        // }
        return $this->fetch();
    }

    public function get_record()
    {
        $user_id = $this->user_id;
        $type = I('type','income');
        $distribut_type = I('distribut_type','0');
        $page = I('page',1);
        $result = array();

        if ($type == 'income') {

            $where = get_comm_condition($distribut_type); //获取条件
            if($distribut_type == 7){
                $result = M('vip_commission_log')->where('to_user_id',$user_id)->where($where)->order('create_time','desc')->field('log_id,money,status,order_id,create_time')->page($page,15)->select();
            }else{
                $result = M('distrbut_commission_log')->where('to_user_id',$user_id)->where($where)->order('create_time','desc')->field('log_id,money,status,order_id,create_time')->page($page,15)->select();
            }

            foreach ($result as $key => $value) {
                $result[$key]['create_time'] = date('Y-m-d H:i',$value['create_time']);
            }
        } else {
            $result = M('account_log')->where('user_money','<',0)->where('user_id',$user_id)->order('create_time','desc')->field('log_id,user_money as money,change_time as create_time,order_id')->page($page,15)->select();

            foreach ($result as $key => $value) {
                $result[$key]['create_time'] = date('Y-m-d H:i',$value['create_time']);
                // $result[$key]['money'] = abs($value['money']);
                $result[$key]['status'] = 1;
            }
        }

        return json($result);
    }
  
  	/**
     * 从新执行返佣失败的数据
     */
    public function record_again()
    {
        //获取所有失败数据
        $faild_data = Db::name('distrbut_commission_log')->where('status',0)->field('log_id,to_user_id,money')->select();
        if(!empty($faild_data)){
            foreach($faild_data as $k=>$v){
                $res = Db::name('users')->where('user_id',$v['to_user_id'])->setInc('user_money',$v['money']);//更新用户余额
              	echo $res;
                if($res){
                    //如果更新成功，改变记录状态
                    $a = Db::name('distrbut_commission_log')->where('log_id',$v['log_id'])->update(['status'=>1]);
                  	if($a > 0){
                      echo "success update";
                    }
                }
            }
        }
    }

    public function account_detail(){
        $log_id = I('log_id/d',0);
        $detail = Db::name('account_log')->where(['log_id'=>$log_id])->find();
        $this->assign('detail',$detail);
        return $this->fetch();
    }

    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, input('type'));
        foreach($data['result'] as $k =>$v){
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if($user_type==1){ //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id'=>$v['cid']])->getField('goods_id');
            }
            if($user_type==2){ //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id'=>$v['cid']])->getField('goods_category_id');
            }
        }
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (input('is_ajax')) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
            // header("Location: " . U('Mobile/User/index'));
            $this->redirect('Mobile/User/index');
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
        $this->assign('referurl', $referurl);
        // 新版支付宝跳转链接
        $this->assign('alipay_url', urlencode(SITE_URL.U("Mobile/LoginApi/login",['oauth'=>'alipaynew'])));
        return $this->fetch();
    }

    /**
     * 登录
     */
    public function do_login()
    {
        $username = trim(I('post.username'));
//        $password = trim(I('post.password'));
        $mobile_code = trim(I('post.mobile_code'));
        //验证码验证
//        if (isset($_POST['verify_code'])) {
//            $verify_code = I('post.verify_code');
//            $verify = new Verify();
//            if (!$verify->check($verify_code, 'user_login')) {
//                $res = array('status' => 0, 'msg' => '验证码错误');
//                exit(json_encode($res));
//            }
//        }
//        if($mobile_code!='000000'){
//            // 验证码
//            //            $code=I('mobile_code');
//            $sms_type=I('sms_type');
//            $checkData['sms_type'] = $sms_type;
//            $checkData['code'] = $mobile_code;
//            $checkData['phone'] = $username;
//            $res = checkPhoneCode($checkData);
//            if ($res['code'] == 0) {
//                exit(json_encode(['status' => 0, 'msg' => $res['msg']]));
//            }
//        }

        $logic = new UsersLogic();
        $res = $logic->login($username, 1);
//        var_dump($res);die;
        if ($res['status'] == 1) {
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']);//登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
        }else if($res['status'] == -1){
            $res['status']  = 1;
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            //之前没有注册过  生成一个新的用户
            $data = $logic->reg($username, '123456', '123456');
//            var_dump($data);die;
            if ($data['status'] != 1) exit(json_encode($data));
            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id'=>$data['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];

            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作

        }
        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {

        if($this->user_id > 0) {
            $this->redirect(U('Mobile/User/index'));
        }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $nickname = I('post.nickname', '');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            $is_bind_account = tpCache('basic.is_bind_account');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();
            $sms_type = I('post.sms_type');

            // 验证码
            $checkData['sms_type'] = 1;
            $checkData['code'] = $code;
            $checkData['phone'] = $username;
            $res = checkPhoneCode($checkData);
            if ($res['code'] == 0) {
                return json([ 'msg' => $res['msg']]);
            }

            //是否开启注册验证码机制
            if(check_mobile($username)){
                if($reg_sms_enable){
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }
            //是否开启注册邮箱验证码机制
            if(check_email($username)){
                if($reg_smtp_enable){
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }

            $invite = I('invite');
            if(!empty($invite)){
                $invite = get_user_info($invite,2);//根据手机号查找邀请人
                if(empty($invite)){
                    $this->ajaxReturn(['status'=>-1,'msg'=>'推荐人不存在','result'=>'']);
                }
            }else{
                $invite = array();
            }
            if($is_bind_account && session("third_oauth")){ //绑定第三方账号
                $thirdUser = session("third_oauth");
                $head_pic = $thirdUser['head_pic'];
                $data = $logic->reg($username, $password, $password2, 0, $invite ,$nickname , $head_pic);
                //用户注册成功后, 绑定第三方账号
                $userLogic = new UsersLogic();
                $data = $userLogic->oauth_bind_new($data['result']);
            }else{
                $data = $logic->reg($username, $password, $password2,0,$invite);
            }


            if ($data['status'] != 1) $this->ajaxReturn($data);

            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id'=>$data['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];

            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('regis_sms_enable',$reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable',$reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    public function bind_guide(){
        $data = session('third_oauth');
        //没有第三方登录的话就跳到登录页
        if(empty($data)){
            $this->redirect('User/login');
        }
        $first_leader = Cache::get($data['openid']);
        if($first_leader){
            //拿关注传时候过来来的上级id
            setcookie('first_leader',$first_leader);
        }
        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);
        $this->assign('store_name',tpCache('shop_info.store_name'));
        return $this->fetch();
    }

    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        $mobile = input('mobile/s');
        $verify_code = input('verify_code/s');
        //发送短信验证码
        $logic = new UsersLogic();
        $check_code = $logic->check_validate_code($verify_code, $mobile, 'phone', session_id(), 1);
        if($check_code['status'] != 1){
            $this->ajaxReturn(['status'=>0,'msg'=>$check_code['msg'],'result'=>'']);
        }
        if(empty($mobile) || !check_mobile($mobile)){
            $this->ajaxReturn(['status' => 0, 'msg' => '手机格式错误']);
        }
        $users = Db::name('users')->where('mobile',$mobile)->find();
        if (empty($users)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '账号不存在']);
        }
        $user = new \app\common\logic\User();
        $user->setUserById($users['user_id']);
        $cartLogic = new CartLogic();
        try{
            $user->checkOauthBind();
            $user->oauthBind();
            $user->doLeader();
            $user->refreshCookie();
            $cartLogic->setUserId($users['user_id']);
            $cartLogic->doUserLoginHandle();
            $orderLogic = new OrderLogic();//登录后将超时未支付订单给取消掉
            $orderLogic->setUserId($users['user_id']);
            $orderLogic->abolishOrder();
            $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功']);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }
    /**
     * 先注册再绑定账号
     * @return \think\mixed
     */
    public function bind_reg()
    {
        $mobile = input('mobile/s');
        $verify_code = input('verify_code/s');
        $password = input('password/s');
        $nickname = input('nickname/s', '');
        if(empty($mobile) || !check_mobile($mobile)){
            $this->ajaxReturn(['status' => 0, 'msg' => '手机格式错误']);
        }
        if(empty($password)){
            $this->ajaxReturn(['status' => 0, 'msg' => '请输入密码']);
        }
        $logic = new UsersLogic();
        $check_code = $logic->check_validate_code($verify_code, $mobile, 'phone', session_id(), 1);
        if($check_code['status'] != 1){
            $this->ajaxReturn(['status'=>0,'msg'=>$check_code['msg'],'result'=>'']);
        }
        $thirdUser = session('third_oauth');
        $data = $logic->reg($mobile, $password, $password, 0, [], $nickname, $thirdUser['head_pic']);
        if ($data['status'] != 1) {
            $this->ajaxReturn(['status'=>0,'msg'=>$data['msg'],'result'=>'']);
        }
        $user = new \app\common\logic\User();
        $user->setUserById($data['result']['user_id']);
        try{
            $user->checkOauthBind();
            $user->oauthBind();
            $user->refreshCookie();
            $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功']);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    public function ajaxAddressList()
    {
        $UserAddress = new UserAddress();
        $address_list = $UserAddress->where('user_id', $this->user_id)->order('is_default desc')->select();
        if($address_list){
            $address_list = collection($address_list)->append(['address_area'])->toArray();
        }else{
            $address_list = [];
        }
        $this->ajaxReturn($address_list);
    }

    /**
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists =  db('user_address')->where('user_id', $this->user_id)->select();
        $region_list = db('region')->cache(true)->getField('id,name');
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /**
     * 保存地址
     */
    public function addressSave()
    {
        $address_id = input('address_id/d',0);
        $data = input('post.');
        $userAddressValidate = Loader::validate('UserAddress');
        if (!$userAddressValidate->batch()->check($data)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'result' => $userAddressValidate->getError()]);
        }
        if (!empty($address_id)) {
            //编辑
            $userAddress = UserAddress::get(['address_id'=>$address_id,'user_id'=> $this->user_id]);
            if(empty($userAddress)){
                $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
            }
        } else {
            //新增
            $userAddress = new UserAddress();
            $user_address_count = Db::name('user_address')->where("user_id", $this->user_id)->count();
            if ($user_address_count >= 20) {
                $this->ajaxReturn(['status' => 0, 'msg' => '最多只能添加20个收货地址']);
            }
            $data['user_id'] = $this->user_id;
        }
        $userAddress->data($data);
        $userAddress['longitude'] = true;
        $userAddress['latitude'] = true;
        $row = $userAddress->save();
        if ($row !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result'=>['address_id'=>$userAddress->address_id]]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
        }
    }
    /*
         * 添加地址
         */
    public function add_address()
    {
        $source = input('source');
        if (IS_POST) {
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, $post_data);
            if ($data['status'] != 1){
                $this->ajaxReturn($data);
            } else {
                $data['url']= U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        $this->assign('source', $source);
        return $this->fetch();

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $post_data = input('post.');
            $source = $post_data['source'];
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, $post_data);
            if ($source == 'cart2') {
                $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$post_data['goods_id'],'goods_num'=>$post_data['goods_num'],'item_id'=>$post_data['item_id'],'action'=>$post_data['action']));
                $this->ajaxReturn($data);
            } elseif ($source == 'integral') {
                $data['url'] = U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$post_data['goods_id'],'goods_num'=>$post_data['goods_num'],'item_id'=>$post_data['item_id']));
                $this->ajaxReturn($data);
            } elseif($source == 'pre_sell_cart'){
                $data['url'] = U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif($source == 'team'){
                $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$post_data['order_id']));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'pre_sell') {
                $prom_id = input('prom_id/d');
                $data['url'] = U('/Mobile/Cart/pre_sell', array('address_id' => $data['result'],'goods_num' => $goods_num,'prom_id' => $prom_id));
                $this->ajaxReturn($data);
            } else {
                $data['url']= U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d');

        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->error('操作失败', U('User/address_list'));
        else
            $this->success("操作成功", U('User/address_list'));
    }


    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            if ($_FILES['head_pic']['tmp_name']) {
                $file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
                $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
                $dir = UPLOAD_PATH.'head_pic/';
                if (!($_exists = file_exists($dir))){
                    $isMk = mkdir($dir);
                }
                $parentDir = date('Ymd');
                $info = $file->validate($validate)->move($dir, true);
                if($info){
                    $post['head_pic'] = '/'.$dir.$parentDir.'/'.$info->getFilename();
                }else{
                    $this->error($file->getError());//上传错误提示错误信息
                }
            }
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            I('post.email') ? $post['email'] = I('post.email') : false; //邮箱
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机

            $email = I('post.email');
            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 6);

            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }

            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");
            setcookie('uname',urlencode($post['nickname']),null,'/');
            $this->success("操作成功",U('User/userinfo'));
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            return $this->fetch("$dispaly");
        }
        return $this->fetch();
    }

    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile(){
        $userLogic = new UsersLogic();
//        $status=0;
        if (IS_POST) {
            $mobile = input('mobile');
            $mobile_code = input('mobile_code');
            $scene = input('post.scene', 6);
            $validate = I('validate',0);
            $status = I('status',0);
            $c = Db::name('users')->where(['mobile' => $mobile, 'user_id' => ['<>', $this->user_id]])->count();
            $c && $this->error('手机已被使用');
//            if (!$mobile_code)
//                $this->error('请输入验证码');
//            $check_code = $userLogic->check_validate_code($mobile_code, $mobile, 'phone', $this->session_id, $scene);
//            if($check_code['status'] !=1){
//                $this->error($check_code['msg']);
//            }
            // 验证码
//            $code=I('mobile_code');
            $sms_type=I('sms_type');
            $checkData['sms_type'] = $sms_type;
            $checkData['code'] = $mobile_code;
            $checkData['phone'] = $mobile;
            $res = checkPhoneCode($checkData);
            if ($res['code'] == 0) {
                $this->error($res['msg']);
            }


            if($validate == 1 && $status == 0){
                $res = Db::name('users')->where(['user_id' => $this->user_id])->update(['mobile'=>$mobile,'mobile_validated'=>1]);

                if($res!==false){
                    $source = I('source');
                    !empty($source) && $this->success('绑定成功', U("User/$source"));
                    $this->success('修改成功',U('User/userinfo'));
                }
                $this->error('修改失败');
            }
        }
        $this->assign('status',$status);
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) {      //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id/d');
        $user_id = $this->user_id;
        if (M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            if(!$this->verifyHandle('message')){
                $this->error('验证码错误', U('User/message_list'));
            };

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id", $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all');    //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $show = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $show);
        $this->assign('listRows', $Page->listRows);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }


    public function points_list()
    {
        $type = I('type','all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->points($this->user_id, $type);

        $this->assign('type', $type);
        $showpage = $result['page']->show();
        $this->assign('account_log', $result['account_log']);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
        }
        return $this->fetch();
    }


    /*
     * 密码修改
     */
    public function password()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->get_info($this->user_id);
            $user = $data['result'];
            if ($user['mobile'] == '' && $user['email'] == '')
                $this->ajaxReturn(['status'=>-1,'msg'=>'请先绑定手机或邮箱','url'=>U('/Mobile/User/index')]);
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
            if ($data['status'] == -1)
                $this->ajaxReturn(['status'=>-1,'msg'=>$data['msg']]);
            Session::delete('user');
            $this->ajaxReturn(['status'=>1,'msg'=>$data['msg'],'url'=>U('/Mobile/User/index')]);
            exit;
        }
        return $this->fetch();
    }

    function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                if(!$this->verifyHandle('forget')){
                    $this->ajaxReturn(['status'=>-1,'msg'=>"验证码错误"]);
                };
                // 验证码
                $code=I('mobile_code');
                $sms_type=I('sms_type');
                $checkData['sms_type'] = $sms_type;
                $checkData['code'] = $code;
                $checkData['phone'] = $username;
                $res = checkPhoneCode($checkData);
                if ($res['code'] == 0) {
                    return json(['status' => -1, 'msg' => $res['msg']]);
                }
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                if ($user) {
                    $sms_status = checkEnableSendSms(2);
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field,'sms_status'=>$sms_status['status']));
                    $regis_smtp_enable = $this->tpshop_config['smtp_regis_smtp_enable'];
                    if(($field=='mobile' && $this->tpshop_config['sms_forget_pwd_sms_enable']==1)){
                        $this->ajaxReturn(['status'=>1,'msg'=>"用户验证成功",'url'=>U('User/find_pwd')]);
                    }

                    if(($field=='email' && $regis_smtp_enable==0) || ($field=='mobile' && $sms_status['status']<1)){
                        $this->ajaxReturn(['status'=>1,'msg'=>"用户验证成功",'url'=>U('User/set_pwd')]);
                    }
                    exit;
                } else {
                    $this->ajaxReturn(['status'=>-1,'msg'=>"用户名不存在，请检查"]);
                }
            }
        }
        return $this->fetch();
    }

    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        return $this->fetch();
    }


    public function set_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect('Mobile/User/index');
        }
        $check = session('validate_code');
        $find_password = session('find_password');
        $field = $find_password['field'];
        $sms_status = session('find_password')['sms_status'];
        $regis_smtp_enable = $this->tpshop_config['smtp_regis_smtp_enable'];
        $is_check_code=false;
        //需要验证邮箱或者手机
        if($field=='email' && $regis_smtp_enable==1)$is_check_code = true;
        if($field=='mobile' && $sms_status['status']==1)$is_check_code = true;
        if ((empty($check) || $check['is_check'] == 0) && $is_check_code) {
            $this->error('验证码还未验证通过',U('User/forget_pwd'));
        }
        if (IS_POST) {
            $data['password'] = $password = I('post.password');
            $data['password2'] = $password2 = I('post.password2');
            $UserRegvalidate = Loader::validate('User');
            if(!$UserRegvalidate->scene('set_pwd')->check($data)){
                $this->error($UserRegvalidate->getError(),U('User/forget_pwd'));
            }
            M('users')->where("user_id", $find_password['user_id'])->save(array('password' => encrypt($password)));
            session('validate_code', null);
            return $this->fetch('reset_pwd_sucess');
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            return false;
        }
        return true;
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 30,
            'length' => 4,
            'imageH' =>  60,
            'imageW' =>  300,
            'fontttf' => '5.ttf',
            'useCurve' => false,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    public function recharge()
    {
        $order_id = I('order_id/d');
        $paymentList = M('Plugin')->where(['type'=>'payment' ,'code'=>['neq','cod'],'status'=>1,'scene'=> ['in','0,1']])->select();
        $paymentList = convert_arr_key($paymentList, 'code');
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            unset($paymentList['weixinH5']);
        }else{
            unset($paymentList['weixin']);
        }
        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        // 查找最近一次充值方式
        $recharge_arr = Db::name('Recharge')->field('pay_code')->where('user_id', $this->user_id)
            ->order('order_id desc')->find();
        $alipay = 'alipayMobile'; //默认支付宝支付
        if($recharge_arr){
            foreach ($paymentList as  $key=>$item) {
                if($key == $recharge_arr['pay_code']){
                    $alipay = $recharge_arr['pay_code'];
                }
            }
        }
        $this->assign('alipay', $alipay);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }

    public function recharge_list(){
        $usersLogic = new UsersLogic;
        $result= $usersLogic->get_recharge_log($this->user_id);  //充值记录
        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
        if (I('is_ajax')) {
            return $this->fetch('ajax_recharge_list');
        }
        return $this->fetch();
    }

    //添加、编辑提现支付宝账号
    public function add_card(){
        $user_id=$this->user_id;
        $data=I('post.');
        if($data['type']==0){
            $info['cash_alipay']=$data['card'];
            $info['realname']=$data['cash_name'];
            $info['user_id']=$user_id;
            $res=DB::name('user_extend')->where('user_id='.$user_id)->count();
            if($res){
                $res2=Db::name('user_extend')->where('user_id='.$user_id)->save($info);
            }else{
                $res2=Db::name('user_extend')->add($info);
            }
            if($res2){
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }else{
                //防止非支付宝类型的表单提交
                $this->ajaxReturn(['status'=>0,'msg'=>'不支持的提现方式']);
            }
        }

        if($data['type']==1){
            $info['cash_unionpay']=$data['card'];
            $info['realname']=$data['cash_name'];
            $info['user_id']=$user_id;
            $res=DB::name('user_extend')->where('user_id='.$user_id)->count();
            if($res){
                $res2=Db::name('user_extend')->where('user_id='.$user_id)->save($info);
            }else{
                $res2=Db::name('user_extend')->add($info);
            }
            if($res2){
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }else{
                //防止非银行卡类型的表单提交
                $this->ajaxReturn(['status'=>0,'msg'=>'不支持的提现方式']);
            }
        }

    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {

        // dump($this->user['goods_id']);
        $config = tpCache('cash');
        // dump($config);
        C('TOKEN_ON', true);
        $cash_open=tpCache('cash.cash_open');
        if($cash_open!=1){
            $this->error('提现功能已关闭,请联系商家');
        }
        if (IS_POST) {
            $cash_open=tpCache('cash.cash_open');
            if($cash_open!=1){
                $this->ajaxReturn(['status'=>0, 'msg'=>'提现功能已关闭,请联系商家']);
            }
            if(intval($config['goods_id'])){
                if($this->user['is_cash'] < 1){
                    $this->ajaxReturn(['status'=>0, 'msg'=>'需完成推广报单产品后解锁提现']);
                }
            }

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $cash = tpCache('cash');

            if(encrypt($data['paypwd']) != $this->user['paypwd']){
                $this->ajaxReturn(['status'=>0, 'msg'=>'支付密码错误']);
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status'=>0, 'msg'=>"本次提现余额不足"]);
            }
            if ($data['money'] <= 0) {
                $this->ajaxReturn(['status'=>0, 'msg'=>'提现额度必须大于0']);
            }
            if ($data['money']%100 !== 0)
            {
                $this->ajaxReturn(['status'=>0,'msg'=>"提现金额必须为100的倍数"]);
                exit;
            }

            // 统计所有0，1的金额
            $status = ['in','0,1'];
//            $total_money = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status))->sum('money');
//            if ($total_money + $data['money'] > $this->user['user_money']) {
//                $this->ajaxReturn(['status'=>0, 'msg'=>"您有提现申请待处理，本次提现余额不足"]);
//            }
            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status'=>0, 'msg'=>"本次提现余额不足"]);
            }

            if ($cash['cash_open'] == 1) {
                $taxfee =  round($data['money'] * $cash['service_ratio'] / 100, 2);
                // 限手续费
                if ($cash['max_service_money'] > 0 && $taxfee > $cash['max_service_money']) {
                    $taxfee = $cash['max_service_money'];
                }
                if ($cash['min_service_money'] > 0 && $taxfee < $cash['min_service_money']) {
                    $taxfee = $cash['min_service_money'];
                }
                if ($taxfee >= $data['money']) {
                    $this->ajaxReturn(['status'=>0, 'msg'=>'提现额度必须大于手续费！']);
                }
                $data['taxfee'] = $taxfee;

                // 每次限最多提现额度
                if ($cash['min_cash'] > 0 && $data['money'] < $cash['min_cash']) {
                    $this->ajaxReturn(['status'=>0, 'msg'=>'每次最少提现额度' . $cash['min_cash']]);
                }
                if ($cash['max_cash'] > 0 && $data['money'] > $cash['max_cash']) {
                    $this->ajaxReturn(['status'=>0, 'msg'=>'每次最多提现额度' . $cash['max_cash']]);
                }

                $status = ['in','0,1,2,3'];
                $create_time = ['gt',strtotime(date("Y-m-d"))];
                // 今天限总额度
                if ($cash['count_cash'] > 0) {
                    $total_money2 = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->sum('money');
                    if (($total_money2 + $data['money'] > $cash['count_cash'])) {
                        $total_money = $cash['count_cash'] - $total_money2;
                        if ($total_money <= 0) {
                            $this->ajaxReturn(['status'=>0, 'msg'=>"你今天累计提现额为{$total_money2},金额已超过可提现金额."]);
                        } else {
                            $this->ajaxReturn(['status'=>0, 'msg'=>"你今天累计提现额为{$total_money2}，最多可提现{$total_money}账户余额."]);
                        }
                    }
                }
                // 今天限申请次数
                if ($cash['cash_times'] > 0) {
                    $total_times = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->count();
                    if ($total_times >= $cash['cash_times']) {
                        $this->ajaxReturn(['status'=>0, 'msg'=>"今天申请提现的次数已用完."]);
                    }
                }
            }else{
                $data['taxfee'] = 0;
            }
//            dump($data);die;
            if (M('withdrawals')->add($data)) {
                Db::name('users')->where('user_id',$data['user_id'])->setDec('user_money',$data['money']);
                $this->ajaxReturn(['status'=>1,'msg'=>"已提交申请",'url'=>U('User/account',['type'=>2])]);
            } else {
                $this->ajaxReturn(['status'=>0,'msg'=>'提交失败,联系客服!']);
            }
        }




        $user_extend=Db::name('user_extend')->where('user_id='.$this->user_id)->find();

        //获取用户绑定openId
//        $oauthUsers = M("OauthUsers")->where(['user_id'=>$this->user_id, 'oauth'=>'wx'])->find();
//        $openid = $oauthUsers['openid'];
//        if(empty($oauthUsers)){
//            $openid = Db::name('oauth_users')->where(['user_id'=>$this->user_id, 'oauth'=>'weixin'])->value('openid');
//        }


        $this->assign('user_extend',$user_extend);
        $this->assign('cash_config', tpCache('cash'));//提现配置项
        $this->assign('user_money', $this->user['user_money']);    //用户余额
//        $this->assign('openid',$openid);    //用户绑定的微信openid
        return $this->fetch();
    }

//    //手机端是通过扫码PC端来绑定微信,需要ajax获取一下openID
//    public function get_openid(){
//        //halt($this->user_id); 22
//        $oauthUsers = M("OauthUsers")->where(['user_id'=>$this->user_id, 'oauth'=>'weixin'])->find();
//        $openid = $oauthUsers['openid'];
//        if(empty($oauthUsers)){
//            $openid = Db::name('oauth_users')->where(['user_id'=>$this->user_id, 'oauth'=>'wx'])->value('openid');
//        }
//        if($openid){
//            $this->ajaxReturn(['status'=>1,'result'=>$openid]);
//        }else{
//            $this->ajaxReturn(['status'=>0,'result'=>'']);
//        }
//    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        // $pagesize = C('PAGESIZE'); //10条数据，不显示滚动效果
        // $page = new Page($count, $pagesize);
        $page = new Page($count, 15);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
        }
        return $this->fetch();
    }

    /**
     * 我的关注
     * @author lxl
     * @time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author yhj
     * @time 2018/07/10
     */
    public function message_notice()
    {
        $message_logic = new Message();
        $message_logic->checkPublicMessage();
        $where = array(
            'user_id' => $this->user_id,
            'deleted' => 0,
            'category' => 0
        );
        $userMessage = new UserMessage();
        $data['message_notice'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        $where['category'] = 1;
        $data['message_activity'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        $where['category'] = 2;
        $data['message_logistics'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        //$where['category'] = 3;
        //$data['message_private'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        $data['no_read'] = $message_logic->getUserMessageCount();

        // 最近消息，日期，内容
        $this->assign($data);
        return $this->fetch();
    }


    /**
     * 查看通知消息详情
     */
    public function message_notice_detail()
    {

        $type = I('type', 0);
        // $type==3私信，暂时没有

        $message_logic = new Message();
        $message_logic->checkPublicMessage();

        $where = array(
            'user_id' => $this->user_id,
            'deleted' => 0,
            'category' => $type
        );
        $userMessage = new UserMessage();
        $count = $userMessage->where($where)->count();
        $page = new Page($count, 10);
        //$lists = $userMessage->where($where)->order("rec_id DESC")->limit($page->firstRow . ',' . $page->listRows)->select();

        $rec_id = $userMessage->where( $where)->LIMIT($page->firstRow.','.$page->listRows)->order('rec_id desc')->column('rec_id');
        $lists = $message_logic->sortMessageListBySendTime($rec_id, $type);

        $this->assign('lists', $lists);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_message_detail');
        }
        if (empty($lists)) {
            return $this->fetch('user/message_none');
        }
        return $this->fetch();
    }

    /**
     * 通知消息详情
     */
    public function message_notice_info(){
        $message_logic = new Message();
        $message_details = $message_logic->getMessageDetails(I('msg_id'), I('type', 0));
        $this->assign('message_details', $message_details);
        return $this->fetch();
    }

    /**
     * 浏览记录
     */
    public function visit_log()
    {
        $count = M('goods_visit')->where('user_id', $this->user_id)->count();
        $Page = new Page($count, 20);
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $this->user_id)
            ->order('v.visittime desc')
            ->limit($Page->firstRow, $Page->listRows)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        $this->assign('visit_list', $visit_list);
        if (I('get.is_ajax', 0)) {
            return $this->fetch('ajax_visit_log');
        }
        return $this->fetch();
    }

    /**
     * 删除浏览记录
     */
    public function del_visit_log()
    {
        $visit_ids = I('get.visit_ids', 0);
        $row = M('goods_visit')->where('visit_id','IN', $visit_ids)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 清空浏览记录
     */
    public function clear_visit_log()
    {
        $row = M('goods_visit')->where('user_id', $this->user_id)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号',U('User/setMobile',['source'=>'paypwd']));
        $step = I('step', 1);
//        if ($step > 1) {
//            $check = session('validate_code');
//            if (empty($check)) {
//                $this->error('验证码还未验证通过', U('mobile/User/paypwd'));
//            }
//        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
//            if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
//                $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>'']);
//            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        return $this->fetch();
    }


    /**
     * 会员签到积分奖励
     * 2017/9/28
     */
    public function sign()
    {
        $userLogic = new UsersLogic();
        $user_id = $this->user_id;
        $info = $userLogic->idenUserSign($user_id);//标识签到
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * Ajax会员签到
     * 2017/11/19
     */
    public function user_sign()
    {
        $userLogic = new UsersLogic();
        $user_id   = $this->user_id;
        $config    = tpCache('sign');
        $date      = I('date'); //2017-9-29
        //是否正确请求
        (date("Y-n-j", time()) != $date) && $this->ajaxReturn(['status' => false, 'msg' => '签到失败！', 'result' => '']);
        //签到开关
        if ($config['sign_on_off'] > 0) {
            $map['sign_last'] = $date;
            $map['user_id']   = $user_id;
            $userSingInfo     = Db::name('user_sign')->where($map)->find();
            //今天是否已签
            $userSingInfo && $this->ajaxReturn(['status' => false, 'msg' => '您今天已经签过啦！', 'result' => '']);
            //是否有过签到记录
            $checkSign = Db::name('user_sign')->where(['user_id' => $user_id])->find();
            if (!$checkSign) {
                $result = $userLogic->addUserSign($user_id, $date);            //第一次签到
            } else {
                $result = $userLogic->updateUserSign($checkSign, $date);       //累计签到
            }
            $return = ['status' => $result['status'], 'msg' => $result['msg'], 'result' => ''];
        } else {
            $return = ['status' => false, 'msg' => '该功能未开启！', 'result' => ''];
        }
        $this->ajaxReturn($return);
    }


    /**
     * vip充值
     */
    public function rechargevip(){
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);
        return $this->fetch();
    }


    /**
     * 个人海报推广二维码 （我的名片）
     */
    public function qr_code()
    {
        $user_id = $this->user['user_id'];
        if (!$user_id) {
            return $this->fetch();
        }
        //判断是否是分销商
        $user = M('users')->where('user_id', $user_id)->find();
//        if (!$user && $user['is_distribut'] != 1) {
//            return $this->fetch();
//        }

        //判断是否存在海报背景图
        if(!DB::name('poster')->where(['enabled'=>1])->find()){
            echo "<script>alert('请上传海报背景');</script>";
            return $this->fetch();
        }

        //分享数据来源
        $shareLink = urlencode("http://{$_SERVER['HTTP_HOST']}/index.php?m=Mobile&c=Index&a=index&first_leader={$user['user_id']}");

        $head_pic = $user['head_pic'] ?: '';
        if ($head_pic && strpos($head_pic, 'http') !== 0) {
            $head_pic = '.'.$head_pic;
        }

        $this->assign('user',  $user);
        $this->assign('head_pic', $head_pic);
        $this->assign('ShareLink', $shareLink);
        return $this->fetch();
    }

    // 用户海报二维码
    public function poster_qrcode()
    {
        ob_end_clean();
        vendor('topthink.think-image.src.Image');
        vendor('phpqrcode.phpqrcode');

        error_reporting(E_ERROR);
        $url = isset($_GET['data']) ? $_GET['data'] : '';
        $url = urldecode($url);

        $poster = DB::name('poster')->where(['enabled'=>1])->find();
        define('IMGROOT_PATH', str_replace("\\","/",realpath(dirname(dirname(__FILE__)).'/../../'))); //图片根目录（绝对路径）
        $project_path = '/public/images/poster/'.I('_saas_app','all');
        $file_path = IMGROOT_PATH.$project_path;

        if(!is_dir($file_path)){
            mkdir($file_path,777,true);
        }

        $head_pic = input('get.head_pic', '');                   //个人头像
        $back_img = IMGROOT_PATH.$poster['back_url'];            //海报背景
        $valid_date = input('get.valid_date', 0);                //有效时间

        $qr_code_path = UPLOAD_PATH.'qr_code/';
        if (!file_exists($qr_code_path)) {
            mkdir($qr_code_path,777,true);
        }

        /* 生成二维码 */
        $qr_code_file = $qr_code_path.time().rand(1, 10000).'.png';
        \QRcode::png($url, $qr_code_file, QR_ECLEVEL_M,1.8);

        /* 二维码叠加水印 */
        $QR = Image::open($qr_code_file);
        $QR_width = $QR->width();
        $QR_height = $QR->height();

        /* 添加头像 */
        if ($head_pic) {
            //如果是网络头像
            if (strpos($head_pic, 'http') === 0) {
                //下载头像
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $head_pic);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $file_content = curl_exec($ch);
                curl_close($ch);
                //保存头像
                if ($file_content) {
                    $head_pic_path = $qr_code_path.time().rand(1, 10000).'.png';
                    file_put_contents($head_pic_path, $file_content);
                    $head_pic = $head_pic_path;
                }
            }
            //如果是本地头像
            if (file_exists($head_pic)) {
                $logo = Image::open($head_pic);
                $logo_width = $logo->height();
                $logo_height = $logo->width();
                $logo_qr_width = $QR_width / 4;
                $scale = $logo_width / $logo_qr_width;
                $logo_qr_height = $logo_height / $scale;
                $logo_file = $qr_code_path.time().rand(1, 10000);
                $logo->thumb($logo_qr_width, $logo_qr_height)->save($logo_file, null, 100);
                $QR = $QR->thumb($QR_width, $QR_height)->water($logo_file, \think\Image::WATER_CENTER);
                $logo_file && unlink($logo_file);
            }
            $head_pic_path && unlink($head_pic_path);
        }

        if ($valid_date && strpos($url, 'weixin.qq.com') !== false) {
            $QR = $QR->text('有效时间 '.$valid_date, "./vendor/topthink/think-captcha/assets/zhttfs/1.ttf", 7, '#00000000', Image::WATER_SOUTH);
        }
        $QR->save($qr_code_file, null, 100);

        $canvas_maxWidth = $poster['canvas_width'];
        $canvas_maxHeight = $poster['canvas_height'];
        $info = getimagesize($back_img);                                                           //取得一个图片信息的数组
        $im = checkPosterImagesType($info,$back_img);                                              //根据图片的格式对应的不同的函数
        $rate_poster_width = $canvas_maxWidth/$info[0];                                            //计算绽放比例
        $rate_poster_height = $canvas_maxHeight/$info[1];
        $maxWidth =  floor($info[0]*$rate_poster_width);
        $maxHeight = floor($info[1]*$rate_poster_height);                                          //计算出缩放后的高度
        $des_im = imagecreatetruecolor($maxWidth,$maxHeight);                                      //创建一个缩放的画布
        imagecopyresized($des_im,$im,0,0,0,0,$maxWidth,$maxHeight,$info[0],$info[1]);              //缩放
        $news_poster = $file_path.'/'.createImagesName() . ".png";                                 //获得缩小后新的二维码路径
        inputPosterImages($info,$des_im,$news_poster);                                             //输出到png即为一个缩放后的文件
        $QR = imagecreatefromstring(file_get_contents($qr_code_file));
        $background_img = imagecreatefromstring ( file_get_contents ( $news_poster ) );

        imagecopyresampled ( $background_img, $QR,$poster['canvas_x'],$poster['canvas_y'],0,0,80,92,80, 78 );      //合成图片
        $result_png = '/'.createImagesName(). ".png";
        $file = $file_path . $result_png;
        imagepng ($background_img, $file);                                                          //输出合成海报图片
        $final_poster = imagecreatefromstring ( file_get_contents (  $file ) );                     //获得该图片资源显示图片
        header("Content-type: image/png");
        imagepng ( $final_poster);
        imagedestroy( $final_poster);
        $news_poster && unlink($news_poster);
        $qr_code_file && unlink($qr_code_file);
        $file && unlink($file);
        exit;
    }
    /**
     * 奖金币互转
     * */
    public function  between()
    {
        if (IS_POST) {
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            //接收方id
            $userid = Db::name('users')->where('user_id',$data['accept_id'])->find();

            if($userid){
                $accept_id = $data['accept_id'];
            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>'用户不存在']);
                exit;
            }
            //转出多少奖金币
            if($data['turnhow']%100 !== 0)
            {
                $this->ajaxReturn(['status'=>0,'msg'=>"转赠金额必须为100的倍数"]);
                exit;
            }
            $turnhow = $data['turnhow'];
            $payp = Db::name('users')->where('user_id',$this->user_id)->field('pay_points')->find();

            $balances = $turnhow + $userid['pay_points'];
            $balance = $payp['pay_points']-$turnhow;

            if ($payp['pay_points'] > $turnhow){

                // 启动事务
                Db::startTrans();
                try {
                    $res1 = Db::name('users')->where('user_id',$accept_id)->setInc('pay_points',$turnhow);
                    $result = Db::execute("update tp_users set pay_points = pay_points-$turnhow where user_id = $this->user_id ");

                    //赠出奖金币记录
                    $res = Db::name('menber_balance_log')->insert([
                        'user_id' => $this->user_id,
                        'balance_type' => 0,
                        'log_type' => 1,
//                        'source_type' => 5,
//                        'source_id' => $data['refund_sn'],
                        'money' => $turnhow,
                        'old_balance' => $payp['pay_points'],
                        'balance' => $balance,
                        'create_time' => time(),
                        'note' => '赠送奖金币'
                    ]);
                    if(!$res){
                        Db::rollback();
                        return false;
                    }

                    //收到奖金币记录
                    $res = Db::name('menber_balance_log')->insert([
                        'user_id' => $accept_id,
                        'balance_type' => 0,
                        'log_type' => 1,
//                        'source_type' => 5,
//                        'source_id' => $data['refund_sn'],
                        'money' => $turnhow,
                        'old_balance' => $userid['pay_points'],
                        'balance' => $balances,
                        'create_time' => time(),
                        'note' => '收到奖金币'
                    ]);
                    if(!$res){
                        Db::rollback();
                        return false;
                    }
                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                }
                $this->ajaxReturn(['status'=>1,'msg'=>"转赠成功"]);
            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>"奖金币余额不足"]);
                exit;
            }

        }

        return $this->fetch();
    }

}
=======
<?php
/**
 * tpshop
 * ============================================================================
 * * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * 2015-11-21
 */
namespace app\mobile\controller;

use app\common\logic\CartLogic;
use app\common\logic\Message;
use app\common\logic\UsersLogic;
use app\common\logic\OrderLogic;
use app\common\model\MenuCfg;
use app\common\model\UserAddress;
use app\common\model\Users as UserModel;
use app\common\model\UserMessage;
use app\common\util\TpshopException;
use app\common\model\UserWaitEarnings as Earnings;
use think\Cache;
use think\Page;
use think\Request;
use think\Verify;
use think\Loader;
use think\db;
use think\Image;
use think\Session;

class User extends MobileBase
{

    public $user_id = 0;
    public $user = array();

    /*
    * 初始化操作
    */
    public function _initialize()
    {
        parent::_initialize();
        if (session('?user')) {
            $User = new UserModel();
            $session_user = session('user');
            $this->user = $User->where('user_id', $session_user['user_id'])->find();
            if(!empty($this->user->auth_users)){
                $session_user = array_merge($this->user->toArray(), $this->user->auth_users[0]);
                session('user', $session_user);  //覆盖session 中的 user
            }
            $this->user_id = $this->user['user_id'];
            $this->assign('user', $this->user); //存储用户信息0
        }
        $nologin = array(
            'login', 'pop_login', 'do_login', 'logout', 'verify', 'set_pwd', 'finished',
            'verifyHandle', 'reg', 'send_sms_reg_code', 'find_pwd', 'check_validate_code',
            'forget_pwd', 'check_captcha', 'check_username', 'send_validate_code', 'express' , 'bind_guide', 'bind_account','bind_reg','getPhoneVerify','record_again'
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

        $order_status_coment = array(
            'WAITPAY' => '待付款 ', //订单查询状态 待支付
            'WAITSEND' => '待发货', //订单查询状态 待发货
            'WAITRECEIVE' => '待收货', //订单查询状态 待收货
            'WAITCCOMMENT' => '待评价', //订单查询状态 待评价
        );
        $Earnings = new Earnings;
        $wait_earnings = $Earnings->where('user_id',$this->user_id)->find();

        //不在同一天清空待收益
        if ($wait_earnings) {
            $today = intval(date('Ymd'));
            $time = intval(date('Ymd',strtotime($wait_earnings['update_time'])));

            if ($today != $time) {
                $wait_earnings->money = 0;
                $wait_earnings->obj = null;

                $wait_earnings->save();
            }
        }

        $this->assign('order_status_coment', $order_status_coment);
    }


    //     //推荐
    //     public function recommend($share_user)
    //     {
    //         //获取上级id
           
    //       //   $recommend_id=19945;
    //         $recommend_id=$share_user;
    
    //         $user_id=$this->user_id;
    //         //判断自己是否已经有直属上级
    //         $myInfo=Db::name('users')->where('user_id','=',$user_id)->find();
    //         if($myInfo['first_leader']){
    //             $this->error("已经有上级不能在被推荐");
    //         }
    //         $time=time();
    //         // $firstUpdate=Db::name('users')->update(['user_id'=>$user_id,'first_leader'=>$recommend_id]);
    
    //         //推荐成功 统计上级的直属下级数量 更新上级的身份  经理还是总监
    //         $upPopCount=Db::name('users')->where('first_leader','=',$recommend_id)->count(); 
        
    //       $manager_ind_sum=$this->popUpdateCondition(1);  //升级经理的条件
    //       $chief_ind_sum=$this->popUpdateCondition(2);    //升级总监的条件
    //       $ceo_ind_sum=$this->popUpdateCondition(3);
    //       $partner_ind_sum=$this->popUpdateCondition(4);
  
    //         if($upPopCount>=$manager_ind_sum&&$upPopCount<$chief_ind_sum){
    //             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>1]);
    //         }
    //         if($upPopCount>=$chief_ind_sum&&$upPopCount<$ceo_ind_sum){
    //             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>2]);
    //         }
    //         if($upPopCount>=$ceo_ind_sum&&$upPopCount<$partner_ind_sum){
    //             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>3]);
    //         }
    //         if($upPopCount>=$partner_ind_sum){
    //             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>4]);
    //         }
    
    
    //         // if($firstUpdate){
    //                               // return $this->success("直属上级推荐成功");
    //             $recommendInfo=Db::name('users')->where('user_id',$recommend_id)->find();
    //                 if($recommendInfo['agent_level']){
    //                     //如果上级是代理身份,就给上级奖励   //并减少对应的推广额度
    
    //                     //这里有个条件前提   周数和业绩   不同要求不一样
    //                         $pop_commission=Db::name('config')->where('name','=','pop_commission')->value('value');
    //                         $pop_money=Db::name('config')->where('name','=','pop_money')->value('value');
    //                         $addmoney=$pop_money*$pop_commission/100;
    //                         $user_money=$recommendInfo['user_money']+$addmoney;
    //                         Db::name('users')->update(['user_id'=>$recommend_id,'user_money'=>$user_money]);
    //                         Db::name('account_log')->insert(['user_id'=>$recommend_id,'user_money'=>$addmoney,'pay_points'=>0,'change_time'=>$time,'desc'=>'邀请1个新会员奖励50','type'=>2]);
                        
    //                         $whereStr['user_id']=['=',$recommendInfo['user_id']];
    //                         $whereStr['period']=['=',$recommendInfo['default_period']];
    //                         $periodInfo=Db::name('pop_period')->where($whereStr)->find();
    //                         if($periodInfo['begin_time']){  //如果时间已经开始再操作下面
    //                             if($periodInfo['poped_per_num']<$periodInfo['person_num']){ //还有位置就操作
    //                                 Db::name('pop_period')->where($whereStr)->setInc('poped_per_num');
    //                             }else{ //没有位置就跳到上一级    如果没有上一级就修改用户表    
    //                                 $upPeriod=$recommendInfo['default_period']+1; 
    //                                 $upPeriodInfo=Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->find();
    //                                 if($upPeriodInfo){ //如果有上级
    //                                     //还有下一期的话 分情况   一周内 和一周外
    //                                     if(($periodInfo['begin_time']+3600*24*7)>$time){
    //                                             Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['day_release'=>1]);
    //                                     }else{
    //                                             Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['week_release'=>1]);
    //                                     }
    //                                     // Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['poped_per_num'=>1,'begin_time'=>$time]);
    //                                     // Db::name('users')->where('user_id','=',$recommendInfo['user_id'])->update(['default_period'=>$upPeriod]);
    //                                 }else{
    //                                     Db::name('users')->where('user_id','=',$recommendInfo['user_id'])->update(['agent_level'=>0,'default_period'=>0,'add_agent_time'=>0]);
    //                                 }
    
    //                             }
                               
    //                         }
                           
    //                 }                
    
    //         // }
    //     }
  
    //     //会员升级条件     //符合推广人数   就升级 如：经理
    //     public function popUpdateCondition($levelNum)
    //     {
    //       $ind_goods_sum=Db::name('agent_level')->where('level','=',$levelNum)->value("ind_goods_sum");
    //       return $ind_goods_sum;
    //     }
  
      //每日定时释放推广名额
      public function day_release_handle()
      {
          $dayList=Db::name('pop_period')->where('day_release','=',1)->select();
          $time=time();
          foreach($dayList as $dk=>$dv){
              Db::name('users')->where('user_id','=',$dv['user_id'])->setInc('default_period');
              Db::name('pop_period')->where('user_id','=',$dv['user_id'])->where('period','=',$dv['period'])->update(['begin_time'=>$time,'day_release'=>0]);
          }
      }
  
      //每周定时释放推广名额
      public function week_release_handle()
      {
          $dayList=Db::name('pop_period')->where('week_release','=',1)->select();
          $time=time();
          foreach($dayList as $dk=>$dv){
              Db::name('users')->where('user_id','=',$dv['user_id'])->setInc('default_period');
              Db::name('pop_period')->where('user_id','=',$dv['user_id'])->where('period','=',$dv['period'])->update(['begin_time'=>$time,'week_release'=>0]);
          }
      }
  
    

       //每月定时发放极差奖领导奖  优化方法
       public function team_bonus(){
            $allUserPerformace=Db::name('users')->alias('u')->join('agent_performance ap','u.user_id=ap.user_id',LEFT)->field('u.leader_level,u.user_id,u.mobile,u.nickname,,u.distribut_money,ap.ind_per,ap.agent_per')->where('leader_level','<>','0')->select();
            $time=time();
            if($allUserPerformace){
                $accountLogModel=Db::name('account_log');
                foreach($allUserPerformace as $ak=>$av){
                    $one_agent_level=Db::name('agent_level')->where('level','=',$av['leader_level'])->find();
                    if($av['agent_per']>=$one_agent_level['describe']){
                        $bonus=$av['agent_per']*$one_agent_level['retio']/100;
                        $addDistribut=$av['distribut_money']+$bonus;
                        if($av['leader_level']==4){
                            $accountLogModel->insert(['user_id'=>$av['user_id'],'user_money'=>$addDistribut,'pay_points'=>0,'change_time'=>$time,'desc'=>'奖励豪车','type'=>6]);
                        }else{
                            $bonus=$av['agent_per']*$one_agent_level['ratio']/100;
                            $addDistribut=$av['distribut_money']+$bonus;
                            Db::name('users')->where('user_id','=',$av['user_id'])->update(['distribut_money'=>$addDistribut]);
                            $accountLogModel->insert(['user_id'=>$av['user_id'],'user_money'=>$addDistribut,'pay_points'=>0,'change_time'=>$time,'desc'=>'级差奖领导奖','type'=>5]);
                        }
                    }
                }
            }
       }


      

    public function index()
    {
        $user_id = $this->user_id;
        $agent_level = M('agent_level')->field('level,level_name')->select();
        // dump($agent_level);
        if($agent_level){
            foreach($agent_level as $v){
                $agnet_name[$v['level']] = $v['level_name'];
            }
            // dump($agnet_name);
            $this->assign('agnet_name', $agnet_name);
        }

        $MenuCfg = new MenuCfg();
        $menu_list = $MenuCfg->where('is_show', 1)->order('menu_id asc')->select();

        $app_config = Db::name('config')->where(['inc_type'=>'shop_info','name'=>['in','android_app_url,ios_app_url']])->field('name,value')->select();
        if($app_config){
            foreach($app_config as $v){
                if($v['value']){
                    $app[$v['name']] = $v['value'];
                }
            }
            if(isset($app)){
                $this->assign('app', $app);
            }
        }
        //用户余额
        $user_money = Db::name('users')->where(['user_id'=>$user_id])->field('user_money,user_id')->find();

        //推广人数
        $pop_periods = Db::name('pop_period')->where(['user_id'=>$user_id])->select();
        $person_num = 0;
        $poped_per_num = 0;
        foreach($pop_periods as $key => $veal){
            $person_num += $veal['person_num'];
            $poped_per_num += $veal['poped_per_num'];
        }
        //总佣金
        $distribut_money = Db::name('account_log')->where(['user_id'=>$user_id,'type'=>['in',[2,3,4,5]]])->sum(user_money);
        $comm2 = Db::name('commission_log')->where(['user_id' => $user_id])->order('id','desc')->sum('money');

        //今日佣金
        $comm = $this->today_commission();

        $is_vip = $this->user['end_time'] > time()?1:0;
        $this->user['today_comm'] = $comm;
        $this->assign('person_num', $person_num-$poped_per_num);
        $this->assign('user_money', $user_money);
        $this->assign('menu_list', $menu_list);
        $this->assign('distribut_money', $distribut_money+$comm2);
        $this->assign('comm', $comm);
        $this->assign('is_vip', $is_vip);
        $this->assign('mobile_validated', $this->user['mobile'] ? 0 : 1);
        
        return $this->fetch();
    }

    //获取今天佣金
    public function today_commission()
    {
        $user_id = $this->user_id;
        $where['to_user_id'] = $user_id;
        $where['type'] = ['in',[1,2,3]];
        $day_account_log = Db::name('account_log')->where(['user_id'=>$user_id,'type'=>['in',[2,3,4,5]]])->whereTime('change_time','today')->sum(user_money);
        // $comm = Db::name('distrbut_commission_log')->where($where)->order('log_id','desc')->whereTime('create_time','today')->sum('money');
        // $vip  = Db::name('vip_commission_log')->where(['to_user_id' =>$user_id ])->order('log_id','desc')->whereTime('create_time','today')->sum('money');
        //邀请奖励
        $comm2 = Db::name('commission_log')->where(['user_id' => $user_id])->order('id','desc')->whereTime('addtime','today')->sum('money');

        $money = $comm2 + $day_account_log;
        return $money;
    }

    /**
     * 生成会员卡信息
     * @param $user_id
     * @return int 1 表示成功 ； 0 表示失败
     */
    public static function info($user_id){
        $company_name = '健康商城';
        $image = '';//图片路径
        $card_no = 'JKSC'.time().$user_id;
        $photoshop = new Photoshop();
        $path = $photoshop->getPosterPhoto($user_id,$card_no);
        $data = [
            'user_id'=>$user_id,
            'card_no'=>$card_no,
            'card_image'=>$path,
            'use_count'=>0,
            'total_count'=>0,
            'not_use_count'=>0,
            'create_time'=>time(),
        ];

        $result = Db::name('user_card')->insert($data);
        if($result){
            return 1;
        }
        return 0;
    }

    

    //上传凭证
    /**
     * 上传支付凭证
     * @param Request $request
     * @return mixed
     */
    public function uploadfile(Request $request)
    {
        $pay_way = Db::name('config')->where('inc_type','pay_setting')->select();
        $pay_way[0]['name'] = '支付宝';
        $pay_way[1]['name'] = '微信';
        $pay_way[0]['img'] = $pay_way[2]['value'];
        $pay_way[1]['img'] = $pay_way[3]['value'];

        $package = null;
//        $package = Db::name('package')->order('pack_time asc')->select();
        $this->assign('pay',$pay_way);
        $this->assign('package',$package);//dump($package);die;
        return $this->fetch();
    }

    //待收益
    public function wait_earnings()
    {
        $user_id = $this->user_id;
        $where['user_id'] = $user_id;

        $Earnings = new Earnings;
        $earnings_info = $Earnings->where($where)->find();
        $obj = $earnings_info->obj;
        $obj = json_decode($obj,true);

        $user = M('users')->where('user_id',$user_id)->field('user_id,first_leader,distribut_level,is_distribut,bonus_products_id,is_lock')->find();
        //冻结账号没有待收益
        if ($user['is_lock'] == 1) {
            $lists = array();
        } else {
            $order_id = $obj ? array_column($obj,'o') : array();
            $user_comm = $this->self_wait($user,$order_id);//自己的待收益
            $lower_comm = $this->lower_wait($user,$order_id);//下级的待收益

            $total_money = $user_comm['total_money'] + $lower_comm['total_money'];
            $list = array();

            if ($user_comm['data'] && $lower_comm['data']) {
                $list = array_merge($user_comm['data'],$lower_comm['data']);
            } elseif ($user_comm['data']) {
                $list = $user_comm['data'];
            } elseif ($lower_comm['data']) {
                $list = $lower_comm['data'];
            }

            //是否有待收益
            if ($list) {
                foreach ($list as $key => $value) {
                    $result[] = ['o'=>$value['order_id'],'g'=>$value['goods_id'],'m'=>$value['money']];
                    $money[] = $value['money'];
                }

                $total_money = ($total_money = array_sum($money)) ? $total_money : array_sum($money);//重新统计
                if ($order_id) {
                    $result = array_merge($obj,$result);
                    $total_money += $earnings_info->money;
                }

                $obj = json_encode($result);
                $Earnings = $Earnings->where($where)->find() ?: $Earnings;

                $Earnings->user_id = $user_id;
                $Earnings->money = $total_money;
                $Earnings->obj = $obj;

                $Earnings->save();

                $earnings_info = $Earnings->where($where)->find();
                $obj = $earnings_info->obj;
                $obj = json_decode($obj,true);
            }
        }

        $lists = array();

        if ($obj) {
            $goods_ids = array_column($obj,'g');
            $order_ids = array_column($obj,'o');
            $goods = M('order_goods')->whereIn('goods_id',$goods_ids)->whereIn('order_id',$order_ids)->field('order_id,goods_id,goods_num,goods_name,goods_price')->select();
            $images = M('goods')->whereIn('goods_id',$goods_ids)->column('goods_id,original_img');

            foreach ($goods as $k => $v1) {
                foreach ($obj as $k2 => $v2) {
                    if ($v2['g'] == $v1['goods_id']) {
                        $money = $v2['m'];
                    }
                }
                $lists[] = ['order_id'=>$v1['order_id'],'goods_id'=>$v1['goods_id'],'goods_num'=>$v1['goods_num'],'money'=>$money,'images'=>$images[$v1['goods_id']],'goods_name'=>$v1['goods_name']];
            }
        }

        $this->assign('list',$lists);
        return $this->fetch();
    }

    //自己购买商品待收益
    public function self_wait($user,$old_order_id)
    {
        $user_id = $user['user_id'];
        $total_money = 0;
        $num = 0;
        $result = array();
        $order_ids = M('order_divide')->where('user_id',$user_id)->column('order_id');

        $all_order_goods = M('order_goods')->whereIn('order_id',function($query) use ($user_id,$order_ids,$old_order_id){
            $query->name('order')->where('user_id',$user_id)->where('order_id','not in',$order_ids)->where('order_id','not in',$old_order_id)->field('order_id');
        })->where('is_send','<>',3)->column('rec_id,goods_id');

        $repeat_ids = $this->repeat_buy($user['distribut_level'],$all_order_goods); //重复购买商品id
        $all_ids = $repeat_ids['all_ids'];
        $goods_ids = $repeat_ids['goods_ids'];

        $order_goods = M('order_goods')->whereIn('rec_id',$goods_ids)->column('goods_id,order_id,goods_name,goods_num,prize_ratio,is_team_prize');

        $comm = self::get_comm_setting(true,$all_ids); //获取返佣设置

        foreach ($comm as $k3 => $v3) {
            $money = 0;
            $preferential = $comm[$k3]['preferential'];
            if (!$preferential) {
                continue;
            }
            $money = $preferential[$user['distribut_level']];
            $total_money += $money;

            $result[$num]['goods_id'] = $order_goods[$k3]['goods_id'];
            $result[$num]['order_id'] = $order_goods[$k3]['order_id'];
            $result[$num]['goods_name'] = $order_goods[$k3]['goods_name'];
            $result[$num]['goods_num'] = $order_goods[$k3]['goods_num'];
            $result[$num]['money'] = $money;
            $num ++;
        }

        $list = array('total_money'=>$total_money,'data'=>$result);

        return $list;
    }

    //获取返佣设置
    public static function get_comm_setting($is_repeat,$goods_id)
    {
        $num = 0;
        $result = array();
        if ($goods_id) {
            $comm_ids = M('goods')->whereIn('goods_id',$goods_id)->column('goods_id,goods_prize');
            foreach ($comm_ids as $k => $v) {
                if (!$v) {
                    unset($comm_ids[$k]);
                    continue;
                }
                $ids = json_decode($v,true);

                if($is_repeat){
                    $fields = 'level,preferential,self_buying as basic,self_poor_prize as poor_prize,self_reword as first_layer,self_reword2 as second_layer';
                } else {
                    $fields = 'level,reward as basic,poor_prize,same_reword as first_layer,same_reword2 as second_layer';
                }

                $comm = M('goods_commission')->where('id','in',$ids)->column($fields);
                if (!$comm) {
                    continue;
                }
                $result[$k]['goods_id'] = 0;
                $result[$k]['basic'] = array();
                $result[$k]['poor_prize'] = array();
                $result[$k]['first_layer'] = array();
                $result[$k]['second_layer'] = array();
                $result[$k]['preferential'] = array();
                $result[$k]['goods_id'] = $k;

                foreach($comm as $key => $value){
                    $result[$k]['basic'][$key] = $value['basic'];
                    $result[$k]['poor_prize'][$key] = $value['poor_prize'];
                    $result[$k]['first_layer'][$key] = $value['first_layer'];
                    $result[$k]['second_layer'][$key] = $value['second_layer'];
                    $result[$k]['preferential'][$key] = $is_repeat ? $value['preferential'] : 0;
                }

                unset($comm_ids[$k]);
            }
        }

        return $result;
    }

    //重复购买商品id
    public function repeat_buy($user_level,$all_order_goods)
    {
        // $order_goods_count = array_count_values($all_order_goods); //统计键值
        $result = array('goods_ids'=>array(),'all_ids'=>array(),'first'=>array());

        foreach ($all_order_goods as $k1 => $v1) {
            if ($user_level === false) {
                $user_level = Db::name('users')->alias('users')
                    ->join('order order','order.user_id = users.user_id')
                    ->join('order_goods goods','order.order_id = goods.order_id')
                    ->where('goods.rec_id',$k1)
                    ->value('distribut_level');
            }

            if ($user_level > 0) {
                $result['goods_ids'][] = $k1;   //重复购买商品id
                $result['all_ids'][] = $v1;
                // foreach ($all_order_goods as $k2 => $v2) {
                //     if ($v2 == $k1) {
                //         $result['all_ids'][] = $k2;
                //         unset($all_order_goods[$k2]);
                //     }
                // }
            } else {
                $result['first'][] = $k1;
            }
            unset($order_goods_count[$k1]);
        }
        return $result;
    }

    //下级购买待收益
    public function lower_wait($user,$old_order_id)
    {
        $user_id = $user['user_id'];
        //$lower_ids = $this->lower_id($user_id);  //获取下级id列表
        $lower_ids = get_all_lower($user_id);  //获取下级id列表

        //获取已返佣的订单
        $order_id = Db::name('order')->alias('order')
            ->join('order_divide divide','order.order_id = divide.order_id')
            ->where('divide.user_id','in',$lower_ids)
            ->column('order.order_id');

        $wait_goods_ids = Db::name('order')->where('order_id','not in',$order_id)->where('order_id','not in',$old_order_id)->where('user_id','in',$lower_ids)->where('pay_status',1)->field('order_id,user_id')->order('add_time','desc')->limit(5)->select();

        if (!$wait_goods_ids) {
            return false;
        }

        $user_ids = array_column($wait_goods_ids,'user_id');
        $order_ids = array_column($wait_goods_ids,'order_id');

        foreach ($user_ids as $key => $value) {
            $leader_list[$value] = get_parents_ids($value);//获取上级id
        }

        $goods_ids = M('order_goods')->whereIn('order_id',$order_ids)->where('is_send','<>',3)->column('rec_id,goods_id');
        //$leader_list = M('users')->whereIn('user_id',$lower_ids)->column('user_id,parents,first_leader,distribut_level,is_distribut,bonus_products_id,is_lock');
        //$leader_list[$user_id] = $user;
        //ksort($leader_list);
        //$order_divide = M('order_divide')->where('user_id','in',$lower_ids)->column('order_id');  //获取已返佣的订单
        // //获取还没返佣的订单

        //$goods_ids = M('order_goods')->whereIn('order_id',function($query) use ($order_divide,$lower_ids,$old_order_id) {
        //    $query->name('order')->where('order_id','not in',$order_divide)->where('user_id','in',$lower_ids)->where('order_id','not in',$old_order_id)->field('order_id');
        //})->where('is_send','<>',3)->column('rec_id,goods_id');

        $repeat_ids = $this->repeat_buy(false,$goods_ids); //是否重复购买
        $second_ids = $repeat_ids['goods_ids'];
        $first_ids = $repeat_ids['first'];

        //获取商品返佣设置
        $comm1 = self::get_comm_setting(false,$first_ids);
        $comm2 = self::get_comm_setting(true,$second_ids);
        $rec_ids = array_flip($goods_ids);//交换数组的键和值

        //计算佣金
        $result = $this->calculate_commission($comm1,$rec_ids,$leader_list,['total_money'=>0,'data'=>[]],$user);
        $result2 = $this->calculate_commission($comm2,$rec_ids,$leader_list,$result,$user);

        return $result2;
    }

    //计算佣金
    public function calculate_commission($comm,$rec_ids,$leader_list,$result = '',$user)
    {
        $total_money = $result['total_money'];
        $list = $result['data'];
        //是否有返佣设置
        if ($comm) {
            $user_id = $user['user_id'];
            $user_level = intval($user['distribut_level']);
            $bonus_products_id = $user['bonus_products_id'];

            $goods_ids = array_column($comm, 'goods_id');
            $goods = M('order_goods')->whereIn('rec_id',$rec_ids)->where('goods_id','in',$goods_ids)->where('is_send','<>',3)->field('order_id,goods_id,goods_name,goods_num,goods_price,is_team_prize,prize_ratio')->select();
            if (!$goods) {
                return $result;
            }
            $order_ids = array_column($goods, 'order_id');
            $order = M('order')->whereIn('order_id',$order_ids)->column('order_id,user_id');
            //是否有团队奖励
            if ($bonus_products_id > 0) {
                $prize_ratio = M('goods')->where('goods_id',$bonus_products_id)->value('prize_ratio');
            }
            foreach ($goods as $k1 => $v1) {
                $order = M('order')->where('order_id',$v1['order_id'])->field('order_id,user_id')->find();
                //$parents = $leader_list[$order['user_id']]['parents'];
                //$parents_id = $parents ? explode(',',$parents) : 0;
                $parent_id = $leader_list[$order[$v1['order_id']]]['first_leader'];
                //$is_exist = in_array($parent_id,$parents_id);
                //if (!$is_exist) {
                //    array_unshift($parents_id,$leader_list[$parent_id]);
                //}
                //krsort($parents_id);
                $parents_id = array_filter($parents_id);  //去除0

                $num = count($list);
                if ($count == count($list,1)) {
                    $num = $num ? 1 : 0;
                }
                //团队奖励
                if ($prize_ratio > 0) {
                    $team_money = $v1['goods_price'] * $v1['goods_num'] * ($prize_ratio / 100);
                    $money = round($money,2);
                    if ($money) {
                        $total_money += $team_money;

                        $list[$num]['goods_id'] = $v1['goods_id'];
                        $list[$num]['order_id'] = $v1['order_id'];
                        $list[$num]['goods_name'] = $v1['goods_name'];
                        $list[$num]['goods_num'] = $v1['goods_num'];
                        $list[$num]['money'] = $team_money;
                        $num ++;
                    }
                }

                if (!$parents_id) {
                    continue;
                }

                $basic_reward = $comm[$v1['goods_id']]['basic'];  //直推奖励
                $poor_prize = $comm[$v1['goods_id']]['poor_prize'];//极差奖励
                $first_layer = $comm[$v1['goods_id']]['first_layer'];//同级一层奖励
                $second_layer = $comm[$v1['goods_id']]['second_layer'];//同级二层奖励

                $is_me = false;
                $layer = 0;
                $level = 0;
                $is_prize = false;

                foreach ($parents_id as $k2 => $v2) {
                    $money = 0;
                    if (!isset($leader_list[$v2])) {
                        continue;
                    }
                    if ($user_level < $leader_list[$v2]['distribut_level']) {
                        break;
                    }
                    if ($is_me) {
                        break;
                    }

                    if ($user_id == $v2) {
                        $is_me = true;
                    }
                    //账号冻结了没有奖励
                    if ($leader_list[$v2]['is_lock'] == 1) {
                        continue;
                    }
                    //不是分销商不奖励
                    if ($leader_list[$v2]['is_distribut'] != 1) {
                        continue;
                    }

                    //平级奖
                    if ($level == $leader_list[$v2]['distribut_level']) {
                        $level = $leader_list[$v2]['distribut_level'];
                        $layer ++;
                        //超过设定层数没有奖励
                        if ($layer > 2) {
                            continue;
                        }
                        if (!$is_prize) {
                            $money = $basic_reward ? floatval($basic_reward[$leader_list[$v2]['distribut_level']]) : 0;
                            $is_prize = true;
                        }
                        //不是自己没有奖金
                        if ($v2 != $user_id) {
                            $money = 0;
                            continue;
                        }
                        //同级层数
                        switch($layer){
                            case 1:
                                $money += floatval($first_layer[$leader_list[$v2]['distribut_level']] * $v1['goods_num']);
                                break;
                            case 2:
                                $money += floatval($second_layer[$leader_list[$v2]['distribut_level']] * $v1['goods_num']);
                                break;
                            default:
                                break;
                        }
                    }

                    //极差奖
                    if ($level < $leader_list[$v2]['distribut_level']) {
                        $layer = 0;
                        //基本奖励已奖励的不再奖励
                        if (!$is_prize && ($v2 == $user_id)) {
                            $money = $basic_reward ? floatval($basic_reward[$leader_list[$v2]['distribut_level']]) : 0;
                            $is_prize = true;
                        }

                        reset($poor_prize);  //重置数组指针

                        //计算极差奖金
                        while(list($pk,$pv) = each($poor_prize)){
                            if ($level >= $pk) {
                                continue;
                            }
                            if (($pk <= $leader_list[$pv]['distribut_level']) && ($v2 == $user_id)) {
                                $pk = $pv ? floatval($pv) : 0;
                                $money += $pv * $v1['goods_num'];

                                continue;
                            }
                            break;
                        }


                        $level = $leader_list[$v2]['distribut_level'];
                    }
                    $money = floatval($money);
                    if (!$money) {
                        continue;
                    }
                    $total_money += $money;

                    $list[$num]['goods_id'] = $v1['goods_id'];
                    $list[$num]['order_id'] = $v1['order_id'];
                    $list[$num]['goods_name'] = $v1['goods_name'];
                    $list[$num]['goods_num'] = $v1['goods_num'];
                    $list[$num]['money'] = $money;
                    $num ++;
                }
            }
        }

        $result['total_money'] = $total_money;
        $result['data'] = $list;

        return $result;
    }

    //  /**
    //  * 我的佣金
    //  * @author Rock
    //  */
    // public function mycommission(){
    //     $user_id = $this->user_id;
    //     // echo $user_id;exit;
    //     # 登录签到佣金
    //     $data['sign_money'] = Db::name('commission_log')->where(['user_id'=>$user_id,'identification'=>1])->sum('money');
    //     $data['invite_money'] = Db::name('commission_log')->where(['user_id'=>$user_id,'identification'=>2])->sum('money');
    //     $data['distribution_rebate'] = Db::name('order_divide')->where(['user_id'=>$user_id])->sum('money');


    //     $this->assign('data',$data);
    //     return $this->fetch();
    // }

    // /**
    //  * 佣金明细
    //  * @param int t 明细类型[1=>登录签到，2=>邀请注册，3=>分销返利]
    //  *
    //  */
    // public function commission_log(){
    //     $t = intval(input('get.t'));
    //     if(!$t){
    //         $this->error('参数错误！');
    //     }
    //     $user_id = $this->user_id;

    //     switch($t){
    //         case '1':
    //             # 签到登录
    //             $log = Db::query("select `money`,`date`,`num` from `tp_commission_log` where `user_id` = '$user_id' and `identification` = 1 order by `date` desc limit 50");
    //             // dump($log);exit;
    //             break;
    //         case '2':
    //             # 邀请注册
    //             $log = Db::query("select a.`add_user_id`,b.`mobile`,b.`nickname`,`money`,`addtime` from `tp_commission_log` as a left join `tp_users` as b on a.`add_user_id` = b.`user_id` where a.`user_id` = '$user_id' and a.`identification` = 2 order by a.`addtime` desc limit 50");
    //             break;
    //         case '3':
    //             #分销返利
    //             $log = DB::query("select `add_time`, `money` from `tp_order_divide` where `user_id` = '$user_id' order by `add_time` desc limit 50");
    //             break;

    //         default:
    //             return $this->error('无效的参数，请重试！');
    //     }


    //     $this->assign('log',$log);
    //     $this->assign('t',$t);
    //     return $this->fetch();
    // }

    /**
     * 所有下级id
     */
    public function lower_id($user_id)
    {
        $d_info = Db::query("select `user_id`, `first_leader`,`parents` from `tp_users` where 'first_leader' = $user_id or parents like '%,$user_id,%'");
        $ids = array();
        if($d_info){
            $ids = array_column($d_info,'user_id');
        }

        return $ids;
    }

    /**
     * 我的分销
     */
    public function team_list()
    {
        $user_id = $this->user_id;

        $leader_id = M('users')->where('user_id',$user_id)->field('nickname,first_leader,user_id')->find();
        $leader = M('users')->where(['user_id'=>$leader_id['first_leader']])->field('nickname,user_id')->find();

        $team_count = Db::query("SELECT count(*) as count FROM tp_parents_cache where find_in_set('$user_id',`parents`)");
        //个人业绩  团队业绩
        $Ad  = M('agent_performance');

        $performance = $Ad->where(['user_id' => $user_id])->find();
        $performance = $performance['ind_per']+$performance['agent_per'];
        if(empty($performance)){
            $performance = 0;
        }
        $performance = bcadd($performance,'0.00',2);
        $bonus = Db::name('account_log')->where(['user_id'=>$user_id,'type'=>['in','2,3,4,5']])->sum('user_money'); 
        $bonus = bcadd($bonus,'0.00',2);

        $this->assign('performance',$performance);
        $this->assign('bonus',$bonus);
        $this->assign('team_count',$team_count[0]['count'] ? $team_count[0]['count'] : 0);
        $this->assign('leader',$leader);
        $this->assign('user_id',$user_id);
        $this->assign('leader_id',$leader_id['first_leader']);

        return $this->fetch();
    }

    /**
     * 明细记录
     */
    public function mixi(){
        $user_id = $this->user_id;
        $account_log = M('account_log')->where(['user_id'=>$user_id,'type'=>2])->select();
        $this->assign('account_log',$account_log);
        return $this->fetch();
    }


    /**
     * 团队列表
     */
    public function group(){
        $user_id = $this->user_id;
        $user = M('users')->where(['user_id'=>$user_id])->field('user_id,nickname,mobile,distribut_level,distribut_money,head_pic')->find();
        $get_all_lower = get_all_lower($user_id);
        foreach($get_all_lower as $key => $vale){
            // dump($vale);
            $get_all_lower[$key] = M('users')->where(['user_id'=>$vale])->field('user_id,nickname,mobile')->find();
            // dump($user);
        }
        $this->assign('nickname',$user['nickname']);
        $this->assign('user_id',$user_id);
        $this->assign('get_all_lower',$get_all_lower);

        return $this->fetch();
    }

    /**
     * 团队订单
     */
    public function order(){
        $user_id = $_GET['id'];
        $orders = Db::name('order')->where(['user_id'=>$user_id])->field('order_sn,consignee,add_time')->select();
        $this->assign('orders',$orders);
        // dump($orders);die;
        return $this->fetch();
    }

    /**
     * 推广名额
     */
    public function tuiguang(){
        $user_id = $this->user_id;
        $pop_period = Db::name('pop_period')->where(['user_id'=>$user_id])->select();
        $users_period = [];
        foreach($pop_period as $key => $veal)
        {
            $pop_period[$key]['nums'] = $veal['person_num'] - $veal['poped_per_num'];
            $users_period[] = Db::name('account_log')->where(['user_id'=>$user_id,'type'=>2,'change_time'=>['>=',$pop_period['begin_time']]])->select();
            // $pop_period[$key]['users_period'] = $users_period;
        }
        // dump($users_period);die;
        $this->assign('pop_period',$pop_period);
        $this->assign('users_period',$users_period);
        return $this->fetch();
    }


    // 转账记录
    public function transfer(){
        return $this->fetch();
    }

    // 余额转账
    public function balance(){

        return $this->fetch();
    }

    // 余额转账详情
    public function remainingsum(){
        $user_id=input('user_id');
        $my_user_id=$this->user_id;
        $endUser=Db::name('users')->field('user_id,head_pic,mobile,nickname')->where('user_id','=',$user_id)->find();
        $myInfo=Db::name('users')->where("user_id",'=',$my_user_id)->field('user_money')->find();
        $this->assign([
            'endUser'=>$endUser,
            'myInfo'=>$myInfo,
        ]);
        return $this->fetch();
    }

    //处理转账
    public function exchange_money_handle()
    {
        $time=time();
        $data=input('post.');
        if(!$data['end_user_id']){
            $this->ajaxReturn(['status' => -1, 'msg' => '转入人不能为空']);
        }
        if(!$data['exchange_money']){
            $this->ajaxReturn(['status' => -1, 'msg' => '转出金额不能为空']);
        }
        $data1['user_id']=$this->user_id;
        $data1['out_user_id']=$this->user_id;
        $data1['in_user_id']=$data['end_user_id'];
        $data1['exchange_money']='-'.$data['exchange_money'];
        $data1['description']=$data['description'];
        $data1['create_time']=$time;
        $data1['type']=2;

        $data2['user_id']=$data['end_user_id'];
        $data2['out_user_id']=$this->user_id;
        $data2['in_user_id']=$data['end_user_id'];
        $data2['exchange_money']=$data['exchange_money'];
        $data2['description']=$data['description'];
        $data2['create_time']=$time;
        $data2['type']=1;

        Db::startTrans();
        try{
            $myUser=Db::name('users')->where('user_id','=',$data1['user_id'])->find();
            $minusMoney=$myUser['user_money']-$data['exchange_money'];
            Db::name('users')->where('user_id','=',$data1['user_id'])->update(['user_money'=>$minusMoney]);

            $otherUser=Db::name('users')->where('user_id','=',$data['end_user_id'])->find();
            $addMoney=$otherUser['user_money']+$data['exchange_money'];
            Db::name('users')->where('user_id','=',$data['end_user_id'])->update(['user_money'=>$addMoney]);

            $res1 = Db::name('exchange_money')->insert($data1);
            $res2 = Db::name('exchange_money')->insert($data2);
            Db::commit();    
            if($res1&&$res2){
                $this->ajaxReturn(['status' => 1, 'msg' => '转账成功']);
            }
        } catch (\Exception $e) {
            Db::rollback();
            $this->ajaxReturn(['status' =>-1, 'msg' => '操作失败']);
        }
    

    }

    /** 模糊查询
     * @param: array  $search_data    搜索关键字
     */
    public function ajax_search()
    {
        $res['code'] = 0;
        $search_data = I('post.key');
        $conn = '';
        if (!empty($search_data)) {
            $key['mobile'] = array('like', '%' . $search_data . '%');
            $conn = M('users')->field('user_id,nickname,head_pic')->where($key)->select();//查询数据
        }
        $conn[0]['head_pic'] = SITE_URL.$conn[0]['head_pic'];
        if ($conn) {
            $res['code'] = 1;
            $res['data'] = $conn;
            $res['msg'] = '成功';
        } else {
            $res['msg'] = '失败';
        }
        echo json_encode($res);
    }


    /**
     * 我的VIP
     */
    public function team_vip_list()
    {
        $user_id = $this->user_id;
         
        $leader_id = M('users')->where('user_id',$user_id)->value('first_leader');
        $leader = M('users')->where('user_id',$leader_id)->field('nickname,mobile,head_pic')->find();
        $first = M('users')->where(['first_leader' =>$user_id,'end_time' => ['neq' ,0]])->column('user_id');
        $second = $first ? M('users')->where(['first_leader'=>['in',$first],'end_time' => ['neq',0]])->column('user_id') : [];
        $third = $second ? M('users')->where(['first_leader'=>['in',$second],'end_time' => ['neq',0]])->column('user_id') : [];

        $first_count = count($first);
        $second_count = count($second);
        $third_count = count($third);

        $team_count = Db::query("SELECT count(*) as count FROM tp_parents_vip_cache where find_in_set('$user_id',`parents`)");
        //$team_count = Db::query("SELECT count(*) as count FROM tp_users where find_in_set('$user_id',`parents`)");

        $this->assign('first_count',$first_count);
        $this->assign('second_count',$second_count);
        $this->assign('third_count',$third_count);
        $this->assign('team_count',$team_count[0]['count'] ? $team_count[0]['count'] : 0);
        $this->assign('leader',$leader);
        // $this->assign('count',$count);
        // $this->assign('team',$team_list);
        return $this->fetch();
    }
    /**
     * 三级分销
     */
    public function three_level()
    {
        $user_id = $this->user_id;
        $leader_ids = I('ids',[]);
        $type = I('type',1);
        $page = I('page',1);
       
        $where = array();
       
        switch($type){
            //一级
            case 1:
                $where['first_leader'] = $user_id;
                break;
            //二级
            case 2:
                $first = M('users')->where('first_leader',$user_id)->column('user_id');
                $where['first_leader'] = $first ? ['in',$first] : array();
                break;
            //三级
            case 3:
                $first = M('users')->where('first_leader',$user_id)->column('user_id');
                $second = $first ? M('users')->where(['first_leader'=>['in',$first]])->column('user_id') : [];
                $where['first_leader'] = $second ? ['in',$second] : array();
                break;
            default: break;
        }

        $team_list = array();
        if ($where['first_leader']) {
            //获取对应下级id的数据
            $team_list = Db::name('users')->where($where)->field('user_id,nickname,mobile,distribut_level,distribut_money,head_pic')->page($page,15)->select();
        }

        $level = M('agent_level')->column('level,level_name');

        foreach($team_list as $k1 => $v1){
            $team_list[$k1]['level_name'] = $level[$v1['distribut_level']];
        }

        return json($team_list);
    }


    /**
     * 三级分销VIP
     */
    public function three_vip_level()
    {
        $user_id = $this->user_id;
        $leader_ids = I('ids',[]);
        $type = I('type',1);
        $page = I('page',1);
        $where = array();
        $where['end_time']     = ['neq',0];
        switch($type){
            //一级
            case 1:
                $where['first_leader'] = $user_id;
                break;
            //二级
            case 2:
                $first = M('users')->where(['first_leader' => $user_id , 'end_time' => ['neq' ,0]])->column('user_id');
                $where['first_leader'] = $first ? ['in',$first] : array();
                break;
            //三级
            case 3:
                $first = M('users')->where(['first_leader' => $user_id , 'end_time' => ['neq' ,0]])->column('user_id');
                $second = $first ? M('users')->where(['first_leader'=>['in',$first],'end_time' => ['neq',0]])->column('user_id') : [];
                $where['first_leader'] = $second ? ['in',$second] : array();
                break;
            default: break;
        }

        $team_list = array();
        if ($where['first_leader']) {
            //获取对应下级id的数据
            $team_list = Db::name('users')->where($where)->field('user_id,nickname,mobile,distribut_level,distribut_money_vip,head_pic')->page($page,15)->select();
        }

        $level = M('agent_level')->column('level,level_name');

        foreach($team_list as $k1 => $v1){
            $team_list[$k1]['level_name'] = $level[$v1['distribut_level']];
        }

        return json($team_list);
    }

    /**
     * 下级购买记录
     */
    public function purchase_log()
    {
        $id = input('id/d');

        $log = Db::name('order')->alias('order')
            ->distinct(true)
            ->join('order_goods goods','order.order_id = goods.order_id')
            ->where('order.pay_status',1)
            ->where('order.user_id',$id)
            ->order('order.pay_time','desc')
            ->field('goods.rec_id,order.pay_time,goods.goods_price,goods.goods_num')
            ->limit(50)
            ->select();

        $user_info = Db::name('users')->where('user_id',$id)->field('nickname,mobile,head_pic')->find();

        $this->assign('info',$user_info);
        $this->assign('log',$log);
        return $this->fetch();
    }

     /**
     * 下级购买记录
     */
    public function vip_purchase_log()
    {
        $id = input('id/d');

        $log = Db::name('buy_vip')
            ->distinct(true)
            ->where(['user_id' => $id ,'pay_status' => 1])
            ->order('order_id desc')
            ->field('account,ctime')
            ->limit(50)
            ->select();

        $user_info = Db::name('users')->where('user_id',$id)->field('nickname,mobile,head_pic')->find();

        $this->assign('info',$user_info);
        $this->assign('log',$log);
        return $this->fetch();
    }

    /**
     *邀请用户
     */
    public function invite_user(){
        $user_id = $this->user_id;
        $sql = "select a.*,b.head_pic,b.nickname,b.mobile from `tp_commission_log` as a left join `tp_users` as b on a.add_user_id = b.user_id where a.identification = 2 and a.user_id = '$user_id' order by addtime desc limit 50";
        $log = Db::query($sql);
        $this->assign('log',$log);
        return $this->fetch();
    }

    /**
     *登录签到
     */
    public function sign_list(){
        $user_id = $this->user_id;
        $log = Db::query("select `money`,`date`,`num` from `tp_commission_log` where `user_id` = '$user_id' and `identification` = 1 order by `date` desc limit 50");
        $this->assign('log',$log);
        return $this->fetch();
    }

    /**
     *分销返利
     */
    public function distribution_rebate(){
        $user_id = $this->user_id;
        $log = DB::query("select `add_time`, `money` from `tp_order_divide` where `user_id` = '$user_id' order by `add_time` desc limit 50");
        $this->assign('log',$log);
        return $this->fetch();
    }


    public function sharePoster(){

        $user_id = $this->user_id;
        $share_error = 0;

        $filename = $user_id.'-qrcode.png';
        $save_dir = ROOT_PATH.'public/shareposter/';
        $my_poster = $save_dir.$user_id.'-share.png';
        $my_poster_src = '/public/shareposter/'.$user_id.'-share.png';
        if( !file_exists($my_poster) ){
            $shareposter = Db::name('users')->where('user_id',$user_id)->value('shareposter');
            if($shareposter != 1){
                $imgUrl = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . '/index.php?dfc5b='.$this->user_id;
                vendor('phpqrcode.phpqrcode');
                \QRcode::png($imgUrl, $save_dir.$filename, QR_ECLEVEL_M);
                $image_path =  ROOT_PATH.'public/shareposter/load/qr_backgroup.png';
                if(!file_exists($image_path)){
                    $share_error = 1;
                }
                # 分享海报
                if(!file_exists($my_poster) && !$share_error){
                    # 海报配置
                    $conf = Db::name('config')->where(['inc_type' => 'shareposter', 'name' => 'shareposter'])->find();
                    if($conf){
                        $config = json_decode($conf['value'],true);

                        $image_w = $config['w'] ? $config['w'] : 75;
                        $image_h = $config['h'] ? $config['h'] : 75;
                        $image_x = $config['x'] ? $config['x'] : 0;
                        $image_y = $config['y'] ? $config['y'] : 0;

                        # 根据设置的尺寸，生成缓存二维码
                        $qr_image = \think\Image::open($save_dir.$filename);
                        $qrcode_temp_path = $save_dir.$user_id.'-poster.png';
                        $qr_image->thumb($image_w,$image_h,\think\Image::THUMB_SOUTHEAST)->save($qrcode_temp_path);
                        
                        if($image_x > 0 || $image_y > 0){
                            $water = [$image_x, $image_y];
                        }else{
                            $water = 5;
                        }
                        
                        # 图片合成
                        $image = \think\Image::open($image_path);
                        $image->water($qrcode_temp_path, $water)->save($my_poster);
                        @unlink($qrcode_temp_path);
                        @unlink($save_dir.$filename);

                    }else{
                        $share_error = 1;
                    }


                }
            }
        }
        
        $this->assign('my_poster_src', $my_poster_src);
        return $this->fetch('sharePoster');
    }

    /**
     * 我的分享
     * @author Rock
     * @date 2019/03/23
     */
    public function sharePoster2(){

        $user_id = $this->user_id;
        $share_error = 0;
        $this->Auto_Refresh_Access_Token();
        $filename = 'qrcode.png';
        $save_dir = ROOT_PATH.'public/shareposter/user/'.$user_id.'/';
        $my_poster = $save_dir.'poster.png';
        $my_poster_src = '/public/shareposter/user/'.$user_id.'/poster.png';

        $shareposter = Db::name('users')->field('shareposter')->where('user_id',$user_id)->find();
        $shareposter = $shareposter['shareposter'];

        if($shareposter){
            $shareposter = json_decode($shareposter,true);
            $ticket = $shareposter['ticket'];
            $expire_seconds = $shareposter['expire_seconds'];
            if($expire_seconds < time()){
                Db::execute("update `tp_users` set `shareposter` = '' where `user_id` = '$user_id'");

                # 删除已存在的二维码文件
                unlink($save_dir.$filename);
                $this->redirect('sharePoster');
            }
        }else{
            $conf = Db::name('wx_user')->field('web_expires,web_access_token')->where('wait_access',1)->find();

            $token = $conf['web_access_token'];
            $param = [
                'expire_seconds' => 2592000,
                'action_name' => 'QR_STR_SCENE',
                'action_info' => [
                    'scene' => [
                        'scene_str' => 'sharePoster',
                    ],
                ],
            ];

            $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=$token";
            $res = httpRequest($url,'POST',json_encode($param));
            $res = json_decode($res,true);

            if($res['ticket']){
                $ticket = $res['ticket'];
                $expire_seconds = time() + $res['expire_seconds'] - 200;
                $update = json_encode(['ticket'=>$ticket,'expire_seconds'=>$expire_seconds]);
                Db::execute("update `tp_users` set `shareposter` = '$update' where `user_id` = '$user_id'");
            }else{
                $share_error = 1;
            }
        }
        $imgUrl = "https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".UrlEncode($ticket);


        # 临时二维码
        if(!file_exists($save_dir.$filename)){
            $this->getImage($imgUrl,$save_dir,$filename);
        }
        $image_path =  ROOT_PATH.'public/shareposter/load/qr_backgroup.png';
        if(!file_exists($image_path)){
            $share_error = 1;
        }

        # 分享海报
        if(!file_exists($my_poster) && !$share_error){
            # 海报配置
            $conf = Db::name('config')->where(['inc_type' => 'shareposter', 'name' => 'shareposter'])->find();
            if($conf){
                $config = json_decode($conf['value'],true);

                $image_w = $config['w'] ? $config['w'] : 75;
                $image_h = $config['h'] ? $config['h'] : 75;
                $image_x = $config['x'] ? $config['x'] : 0;
                $image_y = $config['y'] ? $config['y'] : 0;

                # 根据设置的尺寸，生成缓存二维码
                $qr_image = \think\Image::open($save_dir.$filename);
                $qrcode_temp_path = $save_dir.'qrcode_temp.png';
                $qr_image->thumb($image_w,$image_h,\think\Image::THUMB_SOUTHEAST)->save($qrcode_temp_path);

                if($image_x > 0 || $image_y > 0){
                    $water = [$image_x, $image_y];
                }else{
                    $water = 5;
                }

                # 图片合成
                $image = \think\Image::open($image_path);
                $image->water($qrcode_temp_path, $water)->save($my_poster);
                @unlink($qrcode_temp_path);
                @unlink($save_dir.$filename);

            }else{
                $share_error = 1;
            }


        }

        $this->assign('my_poster_src', $my_poster_src);
        return $this->fetch();
    }

    function getImage($url,$save_dir='',$filename='',$type=0){
        if(trim($url)==''){
            return array('file_name'=>'','save_path'=>'','error'=>1);
        }
        if(trim($save_dir)==''){
            $save_dir='./';
        }
        if(trim($filename)==''){//保存文件名
            $ext=strrchr($url,'.');
            if($ext!='.gif'&&$ext!='.jpg'){
                return array('file_name'=>'','save_path'=>'','error'=>3);
            }
            $filename=time().$ext;
        }
        if(0!==strrpos($save_dir,'/')){
            $save_dir.='/';
        }
        //创建保存目录
        if(!file_exists($save_dir)&&!mkdir($save_dir,0777,true)){
            return array('file_name'=>'','save_path'=>'','error'=>5);
        }
        //获取远程文件所采用的方法
        if($type){
            $ch=curl_init();
            $timeout=5;
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
            $img=curl_exec($ch);
            curl_close($ch);
        }else{
            ob_start();
            readfile($url);
            $img=ob_get_contents();
            ob_end_clean();
        }
        //$size=strlen($img);
        //文件大小
        $fp2=@fopen($save_dir.$filename,'a');
        fwrite($fp2,$img);
        fclose($fp2);
        unset($img,$url);
        return array('file_name'=>$filename,'save_path'=>$save_dir.$filename,'error'=>0);
    }


    public function poster_qr($qr_code_file,$qr_code_path)
    {
        ob_end_clean();
        vendor('topthink.think-image.src.Image');

        error_reporting(E_ERROR);

        define('IMGROOT_PATH', str_replace("\\","/",realpath(dirname(dirname(__FILE__)).'/../../'))); //图片根目录（绝对路径）

        $back_img = IMGROOT_PATH.tpCache('background.background'); //获取背景图

        if (!is_file($back_img) || !is_file($qr_code_file)) {
            return $this->fetch('sharePoster');
        }

        $back_info = getimagesize($back_img);    //获取图片信息
        $im = checkPosterImagesType($back_info,$back_img);

        $back_width = imagesx($im);    //背景图宽
        $back_height = imagesy($im);   //背景图高
        $canvas = imagecreatetruecolor($back_width,$back_height);  //创建画布

        imagecopyresized($canvas,$im,0,0,0,0,$back_width,$back_height,$back_width,$back_height);   //缩放
        $new_QR = $qr_code_path.createImagesName().".png";    //获得缩小后新的二维码路径

        inputPosterImages($back_info,$canvas,$new_QR);  //输出到png即为一个缩放后的文件

        $QR = imagecreatefromstring(file_get_contents($qr_code_file));
        $background_img = imagecreatefromstring(file_get_contents($new_QR));
        imagecopyresampled($background_img,$QR,$back_width-130,$back_height-150,0,0,110,110,430,430);  //合成图片
        $result_png = createImagesName().".png";
        $file = $qr_code_path.$result_png;
        imagepng ($background_img,$file);  //输出合成海报图片

        $final_poster = imagecreatefromstring(file_get_contents($file)); //获得该图片资源显示图片

        header("Content-type: image/png");
        imagepng($final_poster);
        imagedestroy($final_poster);

        $new_QR && unlink($new_QR);
        // $qr_code_file && unlink($qr_code_file);
        $file && unlink($file);
        exit();
    }

    public function logout()
    {
        session_unset();
        session_destroy();
        setcookie('uname','',time()-3600,'/');
        setcookie('cn','',time()-3600,'/');
        setcookie('user_id','',time()-3600,'/');
        setcookie('PHPSESSID','',time()-3600,'/');
        //$this->success("退出成功",U('Mobile/Index/index'));
        header("Location:" . U('Mobile/Index/index'));
        exit();
    }

    /*
     * 账户资金
     */
    public function account()
    {
//        $user = session('user');
        $user = $this->user;
        //获取账户资金记录
        $logic = new UsersLogic();
        $data = $logic->get_account_log($this->user_id, I('get.type'));
        $account_log = $data['result'];
        $this->assign('user', $user);
        $this->assign('account_log', $account_log);
        $this->assign('page', $data['show']);

        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_account_list');
            exit;
        }
        return $this->fetch();
    }

    public function account_list()
    {
        // // $usersLogic = new UsersLogic;
        // // $result = $usersLogic->account($this->user_id, $type);

        // if ($_GET['is_ajax']) {
        // 	return $this->fetch('ajax_account_list');
        // }
        return $this->fetch();
    }

    public function get_record()
    {
        $user_id = $this->user_id;
        $type = I('type','income');
        $distribut_type = I('distribut_type','0');
        $page = I('page',1);
        $result = array();

        if ($type == 'income') {

            $where = get_comm_condition($distribut_type); //获取条件
            if($distribut_type == 7){
                $result = M('vip_commission_log')->where('to_user_id',$user_id)->where($where)->order('create_time','desc')->field('log_id,money,status,order_id,create_time')->page($page,15)->select();
            }else{
                $result = M('distrbut_commission_log')->where('to_user_id',$user_id)->where($where)->order('create_time','desc')->field('log_id,money,status,order_id,create_time')->page($page,15)->select();
            }

            foreach ($result as $key => $value) {
                $result[$key]['create_time'] = date('Y-m-d H:i',$value['create_time']);
            }
        } else {
            $result = M('account_log')->where('user_money','<',0)->where('user_id',$user_id)->order('create_time','desc')->field('log_id,user_money as money,change_time as create_time,order_id')->page($page,15)->select();

            foreach ($result as $key => $value) {
                $result[$key]['create_time'] = date('Y-m-d H:i',$value['create_time']);
                // $result[$key]['money'] = abs($value['money']);
                $result[$key]['status'] = 1;
            }
        }

        return json($result);
    }
  
  	/**
     * 从新执行返佣失败的数据
     */
    public function record_again()
    {
        //获取所有失败数据
        $faild_data = Db::name('distrbut_commission_log')->where('status',0)->field('log_id,to_user_id,money')->select();
        if(!empty($faild_data)){
            foreach($faild_data as $k=>$v){
                $res = Db::name('users')->where('user_id',$v['to_user_id'])->setInc('user_money',$v['money']);//更新用户余额
              	echo $res;
                if($res){
                    //如果更新成功，改变记录状态
                    $a = Db::name('distrbut_commission_log')->where('log_id',$v['log_id'])->update(['status'=>1]);
                  	if($a > 0){
                      echo "success update";
                    }
                }
            }
        }
    }

    public function account_detail(){
        $log_id = I('log_id/d',0);
        $detail = Db::name('account_log')->where(['log_id'=>$log_id])->find();
        $this->assign('detail',$detail);
        return $this->fetch();
    }

    /**
     * 优惠券
     */
    public function coupon()
    {
        $logic = new UsersLogic();
        $data = $logic->get_coupon($this->user_id, input('type'));
        foreach($data['result'] as $k =>$v){
            $user_type = $v['use_type'];
            $data['result'][$k]['use_scope'] = C('COUPON_USER_TYPE')["$user_type"];
            if($user_type==1){ //指定商品
                $data['result'][$k]['goods_id'] = M('goods_coupon')->field('goods_id')->where(['coupon_id'=>$v['cid']])->getField('goods_id');
            }
            if($user_type==2){ //指定分类
                $data['result'][$k]['category_id'] = Db::name('goods_coupon')->where(['coupon_id'=>$v['cid']])->getField('goods_category_id');
            }
        }
        $coupon_list = $data['result'];
        $this->assign('coupon_list', $coupon_list);
        $this->assign('page', $data['show']);
        if (input('is_ajax')) {
            return $this->fetch('ajax_coupon_list');
            exit;
        }
        return $this->fetch();
    }

    /**
     *  登录
     */
    public function login()
    {
        if ($this->user_id > 0) {
            // header("Location: " . U('Mobile/User/index'));
            $this->redirect('Mobile/User/index');
        }
        $referurl = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : U("Mobile/User/index");
        $this->assign('referurl', $referurl);
        // 新版支付宝跳转链接
        $this->assign('alipay_url', urlencode(SITE_URL.U("Mobile/LoginApi/login",['oauth'=>'alipaynew'])));
        return $this->fetch();
    }

    /**
     * 登录
     */
    public function do_login()
    {
        $username = trim(I('post.username'));
//        $password = trim(I('post.password'));
        $mobile_code = trim(I('post.mobile_code'));
        //验证码验证
//        if (isset($_POST['verify_code'])) {
//            $verify_code = I('post.verify_code');
//            $verify = new Verify();
//            if (!$verify->check($verify_code, 'user_login')) {
//                $res = array('status' => 0, 'msg' => '验证码错误');
//                exit(json_encode($res));
//            }
//        }
//        if($mobile_code!='000000'){
//            // 验证码
//            //            $code=I('mobile_code');
//            $sms_type=I('sms_type');
//            $checkData['sms_type'] = $sms_type;
//            $checkData['code'] = $mobile_code;
//            $checkData['phone'] = $username;
//            $res = checkPhoneCode($checkData);
//            if ($res['code'] == 0) {
//                exit(json_encode(['status' => 0, 'msg' => $res['msg']]));
//            }
//        }

        $logic = new UsersLogic();
        $res = $logic->login($username, 1);
//        var_dump($res);die;
        if ($res['status'] == 1) {
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            session('user', $res['result']);
            setcookie('user_id', $res['result']['user_id'], null, '/');
            setcookie('is_distribut', $res['result']['is_distribut'], null, '/');
            $nickname = empty($res['result']['nickname']) ? $username : $res['result']['nickname'];
            setcookie('uname', urlencode($nickname), null, '/');
            setcookie('cn', 0, time() - 3600, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($res['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $orderLogic = new OrderLogic();
            $orderLogic->setUserId($res['result']['user_id']);//登录后将超时未支付订单给取消掉
            $orderLogic->abolishOrder();
        }else if($res['status'] == -1){
            $res['status']  = 1;
            $res['url'] = htmlspecialchars_decode(I('post.referurl'));
            //之前没有注册过  生成一个新的用户
            $data = $logic->reg($username, '123456', '123456');
//            var_dump($data);die;
            if ($data['status'] != 1) exit(json_encode($data));
            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id'=>$data['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];

            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作

        }
        exit(json_encode($res));
    }

    /**
     *  注册
     */
    public function reg()
    {

        if($this->user_id > 0) {
            $this->redirect(U('Mobile/User/index'));
        }
        $reg_sms_enable = tpCache('sms.regis_sms_enable');
        $reg_smtp_enable = tpCache('sms.regis_smtp_enable');

        if (IS_POST) {
            $logic = new UsersLogic();
            //验证码检验
            //$this->verifyHandle('user_reg');
            $nickname = I('post.nickname', '');
            $username = I('post.username', '');
            $password = I('post.password', '');
            $password2 = I('post.password2', '');
            $is_bind_account = tpCache('basic.is_bind_account');
            //是否开启注册验证码机制
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 1);

            $session_id = session_id();
            $sms_type = I('post.sms_type');

            // 验证码
            $checkData['sms_type'] = 1;
            $checkData['code'] = $code;
            $checkData['phone'] = $username;
            $res = checkPhoneCode($checkData);
            if ($res['code'] == 0) {
                return json([ 'msg' => $res['msg']]);
            }

            //是否开启注册验证码机制
            if(check_mobile($username)){
                if($reg_sms_enable){
                    //手机功能没关闭
                    $check_code = $logic->check_validate_code($code, $username, 'phone', $session_id, $scene);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }
            //是否开启注册邮箱验证码机制
            if(check_email($username)){
                if($reg_smtp_enable){
                    //邮件功能未关闭
                    $check_code = $logic->check_validate_code($code, $username);
                    if($check_code['status'] != 1){
                        $this->ajaxReturn($check_code);
                    }
                }
            }

            $invite = I('invite');
            if(!empty($invite)){
                $invite = get_user_info($invite,2);//根据手机号查找邀请人
                if(empty($invite)){
                    $this->ajaxReturn(['status'=>-1,'msg'=>'推荐人不存在','result'=>'']);
                }
            }else{
                $invite = array();
            }
            if($is_bind_account && session("third_oauth")){ //绑定第三方账号
                $thirdUser = session("third_oauth");
                $head_pic = $thirdUser['head_pic'];
                $data = $logic->reg($username, $password, $password2, 0, $invite ,$nickname , $head_pic);
                //用户注册成功后, 绑定第三方账号
                $userLogic = new UsersLogic();
                $data = $userLogic->oauth_bind_new($data['result']);
            }else{
                $data = $logic->reg($username, $password, $password2,0,$invite);
            }


            if ($data['status'] != 1) $this->ajaxReturn($data);

            //获取公众号openid,并保持到session的user中
            $oauth_users = M('OauthUsers')->where(['user_id'=>$data['result']['user_id'] , 'oauth'=>'weixin' , 'oauth_child'=>'mp'])->find();
            $oauth_users && $data['result']['open_id'] = $oauth_users['open_id'];

            session('user', $data['result']);
            setcookie('user_id', $data['result']['user_id'], null, '/');
            setcookie('is_distribut', $data['result']['is_distribut'], null, '/');
            $cartLogic = new CartLogic();
            $cartLogic->setUserId($data['result']['user_id']);
            $cartLogic->doUserLoginHandle();// 用户登录后 需要对购物车 一些操作
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('regis_sms_enable',$reg_sms_enable); // 注册启用短信：
        $this->assign('regis_smtp_enable',$reg_smtp_enable); // 注册启用邮箱：
        $sms_time_out = tpCache('sms.sms_time_out')>0 ? tpCache('sms.sms_time_out') : 120;
        $this->assign('sms_time_out', $sms_time_out); // 手机短信超时时间
        return $this->fetch();
    }

    public function bind_guide(){
        $data = session('third_oauth');
        //没有第三方登录的话就跳到登录页
        if(empty($data)){
            $this->redirect('User/login');
        }
        $first_leader = Cache::get($data['openid']);
        if($first_leader){
            //拿关注传时候过来来的上级id
            setcookie('first_leader',$first_leader);
        }
        $this->assign("nickname", $data['nickname']);
        $this->assign("oauth", $data['oauth']);
        $this->assign("head_pic", $data['head_pic']);
        $this->assign('store_name',tpCache('shop_info.store_name'));
        return $this->fetch();
    }

    /**
     * 绑定已有账号
     * @return \think\mixed
     */
    public function bind_account()
    {
        $mobile = input('mobile/s');
        $verify_code = input('verify_code/s');
        //发送短信验证码
        $logic = new UsersLogic();
        $check_code = $logic->check_validate_code($verify_code, $mobile, 'phone', session_id(), 1);
        if($check_code['status'] != 1){
            $this->ajaxReturn(['status'=>0,'msg'=>$check_code['msg'],'result'=>'']);
        }
        if(empty($mobile) || !check_mobile($mobile)){
            $this->ajaxReturn(['status' => 0, 'msg' => '手机格式错误']);
        }
        $users = Db::name('users')->where('mobile',$mobile)->find();
        if (empty($users)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '账号不存在']);
        }
        $user = new \app\common\logic\User();
        $user->setUserById($users['user_id']);
        $cartLogic = new CartLogic();
        try{
            $user->checkOauthBind();
            $user->oauthBind();
            $user->doLeader();
            $user->refreshCookie();
            $cartLogic->setUserId($users['user_id']);
            $cartLogic->doUserLoginHandle();
            $orderLogic = new OrderLogic();//登录后将超时未支付订单给取消掉
            $orderLogic->setUserId($users['user_id']);
            $orderLogic->abolishOrder();
            $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功']);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }
    /**
     * 先注册再绑定账号
     * @return \think\mixed
     */
    public function bind_reg()
    {
        $mobile = input('mobile/s');
        $verify_code = input('verify_code/s');
        $password = input('password/s');
        $nickname = input('nickname/s', '');
        if(empty($mobile) || !check_mobile($mobile)){
            $this->ajaxReturn(['status' => 0, 'msg' => '手机格式错误']);
        }
        if(empty($password)){
            $this->ajaxReturn(['status' => 0, 'msg' => '请输入密码']);
        }
        $logic = new UsersLogic();
        $check_code = $logic->check_validate_code($verify_code, $mobile, 'phone', session_id(), 1);
        if($check_code['status'] != 1){
            $this->ajaxReturn(['status'=>0,'msg'=>$check_code['msg'],'result'=>'']);
        }
        $thirdUser = session('third_oauth');
        $data = $logic->reg($mobile, $password, $password, 0, [], $nickname, $thirdUser['head_pic']);
        if ($data['status'] != 1) {
            $this->ajaxReturn(['status'=>0,'msg'=>$data['msg'],'result'=>'']);
        }
        $user = new \app\common\logic\User();
        $user->setUserById($data['result']['user_id']);
        try{
            $user->checkOauthBind();
            $user->oauthBind();
            $user->refreshCookie();
            $this->ajaxReturn(['status' => 1, 'msg' => '绑定成功']);
        }catch (TpshopException $t){
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    public function ajaxAddressList()
    {
        $UserAddress = new UserAddress();
        $address_list = $UserAddress->where('user_id', $this->user_id)->order('is_default desc')->select();
        if($address_list){
            $address_list = collection($address_list)->append(['address_area'])->toArray();
        }else{
            $address_list = [];
        }
        $this->ajaxReturn($address_list);
    }

    /**
     * 用户地址列表
     */
    public function address_list()
    {
        $address_lists =  db('user_address')->where('user_id', $this->user_id)->select();
        $region_list = db('region')->cache(true)->getField('id,name');
        $this->assign('region_list', $region_list);
        $this->assign('lists', $address_lists);
        return $this->fetch();
    }

    /**
     * 保存地址
     */
    public function addressSave()
    {
        $address_id = input('address_id/d',0);
        $data = input('post.');
        $userAddressValidate = Loader::validate('UserAddress');
        if (!$userAddressValidate->batch()->check($data)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败', 'result' => $userAddressValidate->getError()]);
        }
        if (!empty($address_id)) {
            //编辑
            $userAddress = UserAddress::get(['address_id'=>$address_id,'user_id'=> $this->user_id]);
            if(empty($userAddress)){
                $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
            }
        } else {
            //新增
            $userAddress = new UserAddress();
            $user_address_count = Db::name('user_address')->where("user_id", $this->user_id)->count();
            if ($user_address_count >= 20) {
                $this->ajaxReturn(['status' => 0, 'msg' => '最多只能添加20个收货地址']);
            }
            $data['user_id'] = $this->user_id;
        }
        $userAddress->data($data);
        $userAddress['longitude'] = true;
        $userAddress['latitude'] = true;
        $row = $userAddress->save();
        if ($row !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '操作成功', 'result'=>['address_id'=>$userAddress->address_id]]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '操作失败']);
        }
    }
    /*
         * 添加地址
         */
    public function add_address()
    {
        $source = input('source');
        if (IS_POST) {
            $post_data = input('post.');
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, 0, $post_data);
            if ($data['status'] != 1){
                $this->ajaxReturn($data);
            } else {
                $data['url']= U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $this->assign('province', $p);
        $this->assign('source', $source);
        return $this->fetch();

    }

    /*
     * 地址编辑
     */
    public function edit_address()
    {
        $id = I('id/d');
        $address = M('user_address')->where(array('address_id' => $id, 'user_id' => $this->user_id))->find();
        if (IS_POST) {
            $post_data = input('post.');
            $source = $post_data['source'];
            $logic = new UsersLogic();
            $data = $logic->add_address($this->user_id, $id, $post_data);
            if ($source == 'cart2') {
                $data['url']=U('/Mobile/Cart/cart2', array('address_id' => $data['result'],'goods_id'=>$post_data['goods_id'],'goods_num'=>$post_data['goods_num'],'item_id'=>$post_data['item_id'],'action'=>$post_data['action']));
                $this->ajaxReturn($data);
            } elseif ($source == 'integral') {
                $data['url'] = U('/Mobile/Cart/integral', array('address_id' => $data['result'],'goods_id'=>$post_data['goods_id'],'goods_num'=>$post_data['goods_num'],'item_id'=>$post_data['item_id']));
                $this->ajaxReturn($data);
            } elseif($source == 'pre_sell_cart'){
                $data['url'] = U('/Mobile/Cart/pre_sell_cart', array('address_id' => $data['result'],'act_id'=>$post_data['act_id'],'goods_num'=>$post_data['goods_num']));
                $this->ajaxReturn($data);
            } elseif($source == 'team'){
                $data['url']= U('/Mobile/Team/order', array('address_id' => $data['result'],'order_id'=>$post_data['order_id']));
                $this->ajaxReturn($data);
            } elseif ($_POST['source'] == 'pre_sell') {
                $prom_id = input('prom_id/d');
                $data['url'] = U('/Mobile/Cart/pre_sell', array('address_id' => $data['result'],'goods_num' => $goods_num,'prom_id' => $prom_id));
                $this->ajaxReturn($data);
            } else {
                $data['url']= U('/Mobile/User/address_list');
                $this->ajaxReturn($data);
            }
        }
        //获取省份
        $p = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        $c = M('region')->where(array('parent_id' => $address['province'], 'level' => 2))->select();
        $d = M('region')->where(array('parent_id' => $address['city'], 'level' => 3))->select();
        if ($address['twon']) {
            $e = M('region')->where(array('parent_id' => $address['district'], 'level' => 4))->select();
            $this->assign('twon', $e);
        }
        $this->assign('province', $p);
        $this->assign('city', $c);
        $this->assign('district', $d);
        $this->assign('address', $address);
        return $this->fetch();
    }

    /*
     * 设置默认收货地址
     */
    public function set_default()
    {
        $id = I('get.id/d');
        $source = I('get.source');
        M('user_address')->where(array('user_id' => $this->user_id))->save(array('is_default' => 0));
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->save(array('is_default' => 1));
        if ($source == 'cart2') {
            header("Location:" . U('Mobile/Cart/cart2'));
            exit;
        } else {
            header("Location:" . U('Mobile/User/address_list'));
        }
    }

    /*
     * 地址删除
     */
    public function del_address()
    {
        $id = I('get.id/d');

        $address = M('user_address')->where("address_id", $id)->find();
        $row = M('user_address')->where(array('user_id' => $this->user_id, 'address_id' => $id))->delete();
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if ($address['is_default'] == 1) {
            $address2 = M('user_address')->where("user_id", $this->user_id)->find();
            $address2 && M('user_address')->where("address_id", $address2['address_id'])->save(array('is_default' => 1));
        }
        if (!$row)
            $this->error('操作失败', U('User/address_list'));
        else
            $this->success("操作成功", U('User/address_list'));
    }


    /*
     * 个人信息
     */
    public function userinfo()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        if (IS_POST) {
            if ($_FILES['head_pic']['tmp_name']) {
                $file = $this->request->file('head_pic');
                $image_upload_limit_size = config('image_upload_limit_size');
                $validate = ['size'=>$image_upload_limit_size,'ext'=>'jpg,png,gif,jpeg'];
                $dir = UPLOAD_PATH.'head_pic/';
                if (!($_exists = file_exists($dir))){
                    $isMk = mkdir($dir);
                }
                $parentDir = date('Ymd');
                $info = $file->validate($validate)->move($dir, true);
                if($info){
                    $post['head_pic'] = '/'.$dir.$parentDir.'/'.$info->getFilename();
                }else{
                    $this->error($file->getError());//上传错误提示错误信息
                }
            }
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            I('post.head_pic') ? $post['head_pic'] = I('post.head_pic') : false; //头像地址
            I('post.sex') ? $post['sex'] = I('post.sex') : $post['sex'] = 0;  // 性别
            I('post.birthday') ? $post['birthday'] = strtotime(I('post.birthday')) : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            I('post.email') ? $post['email'] = I('post.email') : false; //邮箱
            I('post.mobile') ? $post['mobile'] = I('post.mobile') : false; //手机

            $email = I('post.email');
            $mobile = I('post.mobile');
            $code = I('post.mobile_code', '');
            $scene = I('post.scene', 6);

            if (!empty($email)) {
                $c = M('users')->where(['email' => input('post.email'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("邮箱已被使用");
            }
            if (!empty($mobile)) {
                $c = M('users')->where(['mobile' => input('post.mobile'), 'user_id' => ['<>', $this->user_id]])->count();
                $c && $this->error("手机已被使用");
                if (!$code)
                    $this->error('请输入验证码');
                $check_code = $userLogic->check_validate_code($code, $mobile, 'phone', $this->session_id, $scene);
                if ($check_code['status'] != 1)
                    $this->error($check_code['msg']);
            }

            if (!$userLogic->update_info($this->user_id, $post))
                $this->error("保存失败");
            setcookie('uname',urlencode($post['nickname']),null,'/');
            $this->success("操作成功",U('User/userinfo'));
            exit;
        }
        //  获取省份
        $province = M('region')->where(array('parent_id' => 0, 'level' => 1))->select();
        //  获取订单城市
        $city = M('region')->where(array('parent_id' => $user_info['province'], 'level' => 2))->select();
        //  获取订单地区
        $area = M('region')->where(array('parent_id' => $user_info['city'], 'level' => 3))->select();
        $this->assign('province', $province);
        $this->assign('city', $city);
        $this->assign('area', $area);
        $this->assign('user', $user_info);
        $this->assign('sex', C('SEX'));
        //从哪个修改用户信息页面进来，
        $dispaly = I('action');
        if ($dispaly != '') {
            return $this->fetch("$dispaly");
        }
        return $this->fetch();
    }

    /**
     * 修改绑定手机
     * @return mixed
     */
    public function setMobile(){
        $userLogic = new UsersLogic();
//        $status=0;
        if (IS_POST) {
            $mobile = input('mobile');
            $mobile_code = input('mobile_code');
            $scene = input('post.scene', 6);
            $validate = I('validate',0);
            $status = I('status',0);
            $c = Db::name('users')->where(['mobile' => $mobile, 'user_id' => ['<>', $this->user_id]])->count();
            $c && $this->error('手机已被使用');
//            if (!$mobile_code)
//                $this->error('请输入验证码');
//            $check_code = $userLogic->check_validate_code($mobile_code, $mobile, 'phone', $this->session_id, $scene);
//            if($check_code['status'] !=1){
//                $this->error($check_code['msg']);
//            }
            // 验证码
//            $code=I('mobile_code');
            $sms_type=I('sms_type');
            $checkData['sms_type'] = $sms_type;
            $checkData['code'] = $mobile_code;
            $checkData['phone'] = $mobile;
            $res = checkPhoneCode($checkData);
            if ($res['code'] == 0) {
                $this->error($res['msg']);
            }


            if($validate == 1 && $status == 0){
                $res = Db::name('users')->where(['user_id' => $this->user_id])->update(['mobile'=>$mobile,'mobile_validated'=>1]);

                if($res!==false){
                    $source = I('source');
                    !empty($source) && $this->success('绑定成功', U("User/$source"));
                    $this->success('修改成功',U('User/userinfo'));
                }
                $this->error('修改失败');
            }
        }
        $this->assign('status',$status);
        return $this->fetch();
    }

    /*
     * 邮箱验证
     */
    public function email_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['email_validated'] == 0)
            $step = 2;
        //原邮箱验证是否通过
        if ($user_info['email_validated'] == 1 && session('email_step1') == 1)
            $step = 2;
        if ($user_info['email_validated'] == 1 && session('email_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $email = I('post.email');
            $code = I('post.code');
            $info = session('email_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $email || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('email_code', null);
                    session('email_step1', null);
                    if (!$userLogic->update_email_mobile($email, $this->user_id))
                        $this->error('邮箱已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('email_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/email_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码邮箱不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /*
    * 手机验证
    */
    public function mobile_validate()
    {
        $userLogic = new UsersLogic();
        $user_info = $userLogic->get_info($this->user_id); // 获取用户信息
        $user_info = $user_info['result'];
        $step = I('get.step', 1);
        //验证是否未绑定过
        if ($user_info['mobile_validated'] == 0)
            $step = 2;
        //原手机验证是否通过
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') == 1)
            $step = 2;
        if ($user_info['mobile_validated'] == 1 && session('mobile_step1') != 1)
            $step = 1;
        if (IS_POST) {
            $mobile = I('post.mobile');
            $code = I('post.code');
            $info = session('mobile_code');
            if (!$info)
                $this->error('非法操作');
            if ($info['email'] == $mobile || $info['code'] == $code) {
                if ($user_info['email_validated'] == 0 || session('email_step1') == 1) {
                    session('mobile_code', null);
                    session('mobile_step1', null);
                    if (!$userLogic->update_email_mobile($mobile, $this->user_id, 2))
                        $this->error('手机已存在');
                    $this->success('绑定成功', U('Home/User/index'));
                } else {
                    session('mobile_code', null);
                    session('email_step1', 1);
                    redirect(U('Home/User/mobile_validate', array('step' => 2)));
                }
                exit;
            }
            $this->error('验证码手机不匹配');
        }
        $this->assign('step', $step);
        return $this->fetch();
    }

    /**
     * 用户收藏列表
     */
    public function collect_list()
    {
        $userLogic = new UsersLogic();
        $data = $userLogic->get_goods_collect($this->user_id);
        $this->assign('page', $data['show']);// 赋值分页输出
        $this->assign('goods_list', $data['result']);
        if (IS_AJAX) {      //ajax加载更多
            return $this->fetch('ajax_collect_list');
            exit;
        }
        return $this->fetch();
    }

    /*
     *取消收藏
     */
    public function cancel_collect()
    {
        $collect_id = I('collect_id/d');
        $user_id = $this->user_id;
        if (M('goods_collect')->where(['collect_id' => $collect_id, 'user_id' => $user_id])->delete()) {
            $this->success("取消收藏成功", U('User/collect_list'));
        } else {
            $this->error("取消收藏失败", U('User/collect_list'));
        }
    }

    /**
     * 我的留言
     */
    public function message_list()
    {
        C('TOKEN_ON', true);
        if (IS_POST) {
            if(!$this->verifyHandle('message')){
                $this->error('验证码错误', U('User/message_list'));
            };

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $user = session('user');
            $data['user_name'] = $user['nickname'];
            $data['msg_time'] = time();
            if (M('feedback')->add($data)) {
                $this->success("留言成功", U('User/message_list'));
                exit;
            } else {
                $this->error('留言失败', U('User/message_list'));
                exit;
            }
        }
        $msg_type = array(0 => '留言', 1 => '投诉', 2 => '询问', 3 => '售后', 4 => '求购');
        $count = M('feedback')->where("user_id", $this->user_id)->count();
        $Page = new Page($count, 100);
        $Page->rollPage = 2;
        $message = M('feedback')->where("user_id", $this->user_id)->limit($Page->firstRow . ',' . $Page->listRows)->select();
        $showpage = $Page->show();
        header("Content-type:text/html;charset=utf-8");
        $this->assign('page', $showpage);
        $this->assign('message', $message);
        $this->assign('msg_type', $msg_type);
        return $this->fetch();
    }

    /**账户明细*/
    public function points()
    {
        $type = I('type', 'all');    //获取类型
        $this->assign('type', $type);
        if ($type == 'recharge') {
            //充值明细
            $count = M('recharge')->where("user_id", $this->user_id)->count();
            $Page = new Page($count, 16);
            $account_log = M('recharge')->where("user_id", $this->user_id)->order('order_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else if ($type == 'points') {
            //积分记录明细
            $count = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id, 'pay_points' => ['<>', 0]])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        } else {
            //全部
            $count = M('account_log')->where(['user_id' => $this->user_id])->count();
            $Page = new Page($count, 16);
            $account_log = M('account_log')->where(['user_id' => $this->user_id])->order('log_id desc')->limit($Page->firstRow . ',' . $Page->listRows)->select();
        }
        $show = $Page->show();
        $this->assign('account_log', $account_log);
        $this->assign('page', $show);
        $this->assign('listRows', $Page->listRows);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
            exit;
        }
        return $this->fetch();
    }


    public function points_list()
    {
        $type = I('type','all');
        $usersLogic = new UsersLogic;
        $result = $usersLogic->points($this->user_id, $type);

        $this->assign('type', $type);
        $showpage = $result['page']->show();
        $this->assign('account_log', $result['account_log']);
        $this->assign('page', $showpage);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_points');
        }
        return $this->fetch();
    }


    /*
     * 密码修改
     */
    public function password()
    {
        if (IS_POST) {
            $logic = new UsersLogic();
            $data = $logic->get_info($this->user_id);
            $user = $data['result'];
            if ($user['mobile'] == '' && $user['email'] == '')
                $this->ajaxReturn(['status'=>-1,'msg'=>'请先绑定手机或邮箱','url'=>U('/Mobile/User/index')]);
            $userLogic = new UsersLogic();
            $data = $userLogic->password($this->user_id, I('post.old_password'), I('post.new_password'), I('post.confirm_password'));
            if ($data['status'] == -1)
                $this->ajaxReturn(['status'=>-1,'msg'=>$data['msg']]);
            Session::delete('user');
            $this->ajaxReturn(['status'=>1,'msg'=>$data['msg'],'url'=>U('/Mobile/User/index')]);
            exit;
        }
        return $this->fetch();
    }

    function forget_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect("User/index");
        }
        $username = I('username');
        if (IS_POST) {
            if (!empty($username)) {
                if(!$this->verifyHandle('forget')){
                    $this->ajaxReturn(['status'=>-1,'msg'=>"验证码错误"]);
                };
                // 验证码
                $code=I('mobile_code');
                $sms_type=I('sms_type');
                $checkData['sms_type'] = $sms_type;
                $checkData['code'] = $code;
                $checkData['phone'] = $username;
                $res = checkPhoneCode($checkData);
                if ($res['code'] == 0) {
                    return json(['status' => -1, 'msg' => $res['msg']]);
                }
                $field = 'mobile';
                if (check_email($username)) {
                    $field = 'email';
                }
                $user = M('users')->where("email", $username)->whereOr('mobile', $username)->find();
                if ($user) {
                    $sms_status = checkEnableSendSms(2);
                    session('find_password', array('user_id' => $user['user_id'], 'username' => $username,
                        'email' => $user['email'], 'mobile' => $user['mobile'], 'type' => $field,'sms_status'=>$sms_status['status']));
                    $regis_smtp_enable = $this->tpshop_config['smtp_regis_smtp_enable'];
                    if(($field=='mobile' && $this->tpshop_config['sms_forget_pwd_sms_enable']==1)){
                        $this->ajaxReturn(['status'=>1,'msg'=>"用户验证成功",'url'=>U('User/find_pwd')]);
                    }

                    if(($field=='email' && $regis_smtp_enable==0) || ($field=='mobile' && $sms_status['status']<1)){
                        $this->ajaxReturn(['status'=>1,'msg'=>"用户验证成功",'url'=>U('User/set_pwd')]);
                    }
                    exit;
                } else {
                    $this->ajaxReturn(['status'=>-1,'msg'=>"用户名不存在，请检查"]);
                }
            }
        }
        return $this->fetch();
    }

    function find_pwd()
    {
        if ($this->user_id > 0) {
            header("Location: " . U('User/index'));
        }
        $user = session('find_password');
        if (empty($user)) {
            $this->error("请先验证用户名", U('User/forget_pwd'));
        }
        $this->assign('user', $user);
        return $this->fetch();
    }


    public function set_pwd()
    {
        if ($this->user_id > 0) {
            $this->redirect('Mobile/User/index');
        }
        $check = session('validate_code');
        $find_password = session('find_password');
        $field = $find_password['field'];
        $sms_status = session('find_password')['sms_status'];
        $regis_smtp_enable = $this->tpshop_config['smtp_regis_smtp_enable'];
        $is_check_code=false;
        //需要验证邮箱或者手机
        if($field=='email' && $regis_smtp_enable==1)$is_check_code = true;
        if($field=='mobile' && $sms_status['status']==1)$is_check_code = true;
        if ((empty($check) || $check['is_check'] == 0) && $is_check_code) {
            $this->error('验证码还未验证通过',U('User/forget_pwd'));
        }
        if (IS_POST) {
            $data['password'] = $password = I('post.password');
            $data['password2'] = $password2 = I('post.password2');
            $UserRegvalidate = Loader::validate('User');
            if(!$UserRegvalidate->scene('set_pwd')->check($data)){
                $this->error($UserRegvalidate->getError(),U('User/forget_pwd'));
            }
            M('users')->where("user_id", $find_password['user_id'])->save(array('password' => encrypt($password)));
            session('validate_code', null);
            return $this->fetch('reset_pwd_sucess');
        }
        $is_set = I('is_set', 0);
        $this->assign('is_set', $is_set);
        return $this->fetch();
    }

    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            return false;
        }
        return true;
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 30,
            'length' => 4,
            'imageH' =>  60,
            'imageW' =>  300,
            'fontttf' => '5.ttf',
            'useCurve' => false,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
        exit();
    }

    /**
     * 账户管理
     */
    public function accountManage()
    {
        return $this->fetch();
    }

    public function recharge()
    {
        $order_id = I('order_id/d');
        $paymentList = M('Plugin')->where(['type'=>'payment' ,'code'=>['neq','cod'],'status'=>1,'scene'=> ['in','0,1']])->select();
        $paymentList = convert_arr_key($paymentList, 'code');
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            unset($paymentList['weixinH5']);
        }else{
            unset($paymentList['weixin']);
        }
        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);

        // 查找最近一次充值方式
        $recharge_arr = Db::name('Recharge')->field('pay_code')->where('user_id', $this->user_id)
            ->order('order_id desc')->find();
        $alipay = 'alipayMobile'; //默认支付宝支付
        if($recharge_arr){
            foreach ($paymentList as  $key=>$item) {
                if($key == $recharge_arr['pay_code']){
                    $alipay = $recharge_arr['pay_code'];
                }
            }
        }
        $this->assign('alipay', $alipay);

        if ($order_id > 0) {
            $order = M('recharge')->where("order_id", $order_id)->find();
            $this->assign('order', $order);
        }
        return $this->fetch();
    }

    public function recharge_list(){
        $usersLogic = new UsersLogic;
        $result= $usersLogic->get_recharge_log($this->user_id);  //充值记录
        $this->assign('page', $result['show']);
        $this->assign('lists', $result['result']);
        if (I('is_ajax')) {
            return $this->fetch('ajax_recharge_list');
        }
        return $this->fetch();
    }

    //添加、编辑提现支付宝账号
    public function add_card(){
        $user_id=$this->user_id;
        $data=I('post.');
        if($data['type']==0){
            $info['cash_alipay']=$data['card'];
            $info['realname']=$data['cash_name'];
            $info['user_id']=$user_id;
            $res=DB::name('user_extend')->where('user_id='.$user_id)->count();
            if($res){
                $res2=Db::name('user_extend')->where('user_id='.$user_id)->save($info);
            }else{
                $res2=Db::name('user_extend')->add($info);
            }
            if($res2){
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }else{
                //防止非支付宝类型的表单提交
                $this->ajaxReturn(['status'=>0,'msg'=>'不支持的提现方式']);
            }
        }

        if($data['type']==1){
            $info['cash_unionpay']=$data['card'];
            $info['realname']=$data['cash_name'];
            $info['user_id']=$user_id;
            $res=DB::name('user_extend')->where('user_id='.$user_id)->count();
            if($res){
                $res2=Db::name('user_extend')->where('user_id='.$user_id)->save($info);
            }else{
                $res2=Db::name('user_extend')->add($info);
            }
            if($res2){
                $this->ajaxReturn(['status'=>1,'msg'=>'操作成功']);
            }else{
                //防止非银行卡类型的表单提交
                $this->ajaxReturn(['status'=>0,'msg'=>'不支持的提现方式']);
            }
        }

    }

    /**
     * 申请提现记录
     */
    public function withdrawals()
    {

        // dump($this->user['goods_id']);
        $config = tpCache('cash');
        // dump($config);
        C('TOKEN_ON', true);
        $cash_open=tpCache('cash.cash_open');
        if($cash_open!=1){
            $this->error('提现功能已关闭,请联系商家');
        }
        if (IS_POST) {
            $cash_open=tpCache('cash.cash_open');
            if($cash_open!=1){
                $this->ajaxReturn(['status'=>0, 'msg'=>'提现功能已关闭,请联系商家']);
            }
            if(intval($config['goods_id'])){
                if($this->user['is_cash'] < 1){
                    $this->ajaxReturn(['status'=>0, 'msg'=>'需完成推广报单产品后解锁提现']);
                }
            }

            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            $cash = tpCache('cash');

            if(encrypt($data['paypwd']) != $this->user['paypwd']){
                $this->ajaxReturn(['status'=>0, 'msg'=>'支付密码错误']);
            }
            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status'=>0, 'msg'=>"本次提现余额不足"]);
            }
            if ($data['money'] <= 0) {
                $this->ajaxReturn(['status'=>0, 'msg'=>'提现额度必须大于0']);
            }
            if ($data['money']%100 !== 0)
            {
                $this->ajaxReturn(['status'=>0,'msg'=>"提现金额必须为100的倍数"]);
                exit;
            }

            // 统计所有0，1的金额
            $status = ['in','0,1'];
//            $total_money = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status))->sum('money');
//            if ($total_money + $data['money'] > $this->user['user_money']) {
//                $this->ajaxReturn(['status'=>0, 'msg'=>"您有提现申请待处理，本次提现余额不足"]);
//            }
            if ($data['money'] > $this->user['user_money']) {
                $this->ajaxReturn(['status'=>0, 'msg'=>"本次提现余额不足"]);
            }

            if ($cash['cash_open'] == 1) {
                $taxfee =  round($data['money'] * $cash['service_ratio'] / 100, 2);
                // 限手续费
                if ($cash['max_service_money'] > 0 && $taxfee > $cash['max_service_money']) {
                    $taxfee = $cash['max_service_money'];
                }
                if ($cash['min_service_money'] > 0 && $taxfee < $cash['min_service_money']) {
                    $taxfee = $cash['min_service_money'];
                }
                if ($taxfee >= $data['money']) {
                    $this->ajaxReturn(['status'=>0, 'msg'=>'提现额度必须大于手续费！']);
                }
                $data['taxfee'] = $taxfee;

                // 每次限最多提现额度
                if ($cash['min_cash'] > 0 && $data['money'] < $cash['min_cash']) {
                    $this->ajaxReturn(['status'=>0, 'msg'=>'每次最少提现额度' . $cash['min_cash']]);
                }
                if ($cash['max_cash'] > 0 && $data['money'] > $cash['max_cash']) {
                    $this->ajaxReturn(['status'=>0, 'msg'=>'每次最多提现额度' . $cash['max_cash']]);
                }

                $status = ['in','0,1,2,3'];
                $create_time = ['gt',strtotime(date("Y-m-d"))];
                // 今天限总额度
                if ($cash['count_cash'] > 0) {
                    $total_money2 = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->sum('money');
                    if (($total_money2 + $data['money'] > $cash['count_cash'])) {
                        $total_money = $cash['count_cash'] - $total_money2;
                        if ($total_money <= 0) {
                            $this->ajaxReturn(['status'=>0, 'msg'=>"你今天累计提现额为{$total_money2},金额已超过可提现金额."]);
                        } else {
                            $this->ajaxReturn(['status'=>0, 'msg'=>"你今天累计提现额为{$total_money2}，最多可提现{$total_money}账户余额."]);
                        }
                    }
                }
                // 今天限申请次数
                if ($cash['cash_times'] > 0) {
                    $total_times = Db::name('withdrawals')->where(array('user_id' => $this->user_id, 'status' => $status, 'create_time' => $create_time))->count();
                    if ($total_times >= $cash['cash_times']) {
                        $this->ajaxReturn(['status'=>0, 'msg'=>"今天申请提现的次数已用完."]);
                    }
                }
            }else{
                $data['taxfee'] = 0;
            }
//            dump($data);die;
            if (M('withdrawals')->add($data)) {
                Db::name('users')->where('user_id',$data['user_id'])->setDec('user_money',$data['money']);
                $this->ajaxReturn(['status'=>1,'msg'=>"已提交申请",'url'=>U('User/account',['type'=>2])]);
            } else {
                $this->ajaxReturn(['status'=>0,'msg'=>'提交失败,联系客服!']);
            }
        }




        $user_extend=Db::name('user_extend')->where('user_id='.$this->user_id)->find();

        //获取用户绑定openId
//        $oauthUsers = M("OauthUsers")->where(['user_id'=>$this->user_id, 'oauth'=>'wx'])->find();
//        $openid = $oauthUsers['openid'];
//        if(empty($oauthUsers)){
//            $openid = Db::name('oauth_users')->where(['user_id'=>$this->user_id, 'oauth'=>'weixin'])->value('openid');
//        }


        $this->assign('user_extend',$user_extend);
        $this->assign('cash_config', tpCache('cash'));//提现配置项
        $this->assign('user_money', $this->user['user_money']);    //用户余额
//        $this->assign('openid',$openid);    //用户绑定的微信openid
        return $this->fetch();
    }

//    //手机端是通过扫码PC端来绑定微信,需要ajax获取一下openID
//    public function get_openid(){
//        //halt($this->user_id); 22
//        $oauthUsers = M("OauthUsers")->where(['user_id'=>$this->user_id, 'oauth'=>'weixin'])->find();
//        $openid = $oauthUsers['openid'];
//        if(empty($oauthUsers)){
//            $openid = Db::name('oauth_users')->where(['user_id'=>$this->user_id, 'oauth'=>'wx'])->value('openid');
//        }
//        if($openid){
//            $this->ajaxReturn(['status'=>1,'result'=>$openid]);
//        }else{
//            $this->ajaxReturn(['status'=>0,'result'=>'']);
//        }
//    }

    /**
     * 申请记录列表
     */
    public function withdrawals_list()
    {
        $withdrawals_where['user_id'] = $this->user_id;
        $count = M('withdrawals')->where($withdrawals_where)->count();
        // $pagesize = C('PAGESIZE'); //10条数据，不显示滚动效果
        // $page = new Page($count, $pagesize);
        $page = new Page($count, 15);
        $list = M('withdrawals')->where($withdrawals_where)->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();

        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list', $list); // 下线
        if (I('is_ajax')) {
            return $this->fetch('ajax_withdrawals_list');
        }
        return $this->fetch();
    }

    /**
     * 我的关注
     * @author lxl
     * @time   2017/1
     */
    public function myfocus()
    {
        return $this->fetch();
    }

    /**
     *  用户消息通知
     * @author yhj
     * @time 2018/07/10
     */
    public function message_notice()
    {
        $message_logic = new Message();
        $message_logic->checkPublicMessage();
        $where = array(
            'user_id' => $this->user_id,
            'deleted' => 0,
            'category' => 0
        );
        $userMessage = new UserMessage();
        $data['message_notice'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        $where['category'] = 1;
        $data['message_activity'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        $where['category'] = 2;
        $data['message_logistics'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        //$where['category'] = 3;
        //$data['message_private'] = $userMessage->where($where)->LIMIT(1)->order('rec_id desc')->find();

        $data['no_read'] = $message_logic->getUserMessageCount();

        // 最近消息，日期，内容
        $this->assign($data);
        return $this->fetch();
    }


    /**
     * 查看通知消息详情
     */
    public function message_notice_detail()
    {

        $type = I('type', 0);
        // $type==3私信，暂时没有

        $message_logic = new Message();
        $message_logic->checkPublicMessage();

        $where = array(
            'user_id' => $this->user_id,
            'deleted' => 0,
            'category' => $type
        );
        $userMessage = new UserMessage();
        $count = $userMessage->where($where)->count();
        $page = new Page($count, 10);
        //$lists = $userMessage->where($where)->order("rec_id DESC")->limit($page->firstRow . ',' . $page->listRows)->select();

        $rec_id = $userMessage->where( $where)->LIMIT($page->firstRow.','.$page->listRows)->order('rec_id desc')->column('rec_id');
        $lists = $message_logic->sortMessageListBySendTime($rec_id, $type);

        $this->assign('lists', $lists);
        if ($_GET['is_ajax']) {
            return $this->fetch('ajax_message_detail');
        }
        if (empty($lists)) {
            return $this->fetch('user/message_none');
        }
        return $this->fetch();
    }

    /**
     * 通知消息详情
     */
    public function message_notice_info(){
        $message_logic = new Message();
        $message_details = $message_logic->getMessageDetails(I('msg_id'), I('type', 0));
        $this->assign('message_details', $message_details);
        return $this->fetch();
    }

    /**
     * 浏览记录
     */
    public function visit_log()
    {
        $count = M('goods_visit')->where('user_id', $this->user_id)->count();
        $Page = new Page($count, 20);
        $visit = M('goods_visit')->alias('v')
            ->field('v.visit_id, v.goods_id, v.visittime, g.goods_name, g.shop_price, g.cat_id')
            ->join('__GOODS__ g', 'v.goods_id=g.goods_id')
            ->where('v.user_id', $this->user_id)
            ->order('v.visittime desc')
            ->limit($Page->firstRow, $Page->listRows)
            ->select();

        /* 浏览记录按日期分组 */
        $curyear = date('Y');
        $visit_list = [];
        foreach ($visit as $v) {
            if ($curyear == date('Y', $v['visittime'])) {
                $date = date('m月d日', $v['visittime']);
            } else {
                $date = date('Y年m月d日', $v['visittime']);
            }
            $visit_list[$date][] = $v;
        }

        $this->assign('visit_list', $visit_list);
        if (I('get.is_ajax', 0)) {
            return $this->fetch('ajax_visit_log');
        }
        return $this->fetch();
    }

    /**
     * 删除浏览记录
     */
    public function del_visit_log()
    {
        $visit_ids = I('get.visit_ids', 0);
        $row = M('goods_visit')->where('visit_id','IN', $visit_ids)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 清空浏览记录
     */
    public function clear_visit_log()
    {
        $row = M('goods_visit')->where('user_id', $this->user_id)->delete();

        if(!$row) {
            $this->error('操作失败',U('User/visit_log'));
        } else {
            $this->success("操作成功",U('User/visit_log'));
        }
    }

    /**
     * 支付密码
     * @return mixed
     */
    public function paypwd()
    {
        //检查是否第三方登录用户
        $user = M('users')->where('user_id', $this->user_id)->find();
        if ($user['mobile'] == '')
            $this->error('请先绑定手机号',U('User/setMobile',['source'=>'paypwd']));
        $step = I('step', 1);
//        if ($step > 1) {
//            $check = session('validate_code');
//            if (empty($check)) {
//                $this->error('验证码还未验证通过', U('mobile/User/paypwd'));
//            }
//        }
        if (IS_POST && $step == 2) {
            $new_password = trim(I('new_password'));
            $confirm_password = trim(I('confirm_password'));
            $oldpaypwd = trim(I('old_password'));
            //以前设置过就得验证原来密码
//            if(!empty($user['paypwd']) && ($user['paypwd'] != encrypt($oldpaypwd))){
//                $this->ajaxReturn(['status'=>-1,'msg'=>'原密码验证错误！','result'=>'']);
//            }
            $userLogic = new UsersLogic();
            $data = $userLogic->paypwd($this->user_id, $new_password, $confirm_password);
            $this->ajaxReturn($data);
            exit;
        }
        $this->assign('step', $step);
        return $this->fetch();
    }


    /**
     * 会员签到积分奖励
     * 2017/9/28
     */
    public function sign()
    {
        $userLogic = new UsersLogic();
        $user_id = $this->user_id;
        $info = $userLogic->idenUserSign($user_id);//标识签到
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * Ajax会员签到
     * 2017/11/19
     */
    public function user_sign()
    {
        $userLogic = new UsersLogic();
        $user_id   = $this->user_id;
        $config    = tpCache('sign');
        $date      = I('date'); //2017-9-29
        //是否正确请求
        (date("Y-n-j", time()) != $date) && $this->ajaxReturn(['status' => false, 'msg' => '签到失败！', 'result' => '']);
        //签到开关
        if ($config['sign_on_off'] > 0) {
            $map['sign_last'] = $date;
            $map['user_id']   = $user_id;
            $userSingInfo     = Db::name('user_sign')->where($map)->find();
            //今天是否已签
            $userSingInfo && $this->ajaxReturn(['status' => false, 'msg' => '您今天已经签过啦！', 'result' => '']);
            //是否有过签到记录
            $checkSign = Db::name('user_sign')->where(['user_id' => $user_id])->find();
            if (!$checkSign) {
                $result = $userLogic->addUserSign($user_id, $date);            //第一次签到
            } else {
                $result = $userLogic->updateUserSign($checkSign, $date);       //累计签到
            }
            $return = ['status' => $result['status'], 'msg' => $result['msg'], 'result' => ''];
        } else {
            $return = ['status' => false, 'msg' => '该功能未开启！', 'result' => ''];
        }
        $this->ajaxReturn($return);
    }


    /**
     * vip充值
     */
    public function rechargevip(){
        $paymentList = M('Plugin')->where("`type`='payment' and code!='cod' and status = 1 and  scene in(0,1)")->select();
        //微信浏览器
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            $paymentList = M('Plugin')->where("`type`='payment' and status = 1 and code='weixin'")->select();
        }
        $paymentList = convert_arr_key($paymentList, 'code');

        foreach ($paymentList as $key => $val) {
            $val['config_value'] = unserialize($val['config_value']);
            if ($val['config_value']['is_bank'] == 2) {
                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
            }
        }
        $bank_img = include APP_PATH . 'home/bank.php'; // 银行对应图片
        $payment = M('Plugin')->where("`type`='payment' and status = 1")->select();
        $this->assign('paymentList', $paymentList);
        $this->assign('bank_img', $bank_img);
        $this->assign('bankCodeList', $bankCodeList);
        return $this->fetch();
    }


    /**
     * 个人海报推广二维码 （我的名片）
     */
    public function qr_code()
    {
        $user_id = $this->user['user_id'];
        if (!$user_id) {
            return $this->fetch();
        }
        //判断是否是分销商
        $user = M('users')->where('user_id', $user_id)->find();
//        if (!$user && $user['is_distribut'] != 1) {
//            return $this->fetch();
//        }

        //判断是否存在海报背景图
        if(!DB::name('poster')->where(['enabled'=>1])->find()){
            echo "<script>alert('请上传海报背景');</script>";
            return $this->fetch();
        }

        //分享数据来源
        $shareLink = urlencode("http://{$_SERVER['HTTP_HOST']}/index.php?m=Mobile&c=Index&a=index&first_leader={$user['user_id']}");

        $head_pic = $user['head_pic'] ?: '';
        if ($head_pic && strpos($head_pic, 'http') !== 0) {
            $head_pic = '.'.$head_pic;
        }

        $this->assign('user',  $user);
        $this->assign('head_pic', $head_pic);
        $this->assign('ShareLink', $shareLink);
        return $this->fetch();
    }

    // 用户海报二维码
    public function poster_qrcode()
    {
        ob_end_clean();
        vendor('topthink.think-image.src.Image');
        vendor('phpqrcode.phpqrcode');

        error_reporting(E_ERROR);
        $url = isset($_GET['data']) ? $_GET['data'] : '';
        $url = urldecode($url);

        $poster = DB::name('poster')->where(['enabled'=>1])->find();
        define('IMGROOT_PATH', str_replace("\\","/",realpath(dirname(dirname(__FILE__)).'/../../'))); //图片根目录（绝对路径）
        $project_path = '/public/images/poster/'.I('_saas_app','all');
        $file_path = IMGROOT_PATH.$project_path;

        if(!is_dir($file_path)){
            mkdir($file_path,777,true);
        }

        $head_pic = input('get.head_pic', '');                   //个人头像
        $back_img = IMGROOT_PATH.$poster['back_url'];            //海报背景
        $valid_date = input('get.valid_date', 0);                //有效时间

        $qr_code_path = UPLOAD_PATH.'qr_code/';
        if (!file_exists($qr_code_path)) {
            mkdir($qr_code_path,777,true);
        }

        /* 生成二维码 */
        $qr_code_file = $qr_code_path.time().rand(1, 10000).'.png';
        \QRcode::png($url, $qr_code_file, QR_ECLEVEL_M,1.8);

        /* 二维码叠加水印 */
        $QR = Image::open($qr_code_file);
        $QR_width = $QR->width();
        $QR_height = $QR->height();

        /* 添加头像 */
        if ($head_pic) {
            //如果是网络头像
            if (strpos($head_pic, 'http') === 0) {
                //下载头像
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL, $head_pic);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                $file_content = curl_exec($ch);
                curl_close($ch);
                //保存头像
                if ($file_content) {
                    $head_pic_path = $qr_code_path.time().rand(1, 10000).'.png';
                    file_put_contents($head_pic_path, $file_content);
                    $head_pic = $head_pic_path;
                }
            }
            //如果是本地头像
            if (file_exists($head_pic)) {
                $logo = Image::open($head_pic);
                $logo_width = $logo->height();
                $logo_height = $logo->width();
                $logo_qr_width = $QR_width / 4;
                $scale = $logo_width / $logo_qr_width;
                $logo_qr_height = $logo_height / $scale;
                $logo_file = $qr_code_path.time().rand(1, 10000);
                $logo->thumb($logo_qr_width, $logo_qr_height)->save($logo_file, null, 100);
                $QR = $QR->thumb($QR_width, $QR_height)->water($logo_file, \think\Image::WATER_CENTER);
                $logo_file && unlink($logo_file);
            }
            $head_pic_path && unlink($head_pic_path);
        }

        if ($valid_date && strpos($url, 'weixin.qq.com') !== false) {
            $QR = $QR->text('有效时间 '.$valid_date, "./vendor/topthink/think-captcha/assets/zhttfs/1.ttf", 7, '#00000000', Image::WATER_SOUTH);
        }
        $QR->save($qr_code_file, null, 100);

        $canvas_maxWidth = $poster['canvas_width'];
        $canvas_maxHeight = $poster['canvas_height'];
        $info = getimagesize($back_img);                                                           //取得一个图片信息的数组
        $im = checkPosterImagesType($info,$back_img);                                              //根据图片的格式对应的不同的函数
        $rate_poster_width = $canvas_maxWidth/$info[0];                                            //计算绽放比例
        $rate_poster_height = $canvas_maxHeight/$info[1];
        $maxWidth =  floor($info[0]*$rate_poster_width);
        $maxHeight = floor($info[1]*$rate_poster_height);                                          //计算出缩放后的高度
        $des_im = imagecreatetruecolor($maxWidth,$maxHeight);                                      //创建一个缩放的画布
        imagecopyresized($des_im,$im,0,0,0,0,$maxWidth,$maxHeight,$info[0],$info[1]);              //缩放
        $news_poster = $file_path.'/'.createImagesName() . ".png";                                 //获得缩小后新的二维码路径
        inputPosterImages($info,$des_im,$news_poster);                                             //输出到png即为一个缩放后的文件
        $QR = imagecreatefromstring(file_get_contents($qr_code_file));
        $background_img = imagecreatefromstring ( file_get_contents ( $news_poster ) );

        imagecopyresampled ( $background_img, $QR,$poster['canvas_x'],$poster['canvas_y'],0,0,80,92,80, 78 );      //合成图片
        $result_png = '/'.createImagesName(). ".png";
        $file = $file_path . $result_png;
        imagepng ($background_img, $file);                                                          //输出合成海报图片
        $final_poster = imagecreatefromstring ( file_get_contents (  $file ) );                     //获得该图片资源显示图片
        header("Content-type: image/png");
        imagepng ( $final_poster);
        imagedestroy( $final_poster);
        $news_poster && unlink($news_poster);
        $qr_code_file && unlink($qr_code_file);
        $file && unlink($file);
        exit;
    }
    /**
     * 奖金币互转
     * */
    public function  between()
    {
        if (IS_POST) {
            $data = I('post.');
            $data['user_id'] = $this->user_id;
            $data['create_time'] = time();
            //接收方id
            $userid = Db::name('users')->where('user_id',$data['accept_id'])->find();

            if($userid){
                $accept_id = $data['accept_id'];
            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>'用户不存在']);
                exit;
            }
            //转出多少奖金币
            if($data['turnhow']%100 !== 0)
            {
                $this->ajaxReturn(['status'=>0,'msg'=>"转赠金额必须为100的倍数"]);
                exit;
            }
            $turnhow = $data['turnhow'];
            $payp = Db::name('users')->where('user_id',$this->user_id)->field('pay_points')->find();

            $balances = $turnhow + $userid['pay_points'];
            $balance = $payp['pay_points']-$turnhow;

            if ($payp['pay_points'] > $turnhow){

                // 启动事务
                Db::startTrans();
                try {
                    $res1 = Db::name('users')->where('user_id',$accept_id)->setInc('pay_points',$turnhow);
                    $result = Db::execute("update tp_users set pay_points = pay_points-$turnhow where user_id = $this->user_id ");

                    //赠出奖金币记录
                    $res = Db::name('menber_balance_log')->insert([
                        'user_id' => $this->user_id,
                        'balance_type' => 0,
                        'log_type' => 1,
//                        'source_type' => 5,
//                        'source_id' => $data['refund_sn'],
                        'money' => $turnhow,
                        'old_balance' => $payp['pay_points'],
                        'balance' => $balance,
                        'create_time' => time(),
                        'note' => '赠送奖金币'
                    ]);
                    if(!$res){
                        Db::rollback();
                        return false;
                    }

                    //收到奖金币记录
                    $res = Db::name('menber_balance_log')->insert([
                        'user_id' => $accept_id,
                        'balance_type' => 0,
                        'log_type' => 1,
//                        'source_type' => 5,
//                        'source_id' => $data['refund_sn'],
                        'money' => $turnhow,
                        'old_balance' => $userid['pay_points'],
                        'balance' => $balances,
                        'create_time' => time(),
                        'note' => '收到奖金币'
                    ]);
                    if(!$res){
                        Db::rollback();
                        return false;
                    }
                    // 提交事务
                    Db::commit();
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                }
                $this->ajaxReturn(['status'=>1,'msg'=>"转赠成功"]);
            }else{
                $this->ajaxReturn(['status'=>0,'msg'=>"奖金币余额不足"]);
                exit;
            }

        }

        return $this->fetch();
    }

}
>>>>>>> 9a05dee4f7842a2a154ada0b9aec075ed020f85f
