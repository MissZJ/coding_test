<?php
namespace frontend\controllers;

use common\models\order\OrderInvoiceInfo;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use common\models\user\UserMember;
use common\models\goods\SupplierGoods;
use common\models\markting\SeckillGoods;
use common\models\goods\Photo;
use common\models\goods\BaseGoods;
use common\models\markting\SeckillActive;
use common\models\markting\SeckillOrder;
use common\models\markting\SeckillCity;
use common\models\goods\Unit;
use common\models\user\ReceiveAddress;
use common\models\other\OtherRegion;
use yii\db\Query;
use common\models\goods\Depot;
use common\models\order\OmsInfo;
use common\models\order\OmsGoods;
use common\models\user\ShoppingCart;
use common\models\markting\CouponUser;
use common\models\markting\CouponType;
use common\models\markting\CouponInfo;
use common\models\markting\CouponSelect;
use common\models\order\OrderShippingWay;
use common\models\order\PayBlankCard;
use frontend\components\Controller2016;
use common\models\goods\GoodsPlanSaleGoods;
use common\models\order\OmsInfoExt;

class OrderSuccessController extends Controller2016{ 
	
	/**
	 * @description:下单成功页面
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年11月23日下午2:50:41
	 * @modified_date: 2015年11月23日下午2:50:41
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionIndex(){
		header("Content-Type: text/html; charset=UTF-8");
		$order_ids = f_get('order_id','');
		if($order_ids) {
			$order_ids_array = explode('-', $order_ids);
		} else {
			exit('获取返回数据失败');
		}
		
		//订单号 ，订单金额 ，物流方式，预计送达时间，运费
		
		$hour = date("H");
		$d = date('d');
		$m = date('m');
                $today = date('Y-m-d H:i:s');
		$invoice = [];
                $user_level = $this->user_info['level'];  //当前用户等级
		foreach ($order_ids_array as $key=>$value) {
			$oms_info = OmsInfo::find()->where(['id'=>$value])->asArray()->one();
			$orders[$key]['order_code'] = $oms_info['order_code'];
			$orders[$key]['special_type'] =$oms_info['special_type'];
			if($oms_info['special_type'] == 3){
				$invoice=OrderInvoiceInfo::find()->select('invoice_code,js_time')->where(['order_code'=>$oms_info['order_code']])->asArray()->one();
			}
			$orders[$key]['invoice'] = $invoice;
			$orders[$key]['yunfei'] = $oms_info['express_price'];
			$orders[$key]['order_price'] = $oms_info['order_price'];
			$orders[$key]['collecting_price'] = $oms_info['collecting_price'];
			$orders[$key]['express_name'] = OrderShippingWay::getShipping($oms_info['express_way']);
                        if($user_level === 4){
                            $now_date = date('Y-m-d 18:00:00');
                            if($today < $now_date){   //6点
                                $orders[$key]['delivery_date'] = date("Y-m-d",strtotime("+1 day"));
                            }else{
                                $orders[$key]['delivery_date'] = date("Y-m-d",strtotime("+2 day"));
                            }
                        }else{
                            $now_date = date('Y-m-d 17:00:00');
                            if($today < $now_date){   //5点
                                $orders[$key]['delivery_date'] = date("Y-m-d",strtotime("+1 day"));
                            }else{
                                $orders[$key]['delivery_date'] = date("Y-m-d",strtotime("+2 day"));
                            }
                        }
//			if($hour <= 17) {
//				$orders[$key]['delivery_date'] = date("Y-m-d",strtotime("+1 day"));
//			} else 
//				$orders[$key]['delivery_date'] = date("Y-m-d",strtotime("+2 day"));
//			}
			if($d <= 15){
				$orders[$key]['delivery_invoice'] = $m.'月20号';
			}else{
				$orders[$key]['delivery_invoice'] = ($m+1).'月5号';
			}
		}
		$ad_data = [];
		$this->layout = '_blank';
		return $this->render('index',[
				'orders'=>$orders,
				'ad_data'=>$ad_data,
				'user_province'=>$this->user_info['province']
		]);
	}
	/**
	 * @description:下单错误页面
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年11月23日下午2:50:41
	 * @modified_date: 2015年11月23日下午2:50:41
	 * @modified_user: sunkouping
	 * @review_user:
	 */
	public function actionError(){
		header("Content-Type: text/html; charset=UTF-8");
		$out = f_get('data','');
		if($out) {
			$out = unserialize($out);
		} else {
			$message = '获取返回数据失败';
		}
		$message = $out['message'];
		
		$this->layout = '_blank';
		return $this->render('order_error',[
				'message'=>$message,
		]);
	}
	/**
	 * @description:下单错误页面
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年11月23日下午2:50:41
	 * @modified_date: 2015年11月23日下午2:50:41
	 * @modified_user: sunkouping
	 * @review_user:
	 */
	public function actionError2(){
		header("Content-Type: text/html; charset=UTF-8");
		
		$message = f_get('data','');
	
		$this->layout = '_blank';
		return $this->render('order_error',[
				'message'=>$message,
		]);
	}
	/**
	 * @description: 银行卡转账的支付 录入银行卡信息
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年12月22日下午5:18:45
	 * @modified_date: 2015年12月22日下午5:18:45
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionBlankCard(){
		header("Content-Type: text/html; charset=UTF-8");
		$order_ids = f_get('order_id','');
		if($order_ids) {
			$order_ids_array = explode('-', $order_ids);
		} else {
			exit('获取订单数据失败');
		}
		//用户信息
		$user_id = \Yii::$app->user->id;
		$user_info = UserMember::find()->where(['id'=>$user_id])->one();
		if(!$user_info) {
			return $this->msg('请登录', '/site/login');
		}
		
		if(isset($_POST['sub'])) {
			//
			foreach ($order_ids_array as $value) {
				$order_info = OmsInfo::find()->where(['id'=>$value])->one();
				
				$model = new PayBlankCard();
				$model->time = date('Y-m-d H:s:s');
				$model->bank_code = $_POST['card_num'];
				$model->order_code = $order_info->order_code;
				$model->shop_name = $user_info->shop_name;
				$model->open_name = $_POST['card_name'];
				$model->telphone = $_POST['card_phone'];
				$model->remark = $_POST['card_remark'];
				if($model->save()){
					
				} else {
					
				}
			}
			return $this->redirect(['/order-success/index','order_id'=>$order_ids,]);
		}
		//
		
		return $this->render('__blank_card',[
				'order_ids'=>$order_ids
				
		]);
		
	}
	/**
	 * @description:取消订单，检验运费
	 * @return: '' or code
	 * @author: sunkouping
	 * @date: 2016年6月3日下午4:55:51
	 * @modified_date: 2016年6月3日下午4:55:51
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionYunFeiSkp(){
		$order_id = f_post('order_id',0);
		$order_code = '';
		if($order_id) {
			$check_code = OmsInfo::find()->select('check_code')->where(['id'=>$order_id])->scalar();
			if($check_code) {
				//查出其他的同结算的 订单
				$other_order = OmsInfo::find()->where(['check_code'=>$check_code])
					->andWhere(['order_status'=>5])
					->andWhere(['!=','id',$order_id])
					->asArray()->all();
				
				if($other_order) {
					$yunfei = 0;
					$goods_price = 0;
					foreach ($other_order as $key=>$value) {
						$yunfei = $yunfei + $value['express_price'];
						$goods_price = $goods_price + $value['goods_price'];
					}
					
					if($yunfei == 0 && $goods_price <= 300) {
						$order_code = $other_order[0]['order_code'];
					}
				}
			}
		}
		return $order_code;
	}
	
	/**
	 * @description: 预约上下架脚本
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2016年6月6日下午4:40:44
	 * @modified_date: 2016年6月6日下午4:40:44
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionTimeUpAndDown(){
		GoodsPlanSaleGoods::TimeUpAndDown();
	}
	/**
	 * @description:处理订单
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2016年7月1日上午10:01:05
	 * @modified_date: 2016年7月1日上午10:01:05
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionTest(){
		$pay_way = [1,4,3];    //在线支付   云贷支付  银行卡打款
		$status = [165,75,600]; //,600（云贷）,75(银行卡),165(在线支付)
		//一天之前的时间	
		$end_time = f_date(time()-3600*24);
		//取消 没有取消的在线支付的订单、
		$orders = OmsInfo::find()->where(['order_status'=>$status])
			->andWhere(['<=','order_time',$end_time])
			->asArray()
			->all();
		if(f_get('fd','')) {
			f_d($orders);
		}
		foreach ($orders as $v) {
			if($v['pay_way'] == 1){
				$out = OmsInfo::changeOrderStatus($v['id'],170,'在线支付订单六小时未付款，系统自动取消该订单','系统取消',$v['order_status']);
			}else if($v['pay_way'] == 4){
				$out = OmsInfo::changeOrderStatus($v['id'],605,'云贷支付订单六小时未付款，系统自动取消该订单','系统取消',$v['order_status']);
			} elseif($v['pay_way'] == 3){   //银行卡打款
                $out = OmsInfo::changeOrderStatus($v['id'],80,'银行卡付款订单一天之内未付款，系统自动取消该订单','系统取消',$v['order_status']);
			}
		}
	}
}
