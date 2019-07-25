<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * 采用最新Thinkphp5助手函数特性实现单字母函数M D U等简写方式
 * ============================================================================
 * Author: dyr
 * Date: 2017-12-04
 */

namespace app\common\logic;
use app\common\logic\team\Team;
use app\common\model\CouponList;
use app\common\model\Order;
use app\common\model\PreSell;
use app\common\model\ShopOrder;
use app\common\model\Users;
use app\common\util\TpshopException;
use think\Cache;
use think\Hook;
use think\Model;
use think\Db;
/**
 * 提交下单类
 * Class CatsLogic
 * @package Home\Logic
 */
class PlaceOrder
{
    private $invoiceTitle;
    private $userNote;
    private $taxpayer;
    private $invoiceDesc;
    private $pay;
    private $order;
    private $userAddress;
    private $payPsw;
    private $promType;
    private $promId;
    private $consignee;
    private $mobile;
    private $shop;
    private $take_time;
    private $preSell;

    /**
     * PlaceOrder constructor.
     * @param Pay $pay
     */
    public function __construct(Pay $pay)
    {
        $this->pay = $pay;
        $this->order = new Order();
    }

    public function addNormalOrder()
    {
        $this->check();
        $this->queueInc();
        $this->addOrder();
        $this->addOrderGoods();
        $this->addShopOrder();
        Hook::listen('user_add_order', $this->order);//下单行为
        $reduce = tpCache('shopping.reduce');
        if($reduce== 1 || empty($reduce)){
            minus_stock($this->order);//下单减库存
        }

        // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
        if ($this->order['order_amount'] == 0) {
            update_pay_status($this->order['order_sn']);
            //设置推广用户名额
            $this->set_pop_person($order['order_sn']);
            //购买后增加自己的业绩和团队的业绩
            $this->add_agent_performance($order['order_sn']);
        }
        
        $this->deductionCoupon();//扣除优惠券
        $this->changUserPointMoney($this->order);//扣除用户积分余额
        $this->queueDec();
    }

    
    public function add_agent_performance($order_sn)
    {
        //添加自己本身的业绩
        $order=Db::name('order')
        ->alias('or')
        ->join('order_goods og','or.order_id=og.order_id',LEFT)
        ->join('goods g',"g.goods_id=og.goods_id",LEFT)
        ->field('g.agent_good,or.total_amount,or.user_id')
        ->where('or.order_sn','=',$order_sn)->find();

        $upArr=get_uper_user($order['user_id']);
        foreach($upArr['recUser'] as $k=>$v){
                $user_agent=Db::name('agent_performance')->where('user_id','=', $v['user_id'])->find();
                $time=time(); 
                $ind_per=$user_agent['ind_per']+$order['total_amount'];
            if($order['user_id']==$v['user_id']){
                if($user_agent){    
                    Db::name('agent_performance')->where('performance_id','=',$user_agent['performance_id'])->update(['user_id'=>$v['user_id'],'ind_per'=>$ind_per,'update_time'=>$time]);
                }else{
                    Db::name('agent_performance')->insert(['user_id'=>$v['user_id'],'ind_per'=>$ind_per,'agent_per'=>0,'create_time'=>$time]);
                }
            }else{
                $agent_per=$user_agent['agent_per']+$order['total_amount'];
                if($user_agent){    
                    Db::name('agent_performance')->where('performance_id','=',$user_agent['performance_id'])->update(['user_id'=>$v['user_id'],'agent_per'=>$agent_per,'update_time'=>$time]);
                }else{
                    Db::name('agent_performance')->insert(['user_id'=>$v['user_id'],'agent_per'=>$agent_per,'create_time'=>$time]);
                }
            }
        }
    }

