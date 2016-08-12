<?php
namespace frontend\controllers;

use common\models\goods\GoodsPresell;
use common\models\goods\search\DepositInfo;
use common\models\order\OmsStatus;
use common\models\user\Supplier;
use Yii;
use yii\db\Exception;
use yii\filters\AccessControl;
use yii\helpers\Json;
use yii\web\Controller;
use common\models\user\UserMember;
use common\models\goods\SupplierGoods;
use common\models\markting\SeckillGoods;
use common\models\goods\Photo;
use common\models\goods\BaseGoods;
use common\models\markting\SeckillActive;
use yii\db\Query;
use common\models\markting\SeckillOrder;
use common\models\markting\SeckillCity;
use common\models\goods\Unit;
use common\models\user\ShoppingCart;
use common\models\order\OrderMinusGoods;
use common\models\markting\LadderGroupGoods;
use common\models\markting\LadderGroupPrice;
use frontend\components\Controller2016;
use common\models\markting\SpecialInfo;
use frontend\controllers\GoodsController;
use common\models\goods\GoodsLimitOrder;
use common\models\markting\GiftGoods;
use common\models\markting\GiftActive;
class CartController extends Controller2016{ 
	
	/**
	 * @description:用户购物车
	 * @return: 
	 * @author: sunkouping
	 * @date: 2015年11月4日下午3:05:08
	 * @modified_date: 2015年11月4日下午3:05:08
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionIndex(){
		header("Content-Type: text/html; charset=UTF-8");
		
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
		/**
		 * 查出该用户购物车的所有商品  按时效分组
		 */

		$D2_goods = ShoppingCart::getAllGoodsForPc($user_id,1);
		$D3_goods = ShoppingCart::getAllGoodsForPc($user_id,2);
		$tpl_goods = ShoppingCart::getAllGoodsForPc($user_id,3);   //第三方物流商品
		//按时效分组合商品
//		$D2_promotion_goods = ShoppingCart::getPromotionGourp($user_id,1);
//		$D3_promotion_goods = ShoppingCart::getPromotionGourp($user_id,2);

		/**
		 * --再按有效性分组
		 */
		$D2_goods = ShoppingCart::group($D2_goods);
		$D3_goods = ShoppingCart::group($D3_goods);
		$tpl_goods = ShoppingCart::group($tpl_goods);

//		$D2_promotion_goods = ShoppingCart::groupForPromotion($D2_promotion_goods);//商品组合
//		$D3_promotion_goods = ShoppingCart::groupForPromotion($D3_promotion_goods);//商品组合
		
