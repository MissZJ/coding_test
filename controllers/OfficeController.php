<?php

namespace frontend\controllers;
use frontend\components\Controller2016;
use common\models\other\MallRnav;
use common\models\other\AdInfo;
use common\models\other\OtherMainMenu;
use common\models\other\OtherHelpCenterArticle;
use common\models\user\UserMember;
use common\models\floor\FloorInfo;
use common\models\other\OtherHandpick;
use common\models\goods\SupplierGoods;
use common\models\goods\BaseGoods;


/**
 * Description of RumController
 *
 * @author lxzmy
 */
class OfficeController extends Controller2016 {
    
    public function actionIndex1(){
        //旧版
        $this->view->title = '51订货网-文体办公';
        header("Content-Type: text/html; charset=UTF-8");
        $user_id = \Yii::$app->user->id;
        $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
    
        //用户信息
        if(!$user_info) {
            $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
        }
    
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
        $time = 3600;
    
        //左侧导航数据 文体商城： mall_id = 7
        $menu = f_c('office_menu_'.$city_id);
        if($menu === false) {
            $menu = [];
            $menu = MallRnav::getRightNavData(7,$user_info['city'], 0);
            f_c('office_menu_'.$city_id,$menu,$time);
        }
    
        //广告
        $ad = f_c('office_ad_'.$city_id);
        if($ad === false) {
            $ad = [];
            	
            $ad['banner'] = AdInfo::getAd(116,$city_id);				//大banner图
            $ad['banner_next'] = AdInfo::getAd(149,$city_id);		//banner下方
            if($ad['banner_next']) {
                $ad['banner_next'] = array_chunk($ad['banner_next'], 3);
                $tempcount = count($ad['banner_next']);
                if(count($ad['banner_next'][$tempcount - 1]) !== 3) unset($ad['banner_next'][$tempcount - 1]);
            }
            $ad['finance'] = AdInfo::getAd(117,$city_id,4);			//金融4个
            $ad['today_bao'] = AdInfo::getAd(118,$city_id,1);		//今日爆品
            $ad['ad_top'] = AdInfo::getAd(119,$city_id,1);			//顶部广告位
            $ad['ad_pin'] = AdInfo::getAd(120,$city_id,8);			//一周精选下的8个品牌广告位
            f_c('office_ad_'.$city_id,$ad,$time);
        }
    
        //横向导航栏
        $menu_heng = f_c('office_menuheng_'.$city_id);
        if($menu_heng === false) {
            $menu_heng = OtherMainMenu::getMainMenu(7,$city_id,8);
            f_c('office_menuheng_'.$city_id,$menu_heng,$time);
        }
    
        //公告信息
        $notice = f_c('office_notice'.$city_id);
        if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
            $notice = [];
            $notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city_id,7)['list'];//促销公告
            f_c('office_notice'.$city_id,$notice,$time);
        }
    
        //用户信息 签到
        $memder_data = f_c('home_user_ext_'.$user_id);
        if(!$memder_data) {
            $memder_data = UserMember::getHomeInfo($user_id);
            f_c('home_user_ext_'.$user_id,$memder_data,$time);
        }
    
        //一周精选
        $one_week = f_c('office_one_week_'.$city_id);
        if(!$one_week){
            $one_week = OtherHandpick::getWeekInfo($city_id, 7,10);
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
            f_c('office_one_week_'.$city_id,$one_week,1200);
        }
    
        //楼层遍历
        $floor = f_c('office_floor_'.$city_id);
        if(!$floor){
            $floor = FloorInfo::getFloorExitOne($city_id,7);
            f_c('office_floor_'.$city_id,$floor,$time);
        }
        //f_d($ad['banner_next']);
        return $this->render('index',[
            'menu'=>$menu,
            'ad'=>$ad,
            'user_info'=>$user_info,
            'menu_heng'=>$menu_heng,
            'memder_data'=>$memder_data,
            //'ad_pin' => $ad_pin,
            'notice'=>$notice,
            'floor'=>$floor,
            'one_week'=>$one_week,
        ]);
    }
    
    public function actionIndex(){
        //新版，文体办公，2016年8月5日
        $this->view->title = '51订货网-文体办公';
        header("Content-Type: text/html; charset=UTF-8");
        $user_id = \Yii::$app->user->id;
        $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
        
        //用户信息
		if(!$user_info) $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		
        $city_id = $user_info['city'];
        
        //用户状态校验
        if($user_info['user_status'] != 1 && $user_info['user_status'] != 4){
        	$view = \Yii::$app->view->render('@common/widgets/alert/Alert.php',[
        			'type'=>'warn',
        			'message'=>'帐号状态异常,即将退出登录',
        			'is_next'=>'yes',
        			'next_url'=>'/site/logout',
        	]);
        	return $view;exit;
        }
		$time = 3600;
        
        //左侧导航数据 文体商城： mall_id = 7
		$menu = f_c('office_menu_'.$city_id);
		if($menu === false) {
			$menu = [];	
			$menu = MallRnav::getRightNavData(7,$user_info['city'], 0);
			f_c('office_menu_'.$city_id, $menu, $time);
		}
		
        //广告
		$ad = f_c('office_ad_'.$city_id);
		if($ad === false) {
			$ad = [];
			
			$ad['banner'] = AdInfo::getAd(116,$city_id);			//大banner图
			$ad['banner_next'] = AdInfo::getAd(149,$city_id);		//banner下方
			if($ad['banner_next']) {
				$ad['banner_next'] = array_chunk($ad['banner_next'], 3);
				$tempcount = count($ad['banner_next']);
				if(count($ad['banner_next'][$tempcount - 1]) !== 3) unset($ad['banner_next'][$tempcount - 1]);
			}
			$ad['finance'] = AdInfo::getAd(117,$city_id,4);			//金融4个
			$ad['today_bao'] = AdInfo::getAd(118,$city_id,1);		//今日爆品
			$ad['ad_top'] = AdInfo::getAd(119,$city_id,1);			//顶部广告位
			$ad['ad_pin'] = AdInfo::getAd(120,$city_id,8);			//一周精选下的8个品牌广告位
			f_c('office_ad_'.$city_id,$ad,$time);
		}
		
        //横向导航栏
		$menu_heng = f_c('office_menuheng_'.$city_id);
		if($menu_heng === false) {
			$menu_heng = OtherMainMenu::getMainMenu(7,$city_id,8);
			f_c('office_menuheng_'.$city_id,$menu_heng,$time);
		}
		
		//公告信息
		$notice = f_c('office_notice'.$city_id);
		if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
			$notice = [];
			$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city_id,7)['list'];//促销公告
			f_c('office_notice'.$city_id,$notice,$time);
		}
		
        //用户信息 签到
		$memder_data = f_c('home_user_ext_'.$user_id);
		if(!$memder_data) {
			$memder_data = UserMember::getHomeInfo($user_id);
			f_c('home_user_ext_'.$user_id,$memder_data,$time);
		}
		
		//一周精选
		$one_week = f_c('office_one_week_'.$city_id);
		$one_week = [];
		if(!$one_week){
		    $one_week = OtherHandpick::getWeekInfoB($city_id, 7, null, 1);
		    f_c('office_one_week_'.$city_id,$one_week,$time);
		}
        
        //楼层遍历 
        $floorData = f_c('office_floor_'.$city_id);
        if(!$floorData){
            $floorData = FloorInfo::getFloorExitOne($city_id,7);
            if(!$floorData) {
                $floorData= [];
            }
            f_c('office_floor_'.$city_id, $floorData, $time);
        }
        //f_d($floorData);
        return $this->render('index1',[
            'menu' => $menu,
            'ad' => $ad,
            'user_info' => $user_info,
            'menu_heng' => $menu_heng,
            'memder_data' => $memder_data,
            'notice'=>$notice,
            'floorData' => $floorData,
            'one_week' => $one_week,
        ]);
    }
}
