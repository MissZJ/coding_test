<?php
namespace frontend\controllers;

use common\models\goods\GoodsPresell;
use \common\models\goods\DepositGoods;
use common\models\markting\PromotionCombineGoods;
use common\models\order\OrderMinusCity;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use frontend\components\Controller2016;
use common\models\goods\SupplierGoods;
use common\models\goods\Photo;
use common\models\user\UserShopsSupplier;
use common\models\goods\GoodsComment;
use common\models\goods\AttrExtend;
use common\models\goods\DepotCity;
use common\models\order\OrderMinusGoods;
use common\models\goods\BaseGoods;
use common\models\goods\Depot;
use common\models\user\Supplier;
use common\models\goods\GoodsRestCityGoods;
use common\models\markting\MarktingCouponBySupplier;
use common\models\user\SupplierCityScreen;
use common\models\markting\LadderGroup;
use common\models\markting\LadderGroupGoods;
use common\models\order\OmsInfo;
use common\models\user\ShoppingCart;
use common\models\markting\LadderGroupPrice;
use common\models\user\StoreMall;
use common\models\user\GoodsPrivilegeUser;
use yii\db\Query;
use common\models\sales\GoodsSalesStatics;
use common\models\goods\GoodsSales;
use common\models\goods\Type;
use common\models\goods\GoodsPhoto;
use common\models\goods\RelateAccessory;
use common\models\goods\GoodsSupplierAppend;
use common\models\goods\RecommendPrice;
use common\models\goods\Exvalue;
use common\models\markting\GiftGoods;
use common\models\markting\GiftActive;
use common\models\goods\GoodsBuyNow;

/**
 * Site controller
 */
class GoodsController extends Controller2016
{
    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [];
    }
    
    /**
      * @Title:beforeAction
      * @Description:复写beforeAction
      * @return:boolean || redirect
      * @author:huaiyu.cui
      * @date:2015-12-2 下午4:54:05
     */
    public function beforeAction($action) {
        $freeArr = [
            'lowest-quotation',
        ];
        if(in_array($action->id, $freeArr)){
            return true;
        }else{
            if ($this->user_info) {
                return true;
            }else{
                header("Location: /site/login"); exit;
                return FALSE;
            }
            
        }
    }
    
    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
//             'error' => [
//                 'class' => 'yii\web\ErrorAction',
//             ],
        ];
    }
