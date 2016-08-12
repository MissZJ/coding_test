<?php
namespace frontend\controllers;
use frontend\components\Controller2016;
use Yii;
use common\models\user\UserMember;
use common\models\other\OtherRegion;

class FourApiController extends Controller2016
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
		return true;
	}
	
	/**
	 * @return string
	 * @description: (400项目)获取客户信息
	 * @author: qiang.zuo
	 * @date: 2015-08-18 11:08:34
	 */
	public function actionGetClientInfo(){
		$phone = $_POST['phone'];
		$out = f_c('get-client-info'.$phone);
		if(empty($out)){
			$out = UserMember::getClientInfo($phone);
			f_c('get-client-info'.$phone,$out,86400);
		}
		return json_encode($out);
	}
	
	/**
	 * @return string
	 * @description: (400项目)获取地区名称
	 * @author: qiang.zuo
	 * @date: 2015年8月18日11:15:47
	 */
	public function actionGetRegionName(){
		$region_id = $_POST['region_id'];
		$out = f_c('get-region-name'.$region_id);
		if(empty($out)){
			$out = OtherRegion::getRegionOneName($region_id);
			f_c('get-region-name'.$region_id,$out,86400);
		}
		return json_encode($out);
	}
	
	/**
	 * @return string
	 * @description: (400项目)获取订单信息
	 * @author: jiangtao.ren
	 * @date: 2015-08-18 11:21:13
	 */
	public function actionGetOrderInfoByNum(){
		$phone = $_POST['phone'];
		$out = f_c('get-order-info-by-num'.$phone);
		if(empty($out)){
			$out = UserMember::getOrderInfoByNum($phone);
			f_c('get-order-info-by-num'.$phone,$out,86400);
		}
		return json_encode($out);
	}
	
	/**
	 * @return string
	 * @description: (400项目)通过手机号获取用户信息
	 * @author: qiang.zuo
	 * @date: 2015-08-18 17:09:03
	 */
	public function actionGetUserInfoByPhone(){
		$phone = $_POST['phone'];
		$out = f_c('get-user-info-by-phone'.$phone);
		if(empty($out)){
			$out = UserMember::getUserInfoByPhone($phone);
			f_c('get-user-info-by-phone'.$phone,$out,86400);
		}
		return json_encode($out);
	}
	
	/**
	 * @return string
	 * @description: (400项目)获取所有省
	 * @author: jiangtao.ren
	 * @date: 2015-08-18 17:09:03
	 */
	public function actionGetAllProvince(){
		$out = f_c('get-all-province');
		if(empty($out)){
			$out = OtherRegion::getRegion(1);
			f_c('get-all-province',$out,864000);
		}
		return json_encode($out);
	}
	
}