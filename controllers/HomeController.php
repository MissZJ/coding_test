<?php
namespace frontend\controllers;

use common\models\other\OtherAdClickStat;
use common\models\stat\StatClickDetail;
use common\models\stat\StatPvUvDetail;
use Yii;
use yii\helpers\Json;
use common\models\order\OmsGoods;
use common\models\other\AdInfo;
use common\models\other\HomeFloor;
use common\models\other\FavoriteGoods;
use common\models\other\OtherHelpCenterArticle;
use frontend\components\Controller2016;
use common\models\other\OtherActivityArea;
use common\models\user\Sign;
use common\models\user\UserMember;
use common\models\other\OtherMainMenu;
use common\models\other\MallRnav;
use common\models\user\UserPushTime;
use common\models\user\UserStoreType;
use common\models\user\UserApplyMall;
use common\models\user\StoreMall;
use common\models\other\MallBase;

use common\models\other\OtherExam;
use common\models\other\OtherExamOption;
use common\models\other\OtherExamUser;
use common\models\other\OtherExamRecord;
use common\models\markting\CouponUser;
use common\models\markting\CouponInfo;
use common\models\mall\LiveTv;
use common\models\other\Log;
use common\models\other\OtherRegion;
use common\models\data\CartTongji;
class HomeController extends Controller2016{


    public function init() {
        $this->layout = false;
        return parent::init();
    }

    /**
     * @description:网站首页
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年12月26日下午2:50:41
     */
    public function actionIndex() {
        $cacheLiftTime = Yii::$app->params['home.life.time'];     
        $user_id = \Yii::$app->user->id;
        $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
        if(!$user_info) {
         $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
        }
        //系统升级(用户店铺类型)---
        if($user_info['store_type'] == 0 || $user_info['store_type'] == 10){
            //门店性质
            $store_propertys = UserMember::storePropertys();
            //店铺类型
            $store_types = UserStoreType::getStoreInfo();
            return $this->render('shengji',[
                'store_type'=>$store_types,
                'store_property'=>$store_propertys,
                'user_id'=>$user_id,
            ]);
        }
        //发送用户到各自的主营商城
        $main_mall_id = StoreMall::find()->where(['status'=>1,'store_id'=>$user_info['store_type']])->asArray()->one();
        if(in_array($user_info['city'],[387,391])){
        	return $this->redirect('/baby/index');
        }
        if($main_mall_id) {
			$mall_info = MallBase::find()->where(['id'=>$main_mall_id['mall_id']])->asArray()->one();    
                        //用户登录后系统检测密码，若密码为123456则提示更改
                        $user_info = UserMember::find()->where(['id'=>$user_info['id']])->asArray()->one();
                        if(md5('123456') == $user_info['password']){
                            $view = Yii::$app->view->render('@common/widgets/alert/UpdatePwd.php',[
                                                        'type'=>'warn',
                                                        'message'=>'密碼太簡單，修改密碼',
                                                        'mall_id'=>$mall_info['url'],  //取消之后依然跳转主商城
                                                        'is_next'=>'yes',
                                                        'next_url'=>'/ucenter/account?action=password',  //確定跳轉修改密碼頁面
                                        ]);
                                        return $view;exit;
                        }
        	return $this->redirect($mall_info['url']);
        } else {
        	f_msg('未找到您的主营业务','/ucenter/index');
        }
        
        
        $user_group = $user_info['user_group'];
        $city_id = $user_info['city'];
        //广告
        $ad = f_c('cmcc_home_ad_'.$city_id);
        //商品分类
        $key_category = "home_nav_category_list_{$this->user_info['city']}_{$this->user_info['user_group']}";
        $category_list =  f_c($key_category);
        if(YII_DEBUG || !$category_list) {
            $category_list = MallRnav::getRightNavData(0,$this->user_info['city'],$this->user_info['user_group']);
            if(!$category_list) {
                $category_list = [];
                //to-do 请维护
            }
            f_c($key_category,$category_list,$cacheLiftTime);
        }
        //头部导航
        $nav_menu_list =  f_c("home_nav_menu_list_{$this->user_info['city']}");
        if(YII_DEBUG || !$nav_menu_list) {
            $nav_menu_list = OtherMainMenu::getNavMenuListByTypeAndCity(0,$this->user_info['city']);
            if(!$nav_menu_list) {
                //to-do 请维护
            }
            f_c("home_nav_menu_list_{$this->user_info['city']}",$nav_menu_list,$cacheLiftTime);
        }
         //用户楼层信息
        $key_floor = "home_floor_data_list_".$city_id."_".$user_group;
        $floor_data_list = f_c($key_floor);

        if(YII_DEBUG || !$floor_data_list) {
           $floor_list = HomeFloor::getFloorsInfoByUser($this->user_info);
           if(!$floor_list) {
               //to-do 如果该用户楼层信息未配置应该设置默认的楼层信息
           }

           $floor_data_list = HomeFloor::getAllFloorData($floor_list);
           f_c($key_floor,$floor_data_list, Yii::$app->params['home.floor.life.time']);
        }
        //最近交易的订单
        $last_order = f_c('home_last_order');

        if(!$last_order) {
            $last_order = OmsGoods::getLastFifty();
            f_c('home_last_order',$last_order,$cacheLiftTime);
        }
        //公告信息
        $notice = f_c('home_notice');

        if(YII_DEBUG || !$notice || isset($_GET['fresh']) && $_GET['fresh']==1) {
            $notice = [];
            $notice['novice'] =  OtherHelpCenterArticle::getArticleApiList(Yii::$app->params['home.helpcenter.novice'],2,$city_id);  //买家入门
            $notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,2,$city_id);//促销公告
            f_c('home_notice',$notice,1800);
        }
        //用户扩展信息
        $user_ext_data = f_c('home_user_ext_'.$this->user_info['id']);

