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
 * $Author: 当燃 2016-01-09
 */
namespace app\mobile\controller;

use Think\Db;
use app\common\logic\wechat\WechatUtil;

class Bonus extends MobileBase {

    public function bonus_action()
    {
        $data = [
            'order_id'=>1,
            'goods_id'=>1,
            'user_id' =>8836,
            'money'=>29800,
            'shop_price'=>39800,
            'nums'=>1
        ];
        //设置参数
        if(!$this->setParams($data)) return true;
        $this->bonus($data);      	 		
    }

    //设置参数
    private function setParams($data)
    {
        $this->order_id    = $data['order_id']; //订单id
        $this->goods_id    = $data['goods_id']; //商品id
        $this->user_id     = $data['user_id'];//用户id
        $this->money       = $data['money']; //购买价格
        $this->shop_price  = $data['shop_price']; //原价
        // $this->gold_card_price           = $data['gold_card_price'];//金卡会员价格
        // $this->brick_card_price          = $data['brick_card_price'];//钻石会员价格
        // $this->fine_brick_card_price     = $data['fine_brick_card_price'];//精钻会员价格
        $this->nums                      = $data['nums'];//商品数量
        return true;
    } 

    private function bonus()
    {
        //获取返佣人信息
        $users = $this->reward_leve( $this->user_id);
        $level = 0;
        foreach($users as $key => $vale)
        {
            if($key<=0) continue;
           
            if($vale['level']>=$level){
                //二级返佣
                $this->second_level($vale);                
            }
            //直推极差价
            if($key==1){

                if($vale['level']<1) continue;
                $this->bonus_range($vale);
            }
            $level = $vale['level'];
            
        }
        
    }
    private function account_log($data){
        $accountLogData = [
            'user_id' => $this->user_id,
            'user_money' => $data['money'],
            'change_time' => time(),
            'desc' => $data['desc'],
            'type'=>$data['type'],
            'order_id'=>$this->order_id,
        ];
        $afel = Db::name('account_log')->insert($accountLogData);
        if($afel){
            return $afel;
        }
    }
    private function bonus_range($data){
        //返佣金额
        $money = $this->shop_price-$this->money;
        if($money<=0) return false;
        //操作用户余额
        $user_bonus = $this->user_bonus($money,$data['user_id']);
        if($user_bonus){
            $account_log_arr = [
                'money'=>$money,
                'desc'=>"用户【'".$this->user_id."'】购买礼包上级【'".$data['user_id']."'】获得差价'".$money."'元",
                'type'=>23
            ];
            $this->account_log($account_log_arr);
            return $user_bonus;
        }
    }

    //二级分佣
    private function second_level($data){

        $level_bonus = $this->level_bonus($data['level']);
        //返佣金额
        $money = $this->money*$level_bonus['ratio']/100;
        //操作用户余额
        $user_bonus = $this->user_bonus($money,$data['user_id']);
        if($user_bonus){
            $account_log_arr = [
                'money'=>$money,
                'desc'=>"用户【'".$this->user_id."'】购买礼包上级【'".$data['user_id']."'】获得返佣'".$money."'元",
                'type'=>22
            ];
            $this->account_log($account_log_arr);
            return $user_bonus;
        }
    }

    //增加用户余额
    private function user_bonus($money,$user_id){
        $user_money_bonus = Db::name('users')->where(['user_id'=>$user_id])->setInc('user_money_bf',$money);
        if($user_money_bonus){
            return $user_money_bonus;
        }
    }

    //获取等级比例
    private function level_bonus($level){
        $field = "level_name, level,ratio";
        $agent_level = M('agent_level')->field($field)->where(['level' => $level])->find();
        if($agent_level)
        {
            return $agent_level;
        }
    }

    public function reward_leve($user_id,$num = 0,&$userList = array()){

		$num += 1;
        $field = "user_id, first_leader, level,parents_cache_vip,gift_pack_1,gift_pack_2,gift_pack_3,set_up_shop";
        $UpInfo = M('users')->field($field)->where(['user_id' => $user_id])->find();
        
		$first_leader = $UpInfo['first_leader'];
		
		if($first_leader < 0 || $num > 3){
			return true;
        }
        if ($UpInfo)  //有上级
        {
            $userList[] = $UpInfo;
            $this->reward_leve($first_leader,$num,$userList);
        }   
        return $userList;
		
	}
	
}