    public function set_pop_person($order_sn)
    {   
            // $order_sn='201907161126392756';
            $confModel = M('config');
            $order=Db::name('order')
            ->alias('or')
            ->join('order_goods og','or.order_id=og.order_id',LEFT)
            ->join('goods g',"g.goods_id=og.goods_id",LEFT)
            ->field('g.agent_good,or.user_id')
            ->where('or.order_sn','=',$order_sn)->find();
   
        if($order['agent_good']){  //是代理
            //判断购买代理商品的用户本身是不是代理，如果是代理就不能再买
             $user_agent_info=Db::name('users')->where('user_id','=',$order['user_id'])->field('agent_level')->find();
                if($user_agent_info['agent_level']){
                    $this->ajaxReturn(['status' => -1, 'msg' => '用户是代理身份不能重复购买']);
             }
            $time=time();
            Db::name('users')->update(['user_id'=>$order['user_id'],'agent_level'=>$order['agent_good'],'default_period'=>1,'add_agent_time'=>$time]);

            $pop_num_area= $confModel->where('name','=','pop_num_area')->value('value');
            $pop_num_city= $confModel->where('name','=','pop_num_city')->value('value');
            $pop_num_province= $confModel->where('name','=','pop_num_province')->value('value');
             if($order['agent_good']==1){
                 $pop_name='pop_person_num';
                 $pop_num=$pop_num_area;
             }
             if($order['agent_good']==2){
                $pop_name='pop_person_num_city';
                $pop_num=$pop_num_city;
             }
             if($order['agent_good']==3){
                $pop_name='pop_person_num_province';
                $pop_num=$pop_num_province;
             }
            $pop_person_num=Db::name('config')->where(['name' => $pop_name])->value('value');
            $period_count=ceil($pop_person_num/$pop_num);
            static $current_num='';
            $current_num=$pop_person_num;
            $popPeriodModel=Db::name('pop_period');
           for($i=1;$i<=$period_count;$i++){
                if($current_num>$pop_num){
                    $current_num-=$pop_num;
                    if($i==1){
                        $popPeriodModel->insert(['user_id'=>$order['user_id'],'person_num'=>$pop_num,'poped_per_num'=>0,'period'=>$i,'level'=>$order['agent_good'],'begin_time'=>$time,'end_time'=>'']);
                    }else{
                        $popPeriodModel->insert(['user_id'=>$order['user_id'],'person_num'=>$pop_num,'poped_per_num'=>0,'period'=>$i,'level'=>$order['agent_good'],'begin_time'=>'','end_time'=>'']);
                    }
                }else{
                    $popPeriodModel->insert(['user_id'=>$order['user_id'],'person_num'=>$current_num,'poped_per_num'=>0,'period'=>$i,'level'=>$order['agent_good'],'begin_time'=>'','end_time'=>'']);
                }
           }

            //升级奖励上级和上上级
            $this->pay_leader($order['user_id'],$order['agent_good']);
        }
    }

