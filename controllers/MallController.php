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
use common\models\seas\SeasUserSupplier;
use common\models\markting\CouponSupplier;
use common\models\user\Supplier;
use common\models\markting\MarktingCouponBySupplier;

class MallController extends Controller2016{
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
        $this->mall_id = 9;
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
	public function actionPrint(){
		header("Content-Type: text/html; charset=UTF-8");
		$this->layout = '_blank';
		$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		
		$mall_id = 9;
		$life_time = 2;
		
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
		$menu = f_c('print_menu_'.$city_id);
		if($menu === false) {
			$menu = [];	
			$menu = MallRnav::getRightNavData($mall_id,$user_info['city'], 0);
			f_c('print_menu_'.$city_id,$menu,$life_time);
		}
		
		//广告
		$ad = f_c('print_ad_'.$city_id);
		if($ad === false) {
			$ad = [];
			$ad['banner'] = AdInfo::getAd(136,$city_id);				//大banner图
			$ad['banner_next'] = AdInfo::getAd(137,$city_id);		//banner下方 3个轮播
			$temp_ad = [];
			for ($i=0;$i<ceil(count($ad['banner_next'])/3);$i++) {
				$temp_ad[] = array_slice($ad['banner_next'],$i*3,3);
			}
			$ad['banner_next'] = $temp_ad;
			$ad['finance'] = AdInfo::getAd(139,$city_id,4);			//金融4个
			$ad['today_bao'] = AdInfo::getAd(138,$city_id,1);			//今日爆品
			
			$ad['ad_top'] = AdInfo::getAd(140,$city_id,1);			//顶部广告位
			//$ad['ad_middle'] = AdInfo::getAd(122,$city_id,1);		//中部广告位
			f_c('print_ad_'.$city_id,$ad,$life_time);
		}
		//楼层信息
		$floor = f_c('print_floor_'.$city_id);
		if(!$floor){
			$floor = FloorInfo::getFloorExitOne($city_id,$mall_id);
			f_c('print_floor_'.$city_id,$floor,$life_time);
		}
		//f_d($floor);
		//搜索框
		$seach = f_c('print_search_'.$user_id);
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
		$menu_heng = f_c('print_menuheng_'.$city_id);
		if($menu_heng === false) {
			$menu_heng = OtherMainMenu::getMainMenu($mall_id,$city_id);
			f_c('print_menuheng_'.$city_id,$menu_heng,$life_time);
		}
		
		//公告信息
		$notice = f_c('print_notice'.$city_id);
		if(!$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
			$notice = [];
			$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city_id,$mall_id)['list'];//促销公告
			f_c('print_notice'.$city_id,$notice,$life_time);
		}
		//用户信息 签到
		$memder_data = f_c('print_user_ext_'.$user_id);
		if(!$memder_data) {
			$memder_data = UserMember::getHomeInfo($user_id);
			f_c('print_user_ext_'.$user_id,$memder_data,$life_time);
		}
		//该用户可看商城
    	$malls = StoreMall::getMalls($user_id);
    	$yes_malls = ArrayHelper::getColumn($malls['yes_malls'], 'id');
    	if(!in_array($mall_id, $yes_malls)) {
    		//f_msg('您无法查看此商城', '/home/index');
    	}
    	//f_d($menu);
    	//收益
    	$profitInfo = UserMember::getProfitByApi();
		return $this->render('print',[
			    'ad'=>$ad,
				'mall_id'=>$mall_id,
				'memder_data'=>$memder_data,
				'menu'=>$menu,
				'search'=>$seach,
				'menu_heng'=>$menu_heng,
				'user_info'=>$user_info,
				'notice'=>$notice,
				'malls'=>$malls,
				'profitInfo'=>$profitInfo,
				'floor'=>$floor,
		]);
	}
	/**
	 * @description:红包专区首页
	 * @return:
	 * @author: wangjiarui
	 * @date: 2016年6月15日上午9:17:57
	 * @modified_date:
	 * @modified_user:
	 * @review_user:
	 */
	public function actionCoupon(){
		$this->layout='_blank';
		$mall_id = f_get('mall_id');
		$search = f_get('search','');
		$sort = f_get('sort','');
		$page = f_get('page',1);
		$page_size = 12;
		$start = ($page-1)*$page_size;
		$coupons_info = array();
		$price_range = f_get('price_range','');
		if($price_range){
			$price_range = explode('-',$price_range);
		}
		//$suppliers = SeasUserSupplier::find()->where(['mall_id'=>$mall_id])->asArray()->all();
		if($mall_id){
			$coupons_info = CouponSupplier::getCouponInfoByMall($mall_id,$search,$start,$page_size,$price_range,$sort);
		}
		$total = CouponSupplier::countCouponInfoByMall($mall_id,$search,$price_range);
		$total_page = ceil($total/$page_size);
		$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		
		if(!$user_info) {
			$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		}
		//判断是否是用户  组
		$city_id = $user_info['city'];
		//广告
		$ad = f_c('tongxun_coupon_ad_'.$city_id);
		if(!$ad) {
			$ad = [];
			$ad['banner'] = AdInfo::getAd(132,$city_id);				//大banner图
			$ad['hot'] = AdInfo::getAd(133,$city_id,4);		
			f_c('tongxun_coupon_ad_'.$city_id,$ad,0);
		}
		//f_d($coupons_info);
		$price_range = f_get('price_range','');
		return $this->render('coupon',[
				'coupons_info' => $coupons_info,
				'ad' =>$ad,
				'mall_id' => $mall_id,
				'total_page' => $total_page,
				'page' => $page,
				'search' => $search,
				'sort' => $sort,
				'price_range'=>$price_range
		]);
	}
	
	/**
	 * @description:领取红包
	 * @return:
	 * @author: wangjiarui
	 * @date: 2016年6月21日下午15:12:05
	 * @modified_date:
	 * @modified_user:
	 * @review_user:
	 */
	public function actionGetCoupon(){
		$coupon_id = f_get('coupon_id');
		$user_id = \Yii::$app->user->id;
		$user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
		if(!$user_info) {
			$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		}
		$coupon_user = '';
		$coupon_user = CouponUser::find()->where(['coupon_id'=>$coupon_id,'user_id'=>$user_id])->asArray()->one();
		if(!$coupon_user){
			$coupon_info = MarktingCouponBySupplier::find()->where(['coupon_info_id'=>$coupon_id])->asArray()->one();
			if($coupon_info['coupon_num']>0){
				$model = new CouponUser();
				$model->coupon_id = $coupon_info['coupon_info_id'];
				$model->user_id = $user_id;
				$model->admin_id = Supplier::getSupplierNameById($coupon_info['supplier_id']);
				$model->start_time = $coupon_info['begin_time'];
				$model->end_time = $coupon_info['end_time'];
				$model->add_time = f_date(time());
				$model->status = 1;
				$model->remark = $coupon_info['coupon_remark'];
				$model->add_ip = f_ip();
				if($model->save()){
					$coupon_num = $coupon_info['coupon_num']-1;
					$received_num = $coupon_info['received_num']+1;
					MarktingCouponBySupplier::updateAll(['coupon_num'=>$coupon_num,'received_num'=>$received_num],['id'=>$coupon_info['id']]);
					echo "1";//领取成功
				}
			}else{
				echo "2";//红包已领完
			}
			
		}else{
			echo "3";//已领取不能重复领取
		}
		
	}
}
