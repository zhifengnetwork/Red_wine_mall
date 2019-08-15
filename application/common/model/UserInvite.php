<?php

/**
 * 用户邀请新用户注册送佣金
 * @author Rock
 * @date 2019/03/22
 */


namespace app\common\model;

use think\Db;
use think\Model;

class UserInvite extends Model
{

    // config 名称
    public $conf_name = 'user_invite_rule';
    // config 
    public $conf_inc_type = 'user_invite_rule';
    // 网站设置
    public $config = [];
    // 邀新送佣金设置
    public $invite_conf = [];
    // 邀新送佣金开关 0 关闭  1 开启
    public $invite_on_off = 0;
    // 奖励规则
    public $rule = [];

    function __construct()
    {
        // 实例化时，初始化类
        $this->custo_init();
    }

    // 自定义初始化
    public function custo_init()
    {
        $config = Db::query("select * from `tp_config` where `name` = '" . $this->conf_inc_type . "' and `inc_type` = '" . $this->conf_inc_type . "'");
        if ($config) {
            $config = $config[0];
            $this->invite_conf = $config['value'] = json_decode($config['value'], true);
            $this->invite_on_off = $config['value']['invite_on_off'];
            $this->rule = $config['value']['rule'];
            $this->config = $config;
        } else {
            return false;
        }
    }


    /**
     * 当前用户主动调用程序
     */
    public function user_invite($user_id = 0)
    {
        $user_id = intval($user_id);
        $info = Db::name('users')->field('first_leader')->where('user_id', $user_id)->find();

        if ($info && $info['first_leader']) {

            $this->invite($info['first_leader'], $user_id);
        }
    }

