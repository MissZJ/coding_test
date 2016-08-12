<?php
namespace frontend\controllers;

use common\models\other\AdInfo;
use common\models\other\MallRnav;
use common\models\other\OtherMainMenu;
use common\models\other\OtherHandpick;
use common\models\user\UserMember;
use frontend\components\Controller2016;
use \common\models\baby\BabyFloorBase;
use common\models\user\SupplierCityScreen;
use common\models\goods\SupplierGoods;
use common\models\user\Supplier;
use common\models\other\OtherHelpCenterArticle;
use common\models\user\StoreMall;
use yii\helpers\ArrayHelper;
use common\models\order\OmsNopay;
use common\models\order\OmsInfo;
use common\models\floor\TempMallProvince;
use yii\data\Pagination;
use common\models\goods\BaseGoods;
use common\models\floor\FloorInfo;
use common\models\gw\GwPushInterface;
class BabyController extends Controller2016{

    public function init() {
        $this->layout = false;
        return parent::init();
    }

    public function getNeedStatAction(){
        $this->mall_id = 3;
        return ['actionIndex'];
    }
    /**
     * @description:母婴商城首页
     * @return: []
     * @author: wangjiarui
     * @date: 2016年5月25日下午2:17:34
     * @modified_date: 2016年5月25日下午2:17:34
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionIndex(){
    	$this->view->title = '母婴商城-51订货网';
    	//孙扣平代码，勿删结束
    	$ko = 'oneHourNoPay';
    	$out_ko = f_c($ko);
    	if($out_ko === false) {
    		OmsNopay::oneHourNoPay();//自动取消订单
    		f_c($ko,'s_b',600);
    	}
    	$life_time = 600;
    	//孙扣平代码，勿删开始
    	//用户状态校验
    	$user_id = \Yii::$app->user->id;
    	$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
    
    	//用户信息
    	if(!$user_info) {
    		$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
    	}
    
    	if($user_info['user_status'] != 1&&$user_info['user_status'] != 4){
    		$view = \Yii::$app->view->render('@common/widgets/alert/Alert.php',[
    				'type'=>'warn',
    				'message'=>'帐号状态异常,即将退出登录',
    				'is_next'=>'yes',
    				'next_url'=>'/site/logout',
    		]);
    		return $view;exit;
    	}
    	$city = $this->user_info['city'];
    	//导航数据
    	$leftNavCacheKey = "baby_new_left_nav_3_".$city;
    	$leftNavCacheData = f_c($leftNavCacheKey);
    	if(!$leftNavCacheData){
    		$leftNavCacheData = MallRnav::getRightNavData(3,$city, 0);
    		f_c($leftNavCacheKey,$leftNavCacheData,$life_time);
    	}
    	//f_d($leftNavCacheData);
    	//广告
    	$adCacheKey = "baby_new_index_".$city;
    	$adCacheData = f_c($adCacheKey);
    	if(!$adCacheData) {
    		$ad = [];
    		$ad['lbt'] = AdInfo::getAd(44,$city,8); 				//首屏轮播大图
    		$ad['lbt_next'] = AdInfo::getAd(145,$city);		//大图下方三联播
    		if($ad['lbt_next']) {
    			$ad['lbt_next'] = array_chunk($ad['lbt_next'], 3);
    			$tempcount = count($ad['lbt_next']);
    			if(count($ad['lbt_next'][$tempcount - 1]) !== 3) unset($ad['lbt_next'][$tempcount - 1]);
    		}
    		$ad['xtb'] = AdInfo::getAdExt(45,$city,4);				//首屏右侧小图标
    		$ad['bkrm_4'] = AdInfo::getAdExt(46,$city,4);		//爆款热卖头部4图广告
    		$ad['bkrm_2'] = AdInfo::getAdExt(47,$city,2);			//爆款热卖头部2图广告
    		$ad['grzx'] = AdInfo::getAdExt(48,$city,1); 				//首屏个人中心下方图
    		$ad['ad_top'] = AdInfo::getAd(98,$city,1);			//顶部广告位
    		$ad['ad_middle'] = AdInfo::getAd(108,$city,1);		//中部广告位
    		$ad['temp6'] = AdInfo::getAd(87,$city,6);
//     		f_c($adCacheKey,$ad,BabyFloorBase::CACHE_DATA_TIME);
    		f_c($adCacheKey,$ad,$life_time);
    		$adCacheData = $ad;
    	}
    	//f_d($adCacheData);
    	//一周精选
    	$one_week = f_c('baby_new_one_week_'.$city);
    	$arr = array();
    	if(!$one_week){
    		$one_week = OtherHandpick::getWeekInfoB($city,3);
    		f_c('baby_new_one_week_'.$city,$one_week,$life_time);
    	}
    	//f_d($one_week);
    	//用户信息
    	$user_id = $this->user_info['id'];
    	$memder_data = f_c('home_user_ext_'.$user_id);
    	if(!$memder_data) {
    		$memder_data = UserMember::getHomeInfo($user_id);
    		f_c('home_user_ext_'.$user_id,$memder_data,$life_time);
    	}
    	//横向导航栏
    	$henCacheKey = "baby_new_hen_3_".$city;
    	$henCacheData = f_c($henCacheKey);
    	if(!$henCacheData){
    		$henCacheData = OtherMainMenu::getMainMenu(3, $city);
    		f_c($henCacheKey,$henCacheData,$life_time);
    	}
    	//公告信息
    	$notice = f_c('baby_new_notice'.$city);
    	if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1 ) {
    		$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(2,1,$city,3)['list']; //新手
    		$notice['novice'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city,3)['list'];//促销公告
    		f_c('baby_new_notice'.$city,$notice,$life_time);
    	}
    	//收益
    	$profitInfo = UserMember::getProfitByApi();
    	//用户所在省份
    	$if_temp = TempMallProvince::Iftemp($this->user_info['province'],3);
    	if($if_temp){
    		$query = TempMallProvince::tempGoods(false,$this->user_info['city'], 3);
    		$this->layout = '_blank';
    		$pages = new Pagination(['totalCount' =>$query->count(), 'pageSize' => '40']);
    		$model = $query->offset($pages->offset)->limit($pages->limit)->all();
    
    		return $this->render('temp_baby',[
    				'leftNavMenu'=>$leftNavCacheData,
    				'adInfo'=>$adCacheData,
    				'memder_data'=>$memder_data,
    				'user_info'=>$this->user_info,
    				'menu_heng'=>$henCacheData,
    				'notice'=>$notice,
    				'data'=>$model,
    				'pages'=>$pages,
    				'profitInfo'=>$profitInfo
    		]);
    	}
    	//楼层数据
    	//$floorData = BabyFloorBase::getFrontFloorData($city);
    	//楼层信息
		$floorData = f_c('baby_new_floor_'.$city);
		if(!$floorData){
			$floorData = FloorInfo::getFloorExitOne($city,3);
			if(!$floorData) {
				$floorData= [];
			}
			f_c('baby_new_floor_'.$city,$floorData,$life_time);
		}
    
    	$this->layout = '_blank';
    	$malls = StoreMall::getMalls($user_id);
    	$yes_malls = ArrayHelper::getColumn($malls['yes_malls'], 'id');
    	if(!in_array(3, $yes_malls) && !in_array($user_info['city'], [391])) {
    		f_msg('您无法查看此商城', '/home/index');
    	}
    	//f_d($floorData);
    	return $this->render('index1',[
    			'leftNavMenu'=>$leftNavCacheData,
    			'floorData'=>$floorData,
    			'adInfo'=>$adCacheData,
    			'memder_data'=>$memder_data,
    			'user_info'=>$this->user_info,
    			'menu_heng'=>$henCacheData,
    			'notice'=>$notice,
    			'malls'=>$malls,
    			'profitInfo'=>$profitInfo,
    			'one_week'=>$one_week
    	]);
    }
    
}
