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
 * $Author: IT宇宙人 2015-08-10 $
 */

namespace app\mobile\controller;

use app\common\logic\CartLogic;
use app\common\logic\CouponLogic;
use app\common\logic\Integral;
use app\common\logic\Pay;
use app\common\logic\PlaceOrder;
use app\common\logic\PreSellLogic;
use app\common\model\Combination;
use app\common\model\Goods;
use app\common\model\Order;
use app\common\model\PreSell;
use app\common\model\SpecGoodsPrice;
use app\common\util\TpshopException;
use app\common\logic\UsersLogic;
use app\common\logic\LevelLogic;
use think\Db;
use think\Loader;
use think\Url;

class Cart extends MobileBase
{

    public $cartLogic; // 购物车逻辑操作类    
    public $user_id = 0;
    public $user = array();
    /**
     * 析构流函数
     */
    public function  __construct()
    {
        parent::__construct();
        $this->cartLogic = new CartLogic();
        if (session('?user')) {
            $user = session('user');
            $user = M('users')->where("user_id", $user['user_id'])->find();
            session('user', $user);  //覆盖session 中的 user
            $this->user = $user;
            $this->user_id = $user['user_id'];
            $this->assign('user', $user); //存储用户信息
            // 给用户计算会员价 登录前后不一样
            if ($user) {
                $discount = (empty((float) $user['discount'])) ? 1 : $user['discount'];
                if ($discount != 1) {
                    $discount = 1; //没有折扣，全部变为1
                    $c = Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->where('member_goods_price = goods_price')->count();
                    $c && Db::name('cart')->where(['user_id' => $user['user_id'], 'prom_type' => 0])->update(['member_goods_price' => ['exp', 'goods_price*' . $discount]]);
                }
            }
        }
    }

    //异步调用升级
    public function curls()
    {
        write_log('curls 函数体 进来');

        ignore_user_abort(true);
        set_time_limit(0);
        $data = file_get_contents("php://input"); //接收json数据

        $leaderId = input('leaderId');
        $le = new LevelLogic();
        $ll = $le->user_in($leaderId); //dump($ll);//die;

        write_log('curls 函数体 结束');
    }



    public function index()
    {
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartList = $cartLogic->getCartList(); //用户购物车
        $hot_goods = db('Goods')->where('is_hot=1 and is_on_sale=1')->limit(20)->cache(true, TPSHOP_CACHE_TIME)->select();
        $this->assign('hot_goods', $hot_goods);
        $this->assign('cartList', $cartList); //购物车列表
        return $this->fetch();
    }