    //晋升为县代奖励上级
public function pay_leader($userid,$agent_level)
{
    $userModel=Db::name('users');
    $accountLogModel=Db::name('account_log');
    $achievement=Db::name('order')->where('user_id','=',$userid)->sum('total_amount');

    //判断当前用户的生份    切换对应的代理级别  百分比
    $county_bonus=$this->first_agent_persent($agent_level);   //下级升级县级奖励百分比   市级一样
    $sec_county_bonus=$this->sec_agent_persent($agent_level);  //二级下级升级县级奖励百分比

    $user=$userModel->where('user_id','=',$userid)->find();
    $firstLeader=Db::name('users')->where('user_id','=',$user['first_leader'])->find();
    $time=time();
    if($user['first_leader']){
        $firstAch=Db::name('order')->where('user_id','=',$firstLeader['user_id'])->sum('total_amount');

        //上一级的区域代理金额自身要求
        $bonus_position_firs=$this->get_bonus_position($firstLeader['agent_level']);
        $five_times=$this->week_times(5);
        $six_times=$this->week_times(6);

        $agentLevelList=array('1'=>'县级','2'=>'市级','3'=>'省级');
        $agentLevel=$agentLevelList[$agent_level];

           //4周内
        if($this->in_four_week($firstLeader['add_agent_time'])){
                //插入上级
                $firstBonus=$achievement*$county_bonus/100;
                $distribut_money=$firstLeader['distribut_money']+$firstBonus;
                $userModel->update(['user_id'=>$firstLeader['user_id'],'distribut_money'=>$distribut_money]);
                
                $accountLogModel->insert(['user_id'=>$firstLeader['user_id'],'user_money'=>$firstBonus,'pay_points'=>0,'change_time'=>$time,'desc'=>"下级用户【'".$user['user_id']."'】晋升为".$agentLevel."奖励",'type'=>3]);
        }
        //5周
        if($this->in_five_week($firstLeader['add_agent_time'])){
            if($firstAch>$five_times*$bonus_position_firs){
                $firstBonus=$achievement*$county_bonus/100;
                $distribut_money=$firstLeader['distribut_money']+$firstBonus;
                $userModel->update(['user_id'=>$firstLeader['user_id'],'distribut_money'=>$distribut_money]);
                
                $accountLogModel->insert(['user_id'=>$firstLeader['user_id'],'user_money'=>$firstBonus,'pay_points'=>0,'change_time'=>$time,'desc'=>"下级用户【'".$user['user_id']."'】晋升为".$agentLevel."奖励",'type'=>3]);
            }
        }
        //6周后
        if($this->after_six_week($firstLeader['add_agent_time'])){
            if($firstAch>$six_times*$bonus_position_firs){
                $firstBonus=$achievement*$county_bonus/100;
                $distribut_money=$firstLeader['distribut_money']+$firstBonus;
                $userModel->update(['user_id'=>$firstLeader['user_id'],'distribut_money'=>$distribut_money]);
                $accountLogModel->insert(['user_id'=>$firstLeader['user_id'],'user_money'=>$firstBonus,'pay_points'=>0,'change_time'=>$time,'desc'=>"下级用户【'".$user['user_id']."'】晋升为".$agentLevel."奖励",'type'=>3]);
            }
        }

        $secondLeader= $userModel->where('user_id','=',$firstLeader['first_leader'])->find();
        if($secondLeader['user_id']){
            $bonus_position_sec=$this->get_bonus_position($secondLeader['agent_level']);
            $secAch=Db::name('order')->where('user_id','=',$secondLeader['user_id'])->sum('total_amount');
            //4周内
            if($this->in_four_week($secondLeader['add_agent_time'])){
                    // 插入二级上级
                    $secondBonus=$achievement*$sec_county_bonus/100;
                    $sec_distribut_money=$secondLeader['distribut_money']+$secondBonus;
                    $userModel->update(['user_id'=>$secondLeader['user_id'],'distribut_money'=>$sec_distribut_money]);
                    $accountLogModel->insert(['user_id'=>$secondLeader['user_id'],'user_money'=>$secondBonus,'pay_points'=>0,'change_time'=>$time,'desc'=>"下级用户【'".$user['user_id']."'】晋升为".$agentLevel."奖励",'type'=>4]);
            }
            //五周内
            if($this->in_five_week($secondLeader['add_agent_time'])){
                if($secAch>$five_times*$bonus_position_sec){
                    $secondBonus=$achievement*$sec_county_bonus/100;
                    $sec_distribut_money=$secondLeader['distribut_money']+$secondBonus;
                    $userModel->update(['user_id'=>$secondLeader['user_id'],'distribut_money'=>$sec_distribut_money]);
                    $accountLogModel->insert(['user_id'=>$secondLeader['user_id'],'user_money'=>$secondBonus,'pay_points'=>0,'change_time'=>$time,'desc'=>"下级用户【'".$user['user_id']."'】晋升为".$agentLevel."奖励",'type'=>4]);
                }
            }
              //6周后
         if($this->after_six_week($secondLeader['add_agent_time'])){
            if($secAch>$six_times*$bonus_position_sec){
                $secondBonus=$achievement*$sec_county_bonus/100;
                $sec_distribut_money=$secondLeader['distribut_money']+$secondBonus;
                $userModel->update(['user_id'=>$secondLeader['user_id'],'distribut_money'=>$sec_distribut_money]);
                $accountLogModel->insert(['user_id'=>$secondLeader['user_id'],'user_money'=>$secondBonus,'pay_points'=>0,'change_time'=>$time,'desc'=>"下级用户【'".$user['user_id']."'】晋升为".$agentLevel."奖励",'type'=>4]);
            }
         }
     }
    }
}


public function first_agent_persent($agent_level){
    if($agent_level==1){
        $county_bonus=Db::name('config')->where('name','=','county_bonus')->value('value');   
    }
    if($agent_level==2){
        $county_bonus=Db::name('config')->where('name','=','county_bonus_city')->value('value');  
    }
    if($agent_level==3){
        $county_bonus=Db::name('config')->where('name','=','county_bonus_province')->value('value');   
    }
    return $county_bonus;
}

public function sec_agent_persent($agent_level){
    if($agent_level==1){
        $sec_county_bonus=Db::name('config')->where('name','=','sec_county_bonus')->value('value');
    }
    if($agent_level==2){
        $sec_county_bonus=Db::name('config')->where('name','=','sec_county_bonus_city')->value('value');
    }
    if($agent_level==3){
        $sec_county_bonus=Db::name('config')->where('name','=','sec_county_bonus_province')->value('value');
    }
    return $sec_county_bonus;
}



//检查是否在四周内
public function in_four_week($begin_time){
    if($begin_time+3600*24*7*4>time()){
        return true;
    }else{
        return false;
    }
}


//检查是否在第五周
public function in_five_week($begin_time){
    if($begin_time+3600*24*7*5>time()&&$firstLeader['begin_time']+3600*24*7*6<time()){
        return true;
    }else{
        return false;
    }
    
}

//检查是否6周后
public function after_six_week($begin_time){
    if($begin_time+3600*24*7*6>time()){
            return true;
    }else{
        return false;
    }
    
}

//获取地区代理的自身业绩要求
public function get_bonus_position($user_level){
    if($user_level){
        $bonus_position=Db::name('config')->where('name','=','district_bonus')->value('value');
    }
    if($user_level==2){
        $bonus_position=Db::name('config')->where('name','=','city_bonus')->value('value');
    }
    if($user_level==3){
        $bonus_position=Db::name('config')->where('name','=','province_bonus')->value('value');
    }
    return $bonus_position;
}


//获取对应星期的倍数
public function week_times($num){
    if($num==5){
        $week_times=Db::name('config')->where('name','=','five_week_require')->value('value');
    }
    if($num==6){
        $week_times=Db::name('config')->where('name','=','six_week_require')->value('value');
    }
    return $week_times;
}


