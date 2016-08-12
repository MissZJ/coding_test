<?php
namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\base\Action;
use yii\db\Query;
use common\models\markting\CouponUser;
use common\models\markting\CouponInfo;
use common\models\markting\CouponBrand;
use common\models\markting\CouponType;
use yii\db\Connection;
use common\models\order\OmsGoods;
use common\models\goods\SupplierGoods;
use common\models\goods\GoodsRestCityGoods;
use common\models\order\OmsInfo;
use yii\helpers\ArrayHelper;
use common\models\order\OmsNopay;
use common\models\goods\Type;
use common\models\goods\GoodsFreight;
use common\models\user\UserStoreType;
use common\models\goods\BaseGoods;
use yii\helpers\Json;
use common\models\user\ReceiveAddress;
use common\models\user\UserDisabled;
use common\models\user\UserMember;
/**
 *临时执行sql，或数据切割的控制器 使用完记得删除action，
 *严防事故,数据溢出，执行超时，cpu满载,数据库锁死 
 *禁止大事务
 */
set_time_limit(18000);
ini_set('memory_limit','2000M');
class TempController extends Controller{ 
    /**
     * @description:
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年1月7日上午9:56:35
     * @modified_date: 2016年1月7日上午9:56:35
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionUpOrder(){
    	$num = f_get('num',1);
    	$limit = 5000;
    	$offset = ($num-1)*$limit;
    	
    	//查询
    	$all_data = OmsGoods::find()->select('id,goods_id,goods_color')->offset($offset)->limit($limit)->asArray()->all();
    	$num = 0;
    	foreach ($all_data as $value) {
    		$order_model = OmsGoods::find()->where(['id'=>$value['id']])->one();
    		$color_id = SupplierGoods::find()->select('color_id')->where(['id'=>$value['goods_id']])->scalar();
    		$order_model->goods_color = $color_id;
    		$order_model->save();
    		if($color_id != $value['goods_color']) {
    			$num ++;
    		}
    	}
    	f_d($num);
    }
    
    /**
     * @description:
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年1月10日下午5:26:33
     * @modified_date: 2016年1月10日下午5:26:33
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionRoll(){
    	$user_id = 122160;//153;//
    	$orders = OmsInfo::find()->where(['user_id'=>$user_id])->asArray()->all();
    	$orders = ArrayHelper::getColumn($orders, 'order_code');
    	$num = 0;
    	foreach ($orders as $value) {
    		$ogs = OmsGoods::find()->where(['order_code'=>$orders])->asArray()->all();
    		foreach ($ogs as $v) {
    			$goods_id = $v['goods_id'];
    			$goods_id_info = SupplierGoods::find()->where(['id'=>$goods_id])->one();
    			if($goods_id_info) {
	    			$goods_id_info->sale_num = $goods_id_info->sale_num -$v['goods_num'];
	    			$goods_id_info->save();
	    			$num = $num + $v['goods_num'];
    			}
    		}
    	}
    	f_d($num);
    }
    /**
     * @description:红包修改有效期
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年1月11日上午9:35:15
     * @modified_date: 2016年1月11日上午9:35:15
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionRedBug(){
    	$rid = 	1746;
    	$rid = 1747;
    	$cs = CouponUser::find()->where(['coupon_id'=>1746])->orWhere(['coupon_id'=>1747])->asArray()->all();
//     	CouponUser::updateAll();
    	foreach ($cs as $value){
    		$u_info = CouponUser::find()->where(['id'=>$cs['id']])->one();
    		$u_info->start_time = '2016-01-11 00:00:00';
    		$u_info->end_time = '2016-01-17 23:58:89';
    		$u_info->save();
    	}
    }
    
    /**
     * @description:测试在线支付订单 一小时之内未付款则取消该订单 并且生成一条系统取消记录
     * @author: lxzmy
     * @date: 2016年1月14日
     */
    public function actionOneHourNoPayOrder(){
        $res = OmsNopay::oneHourNoPay();
         if($res){
             f_msg('修改成功', 'http://www.51dh.com.cn/');
         }else{
             f_msg('修改失败', 'http://www.51dh.com.cn/');
         }
    }
    /**
     * @description:一键免邮
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年1月19日上午10:10:17
     * @modified_date: 2016年1月19日上午10:10:17
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionA(){
    	$types = Type::find()->select('id')->asArray()->all();
    	foreach ($types as $value) {
    		$type_id  = $value['id'];
    		$model = new GoodsFreight();
    		$model->goods_type_id = $type_id;
    		$model->free_type = 1;
    		$model->end_time = f_date(time());
    		$model->add_time = f_date(time());
    		$model->save();
    	}
    }
    
    /**
     * @description:插入数据
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年1月22日下午2:27:18
     * @modified_date: 2016年1月22日下午2:27:18
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionB(){
    	$data = [
//            "update goods_intake_info set order_status = 3 where order_code = '146054086073291'",
//            "update goods_intake_goods set goods_num = subscribe_num where id in(25422,25423,25425)",
//            "update goods_intake_goods set goods_num = subscribe_num where order_code = '146053397828916'",
//            "update goods_intake_info set total_num = subscribe_num where order_code = '146053397828916'",
            "update goods_intake_info set flag = 0 where id = 11535"
    	];
    	foreach ($data as $value) {
    		$sql = $value;
    		$conection = yii::$app->db;
    		$conection->createCommand($sql)->execute();
    	}
    }
  

	public function actionTest() {
		return $this->render('temp');
	}

    public function actionG(){
        $other_num = BaseGoods::getGoodsList($filters = [], $select = [], $sort = [], $page = [], $type = 'not_mobile');
        f_d($other_num);
    }
    /**
     * 51推送：51获取广告推送接口
     * 
     * @author: Taoke
     * @date: 2015年11月25日下午3:44:04
     * @modified_date: 2015年11月25日下午3:44:04
     * @modified_user: Taoke
     * @return json {['status':200,'data'=>array]} status 200有推送，0是没有推送
     * @update :lxzmy 2016-1-15 14:30
     */
    public function actionPopping() {
        // 指定允许其他域名访问  
        header('Access-Control-Allow-Origin:*');  
        // 响应类型  
        header('Access-Control-Allow-Methods:POST');  
        // 响应头设置  
        header('Access-Control-Allow-Headers:x-requested-with,content-type'); 
        
        if (\Yii::$app->request->post()) {
            $userId = \Yii::$app->request->post('user_id');
            $pro_id = \Yii::$app->request->post('province_id');
           
            //1、查询是有效推送消息相关信息
            //失效时间必须大于当前时间
            //起始推送时间必须小于当前时间
            //排序规则：先按是否置顶排序，再按添加时间排序
            $query = new Query();
                $notice = $query->select(['id','title','content','img_url','url','type','goal','category_id','file_execl'])
                ->from('e_push_notice')
                ->where(['>','end_time', date('Y-m-d H:i:s')])
                ->andWhere(['<','start_time', date('Y-m-d H:i:s')])
                ->andWhere(['status'=>1])
                ->orderBy(['sort'=>'DESC','add_time'=>'DESC'])->all();
            if ($notice) {
                //2、查询该用户是否有该消息的阅读权限
                //3、查询该用户是否已经读过该消息
                
                foreach ($notice as $v) {
                    //查询该推送的分类
//                    if (isset($v['category_id'])) {
//                        $v['category'] = (new Query())->select('name')->from('e_push_category')->where(['id'=>$v['category_id']])->scalar();
//                    }
                    if ($v['goal'] == 1) {//指定用户
                        //查询用户是否属于该指定用户
                            $isSetUser = (new Query())->select('id')->from('e_push_notice_user')
                            ->where(['user_id'=>$userId, 'notice_id'=>$v['id']])->one();
                        if ($isSetUser) {//属于
                            //查询该用户是否已阅
                            if (!$this->isRead($userId, $v['id'])) {//未读
                                //返回该推送消息，并将该该推送消息id和用户ID插入已读表e_push_notice_user_read中
                                if ($this->insertIntoRead($userId, $v['id'])) {
                                    $res = $v;
                                    $res['status'] = 200;
                                    return Json::encode($res);
                                }
                            }else{
                                return Json::encode(['status'=>0,'data'=>'您已读推送消息']);
                            }
                        }else{
                            return Json::encode(['status'=>0,'data'=>'没有推送到你的账户']);
                        }
                    }elseif ($v['goal'] == 2) {//按省份推送
                        //查询该用户是否该省份的
                             $isProvince = (new Query())->select('id')->from('e_push_notice_province')->where(['pro_id'=>$pro_id,'notice_id'=>$v['id']])->one();
                        if ($isProvince) {
                            //查询该用户是否已阅
                            if (!$this->isRead($userId, $v['id'])) {//未读
                                //返回该推送消息，并将该该推送消息id和用户ID插入已读表e_push_notice_user_read中
                                if ($this->insertIntoRead($userId, $v['id'])) {
                                    $res = $v;
                                    $res['status'] = 200;
                                    return Json::encode($res);
                                }
                            }else{
                                return Json::encode(['status'=>0,'data'=>'您已读推送消息']);
                            }
                        }else{
                            return Json::encode(['status'=>0,'data'=>'该区域不包含推送']);
                        }
                    }else {//所有人
                        //查询该用户是否已阅
                        if (!$this->isRead($userId, $v['id'])) {//未读
                            //返回该推送消息，并将该该推送消息id和用户ID插入已读表e_push_notice_user_read中
                            if ($this->insertIntoRead($userId, $v['id'])) {
                                $res = $v;
                                $res['status'] = 200;
                                return Json::encode($res);
                            }
                        }else{
                            return Json::encode(['status'=>0,'data'=>'您已读推送消息']);
                        }
                    }
                }
            }
            return Json::encode(['status'=>0,'data'=>'没有推送消息']);
        }
        return Json::encode(['status'=>0,'data'=>'没有请求数据']);
    }
    
