<?php
namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use common\models\user\UserMember;
use common\models\goods\SupplierGoods;
use common\models\markting\SeckillGoods;
use common\models\goods\Photo;
use common\models\goods\BaseGoods;
use common\models\goods\Type;
use common\models\markting\SeckillActive;
use common\models\markting\SeckillOrder;
use common\models\markting\SeckillCity;
use common\models\goods\Unit;
use frontend\components\Controller2016;

class SeckillController extends Controller2016{ 
	
	/**
	 * @description: 秒杀的活动页面
	 * @return: html
	 * @author: sunkouping
	 * @date: 2015年10月31日下午4:18:30
	 * @modified_date: 2015年10月31日下午4:18:30
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionIndex(){
		header("Content-Type: text/html; charset=UTF-8");
		
		$user_id = \Yii::$app->user->id;
		$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		
		$goods_id = f_get('goods_id',0);
		$active_id = f_get('active_id',0);
		
		$ck = 'ms_cache_key'.$user_info['city'].'_'.$active_id.'_'.$goods_id;
		$ck = f_isMobile() ? $ck.'mb' : $ck.'pc';
		
		$this_goods_info = SupplierGoods::find()->where(['id'=>$goods_id])->asArray()->one();//该商品的所有信息，单一的
		if(!$this_goods_info){
			exit('商品未找到');
			return $this->render('/site/nodetails');
		}

        //查询该秒杀商品的自定义描述,惊喜价，是否显示惊喜价
        $this_ms_goods_info = SeckillGoods::find()->where(['goods_id'=>$goods_id,'active_id'=>$active_id])->asArray()->one();
        if($this_ms_goods_info) {
	        $this_goods_info['ms_description'] = $this_ms_goods_info['description'];
	        $this_goods_info['supprice'] = $this_ms_goods_info['supprice'];
	        $this_goods_info['if_supprice'] = $this_ms_goods_info['if_supprice'];
        } else {
        	exit('活动商品未找到');
        }

		$supplier_id = $this_goods_info['supplier_id'];
		$base_id = $this_goods_info['base_id'];

		//获得相册
		$pics = Photo::find()->where(['base_id'=>$base_id])->asArray()->all();
		
		if(!$pics){
			$pics = [];
			$fengming = 'undefined.jpg';
		}else{
			$fengming = '';
			foreach ($pics as $value){
				if($value['is_cover'] == 1);
				$fengming = $value['img_url'];
			}
		}
// 		f_d($pics);
		$base_info = BaseGoods::find()->where(['id'=>$base_id])->asArray()->one();
		
		$mall_id = Type::find()->select(['mall_id'])->where(['id' => $base_info['type_id']])->asArray()->scalar();
		$mall_id = $mall_id ? $mall_id : 1;
		/**
		 * 获取秒杀商品的信息
		 */
		$tong_base = [];
		//商品状态
		$ms_goods = SeckillGoods::find()->where(['active_id'=>$active_id,'goods_id'=>$goods_id])->orderBy('id desc')->asArray()->one();
		
		if ($ms_goods) {
			//查询同活动中的其他商品 用于颜色切换 【同一活动，与此商品共基础商品，不同颜色的商品】
			$all_active_goods = SeckillGoods::find()->select('goods_id')->where(['active_id'=>$active_id])->asArray()->all();
			foreach ($all_active_goods as $value) {
				$v_info = SupplierGoods::find()->select('id,color_id,base_id')->where(['id'=>$value['goods_id']])->asArray()->one();
				if($v_info['base_id'] == $base_id && $v_info['id'] != $goods_id) {
					$tong_base[] = ['goods_id'=>$v_info['id'],'color_id'=>$v_info['color_id']];
				}
			}
		} else {
			exit('未找到该商品');
		}
		