    public function addTeamOrder(Team $team)
    {
        $this->setPromType(6);
        $teamActivity = $team->getTeamActivity();
        $teamFoundId = $team->getFoundId();
        if($teamFoundId){
            $team_found_queue = Cache::get('team_found_queue');
            if($team_found_queue[$teamFoundId] <= 0){
                throw new TpshopException('提交订单', 0, ['status' => -1, 'msg' => '当前人数过多请耐心排队!', 'result' => '']);
            }
            $team_found_queue[$teamFoundId] = $team_found_queue[$teamFoundId] - 1;
            Cache::set('team_found_queue', $team_found_queue);
        }
        $this->setPromId($teamActivity['team_id']);
        $this->check();
        $this->queueInc();
        $this->addOrder();
        $this->addOrderGoods();
        Hook::listen('user_add_order', $this->order);//下单行为
        if($teamActivity['team_type'] != 2){
            if(tpCache('shopping.reduce') == 1){
                minus_stock($this->order);//下单减库存
            }
        }
        // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
        if ($this->order['order_amount'] == 0) {
            update_pay_status($this->order['order_sn']);
        }
        $this->queueDec();
    }

    /**
     * 预售订单下单
     * @param PreSell $preSell
     */
    public function addPreSellOrder(PreSell $preSell)
    {
        $this->preSell = $preSell;
        $this->setPromType(4);
        $this->setPromId($preSell['pre_sell_id']);
        $this->check();
        $this->queueInc();
        $this->addOrder();
        $this->addOrderGoods();
        $reduce = tpCache('shopping.reduce');
        Hook::listen('user_add_order', $this->order);//下单行为
        if($reduce == 1 || empty($reduce)){
            minus_stock($this->order);//下单减库存
        }
        //预售暂不至此积分余额优惠券支付
        // 如果应付金额为0  可能是余额支付 + 积分 + 优惠券 这里订单支付状态直接变成已支付
//            if ($this->order['order_amount'] == 0) {
//                update_pay_status($this->order['order_sn']);
//            }
//        $this->changUserPointMoney();//扣除用户积分余额
        $this->queueDec();
    }

