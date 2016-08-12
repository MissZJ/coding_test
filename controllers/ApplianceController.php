<?php
namespace frontend\controllers;

use frontend\components\Controller2016;
use Yii;
use common\models\other\ActiveModelItems;
use common\models\other\ActiveModel;
use common\models\other\PrizesUser;
use common\models\other\Prizes;
use common\models\markting\CouponUser;
use common\models\other\MallRnav;
use common\models\other\AdInfo;
use common\models\other\LifeFloor;
use common\models\user\UserMember;
use common\models\search\HotSearch;
use common\models\other\OtherMainMenu;
use common\models\other\OtherHelpCenterArticle;
use common\models\user\StoreMall;
use ms\controllers\ContractGoodsCityController;
use yii\helpers\ArrayHelper;
use common\models\floor\FloorInfo;
use common\models\other\OtherHandpick;
use common\models\goods\BaseGoods;
use common\models\goods\SupplierGoods;

class ApplianceController extends Controller2016{
	/**
	 * @description:家电生活管
	 * @return: string
	 * @author: sunkouping
	 * @date: 2016年1月27日下午2:04:34
	 * @modified_date: 2016年1月27日下午2:04:34
	 * @modified_user: sunkouping
	 * @review_user:
	*/
    public function getNeedStatAction(){
        $this->mall_id = 4;
        return ['actionIndex'];
    }

