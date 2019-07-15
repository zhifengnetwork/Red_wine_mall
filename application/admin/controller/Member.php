<?php
/**
 * Created by PhpStorm.
 * User: MyPc
 * Date: 2019/4/17 0017
 * Time: 21:14
 */
namespace app\admin\controller;

use think\Db;

class Member extends Base
{


    /**
     * 用户支付详情
     */
    public function pay_check(){
        $seach = isset($_GET['seach']) ? $_GET['seach'] : '';
        $m_conditions   = isset($seach['m_conditions']) ? $seach['m_conditions'] : '';
        $datemin        = isset($seach['datemin']) ? $seach['datemin'] : '';
        $datemax        = isset($seach['datemax']) ? $seach['datemax'] : '';
       
        $where = '';
//        $where['id'] = ['>', 0];
        if($seach){
            // 搜索条件
            $name = 'pack_name|nickname';
            $where=s_condition($seach,$name);
            $seach = [
                'm_conditions'  => $m_conditions,
                'datemin'       => strtotime($datemin),
                'datemax'       => strtotime($datemax),
            ];
            $this->assign('seach', $seach); 
        }
        // 列出数据
        $list = Db::name('user_pay_log')
                ->alias('p')
                ->join('users u', 'u.user_id = p.user_id')
                ->join('package pa', 'pa.id = p.package_id')
                ->where($where)
                ->field( 'p.*,u.nickname,u.mobile,pa.pack_name')
                ->order('id desc')
                ->paginate(15, false, ['query' => request()->param()]);;
        $num = count($list);
        $this->assign('num',$num);
        $this->assign('list',$list);
        return $this->fetch();
    }

    public function img(){
        $id = input('get.id/d');
        $info = Db::name('user_pay_log')->where('id',$id)->find();
        $this->assign('info',$info); 
        return $this->fetch();
    }

    public function audit(){
        $data = input('post.');
        if($_POST){           
            // 启动事务
            Db::startTrans();
            try{
                // 通过
                if($data['pay_status']==1){
                    $data['utime'] = time();
                    $res =  Db::name('user_pay_log')->update($data);                    
                    //给用户添加对应时长
                    $where = $this->add_condition($data);
//                    var_dump($where);die;
                    // 判断是否存在上级，存在则给上级加佣金
                    $user_leader = Db::name('users')->where('id', $where['id'])->find();
                    if($user_leader['first_leader']){
                        // 上级用户
                        Db::name('users')->where('id',$user_leader['first_leader'])->setInc('commission', $where['first_commission']);
                        $rebate_data = [
                            'order_id'      =>  $data['id'],
                            'first_leader'  =>  $user_leader['first_leader'],
                            'rebate_money'  =>  $where['first_commission'],
                            'rebate_time'   =>  time()
                        ];
                        // 佣金表变动
                        Db::name('rebate_log')->insert($rebate_data);
                        //再看看上级有没有上级了
                        $user_up_leader = Db::name('users')->where('id', $user_leader['first_leader'])->find();
                        if(isset($user_up_leader)&&$user_up_leader['first_leader']){
                            // 上级用户
                            Db::name('users')->where('id',$user_up_leader['first_leader'])->setInc('commission', $where['second_commission']);
                            $rebate_data = [
                                'order_id'      =>  $data['id'],
                                'first_leader'  =>  $user_up_leader['first_leader'],
                                'rebate_money'  =>  $where['second_commission'],
                                'rebate_time'   =>  time()
                            ];
                            // 佣金表变动
                            Db::name('rebate_log')->insert($rebate_data);
                        }
                    }
                    //下级用户
                    unset($where['first_commission']);
                    unset($where['second_commission']);
                    $user_add = Db::name('users')->update($where);
                   
                }else{
                    $data['utime'] = time();
                    $res =  Db::name('user_pay_log')->update($data);
                    //套餐id 通过套餐id 获取支付金额
                    $pack_info = Db::name('user_pay_log')->where('id',$data['id'])->find();
                    if($pack_info){
                        // 返还对应的金额
                        $re_user = Db::name('users')->where('id', $pack_info['user_id'])->setInc('commission', $pack_info['pay_money']);
                    }

                }
                // 提交事务
                Db::commit();
                return json(['code'=>1]);    
            } catch (\Exception $e) {
                // 回滚事务
                Db::rollback();
                return json(['code'=>0]);
            }            
        }   
    }

    // 条件：给用户添加时间
    public function add_condition($data){
        $info = Db::name('user_pay_log')
            ->alias('p')
            ->join('package pa', 'pa.id = p.package_id')
            ->join('users u', 'u.id = p.user_id')
            ->where('p.id', $data['id'])
            ->field('pa.*,p.user_id,u.end_time')
            ->find();
        $time = $info['pack_time'];
        $end_time = strtotime(date('Y-m-d H:i:s', strtotime("+$time day", $info['end_time'])));
        // 判断是否套餐是否过期
        if ($info['end_time'] < time()) {
            $t = time();
        } else {
            $t = $info['end_time'];
        }
        $end_time = strtotime(date('Y-m-d H:i:s', strtotime("+$time day", $t)));
          
        $where = [
            'end_time' => $end_time,
            'id'       => $info['user_id'],
            'first_commission' =>$info['first_rebate_money'],
            'second_commission' =>$info['second_rebate_money']
        ];
        return $where;       
    }  

    public function show()
    {
        $user_id = input('get.id/d');
        $info = Db::name('users')->where('id',$user_id)->find();
        $first_leader = Db::name('users')->where('id',$info['first_leader'])->value('nickname');
        $this->assign('info',$info);
        $this->assign('first_leader',$first_leader);
        return $this->fetch();
    }

    public function change_password()
    {
        $user_id = input('get.id/d');
        $info = Db::name('users')->where('id',$user_id)->find();
        $act = 'change_password';
        $this->assign('act',$act);
        $this->assign('id',$user_id);
        $this->assign('info',$info);
        return $this->fetch();
    }

    public function level()
    {
        return $this->fetch();
    }

    public function scoreoperation()
    {
        return $this->fetch();
    }

    public function record_browse()
    {
        return $this->fetch();
    }

    public function record_download()
    {
        return $this->fetch();
    }

    public function record_share()
    {
        return $this->fetch();
    }

}