    private function addShopOrder()
    {
        $shop = $this->pay->getShop();
        if(empty($shop)){
            return;
        }
        $shop_order_data = [
            'order_id' => $this->order['order_id'],
            'order_sn' => $this->order['order_sn'],
            'shop_id' => $shop['shop_id'],
            'take_time' => date('Y-m-d H:i:s', $this->take_time),
            'add_time' => time(),
        ];
        $shopOrder = new ShopOrder();
        $shopOrder->data($shop_order_data, true)->save();
    }

    /**
     * 提交订单前检查
     * @throws TpshopException
     */
    public function check()
    {
        $shop = $this->pay->getShop();
        if($shop['shop_id'] > 0){
            if($this->take_time <= time()){
                throw new TpshopException('提交订单', 0, ['status' => 0, 'msg' => '自提时间不能小于当前时间', 'result' => '']);
            }
            $weekday = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            $day = $weekday[date('w', $this->take_time)];
            if($shop[$day] == 0){
                throw new TpshopException('提交订单', 0, ['status' => 0, 'msg' => '自提时间不在营业日范围', 'result' => '']);
            }
            $that_day = date('Y-m-d', $this->take_time);
            $that_day_start_time = strtotime($that_day . ' '.$shop['work_start_time'] . ':00');
            $that_day_end_time = strtotime($that_day . ' '.$shop['work_end_time'] . ':00');
            if($this->take_time < $that_day_start_time || $this->take_time > $that_day_end_time){
                throw new TpshopException('提交订单', 0, ['status' => 0, 'msg' => '自提时间不在营业时间范围', 'result' => '']);
            }
            if(empty($this->consignee)){
                throw new TpshopException('提交订单', 0, ['status' => 0, 'msg' => '请填写提货人姓名', 'result' => '']);
            }
            if(empty($this->mobile) || !check_mobile($this->mobile)){
                throw new TpshopException('提交订单', 0, ['status' => 0, 'msg' => '提货人联系方式格式不正确', 'result' => '']);
            }
        }
        $pay_points = $this->pay->getPayPoints();
        $user_money = $this->pay->getUserMoney();
        if ($pay_points || $user_money) {
            $user = $this->pay->getUser();
            if ($user['is_lock'] == 1) {
                throw new TpshopException('提交订单', 0, ['status' => -5, 'msg' => "账号异常已被锁定，不能使用余额支付！", 'result' => '']);
            }
            if (empty($user['paypwd'])) {
                throw new TpshopException('提交订单', 0, ['status' => -6, 'msg' => "请先设置支付密码", 'result' => '']);
            }
            if (empty($this->payPsw)) {
                throw new TpshopException('提交订单', 0, ['status' => -7, 'msg' => "请输入支付密码", 'result' => '']);
            }
            if ($this->payPsw !== $user['paypwd'] && encrypt($this->payPsw) !== $user['paypwd']) {
                throw new TpshopException('提交订单', 0, ['status' => -8, 'msg' => '支付密码错误', 'result' => '']);
            }
        }
    }

    private function queueInc()
    {
        $queue = Cache::get('queue');
        if($queue >= 100){
            throw new TpshopException('提交订单', 0, ['status' => -99, 'msg' => "当前人数过多请耐心排队!", 'result' => '']);
        }
        Cache::inc('queue');
    }

    /**
     * 订单提交结束
     */
    private function queueDec()
    {
        Cache::dec('queue');
    }