    //清除推送缓存 
    //lxzmy   2016-2-25 10:05
    public function actionPushCache(){
        $cache_notice = 'cache_notice';  // 所有消息
        $cache_provice = 'cache_provice';  //按省份
        $cache_user = 'cache_user';     //按用户
        $notice ='';
        $isProvince='';
        $isSetUser='';
        if(f_c($cache_notice)){
            f_c($cache_notice,$notice,false); 
        }
        if(f_c($cache_provice)){
            f_c($cache_provice,$isProvince,false);
        }
        if(f_c($cache_user)){
            f_c($cache_user,$isSetUser,false);
        }
    }
    
    /**
     * 51推送：查询是否已阅
     * @param int $userId
     * @param int $noticeId
     * @return boolean
     * @author: Taoke
     * @date: 2015年11月25日下午7:43:14
     * @modified_date: 2015年11月25日下午7:43:14
     * @modified_user: Taoke
     */
    public function isRead($userId,$noticeId) {
        return (new Query())->select('id')->from('e_push_notice_user_read')
        ->where(['notice_id'=>$noticeId,'user_id'=>$userId])->one();
    }
    
    /**
     * 51推送：设置某用户对于某条推送消息已阅
     * @param int $userId
     * @param int $noticeId
     * @return boolean
     * @author: Taoke
     * @date: 2015年11月25日下午7:45:58
     * @modified_date: 2015年11月25日下午7:45:58
     * @modified_user: Taoke
     */
    public function insertIntoRead($userId,$noticeId) {
        $sql = "insert into e_push_notice_user_read (user_id,notice_id) values ('$userId','$noticeId')";
        return \Yii::$app->db->createCommand($sql)->execute();
    }
    
