<?php

namespace frontend\controllers;
use frontend\components\Controller2016;
use common\models\other\MallRnav;
use common\models\other\AdInfo;
use common\models\other\OtherMainMenu;
use common\models\other\OtherHelpCenterArticle;
use common\models\floor\FloorAd;
use common\models\floor\FloorGoods;
use common\models\user\UserMember;
use common\models\floor\FloorInfo;
use common\models\other\OtherHandpick;
use common\models\goods\SupplierGoods;
use common\models\goods\BaseGoods;
use common\models\other\GroundBagCity;
use common\models\markting\LadderGroup;
use yii\db\Query;
use common\models\order\OmsGoods;
use common\models\gw\GwPushInterface;
use common\models\skp\Skp;
use common\models\order\OmsInfoExt;
use common\models\Goods\GoodsBaseGoodsCity;


/**
 * Description of RumController
 *
 * @author lxzmy
 */
class RumController extends Controller2016 {
    public function getNeedStatAction(){
        $this->mall_id = 5;
        return ['actionIndex'];
    }

    public function actionIndex(){
        return $this->redirect('/appliance'); //合并到IT家电生活馆
        
        $this->view->title = '51订货网-51超市';
        header("Content-Type: text/html; charset=UTF-8");
        $user_id = \Yii::$app->user->id;
        $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
                //用户信息
		if(!$user_info) {
			$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		}
        $city_id = $user_info['city'];
        //用户状态校验
        if($user_info['user_status'] != 1){
        	$view = \Yii::$app->view->render('@common/widgets/alert/Alert.php',[
        			'type'=>'warn',
        			'message'=>'帐号状态异常,即将退出登录',
        			'is_next'=>'yes',
        			'next_url'=>'/site/logout',
        	]);
        	return $view;exit;
        }
        //判断是否开通地包
        
        if(GroundBagCity::find()->where(['city_id'=>$city_id,'mall_id'=>5])->asArray()->one()) {
        	return $this->render('dbcity');
        }
        //关超市 非江苏 安徽 的用户跳转到文体商城
        if(!in_array($user_info['province'] ,[3,16])) {
        	return $this->render('guan_men');
        }
        
        
        
		$time = 3600;
                //左侧导航数据  mall_id = 5
		$menu = f_c('rum_menu_'.$city_id);
		if($menu === false) {
			$menu = [];	
			$menu = MallRnav::getRightNavData(5,$user_info['city'], 0);
			f_c('rum_menu_'.$city_id,$menu,$time);
		}
                //广告
		$ad = f_c('rum_ad_'.$city_id);
		if($ad === false) {
			$ad = [];
			
			$ad['banner'] = AdInfo::getAd(100,$city_id);				//大banner图
			$ad['banner_xia'] = AdInfo::getAd(115,$city_id);   //大banner下面图
			if($ad['banner_xia']) {
			    $ad['banner_xia'] = array_chunk($ad['banner_xia'], 3);
			    $tempcount = count($ad['banner_xia']);
			    if(count($ad['banner_xia'][$tempcount - 1]) !== 3) unset($ad['banner_xia'][$tempcount - 1]);
			}
			
			$ad['banner_next'] = AdInfo::getAd(81,$city_id,3);		//banner下方
			$ad['finance'] = AdInfo::getAd(104,$city_id,4);			//金融4个
			$ad['today_bao'] = AdInfo::getAd(103,$city_id,1);			//今日爆品
			$ad['ad_top'] = AdInfo::getAd(99,$city_id,1);			//顶部广告位
                        $ad['ad_pin'] = AdInfo::getAd(106,$city_id,8);			//一周精选下的8个品牌广告位
			f_c('rum_ad_'.$city_id,$ad,$time);
		}
		
                //横向导航栏
		$menu_heng = f_c('rum_menuheng_'.$city_id);
		if($menu_heng === false) {
			$menu_heng = OtherMainMenu::getMainMenu(5,$city_id);
			f_c('rum_menuheng_'.$city_id,$menu_heng,$time);
		}
		//公告信息
		$notice = f_c('rum_notice'.$city_id);
		if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
			$notice = [];
			$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city_id,5)['list'];//促销公告
			f_c('rum_notice'.$city_id,$notice,$time);
		}
                //用户信息 签到
		$memder_data = f_c('home_user_ext_'.$user_id);
		if(!$memder_data) {
			$memder_data = UserMember::getHomeInfo($user_id);
			f_c('home_user_ext_'.$user_id,$memder_data,$time);
		}
                //f_d($memder_data);
                //51超市一周精选
                $one_week = f_c('rum_one_week_'.$city_id);
                if(!$one_week){
                    $one_week = OtherHandpick::getWeekInfo($city_id, 5,5);
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
                    f_c('rum_one_week_'.$city_id,$one_week,1200);
                }
                
                
                //楼层遍历 
                $floor = f_c('rum_floor_'.$city_id);
                if(!$floor){
                    $floor = FloorInfo::getFloorExitOne($city_id,5);
                    f_c('rum_floor_'.$city_id,$floor,$time);
                }
                
               return $this->render('index',[
                    'menu'=>$menu,
                    'ad'=>$ad,
                    'user_info'=>$user_info,
                    'menu_heng'=>$menu_heng,
                    'memder_data'=>$memder_data,
                    //'ad_pin' => $ad_pin,
                    'mall_id'=>5,
               		'notice'=>$notice,
                    'floor'=>$floor,
                    'one_week'=>$one_week,
                ]);
    }
    public function actionOneHourNoPayOrder(){
        $res = \common\models\order\OmsNopay::oneHourNoPay();
         if($res){
             f_msg('修改成功', 'http://www.51dh.com.cn/');
         }else{
             f_msg('修改失败', 'http://www.51dh.com.cn/');
         }
    }
    /**
     * @description:处理订单行
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年7月1日上午10:01:05
     * @modified_date: 2016年7月1日上午10:01:05
     * @modified_user: sunkouping
     * @review_user:
     */
    public function actionGood(){
    	$sql = "SELECT
			t1.old_price AS yuanjia,
			t1.goods_price as c,
			t1.ms_discount_price as d,
			t1.gys_discount_price as g,
			t2.order_code,
			t2.order_time,
			t2.come_from,
			t1.id
			FROM
				`order_oms_goods` AS t1
			LEFT JOIN order_oms_info as t2 ON t1.order_id = t2.id
			WHERE t2.order_time >= '2016-07-25 00:00:00'
			HAVING
				yuanjia != (
					c + d + g
				);"
    			;
    		$data = OmsInfoExt::findBySql($sql)->asArray()->all();
    			foreach ($data as $value) {
    				$model = OmsGoods::findOne($value['id']);
    				if($model) {
    					//if(strpos($value['come_from'],'台下单')) {
    					$model->old_price = $model->goods_price + $model->ms_discount_price;
    					if(!$model->save()){
    						f_d($model->errors);
    					}
    					//}
    				}
    			}
    }
}