    /**
     * 插入订单表
     * @throws TpshopException
     */
    private function addOrder()
    {
        $OrderLogic = new OrderLogic();
        $user = $this->pay->getUser();
        $shop = $this->pay->getShop();
        $invoice_title = $this->invoiceTitle;
        if($this->invoiceTitle == "" && $this->invoiceDesc != "不开发票"){
            $invoice_title = "个人";
        }
        $orderData = [
            'order_sn' => $OrderLogic->get_order_sn(), // 订单编号
            'user_id' => $user['user_id'], // 用户id
            'email' => $user['email'],//'邮箱'
            'invoice_title' => ($this->invoiceDesc != '不开发票') ?  $invoice_title : '', //'发票抬头',
            'invoice_desc' => $this->invoiceDesc, //'发票内容',
            'goods_price' => $this->pay->getGoodsPrice(),//'商品价格',
            'shipping_price' => $this->pay->getShippingPrice(),//'物流价格',
            'user_money' => $this->pay->getUserMoney(),//'使用余额',
            'coupon_price' => $this->pay->getCouponPrice(),//'使用优惠券',
            'integral' => $this->pay->getPayPoints(), //'使用积分',
            'integral_money' => $this->pay->getIntegralMoney(),//'使用积分抵多少钱',
            'total_amount' => $this->pay->getTotalAmount(),// 订单总额
            'order_amount' => $this->pay->getOrderAmount(),//'应付款金额',
            'add_time' => time(), // 下单时间
        ];
        if($orderData["order_amount"] < 0){
            throw new TpshopException("订单入库", 0, ['status' => -8, 'msg' => '订单金额不能小于0', 'result' => '']);
        }
        if ($this->promType == 4) {
            //预售订单
            if ($this->preSell['deposit_price'] > 0) {
                $orderData['goods_price'] = $this->preSell['ing_price'] * $this->pay->getToTalNum();
                $orderData['total_amount'] = $this->preSell['ing_price'] * $this->pay->getToTalNum();
                $orderData['order_amount'] = $this->preSell['deposit_price'] * $this->pay->getToTalNum() - $this->pay->getIntegralMoney() - $this->pay->getUserMoney();
            }
        }
        if (!empty($shop)) {
            $orderData['shop_id'] = $shop['shop_id'];
            $orderData['consignee'] = $this->consignee;
            $orderData['mobile'] = $this->mobile;
            $orderData['province'] = $shop['province_id'];
            $orderData['city'] = $shop['city_id'];
            $orderData['district'] = $shop['district_id'];
            $orderData['address'] = $shop['shop_address'];
            $orderData['zipcode'] = $shop['shop_zip'];
        } elseif (!empty($this->userAddress)) {
            $orderData['consignee'] = $this->userAddress['consignee'];// 收货人
            $orderData['province'] = $this->userAddress['province'];//'省份id',
            $orderData['city'] = $this->userAddress['city'];//'城市id',
            $orderData['district'] = $this->userAddress['district'];//'县',
            $orderData['twon'] = $this->userAddress['twon'];// '街道',
            $orderData['address'] = $this->userAddress['address'];//'详细地址'
            $orderData['mobile'] = $this->userAddress['mobile'];//'手机',
            $orderData['zipcode'] = $this->userAddress['zipcode'];//'邮编',
        } else {
            $orderData['consignee'] = $user['nickname'];// 收货人
            $orderData['mobile'] = $user['mobile'];//'手机',
        }
        if (!empty($this->userNote)) {
            $orderData['user_note'] = $this->userNote;// 用户下单备注
        }
        if (!empty($this->taxpayer)) {
            $orderData['taxpayer'] = $this->taxpayer; //'发票纳税人识别号',
        }
        $orderPromId = $this->pay->getOrderPromId();
        $orderPromAmount = $this->pay->getOrderPromAmount();
        if ($orderPromId > 0) {
            $orderData['order_prom_id'] = $orderPromId;//'订单优惠活动id',
            $orderData['order_prom_amount'] = $orderPromAmount;//'订单优惠活动金额,
        }

        $payList = $this->pay->getPayList();
        if($payList[0]['is_virtual']){
            $this->promType = 5;
            $orderData['shipping_time'] = $payList[0]['virtual_indate'];
            $orderData['mobile'] = $this->mobile;
        }

        if ($this->promType) {
            $orderData['prom_type'] = $this->promType;//订单类型
        }
        if ($this->promId > 0) {
            $orderData['prom_id'] = $this->promId;//活动id
        }
        if ($orderData['integral'] > 0 || $orderData['user_money'] > 0) {
            $orderData['pay_name'] = $orderData['user_money']>0 ? '余额支付' : '积分兑换';//支付方式，可能是余额支付或积分兑换，后面其他支付方式会替换
        }

        $this->order->data($orderData, true);
        $orderSaveResult = $this->order->save();
        if ($orderSaveResult === false) {
            throw new TpshopException("订单入库", 0, ['status' => -8, 'msg' => '添加订单失败', 'result' => '']);
        }
    }

