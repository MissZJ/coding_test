<?php

namespace frontend\controllers;

use common\models\goods\Type;
use common\models\markting\PromotionCombine;
use common\models\order\OmsLog;
use common\models\order\OrderOmsInnerMaterials;
use common\models\user\Supplier;
use common\models\user\UserInvoice;
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
use common\models\order\OrderPayWay;
use common\models\other\CityFunctionManage;
use common\models\order\OrderMinusGoods;
use frontend\components\Controller2016;
use yii\helpers\ArrayHelper;
use common\models\order\OmsNopay;
use common\models\order\PayBlankCard;
use common\models\markting\LadderGroup;
use common\models\api\OrderInterfaceLog;
use common\models\user\StoreMall;
use common\models\order\OmsStatus;
use common\models\other\OtherCountyDistLimit;
use common\models\goods\RecordSeries;
use common\models\goods\SelfGoods;
use common\models\goods\GoodsLimitOrder;
use common\models\seas\SeasUserSupplier;
use common\models\skp\Skp;
use common\models\goods\BuySaleBase;
use common\models\markting\GiftGoods;
use common\models\markting\GiftActive;
use common\models\order\GiftInfo;
use common\models\goods\SupplierGoodsLog;
use common\models\order\OrderShippingWay;
class CheckController extends Controller2016{ 
	