        if(YII_DEBUG || !$user_ext_data) {
            $user_ext_data = UserMember::getHomeInfo($this->user_info['id']);
            f_c('home_user_ext_'.$this->user_info['id'],$user_ext_data,$cacheLiftTime);
        }
        //广告位
        $key_ad = 'home_ad_'.$city_id.'_'.$user_group;
        $ad = f_c($key_ad);
        if(YII_DEBUG || !$ad) {
            $img_ad_postion_config = $this->user_info['user_group'] ? Yii::$app->params['home.cmcc.ad.positon']:Yii::$app->params['home.ad.positon'];
            $ad = [];
            foreach($img_ad_postion_config as $key=>$val) {
                $ad[$key] = AdInfo::getAd($val['pid'], $this->user_info['city'], $val['num']);
                f_c($key_ad, $ad, $cacheLiftTime);
            }
        }

        $re = UserMember::userSelfUpdate(\Yii::$app->user->id);
        return $this->render('index',[
            'nav_menu_list' => $nav_menu_list,
            'category_list'=>$category_list,
            'ad'=>$ad,
            'last_order'=>$last_order,
            'notice'=>$notice,
            'user_info'=>$this->user_info,
            'user_ext_data'=>$user_ext_data,
            'floor_data_list'=>$floor_data_list,
            'open_id'=>$this->user_info['open_id'],
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
		$ad = f_c('cmcc_home_ad_'.$city_id);
		if(!$ad) {
			$ad = [];
			$ad['banner'] = AdInfo::getAd(10,$city_id);				//大banner图
			$ad['banner_next'] = AdInfo::getAd(11,$city_id,3);		//banner下方
			$ad['finance'] = AdInfo::getAd(12,$city_id,4);			//金融4个
			$ad['today_bao'] = AdInfo::getAd(13,$city_id);			//今日爆品
			//f_c('cmcc_home_ad_'.$city_id,$ad,3600*12);
		}
		//最近交易的订单
		$last_order = f_c('home_last_order');
		if(!$last_order) {
			$last_order = OmsGoods::getLastFifty();
			f_c('home_last_order',$last_order,1800);
		}
		//我的关注
		$my_care = f_c('home_my_care_'.$user_id);
		if(!$my_care) {
			$my_care = FavoriteGoods::getmycare($user_id,$city_id);
			f_c('home_my_care_'.$user_id,$my_care,3600*24);
		}
		//楼层信息
		$one_floor = f_c('cmcc_home_one_floor_'.$city_id);
		if(!$one_floor) {
			$floor_info = HoneFloor::getCityFloor($city_id,1);  // 首先获取楼层的数量
			$one_floor = HoneFloor::getOneFloorData($floor_info); //获取一楼的数据
			f_c('cmcc_home_one_floor_'.$city_id,$one_floor,3600*24);
		}
		//公告信息
		$notice = f_c('home_notice');
		if(!$notice) {
			$notice = [];
			$notice['novice'] = OtherHelpCenterArticle::getArticleApiList(2,3,$city_id)['list'];	//新手
			$notice['promotion'] = OtherHelpCenterArticle::getArticleApiList(3,3,$city_id)['list'];//促销公告
			f_c('home_notice',$notice,1800);
		}
		//用户信息
		$memder_data = f_c('home_member_data_'.$user_id);
		if(!$memder_data) {
			$memder_data = UserMember::getHomeInfo($user_id);
			f_c('home_member_data_'.$user_id,$memder_data,3600);
		}
		
		

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
        if(Yii::$app->request->getIsAjax()){
            $return = Sign::updateSignInfo();
            $memder_data = UserMember::getHomeInfo($this->user_info['id']);
            f_c('home_user_ext_'.$this->user_info['id'],$memder_data,3600);
            return json_encode($return);
        }
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
            $model->save();
            return 1;
            Yii::$app->end();
        }
    }
    
