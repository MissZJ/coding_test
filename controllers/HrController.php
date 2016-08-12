<?php
namespace frontend\controllers;

use common\models\other\OtherTown;
use common\models\other\OtherAdClickStat;
use Yii;
use yii\debug\models\search\Debug;
use yii\filters\AccessControl;
use yii\helpers\Json;
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
use common\models\other\AdPosition;
use common\models\other\AdInfo;
use common\models\other\HoneFloor;
use common\models\other\FavoriteGoods;
use common\models\other\OtherHelpCenterArticle;
use frontend\components\Controller2016;
use common\models\other\OtherActivityArea;
use common\models\user\Sign;
use common\models\other\MallRnav;
use common\models\search\HotSearch;
use common\models\other\OtherMainMenu;
use common\models\user\UserStoreType;
use common\models\user\Pusher;
class HrController extends Controller2016{ 
	/**
	 * @description:协助注册
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2016年1月7日 17:36:37
	*/
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
				'regist','check-pusher-pwd','reg','region',
				'coupon',
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
	 * @description:
	 * @return: Ambigous <string, string>
	 * @author: sunkouping
	 * @date: 2016年1月7日下午5:40:22
	 * @modified_date: 2016年1月7日下午5:40:22
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionRegist(){
	    $this->layout = '_blank';
	    
		$provinceArr = OtherRegion::getRegion('1');
		$store_property = UserMember::storePropertys();
		$store_type = UserStoreType::getStoreInfo();
		 
		return $this->render('regist',[
				'provinceArr' => $provinceArr,
				'store_property' => $store_property,
				'store_type' => $store_type,
		]);
	}

	/**
	 *
	 * @Title: actionCheckPusherPwd
	 * @Description: 检测推广人员账号与密码
	 * @return: string
	 * @author: yulong.wang
	 * @date: 2016-1-5上午11:42:07
	 */
	public function actionCheckPusherPwd(){
		$push_name = f_post('push_name','');
		$push_pwd = f_post('push_pwd','');
		$returnVal = ['status'=>0,'msg'=>''];
		
		if($push_name != '' && $push_pwd != '') {
			$pusherInfo = Pusher::find()->where(['login_account'=>$push_name])->one();
			if ($pusherInfo) {
				if(md5($push_pwd) == $pusherInfo->password) {
					$returnVal = ['status' => 1, 'msg' => '恭喜'];
				} else {
					$returnVal = ['status' => 0, 'msg' => '推广员密码错误'];
				}
			} else {
				$returnVal = ['status' => 0, 'msg' => '推广员账号错误'];
			}
		} else {
			$returnVal = ['status' => 0, 'msg' => '参数不存在'];
		}
		return json_encode($returnVal);
	}
	