		/**
		 * 获取秒杀活动的信息
		 */
		$time_status = [];
		$active_info = SeckillActive::find()->where(['id'=>$active_id])->asArray()->one();
// 		f_d($active_info);
		if ($active_info) {
			
			$start_time = $active_info['start_time'];
			$end_time = $active_info['end_time'];
			$tian_kai = (strtotime($start_time) - time())/3600/24;
			
			if($tian_kai < 0 ){
				$time_status['status'] = 0;
				$time_status['value'] = $start_time;
			} else if($tian_kai < 1) {
				$time_status['status'] = 1;
				$time_status['value'] = $start_time;
			} else if($tian_kai <= 3) {
				$time_status['status'] = 2;
				$time_status['value'] = ceil($tian_kai);
			} else{
				$time_status['status'] =3;
				$time_status['value'] = $start_time;
			}

			if($active_info['status'] == 1) {
				if(strtotime($start_time) > time()){
					$active_status = 0;
				} else if(time() > strtotime($end_time)){
					$active_status = 2;
				} else {
					//判断已经被购买的数量
					$had_buy_num = SeckillOrder::getThisHadBuy($active_id, $goods_id);
					if($had_buy_num >= $ms_goods['store'] || $this_goods_info['num_avai'] <= 0) {
						$active_status = 2;//库存不足
					} else {
						$active_status = 1;
					}
				}
			} else {
				$active_status = 2;//活动不可用
			}
			
		} else {
			//查不到活动
			exit('活动错误');
		}
// 		f_d($active_status);
		//站点状态
		$active_city = SeckillCity::getActiveCity($active_id,$goods_id,$user_info['city']);
		if(!$active_city) {
			exit('该商品不参加本站点的此次活动');
		}
		$this_goods_info['unit'] = Unit::getUnitNameById($this_goods_info['unit_id']);
		$this->layout = '_blank';
		return  $this->render('index',[
		        'mall_id' => $mall_id,
				'user_info'=>$user_info,
				'this_goods_info'=>$this_goods_info,
				'pics'=>$pics,
				'base_info'=>$base_info,
				'fengming'=>$fengming,
				'goods_status'=>$ms_goods,
				'active_status'=>$active_status,
				'time_status'=>$time_status,
				'active_id'=>$active_id,
				'tong_base'=>$tong_base,
		]);
	}
	/**
	 * @description:验证秒杀
	 * @return: 
	 * @author: sunkouping
	 * @date: 2015年11月2日下午6:56:15
	 * @modified_date: 2015年11月2日下午6:56:15
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionYzms(){
		header("Content-Type: text/html; charset=UTF-8");
                if (isset($_REQUEST['goods_id']) && isset($_REQUEST['active_id']) && isset($_REQUEST['num'])) {
                    $goods_id = $_REQUEST['goods_id'];
                   $active_id = $_REQUEST['active_id'];
                   $goods_num = $_REQUEST['num']; 
			
                }else{
                  return Yii::$app->view->render('@common/widgets/alert/Alert.php',[
					'type'=>'warn',
					'message'=>'未找到该商品',
					'is_next'=>'yes',
					'next_url'=>'/index/index',
			]);
			exit;
			//return $this->msg('未找到该商品', '/index/index');
                }
		
		
		$user_id = \Yii::$app->user->id;
		$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		
		$this->layout = '_blank';
		//判断格挡的概率
		$a = rand(1, 10);
		if($a <= 1) {
			return $this->render('hdmang', [
					'active_id'=>$active_id,
					'goods_id'=>$goods_id,
			]);
		}
		

		/**
		 *获取秒杀商品的信息
		 */
		$goods_status = SeckillGoods::find()->where(['active_id'=>$active_id,'goods_id'=>$goods_id])->orderBy('id desc')->asArray()->one();
		if (!$goods_status) {
			return $this->msg('未找到该商品', '/index/index');
		}
		/**
		 * 读取商品购买数量库存的缓存
		 */
		$allow_num = $goods_status['store'] * 2;
		$ck = 'ms_goods'.$active_id.$goods_id;
		$num_cache = f_c($ck);
		if($num_cache != false) {
			f_c($ck,$num_cache-$goods_num,60);
		} elseif($num_cache === false) {
			f_c($ck,$allow_num,60);
		}
		$num_cache = f_c($ck);
		
		/**
		 * 数据缓存控制访问人数
		*/
		$cr = $ck.'refuse';
		//if($num_cache <= 0) {
		if(1===2) {
			//记录或更新拒绝的人数
			$refuse = f_c($cr);
			if($refuse) {
				f_c($cr,$refuse+1,60);
			} else {
				f_c($cr,1,60);
			}
			$refuse = f_c($cr);
			/**
			 * 缓存数据修正
			*/
			if($refuse >= 20 ){
				$xz = $goods_status['store'] - SeckillOrder::getThisHadBuy($active_id, $goods_id) + 1;
				if($xz){
					f_c($ck,$xz,60);
					f_c($cr,0,60);
				}
			}
			return $this->msg('系统繁忙', "/seckill/index?goods_id=$goods_id&active_id=$active_id");
		}
		/**
		 * 获取该商品的库存信息
		 */
		$goods_numavai = SupplierGoods::find()->select('num_avai')->where(['id'=>$goods_id])->asArray()->one()['num_avai'];
		if($goods_numavai <= 0){
			return $this->msg('商品已被抢光',"/seckill/index?goods_id=$goods_id&active_id=$active_id");
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
		/**
		 * 活动时间
		 */
		$active_info = SeckillActive::find()->where(['id'=>$active_id])->asArray()->one();
		if($active_info['start_time'] >= f_date(time()) || $active_info['end_time'] <= f_date(time())) {
			return $this->msg("亲，非活动时间", "/seckill/index?goods_id=$goods_id&active_id=$active_id");
		}
		
		/**
		 * 可以购买 去结算
		 */
		$goods_data = [];
		$goods_data[0]['goods_id'] = $goods_id;
		$goods_data[0]['goods_num'] = $goods_num;
		$goods_info = SupplierGoods::find()->where(['id'=>$goods_id])->one();
		$goods_data[0]['type_id'] = $goods_info->type_id;
		$goods_data[0]['goods_name'] = $goods_info->goods_name;
		$goods_data[0]['price'] = $goods_status['price'];
		$goods_data[0]['color_id'] = $goods_info->color_id;
		$goods_data[0]['cover_id'] = $goods_info->cover_id;
		$goods_data[0]['supplier_id'] = $goods_info->supplier_id;
		//存入session 跳转到结算页面
		f_s('ms',false);
		f_s('ms',$goods_data);
		header("Location: /check/seckill?ms=1&active_id=$active_id&goods_id=$goods_id&goods_num=$goods_num");exit;
	}
}