    /**
     * @description:首页推送消息
     * @author:lxzmy
     * @date:2016-1-7 11-08
     */
    public function actionUserPushNotice(){
        $user = $this->user_info;
        $user_id = $user['id'];
        $push = UserPushTime::find()->asArray()->one();
        if($push){
//            $first = 1000*60*$push['first_time'];   //第一次获取的时间(分钟)
//            $second = 1000*60*$push['second_time'];   //第二次获取时间（分钟）  
            $first = 1000*$push['first_time'];   //第一次获取的时间(秒)
            $second = 1000*$push['second_time'];   //第二次获取时间（秒）  
        }else{
//            $first = 1000*60*2;   
//            $second = 1000*60*8;   
            $first = 1000*3;   
            $second = 1000*30;
        }
        $province_id = $user['province'];    //获取省份ID
        $guding = 5184;        //固定值
        $hkh = 'HKH$K1DS';
        $time = date("Ymd");
        $str = md5($guding.$hkh.$time);
        
        $res ="var push_51_first_time = $first;\n";     
        $res .="var push_51_start_time = $second;\n"; 
        $res .="var push_51_province = $province_id;\n";
        $res .="var push_51_user_id = $user_id;\n";  
        $res .="var push_51_hbid = $guding;\n";    
        $res .="var push_51_token = '$str';\n";
        return $res;
    }
    /**
     * @description:签到弹框
     * @return: Ambigous <string, string>
     * @author: sunkouping
     * @date: 2016年1月8日上午9:12:37
     * @modified_date: 2016年1月8日上午9:12:37
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionSignTan(){
    	$return = Sign::updateSignInfo();
    	$mes['exp'] = $return['exp'];
    	$data = Sign::getUserSignInfo();
    	$mes['continues'] = $data['continues'];
    	$memder_data = UserMember::getHomeInfo($this->user_info['id']);
    	f_c('home_user_ext_'.$this->user_info['id'],$memder_data,3600);
    
    	$today = date("d",time());
    	$mes['pev1'] =date("d",strtotime("-1 day"));
    	$mes['pev2'] =date("d",strtotime("-2 day"));
    	$mes['pev3'] =date("d",strtotime("-3 day"));
    	$mes['next1'] =date("d",strtotime("1 day"));
    	$mes['next2'] =date("d",strtotime("2 day"));
    	$mes['next3'] =date("d",strtotime("3 day"));

        return json_encode($mes);
    }
    /**
     * @description:店家完善店铺类型&手机店用户选择门店性质
     * @author:bafeitu
     * @date:2016年1月25日10:02:18
     */
    public function actionUpdateStore(){
        $user_id = f_post('user_id');
        $store_type = f_post('store_type');
        $property = f_post('property',0);
        $model = UserMember::findOne($user_id);
        $model->store_type = $store_type;
        if($property != 0){
            $model->store_property = $property;
        }
        if($model->save()){
            //更新用户信息缓存
            $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
            $key = 'frontend-'.$user_id.'-new_51dh3.0';
            f_c($key,$user_info);
            f_msg('升级成功','/home/index');  
        }else{
            f_msg('升级失败','/home/index');  
        }
    }
    