    /**
     * 更新购物车，并返回计算结果
     */
    public function AsyncUpdateCart()
    {
        $cart = input('cart/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->AsyncUpdateCart($cart);
        $select_cart_list = $cartLogic->getCartList(1); //获取选中购物车
        $cart_price_info = $cartLogic->getCartPriceInfo($select_cart_list); //计算选中购物车
        $user_cart_list = $cartLogic->getCartList(); //获取用户购物车
        $return['cart_list'] = $cartLogic->cartListToArray($user_cart_list); //拼接需要的数据
        $return['cart_price_info'] = $cart_price_info;
        $this->ajaxReturn(['status' => 1, 'msg' => '计算成功', 'result' => $return]);
    }

    /**
     *  购物车加减
     */
    public function changeNum()
    {
        $cart = input('cart/a', []);
        if (empty($cart)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '请选择要更改的商品', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $result = $cartLogic->changeNum($cart['id'], $cart['goods_num']);
        $this->ajaxReturn($result);
    }

    /**
     * 删除购物车商品
     */
    public function delete()
    {
        $cart_ids = input('cart_ids/a', []);
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $result = $cartLogic->delete($cart_ids);
        if ($result !== false) {
            $this->ajaxReturn(['status' => 1, 'msg' => '删除成功', 'result' => $result]);
        } else {
            $this->ajaxReturn(['status' => 0, 'msg' => '删除失败', 'result' => $result]);
        }
    }

    /**
     * 购物车第二步确定页面
     */
    public function cart2()
    {
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d"); // 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action/s"); // 行为
        if ($this->user_id == 0) {
            $this->error('请先登录', U('Mobile/User/login'));
        }
        $cartLogic = new CartLogic();
        $couponLogic = new CouponLogic();
        $cartLogic->setUserId($this->user_id);
        //立即购买
        if ($action == 'buy_now') {
            $cartLogic->setGoodsModel($goods_id);
            $cartLogic->setSpecGoodsPriceById($item_id);
            $cartLogic->setGoodsBuyNum($goods_num);
            $buyGoods = [];
            try {
                $buyGoods = $cartLogic->buyNow();
            } catch (TpshopException $t) {
                $error = $t->getErrorArr();
                $this->error($error['msg']);
            }
            $cartList['cartList'][0] = $buyGoods;
            $cartGoodsTotalNum = $goods_num;
            $setRedirectUrl = new UsersLogic();
            $setRedirectUrl->orderPageRedirectUrl($_SERVER['REQUEST_URI'], '', $goods_id, $goods_num, $item_id, $action);
        } else {
            if ($cartLogic->getUserCartOrderCount() == 0) {
                $this->error('你的购物车没有选中商品', 'Cart/index');
            }
            $cartList['cartList'] = $cartLogic->getCartList(1); // 获取用户选中的购物车商品
            $cartList['cartList'] = $cartLogic->getCombination($cartList['cartList']);  //找出搭配购副商品
            $cartGoodsTotalNum = count($cartList['cartList']);
        }
        $cartGoodsList = get_arr_column($cartList['cartList'], 'goods');
        $cartGoodsId = get_arr_column($cartGoodsList, 'goods_id');
        $cartGoodsCatId = get_arr_column($cartGoodsList, 'cat_id');
        $cartPriceInfo = $cartLogic->getCartPriceInfo($cartList['cartList']);  //初始化数据。商品总额/节约金额/商品总共数量
        $userCouponList = $couponLogic->getUserAbleCouponList($this->user_id, $cartGoodsId, $cartGoodsCatId); //用户可用的优惠券列表
        $cartList = array_merge($cartList, $cartPriceInfo);
        $userCartCouponList = $cartLogic->getCouponCartList($cartList, $userCouponList);
        $userCouponNum = $cartLogic->getUserCouponNumArr();
        $this->assign('userCartCouponList', $userCartCouponList);  //优惠券，用able判断是否可用
        $this->assign('userCouponNum', $userCouponNum);  //优惠券数量
        $this->assign('cartGoodsTotalNum', $cartGoodsTotalNum);
        $this->assign('cartList', $cartList['cartList']); // 购物车的商品
        $this->assign('cartPriceInfo', $cartPriceInfo); //商品优惠总价
        return $this->fetch();
    }

    /**
     * ajax 获取订单商品价格 或者提交 订单
     */
    public function cart3()
    {
        if ($this->user_id == 0) {
            exit(json_encode(array('status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null))); // 返回结果状态
        }
        $address_id = input("address_id/d", 0); //  收货地址id
        $invoice_title = input('invoice_title');  // 发票
        $taxpayer = input('taxpayer');       // 纳税人识别号
        $invoice_desc = input('invoice_desc');       // 发票内容
        $coupon_id = input("coupon_id/d"); //  优惠券id
        $pay_points = input("pay_points/d", 0); //  使用积分
        $user_money = input("user_money/f", 0); //  使用余额
        $user_note = input("user_note/s", ''); // 用户留言
        $pay_pwd = input("pay_pwd/s", ''); // 支付密码
        $goods_id = input("goods_id/d"); // 商品id
        $goods_num = input("goods_num/d"); // 商品数量
        $item_id = input("item_id/d"); // 商品规格id
        $action = input("action"); // 立即购买
        $shop_id = input('shop_id/d', 0); //自提点id
        $take_time = input('take_time/d'); //自提时间
        $consignee = input('consignee/s'); //自提点收货人
        $mobile = input('mobile/s'); //自提点联系方式
        $is_virtual = input('is_virtual/d', 0);
        $data = input('request.');
        $cart_validate = Loader::validate('Cart');
        if ($is_virtual === 1) {
            $cart_validate->scene('is_virtual');
        }
        if (!$cart_validate->check($data)) {
            $error = $cart_validate->getError();
            $this->ajaxReturn(['status' => 0, 'msg' => $error, 'result' => '']);
        }
        $address = Db::name('user_address')->where("address_id", $address_id)->find();
        $cartLogic = new CartLogic();
        $pay = new Pay();
        Db::startTrans();
        try {
            $cartLogic->setUserId($this->user_id);
            if ($action == 'buy_now') {
                $cartLogic->setGoodsModel($goods_id);
                $cartLogic->setSpecGoodsPriceById($item_id);
                $cartLogic->setGoodsBuyNum($goods_num);
                $buyGoods = $cartLogic->buyNow();
                $cartList[0] = $buyGoods;
                $pay->payGoodsList($cartList);
            } else {
                $userCartList = $cartLogic->getCartList(1);
                $cartLogic->checkStockCartList($userCartList);
                $pay->payCart($userCartList);
            }
            $pay->setUserId($this->user_id)->setShopById($shop_id)->delivery($address['district'])->orderPromotion()
                ->useCouponById($coupon_id)->useUserMoney($user_money)->usePayPoints($pay_points, false, 'mobile');
            // 提交订单
            if ($_REQUEST['act'] == 'submit_order') {
                $placeOrder = new PlaceOrder($pay);
                $placeOrder->setMobile($mobile)->setUserAddress($address)->setConsignee($consignee)->setInvoiceTitle($invoice_title)
                    ->setUserNote($user_note)->setTaxpayer($taxpayer)->setInvoiceDesc($invoice_desc)->setPayPsw($pay_pwd)->setTakeTime($take_time)->addNormalOrder();
                $cartLogic->clear();
                $order = $placeOrder->getOrder();

                $this_order = Db::name('order')->where('order_sn', '=', $order['order_sn'])->find();
                if ($this_order['pay_status'] == 1) {
                    //设置推广用户名额
                    $this->set_pop_person($order['order_sn']);
                    //购买后增加自己的业绩和团队的业绩
                    $this->add_agent_performance($order['order_sn']);
                }

                Db::commit();
                $this->ajaxReturn(['status' => 1, 'msg' => '提交订单成功', 'result' => $order['order_sn']]);
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '计算成功', 'result' => $pay->toArray()]);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
            Db::rollback();
        }
    }


    public function add_agent_performance($order_sn)
    {

        //添加自己本身的业绩
        $order = Db::name('order')
            ->alias('or')
            ->join('order_goods og', 'or.order_id=og.order_id', LEFT)
            ->join('goods g', "g.goods_id=og.goods_id", LEFT)
            ->field('g.agent_good,or.total_amount,or.user_id')
            ->where('or.order_sn', '=', $order_sn)->find();

        $upArr = get_uper_user($order['user_id']);
        foreach ($upArr['recUser'] as $k => $v) {
            $user_agent = Db::name('agent_performance')->where('user_id', '=', $v['user_id'])->find();
            $time = time();
            $ind_per = $user_agent['ind_per'] + $order['total_amount'];
            if ($order['user_id'] == $v['user_id']) {
                if ($user_agent) {
                    Db::name('agent_performance')->where('performance_id', '=', $user_agent['performance_id'])->update(['user_id' => $v['user_id'], 'ind_per' => $ind_per, 'update_time' => $time]);
                } else {
                    Db::name('agent_performance')->insert(['user_id' => $v['user_id'], 'ind_per' => $ind_per, 'agent_per' => 0, 'create_time' => $time]);
                }
            } else {
                $agent_per = $user_agent['agent_per'] + $order['total_amount'];
                if ($user_agent) {
                    Db::name('agent_performance')->where('performance_id', '=', $user_agent['performance_id'])->update(['user_id' => $v['user_id'], 'agent_per' => $agent_per, 'update_time' => $time]);
                } else {
                    Db::name('agent_performance')->insert(['user_id' => $v['user_id'], 'agent_per' => $agent_per, 'create_time' => $time]);
                }
            }
            $this->check_user_upgrade($v['user_id']);
        }
    }

    public function check_user_upgrade($recommend_id){     
            $userModel=Db::name('users');       
            $upPopCount=$userModel->where('first_leader','=',$recommend_id)->count(); 
            $upPopPerformance=Db::name('agent_performance')->where('user_id','=',$recommend_id)->value("agent_per");  
            $manager_ind_sum=$this->popUpdateCondition(1); 
            $chief_ind_sum=$this->popUpdateCondition(2);   
            $ceo_ind_sum=$this->popUpdateCondition(3);
            $partner_ind_sum=$this->popUpdateCondition(4);
            if($upPopCount>=$manager_ind_sum["ind_goods_sum"]&&$upPopCount<$chief_ind_sum["ind_goods_sum"]&&$upPopPerformance>=$manager_ind_sum['describe']){
                $userModel->where('user_id','=',$recommend_id)->update(['leader_level'=>1]);
            }
            if($upPopCount>=$chief_ind_sum["ind_goods_sum"]&&$upPopCount<$ceo_ind_sum["ind_goods_sum"]&&$upPopPerformance>=$chief_ind_sum['describe']){
                $userModel->where('user_id','=',$recommend_id)->update(['leader_level'=>2]);
            }
            if($upPopCount>=$ceo_ind_sum["ind_goods_sum"]&&$upPopCount<$partner_ind_sum["ind_goods_sum"]&&$upPopPerformance>=$ceo_ind_sum['describe']){
                $userModel->where('user_id','=',$recommend_id)->update(['leader_level'=>3]);
            }
            if($upPopCount>=$partner_ind_sum["ind_goods_sum"]&&$upPopPerformance>=$partner_ind_sum['describe']){
                $userModel->where('user_id','=',$recommend_id)->update(['leader_level'=>4]);
        }
    }

        //会员升级条件  
        public function popUpdateCondition($levelNum)
        {
          $ind_goods_sum=Db::name('agent_level')->where('level','=',$levelNum)->find();
          return $ind_goods_sum;
        }




    public function set_pop_person($order_sn)
    {
        // $order_sn='201907161126392756';
        $confModel = M('config');
        $order = Db::name('order')
            ->alias('or')
            ->join('order_goods og', 'or.order_id=og.order_id', LEFT)
            ->join('goods g', "g.goods_id=og.goods_id", LEFT)
            ->field('g.agent_good,or.user_id')
            ->where('or.order_sn', '=', $order_sn)->find();

        if ($order['agent_good']) {  //是代理
            //判断购买代理商品的用户本身是不是代理，如果是代理就不能再买
            $user_agent_info = Db::name('users')->where('user_id', '=', $order['user_id'])->field('agent_level')->find();
            if ($user_agent_info['agent_level']) {
                $this->ajaxReturn(['status' => -1, 'msg' => '用户是代理身份不能重复购买']);
            }
            $time = time();
            Db::name('users')->update(['user_id' => $order['user_id'], 'agent_level' => $order['agent_good'], 'default_period' => 1, 'add_agent_time' => $time]);

            $pop_num_area = $confModel->where('name', '=', 'pop_num_area')->value('value');
            $pop_num_city = $confModel->where('name', '=', 'pop_num_city')->value('value');
            $pop_num_province = $confModel->where('name', '=', 'pop_num_province')->value('value');
            if ($order['agent_good'] == 1) {
                $pop_name = 'pop_person_num';
                $pop_num = $pop_num_area;
            }
            if ($order['agent_good'] == 2) {
                $pop_name = 'pop_person_num_city';
                $pop_num = $pop_num_city;
            }
            if ($order['agent_good'] == 3) {
                $pop_name = 'pop_person_num_province';
                $pop_num = $pop_num_province;
            }
            $pop_person_num = Db::name('config')->where(['name' => $pop_name])->value('value');
            $period_count = ceil($pop_person_num / $pop_num);
            static $current_num = '';
            $current_num = $pop_person_num;
            $popPeriodModel = Db::name('pop_period');
            for ($i = 1; $i <= $period_count; $i++) {
                if ($current_num > $pop_num) {
                    $current_num -= $pop_num;
                    if ($i == 1) {
                        $popPeriodModel->insert(['user_id' => $order['user_id'], 'person_num' => $pop_num, 'poped_per_num' => 0, 'period' => $i, 'level' => $order['agent_good'], 'begin_time' => $time, 'end_time' => '']);
                    } else {
                        $popPeriodModel->insert(['user_id' => $order['user_id'], 'person_num' => $pop_num, 'poped_per_num' => 0, 'period' => $i, 'level' => $order['agent_good'], 'begin_time' => '', 'end_time' => '']);
                    }
                } else {
                    $popPeriodModel->insert(['user_id' => $order['user_id'], 'person_num' => $current_num, 'poped_per_num' => 0, 'period' => $i, 'level' => $order['agent_good'], 'begin_time' => '', 'end_time' => '']);
                }
            }

            //升级奖励上级和上上级
            $this->pay_leader($order['user_id'], $order['agent_good']);
        }
    }

    //晋升为县代奖励上级
    public function pay_leader($userid, $agent_level)
    {
        $userModel = Db::name('users');
        $accountLogModel = Db::name('account_log');
        $achievement = Db::name('order')->where('user_id', '=', $userid)->where('pay_stauts','=','1')->sum('total_amount');

        $pop_commission=Db::name('config')->where('name','=','pop_commission')->value('value');
        //判断当前用户的生份    切换对应的代理级别  百分比
        $county_bonus = $this->first_agent_persent($agent_level);   //下级升级县级奖励百分比   市级一样
        $sec_county_bonus = $this->sec_agent_persent($agent_level);  //二级下级升级县级奖励百分比

        $user = $userModel->where('user_id', '=', $userid)->find();
        $firstLeader = Db::name('users')->where('user_id', '=', $user['first_leader'])->find();
        $time = time();
        if ($user['first_leader']) {
            $firstAch = Db::name('order')->where('user_id', '=', $firstLeader['user_id'])->where('pay_stauts','=','1')->sum('total_amount');

            //上一级的区域代理金额自身要求
            $bonus_position_firs = $this->get_bonus_position($firstLeader['agent_level']);
            $five_times = $this->week_times(5);
            $six_times = $this->week_times(6);

            $agentLevelList = array('1' => '县级', '2' => '市级', '3' => '省级');
            $agentLevel = $agentLevelList[$agent_level];

            //4周内
            if ($this->in_four_week($firstLeader['add_agent_time'])) {
                //插入上级
                $firstBonus = $achievement * $county_bonus / 100;
                $distribut_money = $firstLeader['distribut_money'] + $firstBonus;
                $user_money=$firstLeader['user_money']+$firstBonus * $pop_commission / 100;
                $userModel->update(['user_id' => $firstLeader['user_id'], 'distribut_money' => $distribut_money,'user_money'=>$user_money]);

                $accountLogModel->insert(['user_id' => $firstLeader['user_id'], 'user_money' => $firstBonus, 'pay_points' => $user_money, 'change_time' => $time, 'desc' => "下级用户【'" . $user['user_id'] . "'】晋升为" . $agentLevel . "奖励", 'type' => 3]);
            }
            //5周
            if ($this->in_five_week($firstLeader['add_agent_time'])) {
                if ($firstAch > $five_times * $bonus_position_firs) {
                    $firstBonus = $achievement * $county_bonus / 100;
                    $distribut_money = $firstLeader['distribut_money'] + $firstBonus;
                    $user_money=$firstLeader['user_money']+$firstBonus * $pop_commission / 100;
                    $userModel->update(['user_id' => $firstLeader['user_id'], 'distribut_money' => $distribut_money,'user_money'=>$user_money]);
                    $accountLogModel->insert(['user_id' => $firstLeader['user_id'], 'user_money' => $firstBonus, 'pay_points' => $user_money, 'change_time' => $time, 'desc' => "下级用户【'" . $user['user_id'] . "'】晋升为" . $agentLevel . "奖励", 'type' => 3]);
                }
            }
            //6周后
            if ($this->after_six_week($firstLeader['add_agent_time'])) {
                if ($firstAch > $six_times * $bonus_position_firs) {
                    $firstBonus = $achievement * $county_bonus / 100;
                    $distribut_money = $firstLeader['distribut_money'] + $firstBonus;
                    $user_money=$firstLeader['user_money']+$firstBonus * $pop_commission / 100;
                    $userModel->update(['user_id' => $firstLeader['user_id'], 'distribut_money' => $distribut_money,'user_money'=>$user_money]);
                    $accountLogModel->insert(['user_id' => $firstLeader['user_id'], 'user_money' => $firstBonus, 'pay_points' => $user_money, 'change_time' => $time, 'desc' => "下级用户【'" . $user['user_id'] . "'】晋升为" . $agentLevel . "奖励", 'type' => 3]);
                }
            }

            $secondLeader = $userModel->where('user_id', '=', $firstLeader['first_leader'])->find();
            if ($secondLeader['user_id']) {
                $bonus_position_sec = $this->get_bonus_position($secondLeader['agent_level']);
                $secAch = Db::name('order')->where('user_id', '=', $secondLeader['user_id'])->where('pay_stauts','=','1')->sum('total_amount');
                //4周内
                if ($this->in_four_week($secondLeader['add_agent_time'])) {
                    // 插入二级上级
                    $secondBonus = $achievement * $sec_county_bonus / 100;
                    $sec_distribut_money = $secondLeader['distribut_money'] + $secondBonus;
                    $sec_user_money=$secondLeader['user_money']+ $secondBonus  * $pop_commission / 100;
                    $userModel->update(['user_id' => $secondLeader['user_id'], 'distribut_money' => $sec_distribut_money,'user_money'=>$sec_user_money]);
                    $accountLogModel->insert(['user_id' => $secondLeader['user_id'], 'user_money' => $secondBonus, 'pay_points' => $sec_user_money, 'change_time' => $time, 'desc' => "下级用户【'" . $user['user_id'] . "'】晋升为" . $agentLevel . "奖励", 'type' => 4]);
                }
                //五周内
                if ($this->in_five_week($secondLeader['add_agent_time'])) {
                    if ($secAch > $five_times * $bonus_position_sec) {
                        $secondBonus = $achievement * $sec_county_bonus / 100;
                        $sec_distribut_money = $secondLeader['distribut_money'] + $secondBonus;
                        $sec_user_money=$secondLeader['user_money']+ $secondBonus * $pop_commission / 100;
                        $userModel->update(['user_id' => $secondLeader['user_id'], 'distribut_money' => $sec_distribut_money,'user_money'=>$sec_user_money]);
                        $accountLogModel->insert(['user_id' => $secondLeader['user_id'], 'user_money' => $secondBonus, 'pay_points' => $sec_user_money, 'change_time' => $time, 'desc' => "下级用户【'" . $user['user_id'] . "'】晋升为" . $agentLevel . "奖励", 'type' => 4]);
                    }
                }
                //6周后
                if ($this->after_six_week($secondLeader['add_agent_time'])) {
                    if ($secAch > $six_times * $bonus_position_sec) {
                        $secondBonus = $achievement * $sec_county_bonus / 100;
                        $sec_distribut_money = $secondLeader['distribut_money'] + $secondBonus;
                        $sec_user_money=$secondLeader['user_money']+ $secondBonus * $pop_commission / 100;
                        $userModel->update(['user_id' => $secondLeader['user_id'], 'distribut_money' => $sec_distribut_money,'user_money'=>$sec_user_money]);
                        $accountLogModel->insert(['user_id' => $secondLeader['user_id'], 'user_money' => $secondBonus, 'pay_points' => $sec_user_money, 'change_time' => $time, 'desc' => "下级用户【'" . $user['user_id'] . "'】晋升为" . $agentLevel . "奖励", 'type' => 4]);
                    }
                }
            }
        }
    }


    public function first_agent_persent($agent_level)
    {
        if ($agent_level == 1) {
            $county_bonus = Db::name('config')->where('name', '=', 'county_bonus')->value('value');
        }
        if ($agent_level == 2) {
            $county_bonus = Db::name('config')->where('name', '=', 'county_bonus_city')->value('value');
        }
        if ($agent_level == 3) {
            $county_bonus = Db::name('config')->where('name', '=', 'county_bonus_province')->value('value');
        }
        return $county_bonus;
    }

    public function sec_agent_persent($agent_level)
    {
        if ($agent_level == 1) {
            $sec_county_bonus = Db::name('config')->where('name', '=', 'sec_county_bonus')->value('value');
        }
        if ($agent_level == 2) {
            $sec_county_bonus = Db::name('config')->where('name', '=', 'sec_county_bonus_city')->value('value');
        }
        if ($agent_level == 3) {
            $sec_county_bonus = Db::name('config')->where('name', '=', 'sec_county_bonus_province')->value('value');
        }
        return $sec_county_bonus;
    }



    //检查是否在四周内
    public function in_four_week($begin_time)
    {
        if ($begin_time + 3600 * 24 * 7 * 4 > time()) {
            return true;
        } else {
            return false;
        }
    }

    //检查是否在第五周
    public function in_five_week($begin_time)
    {
        if ($begin_time + 3600 * 24 * 7 * 5 > time() && $firstLeader['begin_time'] + 3600 * 24 * 7 * 6 < time()) {
            return true;
        } else {
            return false;
        }
    }

    //检查是否6周后
    public function after_six_week($begin_time)
    {
        if ($begin_time + 3600 * 24 * 7 * 6 > time()) {
            return true;
        } else {
            return false;
        }
    }

    //获取地区代理的自身业绩要求
    public function get_bonus_position($user_level)
    {
        if ($user_level) {
            $bonus_position = Db::name('config')->where('name', '=', 'district_bonus')->value('value');
        }
        if ($user_level == 2) {
            $bonus_position = Db::name('config')->where('name', '=', 'city_bonus')->value('value');
        }
        if ($user_level == 3) {
            $bonus_position = Db::name('config')->where('name', '=', 'province_bonus')->value('value');
        }
        return $bonus_position;
    }


    //获取对应星期的倍数
    public function week_times($num)
    {
        if ($num == 5) {
            $week_times = Db::name('config')->where('name', '=', 'five_week_require')->value('value');
        }
        if ($num == 6) {
            $week_times = Db::name('config')->where('name', '=', 'six_week_require')->value('value');
        }
        return $week_times;
    }






    /*
     * 订单支付页面
     */
    public function cart4()
    {
        if (empty($this->user_id)) {
            $this->redirect('User/login');
        }
        $order_id = I('order_id/d');
        $order_sn = I('order_sn/s', '');
        $order_where = ['user_id' => $this->user_id];
        if ($order_sn) {
            $order_where['order_sn'] = $order_sn;
        } else {
            $order_where['order_id'] = $order_id;
        }
        $Order = new Order();
        $order = $Order->where($order_where)->find();
        empty($order) && $this->error('订单不存在！');
        if ($order['order_status'] == 3) {
            $this->error('该订单已取消', U("Mobile/Order/order_detail", array('id' => $order['order_id'])));
        }
        if (empty($order) || empty($this->user_id)) {
            $order_order_list = U("User/login");
            header("Location: $order_order_list");
            exit;
        }
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        if ($order['pay_status'] == 1) {
            $order_detail_url = U("Mobile/Order/order_detail", array('id' => $order['order_id']));
            header("Location: $order_detail_url");
            exit;
        }
        $orderGoodsPromType = M('order_goods')->where(['order_id' => $order['order_id']])->getField('prom_type', true);
        //如果是预售订单，支付尾款
        if ($order['pay_status'] == 2 && $order['prom_type'] == 4) {
            if ($order['pre_sell']['pay_start_time'] > time()) {
                $this->error('还未到支付尾款时间' . date('Y-m-d H:i:s', $order['pre_sell']['pay_start_time']));
            }
            if ($order['pre_sell']['pay_end_time'] < time()) {
                $this->error('对不起，该预售商品已过尾款支付时间' . date('Y-m-d H:i:s', $order['pre_sell']['pay_end_time']));
            }
        }
        $payment_where['type'] = 'payment';
        $no_cod_order_prom_type = [4, 5]; //预售订单，虚拟订单不支持货到付款
        if (strstr($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            //微信浏览器
            if (in_array($order['prom_type'], $no_cod_order_prom_type) || in_array(1, $orderGoodsPromType) || $order['shop_id'] > 0) {
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = 'weixin';
            } else {
                $payment_where['code'] = array('in', array('weixin', 'cod'));
            }
        } else {
            if (in_array($order['prom_type'], $no_cod_order_prom_type) || in_array(1, $orderGoodsPromType) || $order['shop_id'] > 0) {
                //预售订单和抢购不支持货到付款
                $payment_where['code'] = array('neq', 'cod');
            }
            $payment_where['scene'] = array('in', array('0', '1'));
        }
        $payment_where['status'] = 1;
        //预售和抢购暂不支持货到付款
        $orderGoodsPromType = M('order_goods')->where(['order_id' => $order['order_id']])->getField('prom_type', true);
        if ($order['prom_type'] == 4 || in_array(1, $orderGoodsPromType)) {
            $payment_where['code'] = array('neq', 'cod');
        }

        //读取配置列表
        $paymentList = null;
        $pay_way = Db::name('config')->where('inc_type', 'pay_setting')->select();
        if ($pay_way) {
            foreach ($pay_way as $k => $v) {
                $decode = json_decode($v['value'], true);
                if ($v['name'] == 'wechat') {
                    $paymentList['weixin']['name'] = '账户：' . $decode['code'];
                    $paymentList['weixin']['img'] = SITE_URL . $decode['img'];
                    $paymentList['weixin']['code'] = 'alipayMobile';
                    $paymentList['weixin']['type'] = 'payment';
                    $paymentList['weixin']['icon'] = 'logo.jpg';
                } elseif ($v['name'] == 'ali') {
                    $paymentList['alipayMobile']['name'] = '账户：' . $decode['code'];
                    $paymentList['alipayMobile']['img'] = SITE_URL . $decode['img'];
                    $paymentList['alipayMobile']['code'] = 'weixin';
                    $paymentList['alipayMobile']['type'] = 'payment';
                    $paymentList['alipayMobile']['icon'] = 'logo.jpg';
                }
            }
        }

        //        $paymentList = M('Plugin')->where($payment_where)->select();
        //        $paymentList = convert_arr_key($paymentList, 'code');
        //        print_r($paymentList);die;
        //
        //        foreach($paymentList as $key => $val)
        //        {
        //            $val['config_value'] = unserialize($val['config_value']);
        //            if($val['config_value']['is_bank'] == 2)
        //            {
        //                $bankCodeList[$val['code']] = unserialize($val['bank_code']);
        //            }
        //            if($key != 'cod' && (($key == 'weixin' && !is_weixin()) // 不是微信app,就不能微信付，只能weixinH5付,用于手机浏览器
        //                || ($key != 'weixin' && is_weixin()) //微信app上浏览，只能微信
        //                || ($key != 'alipayMobile' && is_alipay()))){ //在支付宝APP上浏览，只能用支付宝支付
        //                unset($paymentList[$key]);
        //            }
        //        }

        //        $bank_img = include APP_PATH.'home/bank.php'; // 银行对应图片

        $this->assign('paymentList', $paymentList);
        //        $this->assign('bank_img',$bank_img);
        $this->assign('order', $order);
        //        $this->assign('bankCodeList',$bankCodeList);
        $this->assign('pay_date', date('Y-m-d', strtotime("+1 day")));
        return $this->fetch();
    }

    /**
     * ajax 检查订单是否支付成功
     */
    public function check_order($order_id)
    {
        // 如果已经支付过的订单直接到订单详情页面. 不再进入支付页面
        $order_status = Db::name('order')->where('order_id', $order_id)->value('pay_status');
        if ($order_status == 1) {
            $this->ajaxReturn(['status' => 1, 'msg' => '支付成功', 'result' => '']);
        } else {
            $this->ajaxReturn(['status' => -1, 'msg' => "还没支付"]);
        }
    }

    /**
     * ajax 将商品加入购物车
     */
    function add()
    {
        $goods_id = I("goods_id/d"); // 商品id
        $goods_num = I("goods_num/d"); // 商品数量
        $item_id = I("item_id/d"); // 商品规格id
        if (empty($goods_id)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '请选择要购买的商品', 'result' => '']);
        }
        if (empty($goods_num)) {
            $this->ajaxReturn(['status' => -1, 'msg' => '购买商品数量不能为0', 'result' => '']);
        }
        $cartLogic = new CartLogic();
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setGoodsModel($goods_id);
        $cartLogic->setSpecGoodsPriceById($item_id);
        $cartLogic->setGoodsBuyNum($goods_num);
        try {
            $cartLogic->addGoodsToCart();
            $this->ajaxReturn(['status' => 1, 'msg' => '加入购物车成功']);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    /**
     * ajax 将搭配购商品加入购物车
     */
    public function addCombination()
    {
        $combination_id = input('combination_id/d'); //搭配购id
        $num = input('num/d'); //套餐数量
        $combination_goods = input('combination_goods/a'); //套餐里的商品
        if (empty($combination_id)) {
            $this->ajaxReturn(['status' => 0, 'msg' => '参数错误']);
        }
        $cartLogic = new CartLogic();
        $combination = Combination::get(['combination_id' => $combination_id]);
        $cartLogic->setUserId($this->user_id);
        $cartLogic->setCombination($combination);
        $cartLogic->setGoodsBuyNum($num);
        try {
            $cartLogic->addCombinationToCart($combination_goods);
            $this->ajaxReturn(['status' => 1, 'msg' => '成功加入购物车']);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    /**
     * ajax 获取用户收货地址 用于购物车确认订单页面
     */
    public function ajaxAddress()
    {
        $regionList = get_region_list();
        $address_list = M('UserAddress')->where("user_id", $this->user_id)->select();
        $c = M('UserAddress')->where("user_id = {$this->user_id} and is_default = 1")->count(); // 看看有没默认收货地址
        if ((count($address_list) > 0) && ($c == 0)) // 如果没有设置默认收货地址, 则第一条设置为默认收货地址
            $address_list[0]['is_default'] = 1;

        $this->assign('regionList', $regionList);
        $this->assign('address_list', $address_list);
        return $this->fetch('ajax_address');
    }


    /**
     * 兑换积分商品
     */
    public function buyIntegralGoods()
    {
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num');
        $Integral = new Integral();
        $Integral->setUserById($this->user_id);
        $Integral->setGoodsById($goods_id);
        $Integral->setSpecGoodsPriceById($item_id);
        $Integral->setBuyNum($goods_num);
        try {
            $Integral->checkBuy();
            $url = U('Cart/integral', ['goods_id' => $goods_id, 'item_id' => $item_id, 'goods_num' => $goods_num]);
            $result = ['status' => 1, 'msg' => '购买成功', 'result' => ['url' => $url]];
            $this->ajaxReturn($result);
        } catch (TpshopException $t) {
            $result = $t->getErrorArr();
            $this->ajaxReturn($result);
        }
    }

    /**
     *  积分商品结算页
     * @return mixed
     */
    public function integral()
    {
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d', 1);
        if (empty($goods_id)) {
            $this->error('非法操作');
        }
        $Goods = new Goods();
        $goods = $Goods->where(['goods_id' => $goods_id])->find();
        if (empty($goods)) {
            $this->error('该商品不存在');
        }
        $goods = $goods->toArray();
        if ($item_id) {
            $SpecGoodsPrice = new SpecGoodsPrice();
            $spec_goods_price = $SpecGoodsPrice->where('goods_id', $goods_id)->where('item_id', $item_id)->find();
            $goods['shop_price'] = $spec_goods_price['price'];
            $goods['key_name'] = $spec_goods_price['key_name'];
        }
        $goods['goods_num'] = $goods_num;
        $this->assign('goods', $goods);
        return $this->fetch();
    }

    /**
     *  积分商品价格提交
     * @return mixed
     */
    public function integral2()
    {
        if ($this->user_id == 0) {
            $this->ajaxReturn(['status' => -100, 'msg' => "登录超时请重新登录!", 'result' => null]);
        }
        $goods_id = input('goods_id/d');
        $item_id = input('item_id/d');
        $goods_num = input('goods_num/d');
        $address_id = input("address_id/d"); //  收货地址id
        $user_note = input('user_note'); // 给卖家留言
        $invoice_title = input('invoice_title');  // 发票
        $taxpayer = input('taxpayer');       // 纳税人识别号
        $invoice_desc = input('invoice_desc');       // 发票内容
        $user_money = input("user_money/f", 0); //  使用余额
        $pay_pwd = input('pay_pwd');
        $shop_id = input('shop_id/d', 0); //自提点id
        $take_time = input('take_time/d'); //自提时间
        $consignee = input('consignee/s'); //自提点收货人
        $mobile = input('mobile/s'); //自提点联系方式
        $integral = new Integral();
        $integral->setUserById($this->user_id);
        $integral->setShopById($shop_id);
        $integral->setGoodsById($goods_id);
        $integral->setBuyNum($goods_num);
        $integral->setSpecGoodsPriceById($item_id);
        $integral->setUserAddressById($address_id);
        $integral->useUserMoney($user_money);
        try {
            $integral->checkBuy();
            $pay = $integral->pay();
            // 提交订单
            if ($_REQUEST['act'] == 'submit_order') {
                $placeOrder = new PlaceOrder($pay);
                $placeOrder->setUserAddress($integral->getUserAddress());
                $placeOrder->setConsignee($consignee);
                $placeOrder->setMobile($mobile);
                $placeOrder->setInvoiceTitle($invoice_title);
                $placeOrder->setUserNote($user_note);
                $placeOrder->setTaxpayer($taxpayer);
                $placeOrder->setInvoiceDesc($invoice_desc);
                $placeOrder->setPayPsw($pay_pwd);
                $placeOrder->setTakeTime($take_time);
                $placeOrder->addNormalOrder();
                $order = $placeOrder->getOrder();
                $this->ajaxReturn(['status' => 1, 'msg' => '提交订单成功', 'result' => $order['order_id']]);
                if ($_SESSION["invoiceInfo"] != "") {
                    unset($_SESSION["invoiceInfo"]);
                }
            }
            $this->ajaxReturn(['status' => 1, 'msg' => '计算成功', 'result' => $pay->toArray()]);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->ajaxReturn($error);
        }
    }

    /**
     *  获取发票信息
     * @date2017/10/19 14:45
     */
    public function invoice()
    {
        $setRedirectUrl = new UsersLogic();
        $setRedirectUrl->setUserId($this->user_id);
        $info = $setRedirectUrl->getUserDefaultInvoice();
        if (empty($info)) {
            $result = ['status' => -1, 'msg' => 'N', 'result' => ''];
        } else {
            $result = ['status' => 1, 'msg' => 'Y', 'result' => $info];
        }
        $this->ajaxReturn($result);
    }
    /**
     *  保存发票信息
     * @date2017/10/19 14:45
     */
    public function save_invoice()
    {

        if (IS_AJAX) {

            //A.1获取发票信息
            $invoice_title = trim(I("invoice_title"));
            $taxpayer      = trim(I("taxpayer"));
            $invoice_desc  = trim(I("invoice_desc"));

            //B.1校验用户是否有历史发票记录
            $map            = [];
            $map['user_id'] =  $this->user_id;
            $info           = M('user_extend')->where($map)->find();

            //B.2发票信息
            $data = [];
            $data['invoice_title'] = $invoice_title;
            $data['taxpayer']      = $taxpayer;
            $data['invoice_desc']  = $invoice_desc;

            //B.3发票抬头
            if ($invoice_title == "个人") {
                $data['invoice_title'] = "个人";
                $data['taxpayer']      = "";
            }


            //是否存贮过发票信息
            if (empty($info)) {
                $data['user_id'] = $this->user_id;
                (M('user_extend')->add($data)) ?
                    $status = 1 : $status = -1;
            } else {
                (M('user_extend')->where($map)->save($data)) ?
                    $status = 1 : $status = -1;
            }
            $result = ['status' => $status, 'msg' => '', 'result' => ''];
            $this->ajaxReturn($result);
        }
    }
    /**
     * 预售
     */
    public function pre_sell()
    {
        $prom_id = input('prom_id/d');
        $goods_num = input('goods_num/d');
        if ($this->user_id == 0) {
            $this->error('请先登录');
        }
        if (empty($prom_id)) {
            $this->error('参数错误');
        }
        $PreSell = new PreSell();
        $preSell = $PreSell::get($prom_id);
        if (empty($preSell)) {
            $this->error('活动不存在');
        }
        $PreSellLogic = new PreSellLogic($preSell->goods, $preSell->specGoodsPrice);
        if ($PreSellLogic->checkActivityIsEnd()) {
            $this->error('活动已结束');
        }
        if (!$PreSellLogic->checkActivityIsAble()) {
            $this->error('活动未开始');
        }
        $cartList = [];
        try {
            $cartList[0] = $PreSellLogic->buyNow($goods_num);
        } catch (TpshopException $t) {
            $error = $t->getErrorArr();
            $this->error($error['msg']);
        }
        $cartTotalPrice = array_sum(array_map(function ($val) {
            return $val['goods_fee'];
        }, $cartList)); //商品优惠总价
        $this->assign('cartList', $cartList); //购物车列表
        $this->assign('preSell', $preSell);
        $this->assign('cartTotalPrice', $cartTotalPrice);
        return $this->fetch();
    }
    //返佣补漏
    public function aoe()
    { }
}