	/**
	 * @description:
	 * @return: unknown|\yii\web\Response|Ambigous <string, string>
	 * @author: sunkouping
	 * @date: 2016年6月13日下午2:43:04
	 * @modified_date: 2016年6月13日下午2:43:04
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionCheckCart(){
		
		header("Content-Type: text/html; charset=UTF-8");
		$this->view->title = '确认订单';
		$d2 = f_get('time_line',1);
		$user_id = \Yii::$app->user->id;
		$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		//用户状态校验
		if($user_info['user_status'] != 1){
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
					'type'=>'warn',
					'message'=>'帐号状态异常,即将退出登录',
					'is_next'=>'yes',
					'next_url'=>'/site/logout',
			]);
			return $view;exit;
		}
		
		$city = $user_info['city'];
		//收货地址
		$address = [];
		$address_moren = 0;
		$address = ReceiveAddress::find()->where(['user_id'=>$user_id])->orderBy('status desc')->asArray()->all();
		foreach ($address as $key=>$value) {
			$address[$key]['details'] = OtherRegion::getRegionName($value['province'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['city'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['district'])['region_name'];
			$address[$key]['details'] .= $value['address'];
			if($value['status'] == 1) {
				$address_moren = $value['id']; 
			}
		}
		//物流信息
		$shipping_info = (new Query())->select("t2.*")->from('{{order_shipping_way_city}} AS t1')
		->leftJoin('{{order_shipping_way}} AS t2','t1.shipping_id=t2.id')
		->where(['t1.city'=>$user_info['city']])->andWhere(['t2.status'=>1])->all();

		//数据处理
		$data = f_post('d2_goods',[]); //购物车内的主键一维数组
		
        $data = array_unique($data); // add by :wmc  2016-1-13
       
		$shop_car_ids = $data;
		if(!$data){
			return $this->redirect('/ucenter/order');
		}
		$goods = [];

        $ys_goods = [];
		$flag = 0;

		$is_invoice = 0;
		$invoice = [];
		$invoice_num = 0;
		$invoice_status = 0;
		//获取所有goods数组
		$goods_id_array = [];
        foreach ($data as $key => $value) {
			$relsut = ShoppingCart::find()->where(['id'=>$value])->asArray()->one();
            $goods_id_array[$relsut['goods_id']] = $relsut['goods_num'];
        }
		

        $yfb_open = 0;
        $user_yfb_open = (isset($this->user_info['yfb_open']) && $this->user_info['yfb_open'])?1:0;
        $preGoodsInfo = [];
        $inner_buy_status = true;
        foreach ($data as $value) {
            $cart_info = ShoppingCart::find()->where(['id'=>$value])->asArray()->one();
            if(!$cart_info) {
				return Yii::$app->view->render('@common/widgets/alert/Alert.php',[
						'type'=>'warn',
						'message'=>'购物车商品不存在,点击确定返回购物车',
						'is_next'=>'yes',
						'next_url'=>'/cart/index',
				]);exit;
                //return $this->msg('购物车商品不存在,点击确定返回购物车', '/cart/index');
            }
            $g = SupplierGoods::find()
                ->select('goods_supplier_goods.*,b.is_invoice, b.is_tpl,t3.id as is_ys,t3.begin_time as ys_begin_time,t3.end_time as ys_end_time,t4.mall_id as inner_mall_id,t5.price as insurance')
                ->leftJoin('user_supplier as b','goods_supplier_goods.supplier_id=b.id')
                ->leftJoin('goods_presell as t3','goods_supplier_goods.id=t3.goods_id')
                ->leftJoin('goods_type t4','goods_supplier_goods.type_id=t4.id')
					->leftJoin('goods_insurance t5','goods_supplier_goods.id=t5.good_id')
                ->where(['goods_supplier_goods.id'=>$cart_info['goods_id']])->asArray()->one();
            $d = Depot::find()->where(['id'=>$g['depot_id']])->asArray()->one();
            $g['num'] = $cart_info['goods_num'];
            $g['shop_car_id'] = $cart_info['id'];
            //立减单价金额
            $minus_price = OrderMinusGoods::getMinusPrice($g['id'], $user_info['city']);
            //同一阶梯组的商品总数量
    		$num_group = LadderGroup::getGroupNum($g['id'], $goods_id_array, $user_info['city']);

            $g['price'] = f_price($g['id'], $num_group,$user_info['city'],1)-$minus_price;
            $g['minus_price'] = $minus_price;
            $g['depot_nature'] = $d['depot_nature'];
            $g['goods_id'] = $g['id'];
            
			//屏蔽POS机货到付款
			if($g['type_id'] == 65){
				$flag += 1;
			}

			//上下架 回收站
			if($g['status'] == 0 || $g['enable'] != 1 || $g['is_deleted'] ==1){
				return Yii::$app->view->render('@common/widgets/alert/Alert.php',[
						'type'=>'warn',
						'message'=>'购物车商品已下架,点击确定返回购物车',
						'is_next'=>'yes',
						'next_url'=>'/cart/index',
				]);exit;
			}

			//是否含有开发票的供应商商品
			if($g['is_invoice'] == 2){
				$is_invoice += 1;
				//获取用户发票信息
				$invoice = UserInvoice::find()->where(['user_id'=>$user_id])->orderBy('status desc')->asArray()->all();
				$invoice_num = count($invoice);
				if($invoice){
					foreach($invoice as $vo){
						if($vo['status'] == 2){
							$invoice_status = $vo['id'];
						}
					}
				}
			}
			unset($g['keywords']);
			unset($g['tips']);
			
            //如果是预售商品，此商品走预售拆单逻辑
            if($g['is_ys'] && time()>=strtotime($g['ys_begin_time']) && time()<=strtotime($g['ys_end_time'])){
                $ys_index = "ys_".$g['id'];
                $ys_goods[$ys_index][] = $g;
            }else{
                $goods[] = $g;
            }
            //如果是预存宝商品，预存宝状态为1，渲染页支付方式去除预存宝支付
            if($g['type_id']==217){
                $yfb_open = 1;
            }

            $preGoodsInfo[] = [
                'goods_id'=>$g['id'],
                'goods_num'=>$g['num'],
                'goods_price'=>$g['price']
            ];

            //如果商品中含有非 4,7商城的商品，需要屏蔽内部采购支付方式   code by :wmc   2016.6.5
            if(!in_array($g['inner_mall_id'],[4,5,7])){
                $inner_buy_status = false;
            }
        }
        //支付方式
        $query_pay = (new Query())->select("t2.*")->from('{{order_pay_way_city}} AS t1')
        ->leftJoin('{{order_pay_way}} AS t2','t1.pay_id=t2.id')
        ->where(['t1.city'=>$user_info['city']])->andWhere(['t2.status'=>1]);
        $query_pay->andWhere(['!=','t2.pay_type',2]);//去除银行卡打款方式  code by :wmc
        if($user_info['denied_num'] >= 4 || $user_info['integrity_point'] <= 0 || $flag > 0) {//拒签次数大于4的, D3商品不让货到付款 $d2 == 2  ||
        	$query_pay->andWhere(['!=','t2.pay_type',1]);
        }
        
        //非金银牌用户过滤
        if(!in_array($user_info['level'],[3,4])){
        	$query_pay->andWhere(['!=','t2.pay_type',4]);
        }
        
        //存在预售商品，只接受在线支付
        if($ys_goods){
        	$query_pay->andWhere(['t2.pay_type'=>[3,4]]);
        }
        
        $query_pay->orderBy('t2.pay_type asc');
        $pay_way_info = $query_pay->all();
        
        //定金支付
        $warpExt = [];
        $totalPrePayment = 0.00;
        if($preRes = OmsInfo::getIsPrePayment($preGoodsInfo,$user_info['city'])){
        	if($preRes['isPre']){
        		$warpExt[] = 'pre_payment';
        	}
        	$totalPrePayment = $preRes['totalPre'];
        }
        $pay_way_info = OrderPayWay::getWarpPayWayList($pay_way_info,$warpExt);
        //内部物资采购审批结算
        if(isset($user_info['inner_buy']) && $user_info['inner_buy']==1){
        	if($inner_buy_status){
        		array_unshift($pay_way_info,[
        		'id'=> 99,
        		'name'=> '审批结算',
        		'online_type'=> 0
        		]);
        	}
        }      
		$separateInfo = OmsInfo::separateDepot($goods);
		$inDepot = isset($separateInfo['inDepot']) && is_array($separateInfo['inDepot']) ? $separateInfo['inDepot'] : [];
		$outDepot = isset($separateInfo['outDepot']) && is_array($separateInfo['outDepot']) ? $separateInfo['outDepot'] : [];
		 //然后在仓的根据仓库depot_id,根据仓库拆单
		$inDepotArr = OmsInfo::depotById($inDepot);
		
		 //然后揽件，根据supplier_id拆单
		$outDepotArr = OmsInfo::depotByGys($outDepot);
                
		 //组装数据返回
		$order_data = array_merge($inDepotArr,$outDepotArr,$ys_goods);
                //f_d($order_data);
		$order_temp = [];

		foreach ($order_data as $key=>$value){
			$k = 'order_num_'.$key;
			$order_temp[$k] = $value;
		}
		$order_data = $order_temp;
                //f_d($order_data);
		 //处理数据
		$total_goods_price = 0;
                $record_price=0;
		$types = [];
		$typePrice =[];
		$type_arr = [];
		 foreach ($order_data as $key=>$value) {
		 	foreach ($value as $k=>$v) {
		 		$order_data[$key][$k]['img_url'] = Photo::find()->select('img_url')->where(['id'=>$v['cover_id']])->scalar();
		 		$types[] = $v['type_id'];
				if(!isset($typePrice[$v['type_id']])) $typePrice[$v['type_id']] = 0.00;
				$typePrice[$v['type_id']] += $v['price'] * $v['num'];
		 		$total_goods_price += $v['price'] * $v['num'];
                                //获取商品仓库 是否收取录串手续费
                                $province = SelfGoods::getProvinceByGoodsId($v['goods_id']);  //为自备机并且返回省份
                                if($province){
                                    $series_price = RecordSeries::getSeriesByProvince($province);  //返回手续费接口
                                    if($series_price){
                                        $order_data[$key][$k]['series_price'] = $series_price*$v['num']; //列表使用
                                        $record_price += $series_price*$v['num'];
                                    }else{
                                        $order_data[$key][$k]['series_price']='';
                                    }
                                }else{
                                    $order_data[$key][$k]['series_price']='';
                                }
               if(!in_array($v['type_id'], $type_arr)) $type_arr[] = $v['type_id'];
		 	}
		 }

		 //运费接口
		 //$yunfei_price = OmsInfo::getYunfei($types,$total_goods_price);
		$yunfei_price = OmsInfo::getNewYunfei($typePrice);
		 
		 //获取所有优惠券
		 $coupon_youxiao = [];
		 $coupon_wuxiao = [];
		 $coupons = CouponUser::getAll($user_id);
		 
		 if($coupons) {
		 	foreach ($order_data as $order_num => $od) {
		 		foreach ($coupons as $key=>$value) {
		 			$rs = CouponInfo::mayUse($od,$value['coupon_id']);
		 			if($rs['status'] == 1) {
		 				$coupon_youxiao[$value['id']]['order_num'] = $order_num;
		 				unset($coupons[$key]);
		 			} else {
		 				$coupons[$key]['msg'] = $rs['msg'];
		 			}
		 		}
		 	}
		}
		foreach ($coupon_youxiao as $key=>$value) {
			$u_info = CouponUser::find()->where(['id'=>$key])->asArray()->one();
// 			f_d($u_info);
			$c_info = CouponInfo::find()->where(['id'=>$u_info['coupon_id']])->asArray()->one();
			$coupon_youxiao[$key]['start_time'] = $u_info['start_time'];
			$coupon_youxiao[$key]['end_time'] = $u_info['end_time'];
			$coupon_youxiao[$key]['amount'] = $c_info['amount'];
			$coupon_youxiao[$key]['coupon_name'] = $c_info['coupon_name'];
			$coupon_youxiao[$key]['type'] = CouponSelect::getTypeName($c_info['type_id']);
		}
		
		$coupon_wuxiao = $coupons;
		foreach ($coupon_wuxiao as $key => $value) {
			$c_info = CouponInfo::find()->where(['id'=>$value['coupon_id']])->asArray()->one();
			$coupon_wuxiao[$key]['coupon_name'] = $c_info['coupon_name'];
			$coupon_wuxiao[$key]['type'] = CouponSelect::getTypeName($c_info['type_id']);
			$coupon_wuxiao[$key]['amount'] = $c_info['amount'];
		}	

		$this->layout = '_blank';
// 		f_d($coupon_youxiao);
        
	    $ad = \common\models\other\AdInfo::getAd(112,$city,1);			//支付方式位置旁的广告位
	    
	    $pay_way_info = Type::resetPayWayInfo($pay_way_info, $type_arr); //根据商品类型重新筛选支付方式
	    
		return $this->render('check_cart',[
				'address'=>$address,
				'address_moren'=>$address_moren,
				'shipping_info'=>$shipping_info,
				'pay_way_info'=>$pay_way_info,
				'order_data'=>$order_data,
				'yunfei'=>$yunfei_price,
				'total_goods_price'=>$total_goods_price,
                'record_price'=>$record_price,
				'coupon_wuxiao'=>$coupon_wuxiao,
				'coupon_youxiao'=>$coupon_youxiao,
                'shop_car_ids'=>$shop_car_ids,
                'user_info'=>$this->user_info,
			    'is_invoice'=>$is_invoice,
			    'invoice'=>$invoice,
				'invoice_num'=>$invoice_num,
			    'invoice_status' => $invoice_status,
                'goods_yfb_open'=>$yfb_open,
		        'ad'=>$ad,
				'is_service' => $user_info['is_service'],
                'totalPrePayment'=>$totalPrePayment
		]);
	}
	
	/**
	 * @description:秒杀结算页面
	 * @return: 
	 * @author: sunkouping
	 * @date: 2015年11月2日下午7:53:12
	 * @modified_date: 2015年11月2日下午7:53:12
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionSeckill(){
		
		$data = f_s('ms');
		header("Content-Type: text/html; charset=UTF-8");
		
		$user_id = \Yii::$app->user->id;
		$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		$city = $user_info['city'];
		//收货地址
		$address = [];
		$address_moren = 0;
		$address = ReceiveAddress::find()->where(['user_id'=>$user_id])->orderBy('status desc')->asArray()->all();
		foreach ($address as $key=>$value) {
			$address[$key]['details'] = OtherRegion::getRegionName($value['province'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['city'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['district'])['region_name'];
			$address[$key]['details'] .= $value['address'];
			if($value['status'] == 1) {
				$address_moren = $value['id'];
			}
		}
		//物流信息
		$shipping_info = (new Query())->select("t2.*")->from('{{order_shipping_way_city}} AS t1')
		->leftJoin('{{order_shipping_way}} AS t2','t1.shipping_id=t2.id')
		->where(['t1.city'=>$user_info['city']])->andWhere(['t2.status'=>1])->all();

		//数据处理
		$goods = [];
        $ys_goods = [];
        $yfb_open = 0;
        $user_yfb_open = (isset($this->user_info['yfb_open']) && $this->user_info['yfb_open'])?1:0;
        $preGoodsInfo = [];
        foreach ($data as $value) {
            $g = SupplierGoods::find()
                ->select('goods_supplier_goods.id,base_id,type_id,brand_id,unit_id,goods_name,color_id,supplier_id,depot_id,cover_id,t3.id as is_ys,t3.begin_time as ys_begin_time,t3.end_time as ys_end_time')
                ->leftJoin('user_supplier as b','goods_supplier_goods.supplier_id=b.id')
                ->leftJoin('goods_presell as t3','goods_supplier_goods.id=t3.goods_id')
                ->where(['goods_supplier_goods.id'=>$value['goods_id']])
                ->asArray()
                ->one();
            $d = Depot::find()->where(['id'=>$g['depot_id']])->asArray()->one();
            $g['num'] = $value['goods_num'];
            $g['price'] = $value['price']; //重新获取单价
            $g['minus_price'] = 0; //重新获取单价
            $g['depot_nature'] = $d['depot_nature'];
            $g['goods_id'] = $g['id'];
            //如果是预售供应商，此商品走预售拆单逻辑
            unset($g['keywords']);
            unset($g['tips']);

            //如果是预售商品，此商品走预售拆单逻辑
            if($g['is_ys'] && time()>=strtotime($g['ys_begin_time']) && time()<=strtotime($g['ys_end_time'])){
                $ys_index = "ys_".$g['id'];
                $ys_goods[$ys_index][] = $g;
            }else{
                $goods[] = $g;
            }

            //如果是预存宝商品，预存宝状态为1，渲染页支付方式去除预存宝支付
            if($g['type_id']==217){
                $yfb_open = 1;
            }

            $preGoodsInfo[] = [
                'goods_id'=>$g['id'],
                'goods_num'=>$g['num'],
                'goods_price'=>$g['price']
            ];
        }
        //支付方式
        $pay_way_query = (new Query())->select("t2.*")->from('{{order_pay_way_city}} AS t1')
            ->leftJoin('{{order_pay_way}} AS t2','t1.pay_id=t2.id')
            ->where(['t1.city'=>$user_info['city']])->andWhere(['!=','t2.pay_type',2])->andWhere(['t2.status'=>1]);
        if($ys_goods){
            $pay_way_query->andWhere(['t2.pay_type'=>[3,4]]);
        }

        $pay_way_info = $pay_way_query->orderBy('t2.pay_type asc')->all();

        //定金支付
        $warpExt = [];
        $totalPrePayment = 0.00;
        if($preRes = OmsInfo::getIsPrePayment($preGoodsInfo,$user_info['city'])){
            if($preRes['isPre']){
                $warpExt[] = 'pre_payment';
            }
            $totalPrePayment = $preRes['totalPre'];
        }
        $pay_way_info = OrderPayWay::getWarpPayWayList($pay_way_info,$warpExt);


   		$separateInfo = OmsInfo::separateDepot($goods);
   		$inDepot = isset($separateInfo['inDepot']) && is_array($separateInfo['inDepot']) ? $separateInfo['inDepot'] : [];
   		$outDepot = isset($separateInfo['outDepot']) && is_array($separateInfo['outDepot']) ? $separateInfo['outDepot'] : [];
   		//然后在仓的根据仓库id,根据仓库拆单
   		$inDepotArr = OmsInfo::depotById($inDepot);
   		//然后揽件，根据gys菜单
   		$outDepotArr = OmsInfo::depotByGys($outDepot);
   		//组装数据返回
   		$order_data = array_merge($inDepotArr,$outDepotArr,$ys_goods);
   		$order_temp = [];
   		
   		foreach ($order_data as $key=>$value){
   			$k = 'order_num_'.$key;
   			$order_temp[$k] = $value;
   		}
   		$order_data = $order_temp;
   		//处理数据
   		$total_goods_price = 0;
   		$types = [];
   		foreach ($order_data as $key=>$value) {
   			foreach ($value as $k=>$v) {
   				$order_data[$key][$k]['img_url'] = Photo::find()->select('img_url')->where(['id'=>$v['cover_id']])->scalar();
   				$types[] = $v['type_id'];
   				$total_goods_price += $v['price'] * $v['num'];
   			}
   		}
		$yunfei_price = 0;
   		//活动信息
   		$active_id = f_get('active_id',0);
   		$goods_id = f_get('goods_id',0);
   		$goods_num = f_get('goods_num',0);
                   
		return $this->render('seckill',[
				'address'=>$address,
				'address_moren'=>$address_moren,
				'shipping_info'=>$shipping_info,
				'pay_way_info'=>$pay_way_info,
				'order_data'=>$order_data,
				'yunfei'=>$yunfei_price,
				'total_goods_price'=>$total_goods_price,
				'active_id'=>$active_id,
				'goods_id'=>$goods_id,
				'goods_num'=>$goods_num,
                'user_info'=>$this->user_info,
                'goods_yfb_open'=>$yfb_open,
                'totalPrePayment'=>$totalPrePayment
		]);
	}
	
	
	/**
	 * @description:所有的不走购物车的结算页
	 * @return:
	 * @author: sunkouping
	 * @date: 2015年11月2日下午7:53:12
	 * @modified_date: 2015年11月2日下午7:53:12
	 * @modified_user: sunkouping
	 * @review_user:
	 */
public function actionAlone(){
		
		header("Content-Type: text/html; charset=UTF-8");
		$this->view->title = '确认订单';
		$user_id = \Yii::$app->user->id;
		$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		$city = $user_info['city'];
		//收货地址
		$address = [];
		$address_moren = 0;
		$address = ReceiveAddress::find()->where(['user_id'=>$user_id])->orderBy('status desc')->asArray()->all();
		foreach ($address as $key=>$value) {
			$address[$key]['details'] = OtherRegion::getRegionName($value['province'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['city'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['district'])['region_name'];
			$address[$key]['details'] .= $value['address'];
			if($value['status'] == 1) {
				$address_moren = $value['id']; 
			}
		}
		//物流信息
		$shipping_info = (new Query())->select("t2.*")->from('{{order_shipping_way_city}} AS t1')
		->leftJoin('{{order_shipping_way}} AS t2','t1.shipping_id=t2.id')
		->where(['t1.city'=>$user_info['city']])->andWhere(['t2.status'=>1])->all();
		$data = f_post('data',[]); //购物车内的主键一维数组 id=>num    
                
		if(!$data){
			return $this->goBack();
		}
		$goods = [];
        $ys_goods = []; //预售
		$flag = 0;

		$is_invoice = 0; //开票
		$invoice = [];
		$invoice_num = 0;
		$invoice_status = 0;
		//获取所有goods数组
		$goods_id_array = [];
		
        foreach ($data as $key => $value) {
            $goods_id_array[$key] = $value;
        }

        $yfb_open = 0;
        $user_yfb_open = (isset($this->user_info['yfb_open']) && $this->user_info['yfb_open'])?1:0;
        $preGoodsInfo = [];
        foreach ($data as $key_goods_id=>$value) {
            $g = SupplierGoods::find()
                ->select('goods_supplier_goods.*,b.is_invoice,t3.id as is_ys,t3.begin_time as ys_begin_time,t3.end_time as ys_end_time')
                ->leftJoin('user_supplier as b','goods_supplier_goods.supplier_id=b.id')
                ->leftJoin('goods_presell as t3','goods_supplier_goods.id=t3.goods_id')
                ->where(['goods_supplier_goods.id'=>$key_goods_id])
            	->asArray()->one();
            $d = Depot::find()->where(['id'=>$g['depot_id']])->asArray()->one();
            $g['num'] = $value;
            //立减单价金额
            $minus_price = OrderMinusGoods::getMinusPrice($g['id'], $user_info['city']);
            //同一阶梯组的商品总数量
    		$num_group = LadderGroup::getGroupNum($g['id'], $goods_id_array, $user_info['city']);

            $g['price'] = f_price($g['id'], $num_group,$user_info['city'],1)-$minus_price;
            $g['minus_price'] = $minus_price;
            $g['depot_nature'] = $d['depot_nature'];
            $g['goods_id'] = $g['id'];
			//屏蔽POS机货到付款
			if($g['type_id'] == 65){
				$flag += 1;
			}

			//上下架 回收站
			if($g['status'] == 0 || $g['enable'] != 1 || $g['is_deleted'] ==1){
				return Yii::$app->view->render('@common/widgets/alert/Alert.php',[
						'type'=>'warn',
						'message'=>'购物车商品已下架,点击确定返回购物车',
						'is_next'=>'yes',
						'next_url'=>'/cart/index',
				]);exit;
			}

			//是否含有开发票的供应商商品
			if($g['is_invoice'] == 2){
				$is_invoice += 1;
				//获取用户发票信息
				$invoice = UserInvoice::find()->where(['user_id'=>$user_id])->orderBy('status desc')->asArray()->all();
				$invoice_num = count($invoice);
				if($invoice){
					foreach($invoice as $vo){
						if($vo['status'] == 2){
							$invoice_status = $vo['id'];
						}
					}
				}
			}
			unset($g['keywords']);
			unset($g['tips']);
			
            //如果是预售商品，此商品走预售拆单逻辑
            if($g['is_ys'] && time()>=strtotime($g['ys_begin_time']) && time()<=strtotime($g['ys_end_time'])){
                $ys_index = "ys_".$g['id'];
                $ys_goods[$ys_index][] = $g;
            }else{
                $goods[] = $g;
            }
            

            //如果是预存宝商品，预存宝状态为1，渲染页支付方式去除预存宝支付
            if($g['type_id']==217){
                $yfb_open = 1;
            }

            $preGoodsInfo[] = [
                'goods_id'=>$g['id'],
                'goods_num'=>$g['num'],
                'goods_price'=>$g['price']
            ];
        }
		//支付方式
		$query_pay = (new Query())->select("t2.*")->from('{{order_pay_way_city}} AS t1')
				->leftJoin('{{order_pay_way}} AS t2','t1.pay_id=t2.id')
				->where(['t1.city'=>$user_info['city']])->andWhere(['t2.status'=>1]);
        $query_pay->andWhere(['!=','t2.pay_type',2]);//去除银行卡打款方式  code by :wmc
		if($user_info['denied_num'] >= 4 || $user_info['integrity_point'] <= 0 || $flag > 0) {//拒签次数大于4的, D3商品不让货到付款 $d2 == 2  ||
			$query_pay->andWhere(['!=','t2.pay_type',1]);
		}

		//非金银牌用户过滤
		if(!in_array($user_info['level'],[3,4])){
			$query_pay->andWhere(['!=','t2.pay_type',4]);
		}

        //存在预售商品，只接受在线支付
        if($ys_goods){
            $query_pay->andWhere(['t2.pay_type'=>[3,4]]);
        }

		$query_pay->orderBy('t2.pay_type asc');
		$pay_way_info = $query_pay->all();

        //定金支付
        $warpExt = [];
        $totalPrePayment = 0.00;
        if($preRes = OmsInfo::getIsPrePayment($preGoodsInfo,$user_info['city'])){
            if($preRes['isPre']){
                $warpExt[] = 'pre_payment';
            }
            $totalPrePayment = $preRes['totalPre'];
        }
        $pay_way_info = OrderPayWay::getWarpPayWayList($pay_way_info,$warpExt);
		//f_d($query_pay->createCommand()->getRawSql());
		
		$separateInfo = OmsInfo::separateDepot($goods);

		$inDepot = isset($separateInfo['inDepot']) && is_array($separateInfo['inDepot']) ? $separateInfo['inDepot'] : [];
		$outDepot = isset($separateInfo['outDepot']) && is_array($separateInfo['outDepot']) ? $separateInfo['outDepot'] : [];
		 //然后在仓的根据仓库depot_id,根据仓库拆单
		$inDepotArr = OmsInfo::depotById($inDepot);
		
		 //然后揽件，根据supplier_id拆单
		$outDepotArr = OmsInfo::depotByGys($outDepot);
                
		 //组装数据返回
		$order_data = array_merge($inDepotArr,$outDepotArr,$ys_goods);
                //f_d($order_data);
		$order_temp = [];

		foreach ($order_data as $key=>$value){
			$k = 'order_num_'.$key;
			$order_temp[$k] = $value;
		}
		$order_data = $order_temp;
		 //处理数据
		$total_goods_price = 0;
                $record_price=0;
		$types = [];
		$typePrice =[];
		 foreach ($order_data as $key=>$value) {
		 	foreach ($value as $k=>$v) {
		 		$order_data[$key][$k]['img_url'] = Photo::find()->select('img_url')->where(['id'=>$v['cover_id']])->scalar();
		 		$types[] = $v['type_id'];
				if(!isset($typePrice[$v['type_id']])) $typePrice[$v['type_id']] = 0.00;
				$typePrice[$v['type_id']] += $v['price'] * $v['num'];
		 		$total_goods_price += $v['price'] * $v['num'];
                                //获取商品仓库 是否收取录串手续费
                                $province = SelfGoods::getProvinceByGoodsId($v['goods_id']);  //为自备机并且返回省份
                                if($province){
                                    $series_price = RecordSeries::getSeriesByProvince($province);  //返回手续费接口
                                    if($series_price){
                                        $order_data[$key][$k]['series_price'] = $series_price*$v['num']; //列表使用
                                        $record_price += $series_price*$v['num'];
                                    }else{
                                        $order_data[$key][$k]['series_price']='';
                                    }
                                }else{
                                    $order_data[$key][$k]['series_price']='';
                                }
		 	}
		 }

		 //运费接口
		 //$yunfei_price = OmsInfo::getYunfei($types,$total_goods_price);
		$yunfei_price = OmsInfo::getNewYunfei($typePrice);
		 
		 //获取所有优惠券
		 $coupon_youxiao = [];
		 $coupon_wuxiao = [];
		 $coupons = CouponUser::getAll($user_id);
		 
		 if($coupons) {
		 	foreach ($order_data as $order_num => $od) {
		 		foreach ($coupons as $key=>$value) {
		 			$rs = CouponInfo::mayUse($od,$value['coupon_id']);
		 			if($rs['status'] == 1) {
		 				$coupon_youxiao[$value['id']]['order_num'] = $order_num;
		 				unset($coupons[$key]);
		 			} else {
		 				$coupons[$key]['msg'] = $rs['msg'];
		 			}
		 		}
		 	}
		}
		foreach ($coupon_youxiao as $key=>$value) {
			$u_info = CouponUser::find()->where(['id'=>$key])->asArray()->one();
// 			f_d($u_info);
			$c_info = CouponInfo::find()->where(['id'=>$u_info['coupon_id']])->asArray()->one();
			$coupon_youxiao[$key]['start_time'] = $u_info['start_time'];
			$coupon_youxiao[$key]['end_time'] = $u_info['end_time'];
			$coupon_youxiao[$key]['amount'] = $c_info['amount'];
			$coupon_youxiao[$key]['coupon_name'] = $c_info['coupon_name'];
			$coupon_youxiao[$key]['type'] = CouponSelect::getTypeName($c_info['type_id']);
		}
		
		$coupon_wuxiao = $coupons;
		foreach ($coupon_wuxiao as $key => $value) {
			$c_info = CouponInfo::find()->where(['id'=>$value['coupon_id']])->asArray()->one();
			$coupon_wuxiao[$key]['coupon_name'] = $c_info['coupon_name'];
			$coupon_wuxiao[$key]['type'] = CouponSelect::getTypeName($c_info['type_id']);
			$coupon_wuxiao[$key]['amount'] = $c_info['amount'];
		}	

		$this->layout = '_blank';
// 		f_d($coupon_youxiao);
	    $ad = \common\models\other\AdInfo::getAd(112,$city,1);			//支付方式位置旁的广告位
		return $this->render('check_cart',[
				'address'=>$address,
				'address_moren'=>$address_moren,
				'shipping_info'=>$shipping_info,
				'pay_way_info'=>$pay_way_info,
				'order_data'=>$order_data,
				'yunfei'=>$yunfei_price,
				'total_goods_price'=>$total_goods_price,
                'record_price'=>$record_price,
				'coupon_wuxiao'=>$coupon_wuxiao,
				'coupon_youxiao'=>$coupon_youxiao,
                'shop_car_ids'=>[],
                'user_info'=>$this->user_info,
			    'is_invoice'=>$is_invoice,
			    'invoice'=>$invoice,
				'invoice_num'=>$invoice_num,
			    'invoice_status' => $invoice_status,
                'goods_yfb_open'=>$yfb_open,
		        'ad'=>$ad,
				'is_service' => $user_info['is_service'],
                'totalPrePayment'=>$totalPrePayment
		]);
	}

	/**
	 * @description:所有的不走购物车的直接进入结算页的组合商品
	 * @return:
	 * @author: jr
	 * @date:
	 * @modified_date:
	 * @modified_user: jr
	 * @review_user:
	 */
	public function actionPromotionOrder(){
		header("Content-Type: text/html; charset=UTF-8");
		$this->view->title = '确认订单';
		$user_id = \Yii::$app->user->id;
		$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		$city = $user_info['city'];
		//收货地址
		$address = [];
		$address_moren = 0;
		$address = ReceiveAddress::find()->where(['user_id'=>$user_id])->orderBy('status desc')->asArray()->all();
		foreach ($address as $key=>$value) {
			$address[$key]['details'] = OtherRegion::getRegionName($value['province'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['city'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['district'])['region_name'];
			$address[$key]['details'] .= $value['address'];
			if($value['status'] == 1) {
				$address_moren = $value['id'];
			}
		}
		//如果收货地址不存在
		if(!$address_moren){
			return $this->goBack();
		}
		//物流信息
		$shipping_info = (new Query())->select("t2.*")->from('{{order_shipping_way_city}} AS t1')
				->leftJoin('{{order_shipping_way}} AS t2','t1.shipping_id=t2.id')
				->where(['t1.city'=>$user_info['city']])->andWhere(['t2.status'=>1])->all();
		$promotion_id = f_get('promotion_id');
		$sole_goods_id = f_get('goods_id');
		if(!$promotion_id){
			return $this->goBack();
		}
		$goods = [];
		$ys_goods = []; //预售
		$flag = 0;

		$is_invoice = 0; //开票
		$invoice = [];
		$invoice_num = 0;
		$invoice_status = 0;
//		//获取所有goods数组
//		$goods_id_array = [];

		$data = PromotionCombine::getGoodsId($promotion_id);//er维数组 组合商品信息
		if(!$data){
			return $this->goBack();
		}

		$yfb_open = 0;
		$user_yfb_open = (isset($this->user_info['yfb_open']) && $this->user_info['yfb_open'])?1:0;
		foreach ($data as $key=>$value) {
			$g = SupplierGoods::find()
					->select('goods_supplier_goods.*,b.is_invoice,t3.id as is_ys,t3.begin_time as ys_begin_time,t3.end_time as ys_end_time')
					->leftJoin('user_supplier as b','goods_supplier_goods.supplier_id=b.id')
					->leftJoin('goods_presell as t3','goods_supplier_goods.id=t3.goods_id')
					->where(['goods_supplier_goods.id'=>$value['goods_id']])
					->asArray()->one();
			$d = Depot::find()->where(['id'=>$g['depot_id']])->asArray()->one();
			$g['num'] = $value['num'];
			$g['minus_price'] = 0;
			$g['depot_nature'] = $d['depot_nature'];
			$g['goods_id'] = $g['id'];

			//组合商品新增字段
			$g['promotion_price'] = $value['promotion_price'];
			$g['promotion_type'] = $value['promotion_type'];
			$g['promotion_id'] = $value['promotion_id'];
			$g['promotion_name'] = PromotionCombine::getPromotionNameById($value['promotion_id']);
			//屏蔽POS机货到付款
			if($g['type_id'] == 65){
				$flag += 1;
			}

			//上下架 回收站
			if($g['status'] == 0 || $g['enable'] != 1 || $g['is_deleted'] ==1){
				return Yii::$app->view->render('@common/widgets/alert/Alert.php',[
						'type'=>'warn',
						'message'=>'购物车商品已下架,点击确定返回购物车',
						'is_next'=>'yes',
						'next_url'=>'/cart/index',
				]);exit;
			}

			//是否含有开发票的供应商商品
			if($g['is_invoice'] == 2){
				$is_invoice += 1;
				//获取用户发票信息
				$invoice = UserInvoice::find()->where(['user_id'=>$user_id])->orderBy('status desc')->asArray()->all();
				$invoice_num = count($invoice);
				if($invoice){
					foreach($invoice as $vo){
						if($vo['status'] == 2){
							$invoice_status = $vo['id'];
						}
					}
				}
			}
			unset($g['keywords']);
			unset($g['tips']);


			$goods[] = $g;

			//如果是预存宝商品，预存宝状态为1，渲染页支付方式去除预存宝支付
			if($g['type_id']==217){
				$yfb_open = 1;
			}

		}
		//支付方式
		$query_pay = (new Query())->select("t2.*")->from('{{order_pay_way_city}} AS t1')
				->leftJoin('{{order_pay_way}} AS t2','t1.pay_id=t2.id')
				->where(['t1.city'=>$user_info['city']])->andWhere(['t2.status'=>1]);
		$query_pay->andWhere(['!=','t2.pay_type',2]);//去除银行卡打款方式  code by :wmc
		if($user_info['denied_num'] >= 4 || $user_info['integrity_point'] <= 0 || $flag > 0) {//拒签次数大于4的, D3商品不让货到付款 $d2 == 2  ||
			$query_pay->andWhere(['!=','t2.pay_type',1]);
		}

		//非金银牌用户过滤
		if(!in_array($user_info['level'],[3,4])){
			$query_pay->andWhere(['!=','t2.pay_type',4]);
		}

		//存在预售商品，只接受在线支付
		if($ys_goods){
			$query_pay->andWhere(['t2.pay_type'=>[3,4]]);
		}

		$query_pay->orderBy('t2.pay_type asc');
		$pay_way_info = $query_pay->all();


		$pay_way_info = OrderPayWay::getWarpPayWayList($pay_way_info,[]);
		//f_d($query_pay->createCommand()->getRawSql());

		$separateInfo = OmsInfo::separateDepot($goods);

		$inDepot = isset($separateInfo['inDepot']) && is_array($separateInfo['inDepot']) ? $separateInfo['inDepot'] : [];
		$outDepot = isset($separateInfo['outDepot']) && is_array($separateInfo['outDepot']) ? $separateInfo['outDepot'] : [];
		//然后在仓的根据仓库depot_id,根据仓库拆单
		$inDepotArr = OmsInfo::depotById($inDepot);

		//然后揽件，根据supplier_id拆单
		$outDepotArr = OmsInfo::depotByGys($outDepot);

		//组装数据返回
		$order_data = array_merge($inDepotArr,$outDepotArr,$ys_goods);
		$order_temp = [];

		foreach ($order_data as $key=>$value){
			$k = 'order_num_'.$key;
			$order_temp[$k] = $value;
		}
		$order_data = $order_temp;
		//处理数据
		$total_goods_price = 0;
		$record_price=0;
		$types = [];
		$typePrice =[];
		//f_d($order_data);
		foreach ($order_data as $key=>$value) {
			foreach ($value as $k=>$v) {
				$order_data[$key][$k]['img_url'] = Photo::find()->select('img_url')->where(['id'=>$v['cover_id']])->scalar();
				$types[] = $v['type_id'];
				if(!isset($typePrice[$v['type_id']])) $typePrice[$v['type_id']] = 0.00;
				$typePrice[$v['type_id']] += $v['price'] * $v['num'];
				$total_goods_price += $v['price'] * $v['num'];
				//获取商品仓库 是否收取录串手续费
				$province = SelfGoods::getProvinceByGoodsId($v['goods_id']);  //为自备机并且返回省份
				if($province){
					$series_price = RecordSeries::getSeriesByProvince($province);  //返回手续费接口
					if($series_price){
						$order_data[$key][$k]['series_price'] = $series_price*$v['num']; //列表使用
						$record_price += $series_price*$v['num'];
					}else{
						$order_data[$key][$k]['series_price']='';
					}
				}else{
					$order_data[$key][$k]['series_price']='';
				}
			}
		}

		//运费接口
		//$yunfei_price = OmsInfo::getYunfei($types,$total_goods_price);
		$yunfei_price = OmsInfo::getYunfeiPromotion($typePrice,$promotion_id);

		//组合商品优惠总金额
		$total_youhui_price = PromotionCombine::getTotalPrices($promotion_id);

		//获取所有优惠券
		$coupon_youxiao = [];
		$coupon_wuxiao = [];
		$coupons = CouponUser::getAll($user_id);

		if($coupons) {
			foreach ($order_data as $order_num => $od) {
				foreach ($coupons as $key=>$value) {
					$rs = CouponInfo::mayUse($od,$value['coupon_id']);
					if($rs['status'] == 1) {
						$coupon_youxiao[$value['id']]['order_num'] = $order_num;
						unset($coupons[$key]);
					} else {
						$coupons[$key]['msg'] = $rs['msg'];
					}
				}
			}
		}
		foreach ($coupon_youxiao as $key=>$value) {
			$u_info = CouponUser::find()->where(['id'=>$key])->asArray()->one();
// 			f_d($u_info);
			$c_info = CouponInfo::find()->where(['id'=>$u_info['coupon_id']])->asArray()->one();
			$coupon_youxiao[$key]['start_time'] = $u_info['start_time'];
			$coupon_youxiao[$key]['end_time'] = $u_info['end_time'];
			$coupon_youxiao[$key]['amount'] = $c_info['amount'];
			$coupon_youxiao[$key]['coupon_name'] = $c_info['coupon_name'];
			$coupon_youxiao[$key]['type'] = CouponSelect::getTypeName($c_info['type_id']);
		}

		$coupon_wuxiao = $coupons;
		foreach ($coupon_wuxiao as $key => $value) {
			$c_info = CouponInfo::find()->where(['id'=>$value['coupon_id']])->asArray()->one();
			$coupon_wuxiao[$key]['coupon_name'] = $c_info['coupon_name'];
			$coupon_wuxiao[$key]['type'] = CouponSelect::getTypeName($c_info['type_id']);
			$coupon_wuxiao[$key]['amount'] = $c_info['amount'];
		}

		$this->layout = '_blank';
// 		f_d($coupon_youxiao);
		$ad = \common\models\other\AdInfo::getAd(112,$city,1);			//支付方式位置旁的广告位
		//f_d(json_encode($order_data));
		return $this->render('promotion_check_cart',[
				'address'=>$address,
				'address_moren'=>$address_moren,
				'shipping_info'=>$shipping_info,
				'pay_way_info'=>$pay_way_info,
				'order_data'=>$order_data,
				'yunfei'=>$yunfei_price,
				'total_goods_price'=>$total_goods_price,
				'record_price'=>$record_price,
				'coupon_wuxiao'=>$coupon_wuxiao,
				'coupon_youxiao'=>$coupon_youxiao,
				'shop_car_ids'=>[],
				'user_info'=>$this->user_info,
				'is_invoice'=>$is_invoice,
				'invoice'=>$invoice,
				'invoice_num'=>$invoice_num,
				'invoice_status' => $invoice_status,
				'goods_yfb_open'=>$yfb_open,
				'ad'=>$ad,
				'is_service' => $user_info['is_service'],
				'promotion_id' =>$promotion_id,
				'total_youhui_price'=>$total_youhui_price,
				'sole_goods_id'=>$sole_goods_id
				//'totalPrePayment'=>$totalPrePayment
		]);
	}
	/**
	 * @description:生成订单
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年11月3日下午7:20:07
	 * @modified_date: 2015年11月3日下午7:20:07
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionGenerOrder(){
		header("Content-Type: text/html; charset=UTF-8");
		$user_id = \Yii::$app->user->id;			//用户ID
		$address_id = f_post('address',0);			//收货地址
		$shipping_id = f_post('shipping_info',0);	//物流方式
		$pay_way = f_post('pay_way',0);				//支付方式
		$liuyan = f_post('liuyan','');				//留言
		$yunfei = f_post('yunfei',0);				//运费金额
		$come_from = f_post('come_from','PC购物车');	//订单来源
		$coupon_id = f_post('coupon_id',0);  		//使用优惠券的订单 key
		$order_num = f_post('order_num',''); 		//使用优惠券的订单 key
		$data = json_decode(f_post('data',''),true);		//订单数据
        $online_type = f_post('online_type',0);     //在线支付类型
		//用户信息
		$user_info = UserMember::find()->where(['id' => $user_id])->asArray()->one();
		//判断是否开通订货功能

		$address_info = ReceiveAddress::find()->where(['id' => $address_id])->asArray()->one();
		if(!CityFunctionManage::CheckOpenOrder($address_info['province'], $address_info['city'])){
			return $this->msg('区域为'.OtherRegion::getRegionName($address_info['province'])->region_name.OtherRegion::getRegionName($address_info['city'])->region_name.'市，暂未开通订货功能！','/cart/index');
		}
		
		//未开通的区县
		$distrity_screen = OtherCountyDistLimit::find()
			->where(['county_id'=>$address_info['district']])
			->andWhere(['<','start_time',date('Y-m-d H:i:s')])
			->andWhere(['>','end_time',date('Y-m-d H:i:s')])
			->asArray()->one();
		if($distrity_screen) {
			if(isset($distrity_screen['tips'])) {
				$tips = $distrity_screen['tips'];
			} else {
				$tips = '该地区未开通订货';
			}
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
					'type'=>'warn',
					'message'=>$tips,
					'is_next'=>'yes',
					'next_url'=>'/cart/index',
			]);
			return $view;exit;
		}
		
		//判断是否超过限购次数
		foreach($data as $value) {
		    foreach($value as $v){
    		    //该商品是否限购
    		    $isOverLimit = GoodsLimitOrder::isOverLimit($v['id'], $user_id);
    		    if($isOverLimit) {
    		        $view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
    		            'type'=>'warn',
    					'message'=>"订单中".$v['goods_name']."已超过商品的限购次数",
    					'is_next'=>'yes',
    					'next_url'=>'/cart/index',
    		        ]);
    		        return $view;exit;
    		    }
		    }
		}
		
		//超级帐号不让下单
		if($user_info['is_service'] == 1 || $user_info['is_service'] == 3){
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
				'type'=>'warn',
				'message'=>'客服帐号、白名单账号,不能提单',
				'is_next'=>'yes',
				'next_url'=>'/cart/index',
			]);
			return $view;exit;
		}
		//收货地址是否在配送范围内  判断
		if($user_id != 54909){ //判断是否蜂星帐号
			if($address_info['province'] != $user_info['province']){
				$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
						'type'=>'warn',
						'message'=>'收货地址与注册地址不一致,物流无法配送',
						'is_next'=>'yes',
						'next_url'=>'/cart/index',
				]);
				return $view;exit;
			}
		}
		//必须字段
		$params1 = [
				'user_id'=>$user_id,
				'address_id'=>$address_id,
				'shipping_id'=>$shipping_id,
				'pay_way'=>$pay_way,
				'liuyan'=>$liuyan,
				'come_from'=>$come_from,
				'yunfei'=>$yunfei,
		];
		//选填字段
		$params2 = [
				'coupon_id'=>$coupon_id,
				'order_num'=>$order_num,
				'if_back'=>false,//是否后台下单
				'note'=>'',//订单备注
		];
		if($user_id && $address_id && $shipping_id && $pay_way && $data) {
			$out=OmsInfo::generOrder($user_id,$address_id,$shipping_id,$pay_way,$liuyan,$come_from,$data,$yunfei,$coupon_id,$order_num,false,"",$online_type);
            if ($out['code'] == 0) { //下单失败
                if ($out['data']) {
                    foreach ($out['data'] as $value) {
						//f_d($value);//删除其余的订单,及订单行
						$order_infomation = OmsInfo::find()->where(["order_code"=>$value['order_code']])->asArray()->one();
						//推送日志也删掉
						OrderInterfaceLog::deleteAll(["order_code"=>$value['order_code']]);
						
						//取消订单
						$cancel_status = 20;
						if($order_infomation['pay_way'] == 3) { //type=2
							$cancel_status = 80;
						} else if($order_infomation['pay_way'] == 1){//type=3
							$cancel_status = 170;
						} else if($order_infomation['pay_way'] == 4){
							$cancel_status = 605;
						}
						OmsInfo::changeOrderStatus($order_infomation['id'],$cancel_status,'先删除后取消','系统执行');
						//订单，与订单行也删掉
						$del_info = OmsInfo::deleteAll(["order_code"=>$value['order_code']]);
						if($del_info) {
							OmsGoods::deleteAll(["order_code"=>$value['order_code']]);
						}
                    }
                }
                //去错误页面
                return $this->redirect(['/order-success/error', 'data' => serialize($out),]);
            } else {
                //下单成功
                //删除购物车中下单成功的记录
                $shop_car_ids_str = f_post('shop_car_ids', 0);
                if ($shop_car_ids_str) {
                    $shop_car_ids = explode(',', $shop_car_ids_str);
                } else {
                    $shop_car_ids = [];
                }
                if ($shop_car_ids) {
                    $shop_car_ids = array_unique($shop_car_ids); //为了去重
                    $shop_car_ids = implode(',', $shop_car_ids);
                    ShoppingCart::deleteAll(['id' => explode(',', $shop_car_ids)]);
                }
				//更新最后下单时间 和ip
				UserMember::setLastOrderTime($user_id);
                //下单成功时  将order_id拼接成字符串
                $order_ids = '';
                foreach ($out['data'] as $value) {
                    $order_code = $value;
                    $order_info = OmsInfo::find()->where(['order_code' => $value])->asArray()->one();
                    $order_ids .= $order_info['id'] . '-';
                   	if($pay_way == 3){
                   		//银行卡付款存信息
	                    $model = new PayBlankCard();
						$model->time = date('Y-m-d H:i:s');
						$model->bank_code = $_POST['card_num'];
						$model->order_code = $order_info['order_code'];
						$model->shop_name = $user_info['shop_name'];
						$model->open_name = $_POST['card_name'];
						$model->telphone = $_POST['card_phone'];
						$model->remark = $_POST['card_remark'];
						$model->save();
                   }
                   
                }

                $order_ids = rtrim($order_ids, '-');

                $pay_way = ($pay_way==99)?2:$pay_way; //内部采购需要调整为货到付款  code by :wmc  2016.5.19
                //根据支付方式确定跳转地址

                $pay_way_info = OrderPayWay::find()->where(['id' => $pay_way])->asArray()->one();
                if ($pay_way_info['pay_type'] == 1 || $pay_way_info['pay_type'] == 2) {
                    //跳转下单成功页面
                    return $this->redirect(['/order-success/index', 'order_id' => $order_ids,]);
                // } else if ($pay_way_info['pay_type'] == 2) {
                //     //跳转银行卡信息页面
                //     return $this->redirect(['/order-success/blank-card', 'order_id' => $order_ids,]);
                } else if (in_array($pay_way_info['pay_type'], [3, 4])) {
                    //跳转到支付页面                        /ucenter/online-payment?order_id=12-19
                    return $this->redirect(['/ucenter/online-payment', 'order_id' => $order_ids,]);
                }
            }
        }
    }
    
    /**
	 * @description:生成订单
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年11月3日下午7:20:07
	 * @modified_date: 2015年11月3日下午7:20:07
	 * @modified_user: sunkouping
	 * @review_user:
         * @modify:lxzmy 
         * @date:2016-6-1 16:41
	*/
	public function actionGenerOrderSeas(){
		header("Content-Type: text/html; charset=UTF-8");
		$user_id = \Yii::$app->user->id;			//用户ID
		$address_id = f_post('seas_address_post',0);			//收货地址
		$shipping_id = f_post('shipping_info',0);	//物流方式
		$pay_way = f_post('pay_way',0);				//支付方式
		$liuyan = f_post('liuyan','');				//留言
		$yunfei = f_post('yunfei',0);				//运费金额
		$come_from = f_post('come_from','跨境商品');	//订单来源
		$coupon_id = f_post('coupon_id',0);  		//使用优惠券的订单 key
		$order_num = f_post('order_num',''); 		//使用优惠券的订单 key
		$data = json_decode(f_post('data',''),true);		//订单数据
                $online_type = f_post('online_type',0);     //在线支付类型
		//用户信息
		$user_info = UserMember::find()->where(['id' => $user_id])->asArray()->one();
		//判断是否开通订货功能

		$address_info = \common\models\seas\SeasUserAddress::find()->where(['id' => $address_id])->asArray()->one();
		if(!CityFunctionManage::CheckOpenOrder($address_info['province'], $address_info['city']) && $address_info['city'] != 391){
			return $this->msg('区域为'.OtherRegion::getRegionName($address_info['province'])->region_name.OtherRegion::getRegionName($address_info['city'])->region_name.'市，暂未开通订货功能！','/seas-goods/index');
		}
		
		//未开通的区县
		$distrity_screen = OtherCountyDistLimit::find()
			->where(['county_id'=>$address_info['district']])
			->andWhere(['<','start_time',date('Y-m-d H:i:s')])
			->andWhere(['>','end_time',date('Y-m-d H:i:s')])
			->asArray()->one();
		if($distrity_screen && $address_info['city'] != 391) {
			if(isset($distrity_screen['tips'])) {
				$tips = $distrity_screen['tips'];
			} else {
				$tips = '该地区未开通订货';
			}
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
					'type'=>'warn',
					'message'=>$tips,
					'is_next'=>'yes',
					'next_url'=>'/seas-goods/index',
			]);
			return $view;exit;
		}
		
		//判断是否超过限购次数
		foreach($data as $value) {
		    foreach($value as $v){
    		    //该商品是否限购
    		    $isOverLimit = GoodsLimitOrder::isOverLimit($v['id'], $user_id);
    		    if($isOverLimit) {
    		        $view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
    		            'type'=>'warn',
    					'message'=>"订单中".$v['goods_name']."已超过商品的限购次数",
    					'is_next'=>'yes',
    					'next_url'=>'/seas-goods/index',
    		        ]);
    		        return $view;exit;
    		    }
		    }
		}
		
		//上下架 回收站
		foreach($data as $value){
			foreach($value as $v){
				if($v['status'] == 0 || $v['enable'] != 1 || $v['is_deleted'] ==1){
					$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
							'type'=>'warn',
							'message'=>"订单中".$v['goods_name']."已下架",
							'is_next'=>'yes',
							'next_url'=>'/seas-goods/index',
					]);
					return $view;exit;
				}
			}
		}
		//超级帐号不让下单
		if($user_info['is_service'] == 1 || $user_info['is_service'] == 3){
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
				'type'=>'warn',
				'message'=>'客服帐号、白名单账号,不能提单',
				'is_next'=>'yes',
				'next_url'=>'/seas-goods/index',
			]);
			return $view;exit;
		}
		//收货地址是否在配送范围内  判断