    /**
     * @description:申请商城
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年1月29日下午3:16:22
     * @modified_date: 2016年1月29日下午3:16:22
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionApplyMall(){
    	//重复申请
    	$user_id = \Yii::$app->user->id;
        $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
        if(!$user_info) {
         	$user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
        }
        $mall_id = $_POST['mall_id'];
        $had_apply = UserApplyMall::find()->where(['user_id'=>$user_id,'mall_id'=>$mall_id])->asArray()->one();
        if($had_apply) {
            if($had_apply['apply_again_date'] >= date('Y-m-d H:i:s')){
                f_msg('您申请的商城考核不通过，请于（'.$had_apply['apply_again_date'].'）之后尝试再次申请', '/home/index');
                exit;
            }else if(!empty($had_apply['apply_again_date'])&&$had_apply['apply_again_date']!='0000-00-00 00:00:00'&&$had_apply['apply_again_date'] <= date('Y-m-d H:i:s')){
                $applay = UserApplyMall::updateAll(['apply_time'=>f_date(time()),'status'=>2],['user_id'=>$user_id,'mall_id'=>$mall_id]);
                if($applay){
                    f_msg('申请成功，请耐心等待审核', '/home/index');exit;
                } else {
                    f_msg('非常抱歉申请失败', '/home/index');exit;
                }
            }
        	f_msg('您已经申请过了，请耐心等待审核', '/home/index');
        } else {
        	$model = new UserApplyMall();
        	$model->user_id = $user_id;
        	$model->mall_id = $mall_id;
            $model->status = 2;
            $model->apply_time = f_date(time());
            $model->sale_money = $_POST['sale_money'];
            $model->apply_reason = $_POST['apply_reason'];
            $model->apply_tips = '';
            if($model->save()){
            	f_msg('申请成功，请耐心等待审核', '/home/index');
            } else {
            	f_msg('非常抱歉申请失败', '/home/index');
            }
        }
    }
    
    /**
     * @description:问卷调查
     * @return: return_type
     * @author: wufeng
     * @date: 2016年3月29日
     * @modified_date: 
     * @modified_user: wufeng
     * @review_user:
     */
    
    public function actionResearch(){
        
        $exam_id = f_get('exam_id', 0);
        $mall_id = f_get('mall_id', 0);
        $exam = OtherExam::getDetailOfExamById($exam_id, $mall_id); //通过商城id获取问卷调查的信息
        
        $this->layout = false;
        return $this->render('research', [
            'exam' => $exam,
        ]);
        
    }
    
    /*
     * @description:答题提交
     * @author:wufeng
     * @date:2016-3-22
     */
    public function actionResearchSubmit() {
        $items = Yii::$app->request->post('items'); //选项值
        $option = [];
        if($items){
            foreach($items as $key => $val){
                if(array_key_exists($val['name'], $option)) $option[$val['name']] = $option[$val['name']].','.$val['val']; //多选题
                else $option[$val['name']] = $val['val'];
            }
        }
    
        $examid = Yii::$app->request->post('examid');
        $userid = \Yii::$app->user->id;
    
        $examUser = OtherExamUser::find()->where(['examid' => $examid, 'userid' => $userid])->one();
        if(!$examUser) {
            if($option) {
                $connection = Yii::$app->db;//事务开始
                $transaction = $connection->beginTransaction();
                try {
                    $examUser = new OtherExamUser();
                    $examUser->examid = $examid;
                    $examUser->userid = $userid;
                    $examUser->endtime = date('Y-m-d H:i:s', time());
                    $examUser->save();
    
                    foreach($option as $key => $val){
                        $examRecord = OtherExamRecord::find()->where(['examid' => $examid, 'exam_optionid' => $key, 'exam_userid' => $examUser->exam_userid])->one();
                        if($examRecord) {
                            $examRecord->answer = trim($val);
                            $examRecord->answertime = date('Y-m-d H:i:s', time());
                            $examRecord->exam_optionid = $key;
                            $examRecord->exam_userid = $userid;
                            $examRecord->examid = $examid;
                            $examRecord->save();
                        }else {
                            $examRecord = new OtherExamRecord();
                            $examRecord->answer = trim($val);
                            $examRecord->answertime = date('Y-m-d H:i:s', time());
                            $examRecord->exam_optionid = $key;
                            $examRecord->exam_userid = $userid;
                            $examRecord->examid = $examid;
                            $examRecord->save();
                        }
                    }
    
                    $exam = OtherExam::getExamByExamid($examid);
                    $now = date('Y-m-d 00:00:00', time());
                    $end = date('Y-m-d 23:59:59', strtotime("+6 day"));
    
                    $coupon = CouponInfo::findOne($exam['coupon_id']);
                    $couponUser = CouponUser::find()->select(['id'])->where(['coupon_id'=>$examid, 'user_id' => $userid, 'remark' => '问卷调查'])->one();
                    if(!$couponUser)  CouponUser::addCoupon($exam['coupon_id'],$userid, '问卷调查组', $now, $end, '问卷调查');
    
                    $examUser->remark = '于'.date('Y-m-d H:i:s', time()).'获得 (优惠券编号：'.$coupon['id'].') '.$coupon['coupon_name'];
                    $examUser->save();
    
                    $transaction->commit();
                } catch (\Exception $e) {
                    $transaction->rollBack();
                }
    
                if($coupon) {
                    $this->layout = false;
                    return $this->render('research_success', [
                        'coupon' => $coupon,
                    ]);
                }
            }
        }
    }

