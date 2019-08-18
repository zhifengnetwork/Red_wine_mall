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

class BeeShop extends MobileBase {

    public $user_id = 0;
    public $user = array();
    public $config = array();
    public $time = '';

    public function _initialize()
    {
        parent::_initialize();

        $this->time = date('Y-m-d H:i:s',time()); //当前时间
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

    public function bee_pay($user_money)
    {
        //使用余额,检查使用余额条件
        if($this->user['is_lock'] == 1){
            return $data = ['status' => 0, 'msg' => '账号异常已被锁定，不能使用积分或余额支付！', 'result' => []];// 用户被冻结不能使用余额支付
        }

        if($user_money && $user_money > $this->user['user_money']){
            return $data = ['status' => 0, 'msg' => '你的账户可用余额为:'.$this->user['user_money'].'元', 'result' => []];
        }

        if($user_money > 0){
            Db::name('users')->where('user_id',$this->user_id)->setDec('user_money',$user_money);//扣除余额
        }

        $this->accountLog($user_money); //记录log 日志

        return ['status' => 1];
    }

    /**
     * 用户余额消费记录
     * @param $user_money
     */
    public function accountLog($user_money=0){
        if($user_money){
            $accountLog['user_id'] = $this->user_id;
            $accountLog['user_money'] = - $user_money;
            $accountLog['change_time'] = time();
            $accountLog['desc'] = '九九蜂王';
            Db::name('account_log')->insert($accountLog);
        }

    }

    // 认养
    public function bee_raise()
    {

        $order_id = I('oid');

        $paymentList = M('user_bee')->where(['order_sn'=>$order_id,'status'=>1,'die_status'=>0])->select();
        if ($paymentList == null) {
            $data['status'] = 0;
            $data['msg'] = '支付失败';
            $data['go_url'] = U('Mobile/Bee/beeIndex');

            $this->assign('data', $data); 
        }

        Db::startTrans();
        try{
            //修改购买记录
            M('users')->where('user_id', $this->user_id)->save(['is_bee'=>1, 'adopt_time' => $this->time]);
            
            //赠送道具
            $count = M('user_bee_account')->where('uid','=',$this->user_id)->find();
            if ($count != null ){
                $prop = [
                    'bee_hive' => $count['bee_hive'] + $this->config['one_give_hive'] == '' ? 0 : $this->config['one_give_hive'], //蜂箱
                    'bee_milk' => $count['bee_milk'] + $this->config['one_bee_milk'], //蜂王浆
                    'gooey' => $count['gooey'] + $this->config['one_gooey'], //蜜糖
                    'water' => $count['water'] + $this->config['one_water'], //水
                    'sun_value' => $count['sun_value'] + $this->config['one_sun'], //阳光
                    'update_time' => time()
                ];
                $row = M('user_bee_account')->where('id', $count['id'])->update($prop);
                $id = $count['id'];
            } else {
                $prop = [
                    'uid' => $this->user_id,
                    'bee_hive' => $this->config['one_give_hive'], //蜂箱
                    'bee_milk' => $this->config['one_bee_milk'], //蜂王浆
                    'gooey' => $this->config['one_gooey'], //蜜糖
                    'water' => $this->config['one_water'], //水
                    'sun_value' => $this->config['one_sun'], //阳光
                    'create_time' => time()
                ];
                $row = M('user_bee_account')->insertGetId($prop);
                $id = $row;
            }
            // 添加积分
//            $this->userPoints( 1, $this->config['one_bee_milk']);
            
            M('user_bee')->where(['order_sn'=>$order_id])->save(['status'=>1]);

            // 提交事务
            Db::commit();
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
        }

        //道具日志
        $log = [
            ['bid' => $id,
             'uid' => $this->user_id,
             'type' => 101,
             'inc_or_dec' => 1,
             'num' => $this->config['one_give_hive'],
             'create_time' => time(),
             'note' => '购买幼蜂赠送'
            ],
            ['bid' => $id,
            'uid' => $this->user_id,
            'type' => 201,
            'inc_or_dec' => 1,
            'num' => $this->config['one_bee_milk'],
            'create_time' => time(),
            'note' => '购买幼蜂赠送'
            ],
            ['bid' => $id,
            'uid' => $this->user_id,
            'type' => 301,
            'inc_or_dec' => 1,
            'num' => $this->config['one_water'],
            'create_time' => time(),
            'note' => '购买幼蜂赠送'
            ],
            ['bid' => $id,
            'uid' => $this->user_id,
            'type' => 401,
            'inc_or_dec' => 1,
            'num' => $this->config['one_sun'],
            'create_time' => time(),
            'note' => '购买幼蜂赠送'
            ],
            ['bid' => $id,
            'uid' => $this->user_id,
            'type' => 202,
            'inc_or_dec' => 1,
            'num' => $this->config['one_gooey'],
            'create_time' => time(),
            'note' => '购买幼蜂赠送蜜糖'
            ]
        ];

        $this->prop_log($log);
        $code = new Code();
        $code->bonus();

        $data['status'] = 1;
        $data['msg'] = '购买成功';
        $data['go_url'] = U('Mobile/Bee/beeIndex');

        $this->assign('data', $data); 
        return $this->fetch('/bee/succeed');
    }

    // 喂养
    public function bee_feed()
    {
        $bee = M('user_bee')->where(array('uid' => $this->user_id, 'status' => 1,'die_status'=>0))->count();
        if($bee >= 1){
            $level = M('user_bee')->where(array('uid' => $this->user_id,'level' => 1, 'status' => 1,'die_status'=>0))->find();

            if($level == null){
                $data['msg'] = '您暂时没有幼蜂需要喂养！';
                exit(json_encode($data));
            }

            $user_prop = M('user_bee_account')->where(array('uid' => $this->user_id))->find();

            if($user_prop['bee_milk'] < $this->config['two_fee_bee_milk']){
                $data['msg'] = '您的蜂王浆不足！';
                exit(json_encode($data));
            }
            if($user_prop['water'] < $this->config['two_fee_water']){
                $data['msg'] = '您的露水不足！';
                exit(json_encode($data));
            }
            if($user_prop['sun_value'] < $this->config['two_fee_sun']){
                $data['msg'] = '您的阳光值不足！';
                exit(json_encode($data));
            }

            //事务
            Db::startTrans();
            try{

                $prop = [
                    'bee_milk' => $user_prop['bee_milk'] - $this->config['two_fee_bee_milk'], //蜂王浆
                    'water' => $user_prop['water'] - $this->config['two_fee_water'], //水
                    'sun_value' => $user_prop['sun_value'] - $this->config['two_fee_sun'], //阳光
                    'update_time' => time()
                ];
                M('user_bee_account')->where(array('uid' => $this->user_id))->save($prop);
                M('user_bee')->where(array('id' => $level['id']))->save(['level'=>2]);

                //道具日志
                $log = [
                    ['bid' => $level['id'],
                        'uid' => $this->user_id,
                        'type' => 201,
                        'inc_or_dec' => 2,
                        'num' => $this->config['two_fee_bee_milk'],
                        'create_time' => time(),
                        'note' => '喂养'
                    ],
                    ['bid' => $level['id'],
                        'uid' => $this->user_id,
                        'type' => 301,
                        'inc_or_dec' => 2,
                        'num' => $this->config['two_fee_water'],
                        'create_time' => time(),
                        'note' => '喂养'
                    ],
                    ['bid' => $level['id'],
                        'uid' => $this->user_id,
                        'type' => 401,
                        'inc_or_dec' => 2,
                        'num' => $this->config['two_fee_sun'],
                        'create_time' => time(),
                        'note' => '喂养'
                    ],
                ];
                $this->prop_log($log);
                //喂养消耗了蜂王浆同时需要消耗会员积分
//                $this->userPoints(2,$this->config['two_fee_bee_milk']);
                Db::commit();

            } catch (\Exception $e) {
                Db::rollback();
            }

        }else{
            $data['msg'] = '您暂时没有幼蜂可喂养去购买一个吧！';
            exit(json_encode($data));
        }

        $data = ['status' => 1, 'msg' => '喂养成功'];
        exit(json_encode($data));

    }

    /*
     * 雄蜂
     * type 1购买，2兑换
     */
    public function drone($type)
    {
        $count = M('user_bee_account')->where('uid','=',$this->user_id)->find();

        if ($type == 1){
            // 余额购买
            $pay = $this->bee_pay($this->config['one_bee_money']);
            if($pay['status'] != 1){
                $this->ajaxReturn($pay);
                exit;
            }

            $data['msg'] = '购买成功';
        } else {
            // 兑换
            if($count['bee_milk'] < $this->config['three_drip_bee_milk']){
                $data['msg'] = '你的蜂王浆不足，兑换需要'.$this->config['three_drip_bee_milk'].'滴蜂王浆！';
                exit(json_encode($data));
            }

            $row = $this->drone_convert($count);
            $data['msg'] = '兑换成功';
        }

        $data['status'] = 1;
        exit(json_encode($data));
    }

    // 兑换雄蜂
    public function drone_convert($data)
    {

        $prop = [
            'bee_milk' => $data['bee_milk'] - $this->config['three_drip_bee_milk'], //蜂王浆
            'drone' => $data['drone'] + 1, //雄蜂
            'update_time' => time()
        ];

        $row = M('user_bee_account')->where(array('id' => $data['id']))->save($prop);

        //道具日志
        $log = [
            ['bid' => $data['id'],
            'uid' => $this->user_id,
            'type' => 501,
            'inc_or_dec' => 1,
            'num' => 1,
            'create_time' => time(),
            'note' => '兑换'
            ],
            ['bid' => $data['id'],
            'uid' => $this->user_id,
            'type' => 201,
            'inc_or_dec' => 2,
            'num' => $this->config['three_drip_bee_milk'],
            'create_time' => time(),
            'note' => '兑换雄蜂'
            ],
        ];
        $this->prop_log($log);
        //消耗了蜂王浆需要减会员积分
//        $this->userPoints(2,$this->config['three_drip_bee_milk']);

        return $row;
    }

    // 购买雄蜂
    public function drone_buy()
    {

        $data = M('user_bee_account')->where('uid','=',$this->user_id)->find();

        if ($data != null){
            $prop = [
                'drone' => $data['drone'] + 1, //雄蜂
                'update_time' => time()
            ];

            M('user_bee_account')->where(array('id' => $data['id']))->save($prop);
            $id = $data['id'];

        } else {
            $prop = [
                'uid' => $this->user_id,
                'drone' => 1, //雄蜂
                'create_time' => time()
            ];
            $id = M('user_bee_account')->insertGetId($prop);
        }

        //道具日志
        $log = [
            ['bid' => $id,
                'uid' => $this->user_id,
                'type' => 501,
                'inc_or_dec' => 1,
                'num' => 1,
                'create_time' => time(),
                'note' => '购买'
            ],
        ];

        $this->prop_log($log);

        $data['status'] = 1;
        $data['msg'] = '购买成功';
        $data['go_url'] = U('Mobile/Bee/beeIndex');

        $this->assign('data', $data); 
        return $this->fetch('/bee/succeed');

    }

//    // 交配
//    public function bee_mating()
//    {
//
//        $bee = M('user_bee')->where(array('uid' => $this->user_id, 'status' => 1, 'level' => 2, 'is_mating' => 0))->find();
//
//        if($bee == null){
//            $data['msg'] = '您没有可进行交配的蜂王！';
//            exit(json_encode($data));
//        }
//
//        $user_prop = M('user_bee_account')->where(array('uid' => $this->user_id))->where('drone','>=',1)->find();
//
//        if($user_prop == null){
//            $data['msg'] = '请先购买雄蜂！';
//            exit(json_encode($data));
//        }
//
//        //事务
//        Db::startTrans();
//        try{
//
//            $prop = [
//                'drone' => $user_prop['drone'] - 1, //雄蜂
//                'update_time' => time()
//            ];
//
//            M('user_bee_account')->where(array('uid' => $this->user_id))->save($prop);
//            M('user_bee')->where(array('id' => $bee['id']))->save(['is_mating'=>1]);
//            M('users')->where(array('user_id' => $this->user_id))->setInc('draw_num');
//
//            $this->lay_eggs($bee); // 产卵
//
//        } catch (\Exception $e) {
//            Db::rollback();
//        }
//
//        //道具日志
//        $log = [
//            ['bid' => $bee['id'],
//            'uid' => $this->user_id,
//            'type' => 501,
//            'inc_or_dec' => 2,
//            'num' => 1,
//            'create_time' => time(),
//            'note' => '交配'
//            ],
//            ['bid' => $bee['id'],
//            'uid' => $this->user_id,
//            'type' => 111,
//            'inc_or_dec' => 1,
//            'num' => 1,
//            'create_time' => time(),
//            'note' => '完成交配赠送抽奖1次'
//            ],
//        ];
//
//        $this->prop_log($log);
//
//        $data = ['status' => 1, 'msg' => '交配成功'];
//        exit(json_encode($data));
//    }
    // 20190322 新的交配  下一级成功送上一级抽奖次数
    public function bee_mating()
    {

        $bee = M('user_bee')->where(array('uid' => $this->user_id, 'status' => 1, 'level' => 2, 'is_mating' => 0,'die_status'=>0))->find();

        if($bee == null){
            $data['msg'] = '您没有可进行交配的蜂王！';
            exit(json_encode($data));
        }

        $user_prop = M('user_bee_account')->where(array('uid' => $this->user_id))->where('drone','>=',1)->find();

        if($user_prop == null){
            $data['msg'] = '请先购买雄蜂！';
            exit(json_encode($data));
        }
        //获取当前用户的上级uid
        $first_leader=M('users')->where(array('user_id'=>$this->user_id))->field('first_leader')->find();
//        $mmp[]=['depart_num','<',60];
//        $mmp[]=['uid','<',$this->user_id];
        $is_need=M("user_bee")->where('depart_num','<',60)->where(['uid'=>$first_leader['first_leader'],'die_status'=>0])->count();
        //事务
        Db::startTrans();
        try{

            $prop = [
                'drone' => $user_prop['drone'] - 1, //雄蜂
                'update_time' => time()
            ];

            M('user_bee_account')->where(array('uid' => $this->user_id))->save($prop);
            M('user_bee')->where(array('id' => $bee['id']))->save(['is_mating'=>1]);
            if($is_need && $first_leader['first_leader']!=0){
                M('users')->where(array('user_id' => $first_leader['first_leader']))->setInc('draw_num');
            }
            $this->lay_eggs($bee); // 产卵
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }

        //道具日志
        if($first_leader['first_leader']==0){
            $log = ['bid' => $bee['id'],
                    'uid' => $this->user_id,
                    'type' => 501,
                    'inc_or_dec' => 2,
                    'num' => 1,
                    'create_time' => time(),
                    'note' => '交配'
                ];
        }else{
            $log = [
                ['bid' => $bee['id'],
                    'uid' => $this->user_id,
                    'type' => 501,
                    'inc_or_dec' => 2,
                    'num' => 1,
                    'create_time' => time(),
                    'note' => '交配'
                ],
                ['bid' => $bee['id'],
                    'uid' => $this->user_id,
                    'type' => 111,
                    'inc_or_dec' => 1,
                    'num' => 1,
                    'create_time' => time(),
                    'note' => '完成交配赠送上一级用户'.$first_leader['first_leader'].'抽奖1次'
                ],
            ];
        }
        $this->prop_log($log);

        $data = ['status' => 1, 'msg' => '交配成功'];
        exit(json_encode($data));
    }

    // 产卵
    public function lay_eggs($data)
    {

        $prop = [
            'is_oviposition' => 1, //产卵
            'oviposition_num' => $this->config['three_oviposition'],
            'worker_bee' => $this->config['four_worker_bee'],
            'scout_bee' => $this->config['four_scouts'],
            'house_bee' => $this->config['four_house_bee'],
            'security_bee' => $this->config['four_security_bee'],
        ];

        $row = M('user_bee')->where(array('id' => $data['id']))->save($prop);

        return $row;
    }

    // 孵化
    public function bee_hatch()
    {
        $bee = M('user_bee')->where(array('uid' => $this->user_id, 'is_mating' => 1, 'is_oviposition' => 1,'die_status'=>0))->find();

        if($bee == null){
            $data['msg'] = '您没有可进行孵化的幼卵！';
            exit(json_encode($data));
        }

        $row = M('user_bee')->where(array('id' => $bee['id']))->save(['is_oviposition' => 2]);

        $data = ['status' => 1, 'msg' => '孵化成功'];
        exit(json_encode($data));
    }

    /*
     * 道具流水
     */
    public function prop_log($data)
    {
        Db::name('bee_flow')->insertAll($data);
    }

    /*
     * 积分
     * $type 1自增，2自减
     */
    public function userPoints($type,$sum)
    {
        if($type == 1){
            // 增
            M('Users')->where("user_id", $this->user_id)->setInc('pay_points', $sum);
        } else {
            // 减
            M('Users')->where("user_id", $this->user_id)->setDec('pay_points', $sum);
        }
    }

}