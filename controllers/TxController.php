<?php
namespace frontend\controllers;

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
use common\models\user\StoreMall;
use yii\helpers\ArrayHelper;
use common\models\data\CartTongji;
use common\models\skp\Skp;
use common\models\floor\FloorInfo;
use common\models\other\OtherHandpick;

class TxController extends Controller2016{

    public function getNeedStatAction(){
        $this->mall_id = 1;
        return ['actionIndex'];
    }
	/**
	 * @description:通讯商城首页
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年11月23日下午2:50:41
	 */
	public function actionIndex(){
		$this->view->title = '51订货网';
		header("Content-Type: text/html; charset=UTF-8");
		$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		if(!$user_info) {
			$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		}
		$mall_id = 1;
		$life_time = 3600;
		$cach_key = 'tongxun_201606_';
		//用户状态校验
		if($user_info['user_status'] != 1&&$user_info['user_status']!=4){
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',['type'=>'warn','message'=>'帐号状态异常,即将退出登录','is_next'=>'yes','next_url'=>'/site/logout',]);return $view;exit;
		}
		//判断是否是用户  组
		$city_id = $user_info['city'];
		//左侧导航
		$menu = Skp::getmenu(['city'=>$city_id,'mall_id'=>$mall_id,'user_group'=>$user_info['user_group'],'key'=>$cach_key]);
		//楼层信息
		$floor_info = Skp::getFloorInfo($city_id, $mall_id,$user_info['user_group'],$cach_key);
		$floor = $floor_info;
		$one_floor = [array_shift($floor_info)]; if(!$one_floor) {$one_floor = [];}
		$other_floor = $floor_info;
		//一周精选
		$one_week = Skp::gethandPick($city_id,$mall_id,$cach_key);
		
		if($user_info['user_group'] == 1) { //移动用户
			//广告
			$ad = f_c($cach_key.'_cmcc_ad_'.$city_id);
			if(!$ad) {
				$ad = [];
				$ad['banner'] = AdInfo::getAd(52,$city_id,8);				//大banner图
				$ad['banner_next'] = AdInfo::getAd(51,$city_id);		//banner下方
				if($ad['banner_next']) {
					$ad['banner_next'] = array_chunk($ad['banner_next'], 3);
					$tempcount = count($ad['banner_next']);
					if(count($ad['banner_next'][$tempcount - 1]) !== 3) unset($ad['banner_next'][$tempcount - 1]);
				}
				$ad['finance'] = AdInfo::getAd(50,$city_id,4);			//金融8个
				$ad['today_bao'] = AdInfo::getAd(49,$city_id,3);			//今日推荐
				$ad['ad_top'] = AdInfo::getAd(95,$city_id,1);			//顶部广告位
				$ad['ad_middle'] = AdInfo::getAd(105,$city_id,1);		//中部广告位
				$ad['special_column'] = [];
	
				f_c($cach_key.'_cmcc_ad_'.$city_id,false);
			}
			//专区活动
			$active_info = f_c('tongxun_active_'.$city_id);
			if(!$active_info) {
				$active_info = OtherActivityArea::getAreaByCity($city_id);
				f_c('tongxun_active_'.$city_id,$active_info,60);
			}
		} else {
			//广告
			$ad = f_c($cach_key.'_ad_'.$city_id);
			if(!$ad) {
				$ad = [];
				$ad['banner'] = AdInfo::getAd(3,$city_id);				//大banner图
				$ad['banner_next'] = AdInfo::getAd(5,$city_id);		//banner下方
				if($ad['banner_next']) {
					$ad['banner_next'] = array_chunk($ad['banner_next'], 3);
					$tempcount = count($ad['banner_next']);
					if(count($ad['banner_next'][$tempcount - 1]) !== 3) unset($ad['banner_next'][$tempcount - 1]);
				}
				$ad['finance'] = AdInfo::getAd(6,$city_id,8);			//金融4个
				$ad['special_column'] = AdInfo::getAd(7,$city_id,3);	//专栏3个
				$ad['today_bao'] = AdInfo::getAd(9,$city_id,3);			//今日爆品
				$ad['ad_top'] = AdInfo::getAd(95,$city_id,1);			//顶部广告位
				$ad['ad_middle'] = AdInfo::getAd(105,$city_id,1);		//中部广告位
				f_c($cach_key.'_ad_'.$city_id,$ad,3600*12);
			}
			//专区活动
			$active_info = [];
		}
	
		//我的关注
		$my_care = f_c('tongxun_my_care_'.$user_id);
		if(!$my_care) {
			$my_care = FavoriteGoods::getmycare($user_id,$city_id);
			f_c('tongxun_my_care_'.$user_id,$my_care,3600*24);
		}
	
		//用户信息
		$memder_data = f_c('home_user_ext_'.$user_id);
		if(!$memder_data) {
			$memder_data = UserMember::getHomeInfo($user_id);
			f_c('home_user_ext_'.$user_id,$memder_data,3600);
		}
	
		$this->layout = '_blank';
		//搜索框
		$seach = Skp::getSearchData($user_id,$mall_id);
		//横向导航栏
		$menu_heng = f_c('tongxun_menuheng_'.$city_id);
		if($menu_heng === false) {
			$menu_heng = OtherMainMenu::getMainMenu(1,$city_id,8);
			f_c('tongxun_menuheng_'.$city_id,$menu_heng,3600);
		}
	
		//新增最近交易的订单
		$last_order = f_c('tongxun_last_order');
		if(!$last_order) {
			$last_order = OmsGoods::getLastFifty();
			f_c('tongxun_last_order',$last_order,1800);
		}
		//公告信息
		$notice = f_c('tongxun_notice'.$city_id);
		if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
			$notice['novice'] = OtherHelpCenterArticle::getArticleApiList(2,2,$city_id,1)['list'];	//新手
			$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city_id,1)['list'];//促销公告
			f_c('tongxun_notice'.$city_id,$notice,1800);
		}
		$malls = StoreMall::getMalls($user_id);
		$yes_malls = ArrayHelper::getColumn($malls['yes_malls'], 'id');
		if(!in_array(1, $yes_malls)) {
			f_msg('您无法查看此商城', '/home/index');
		}
		// f_d($malls);
		$count = 0;
		if($other_floor){
			$count = count($other_floor);
		}
		//收益
		$profitInfo = UserMember::getProfitByApi();
		return $this->render('tongxun2',[
				'ad'=>$ad,
				'one_week'=>$one_week,
				'one_floor'=>$one_floor,
				'other_floor'=>$other_floor,
				'my_care'=>$my_care,
				'active_info'=>$active_info,
				'memder_data'=>$memder_data,
				'menu'=>$menu,
				'search'=>$seach,
				'menu_heng'=>$menu_heng,
				'user_info'=>$user_info,
				'last_order'=>$last_order,
				'notice'=>$notice,
				'malls'=>$malls,
				'count'=>$count,
				'profitInfo'=>$profitInfo,
				'mall_id'=>1,
				'floor'=>$floor
		]);
	}
	/**
	 * @description:移动用户首页
	 * @return: 
	 * @author: sunkouping
	 * @date: 2015年11月26日下午7:47:42
	 * @modified_date: 2015年11月26日下午7:47:42
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionCmcc(){
		$this->view->title = '51订货网-中国移动';
		header("Content-Type: text/html; charset=UTF-8");
		$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		if(!$user_info) {
			$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		}
		//判断是否是用户  组
		if($user_info['user_group'] == 0) { //移动用户
			return $this->redirect('/home/index');
		}
		$city_id = $user_info['city'];
		//广告
		$ad = f_c('tongxun_ad_'.$city_id);
		if(!$ad) {
			$ad = [];
			$ad['banner'] = AdInfo::getAd(10,$city_id);				//大banner图
			$ad['banner_next'] = AdInfo::getAd(11,$city_id,3);		//banner下方
			$ad['finance'] = AdInfo::getAd(12,$city_id,4);			//金融4个
			$ad['today_bao'] = AdInfo::getAd(13,$city_id);			//今日爆品
			//f_c('tongxun_ad_'.$city_id,$ad,3600*12);
		}
		//最近交易的订单
		$last_order = f_c('tongxun_last_order');
		if(!$last_order) {
			$last_order = OmsGoods::getLastFifty();
			f_c('tongxun_last_order',$last_order,1800);
		}
		//我的关注
		$my_care = f_c('tongxun_my_care_'.$user_id);
		if(!$my_care) {
			$my_care = FavoriteGoods::getmycare($user_id,$city_id);
			f_c('tongxun_my_care_'.$user_id,$my_care,3600*24);
		}
		//楼层信息
		$one_floor = f_c('tongxun_one_floor_'.$city_id);
		if(!$one_floor) {
			$floor_info = HoneFloor::getCityFloor($city_id,1);  // 首先获取楼层的数量
			$one_floor = HoneFloor::getOneFloorData($floor_info); //获取一楼的数据
			f_c('tongxun_one_floor_'.$city_id,$one_floor,3600*24);
		}
		//公告信息
		$notice = f_c('tongxun_notice');
		if(!$notice) {
			$notice = [];
			$notice['novice'] = OtherHelpCenterArticle::getArticleApiList(2,3,$city_id,1)['list'];	//新手
			$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,3,$city_id,1)['list'];//促销公告
			f_c('tongxun_notice',$notice,1800);
		}
		//用户信息
		$memder_data = f_c('tongxun_member_data_'.$user_id);
		if(!$memder_data) {
			$memder_data = UserMember::getHomeInfo($user_id);
			f_c('tongxun_member_data_'.$user_id,$memder_data,3600);
		}
		
		//专区活动
		$active_info = f_c('tongxun_active_'.$city_id);
		if(!$active_info) {
			$active_info = OtherActivityArea::getAreaByCity($city_id);
			f_c('tongxun_active_'.$city_id,$active_info,3600*24);
		}
		//f_d($active_info);
		// 		f_d($notice);
		return $this->render('cmcc',[
				'ad'=>$ad,
				'one_floor'=>$one_floor,
				'my_care'=>$my_care,
				'last_order'=>$last_order,
				'notice'=>$notice,
				'memder_data'=>$memder_data,
				'active_info'=>$active_info,
		]);
	}
	/**
     * @description:签到
     * @return: 
     * @author: jiangtao.ren
     * @date: 2015-12-1
     * @review_user:honglang.shen
     */
    public function actionSign(){
    	$user_id = \Yii::$app->user->id;
        $return = Sign::updateSignInfo();
        $memder_data = UserMember::getHomeInfo($user_id);
        f_c('tongxun_member_data_'.$user_id,$memder_data,3600);
        return json_encode($return);
    }

    /**
     * @description:点击广告统计
     * @throws \yii\base\ExitException
     */
    public function actionAdClickStat(){
        if(Yii::$app->request->getIsAjax()){
            $ret['status'] = -1;
            $model = new OtherAdClickStat();
            $model->position_id = Yii::$app->request->post('position_id');
            $model->ad_id = Yii::$app->request->post('ad_id');
            $model->user_id = $this->user_info['id'];
            $model->create_time = date("Y-m-d H:i:s");
            if($model->save()){
                $ret['status'] = 1;
            }
            echo Json::encode($ret);
            Yii::$app->end();
        }
    }
}