    /**
     * @description:最低价插队程序
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年2月17日下午4:51:24
     * @modified_date: 2016年2月17日下午4:51:24
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionChadui(){
    	$id = f_get('id',0);
    	if($id) {
    		SupplierGoods::compareGoodsPrice($id);
    	}
    }
    /**
     * @description:
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年2月19日下午5:30:23
     * @modified_date: 2016年2月19日下午5:30:23
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionOs(){
    	$res = OmsInfo::changeOrderStatus(454855,15,'测试','测试');
    	f_d($res);
    }
    
    /*
     * @description : 清空  e_push_notice_user_read 表中数据
     * @author : lxzmy
     * @date :2016-2-29 11:02
     */
    public function actionDeletePushUserRead(){
        $conn = Yii::$app->db;   //开启 db
        $delsql = 'delete from e_push_notice_user_read ';
        if($conn->createCommand($delsql)->execute()){
            f_d('删除数据成功');
        }else{
            f_d('删除数据失败');
        }
    }
    public function actionF(){
    	$out = [
    			'0'=>['order_code'=>'145706263567931','user_id'=>102711],
    			'1'=>['order_code'=>'145706033329533','user_id'=>112166],
    	];
    	foreach ($out as $value) {
    		$oms_info = OmsInfo::find()->where(['order_code'=>$value['order_code']])->one();
    		$oms_info->user_id = $value['user_id'];
    		$oms_info->save();
    	}
    }
    public function actionOrder(){
    	$sql = "SELECT * FROM `order_oms_goods` where ms_discount like '%阶梯%' and (ms_discount_price != 0 or gys_discount_price != 0) and goods_price = old_price";
    	$data = OmsGoods::findBySql($sql)->asArray()->all();
    	foreach ($data as $key=>$value) {
    		$model = OmsGoods::find()->where(['id'=>$value['id']])->one();
    		$model->old_price = $model->goods_price + $model->ms_discount_price;
    		$model->save();
    	}
    }
    //临时
    public function actionSd(){
        $conn = Yii::$app->db;   //开启 db
        $delsql = 'delete from order_remind_info where order_id=\'2308944\' ';
        if($conn->createCommand($delsql)->execute()){
            f_d('删除数据成功');
        }else{
            f_d('删除数据失败');
        }
    }
    public function actionSdd(){
        $conn = Yii::$app->db;   //开启 db
        $delsql = 'delete from order_remind_goods where remind_id=\'99\' ';
        if($conn->createCommand($delsql)->execute()){
            f_d('删除数据成功');
        }else{
            f_d('删除数据失败');
        }
    }
}