	/**
	 * @description:
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2016年1月7日下午8:21:04
	 * @modified_date: 2016年1月7日下午8:21:04
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionReg(){
		$model = new UserMember();
               if(isset($_POST['UserMember']) && isset($_POST['town_name'])){
                  $user = $_POST['UserMember'];
                  $town_name = $_POST['town_name']; 
                }else{
                 f_msg('信息不全！',f_url(['site/register-login?type=54']));   
                }
		if ($model->load(Yii::$app->request->post())) {
			$model->user_status = 1;
			$model->login_account = $model->phone;
			$model->user_group = 0;
			$model->password = md5($model->password);
			$model->reg_time = f_date(time());
			$model->last_ip = f_ip();
			$pusher_id = Pusher::find()->select('id')->where(['login_account'=>$user['pusher_id']])->scalar();
			if($pusher_id) {
				$model->pusher_id = $pusher_id;
			}
			if(!$model->month_sale_money) {
				$model->month_sale_money = 1;
			}
			if(!$model->store_property){
				$model->store_property = 0;
			} 
			
			if($model->save()){
				$r_model = new ReceiveAddress();
				$r_model->name = $model->user_name;
				$r_model->phone = $model->phone;
				$r_model->province = $model->province;
				$r_model->city = $model->city;
				$r_model->district = $model->district;
				$r_model->address = $model->address;
				$r_model->user_id = $model->id;
				$r_model->status = 1;
				$r_model->save();
				if($model->town_id==-1){
					$town = new OtherTown();
					$town->user_id = $model->id;
					$town->town_name = $town_name;
					$town->add_time = f_date(time());
					if(!$town->save()){
						f_msg('乡镇信息保存失败！',f_url(['site/register-login?type=54']));
					}
				}
				//
// 				发放注册红包
				//CouponUser::addCoupon(375,$model->id,'注册添加',f_date(time()),f_date(strtotime("+7 days",time())));
				f_msg('注册成功,立即登录',f_url(['site/register-status']));
				
			} else {
				$model->save();
				f_d($model->errors);
			}
			//return $this->redirect(['view', 'id' => $model->id]);
		}
	}
	
	/**
	 * @description:一步注册
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2016年1月10日下午3:54:33
	 * @modified_date: 2016年1月10日下午3:54:33
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionRegion(){
		$model = new UserMember();
                if(isset($_POST['UserMember']) && isset($_POST['town_name'])){
                   $user = $_POST['UserMember'];
                  $town_name = $_POST['town_name']; 
                }else{
                 f_msg('信息不全！',f_url(['site/register-login?type=54']));   
                }
		
		if ($model->load(Yii::$app->request->post())) {
			$model->user_status = 2;
			$model->login_account = $model->phone;
			$model->user_group = 0;
			$model->password = md5($model->password);
			$model->reg_time = f_date(time());
			$model->last_ip = f_ip();
			
			if(!$model->month_sale_money) {
				$model->month_sale_money = 1;
			}
			if(!$model->store_property){
				$model->store_property = 0;
			}
				
			if($model->save()){
				$r_model = new ReceiveAddress();
				$r_model->name = $model->user_name;
				$r_model->phone = $model->phone;
				$r_model->province = $model->province;
				$r_model->city = $model->city;
				$r_model->district = $model->district;
				$r_model->address = $model->address;
				$r_model->user_id = $model->id;
				$r_model->status = 1;
				$r_model->save();

				if($model->town_id==-1){
					$town = new OtherTown();
					$town->user_id = $model->id;
					$town->town_name = $town_name;
					$town->add_time = f_date(time());
					if(!$town->save()){
						f_msg('乡镇信息保存失败！',f_url(['site/register-login?type=54']));
					}
				}
				f_msg('注册成功,等待审核通过',f_url(['site/register-status']));
	
			} else {
				$model->save();
				f_d($model->errors);
			}
		}
	}

	//修改乡镇信息
	public function actionTown(){
		$town_id = f_post('town_id',0);
		$town_name = f_post('$town_name','中国');
		$userId = Yii::$app->user->id;
		$user = UserMember::findOne($userId);
		$user->town_id = $town_id;
		if($user->save()) {
			if ($town_id == -1) {
				$town = new OtherTown();
				$town->user_id = $userId;
				$town->town_name = $town_name;
				$town->add_time = f_date(time());
				if ($town->save()) {
					return 0;
				}else{
					return '乡镇信息保存失败！';
				}
			}
		}else{
			return '乡镇信息修改失败！';
		}
	}
	/**
	 * @description:跨系统添加红包
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2016年2月29日上午11:04:56
	 * @modified_date: 2016年2月29日上午11:04:56
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionCoupon(){
		
		
		$couppon_id = isset($_REQUEST['coupon_id']) ? $_REQUEST['coupon_id'] : 0;
		$login_account = isset($_REQUEST['login_account']) ? $_REQUEST['login_account'] : '';
		$start_time = isset($_REQUEST['start_time']) ? $_REQUEST['start_time'] : '';
		$end_time =  isset($_REQUEST['end_time']) ? $_REQUEST['end_time'] : '';
		
		$status = 0;
		$message = '';
		if($couppon_id && $login_account && $start_time && $end_time){
			$user_id = UserMember::find()->select('id')->where(['login_account'=>$login_account])->scalar();
			if($user_id) {
				//重复性检测
				$if_had = false;//CouponUser::find()->where(['user_id'=>$user_id,'coupon_id'=>$couppon_id])->one();
				if($if_had) {
					$status = 2;
					$message = '重复添加';
				} else {
					$out = CouponUser::addCoupon($couppon_id, $user_id,'金融接口', $start_time, $end_time);
					if($out) {
						$status = 1;
						$message = '成功';
					} else {
						$message = '添加失败';
					}
				}
			} else {
				$message = '未找到用户';
			}
		} else {
			$message = '参数不全';
		}
		$out = [
			'status'=>$status,
			'message'=>$message,
				'coupon_id'=>$couppon_id,
				'login_account'=>$login_account,
				'start_time'=>$start_time,
				'end_time'=>$end_time,
		];
		return json_encode($out);
	}
	/**
	 * @description:
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2016年2月29日下午1:47:14
	 * @modified_date: 2016年2月29日下午1:47:14
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionDetail(){
		$user_id = f_get('user_id',0);
		$goods_id = f_get('goods_id',0);
		f_d(SupplierGoods::checkDetails($goods_id, $user_id));
	}
	
}