    /**
     * 插入订单商品表
     */
    private function addOrderGoods()
    {
        if($this->pay->getOrderPromAmount() > 0) {
            $orderDiscounts = $this->pay->getOrderPromAmount() + $this->pay->getCouponPrice();  //整个订单优惠价钱
        }else{
            $orderDiscounts = $this->pay->getCouponPrice();  //整个订单优惠价钱
        }
        $payList = $this->pay->getPayList();
        $goods_ids = get_arr_column($payList,'goods_id');
        $goodsArr = Db::name('goods')->where('goods_id', 'IN', $goods_ids)->getField('goods_id,cost_price,give_integral,is_receiving_commission,prize_ratio,is_team_prize,is_achievement');
        $orderGoodsAllData = [];
        foreach ($payList as $payKey => $payItem) {
            if($this->pay->getGoodsPrice() ==0){  //清华要求加上
                $totalPriceToRatio =0;
            }else{
                $totalPriceToRatio = $payItem['member_goods_price'] / $this->pay->getGoodsPrice();  //商品价格占总价的比例
            }
            $finalPrice = round($payItem['member_goods_price'] - ($totalPriceToRatio * $orderDiscounts), 3);
            $orderGoodsData['order_id'] = $this->order['order_id']; // 订单id
            $orderGoodsData['goods_id'] = $payItem['goods_id']; // 商品id
            $orderGoodsData['goods_name'] = $payItem['goods_name']; // 商品名称
            $orderGoodsData['goods_sn'] = $payItem['goods_sn']; // 商品货号
            $orderGoodsData['goods_num'] = $payItem['goods_num']; // 购买数量
            $orderGoodsData['is_achievement'] = $goodsArr[$payItem['goods_id']]['is_achievement']; // 是否加入业绩
            $orderGoodsData['is_receiving_commission'] = $goodsArr[$payItem['goods_id']]['is_receiving_commission']; // 如何返佣
            $orderGoodsData['prize_ratio'] = $goodsArr[$payItem['goods_id']]['prize_ratio']; // 团队奖励百分比
            $orderGoodsData['is_team_prize'] = $goodsArr[$payItem['goods_id']]['is_team_prize']; // 是否为团队奖励商品（0：不是，1：是）
            $orderGoodsData['final_price'] = $finalPrice; // 每件商品实际支付价格
            $orderGoodsData['goods_price'] = $payItem['goods_price']; // 商品价               为照顾新手开发者们能看懂代码，此处每个字段加于详细注释
            if (!empty($payItem['spec_key'])) {
                $orderGoodsData['spec_key'] = $payItem['spec_key']; // 商品规格
                $orderGoodsData['spec_key_name'] = $payItem['spec_key_name']; // 商品规格名称
                $spec_goods_price = db('spec_goods_price')->where(['goods_id'=>$payItem['goods_id'],'key'=>$payItem['spec_key']])->find();
                $orderGoodsData['cost_price'] = $spec_goods_price['cost_price']; // 成本价
                $orderGoodsData['item_id'] = $spec_goods_price['item_id']; // 商品规格id
            } else {
                $orderGoodsData['spec_key'] = ''; // 商品规格
                $orderGoodsData['spec_key_name'] = ''; // 商品规格名称
                $orderGoodsData['cost_price'] = $goodsArr[$payItem['goods_id']]['cost_price']; // 成本价
                $orderGoodsData['item_id'] = 0; // 商品规格id
            }
            $orderGoodsData['sku'] = $payItem['sku']; // sku
            $orderGoodsData['member_goods_price'] = $payItem['member_goods_price']; // 会员折扣价
            $orderGoodsData['give_integral'] = $goodsArr[$payItem['goods_id']]['give_integral']; // 购买商品赠送积分
            if ($payItem['prom_type']) {
                $orderGoodsData['prom_type'] = $payItem['prom_type']; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                $orderGoodsData['prom_id'] = $payItem['prom_id']; // 活动id
            } else {
                $orderGoodsData['prom_type'] = 0; // 0 普通订单,1 限时抢购, 2 团购 , 3 促销优惠
                $orderGoodsData['prom_id'] = 0; // 活动id
            }
            array_push($orderGoodsAllData, $orderGoodsData);
        }
        Db::name('order_goods')->insertAll($orderGoodsAllData);
    }

