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
use common\models\other\MallBase;
use common\models\floor\FloorInfo;
use common\models\other\OtherHandpick;
use common\models\goods\SupplierGoods;
use common\models\goods\BaseGoods;

class GlassesController extends Controller2016{
	/**
	 * @description:眼睛商城
	 * @return: string
	 * @author: sunkouping
	 * @date: 2016年1月27日下午2:04:34
	 * @modified_date: 2016年1月27日下午2:04:34
	 * @modified_user: sunkouping
	 * @review_user:
	*/
    public function getNeedStatAction(){
        $this->mall_id = 8;
        return ['actionIndex'];
    }

	/**
	 * @description:眼镜商城首页
	 * @return: 
	 * @author: sunkouping
	 * @date: 2016年4月27日下午3:43:57
	 * @modified_date: 2016年4月27日下午3:43:57
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionIndex(){
		//眼镜商城不开通，将其链接转向用户的标准商城
		$user_id = \Yii::$app->user->id;
		$mall_id = StoreMall::getMainMall($user_id);
		$url = MallBase::getMallUrl($mall_id);
		return $this->redirect($url);

		header("Content-Type: text/html; charset=UTF-8");
		$this->layout = '_blank';
		$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		
		$mall_id = 8;
		$life_time = 1;
		
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
		//左侧导航数据
		$menu = f_c('glasses_menu_'.$city_id);
		if($menu === false) {
			$menu = [];	
			$menu = MallRnav::getRightNavData(8,$user_info['city'], 0);
			f_c('glasses_menu_'.$city_id,$menu,$life_time);
		}
		
		//广告
		$ad = f_c('glasses_ad_'.$city_id);
		if($ad === false) {
			$ad = [];
			$ad['banner'] = AdInfo::getAd(123,$city_id);				//大banner图
			$ad['banner_next'] = AdInfo::getAd(124,$city_id);		//banner下方 3个轮播
			$temp_ad = [];
			for ($i=0;$i<ceil(count($ad['banner_next'])/3);$i++) {
				$temp_ad[] = array_slice($ad['banner_next'],$i*3,3);
			}
			$ad['banner_next'] = $temp_ad;
			$ad['finance'] = AdInfo::getAd(126,$city_id,4);			//金融4个
			$ad['today_bao'] = AdInfo::getAd(125,$city_id,1);			//今日爆品
			
			$ad['ad_top'] = AdInfo::getAd(121,$city_id,1);			//顶部广告位
			$ad['ad_middle'] = AdInfo::getAd(122,$city_id,1);		//中部广告位
			$ad['brand_eight'] = AdInfo::getAd(127,$city_id,8);		//八个品牌广告位
			f_c('glasses_ad_'.$city_id,$ad,$life_time);
		}
		//楼层信息
		$floor = f_c('glasses_floor_'.$city_id);
		if(!$floor){
			$floor = FloorInfo::getFloorExitOne($city_id,8);
			f_c('glasses_floor_'.$city_id,$floor,$life_time);
		}
		//一周精选
		$one_week = f_c('glasses_one_week_'.$city_id);
		if(!$one_week){
			$one_week = OtherHandpick::getWeekInfo($city_id,8,5);
			foreach($one_week as $k=>$v){
				if($v['goods_type'] == 1) {//基础商品
					$base_id = $v['goods_id'];
					$cover = BaseGoods::find()->select('cover_id')->where(['id'=>$base_id])->scalar();
					$photo = \common\models\goods\Photo::find()->where(['id'=>$cover])->asArray()->one();
					if($photo){
						$one_week[$k]['img_url'] = $photo['img_url'];
					}else{
						$one_week[$k]['img_url'] = 'undefined.jpg';
					}
					$one_week[$k]['link_url'] = "/supplier-goods/index?id=".$base_id;
				} else { //供应商商品
					$goods_id = $v['goods_id'];
					$cover = SupplierGoods::find()->select('cover_id')->where(['id'=>$goods_id])->scalar();
					$photo = \common\models\goods\Photo::find()->where(['id'=>$cover])->asArray()->one();
					if($photo){
						$one_week[$k]['img_url'] = $photo['img_url'];
					}else{
						$one_week[$k]['img_url'] = 'undefined.jpg';
					}
					$one_week[$k]['link_url'] = "/site/detail?id=".$goods_id;
				}
			}
			f_c('glasses_one_week_'.$city_id,$one_week,$life_time);
		}
		//搜索框
		$seach = f_c('glasses_search_'.$user_id);
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
			$seach['mall_id'] = $mall_id;
			//热门关键字
			$hot_keywords = HotSearch::getHotSearch(1);
			$seach['hot_keywords'] = $hot_keywords;
			f_c('tongxun_search_'.$user_id,$seach,60);
		}
		//横向导航栏
		$menu_heng = f_c('glasses_menuheng_'.$city_id);
		if($menu_heng === false) {
			$menu_heng = OtherMainMenu::getMainMenu(8,$city_id);
			f_c('glasses_menuheng_'.$city_id,$menu_heng,$life_time);
		}
		
		//公告信息
		$notice = f_c('glasses_notice'.$city_id);
		if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
			$notice = [];
			$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city_id,$mall_id)['list'];//促销公告
			f_c('glasses_notice'.$city_id,$notice,$life_time);
		}
		//用户信息 签到
		$memder_data = f_c('glasses_user_ext_'.$user_id);
		if(!$memder_data) {
			$memder_data = UserMember::getHomeInfo($user_id);
			f_c('glasses_user_ext_'.$user_id,$memder_data,$life_time);
		}
		//该用户可看商城
    	$malls = StoreMall::getMalls($user_id);
    	$yes_malls = ArrayHelper::getColumn($malls['yes_malls'], 'id');
    	if(!in_array(8, $yes_malls)) {
    		//f_msg('您无法查看此商城', '/home/index');
    	}
    	
    	//收益
    	$profitInfo = UserMember::getProfitByApi();
		return $this->render('index',[
			    'ad'=>$ad,
				'mall_id'=>8,
				'memder_data'=>$memder_data,
				'menu'=>$menu,
				'search'=>$seach,
				'menu_heng'=>$menu_heng,
				'user_info'=>$user_info,
				'notice'=>$notice,
				'malls'=>$malls,
				'profitInfo'=>$profitInfo,
				'floor'=>$floor,
				'one_week'=>$one_week,
		]);
	}
	
	public function actionOpticianCorner(){
		header("Content-Type: text/html; charset=UTF-8");
		$this->layout = '_blank';
		$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		
		$mall_id = 8;
		$life_time = 60;
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
		//轮播图数据
		$ad = f_c('optician_corner_'.$city_id);
		if($ad === false) {
			$ad = [];
			$ad['banner']= AdInfo::getAd(152,$city_id);				//大banner图
			$ad['step']= AdInfo::getAd(153,$city_id,3);				//三步骤
			f_c('optician_corner_'.$city_id,$ad,$life_time);
		}
		//楼层信息
		$floor = f_c('optician_floor_'.$city_id);
		if(!$floor){
			$floor = FloorInfo::getFloorExitOne($city_id,9001);
			f_c('optician_floor_'.$city_id,$floor,$life_time);
		}
		//f_d($ad);
		return $this->render('optician',[
			    'ad'=>$ad,
				'floor'=>$floor
		]);
		
	}
}