//		if($user_id != 54909){ //判断是否蜂星帐号
//			if($address_info['province'] != $user_info['province']){
//				$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
//						'type'=>'warn',
//						'message'=>'收货地址与注册地址不一致,物流无法配送',
//						'is_next'=>'yes',
//						'next_url'=>'/seas-goods/index',
//				]);
//				return $view;exit;
//			}
//		}
		//必须字段
		$params1 = [
				'user_id'=>$user_id,
				'address_id'=>$address_id,
				'shipping_id'=>$shipping_id,
				'pay_way'=>$pay_way,
				'liuyan'=>$liuyan,
				'come_from'=>$come_from,
				'yunfei'=>$yunfei,
		];
		//选填字段
		$params2 = [
				'coupon_id'=>$coupon_id,
				'order_num'=>$order_num,
				'if_back'=>false,//是否后台下单
				'note'=>'',//订单备注
		];
		if($user_id && $address_id && $shipping_id && $pay_way && $data) {
                    //一个商品一个订单
                            $out=  SeasUserSupplier::generOrder($user_id,$address_id,$shipping_id,$pay_way,$liuyan,$come_from,$data,$yunfei,$coupon_id,$order_num,false,"",$online_type);
                        
			
            if ($out['code'] == 0) { //下单失败
                if ($out['data']) {
                    foreach ($out['data'] as $value) {
						//f_d($value);//删除其余的订单,及订单行
						$order_infomation = OmsInfo::find()->where(["order_code"=>$value['order_code']])->asArray()->one();
						//推送日志也删掉
						OrderInterfaceLog::deleteAll(["order_code"=>$value['order_code']]);
						
						//取消订单
						$cancel_status = 20;
						if($order_infomation['pay_way'] == 3) { //type=2
							$cancel_status = 80;
						} else if($order_infomation['pay_way'] == 1){//type=3
							$cancel_status = 170;
						} else if($order_infomation['pay_way'] == 4){
							$cancel_status = 605;
						}
						OmsInfo::changeOrderStatus($order_infomation['id'],$cancel_status,'先删除后取消','系统执行');
						//订单，与订单行也删掉
						$del_info = OmsInfo::deleteAll(["order_code"=>$value['order_code']]);
						if($del_info) {
							OmsGoods::deleteAll(["order_code"=>$value['order_code']]);
						}
                    }
                }
                //去错误页面
                return $this->redirect(['/order-success/error', 'data' => serialize($out),]);
            } else {
                //下单成功
                //删除购物车中下单成功的记录
                $shop_car_ids_str = f_post('shop_car_ids', 0);
                if ($shop_car_ids_str) {
                    $shop_car_ids = explode(',', $shop_car_ids_str);
                } else {
                    $shop_car_ids = [];
                }
                if ($shop_car_ids) {
                    $shop_car_ids = array_unique($shop_car_ids); //为了去重
                    $shop_car_ids = implode(',', $shop_car_ids);
                    ShoppingCart::deleteAll(['id' => explode(',', $shop_car_ids)]);
                }
				//更新最后下单时间 和ip
				UserMember::setLastOrderTime($user_id);
                //下单成功时  将order_id拼接成字符串
                $order_ids = '';
                foreach ($out['data'] as $value) {
                    $order_code = $value;
                    $order_info = OmsInfo::find()->where(['order_code' => $value])->asArray()->one();
                    $order_ids .= $order_info['id'] . '-';
//                   	if($pay_way == 3){
//                   		//银行卡付款存信息
//                                $model = new PayBlankCard();
//                                $model->time = date('Y-m-d H:i:s');
//                                $model->bank_code = $_POST['card_num'];
//                                $model->order_code = $order_info['order_code'];
//                                $model->shop_name = $user_info['shop_name'];
//                                $model->open_name = $_POST['card_name'];
//                                $model->telphone = $_POST['card_phone'];
//                                $model->remark = $_POST['card_remark'];
//                                $model->save();
//                   }
                }

                $order_ids = rtrim($order_ids, '-');

                $pay_way = ($pay_way==99)?2:$pay_way; //内部采购需要调整为货到付款  code by :wmc  2016.5.19
                //根据支付方式确定跳转地址

                $pay_way_info = OrderPayWay::find()->where(['id' => $pay_way])->asArray()->one();
                if ($pay_way_info['pay_type'] == 1 || $pay_way_info['pay_type'] == 2) {
                    //跳转下单成功页面
                    return $this->redirect(['/order-success/index', 'order_id' => $order_ids,]);
                // } else if ($pay_way_info['pay_type'] == 2) {
                //     //跳转银行卡信息页面
                //     return $this->redirect(['/order-success/blank-card', 'order_id' => $order_ids,]);
                } else if (in_array($pay_way_info['pay_type'], [3, 4])) {
                    //跳转到支付页面                        /ucenter/online-payment?order_id=12-19
                    return $this->redirect(['/ucenter/online-payment', 'order_id' => $order_ids,]);
                }
            }
        }
    }

    //验证收货地址
    public function actionCheckAddress() {
        header("Content-Type: text/html; charset=UTF-8");
        
        $address_id = f_post('add', 0);   //收货地址
        $res = 1;
        //用户信息
        $user_info = $this->user_info;
        //收货地址是否在配送范围内  判断
        if($address_id != 0) {
            $address_info = ReceiveAddress::find()->where(['id' => $address_id])->asArray()->one();
            if ($user_info['id'] != 54909) { //判断是否蜂星帐号
                if ($address_info['province'] != $user_info['province']) {
                    $res = 2;
                    //return $this->msg('收货地址与注册地址不一致,物流无法配送', '/seas-goods/index');
                }
            }
        } else {
            $res = 3;
        }
        return $res;
    }
    /**
     * @description:生成秒杀订单
     * @return: return_type
     * @author: sunkouping
     * @date: 2015年11月3日下午7:20:07
     * @modified_date: 2015年11月3日下午7:20:07
     * @modified_user: sunkouping
     * @review_user:
     */
    public function actionGenerOrderSeckill(){
    	header("Content-Type: text/html; charset=UTF-8");
    	$user_id = \Yii::$app->user->id;			//用户ID
    	//用户信息
    	$user_info = UserMember::find()->where(['id' => $user_id])->asArray()->one();
    	$address_id = f_post('address',0);			//收货地址
    	$shipping_id = f_post('shipping_info',0);	//物流方式
    	$pay_way = f_post('pay_way',0);				//支付方式
    	$liuyan = f_post('liuyan','');				//留言
    	$yunfei = f_post('yunfei',0);				//运费金额
    	$come_from = f_post('come_from','秒杀活动');	//订单来源
    	$coupon_id = f_post('coupon_id',0);  		//使用优惠券的订单 key
    	$order_num = f_post('order_num',''); 		//使用优惠券的订单 key
    	$data = json_decode(f_post('data',''),true);		//订单数据
    	$online_type = f_post('online_type',0);
        if(isset($_POST['active_id']) && isset($_POST['goods_id']) && isset($_POST['goods_num'])){
            $active_id =  $_POST['active_id'];
            $goods_id =  $_POST['goods_id'];
            $goods_num = $_POST['goods_num'];
        }else{
            return $this->msg('缺少参数!', "/home/index");
        }
    	
    	
    	$key = 'seckillorder_'.$active_id.'_'.$goods_id;

    	$order_buy_num = f_c($key);
    	if($order_buy_num === false) {
    		f_c($key,0,150);
    		$order_buy_num = 0;
    	}
    	
    	$goods_status = SeckillGoods::find()->where(['active_id'=>$active_id,'goods_id'=>$goods_id])->asArray()->one();
    	
    	if($order_buy_num >= $goods_status['store']) {
    		return $this->msg('商品已被抢完了', "/seckill/index?goods_id=$goods_id&active_id=$active_id");
    	}
    	/**
    	 * 已被购买的数量
    	 */
    	$had_buy_num = SeckillOrder::getThisHadBuy($active_id, $goods_id);
    	
    	if($goods_status['store'] <= $had_buy_num ) {
    		return $this->msg('商品已售罄了', "/seckill/index?goods_id=$goods_id&active_id=$active_id");
    	}
    		
    	
    	$had_buy_num = $had_buy_num + $goods_num;
    	if($goods_status['store'] < $had_buy_num ) {
    		return $this->msg('剩余库存不足', "/seckill/index?goods_id=$goods_id&active_id=$active_id");
    	}
    	/**
    	 * 单人限制购买的数量
    	 */
    	$me_had_buy = SeckillOrder::myhadbuy($user_id,$active_id,$goods_id);
    	$want_buy_all = $me_had_buy + $goods_num;
    	if($want_buy_all > $goods_status['end_num']) {
    		return $this->msg("亲，您已经买过本商品了噢", "/seckill/index?goods_id=$goods_id&active_id=$active_id");
    	}
    	//插入秒杀购买记录信息
    	$model = new SeckillOrder();
    	$model->active_id = $active_id;  	//'活动编号',
        $model->ms_goods_id = $goods_id;	//商品表编号',
        $model->user_id = $user_id;
        $model->user_name =$user_info['user_name'];
        $model->buy_time = f_date(time());
        $model->buy_num = $goods_num;
        $model->order_code = '暂无';
        $model->city_id = $user_info['city'];
    	if($model->save()) {
    		//插入redis记录
    		f_c($key,($order_buy_num+$goods_num),36000);
    		//获取秒杀记录的条数
    		$t = floor($goods_status['store']/$goods_status['start_num']);
    		$had_ids = SeckillOrder::find()->where(['active_id'=>$active_id,'ms_goods_id'=>$goods_id])->limit($t)->asArray()->all();
    		$had_ids = ArrayHelper::getColumn($had_ids, 'id');
    		if(in_array($model->id,$had_ids)) {
    			//验证通过
    			//判断是否开通订货功能
    			$address_info = ReceiveAddress::find()->where(['id' => $address_id])->asArray()->one();
    			if(!CityFunctionManage::CheckOpenOrder($address_info['province'], $address_info['city'])){
    				return $this->msg('区域为'.OtherRegion::getRegionName($address_info['province'])->region_name.OtherRegion::getRegionName($address_info['city'])->region_name.'市，暂未开通订货功能！','/cart/index');
    			}
    			//未开通区县的限制
    			$distrity_screen = OtherCountyDistLimit::find()
    			->where(['county_id'=>$address_info['district']])
    			->andWhere(['<','start_time',date('Y-m-d H:i:s')])
    			->andWhere(['>','end_time',date('Y-m-d H:i:s')])
    			->asArray()->one();
    			if($distrity_screen) {
    				if(isset($distrity_screen['tips'])) {
    					$tips = $distrity_screen['tips'];
    				} else {
    					$tips = '该地区未开通订货';
    				}
    				$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
    						'type'=>'warn',
    						'message'=>$tips,
    						'is_next'=>'yes',
    						'next_url'=>'/cart/index',
    				]);
    				return $view;exit;
    			}
    			//超级帐号不让下单
    			if($user_info['is_service'] == 1){
    				return $this->msg('客服帐号,不能提单', '/cart/index');
    			}
    			//收货地址是否在配送范围内  判断
    			if($user_id != 54909){ //判断是否蜂星帐号
    				if($address_info['province'] != $user_info['province']){
    					return $this->msg('收货地址与注册地址不一致,物流无法配送','/cart/index');
    				}
    			}
    			
    			if($user_id && $address_id && $shipping_id && $pay_way && $data) {
    				$out=OmsInfo::generOrder($user_id,$address_id,$shipping_id,$pay_way,$liuyan,$come_from,$data,$yunfei,$coupon_id,$order_num,false,"",$online_type);
    				//f_d($out);
    				if ($out['code'] == 0) { //下单失败
    					if ($out['data']) {
    						foreach ($out['data'] as $value) {
    							//f_d($value);//删除其余的订单,及订单行
    							OmsGoods::deleteAll('order_code=' . $value['order_code']);
    							OmsInfo::deleteAll('order_code=' . $value['order_code']);
    						}
    					}
    			
    					//去错误页面
    					return $this->redirect(['/order-success/error', 'data' => serialize($out),]);
    				} else {
    					//下单成功
    					//删除购物车中下单成功的记录
    					$shop_car_ids_str = f_post('shop_car_ids', 0);
    					if ($shop_car_ids_str) {
    						$shop_car_ids = explode(',', $shop_car_ids_str);
    					} else {
    						$shop_car_ids = [];
    					}
    					if ($shop_car_ids) {
    						$shop_car_ids = array_unique($shop_car_ids); //为了去重
    						$shop_car_ids = implode(',', $shop_car_ids);
    						ShoppingCart::deleteAll(['id' => explode(',', $shop_car_ids)]);
    					}
    					//最后下单时间
    					UserMember::setLastOrderTime($user_id);
    					//下单成功时  将order_id拼接成字符串
    					$order_ids = '';
    					foreach ($out['data'] as $value) {
    						$order_code = $value;
    						$order_info = OmsInfo::find()->where(['order_code' => $value])->asArray()->one();
    						$order_ids .= $order_info['id'] . '-';
    					}
    			
    					$order_ids = rtrim($order_ids, '-');
    			
    					//根据支付方式确定跳转地址
    					$pay_way_info = OrderPayWay::find()->where(['id' => $pay_way])->asArray()->one();
    					if ($pay_way_info['pay_type'] == 1) {
    						//跳转下单成功页面
    						return $this->redirect(['/order-success/index', 'order_id' => $order_ids,]);
    					} else if ($pay_way_info['pay_type'] == 2) {
    						//跳转银行卡信息页面
    						return $this->redirect(['/order-success/blank-card', 'order_id' => $order_ids,]);
    					} else if (in_array($pay_way_info['pay_type'], [3, 4])) {
    						//跳转到支付页面                        /ucenter/online-payment?order_id=12-19
    						return $this->redirect(['/ucenter/online-payment', 'order_id' => $order_ids,]);
    					}
    				}
    			}
    		} else {
    			//删除 记录
    			$model->delete();
    			return $this->msg("亲，你下手太慢了", "/seckill/index?goods_id=$goods_id&active_id=$active_id");
    		}
    	} else {
    		return $this->msg("亲，出单失败", "/seckill/index?goods_id=$goods_id&active_id=$active_id");
    	}
    	
    }

	/**
	 * @description:立即购买组合商品的提交订单
	 * @return:
	 * @author: jr
	 * @date:
	 * @modified_date:
	 * @modified_user: jr
	 * @review_user:
	 */
	public function actionGenerOrderPromotion()
	{
		header("Content-Type: text/html; charset=UTF-8");
		$user_id = \Yii::$app->user->id;			//用户ID
		$address_id = f_post('address',0);			//收货地址
		$shipping_id = f_post('shipping_info',0);	//物流方式
		$pay_way = f_post('pay_way',0);				//支付方式
		$liuyan = f_post('liuyan','');				//留言
		$yunfei = f_post('yunfei',0);				//运费金额
		$come_from = f_post('come_from','PC购物车组合');	//订单来源
		$coupon_id = f_post('coupon_id',0);  		//使用优惠券的订单 key
		$order_num = f_post('order_num',''); 		//使用优惠券的订单 key
		$data = json_decode(f_post('data',''),true);		//订单数据
		$online_type = f_post('online_type',0);     //在线支付类型
		$promotion_id = f_post('promotion_id',0);  //组合id
		$sole_goods_id = f_post('sole_goods_id',0);
		//用户信息
		$user_info = UserMember::find()->where(['id' => $user_id])->asArray()->one();
		//判断是否开通订货功能

		$address_info = ReceiveAddress::find()->where(['id' => $address_id])->asArray()->one();
		if(!CityFunctionManage::CheckOpenOrder($address_info['province'], $address_info['city'])){
			return $this->msg('区域为'.OtherRegion::getRegionName($address_info['province'])->region_name.OtherRegion::getRegionName($address_info['city'])->region_name.'市，暂未开通订货功能！',"/goods/detail?goods_id=$sole_goods_id");
		}

		//未开通的区县
		$distrity_screen = OtherCountyDistLimit::find()
				->where(['county_id'=>$address_info['district']])
				->andWhere(['<','start_time',date('Y-m-d H:i:s')])
				->andWhere(['>','end_time',date('Y-m-d H:i:s')])
				->asArray()->one();
		if($distrity_screen) {
			if(isset($distrity_screen['tips'])) {
				$tips = $distrity_screen['tips'];
			} else {
				$tips = '该地区未开通订货';
			}
			return $this->msg($tips, "/detail/index?goods_id=$sole_goods_id");
		}
		//组合活动是否已失效
		$promotion_status = PromotionCombine::getGoodsId($promotion_id);
		if(!$promotion_status){
			return $this->msg("亲，此组合商品已失效请重新选购", "/detail/index?goods_id=$sole_goods_id");
		}

		//上下架 回收站
		foreach($data as $value){
			foreach($value as $v){
				if($v['status'] == 0 || $v['enable'] != 1 || $v['is_deleted'] ==1){
					return $this->msg("订单中".$v['goods_name']."已下架", "/detail/index?goods_id=$sole_goods_id");
					exit;
				}
			}
		}
		//超级帐号不让下单
		if($user_info['is_service'] == 1 || $user_info['is_service'] == 3){
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
					'type'=>'warn',
					'message'=>'客服帐号、白名单账号,不能提单',
					'is_next'=>'yes',
					'next_url'=>'/cart/index',
			]);
			return $view;exit;
		}
		//收货地址是否在配送范围内  判断
		if($user_id != 54909){ //判断是否蜂星帐号
			if($address_info['province'] != $user_info['province']){
				$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
						'type'=>'warn',
						'message'=>'收货地址与注册地址不一致,物流无法配送',
						'is_next'=>'yes',
						'next_url'=>'/cart/index',
				]);
				return $view;exit;
			}
		}

		if($user_id && $address_id && $shipping_id && $pay_way && $data) {
			$out=OmsInfo::generOrder($user_id,$address_id,$shipping_id,$pay_way,$liuyan,$come_from,$data,$yunfei,$coupon_id,$order_num,false,"",$online_type);
			if ($out['code'] == 0) { //下单失败
				if ($out['data']) {
					foreach ($out['data'] as $value) {
						//f_d($value);//删除其余的订单,及订单行
						$order_infomation = OmsInfo::find()->where(["order_code"=>$value['order_code']])->asArray()->one();
						//推送日志也删掉
						OrderInterfaceLog::deleteAll(["order_code"=>$value['order_code']]);

						//取消订单
						$cancel_status = 20;
						if($order_infomation['pay_way'] == 3) { //type=2
							$cancel_status = 80;
						} else if($order_infomation['pay_way'] == 1){//type=3
							$cancel_status = 170;
						} else if($order_infomation['pay_way'] == 4){
							$cancel_status = 605;
						}
						OmsInfo::changeOrderStatus($order_infomation['id'],$cancel_status,'先删除后取消','系统执行');
						//订单，与订单行也删掉
						$del_info = OmsInfo::deleteAll(["order_code"=>$value['order_code']]);
						if($del_info) {
							OmsGoods::deleteAll(["order_code"=>$value['order_code']]);
						}
					}
				}
				//去错误页面
				return $this->redirect(['/order-success/error', 'data' => serialize($out),]);
			} else {
				//下单成功
				//删除购物车中下单成功的记录
				$shop_car_ids_str = f_post('shop_car_ids', 0);
				if ($shop_car_ids_str) {
					$shop_car_ids = explode(',', $shop_car_ids_str);
				} else {
					$shop_car_ids = [];
				}
				if ($shop_car_ids) {
					$shop_car_ids = array_unique($shop_car_ids); //为了去重
					$shop_car_ids = implode(',', $shop_car_ids);
					ShoppingCart::deleteAll(['id' => explode(',', $shop_car_ids)]);
				}
				//更新最后下单时间 和ip
				UserMember::setLastOrderTime($user_id);
				//下单成功时  将order_id拼接成字符串
				$order_ids = '';
				foreach ($out['data'] as $value) {
					$order_code = $value;
					$order_info = OmsInfo::find()->where(['order_code' => $value])->asArray()->one();
					$order_ids .= $order_info['id'] . '-';
					if($pay_way == 3){
						//银行卡付款存信息
						$model = new PayBlankCard();
						$model->time = date('Y-m-d H:i:s');
						$model->bank_code = $_POST['card_num'];
						$model->order_code = $order_info['order_code'];
						$model->shop_name = $user_info['shop_name'];
						$model->open_name = $_POST['card_name'];
						$model->telphone = $_POST['card_phone'];
						$model->remark = $_POST['card_remark'];
						$model->save();
					}
				}

				$order_ids = rtrim($order_ids, '-');

				$pay_way = ($pay_way==99)?2:$pay_way; //内部采购需要调整为货到付款  code by :wmc  2016.5.19
				//根据支付方式确定跳转地址

				$pay_way_info = OrderPayWay::find()->where(['id' => $pay_way])->asArray()->one();
				if ($pay_way_info['pay_type'] == 1 || $pay_way_info['pay_type'] == 2) {
					//跳转下单成功页面
					return $this->redirect(['/order-success/index', 'order_id' => $order_ids,]);
					// } else if ($pay_way_info['pay_type'] == 2) {
					//     //跳转银行卡信息页面
					//     return $this->redirect(['/order-success/blank-card', 'order_id' => $order_ids,]);
				} else if (in_array($pay_way_info['pay_type'], [3, 4])) {
					//跳转到支付页面                        /ucenter/online-payment?order_id=12-19
					return $this->redirect(['/ucenter/online-payment', 'order_id' => $order_ids,]);
				}
			}
		}
	}
	
	/**
	 * @description:新版结算页
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2016年6月30日下午1:34:57
	 * @modified_date: 2016年6月30日下午1:34:57
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionCheckGoods(){
		header("Content-Type: text/html; charset=UTF-8");
		$this->view->title = '确认订单';
		$come_from = 'frontend';
		$this->layout = '_blank';
		$user_id = \Yii::$app->user->id;
		$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		$type = 0;
		//用户状态校验
		if($user_info['user_status'] != 1){
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',['type'=>'warn','message'=>'帐号状态异常,即将退出登录','is_next'=>'yes','next_url'=>'/site/logout']);
			return $view;exit;
		}
		$city = $user_info['city'];
		//收货地址
		$address_info = Skp::getCommonAddress(['user_id'=>$user_id,]);
    	$address_moren = $address_info['address_moren'];
    	$address = $address_info['address'];
    	
		//获得商品数据[[goods_id=>'',goods_num=>'',goods_price=>''],[],]
		$car_data = f_post('d2_goods',[]);//购物车内的主键一维数组
		if($car_data) {
			$come_from = '购物车-PC';
			//解锁购物车商品
			$connection = Yii::$app->db;
			$sql = 'update user_shopping_cart set is_lock = 0 where user_id = '.$user_id;
			$connection->createCommand($sql)->execute();
			//查询数据
			$data = ShoppingCart::find()->select('goods_id,goods_num')->where(['in','id',$car_data])->asArray()->all();
        }else{
            $view = Yii::$app->view->render('@common/widgets/alert/Alert.php',['type'=>'warn','message'=>'未检测到商品','is_next'=>'yes','next_url'=>'/site/logout']);
			return $view;exit; 
        }
		//加入赠品的 的商品
		$data = GiftActive::getGiftGoods($data,$user_info);
		//f_d($data);
		if(!$data){
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',['type'=>'warn','message'=>'未检测到商品','is_next'=>'yes','next_url'=>'/site/logout']);
			return $view;exit;
		}
		//订单拆单第三个参数  活动类型   活动id
		$order_data = Skp::demergeOrder($data,$user_info,['type'=>$type,'id'=>0,'special_type'=>1]);
		$orders = $order_data['order'];
		//f_d($orders);
		//是否预付宝商品
		$yfb_open = $order_data['yfb_open'];
		//是否含有pos机
		$has_pos = $order_data['has_pos'];
		$flag = 0;
		if($has_pos) {
			$flag += 1;
		}
		//是否存在预售商品
		$has_ys = $order_data['has_ys'];
		//商品数组
		$preGoodsInfo = $order_data['preGoodsInfo'];
		//类型数组
		$type_arr = $order_data['type_arr'];
		//是否支持内部采购
		$inner_buy_status = $order_data['inner_buy_status'];//[4,7]
		//是否开通 预付宝
		$yfb_open = 0;
		$user_yfb_open = (isset($this->user_info['yfb_open']) && $this->user_info['yfb_open'])?1:0;
		//获取订单商品的供应商id
		$supplier_ids = [];
		foreach($orders as $order){
			foreach($order as $value){
				foreach($value['oms_goods'] as $vo){
					$supplier_ids[] = $vo['supplier_id'];
				}

			}
		}
		//判断是否第三方物流
		$tpl = Supplier::geiIsTpl($supplier_ids);
		if($tpl) {
		    //如果是第三方物流配送的，配送方式只显示第三方物流
		    $shipping_info = Skp::getTpl(['city'=>$city]);
		} else {
		    //物流信息
		    $shipping_info = Skp::getCommonShipping(['city'=>$city]);
		}
		
		//支付方式
    	$pay_way_info = Skp::getPayway(['user_info'=>$user_info,'flag'=>$flag,'has_ys'=>$has_ys,
    			'preGoodsInfo'=>$preGoodsInfo,'type_arr'=>$type_arr,'inner_buy_status'=>$inner_buy_status,'tpl'=>$tpl]);
    	$totalPrePayment = $pay_way_info['totalPrePayment'];
    	$pay_way_info = $pay_way_info['pay_way_info'];
    	//广告位
		$ad = \common\models\other\AdInfo::getAd(112,$city,1);			//支付方式位置旁的广告位
		//获取所有优惠券
		$coupon_info = Skp::checkCouon($orders,$user_info);
		$coupon_wuxiao = $coupon_info['coupon_wuxiao'];
		$coupon_youxiao = $coupon_info['coupon_youxiao'];
		//返券
		$check_code = f_checkCode();
		f_c($check_code,$orders,1800);
		
		//f_d($orders);
		return $this->render('check_goods',[
				'address'=>$address,
				'address_moren'=>$address_moren,
				'shipping_info'=>$shipping_info,
				'pay_way_info'=>$pay_way_info,
				'order_data'=>$orders,
				'coupon_wuxiao'=>$coupon_wuxiao,
				'coupon_youxiao'=>$coupon_youxiao,
				'user_info'=>$this->user_info,
				'goods_yfb_open'=>$yfb_open,
				'ad'=>$ad,
				'totalPrePayment'=>$totalPrePayment,
				'check_code'=>$check_code,
				'come_from'=>$come_from,
				'tpl'=>$tpl
		]);
	}

	/**
	 * @description:生成订单
	 * @return: \frontend\components\:|string|\yii\web\Response
	 * @author: sunkouping
	 * @date: 2016年6月30日下午3:55:30
	 * @modified_date: 2016年6月30日下午3:55:30
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionGenerGoods(){
		header("Content-Type: text/html; charset=UTF-8");
		$user_id = \Yii::$app->user->id;
		
		$address_id = f_post('address',0);
		$shipping_id = f_post('shipping_info',0);
		$pay_way = f_post('pay_way',0);
		$liuyan = f_post('liuyan','');
		$note = f_post('order_tips' ,'');
		$check_code = f_post('check_code','');
		$come_from =f_post('come_from','f');
		$online_type =f_post('online_type','0');
		$coupon_id = f_post('coupon_id',0);
		$coupon_code = f_post('order_num','');
		$insurance = f_post('insurance','');
		$insurance = json_decode($insurance, TRUE);

		//用户信息
		$user_info = UserMember::find()->where(['id' => $user_id])->asArray()->one();
		//判断是否开通订货功能
		if(!CityFunctionManage::CheckOpenOrder($user_info['province'], $user_info['city'])){
			return $this->msg('区域暂未开通订货功能！','/cart/index?user_id='.$user_id);
		}
		//收货地址信息
		$address_info = ReceiveAddress::find()->where(['id' => $address_id])->asArray()->one();
		//是否屏蔽区县
		$distrity_screen = OtherCountyDistLimit::find()->where(['county_id'=>$address_info['district']])
			->andWhere(['<','start_time',date('Y-m-d H:i:s')])
			->andWhere(['>','end_time',date('Y-m-d H:i:s')])->asArray()->one();
		if($distrity_screen) {
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',['type'=>'warn','message'=>'此地区暂时无法配送','is_next'=>'yes','next_url'=>'/cart/index',]);
			return $view;exit;
		}
		//收货地址是否在配送范围内  判断
		if($user_id != 28761) {
			if($address_info['city'] != $user_info['city']){
				return $this->msg('收货地址与注册地址不一致,物流无法配送','/cart/index?user_id='.$user_id);
			}
		}
		//超级帐号不让下单
		if($user_info['is_service'] == 1 || $user_info['is_service'] == 3){
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
					'type'=>'warn',
					'message'=>'客服帐号、白名单账号,不能提单',
					'is_next'=>'yes',
					'next_url'=>'/cart/index',
			]);
			return $view;exit;
		}
		
		if($user_id && $address_id && $shipping_id && $pay_way && $come_from && $check_code) {
			$order_data = f_c($check_code);
			if(!$order_data) {
				return '没有数据';
			}
			//准备订单数据 
			$success_order = [];
			foreach ($order_data as $mall_id=>$mall_orders) {
				
				foreach ($mall_orders as $order_code => $order_details) {
					$order_goods_price = 0;
					$credit_price = 0;
					$total_insurance = 0;
					$mall_insurance = [];
					if(is_array($insurance) && !empty($insurance)){
						if(array_key_exists($order_code,$insurance)){
							$mall_insurance = $insurance[$order_code];
						}
					}
					$total_insurance = 0;
					foreach ($order_details['oms_goods'] as $k=>$v) {
						$goods_price = $v['goods_price'];
						$order_goods_price += $goods_price * $v['goods_num'];
						$credit_price += $v['minus_price'] * $v['goods_num'];
						if(is_array($mall_insurance) && !empty($mall_insurance)){
							if(in_array($v['id'],$mall_insurance)){
								$total_insurance +=  $v['insurance'] * $v['goods_num'];
								$order_data[$mall_id][$order_code]['oms_goods'][$k]['insurance'] = $v['insurance'];//保险费用
							}else{
								$order_data[$mall_id][$order_code]['oms_goods'][$k]['insurance'] = 0;//保险费用
							}
						}else{
							$order_data[$mall_id][$order_code]['oms_goods'][$k]['insurance'] =0;//保险费用
						}
							
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['goods_id'] = $v['id'];    //供应商商品ID',
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['goods_name'] = $v['goods_name'];//商品名称',
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['goods_num'] = $v['goods_num']; 	//商品数量',
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['goods_price'] = $goods_price;//$v['goods_price'];//商品价格',
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['goods_color'] = $v['color_id'];//颜色',
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['code'] = $v['goods_code'];//商品货号',
							
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['promotion_id'] = $v['promotion_id'];//组合id
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['promotion_name'] = $v['promotion_name'];//组合名称
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['discount_type'] = $v['discount_type'];//立减类型1为51优惠，2为供应商立减',
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['minus_price'] = $v['minus_price'];//立减金额
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['ms_discount'] = $v['ms_discount'];//平台优惠说明
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['ms_discount_price'] = $v['ms_discount_price'];//平台优惠金额
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['gys_discount'] = $v['gys_discount'];//供应商优惠说明
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['gys_discount_price'] = $v['gys_discount_price'];//供应商优惠金额
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['add_price'] = $v['add_price'];//加价
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['is_self'] = $v['is_self'];//自备机
						$order_data[$mall_id][$order_code]['oms_goods'][$k]['is_contract'] = $v['is_contract'];//合约
						//$order_data[$mall_id][$order_code]['oms_goods'][$k]['insurance'] = $v['insurance'] * $v['goods_num'];//保险费用
							
					}
					if($order_code == $coupon_code) {
						$coupon_user = CouponUser::find()->where(['id' => $coupon_id])->one();
						$coupon_info = CouponInfo::find()->where(['id' => $coupon_user['coupon_id']])->asArray()->one();
						$coupon = $coupon_info['amount'];//优惠券金额
						//红包类型
						$coupon_from = 1;
						if ($coupon_info['type_id'] == 4) { //是否供应商优惠券
							$coupon_from = 2;
						}else if($coupon_info['type_id'] == 5 && $coupon_info['creator'] != 0){//是否供应商优惠券
							$coupon_from = 2;
						}
						$this_coupon_id = $coupon_id;
					}else {
						$coupon = 0;
						$coupon_from = 0;
						$this_coupon_id = 0;
					}
					//物流名称
					$express_name = OrderShippingWay::getShipping($shipping_id);
					if($express_name == '未知') {
					    $express_name = '蜂云物流';
					}
					
					//红包信息
					$order_data[$mall_id][$order_code]['red_bag'] = $coupon;                     //红包',
					$order_data[$mall_id][$order_code]['red_bag_from'] = $coupon_from;           //红包来源,
					$order_data[$mall_id][$order_code]['red_bag_id'] = $this_coupon_id;				//用户红包id,
	
					$order_data[$mall_id][$order_code]['check_code'] = $check_code;
					$order_data[$mall_id][$order_code]['order_code'] = $order_code;
					$order_data[$mall_id][$order_code]['user_id'] = $user_id;					//用户ID',
					$order_data[$mall_id][$order_code]['login_account'] = $user_info['login_account'];        //用户帐号',
					$order_data[$mall_id][$order_code]['consignee'] = $address_info['name'];                     //收货人',
					$order_data[$mall_id][$order_code]['shop_name'] = $user_info['shop_name'];                //店铺名称',
					$order_data[$mall_id][$order_code]['phone'] = $address_info['phone'];                        //手机号',
					$order_data[$mall_id][$order_code]['tel'] = $address_info['tel'];                            //电话',
					$order_data[$mall_id][$order_code]['email'] = $user_info['email'];                        //邮箱',
					$order_data[$mall_id][$order_code]['express_way'] = $shipping_id;            //配送方式',
					$order_data[$mall_id][$order_code]['express_name'] = $express_name;            //快递名称',
					$order_data[$mall_id][$order_code]['province'] = $address_info['province'];                	//省份',
					$order_data[$mall_id][$order_code]['city'] = $address_info['city'];                        	//城市',
					$order_data[$mall_id][$order_code]['district'] = $address_info['district'];                	//区县',
					$order_data[$mall_id][$order_code]['address'] = $address_info['address'];                    //详细地址',
					$order_data[$mall_id][$order_code]['come_from'] = $come_from;                //订单来源,
					$order_data[$mall_id][$order_code]['goods_price'] = $order_goods_price;            //商品价格',
					$order_data[$mall_id][$order_code]['express_price'] = $order_details['express_price'];               //快递费用',
					$order_data[$mall_id][$order_code]['credit_price'] = 0;          //减免金额',
					$order_data[$mall_id][$order_code]['order_price'] = $order_details['express_price'] + $order_goods_price+$total_insurance;            //订单总价',
					$order_data[$mall_id][$order_code]['collecting_price'] = $order_details['express_price'] + $order_goods_price - $coupon+$total_insurance;	//代收金额',
					$order_data[$mall_id][$order_code]['order_time'] = date('Y-m-d H:i:s');               //下单时间',
					$order_data[$mall_id][$order_code]['description'] = $liuyan;            //用户备注',
					$order_data[$mall_id][$order_code]['is_ys'] = $order_details['is_ys'];						//是否预售
					$order_data[$mall_id][$order_code]['pay_way'] = $pay_way;                    //支付方式',
					$order_data[$mall_id][$order_code]['supplier_id'] = $order_details['supplier_id'];            //供应商ID',
					$order_data[$mall_id][$order_code]['depot_id'] = $order_details['depot_id'];                  //订单性质,
					$order_data[$mall_id][$order_code]['order_tips'] = '';                             //订单备注',
					$order_data[$mall_id][$order_code]['level'] = $user_info['level'];                    	//订单等级',
					$order_data[$mall_id][$order_code]['online_type'] = $online_type;       //在线支付类型
					$init_status = [1=>5,2=>75,3=>165,4=>600];
					$order_data[$mall_id][$order_code]['order_status'] = $init_status[OrderPayWay::getPayType($order_data[$mall_id][$order_code]['pay_way'])];//订单状态',
					$order_data[$mall_id][$order_code]['is_urgent'] = $order_details['is_urgent'];				//是否加急
					$order_data[$mall_id][$order_code]['special_type'] = $order_details['special_type'];			//订单特殊类型[]
					$order_data[$mall_id][$order_code]['insurance'] = $total_insurance;         //保险费用
					$success_order[] = $order_data[$mall_id][$order_code];
				}
			}
			$out = Skp::createOrder($success_order);
		} else {
			exit('缺少必要信息');
		}
		
		if($out['code'] == 0){  //下单失败
			return $this->redirect(['/order-success/error2', 'data' => $out['msg'],]);
			
		} else if($out['code'] == 1){ //下单成功
			//清理购物车
			$connection = Yii::$app->db;
			foreach ($out['ids'] as $order_id) {
				$oms_goods = OmsGoods::find()->where(['order_id'=>$order_id])->asArray()->all();
				foreach ($oms_goods as $v){
					$goods_id = $v['goods_id'];
					$goods_num = $v['goods_num'];
					//删除购物车
					$sql = 'delete from user_shopping_cart where user_id = '.$user_id.' and goods_id = '.$goods_id;
					$delete_result = $connection->createCommand($sql)->execute();
				}
				//获取结算流水号
				$check_codes = OmsInfo::find()->select('check_code')->where(['id'=>$order_id])->scalar();
			}
			//满赠订单信息入库
			$order_gift = f_c($check_codes.'_gift');
			//f_d($order_gift);
			if($order_gift){
				foreach($order_gift as $value){
					$giftInfo = new GiftInfo();
					$giftInfo->order_code = $value['order_code'];
					$giftInfo->goods_id = $value['goods_id'];
					$giftInfo->gift_id = $value['gift_id'];
					$giftInfo->type = $value['type'];
					$giftInfo->oms_goods_id = $value['oms_goods_id'];
					$giftInfo->save();
				}
			}
			//更新最后下单时间
			UserMember::setLastOrderTime($user_id);
			//下单成功时  将order_id拼接成字符串
			$order_ids = '';
			foreach ($out['ids'] as $order_id) {
				$order_info = OmsInfo::find()->where(['id'=>$order_id])->asArray()->one();
				$order_ids .= $order_id.'-';
				if($pay_way == 3){
					//银行卡付款存信息
					$model = new PayBlankCard();
					$model->time = date('Y-m-d H:i:s');
					$model->bank_code = $_POST['card_num'];
					$model->order_code = $order_info['order_code'];
					$model->shop_name = $user_info['shop_name'];
					$model->open_name = $_POST['card_name'];
					$model->telphone = $_POST['card_phone'];
					$model->remark = $_POST['card_remark'];
					$model->save();
				}
				//在线支付插入记录
				if ($pay_way == 1 || $pay_way == 4 || $pay_way == 3) {   //在线支付   云贷支付  银行卡支付
					//order_oms_nopay 插入记录
					$nopay = new OmsNopay();
					$nopay->order_code = $order_info['order_code'];
					$nopay->user_id = $user_id;
					$nopay->order_time = $order_info['order_time'];
					$nopay->flag = 0;
					$nopay->save();
				}
				//内部采购单
				if($pay_way==99 && isset($user_info['inner_buy']) && $user_info['inner_buy']==1){
					$oms_info = OmsInfo::find()->where(['id'=>$order_id])->one();
					$old_paid_price = $oms_info->collecting_price;
					$oms_info->collecting_price = 0;
					$oms_info->paid_price = $old_paid_price;
					$oms_info->pay_way = 2;
					$oms_info->save();
					
					$oms_log = new OmsLog();
					$oms_log->order_code = $oms_info->order_code;
					$oms_log->log_type = OmsStatus::getStatusName($oms_info->order_status);
					$oms_log->operator = $user_info['login_account'];
					$oms_log->description = "内部物资采购订单生成，待收货款金额已改为0";
					$oms_log->operate_time = date('Y-m-d H:i:s', time());
					$oms_log->save();
					
					$innerBuyModel = new OrderOmsInnerMaterials();
					$innerBuyModel->order_id = $oms_info->id;
					$innerBuyModel->verify_status = 0;
					$innerBuyModel->save();
				}
				
			}
			if($pay_way == 99) {
				$pay_way = 2;
			}
			$order_ids = rtrim($order_ids, '-');
			
			//根据支付方式确定跳转地址
			$pay_way_info = OrderPayWay::find()->where(['id' => $pay_way])->asArray()->one();
			if ($pay_way_info['pay_type'] == 1 || $pay_way_info['pay_type'] == 2) {
				//跳转下单成功页面
				return $this->redirect(['/order-success/index', 'order_id' => $order_ids,]);
				// } else if ($pay_way_info['pay_type'] == 2) {
				//     //跳转银行卡信息页面
				//     return $this->redirect(['/order-success/blank-card', 'order_id' => $order_ids,]);
			} else if (in_array($pay_way_info['pay_type'], [3, 4])) {
				//跳转到支付页面                        /ucenter/online-payment?order_id=12-19
				return $this->redirect(['/ucenter/online-payment', 'order_id' => $order_ids,]);
			}
		}
	}
        
        /**
         * @title:3d18的商品 下单时提示
         * @description:threeEightGoods
         * @author:lxzmy
         * @date:2016-7-8 15:20
         */
        public function actionThreeEightGoods(){
            $res = 0;
            $ids = f_post('goods_id');
            $result = \common\models\goods\DepotCity::getTimeLineByGood2($ids,$this->user_info['city']);
            $now_time = time();
            $si_18 = strtotime("Thursday")+18*3600; //周四18点
            $wu_18 = strtotime("Friday")+18*3600; //周五18点
            $liu_18 = strtotime("Saturday")+18*3600; //周六18点
            
            if($result == 1) { //2D18周五 18：00至周六18点
            	if ($wu_18 <= $now_time && $now_time <= $liu_18) {
            		$res = 1;
            	}
            } else if($result >= 2) {//2D18周五  18：00至周六18点
            if ($si_18 <= $now_time && $now_time <= $liu_18) {
            		$res = 1;
            	}
            } else {
            	$res = 0;
            }
            return $res;
        }
        
        static private function WeekTime($user_level){
        if($user_level == 1){    //注册会员
            $endTime = 17;
        }else{
            $endTime = 18;
        }
        $now_time = time();
        $friday = strtotime("Thursday")+18*3600;
        $saturday = strtotime("Saturday")+18*3600;

        if(date('w',$now_time) == 5) {
            $friday = strtotime(date('Y-m-d 00:00:00',strtotime('-1 day')))+18*3600;
        } elseif(date('w',$now_time) == 6){
            $friday = strtotime(date('Y-m-d 00:00:00',strtotime('-2 day')))+18*3600;
            $saturday = strtotime(date('Y-m-d 18:00:00'));
        } elseif(date('w',$now_time) == 0){
            $friday = strtotime(date('Y-m-d 00:00:00',strtotime('-3 day')))+18*3600;
            $saturday = strtotime(date('Y-m-d 18:00:00',strtotime('-1 day')));
        }

        if ($friday <= $now_time && $now_time <= $saturday) {
            return  true; //相关区域内3d18商品并且符合截单时间
        }else{
            return false;
        }


    }
    /*
     * @title:提交订单时检测赠品库存
     * @description:threeEightGoods
     * @author:lxzmy
     * @date:2016-7-8 15:20
     */
    public function actionCheckGift(){
    	$gifts = f_post('gifts');
    	$main_ids = f_post('main_ids');
    	$gift_ids = [];
    	$active_goods_id = [];
    	$active_names = [];
    	foreach($gifts as $key => $value){
    		$gift_info[$value[0]]['gift_id'] = $value[2];
    		if(isset($gift_info[$value[0]]['num'])){
    			$gift_info[$value[0]]['num'] = $gift_info[$value[0]]['num'] +$value[1];
    		}else{
    			$gift_info[$value[0]]['num'] = $value[1];
    		}
    	}
    	foreach($gift_info as $goods_id => $value){
    		$num_avai = SupplierGoods::find()->select('num_avai')->where(['id'=>$goods_id])->scalar();
    		if($num_avai < $value['num']){
    			$active_name = GiftActive::find()->select('name')->where(['id'=>$value['gift_id']])->scalar();
    			$active_names[] = $active_name;
    		}
    	}
    	if($active_names){
    		$active_names = implode('、', $active_names);
    		echo $active_names;
    	}else{
    		echo 1;
    	}
    }
}