	public function actionIndex1(){
		header("Content-Type: text/html; charset=UTF-8");
		$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		
		$life_time = 3600;
		
		if(!$user_info) {
			$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		}
		//判断是否是用户  组
		$city_id = $user_info['city'];
		//用户状态校验
		if($user_info['user_status'] != 1&&$user_info['user_status']!=4){
			$view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
					'type'=>'warn',
					'message'=>'帐号状态异常,即将退出登录',
					'is_next'=>'yes',
					'next_url'=>'/site/logout',
			]);
			return $view;exit;
		}
		$menu = f_c('appliance_menu_'.$city_id);
		if($menu === false) {
			$menu = [];	//左侧导航数据
			$menu = MallRnav::getRightNavData(4,$user_info['city'], 0);
			f_c('appliance_menu_'.$city_id,$menu,$life_time);
		}
		//广告
		$ad = f_c('appliance_ad_'.$city_id);
		if($ad === false) {
			$ad = [];
			$ad['week'] = AdInfo::getAd(84,$city_id,5); 				//一周精选
			$ad['banner'] = AdInfo::getAd(80,$city_id);				//大banner图
			$ad['banner_next'] = AdInfo::getAd(81,$city_id,3);		//banner下方
			$ad['finance'] = AdInfo::getAd(83,$city_id,4);			//金融4个
			$ad['today_bao'] = AdInfo::getAd(82,$city_id,1);			//今日爆品
			$ad['ad_top'] = AdInfo::getAd(97,$city_id,1);			//顶部广告位
			$ad['ad_middle'] = AdInfo::getAd(107,$city_id,1);		//中部广告位
			f_c('appliance_ad_'.$city_id,$ad,$life_time);
		}
		//楼层信息
		$floor = f_c('appliance_floor_'.$city_id);
		if($floor === false) {
			$floor = [];
			$floor_info = LifeFloor::getCityFloor($city_id,0);  // 首先获取楼层的数量
			$floor = LifeFloor::getOtherFloorData($floor_info); //获取一楼的数据
			f_c('appliance_floor_'.$city_id,$floor,$life_time);
		}
		//用户信息
		$memder_data = f_c('home_user_ext_'.$user_id);
		if(!$memder_data) {
			$memder_data = UserMember::getHomeInfo($user_id);
			f_c('home_user_ext_'.$user_id,$memder_data,3600);
		}
		
		$this->layout = '_blank';
		//搜索框
		$seach = f_c('appliance_search_'.$user_id);
		if($seach){
			$seach = [];
			$key_worded = [];
			$history = json_decode(f_ck('key_words'));
			if(!empty($history)){
				$history =  array_reverse($history);
				foreach ($history as $v){
					if(in_array($v, $key_worded)){
						continue;
					}
					$key_worded[] = $v;
				}
			}
			$seach['last'] = $key_worded;
			//正在热搜
			$hotSearch = HotSearch::getHotSearch(1);
			$seach['hot_search'] = $hotSearch;
			//商城
			$seach['mall_id'] = 4;
			//热门关键字
			$hot_keywords = HotSearch::getHotSearch(1);
			$seach['hot_keywords'] = $hot_keywords;
			f_c('appliance_search_'.$user_id,$seach,60);
		}
		//横向导航栏
		$menu_heng = f_c('appliance_menuheng_'.$city_id);
		if($menu_heng === false) {
			$menu_heng = OtherMainMenu::getMainMenu(4,$city_id,8);
			f_c('appliance_menuheng_'.$city_id,$menu_heng,$life_time);
		}
		//公告信息
		$notice = f_c('appliance_notice'.$city_id);
		if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
			$notice = [];
			$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city_id,4)['list'];//促销公告
			f_c('appliance_notice'.$city_id,$notice,1800);
		}
		
		//该用户可看商城
    	$malls = StoreMall::getMalls($user_id);
    	$yes_malls = ArrayHelper::getColumn($malls['yes_malls'], 'id');
    	if(!in_array(4, $yes_malls)) {
    		f_msg('您无法查看此商城', '/home/index');
    	}
    	
    	//收益
    	$profitInfo = UserMember::getProfitByApi();
		
		// f_d($memder_data);
		return $this->render('index',[
			    'ad'=>$ad,
				'floor'=>$floor,
				'memder_data'=>$memder_data,
				'menu'=>$menu,
				'search'=>$seach,
				'menu_heng'=>$menu_heng,
				'user_info'=>$user_info,
				'notice'=>$notice,
				'malls'=>$malls,
				'profitInfo'=>$profitInfo,
				'mall_id'=>4,
		]);
	}
	/**
	 * @description:
	 * @return: unknown|Ambigous <string, string>
	 * @author: sunkouping
	 * @date: 2016年5月12日上午9:25:56
	 * @modified_date: 2016年5月12日上午9:25:56
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionIndex(){
		header("Content-Type: text/html; charset=UTF-8");
		$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		$this->view->title = '家电生活馆-51订货网';
		$life_time = 120;
	
		if(!$user_info) {
			$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		}
		//判断是否是用户  组
		$city_id = $user_info['city'];
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
		$menu = f_c('appliance0512_menu_'.$city_id);
		if($menu === false) {
			$menu = [];	//左侧导航数据
			$menu = MallRnav::getRightNavData(4,$user_info['city'], 0);
			f_c('appliance0512_menu_'.$city_id,$menu,$life_time);
		}
		//广告
		$ad = f_c('appliance0512_ad_'.$city_id);
		if($ad === false) {
			$ad = [];
			$ad['banner'] = AdInfo::getAd(80,$city_id);				//大banner图
			$ad['banner_next'] = AdInfo::getAd(81,$city_id);		//banner下方
			if($ad['banner_next']) {
				$ad['banner_next'] = array_chunk($ad['banner_next'], 3);
				$tempcount = count($ad['banner_next']);
				if(count($ad['banner_next'][$tempcount - 1]) !== 3) unset($ad['banner_next'][$tempcount - 1]);
			}
// 			$temp_ad = [];
// 			for ($i=0;$i<ceil(count($ad['banner_next'])/3);$i++) {
// 				$temp_ad[] = array_slice($ad['banner_next'],$i*3,3);
// 			}
// 			$ad['banner_next'] = $temp_ad;
			$ad['finance'] = AdInfo::getAd(83,$city_id,4);			//金融4个
			$ad['today_bao'] = AdInfo::getAd(82,$city_id,1);			//今日爆品
			$ad['ad_top'] = AdInfo::getAd(97,$city_id,1);			//顶部广告位
			$ad['ad_middle'] = AdInfo::getAd(107,$city_id,1);		//中部广告位
			$ad['brand_eight'] = AdInfo::getAd(84,$city_id,8);		//一周精选下方的 8个品牌
			f_c('appliance0512_ad_'.$city_id,$ad,$life_time);
		}
		//楼层信息
		$floor = f_c('appliance0512_floor_'.$city_id);
		if($floor === false) {
			$floor = [];
			$floor = FloorInfo::getFloorExitOne($city_id,4);
			f_c('appliance0512_floor_'.$city_id,$floor,$life_time);
		}
		//用户信息
		$memder_data = f_c('home_user_ext_'.$user_id);
		if(!$memder_data) {
			$memder_data = UserMember::getHomeInfo($user_id);
			f_c('home_user_ext_'.$user_id,$memder_data,3600);
		}
	
		$this->layout = '_blank';
		//搜索框
		$seach = f_c('appliance0512_search_'.$user_id);
		if($seach){
			$seach = [];
			$key_worded = [];
			$history = json_decode(f_ck('key_words'));
			if(!empty($history)){
				$history =  array_reverse($history);
				foreach ($history as $v){
					if(in_array($v, $key_worded)){
						continue;
					}
					$key_worded[] = $v;
				}
			}
			$seach['last'] = $key_worded;
			//正在热搜
			$hotSearch = HotSearch::getHotSearch(1);
			$seach['hot_search'] = $hotSearch;
			//商城
			$seach['mall_id'] = 4;
			//热门关键字
			$hot_keywords = HotSearch::getHotSearch(1);
			$seach['hot_keywords'] = $hot_keywords;
			f_c('appliance0512_search_'.$user_id,$seach,60);
		}
		//横向导航栏
		$menu_heng = f_c('appliance0512_menuheng_'.$city_id);
		if($menu_heng === false) {
			$menu_heng = OtherMainMenu::getMainMenu(4,$city_id,8);
			f_c('appliance0512_menuheng_'.$city_id,$menu_heng,$life_time);
		}
		//公告信息
		$notice = f_c('appliance0512_notice'.$city_id);
		if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
			$notice = [];
			$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city_id,4)['list'];//促销公告
			f_c('appliance0512_notice'.$city_id,$notice,1800);
		}
	
		//该用户可看商城
		$malls = StoreMall::getMalls($user_id);
		$yes_malls = ArrayHelper::getColumn($malls['yes_malls'], 'id');
		if(!in_array(4, $yes_malls)) {
			f_msg('您无法查看此商城', '/home/index');
		}
		 
		//收益
		$profitInfo = UserMember::getProfitByApi();
		//一周精选
		$one_week = f_c('appliance0512_one_week_'.$city_id);
		if(!$one_week){
			
			$one_week = OtherHandpick::getWeekInfoB($city_id,4);
			f_c('appliance0512_one_week_'.$city_id,$one_week,$life_time);
		}
	//f_d($ad['banner_next']);
		return $this->render('index1',[
				'ad'=>$ad,
				'floor'=>$floor,
				'memder_data'=>$memder_data,
				'menu'=>$menu,
				'search'=>$seach,
				'menu_heng'=>$menu_heng,
				'user_info'=>$user_info,
				'notice'=>$notice,
				'malls'=>$malls,
				'profitInfo'=>$profitInfo,
				'mall_id'=>4,
				'one_week'=>$one_week,
		]);
	}
}