/**
 * @description:全新的商品详情页
 * @return: Ambigous <string, string>|\frontend\components\:
 * @author: sunkouping
 * @date: 2016年2月25日下午4:50:24
 * @modified_date: 2016年2月25日下午4:50:24
 * @modified_user: sunkouping
 * @review_user:
*/
public function actionDetail() {

        $goods_id = $_GET['id'];
        //跨境专区商品直接购买 不加入购物车
        $is_seas = \common\models\seas\SeasGoodsType::isSeasGoods($goods_id);
        /*
        $alone = [376846,376870,541732]; //单独结算，不经购物车的商品
        if(in_array($goods_id, $alone)) {
        	$if_alone = true;
        } else {
        	$if_alone = false;
        }
        */
        $if_alone = GoodsBuyNow::isBuyNOw($goods_id);
        
      //查看导航片是否是定金商品
        //插入浏览记录
        SupplierGoods::insertViewGoods($goods_id);
        //用户信息
        $user_info = $this->user_info;
        $goods_info = [];
        $goodsColors = [];
        $goodsImgs = [];
        $supplierShop = [];
        $goodsComment = [];
        $goodsAttr = [];
        $timeLine = [];
        $minusInfo = [];//下单立减
        $couponInfo = [];//红包信息
        $see_again_see = []; //看了又看
        $afterSalesType = [];
        $video = '';
        $status = 1; //默认可买
        $down_message = '';//下架原因
        $goods_info = (new Query())->select('t1.id,t1.base_id,t1.supplier_id,t1.depot_id,
        		t1.goods_name,t1.type_id,t1.brand_id,t1.tips,t1.max_num,t1.is_double,
        		t1.privilege,t1.price,t1.num_avai,t1.sale_num,t1.min_num,t1.tips as goods_tip,
        		t4.name as unit_name,t5.name as color_name,
                t2.description,t2.video,
        		t3.id as supplier_id,t3.name as supplier_name,t3.reg_time,t3.overall_merit,
        		t3.type as supplier_type,t3.logo as supplier_logo,t3.is_ys,t3.is_tpl,
        		t6.img_url,
        		t7.mall_id')
                     ->from('{{goods_supplier_goods}} as t1')
                     ->leftJoin('{{goods_base_goods}} as t2','t1.base_id=t2.id')
                     ->leftJoin('{{user_supplier}} as t3','t1.supplier_id=t3.id')
                     ->leftJoin('{{goods_unit}} as t4','t1.unit_id=t4.id')
                     ->leftJoin('{{goods_color}} as t5','t1.color_id=t5.id')
                     ->leftJoin('{{goods_photo}} as t6','t1.cover_id=t6.id')
                     ->leftJoin('{{goods_type}} as t7','t1.type_id=t7.id')
                     ->where(['t1.id'=>$goods_id])->one();
        if (!empty($goods_info)) {
        	$goods_info['price'] = f_price($goods_info['id'],1,$user_info['city'],1);		//新的价格
            //并列商品
        	$goodsColors = SupplierGoods::getGoodsByBase($goods_info['base_id'],$goods_info['supplier_id'],$goods_info['depot_id']);
            foreach ($goodsColors as $key=>$value) {
            	$goodsColors[$key]['price'] = f_price($value['goods_id'],1,$user_info['city'],1);
            }
            //相册
            $goodsImgs = Photo::getBaseImg($goods_info['base_id']);
            //店铺信息
            $supplierShop = UserShopsSupplier::getSupplierShop($goods_info['supplier_id']);
            //商品评论
            //$goodsComment = GoodsComment::getGoodsComment($goods_id);
            $goodsComment =GoodsComment::getGoodsCommentByType($goods_id);
            //扩展属性
            $goodsAttr = AttrExtend::attrInfoArray($goods_info['base_id']);
            //物流失效
            $timeLine = DepotCity::getDepotCityTime($goods_info['supplier_id'],$user_info['city']);
            //立减金额
            $minusInfo = OrderMinusGoods::getMinusPrice($goods_id,$user_info['city']);
            //预售
            $presell = GoodsPresell::getPresellGoods($goods_id);
            //看了又看,维护的数据
            $see_again_see = SupplierGoods::getSeeAngin($goods_id);
            if(!$see_again_see){ //未维护自动抓取,获取同类型月销量前9
                $see_again_see = SupplierGoods::getTypeSaleGoods($goods_id,$user_info['city']);
            }
            //重组 看了又看的数据
            if (!empty($see_again_see)) {
                $see_again_see_array = [];
                for ($i=0;$i<ceil(count($see_again_see));$i++) {
                    $see_again_see_array[] = array_slice($see_again_see, $i * 3 ,3);
                }
                $see_again_see = $see_again_see_array;
            }
            // f_d($see_again_see);
            //排行榜
            $cates_rank = [];
            $cates_rank = self::detailsRank($goods_id);
            if(!$cates_rank){//同商城、同城市所覆盖仓库中同类型销量前10
                $cates_rank = self::getTypeGoods($goods_id,$user_info['city']);
            }
            //定金商品
            $deposit_good = DepositGoods::getGoods($goods_id,$user_info['city']);
            $deposit_info = DepositGoods::getDepositMoney($goods_id, $user_info['city']);

			//上下架状态
            $status_info = SupplierGoods::checkDetails($goods_info['id'], $user_info['id']);
            $status = $status_info['status'];
            $down_message = $status_info['message'];
            $uid = f_get('uid',null);
            if($uid) {
            	f_d($down_message);
            }
            //获取供应商发布的红包信息
            $couponInfo = MarktingCouponBySupplier::isHaveCoupon($goods_info['supplier_id'], $user_info['province'], $user_info['city']);
			//售后服务类型
            $after_sales_type = BaseGoods::getAfterSalesService($goods_info['base_id']);
            
        } else { //未找到该商品
        	exit('no this goods');
        }
        
        //屏蔽供应商
        $ScreenSupplier = SupplierCityScreen::find()
        	->where(['supplier_id'=>$goods_info['supplier_id'],'city_id'=>$user_info['city'],'user_group'=>$user_info['user_group']])
        	->asArray()->all();
        //基础商品信息
		$base_info = BaseGoods::find()->where(['id'=>$goods_info['base_id']])->asArray()->one();
		$base_id = $base_info['id'];
        //阶梯商品--是否存在阶梯定价商品        
        $group_id = LadderGroup::getGroupId($goods_id, $user_info['city']);
        $jt_group_price = [];  	//阶梯价格组
        $jt_active_title = []; 	//阶梯信息组
        $goods_info_jt = []; 	//阶梯商品信息
        $jt_group_price_each = [];
        
        if($group_id){
        	//阶梯商品信息
         	$jt_goods = LadderGroupGoods::find()->where(['group_id'=>$group_id])->asArray()->all();

            foreach($jt_goods as $key=>$value){
              	if($value['goods_id'] != $goods_id){
              		//单个商品信息
                	$goods_info_each = SupplierGoods::find()->where(['id'=>$value['goods_id'],'status'=>1])->asArray()->one();
                	if($goods_info_each) {
                  		$goods_info_jt[] = $goods_info_each;
                	}
              	}
            }
            //阶梯定价
            $jt_group_price = LadderGroupPrice::find()->where(['group_id'=>$group_id])->orderBy('num_range asc')->asArray()->all();
            //阶梯活动描述
            $jt_active_title = LadderGroup::find()->where(['id'=>$group_id])->asArray()->one();
            if(!$jt_active_title['description']) {
            	$jt_active_title['description'] = $goods_info['tips'];
            }
            //获取阶梯定价的单价
            //$jt_group_price_each = LadderGroupPrice::find()->where(['group_id'=>$group_id,'num_range'=>1])->asArray()->all();
            
            $jt_group_price_info = LadderGroupPrice::find()->where(['group_id'=>$group_id])->orderBy('num_range asc')->asArray()->all();
            if($jt_group_price_info){
                $n = 0;
                foreach($jt_group_price_info as $a_k =>$a_v){
                    if($goods_info['min_num'] >= $a_v['num_range']){
                        $n += 1;
                    }
                }
                if($n > 0){
                    $jt_group_price_each = $jt_group_price_info[$n-1]['ladder_price'];
                }else{
                    $jt_group_price_each = $goods_info['price'];
                }
            }else{
                $jt_group_price_each = $goods_info['price'];
            }
      }
      	//根据商品获取mall_id，为空则根据用户获取mall_id
        $StoreMall['mall_id'] = Type::find()->select('mall_id')->where(['id'=>$goods_info['type_id']])->scalar();
        if(empty($StoreMall)){
            $StoreMall['mall_id'] = StoreMall::getMainMall($this->user_info['id']);
        }
        $mall_id = f_get('mall_id',$StoreMall['mall_id']);
        $feng_mian = Photo::getImgUrl($base_info['cover_id']);
        //相关配件
        $pei_jian = RelateAccessory::getPeiJianBase($goods_id,4);
        //f_d($xiang_guan);
        
        //补充信息
        $append = GoodsSupplierAppend::get_append($goods_info['id']);
        //建议零售价
        $recommend_price = RecommendPrice::getRecommendPrice($goods_info['id']);

        //查看是否是组合商品
        $promotion = PromotionCombineGoods::getPromotionGroupByGoods($goods_id,$user_info['city'],$user_info['id']);
        
        //生产日期：status=1,代表前台显示
        $production_date = \common\models\goods\GoodsProductionDate::find()->where(['supplier_goods_id' => $goods_info['id'], 'type_id' => $goods_info['type_id']])->one();
        if($production_date && isset($production_date->value) && $production_date->status) {
            $scrq['start'] = $production_date->value != '0000-00-00' ? date('Y年m月', strtotime($production_date->value)) : '';
            $scrq['end'] = $production_date->value1 != '0000-00-00' ? date('Y年m月', strtotime($production_date->value1)) : '';
        }else $scrq = ['start' => '', 'end' => ''];
        $this->layout = '_blank';

        //24小时退换
		$oneday = Exvalue::find()->select('id')->where(['goods_id'=>$goods_id])->asArray()->one();
		if(empty($oneday)){
			$goods_info['oneday'] = 0;
		}else{
			$goods_info['oneday'] = 1;
		}
		if(in_array($mall_id,[3,4,7]) && $goods_info['depot_id'] == 1 && $user_info['province'] == 22) {
			$timeLine['shipping_time'] = date('Y-m-d',(time() + 2*24*3600));
		}
		//print_r($goods_info);die;
		$gift_id = GiftActive::checkGoodsIsMainGoods($goods_info['id']);
		if(!$gift_id){
			$goods_info['giftMsg'] = "";
			$goods_info['giftId'] = "";
		}else{
			$res = GiftActive::getRules($gift_id);
			$msg = "";
			if($res['type'] == 1||$res['type'] == 2){
				foreach($res['rules'] as $key => $value){
					$good_name = SupplierGoods::find()->select('goods_name')->where(['id' => $res['goods'][$value]])->column();
					if(isset($good_name[1])){
						$good_name = implode(',',$good_name);
					}else{
						$good_name = $good_name['0'];
					}
					$msg []= "满".$value."元送".$good_name;
					$goods_info['giftMsg'] = $msg;
					$goods_info['giftId'] = $gift_id;
					$goods_info['type'] = $res['type'];
				}
			}elseif($res['type'] == 3||$res['type'] == 4){
				foreach($res['rules'] as $key => $value){
					$good_name = SupplierGoods::find()->select('goods_name')->where(['id' => $res['goods'][$value]])->column();
					if(isset($good_name[1])){
						$good_name = implode(',',$good_name);
					}else{
						$good_name = $good_name['0'];
					}	
					$msg []= "满".$value."个送".$good_name;
					$goods_info['giftMsg'] = $msg;
					$goods_info['giftId'] = $gift_id;
					$goods_info['type'] = $res['type'];
				}
			}
		}
		if($goods_info['giftMsg']){
			$goods_info['giftMsg'] = array_unique($goods_info['giftMsg']);
		}
		
		//f_d($goods_info);
                //眼镜商品添加配镜须知
                if(in_array($goods_info['type_id'],[445,446,447,449,451,453,454,455,456,457,458,459,460,462,463])){
                    $notice = true;
                }else{
                    $notice = false;
                }
                
		if($group_id) {
			
			//return $this->redirect('/site/detail?id='.$goods_id);
			return $this->render('group',[
					'down_message'=>$down_message,
					'goods_info' => $goods_info,
					'user_info' => $user_info,
					'goods_colors' => $goodsColors,
					'goods_imgs' => $goodsImgs,
					'supplier_shop' => $supplierShop,
					'goods_attr' => $goodsAttr,
					'goods_comment' => $goodsComment,
					'time_line' => $timeLine,
					'minusInfo' => $minusInfo,
					'cates' => $cates_rank,  //排行榜
					'couponInfo' => $couponInfo,
					'hightSalesGoods' => $see_again_see,//看了又看
					'status' => $status,
					'after_sales_type' => $after_sales_type,
					'ScreenSupplier'=>$ScreenSupplier,
					'base_info'=>$base_info,
					'mall_id' => $mall_id,
					'feng_mian'=>$feng_mian,
					'jt_group_price' => $jt_group_price,
					'jt_active_title' => $jt_active_title,
					'goods_info_jt' => $goods_info_jt,
					'jt_group_each' =>$jt_group_price_each,
					'group_id' => $group_id,
                                        'deposit_info'=>$deposit_info,
                    'pei_jian'=>$pei_jian,
			        'scrq' => $scrq,
                    'presell' =>$presell,
			        'append' => $append,
					'recommend_price'=>$recommend_price,
                    'deposit_good'=>$deposit_good,
					'if_alone'=>$if_alone,
                                        'is_seas'=>$is_seas,
                    'notice' =>$notice,        

			]);
		} else {
 			//f_d($is_seas);
	        return $this->render('index',[
	        	'down_message'=>$down_message,
	            'goods_info' => $goods_info,
	            'user_info' => $user_info,
	            'goods_colors' => $goodsColors,
	            'goods_imgs' => $goodsImgs,
	            'supplier_shop' => $supplierShop,
	            'goods_attr' => $goodsAttr,
	            'goods_comment' => $goodsComment,
	            'time_line' => $timeLine,
	        	'minusInfo' => $minusInfo,
	            'cates' => $cates_rank,  //排行榜
	            'couponInfo' => $couponInfo,
	            'hightSalesGoods' => $see_again_see,//看了又看
	            'status' => $status,
	        	'after_sales_type' => $after_sales_type,
	            'ScreenSupplier'=>$ScreenSupplier,
	        	'base_info'=>$base_info,
	            'mall_id' => $mall_id,
	        	'feng_mian'=>$feng_mian,
        		'jt_group_price' => $jt_group_price,
        		'jt_active_title' => $jt_active_title,
        		'goods_info_jt' => $goods_info_jt,
        		'jt_group_each' =>$jt_group_price_each,
        		'group_id' => $group_id,
                'pei_jian'=>$pei_jian,
	            'scrq' => $scrq,
                'presell'=>$presell,
	            'append' => $append,
	        	'recommend_price'=>$recommend_price,
                'deposit_good'=>$deposit_good,
                'promotion'=>$promotion,
	        	'if_alone'=>$if_alone,
                        'is_seas'=>$is_seas,
                'notice' =>$notice,
                    'deposit_info'=>$deposit_info,

	        ]);
		}
    }

    public function  actionComment() {
        $id = f_post('id');
        $type = f_post('type');
        $page = f_post('page');
        
        $goods_comment = GoodsComment::getGoodsCommentByType($id, $type, $page, false);
        
        $this->layout = false;
        
        return $this->render('comment',[
            'goods_comment' => $goods_comment,
        ]);
    }

    /**
     * @description:同商城、同城市所覆盖仓库中同类型销量前10
     * @return:
     * @author: jr
     * @date:
     * @modified_date:
     * @modified_user: jr
     * @review_user:
     */
    public function getTypeGoods($good_id,$city){
        $key = 'type_goods_'.$good_id;
        $re = f_c($key);
        if($re === false){
            $arr =[];
            $type = SupplierGoods::find()->select(['type_id'])->where(['id'=>$good_id])->scalar();
            //根据城市找出所有覆盖的仓库 $depots[]
            $depots = [];
            $depots = DepotCity::find()->select(['depot_id'])->where(['city'=>$city])->asArray()->all();
            $depots_id = [];
            foreach($depots as $vo){
                $depots_id[] = $vo['depot_id'];
            }

            $goodsInfo = (new Query())->select('t1.id as goods_id,t1.base_id,t1.type_id,t1.brand_id,t1.unit_id,t1.goods_name,t1.color_id,t1.price,t1.sale_num,t1.supplier_id,t4.img_url')
                ->from('goods_supplier_goods as t1')
                ->leftJoin('goods_photo as t4','t1.cover_id=t4.id')
                ->where(['t1.type_id'=>$type,'t1.status'=>1,'t1.enable'=>1,'t1.is_deleted'=>0])
                ->andWhere(['>','t1.price',0])
                ->andWhere(['>','t1.num_avai',0])
                ->andWhere(['in','t1.depot_id',$depots_id])
                ->orderBy('t1.sale_num desc')
                ->limit(10)
                ->all();
            f_c($key,$goodsInfo,3600*8);
        }else{
            $goodsInfo = f_c($key);
        }

        return $goodsInfo;
    }

    /**
     * @description:详情页排行榜维护的数据
     * @return: []
     * @author: sunkouping
     * @date: 2016年2月25日下午5:54:46
     * @modified_date: 2016年2月25日下午5:54:46
     * @modified_user: sunkouping
     * @review_user:
    */
    public function detailsRank($good_id)
    {
        $returnVal = [];
        $key = 'detail_rank_'.$good_id;
        $res = f_c($key);
        if($res === false){
            $goodsId_arr = GoodsSales::find()->select(['goods_id'])->where(['good_id' => $good_id, 'position_id' => 2])->asArray()->all();
            if ($goodsId_arr) {
                $id_arr = [];
                foreach ($goodsId_arr as $id) {
                    $id_arr[] = $id['goods_id'];
                }
                $returnVal = (new Query())->select('t1.id as goods_id,t1.base_id,t1.type_id,t1.brand_id,t1.unit_id,t1.goods_name,t1.color_id,t1.price,t1.sale_num,t1.supplier_id,t4.img_url')
                    ->from('goods_supplier_goods as t1')
                    ->leftJoin('goods_depot as t2', 't1.depot_id=t2.id')
                    ->leftJoin('goods_photo as t4', 't1.cover_id=t4.id')
                    ->leftJoin('goods_sales as t3', 't3.good_id=t1.id')
                    ->where(['t1.status'=>1,'t1.enable'=>1,'t1.is_deleted'=>0,'t2.status'=>1,'t3.position_id'=>2])
                    ->andWhere(['>','t1.price',0])
                    ->andWhere(['>','t1.num_avai',0])
                    ->where(['in', 't1.id', $id_arr])
                    ->orderBy('t3.weight asc,')
                    ->groupBy('t1.id ')
                    ->limit(10)
                    ->all();
                f_c($key,$returnVal,1800);
            }
        }else{
            $returnVal = f_c($key);
        }
        return $returnVal;

    }

    
    
    
    /**
     * 
     * @Title: actionAddShoppingCar
     * @Description: 添加购物车
     * @return: multitype:number string 
     * @author: yulong.wang
     * @date: 2015-11-6下午2:31:55
     */
    public function actionAddShoppingCar(){
        $out = 0;
        $user_info = $this->user_info;
        $sign = SupplierGoods::checkShoppingCarGoods($_POST['goods_id'],$_POST['city']);
        if ($sign == 1) {
            $dateTime = date('Y-m-d H:i:s',time());
            $shoppingCar = ShoppingCart::findOne(['user_id'=>$_POST['user_id'],'goods_id'=>$_POST['goods_id'],'create_time'=>$dateTime,'time_line'=>$_POST['time_line']]);
            if ($shoppingCar) {
                $shoppingCar->goods_num = $shoppingCar->goods_num + $_POST['num'];
                if ($shoppingCar->save()) {
                    $out = 1;
                } 
            } else {
                $shoppingCarMod = new ShoppingCart();
                $shoppingCarMod->user_id = $_POST['user_id'];
                $shoppingCarMod->goods_id = $_POST['goods_id'];
                $shoppingCarMod->goods_num = $_POST['num'];
                $shoppingCarMod->create_time = $dateTime;
                $shoppingCarMod->time_line = $_POST['time_line'];
                if ($shoppingCarMod->save()) {
                    $out = 1;
                }
            }
            
            if ($out == 1) {
                $shoppingGoods = ShoppingCart::find()->where(['user_id'=>$_POST['user_id']])->asArray()->all();
                if (!empty($shoppingGoods)) {
                    $total_goods_num = '';
                    $total_goods_price = '';
                    foreach ($shoppingGoods as $value) {
                         $goods_price = f_price($value['goods_id'],$value['goods_num'],$user_info['city'],1);
                         $total_goods_num += $value['goods_num'];
                         $total_goods_price += $goods_price;
                    }
                    
                    $returnVal['status'] = 1;
                    $returnVal['msg'] = '<p>购物车共有<span>'.$total_goods_num.'</span>件有效商品 合计：<span>￥'.$total_goods_price.'</span></p>';
                }
            } else {
                $returnVal['status'] = 0;
                $returnVal['msg'] = '加入购物车失败';
            }
        } else {
            $returnVal = ['status'=>0,'msg'=>'商品已下架'];
        }
        return json_encode($returnVal);
    }
    
    /**
     * @description:ajax获取头部信息
     * @return: return_type
     * @author: leo
     * @date: 2015年11月20日下午2:35:09
     * @review_user:
    */
    public function actionGetHeaderMes(){
        $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
        if(!empty($user_info)){
            $goods_viewd = SupplierGoods::getViewGoods();
            ksort($goods_viewd);
            
            $goods_view_html = '';
            foreach ($goods_viewd as $key=>$val){
                if($key < 4){
                    $goods_view_html .='<a href="/site/detail?id='.$val.'"><img src="'.Photo::getImgLink($val).'"></a>';
                }
            }
            
            $result = [
                        'login_account'=>$user_info['login_account'],
                        'level_name'=>UserMember::getLevel($user_info['level']),
                        'goods_view_html'=>$goods_view_html,
                        'touxiang'=>UserMember::getUserAvatar($user_info['touxiang']),
                      ];
            
            return Json::encode($result);
        }
    }
    
    /**
     * @description:获取购物车数据
     * @return: return_type
     * @author: leo
     * @date: 2015年11月23日上午10:08:02
     * @review_user:
    */
    public function actionGetCartInfo(){
        $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
        //$cart_data = ShoppingCart::find()->where(['user_id'=>$user_info['id']])->asArray()->all();
        $cart_data1 = ShoppingCart::getAllGoods($user_info['id'],1);
        $cart_data2 = ShoppingCart::getAllGoods($user_info['id'],2);
        $cart_data = array_merge($cart_data1,$cart_data2);
        $count = 0;
        if(!empty($cart_data)){
            $total_price = 0;
            $html = '<p class="cart_tl">最新加入的商品</p><div class="add_product"><ul>';
            foreach ($cart_data as $val){
                //$goods_data = SupplierGoods::findOne($val['goods_id']);
                $html .='<li><a class="prd_img" href="/site/detail?id='.$val['goods_id'].'" title="'.$val['goods_name'].'"><img src="'.Photo::getImgLink($val['goods_id']).'">
                     </a><a class="prd_tl" href="/site/detail?id='.$val['goods_id'].'" title="'.$val['goods_name'].'"><p>'.$val['goods_name'].'</p></a> <div class="prd_pnd">
                     <span>￥'.$val['price'].'</span><span class="multiply">*</span><span>'.$val['goods_num'].'</span>
                     <a class="prd_delete" href="javascript:void(0)" data-id="'.$val['id'].'">删除</a></div></li>';
                $count +=$val['goods_num'];
                $total_price += $val['price']*$val['goods_num'];
            }
            
            $html .= '</ul></div><div class="prd_total"><p>共<span class="orange">'.$count.'</span>件商品&nbsp;总计：￥<span class="orange total_num">'.$total_price.'</span></p>
                            <a class="go_cart" href="/cart/index">去购物车</a>
                        </div>';
            $result = ['html'=>$html,'count'=>$count];
            
            return Json::encode($result);
        }else{
            $html = '<div class="nogoods"><b></b>购物车中还没有商品，赶紧选购吧！</div>';
            $result = ['html'=>$html,'count'=>$count];
            return Json::encode($result);
        }
        
    }
    
    /**
     * @description:头部购物车删除
     * @return: return_type
     * @author: leo
     * @date: 2015年11月23日上午11:12:07
     * @review_user:
    */
    public function actionDeleteHeaderCart(){
        if(isset($_POST['cart_id'])&&!empty($_POST['cart_id'])){
            $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
            $model = ShoppingCart::findOne($_POST['cart_id']);
            if($model){
                $out = ShoppingCart::deleteAll(['id'=>$model->id,'user_id'=>$user_info['id']]);
                if($out){
                    return 1;
                }else{
                    return Json::encode('数据删除错误！');
                }
            }else{
                return Json::encode('未找到数据！');
            }
        }else{
            return Json::encode('数据发生错误！');
        }
    }


    /**
     * @description:更改城市缓存信息
     * @return:
     * @author: wanmin
     * @date: 2015年12月8日上午11:12:07
     *
     */
    public function actionChangeCity()
    {

        $city = \Yii::$app->request->getQueryParam("city");
        $province = \Yii::$app->request->getQueryParam("pro");
        $user_id = \Yii::$app->user->id;
        if(!empty($city) && !empty($province))
        {
            // 更改省市信息  省市ID
            $userInfo = f_c('frontend-'.$user_id.'-new_51dh3.0');
            if(!empty($userInfo))
            {
                $province_id= OtherRegion::getRegionIdViaType($province,1);
                $city_id= OtherRegion::getRegionIdViaType($city,2);
                $district = OtherRegion::getDistrict($city_id);

//                $province_id= OtherRegion::getRegionId($province);
//                $city_id= OtherRegion::getRegionId($city);

                if(!empty($city_id) && !empty($province_id))
                {
                    $temp = array();
                    foreach($userInfo as $k => $v)
                    {
                        if($k == "province")
                        {
                            $temp[$k] = $province_id;
                        }
                        elseif($k == "city")
                        {
                            $temp[$k] = $city_id;
                        }
                        elseif($k == "district")
                        {
                            $temp[$k] = $district;
                        }
                        else
                        {
                            $temp[$k] = $userInfo[$k];
                        }
                    }
                    $model = UserMember::find()->where(['id'=>$user_id])->one();
                    $model->city = $city_id;
                    $model->province = $province_id;
                    $model->district = $district;
                    $model->save();
                    
                    if(is_array($temp) && count($temp) >0)
                    {
                        f_c('frontend-'.\Yii::$app->user->id.'-new_51dh3.0',$temp);
                    }
                }

                return $this->redirect('/home/index');

            }

        }
    }


    /**
     * @description:异步获取价格
     * @return: return_type
     * @author: leo
     * @date: 2015年12月30日下午4:35:17
     * @review_user:
    */
    public function actionGetPrice(){
        $return = [];
        if(isset($_POST['goods_id'])&&isset($_POST['num'])){
            $price = f_price($_POST['goods_id'], $_POST['num'], $this->user_info['city'], 1);
            $return = ['code'=>'200','mes'=>'ok','price'=>$price];
        }else{
            $return = ['code'=>'101','mes'=>'数据传输错误！'];
        }
        
        return json_encode($return);
    }
    
    /**
   * @Title: actionJtallmoney
   * @Description: 阶梯定价的详情页数量修改
   * @return: return_type
   * @author: honglang.shen xiewei
   * @date: 2016-1-13
  */
  public function actionEjtallmoney(){
    $num = $_POST['bnum'];
    $goods_id = $_POST['goods_id'];
    $goods_info = SupplierGoods::findOne($goods_id);
    $group_id = $_POST['group_id'];
    //阶梯定价
    $all_num = $_POST['all_num'];
    $ladder_info = LadderGroupPrice::find()->select('num_range,ladder_price')->where(['group_id'=>$group_id])->asArray()->all();
    $data=[];
    $ladder_price = 0;
    foreach($ladder_info as $key=>$value){
      if($key==0){
        if($all_num<$value['num_range']){
          $ladder_price = $goods_info->price;
          break;
        } 
      }
      if($all_num >= $value['num_range']){
        $ladder_price = $value['ladder_price'];
      }
    }
    $data['ladder_price'] = $ladder_price;
    $ladder_each = 0;
    foreach($ladder_info as $key=>$value){
      if($key==0){
        if($num<$value['num_range']){
          $ladder_each = $goods_info->price;
          break;
        }
      }
      if($num >= $value['num_range']){
        $ladder_each = $value['ladder_price'];
      }
    }
    $data['ladder_each'] = $ladder_each;
    return json_encode($data);exit;
  }
  /**
   * @Title: actionGetOrderNotice
   * @Description: 下单通知系统
   * @return: return_type
   * @author: honglang.shen 
   * @date: 2016-1-21
  */
  public function actionGetOrderNotice(){
    $goods_id = $_POST['goods_id'];
    $data = [];
    foreach ($goods_id as $key => $value) {
      $data[] = OrderNoticeGoods::getOrderNoticeGoods($value);
    }
    
    if(empty($data)){
      $return = '';
    }else{
      $return = $data[0];
    }
    return json_encode($return);
  }
  /**
   * @Title: actionUpdateOrderNotice
   * @Description: 更新下单通知点击数
   * @return: return_type
   * @author: honglang.shen 
   * @date: 2016-1-25
  */
  public function actionUpdateOrderNotice(){
    $goods_id = $_POST['goods_id'];
    $click = $_POST['click'];
    $data = [];
    foreach ($goods_id as $key => $value) {
      $data[] = OrderNoticeGoods::getOrderNoticeGoods($value);
    }
    if(empty($data)){
      $return = 0;
    }else{
      $return = $data[0];
    }
    $connection = \Yii::$app->db;
    foreach ($return as $key => $value) {
      if($click == 'sure'){
        $sql = "update order_notice_group set `confirm_click`=`confirm_click`+1 where id=".$value['id'];
      }elseif($click == 'no'){
        $sql = "update order_notice_group set `cancel_click`=`cancel_click`+1 where id=".$value['id'];
      }
      $data = $connection->createCommand($sql)->execute(); 
    }
  }

    /**
        * @return string
        * @description: 最低报价
        * @date: 2015-12-28 14:44:51
        * @author: qiang.zuo
        * @modify:lxzmy 2016-2-17 10:07 
        */
	public function actionLowestQuotation(){
		$colors_nav = ['a','b','c','d'];
		$ModuleGoodsDetails = 'moduleGoodsDetails';
		$moduleInfo = "moduleInfo";
		$moduleGoodsDetails = Yii::$app->cache->get($ModuleGoodsDetails);
		$ModuleInfo = Yii::$app->cache->get($moduleInfo);
		if(!$ModuleInfo){
			$ModuleInfo = BaseLowestQuotationModule::find()->select('id,name')->where(['status'=>1])->orderBy("sort ASC")->asArray()->all();
			$ModuleInfo = ArrayHelper::map($ModuleInfo,"id","name");
			Yii::$app->cache->set($moduleInfo, $ModuleInfo, 43200);
		}
		if(!$moduleGoodsDetails){
			foreach($ModuleInfo as $key=>$vo){
				$info = BaseLowestQuotation::find()->select("keywords,type")->where(['module_id'=>$key,'status'=>1])->asArray()->all();
				foreach($info as $k=>$v){
					$arr = SupplierGoods::find()->select('id')->where(['like','keywords',$v['keywords']])->andWhere(['type_id'=>$v['type']])->asArray()->all();
					if($arr){
						foreach($arr as $vo1){
							$arr1[] = $vo1['id'];
						}
						$min = (new Query())->select('t1.name,t1.id,t2.price as price,t1.cover_id as img,t2.tips,t2.supplier_id,t2.id as supplier_goods_id')->from('goods_base_goods as t1')->leftJoin('goods_supplier_goods as t2','t1.id = t2.base_id')->where(['t2.id'=>$arr1])->andWhere('t1.status = 1  AND t2.status = 1 ')->andWhere(['>','t2.num_avai',0])->orderBy('t2.price ASC')->one();
						if($min){
							$info[$k]['base_id'] = $min['id'];
							$info[$k]['base_name'] = $min['name'];
							$info[$k]['base_price'] = $min['price'];
							$info[$k]['base_img'] = $min['img'];
							$info[$k]["tips"] = $min['tips'];
							$info[$k]['supplier_id'] = $min['supplier_id'];
							$info[$k]['supplier_goods_id'] = $min['supplier_goods_id'];
                                                        
						}else{
							$info[$k][] = [];
						}
					}else{
						$info[$k] = [];
					}
					unset($arr1);
				}
				$moduleGoodsDetails[$key] = $info;
				unset($info);
			}
			Yii::$app->cache->set($ModuleGoodsDetails, $moduleGoodsDetails, 43200);
		}
		$url = "";
//		$site_id = $this->user_info['city'];
//		if(isset($site_id)){
//			$url = SysSiteLowPrice::getLink($site_id);
//		}
		$error = '';
		$model =new FrontendLoginForm();
		if ($model->load(Yii::$app->request->post())) {
                    $verifyCode = $_POST['FrontendLoginForm']['verifycode'];
			if(f_isMobile()){
				if ($model->login()) {
					return $this->goBack();
				}
			}else{
				if($verifyCode == f_s('verifyCode')){
					if ($model->login()) {
						return $this->redirect('/quotation/lowest-quotation');
					}
				}else{
					header("Content-type:text/html;charset=utf-8");
					return $this->msg("验证码输入不正确！","lowest-quotation");
				}
			}
		}
                
		return $this->renderPartial('lowest_quotation',[
			'moduleInfo' => $ModuleInfo,
			'colors_nav' => $colors_nav,
			'moduleGoodsDetails' => $moduleGoodsDetails,
			'url' => $url,
                       // 'like'=>$like,
		]);
	}
        
        /*
         * @description : 导出当前城市最低报价   用户的当前城市报价
         * @author : lxzmy
         * @date : 2016-2-18 9:37
         */
        public function actionExportLowGoodsPrices(){
            if(isset($this->user_info)){
                $provice = $this->user_info['province'];
                $city = $this->user_info['city'];
                $user_id = $this->user_info['id'];
                $sql = "select t1.base_id 基础商品编号,t2.name 基础商品名称,t3.region_name 省份,t4.region_name 城市,t1.bottom_price 最低价  from goods_base_goods_city as t1 LEFT JOIN goods_base_goods as t2 on t1.base_id=t2.id LEFT JOIN other_region as t3 on t1.province=t3.region_id LEFT JOIN other_region as t4 on t1.city=t4.region_id where 1=1";
                if($provice){
                    $sql .= " and t1.province=".$provice;
                }
                if($city){
                    $sql .= " and t1.city=".$city;
                }
                $key = md5('51dh_news'.date('d', time()) * date('Y', time()));
                $url = file_get_contents(FAST_EXPORT_URL."/shell/mysql_excel3.php?word=".urlencode($sql)."&key=$key");
                header("Location:$url");
                ExportLog::createExportlog(\Yii::$app->user->id, '导出城市内商品最低价');
                exit();
            }
        }
        
        
    /*
     * @title : checkUpLine
     * @description:验证商城上线时间
     * @author:lxzmy
     * @date:2016-4-28 13:52
     * 
     */
    public function actionCheckUpLine(){
        $mall_id = f_post('mall_id',0);
        $res = ['code'=>0,'arr'=>''];
        $now_date = date('Y-m-d H:i:s');
        if($mall_id){
            $arr = \common\models\other\MallBase::find()->where(['id'=>$mall_id])->andWhere(['>','upline_time',$now_date])->asArray()->one();
            if($arr){
                $res['code'] = 1;  //提示还未上线
                $res['arr']=date('Y年n月j日',  strtotime($arr['upline_time']));
            }else{
                $res['code'] = 3;  //已经上线
            }
        }else{
            $res['code'] = 2;  //商城ID不存在
        }
        return json_encode($res);
    }
    
    /*
     * @title : isMaterialGoods
     * @description:判断是否是物料商城商品
     * return mall_id = 6
     * @author:lxzmy
     * @date:2016-5-11 10:20
     * 
     */
    public function actionIsMaterialGoods(){
        $goods_id = f_post('goods_id',0);
        $res = 0;
        if($goods_id){
            $arr = (new Query())->select('t1.id,t1.type_id,t2.mall_id')
                    ->from('goods_supplier_goods t1')
                    ->leftJoin('goods_type t2','t1.type_id=t2.id')
                    ->where(['t1.id'=>$goods_id])
                    ->one();
            if(isset($arr['mall_id'])&& $arr['mall_id']==6){   
                $res = 3;  //不能购买
            }else{
                $res = 1;
            }
        }else{
            $res = 2;  //商品ID不存在
        }
        return $res;
    }

    /**
     * @description:湖北和山东鲁北地区每周五截单后（注册会员17：00，带牌18：00）停止通讯商城时效为3D18的订货功能
     * @return:返回正数是要限制的商品
     * @author: jr
     * @date:
     * @modified_date:
     * @modified_user: jr
     * @review_user:
     */
//    public function actionIsJiedanGoods(){
//        $mall_id = f_post('mall_id',0);
//        $goods_id = f_post('goods_id',0);
//        $user_level = $this->user_info['level'];    //每周五注册会员１７：００截止订货，带牌会员１８:００截止订货
//        $time = self::WeekTime($user_level);
//        if($time && $mall_id == 1){  //通讯商品
//            $result = DepotCity::getTimeLineByGood($goods_id,$this->user_info['city']);
//            if($result){
//                $re = ['code'=>1,'msg'=>'对不起，该商品属于时效为3D18的商品，每周四１８:００截止订货；每周六１８:００后恢复正常订货！给您带来不便，还请谅解！'];
//            }else{
//                $re = ['code'=>-1,'msg'=>''];
//            }
//        }else{
//            $re = ['code'=>-1,'msg'=>''];
//        }
//        echo json_encode($re);exit;
//    }

    /**
     * @description:每周五（注册会员17：00，带牌18：00）截单,每周六１８:００后恢复正常
     * @return:
     * @author: jr
     * @date:
     * @modified_date:
     * @modified_user: jr
     * @review_user:
     */
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
    
        
}
