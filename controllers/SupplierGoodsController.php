<?php
namespace frontend\controllers;

use common\models\goods\GoodsPresell;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use common\models\goods\SupplierGoods;
use common\models\goods\GoodsAttrName;
use common\models\goods\GoodsAttrValue;
use common\models\goods\Photo;
use common\models\goods\Color;
use common\models\goods\Unit;
use common\models\goods\BaseGoods;
use common\models\other\FavoriteGoods;
use frontend\components\Controller2016;
use common\models\user\SupplierCityScreen;
use yii\db\Query;
use common\models\order\OrderMinusGoods;
use common\models\user\UserMember;
use common\models\markting\SpecialInfo;
use common\models\user\GoodsPrivilegeUser;
use common\models\user\StoreMall;
use common\models\order\OrderNoticeGoods;
use common\models\goods\ContractGoodsCity;
use common\models\user\CmccUserQd;
use common\models\markting\LadderGroup;
use common\models\goods\Exvalue;
use common\models\markting\GiftGoods;
use common\models\markting\GiftActive;
use common\models\user\ShoppingCart;
/**
 * Site controller
 */
class SupplierGoodsController extends Controller2016
{
   /**
     * @description 基础商品的供应商商品列表
     * @author honglang.shen
     * @date 2015-11-26
     */
    public function actionIndex(){
        $base_id = f_get('id',0);
        $order = f_get('sort','0');
        $order_by = f_get('order',0);
        $user_id = \Yii::$app->user->id;
        $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
        $contract = f_get('contract',0);
        $page = f_get('page',0);
        $StoreMall = StoreMall::getMainMall($user_id);
        $mall_id = f_get('mall_id',$StoreMall);
        if(!preg_match('/^\d+$/',$mall_id)){
                 return f_msg('搜索条件不符合','/home/index');   
                }
        if (0 == $page){
            $page = 1;
        }
    	//更新总销量数据
    	$connetion = \Yii::$app->db;
    	$sql = "select * from skp_run_api where id = ".$base_id;
    	$out = $connetion->createCommand($sql)->queryOne();
    	if($out){
    		$model = BaseGoods::findOne($base_id);
    		$sql = "SELECT SUM(sale_num) as num FROM `goods_supplier_goods` WHERE base_id = ".$base_id;
    		$sale_num = UserMember::findBySql($sql)->asArray()->one();
    		$model->total_sales = $model->total_sales + $sale_num['num'];
    		$model->save();
    		$sql = "delete from skp_run_api where id = ".$base_id;
    		$connetion->createCommand($sql)->execute();
    	}
    	//更新结束
        $like = FavoriteGoods::find()->where(['user_id'=>$this->user_info['id'],'base_id'=>$base_id])->asArray()->one();
        //基础商品
        $BaseGoods = BaseGoods::find()->where(['id'=>$base_id,'status'=>[1,2,3]])->asArray()->one();
        if(empty($BaseGoods)){
            $BaseGoods['id'] = 0;
            $BaseGoods['name'] = '';
            $BaseGoods['cover_id'] = 0;
            $BaseGoods['tips'] = '';
            $BaseGoods['is_brush'] = '';
            $BaseGoods['unit_id'] = 0;
            $base_id = 0;
        }
        //属性
        $attr = BaseGoods::getBaseGoodsAttr($base_id);
        //所有颜色
        $allColor = Color::getColorByBaseId($base_id,$this->user_info['city']);
        //f_d($allColor);
        $color_id = f_get('color_id') ? f_get('color_id') :'';
        //单位
        $allUnit = \yii\helpers\ArrayHelper::map(Unit::find()->asArray()->all(), 'id', 'name');
        //所有供应商商品
        $data = SupplierGoods::getAllSupplierGoodsPriceNumSkp($base_id,$this->user_info,$mall_id);
        $BaseGoods['low'] = $data['low'];
        $BaseGoods['high'] = $data['high'];
        $num = $data['num'];
        $count_sale_num = $data['sale_num'];

        $page_size = 10;
        $allPage = ceil($num/$page_size);
        $startPage = ($page-1)*$page_size;
        $limit = " LIMIT $startPage,$page_size";

        if($contract == 0){ 
            //$allGoodsInfo = SupplierGoods::getAllSupplierGoods($base_id, $color_id, $this->user_info['city'],$order,$order_by,$limit);
        	$allGoodsInfo = SupplierGoods::getSupplierGoodsByBaseid($base_id, $color_id, $this->user_info,$order,$order_by,$page,$mall_id);
        }else{
            $allGoodsInfo = SupplierGoods::getAllContractGoods($base_id, $color_id, $this->user_info['city'],$order,$order_by,$limit); 
        }
        //商品特权用户
        $privilege = GoodsPrivilegeUser::getUserPrivilege($this->user_info['id']);
        foreach ($allGoodsInfo as $key=>$value) {
            //预售商品
            $presell = GoodsPresell::getPresellGoodsStatus($value['id']);
            $allGoodsInfo[$key]['presell'] = $presell;
            $minus_price = OrderMinusGoods::getMinusPrice($value['id'], $user_info['city']);
            $allGoodsInfo[$key]['minus_price'] = $minus_price;
            $allGoodsInfo[$key]['jt_price'] = LadderGroup::getGroupId($value['id'], $user_info['city']);
            $special = SpecialInfo::getSpecial($value['id'],$user_info['id']);
            if($special['status'] == 0) {
                unset($allGoodsInfo[$key]);
				continue;
            }
			//24小时退换
			$oneday = Exvalue::find()->select('id')->where(['goods_id'=>$value['id']])->asArray()->one();
			//echo $value['id'];die;
			//f_d($oneday);die;
			if(empty($oneday)){
				$allGoodsInfo[$key]['oneday'] = 0;
			}else{
				$allGoodsInfo[$key]['oneday'] = 1;
			}
			
			$allGoodsInfo[$key]['is_seas'] = \common\models\seas\SeasGoodsType::isSeasGoods($value['id']);
			$gift_id = GiftActive::checkGoodsIsMainGoods($allGoodsInfo[$key]['id']);
			//f_d($allGoodsInfo[$key]['id']);
			if(!$gift_id){
				$allGoodsInfo[$key]['msg'] = "";
			}else{
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
				$allGoodsInfo[$key]['msg'] = $msg;
			}
        }
         //if(in_array($value['privilege'],$privilege)){}
        //f_d($allGoodsInfo);
         //屏蔽供应商
        $ScreenSupplier = SupplierCityScreen::getAllSupplier($this->user_info['city'],$this->user_info['user_group']);
        return $this->render('index',[
            'BaseGoods' => $BaseGoods,
            'allGoodsInfo' => $allGoodsInfo,
            'attr' => $attr,
            'allColor' => $allColor, 
            'base_id' => $base_id,
            'order'=>$order,
            'order_by'=>$order_by,
            'count_sale_num'=>$count_sale_num,
            'like'=>$like,
            'city'=>$this->user_info['city'],
            'page'=>$page,
            'allPage'=>$allPage,
            'num'=>$num,
            'ScreenSupplier'=>$ScreenSupplier,
            'privilege'=>$privilege,
            'mall_id'=>$mall_id,
            'user_info'=>$this->user_info,
            'allUnit'=>$allUnit,
        ]);
    }
    /**
   * @Title: actionGetOrderNotice
   * @Description: 下单通知系统
   * @return: return_type
   * @author: honglang.shen 
   * @date: 2016-1-21
  */
  public function actionGetOrderNotice(){
    $goods_id = f_post('goods_id',0);
    $data = '';
    $data = OrderNoticeGoods::getOrderNoticeGoods($goods_id);
    //f_d($data);
    if(empty($data)){
      $return = '';
    }else{
      $return = $data;
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
    $data = '';
    
    $data = OrderNoticeGoods::getOrderNoticeGoods($goods_id);
    
    if(empty($data)){
      $return = 0;
    }else{
      $return = $data;
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
  
  /*
   * @description : 是不是合约机商品
   * @author : lxzmy
   * @date : 2016-2-19 16:39
   */
  public function actionIsHeYue(){
      $res = 0;
      $goods_id = $_POST['goods_id'];
      $user_id = $this->user_info['id'];
      if($goods_id){
          $is_contra = ContractGoodsCity::find()->where(['goods_id'=>$goods_id])->one();//判断是否为合约机商品
          if(!empty($is_contra)){
              $q_no = CmccUserQd::find()->where(['user_id'=>$user_id])->asArray()->one();
              if($q_no){
                  $res = 1;   //不需要输入
              }else{
                  $res = 3;   //提示输入渠道号  
              }
          }
      }else{
          $res = 2;  //商品不存在
      }
      return $res;
  }
  
  /*
   * @description : 满赠商品列表页面
   * @author : wangjiarui
   * @date : 2016-6-30 9:46
   */
  public function actionGiftList(){
  	$this->layout='_blank';
  		$giftId = f_get('id');
		
  		$type = GiftActive::find()->select('type')->where(['id'=>$giftId])->scalar();
  		if($type == 1||$type == 3){
  			$good_id = GiftGoods::find()->select('good_id')->where(['gift_id' => $giftId,'type' => 1])->scalar();
  			return $this->redirect('/goods/detail?id='.$good_id);
  		}
  		$mall_id = f_get('mall_id');
  		$user_id = \Yii::$app->user->id;
  		$res = GiftGoods::find()->select('good_id')->where(['gift_id' => $giftId,'type' => 1])->column();
  		$D2_goods = ShoppingCart::getAllGoodsForPc($user_id,1);
  		$D3_goods = ShoppingCart::getAllGoodsForPc($user_id,2);
  		$D2_goods = ShoppingCart::group($D2_goods);
  		$D3_goods = ShoppingCart::group($D3_goods);
  		$gift_list = array();
  		//f_d($D3_goods);
  		foreach($D2_goods['effective'] as $key => $value){
  			$gift_id = GiftActive::checkGoodsIsMainGoods($value['goods_id']);
  			if($gift_id&&$gift_id==$giftId){
  				$gift_list[] = $value;
  			}
  		}
  		foreach($D3_goods['effective'] as $key => $value){
  			$gift_id = GiftActive::checkGoodsIsMainGoods($value['goods_id']);
  			if($gift_id&&$gift_id==$giftId){
  				$gift_list[] = $value;
  			}
  		}
  		$list = array();
  		$count = count($gift_list);
  		for($i=0;$i<=$count-1;$i=$i+6){
  			$list[]=array_slice($gift_list,$i,6);
  		}
  		//f_d($D2_goods);
  		$total_price = 0;
  		$total_num = 0;
  		foreach($gift_list as $key => $value){
  			$total_price += $value['goods_num']*$value['price'];
  			$total_num += $value['goods_num'];
  		}
  		foreach($res as $value){
  			$goodsInfo[] = (new Query())->select('t1.*,t2.short_name')
  			->from('goods_supplier_goods as t1')
  			->leftJoin('user_supplier as t2','t1.supplier_id=t2.id')
  			->where(['t1.id'=>$value])
  			->one();
  			//$goodsInfo[] = SupplierGoods::find()->leftJoin('user_supplier as b')->where(['id'=>$value])->asArray()->one();
  		}
  		$gift_active = GiftActive::getRules($giftId);
  		//f_d($list);
  		//$allGoodsInfo[$key]['msg'] = $res;
  		$msg = array();
  		if($gift_active['type'] == 1||$gift_active['type'] == 2){
  			foreach($gift_active['rules'] as $k => $v){
  				$msg []= "满".$v."元送赠品";
  			}
  		}elseif($gift_active['type'] == 3||$gift_active['type'] == 4){
  			foreach($gift_active['rules'] as $k => $v){
  				$msg []= "满".$v."个送赠品";
  			}
  		}
  		if($msg){
  			$msg = array_unique($msg);
  		}
  		$msg = implode(';',$msg);
  		foreach($goodsInfo as $key => $value){
  			$arr[] = $value['price'];
  		}
  		$sort = f_get('sort','');
  		if($sort == 2){
  			array_multisort($arr, SORT_ASC, $goodsInfo);
  		}elseif($sort == 1){
  			array_multisort($arr, SORT_DESC, $goodsInfo);
  		}
  		$count = count($goodsInfo);
  		//f_d($msg);
  		return $this->render('gift-list',[
  				'goodsInfo' => $goodsInfo,
  				'mall_id' => $mall_id,
  				'user_info'=>$this->user_info,
  				'gift_list'=>$gift_list,
  				'total_price'=>$total_price,
  				'total_num'=>$total_num,
  				'msg'=>$msg,
  				'list'=>$list,
  				'user_id'=>$user_id,
  				'giftId'=>$giftId,
  				'count'=>$count,
  				'sort'=>$sort
  		]);
  }
  /*
   * @description : 满赠商品列表页面加入购物车
   * @author : wangjiarui
   * @date : 2016-6-30 9:46
   */
  public function actionAddCart(){
  		$user_id = f_get('user_id');
  		$goods_id = f_get('goods_id');
  		//$goods_num = ShoppingCart::find()->select('goods_num')->where(['user_id'=>$user_id,'goods_id'=>$goods_id])->scalar();
  		$goods_num = 1;
  		$res = ShoppingCart::addCart($user_id,$goods_id,$goods_num);
  		echo $res['message'];
  		
  		
  }
}
