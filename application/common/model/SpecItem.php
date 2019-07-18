<?php
/**
 * tpshop
 * ============================================================================
 * 版权所有 2015-2027 深圳搜豹网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.tp-shop.cn
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: IT宇宙人
 * Date: 2015-09-09
 */
namespace app\common\model;
use app\common\util\TpshopException;
use think\Model;
class SpecItem extends Model {
    public function delete()
    {
        $id = $this->getAttr('id');
        $spec_goods_price = db('spec_goods_price')->whereOr('key', $id)->whereOr('key', 'LIKE', '%\_' . $id)->whereOr('key', 'LIKE', $id . '\_%')->find();
        if ($spec_goods_price) {
            $goods_name = db('goods')->where('goods_id', $spec_goods_price['goods_id'])->value('goods_name');
            throw new TpshopException('删除规格值', 0, ['status' => 0, 'msg' => $goods_name . $spec_goods_price['key_name'] . '在使用该规格，不能删除']);
        }
        return parent::delete(); // TODO: Change the autogenerated stub
    }
}