    /**
     * 扣除优惠券
     */
    public function deductionCoupon()
    {
        $couponId = $this->pay->getCouponId();
        if($couponId > 0){
            $user = $this->pay->getUser();
            $couponList = new CouponList();
            $userCoupon = $couponList->where(['status'=>0,'id'=>$couponId])->find();
            if($userCoupon){
                $userCoupon->uid = $user['user_id'];
                $userCoupon->order_id = $this->order['order_id'];
                $userCoupon->use_time = time();
                $userCoupon->status =  1;
                $userCoupon->save();
                Db::name('coupon')->where('id', $userCoupon['cid'])->setInc('use_num');// 优惠券的使用数量加一
            }
        }
    }

    /**
     * 扣除用户积分余额
     * @param Order $order
     */
    public function changUserPointMoney(Order $order)
    {
        if($this->pay->getPayPoints() > 0 || $this->pay->getUserMoney() > 0){
            $user = $this->pay->getUser();
            $user = Users::get($user['user_id']);
            if($this->pay->getPayPoints() > 0){
                $user->pay_points = $user->pay_points - $this->pay->getPayPoints();// 消费积分
            }
            if($this->pay->getUserMoney() > 0){
                $user->user_money = $user->user_money - $this->pay->getUserMoney();// 抵扣余额
                //记录用户余额变动
                setBalanceLog($order['user_id'],5,$this->pay->getUserMoney(),$user->user_money,'下单消费：'.$this->pay->getUserMoney());
            }
            $user->save();

            $accountLogData = [
                'user_id' => $order['user_id'],
                'user_money' => -$this->pay->getUserMoney(),
                'pay_points' => -$this->pay->getPayPoints(),
                'change_time' => time(),
                'desc' => '下单消费',
                'order_sn'=>$order['order_sn'],
                'order_id'=>$order['order_id'],
            ];
            Db::name('account_log')->insert($accountLogData);
        }
    }
    /**
     * 这方法特殊，只限拼团使用。
     * @param $order
     */
    public function setOrder($order)
    {
        $this->order = $order;
    }
    /**
     * 获取订单表数据
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }

    public function setPayPsw($payPsw)
    {
        $this->payPsw = $payPsw;
        return $this;
    }

    public function setInvoiceTitle($invoiceTitle)
    {
        $this->invoiceTitle = $invoiceTitle;
        return $this;
    }
    public function setUserNote($userNote)
    {
        $this->userNote = $userNote;
        return $this;
    }
    public function setTaxpayer($taxpayer)
    {
        $this->taxpayer = $taxpayer;
        return $this;
    }
    public function setInvoiceDesc($invoice_desc)
    {
        $this->invoiceDesc = $invoice_desc;
        return $this;
    }

    public function setUserAddress($userAddress)
    {
        $this->userAddress = $userAddress;
        return $this;
    }
    public function setShop($shop)
    {
        $this->shop = $shop;
        return $this;
    }
    public function setTakeTime($take_time)
    {
        $this->take_time = $take_time;
        return $this;
    }
    public function setConsignee($consignee)
    {
        $this->consignee = $consignee;
        return $this;
    }
    public function setMobile($mobile)
    {
        $payList = $this->pay->getPayList();
        if($payList[0]['is_virtual']){
            if($mobile){
                if(check_mobile($mobile)){
                    $this->mobile = $mobile;
                }else{
                    throw new TpshopException("提交订单",0,['status'=>-1,'msg'=>'手机号码格式错误','result'=>['']]);
                }
            }else{
                throw new TpshopException("提交订单",0,['status'=>-1,'msg'=>'请填写手机号码','result'=>['']]);
            }
        }
        $this->mobile = $mobile;
        return $this;
    }

    private function setPromType($prom_type)
    {
        $this->promType = $prom_type;
        return $this;
    }
    private function setPromId($prom_id)
    {
        $this->promId = $prom_id;
        return $this;
    }

}