    /**
     * @description: 点击统计
     * @author: wangmingcha
     * @date: 2016年4月7日下午3:16:22
     * @modified_date: 2016年1月29日下午3:16:22
     * @modified_user: sunkouping
     */
    public function actionStatClick(){
        if(Yii::$app->request->getIsAjax()){
            $userInfo = $this->user_info;
            $postParams = Yii::$app->request->post();
            $postParams['province'] = $userInfo['province'];
            $postParams['city'] = $userInfo['city'];
            $postParams['create_time'] = date("YmdH");
            $statClickDetail = new StatClickDetail();
            $statClickDetail->addStatClickToRedis($postParams);
        }
    }


    public function actionDigest(){
        $postParams = ['create_time'=>date("YmdH")];
        $statClickDetail = new StatClickDetail();
        $statClickDetail->digestStatClickFromRedis($postParams,true);
    }

    public function actionDigestTest(){
        for($i=0;$i<=23;$i++){
            $create_time = date("Ymd");
            $hour = $i;
            if(strlen($i)==1){
                $hour = "0".$i;
            }
            $create_time.= $hour;
            $params = ['create_time'=>$create_time];
            $statPuUvDetail = new StatPvUvDetail();
            $statPuUvDetail->digestUpvFromRedis($params,true);
        }
    }
    /**
     * @description:直播的ajax
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年5月25日下午5:40:04
     * @modified_date: 2016年5月25日下午5:40:04
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionLive(){
    	$zhibo = LiveTv::getLive(1);
    	return $this->renderAjax('/common/mall/zhibo.php',['zhibo'=>$zhibo]);
    }
    /**
     * @description:广东蓝迅引流统计
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年8月11日下午2:10:47
     * @modified_date: 2016年8月11日下午2:10:47
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionGdtj(){
    	$uid = f_post('id',0);
    	$identy = 'guangdong_ti';
    	$model = Log::find()->where(['foreign_key'=>$uid,'identity'=>$identy])->asArray()->one();
    	if(!$model) {
    		Log::addData([
    		'foreign_key' => $uid,
    		'identity' => $identy,
    		'operator' => '闭门思过',
    		'content' => '蓝迅引流统计',
    		]);
    	}
    	return 1;
    }
    /**
     * @description:母婴全员红包
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年8月11日下午3:55:40
     * @modified_date: 2016年8月11日下午3:55:40
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionBaby(){
    	$connection = \Yii::$app->db;
    	$sql = 'select * from skp_run_jb limit 0,1000';
    	$ids = $connection->createCommand($sql)->queryAll();
    	foreach ($ids as $user_id) {
    		$a = CouponUser::addCoupon('2571',$user_id['id'],'全员推送','2016-08-12 00:00:01','2016-08-15 23:59:58');
    		if($a){
    			$sql = 'delete from skp_run_jb where id = '.$user_id['id'];
    			$connection->createCommand($sql)->execute();
    		}
    	}
    }
}