    //推荐
     public function recommend($share_user, $user_id)
     {
         //获取上级id
 
        //    $recommend_id=1;
         $recommend_id = $share_user;
        //  $user_id=6;

         $myInfo = Db::name('users')->where('user_id', '=', $user_id)->find();
 
         if ($myInfo['user_id'] == $recommend_id) {
             return false;
             die;
         }
 
         $allLower = get_all_lower($user_id);
         if (in_array($recommend_id, $allLower)) {
             return false;
             die;
         }
 
         $time=time();
         // $firstUpdate=Db::name('users')->update(['user_id'=>$user_id,'first_leader'=>$recommend_id]);
 
         //推荐成功 统计上级的直属下级数量 更新上级的身份  经理还是总监
         $upPopCount=Db::name('users')->where('first_leader','=',$recommend_id)->count(); 
         $upPopPerformance=Db::name('agent_performance')->where('user_id','=',$recommend_id)->value("agent_per");  //现在上级的团队业绩情况
         //根据上级的推荐人数和上级团队业绩更新上级身份
       $manager_ind_sum=$this->popUpdateCondition(1);  //升级经理的条件
       $chief_ind_sum=$this->popUpdateCondition(2);    //升级总监的条件
       $ceo_ind_sum=$this->popUpdateCondition(3);
       $partner_ind_sum=$this->popUpdateCondition(4);
         if($upPopCount>=$manager_ind_sum["ind_goods_sum"]&&$upPopCount<$chief_ind_sum["ind_goods_sum"]&&$upPopPerformance>=$manager_ind_sum['describe']){
             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>1]);
         }
         if($upPopCount>=$chief_ind_sum["ind_goods_sum"]&&$upPopCount<$ceo_ind_sum["ind_goods_sum"]&&$upPopPerformance>=$chief_ind_sum['describe']){
             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>2]);
         }
         if($upPopCount>=$ceo_ind_sum["ind_goods_sum"]&&$upPopCount<$partner_ind_sum["ind_goods_sum"]&&$upPopPerformance>=$ceo_ind_sum['describe']){
             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>3]);
         }
         if($upPopCount>=$partner_ind_sum["ind_goods_sum"]&&$upPopPerformance>=$partner_ind_sum['describe']){
             Db::name('users')->where('user_id','=',$recommend_id)->update(['leader_level'=>4]);
         }
 
 
         // if($firstUpdate){
                               // return $this->success("直属上级推荐成功");
             $recommendInfo=Db::name('users')->where('user_id',$recommend_id)->find();
                 if($recommendInfo['agent_level']){
                     //如果上级是代理身份,就给上级奖励   //并减少对应的推广额度
 
                     //这里有个条件前提   周数和业绩   不同要求不一样
                         $pop_commission=Db::name('config')->where('name','=','pop_commission')->value('value');
                         $pop_money=Db::name('config')->where('name','=','pop_money')->value('value');
                         $addmoney=$pop_money*$pop_commission/100;
                         $minusmoney=$pop_money-$addmoney;
                         $user_money=$recommendInfo['user_money']+$addmoney;
                    
                         $whereStr['user_id']=['=',$recommendInfo['user_id']];
                         $whereStr['period']=['=',$recommendInfo['default_period']];
                         $periodInfo=Db::name('pop_period')->where($whereStr)->find();
                         if($periodInfo['begin_time']){  //如果时间已经开始再操作下面

                             if($periodInfo['poped_per_num']<$periodInfo['person_num']){ //还有位置就操作
                                if($periodInfo['poped_per_num']+1==$periodInfo['person_num']){
                                    $upPeriod=$recommendInfo['default_period']+1; 
                                    $upPeriodInfo=Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->find();
                                    if($upPeriodInfo){ //如果有上级
                                        //还有下一期的话 分情况   一周内 和一周外
                                        if(($periodInfo['begin_time']+3600*24*7)>$time){
                                                Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['week_release'=>1]);
                                        }else{
                                                Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['day_release'=>1]);
                                        }
                                        Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$recommendInfo['default_period'])->update(['end_time'=>$time]);
                                    }else{
                                        Db::name('users')->where('user_id','=',$recommendInfo['user_id'])->update(['agent_level'=>0,'default_period'=>0,'add_agent_time'=>0]);
                                        Db::name('pop_period')->where("user_id", '=', $recommendInfo['user_id'])->delete();
                                    }
                                }
                                $ind_res=$periodInfo['poped_per_num']+1;
                                Db::name('pop_period')->where($whereStr)->update(['poped_per_num'=>$ind_res]);
                                Db::name('users')->update(['user_id'=>$recommend_id,'user_money'=>$user_money]);
                                Db::name('account_log')->insert(['user_id'=>$recommend_id,'user_money'=>$pop_money,'pay_points'=>$addmoney,'change_time'=>$time,'desc'=>"邀请1个新会员【{$myInfo['nickname']}-id:{$myInfo['user_id']}】奖励50",'type'=>2]);
                                Db::name('account_log')->insert(['user_id'=>$recommend_id,'user_money'=>$minusmoney,'pay_points'=>$minusmoney,'change_time'=>$time,'desc'=>"邀请1个新会员【{$myInfo['nickname']}-id:{$myInfo['user_id']}】平台扣除手续费{$minusmoney}",'type'=>7]);
                                
                                //  Db::name('pop_period')->where($whereStr)->setInc('poped_per_num');
                             }else{ //没有位置就跳到上一级    如果没有上一级就修改用户表    
                                 $upPeriod=$recommendInfo['default_period']+1; 
                                 $upPeriodInfo=Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->find();
                                 if($upPeriodInfo){ //如果有上级
                                     //还有下一期的话 分情况   一周内 和一周外
                                     if(($periodInfo['begin_time']+3600*24*7)>$time){
                                             Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['week_release'=>1]);
                                     }else{
                                             Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$upPeriod)->update(['day_release'=>1]);
                                     }
                                     Db::name('pop_period')->where('user_id','=',$recommendInfo['user_id'])->where('period','=',$recommendInfo['default_period'])->update(['end_time'=>$time]);
                                 }else{
                                     Db::name('users')->where('user_id','=',$recommendInfo['user_id'])->update(['agent_level'=>0,'default_period'=>0,'add_agent_time'=>0]);
                                     Db::name('pop_period')->where("user_id", '=', $recommendInfo['user_id'])->delete();
                                 }
                             }




                            
                         }
                        
                 }                
 
         // }
     }
 
      //会员升级条件     //符合推广人数   就升级 如：经理
      public function popUpdateCondition($levelNum)
      {
        // $ind_goods_sum=Db::name('agent_level')->where('level','=',$levelNum)->value("ind_goods_sum");
        $ind_goods_sum=Db::name('agent_level')->where('level','=',$levelNum)->find();
        return $ind_goods_sum;
      }


    /**
     * @param $user_id  邀请人用户ID
     * @param $adduser_id   被邀请的新用户ID
     */
    public function invite($user_id = 0, $adduser_id = 0)
    {
        // write_log('邀请人id'. $user_id );
        // write_log('被邀请人id'. $adduser_id );
        $user_id = intval($user_id);
        $adduser_id = intval($adduser_id);

        if (!$user_id || !$adduser_id || !$this->invite_on_off || !$this->rule) {
            return false;
        }


        $addlog = Db::name('commission_log')->where('add_user_id', $adduser_id)->count();
        if ($addlog) {
            return false;
        }

        $rule = $this->rule;
        $num = 1;
        $money = 0.1;
        $time = time();
        $desc = '邀请第' . $num . '个新会员奖励' . $money;
        $this->recommend($user_id, $adduser_id);
        $log = Db::name('commission_log')->where(['user_id' => $user_id, 'identification' => 2])->field('`num`')->order('id desc')->find();
        if ($log) {
            $num   = $log['num'] + 1;
            if (!empty($rule[$num]) && $rule[$num] > 0) {
                $money = $rule[$num] + 0.1;
            }
            $desc = '邀请第' . $num . '个新会员奖励' . $money;
        }
        // write_log('奖励金额'. $money );
        if ($money) {
            $insql = "insert into `tp_commission_log` (`user_id`,`add_user_id`,`identification`,`num`,`money`,`addtime`,`desc`) values ";
            $insql .= " ('$user_id','$adduser_id','2','$num','$money','$time','$desc')";
            $res = Db::execute($insql);
            // write_log('记录用户余额变动bool'. $res );
            if ($res) {
                // write_log('记录用户余额变动'. $user_id );
                Db::execute("update `tp_users` set `user_money` = `user_money` + '$money', `distribut_money` = `distribut_money` + '$money' where `user_id` = '$user_id'");
                //记录用户余额变动
                $user_money = Db::name('users')->where(['user_id' => $user_id])->value('user_money');
                setBalanceLog($user_id, 2, $money, $user_money, '邀请奖励：' . $money);
                return true;
            }
        }
        return false;
    }
}