		$this->layout = '_blank';
		$D2_gifts = [];
		foreach($D2_goods['effective'] as $key => $value){
			$gift_id = GiftActive::checkGoodsIsMainGoods($value['goods_id']);
			if($gift_id){
				$res = GiftActive::getRules($gift_id);
				//f_d($res);
				//$allGoodsInfo[$key]['msg'] = $res;
				$msg = "";
				if($res['type'] == 1||$res['type'] == 2){
					foreach($res['rules'] as $k => $v){
						if($k > 0){
							$msg .= ";";
						}
						$msg .= "满".$v."元送赠品";
					}
				}elseif($res['type'] == 3||$res['type'] == 4){
					foreach($res['rules'] as $k => $v){
						if($k > 0){
							$msg .= ";";
						}
						$msg .= "满".$v."个送赠品";
					}
				}
				$value['msg'] = $msg;
				$D2_gifts[$gift_id][] = $value;
				unset($D2_goods['effective'][$key]);
			}
		}
		$D3_gifts = [];
		foreach($D3_goods['effective'] as $key => $value){
			$gift_id = GiftActive::checkGoodsIsMainGoods($value['goods_id']);
			if($gift_id){
				$res = GiftActive::getRules($gift_id);
				//f_d($res);
				//$allGoodsInfo[$key]['msg'] = $res;
				$msg = "";
				if($res['type'] == 1||$res['type'] == 2){
					foreach($res['rules'] as $k => $v){
						if($k > 0){
							$msg .= ";";
						}
						$msg .= "满".$v."元送赠品";
					}
				}elseif($res['type'] == 3||$res['type'] == 4){
					foreach($res['rules'] as $k => $v){
						if($k > 0){
							$msg .= ";";
						}
						$msg .= "满".$v."个送赠品";
					}
				}
				$value['msg'] = $msg;
				$D3_gifts[$gift_id][] = $value;
				unset($D3_goods['effective'][$key]);
			}
		}
		//$D2_goods['effective'] = array_merge($D2_goods['effective'],$arr);
		//f_d($D2_gifts);
		return  $this->render('index',[
				'user_info'=>$user_info,
				'D2_goods'=>$D2_goods,
				'D3_goods'=>$D3_goods,
				'tpl_goods'=>$tpl_goods,
				'D2_gifts'=>$D2_gifts,
				'D3_gifts'=>$D3_gifts
//				'D2_promotion_goods'=>$D2_promotion_goods,
//				'D3_promotion_goods'=>$D3_promotion_goods

		]);
	}
	/**
	 * @description:购物车删除
	 * @return: 
	 * @author: sunkouping
	 * @date: 2015年11月4日下午8:42:51
	*/
	public function actionDel(){
		$id = f_post('id',0);
		if($id) {
			ShoppingCart::deleteAll('id='.$id);
			return 1;
		}
		return 0;
	}
	
	/**
	 * @description:修改购物车数量
	 * @return: [
	 * code
	 * message
	 * ]
	 * @author: sunkouping
	 * @date: 2015年11月5日下午1:38:12
	*/
	public function actionAjaxUpdate(){
		//库存   最低购买量
		$goods_id = f_post('goods_id',0);
		$num = f_post('num',0);
		
		$goods_info = SupplierGoods::find()->where(['id'=>$goods_id])->asArray()->one();
		if($goods_info['is_double'] == 1 && $goods_info['min_num'] != 1) {
			//$num = (floor($num/$goods_info['min_num']) + 1) * $goods_info['min_num'];
		}
		$user_id = \Yii::$app->user->id;
		$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		$message = '';
		$code = 0;
		$out = [];
		if($goods_info) {
            if($num < $goods_info['min_num']) {
                $message = '不得小于最低购买量';
            } else if($num > $goods_info['num_avai']) {
                $message = '库存不足';
            } else if($num > $goods_info['max_num'] && $goods_info['max_num'] != 0) {
                $message = '超过每日限购量';
            } else {
                $connection = Yii::$app->db;//事务开始
                $transaction = $connection->beginTransaction();
                try{
                    $model = ShoppingCart::find()->where(['user_id'=>$user_id,'goods_id'=>$goods_id])->one();
                    $model->goods_num = $num;
                    if($model->save()){
                        if(GoodsPresell::getUserGoodsYsStatus($goods_id,$user_id)){
                            $transaction->commit();
                            $code = 1;
                        }else{
                            throw new Exception('预售商品超出购买量限制');
                        }

                    }
                }catch (Exception $e){
                    $transaction->rollBack();
                    $message = "预售商品超出购买量限制";
                }
            }

			
		}
		//是否存在下单立减
		$minus_price = OrderMinusGoods::getMinusPrice($goods_id, $user_info['city']);
		if($minus_price) {
			$code = 2;
		}
		//阶梯定价
		$ladder_price = LadderGroupPrice::getladderprice($goods_id,$user_info['city'],$num);
		if($ladder_price != $goods_info['price']) {
			$code = 2;
		}
		
		$out['code'] = $code;
		$out['message'] = $message;
		return json_encode($out);
		
	}

    /**
     * @Title:actionAjaxAddCar
     * @Description: 加入购物车接口请求
     * @author:wangmingcha
     * @date:2015-11-24 下午7:38:55
     */
    public function actionAjaxAddCar(){
        $ajaxRet = ['status'=>0];
        if(Yii::$app->request->getIsAjax()){
            $user_id = Yii::$app->request->post('user_id');
            $goods_id = Yii::$app->request->post('goods_id');
            $goods_num = Yii::$app->request->post('goods_num');
            $flag = Yii::$app->request->post('flag');
            
            //该商品是否限购
            $isOverLimit = GoodsLimitOrder::isOverLimit($goods_id, $user_id);
            if($isOverLimit) {
                //对不起，该商品已达到购买上限
                $msg = '对不起，该商品已达到购买上限';
                $ajaxRet['msg'] = '对不起，该商品已达到购买上限';
                echo Json::encode($ajaxRet);
                Yii::$app->end();
                exit;
            }
            
            if($flag != 1)
            {
                $flag = 0;
            }
            $modelStatus = ShoppingCart::addCart($user_id,$goods_id,$goods_num,$flag);
            //f_d($modelStatus);
            if($modelStatus['code']==1){
                $ajaxRet['status'] = 1;
            }else{
                $ajaxRet['msg'] = $modelStatus['message'];
            }
        }
        echo Json::encode($ajaxRet);
        Yii::$app->end();
    }
    /**
     * @Title:actionAddLadderCar
     * @Description: 阶梯定价 加入购物车
     * @author:honglang.shen
     * @date:2016-1-13
     */
    public function actionAddLadderCar(){
    	//$shop_cart = $_POST['shop_cart'];
    	//f_d($shop_cart);
    	$ajaxRet = ['status'=>0];
        if(Yii::$app->request->getIsAjax()){
            $user_id = Yii::$app->request->post('user_id');
            //$goods_id = Yii::$app->request->post('goods_id');
            $shop_cart = Yii::$app->request->post('shop_cart');
            $flag = Yii::$app->request->post('flag');
            if($flag != 1)
            {
                $flag = 0;
            }
            foreach ($shop_cart as $key => $value) {

            	$modelStatus = ShoppingCart::addCart($user_id,$value['goods_id'],$value['num'],$flag);
            }
            if(isset($modelStatus['code']) && $modelStatus['code']==1){
                $ajaxRet['status'] = 1;
            }else{
                $ajaxRet['msg'] = $modelStatus['message'];
            }
        }
        echo Json::encode($ajaxRet);
        Yii::$app->end();
    }
    /**
     * @Title:actionEvaluate
     * @Description: 购物车成功弹框内容
     * @author:wangmingcha
     * @date:2015-11-24 下午7:38:55
     */
    public function actionAjaxSuccessDialogContent(){
        
        $ajaxRet = ['status'=>0];
        if(Yii::$app->request->getIsAjax()){
        	$userInfo = $this->user_info;
        	$city = isset($userInfo['city']) ? $userInfo['city'] : 0;
                $goods_id = f_post('goods_id',0);
                if($goods_id) {
                        $goodsHigh = SupplierGoods::getSeeAngin($goods_id);
                        if(!$goodsHigh){
                            $goodsHigh = SupplierGoods::getTypeSaleGoods($goods_id,$city);
                        }
                } else {
                	$goodsHigh = [];
                }
                $ajaxRet['html'] = $this->renderPartial('ajax-success-dialog-content',[
                                                        'goodsHigh'=>$goodsHigh,
                                                        'goods_id' =>$goods_id,
                                                            ]);
            $ajaxRet['status'] = 1;
        }
        echo Json::encode($ajaxRet);
        Yii::$app->end();
    }
    
    //删除缓存
    public function actionSuccessCache(){
        $userInfo = f_c('frontend-'.\Yii::$app->user->id.'-new_51dh3.0');
        $city = isset($userInfo['city']) ? $userInfo['city'] : '';
        $goods_id = f_post('goods_id',0);
        
        $goodsHighCacheKey = "type_sale_goods_".$goods_id;
        $goodsHigh ='';
        if(f_c($goodsHighCacheKey)){
            f_del_c($goodsHighCacheKey);
            f_d('清除缓存成功');
        }else{
            f_d('木有缓存');
        }
        
    }
    
    /**
     * @description:ajax 验证
     * @return: return_type
     * @author: sunkouping
     * @date: 2015年12月29日下午4:52:42
     * @modified_date: 2015年12月29日下午4:52:42
     * @modified_user: sunkouping
     * @review_user:
	 * @modified: pei.yu 2015-01-28 14:49
    */
    public function actionAjaxCheckGoods(){
    	$user_id = \Yii::$app->user->id;
    	$id = $_POST['id'];
    	$code = 0;
    	$message = '';
		$flag = "";

    	$cart_info = ShoppingCart::find()->where(['id'=>$id])->asArray()->one();
    	if($cart_info) {
    		$goods_info = SupplierGoods::find()->where(['id'=>$cart_info['goods_id']])->asArray()->one();
    		if($goods_info) {
                        $color_name = \common\models\goods\Color::getColorNameById($goods_info['color_id']);
				//限购待完善
				if(!SupplierGoods::checkMaxSale($goods_info,$cart_info['goods_num'],$user_id)) {
					$message = $goods_info['goods_name'].' '.$color_name.' ' . '超过限购量' ;
				}
				//最低起购量
    			if($goods_info['min_num'] > $cart_info['goods_num']) {
    				$message = $goods_info['goods_name'].' '.$color_name.' '.'小于最低起购量不满足';
    			}
    			//定向发布
    			$special = SpecialInfo::getSpecial($cart_info['goods_id'],$user_id);
    			if($special['status'] == 0) {
    				$message = $goods_info['goods_name'].' '.$color_name.' 为'.$special['message'];
    			}
				if($goods_info['type_id'] == 65){
					$flag .= $goods_info['goods_name']."/";
				}
    		} else {
    			$message = '商品未找到';
    		}
    	} else {
    		$message = '购物车记录未找到';
    	}
		if($flag){
			$flag = substr($flag,0,-1);
		}
    	if($message === ''){
                
    		return json_encode(['code'=>1,'message'=>$message,'flag'=>$flag]);
    	} else {
    		return json_encode(['code'=>$code,'message'=>$message,'flag'=>$flag]);
    	}
    }

	public function actionAjaxNewYear(){
		$user_id = \Yii::$app->user->id;
		$id = $_POST['id'];
		$num = $_POST['num'];
		$goods_id = $_POST['goods_id'];
		$mess = OmsStatus::HappyNewYearPc($user_id,$id,$num,$goods_id);
		//$mess = '非常抱歉,苹果iPhone6 16G公开版(A1586)已停止发货，请于2016-02-14 17:00:00后订货';
		if($mess){
			$res = ['code'=>1,'msg'=>$mess,'goods_id'=>$id]; //不发货
		}else{
			$res = ['code'=>-1];
		}
		echo json_encode($res);
	}

    public function actionAjaxPrePaymentValid(){
       if(Yii::$app->request->getIsAjax()){
           $ajaxRet = [];
           $city = $this->user_info['city'];
           $postParams = Yii::$app->request->post('goods_info');
           $statusMap = [];
		   $tpl = [];
           $crashMsg = '选择项包含需定金支付的商品和一般商品，如需选择定金支付，请先剔除勾选项 ';
           foreach($postParams as $k=>$param){
               $res = DepositInfo::getInfoByGoodsId($param['goods_id'],$city,$param['goods_num']);
			   $tpl[] = Supplier::getTplById($param['goods_id']);
               if($res['status']==0){
                   $crashMsg.= "[".($k+1)."],";
               }
               if(!in_array($res['status'],$statusMap)){
                   $statusMap[] = $res['status'];
               }
           }
           if(count($statusMap)>1){
               $ajaxRet['status'] = 'crash';
               $ajaxRet['msg'] = substr($crashMsg,0,-1);
           }elseif(count(array_unique($tpl))>1){
			   $ajaxRet['status'] = 'tpl';
			   $ajaxRet['msg'] = "选择项中包含第三方物流配送的商品和一般商品，请分开结算";
		   }
           echo Json::encode($ajaxRet);
           Yii::$app->end();
       }
    }
	/**
	 * @description:购物车获取赠品
	 * @return:
	 * @author: wjr
	 * @date:
	 * @modified_date:
	 * @modified_user: 
	 * @review_user:
	 */
	public function actionAjaxGifts(){
		$goods = f_post('goods');
		$gift = array();
		if($goods){
			foreach($goods as $key => $value){
				$gift[$value[0]][]=['goods_num'=>$value[1],'price'=>$value[2],'goods_id'=>$value[3]];
			}
		}
		//f_d($gift);
		$gift = GiftActive::getWhichGift($gift);
		//f_d($gift);
		echo json_encode($gift);die;
	}
	/**
	 * @description:购物车赠品库存校验
	 * @return:
	 * @author: wjr
	 * @date:
	 * @modified_date:
	 * @modified_user:
	 * @review_user:
	 */
	public function actionCheckGifts(){
		$gifts = f_post('gifts',[]);
		$zsp_id = f_post('zsp_id',[]);
		$return = array();
		$zsp_names = array();
		$goods_names = array();
		$active_name = array();
		$res = array();
		foreach($gifts as $key => $value){
			$gift_id = $value[0];
			$result = GiftActive::checkGiftTime($gift_id);
			if($result){
				$active_name[] = GiftActive::find()->select('name')->where(['id'=>$gift_id])->scalar();
			}
			$goods_id = $value[1];
			$goods_num = $value[2];
			$num_avai = SupplierGoods::find()->select('num_avai')->where(['id'=>$goods_id])->scalar();
			if($num_avai < $goods_num){
				$goods_names = (new Query())
				->select('t2.goods_name')
				->from('markting_gift_goods as t1')
				->leftJoin('goods_supplier_goods as t2','t1.good_id = t2.id')
				->where(['t1.gift_id' => $gift_id,'type' => 1])
				->column();
				if($return){
					$return = array_merge($return,$goods_names);
				}else{
					$return = $goods_names;
				}
			}
			if(!$num_avai){
				GiftActive::updateAll(['status'=>3],['id'=>$gift_id]);
			}
		}
		foreach($zsp_id as $key => $value){
			$zsp_names[] = SupplierGoods::find()->select('goods_name')->where(['id'=>$value])->scalar();
		}
		if($return){
			$return=array_intersect($return,$zsp_names);
			$res['msg'] = implode(',', $return);
		}
		if($active_name){
			if(count($active_name)>1){
				$active_name = array_unique($active_name);
			}
			$res['invalid_active'] = implode(',',$active_name);
		}
		if($res){
			echo json_encode($res);
		}else{
			echo 1;
		}
	}
}
