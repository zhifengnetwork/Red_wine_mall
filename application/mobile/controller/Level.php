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

class Level extends MobileBase {
    /**
	 * 判断一个人是否可以升级
	 *  下级
	 */
    public function index(){

        $user_id = 8852;
        $user = Db::name('users')->where('user_id',$user_id)->field('user_id,first_leader,level')->find();
		if($user['level'] == 3 ){
            return true;
        }
        
        //获取用户所有上级
       $user_ids =  get_uper_user($user['user_id']);
       
       foreach($user_ids['recUser'] as $key=>$vale)
       {
            $this->check_can_upgrade($vale);  
       }
    }

	/**
	 * 代理升级
	 */
	private function check_can_upgrade($user)
	{
		//升级 下一级  需要 的条件
        if($user['level']==0){
            $flag1 = $this->get_order_lower($user['user_id']);
            if(!$flag1 || $user['parents_cache_vip']!=1)
            {
                return false;
            }
        }else if($user['level']==1){
           $flag1 = $this->get_order_lower($user['user_id']);
           $flag2 = $this->get_user($user['user_id']);
           if(!$flag1 || !$flag2){
                return false;
           }
        }else if($user['level']==2){
            $flag1 = $this->get_order_nums($user['user_id']);
            if(!$flag1 || $user['set_up_shop']!=1)
            {
                return false;
            }
        }
        $level = $user['level']+1;
        $data = ['level'=>$level];
        $r = DB::name('users')->where(['user_id'=>$user['user_id']])->update($data);
        return $r;   
    }

    //获取订单条数
    private function get_order_nums($user_id){
        $get_lower = Db::name('users')->where(['first_leader'=>$user_id])->field('user_id, first_leader, level,parents_cache_vip')->select();
        $nums_order = 0;
        foreach($get_lower as $key=>$vale){
            $order = Db::name('order')->where(['user_id'=>$vale['user_id']])->count();
            $nums_order +=$order;
        }
        if($nums_order>=100){
            return true;
        }
        
    }
    //下级是否推荐5个会员以上
    private function get_user($user_id){
        $get_lower = Db::name('users')->where(['first_leader'=>$user_id,'parents_cache_vip'=>1])->field('user_id, first_leader, level,parents_cache_vip,gift_pack_1,gift_pack_2,gift_pack_3')->count();
        if($get_lower>=5){
            return true;
        }
    }

    //判断直推会员是否买过商品礼包
    private function get_order_lower($user_id)
    {
        $get_lower = Db::name('users')->where(['first_leader'=>$user_id])->field('user_id, first_leader, level,parents_cache_vip,gift_pack_1,gift_pack_2,gift_pack_3')->select();
        
        foreach($get_lower as $key=>$vale){
            if(isset($vale['gift_pack_1']) || isset($vale['gift_pack_2']) || isset($vale['gift_pack_3'])){
                return true;
            }
            // $order = Db::name('order')->where(['user_id'=>$vale['user_id']])->count();
            // if(empty($order)){
            //     return true;
            // }
        }
        
    }

	
}