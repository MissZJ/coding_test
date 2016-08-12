<?php
namespace frontend\controllers;

use frontend\components\Controller2016;
use Yii;
use common\models\other\ActiveModelItems;
use common\models\other\ActiveModel;
use common\models\other\PrizesUser;
use common\models\other\Prizes;
use common\models\markting\CouponUser;
use common\models\other\ActivityPageStyle;
use common\models\user\UserMember;
use yii\db\Query;

class ActiveController extends Controller2016{
	// 基数: 一百万
	const BASE_NUMBER = 1000000;

	// debug flag,测试用
	const DEBUG_FLAG = false;

	// 活动id
	const ACTIVE_ID = 100;
	//通讯功能机
	public $activeId1 = 2261;
	//通讯智能机
	public $activeId2 = 2262;
	//通讯配件
	public $activeId3 = 2263;
	//IT11点秒杀
	public $activeId4 = 2264;
	//特价畅购专区(26IT)
	public $activeId5 = 2265;
	//开门迎新秒杀（11点）（26母婴）
	public $activeId6 = 2266;
	//开门迎新秒杀（11点）（26家电）
	public $activeId7 = 2267;
	//特价畅购专区（26家电）
	public $activeId8 = 2268;
	//开门迎新秒杀16点(26IT)
	public $activeId9 = 2269;
	//开门迎新秒杀（15点）（26母婴）
	public $activeId10 = 2270;
	//开门迎新秒杀（14点）（26家电）
	public $activeId11 = 2271;
	//开门迎新秒杀（16点）（26家电）
	public $activeId12 = 2272;

	public function beforeAction($action){
		if ($this->user_info['user_group'] == 1){
			$this->activeId1 = $this->activeId1 + 100;
			$this->activeId2 = $this->activeId2 + 100;
			$this->activeId3 = $this->activeId3 + 100;
			$this->activeId4 = $this->activeId4 + 100;
			$this->activeId5 = $this->activeId5 + 100;
			$this->activeId6 = $this->activeId6 + 100;
			$this->activeId7 = $this->activeId7 + 100;
			$this->activeId8 = $this->activeId8 + 100;
			$this->activeId9 = $this->activeId9 + 100;
			$this->activeId10 = $this->activeId10 + 100;
			$this->activeId11 = $this->activeId11 + 100;
			$this->activeId12 = $this->activeId12 + 100;
		}
		return parent::beforeAction($action);
	}

	/**
	 *
	 * @Title: 1.11母婴活动主页面
	 * @Description: 1.11母婴活动页面处理
	 * @return: return_type
	 * @author: yazhou.miao
	 * @date: 2016-1-6 上午10:05:46
	 */
	public function actionIndex(){
		
		// TODO:用户访问限制(地域)
		$flag = $flag1 = $flag2 = false;
		if (f_isMobile()){
			$this->redirect('http://wsc.51dh.com.cn/active/joinbaby');
		}
		$province = [16,3,13,31];
		if (in_array($this->user_info['province'], $province)){
			$flag1 = true;
		}
		$nowTime = date('Y-m-d H:i:s');
		if ($nowTime > '2016-01-11 09:00:00' && $nowTime < '2016-01-17 23:59:59'){
			$flag2 = true;
		}
		$flag1 = true; $flag2 = true;
		if(!($flag1 && $flag2)){
			// 不参加互动，返回首页
			$this->redirect('/');
		}
		// 转盘页面
		$zhuanpan =  $this->getViewZhuanpan();
		
		// 五折限时购(秒杀)
		$seckill =  $this->getViewSeckill();
		
		// 特价商品页面
		$specail =  $this->getViewSpecialGoods();
		
		// 渲染页面
		return $this->renderPartial("active111_baby_index", [
			'zhuanpan' => $zhuanpan,
			'seckill'  => $seckill,
			'special'  => $specail,
			'flag'=>$flag,
		]);
	}
	
	/**
	 * @Title: 处理转盘ajax请求
	 * @Description:
	 * @return: json
	 * @author: yazhou.miao
	 * @date: 2016-1-6 下午3:36:30
	 */
	public function actionAjaxZhuanpan() {
		$json = [];
		
		// TODO: 前台转盘设置的奖品id,正北方向开始，顺时针排序。
		$prize_Ids = [61,62,63,65,64,60];
		
		$user_info = $this->user_info;
		
		// 活动开始时间、活动结束时间
		$activeStartTime = strtotime('2016-1-11 09:00:00');
		$activeEndTime = strtotime('2016-1-17 23:59:59');
		// 当天的起始、截止时间
		$currStartTime = mktime(0,0,0,date('m'),date('d'),date('Y'));
		$currEndTime   = mktime(23,59,59,date('m'),date('d'),date('Y'));
		
		// 1、判断当前是否可以参加活动
		// 判断用户所在省份区域
		$provinces = [16,3,13,31];
		if (!in_array($this->user_info['province'], $provinces)){
			$json['status'] = -1;
			$json['message'] = '抱歉，您所在区域不可参加活动！';
		} else {
			// 判断活动时间
			if(time() < $activeStartTime){
				$json['status'] = -1;
				$json['message'] = '活动尚未开始！';
			} else if(time() > $activeEndTime) {
				$json['status'] = -1;
				$json['message'] = '活动已经结束！';
			}
		}
		
		// 2、判断用户当天是不是已经参与过抽奖
		if(!isset($json['status'])){
		// TODO:测试
		//if(true){
			// TODO:active_id待定
			$winTime = PrizesUser::find()->select('time_added')->where(['user_id' => $user_info['id']])->andWhere(['active_id' => self::ACTIVE_ID])->max('time_added');
			
			// 对话框信息
			if($winTime && $currStartTime <= $winTime && $winTime <= $currEndTime){
				// 如果当天已经参与则不在参与转盘抽奖
				$json['status'] = -1;
				$json['message'] = '抱歉，一天只能参加一次抽奖！';
			} else {
				// 3.抽奖处理
				$currTime = time();
				// 活动天数
				//$activeDays = ceil(($activeEndTime - $activeStartTime)/(60*60*24));
				$activeDays = 7;
				
				// 初始化奖品信息
				$prizesInfo = Prizes::find()->where(['active_id' => self::ACTIVE_ID])->andWhere(['>','qty',0])->all();
				
				$startNum = 0;
				
				foreach($prizesInfo as $prize){
					// 此产品中奖总次数
					$winCount = PrizesUser::find()->where(['active_id' => self::ACTIVE_ID,'prize_id' => $prize['id']])->count();
					// 此产品当天中奖次数
					$winCurrCount = PrizesUser::find()->where(['active_id' => self::ACTIVE_ID,'prize_id' => $prize['id']])->andWhere(['>=','time_added',$currStartTime])->andWhere(['<=','time_added',$currEndTime])->count();
					
					// 当天中奖次数>=平均每天要中奖的次数，则此产品不会再抽中
					if($winCurrCount >= floor(($winCount + $prize['qty'])/$activeDays)){
						// 如果中奖次数
						continue;
					}
					// 中奖区间最小随机数
					$min = $startNum + 1;
					// 中奖区间最小随机数
					$max = $startNum + self::BASE_NUMBER * $prize['rate'];
						
					$prizes[$prize['id']] = [
						'id'   => $prize['id'],
						'min'  => $min,
						'max'  => $max,
						'name' => $prize['name'],
						'coupon_id' => $prize['coupon_id'],
					];
						
					$startNum = $max;
				}
				
				if(isset($prizes)){
					// 必须抽中一个
					do{
						// 中奖的奖品id
						$winPrizeId = $this->getWinPrizeId($prizes);
					} while(!$winPrizeId);
					
					// 中奖后的其他处理
					$return = CouponUser::addCoupon($prizes[$winPrizeId]['coupon_id'], $user_info['id'], '1.11母婴活动', date('Y-m-d H:i:s',time()), date('Y-m-d H:i:s',$activeEndTime),$prizes[$winPrizeId]['name']);
					
					if($return){
						$prize = Prizes::find()->where(['id' => $winPrizeId])->one();
						$prize->qty = $prize->qty > 0 ? $prize->qty - 1 : 0;
						$return = $prize->save();
						if($return){
							$prizesUser = new PrizesUser();
							$prizesUser->user_id = $user_info['id'];
							$prizesUser->active_id = self::ACTIVE_ID;
							$prizesUser->prize_id = $winPrizeId;
							$prizesUser->times = 1;
							$prizesUser->time_added = time();
							
							$return = $prizesUser->insert();
						} 
					}
					
					if($return){
						// 确认奖品在转盘中的位置
						$counter = -1;
						foreach($prize_Ids as $prizeId){
							$counter++;
							if($winPrizeId === $prizeId){
								break;
							}
						}
							
						$json['status'] = 0;
						$json['message'] = "恭喜您抽中" . $prizes[$winPrizeId]['name'] . "!";
						$json['rotate'] = (360/count($prize_Ids)) * $counter; // 转盘转动角度
					} else {
						$json['status'] = -1;
						$json['message'] = '服务端错误！';
					}
				} else {
					$json['status'] = -1;
					$json['message'] = '服务端错误！';
				}
			}
		}
	
		return json_encode($json);
	}
	
	/**
	 * @Title: 抽奖逻辑
	 * @Description: 抽奖内部逻辑 :产生一个随机数，查看该随机数在哪个中奖区间
	 * @param: $prizes array 奖品信息 
	 * 数组中要包括id:奖品id,min:随机区间最小值,max:随机区间最大值
	 * @return: 奖品id
	 * @author: yazhou.miao
	 * @date: 2016-1-7 下午5:05:34
	 */
	public function getWinPrizeId($prizes = []) {
		//  奖品id
		$pid = -1;
		
		if($prizes && is_array($prizes)){
			// 产生随机数
			$randNum = rand(1 , self::BASE_NUMBER);

			// 所中奖品
			foreach($prizes as $prize){
				if(isset($prize['min']) && isset($prize['max']) && $randNum >= $prize['min'] && $randNum <= $prize['max']){
					$pid = $prize['id'];
					break;
				}
			}
		}
		
		return $pid === -1 ? false : $pid;
	}
	/**
	 *
	 * @Title: 转盘初始化页面
	 * @Description: 1.11母婴活动——转盘
	 * @return: 页面渲染结果
	 * @author: yazhou.miao
	 * @date: 2016-1-6 上午10:05:46
	 */
	public function getViewZhuanpan() {
		
		return $this->renderPartial("active111_baby_zhuanpan", [
			// TODO:
		]);
	}
	
	/**
	 * @Title: 秒杀页面
	 * @Description: 返回秒杀产品页面
	 * @return: return_type
	 * @author: yazhou.miao
	 * @date: 2016-1-6 下午6:01:11
	 */
	public function getViewSeckill() {
		// TODO:
		$timeLimit = date('H:i:s');
		$day = (int)date('d');
		$month = (int)date('m');
		if ($timeLimit > '18:00:00'){
			$day = $day + 1;
		}
		$id1 = $month*$day*10+1;
		$id2 = $month*$day*10+2;
		$seckillGoods1 = ActiveModelItems::getActiveGoods($id1, $this->user_info['city']);
		$seckillGoods2 = ActiveModelItems::getActiveGoods($id2, $this->user_info['city']);
		return $this->renderPartial("active111_baby_seckill", ['seckillGoods1'=>$seckillGoods1,
															   'seckillGoods2'=>$seckillGoods2,
															   'timeLimit'=>$timeLimit,
		]);
	}
	
	/**
	 * @Title: 特价商品页面
	 * @Description: 返回特价商品页面
	 * @return: return_type
	 * @author: yazhou.miao
	 * @date: 2016-1-6 下午6:03:12
	 */
	public function getViewSpecialGoods() {
		$specialGoods = ActiveModelItems::getActiveGoods(100, $this->user_info['city']);
		return $this->renderPartial("active111_baby_special", ['specialGoods'=>$specialGoods]);
	}
	/**
     * @Title: actionJoinMonkey
     * @Description: 猴年活动
     * @return: return_type
     * @author: jian.zhang
     * @date: 2016-01-07 17:27
     */
    public function actionJoinmonkey() {
        $user_info = $this->user_info;
        $active_id1 = 1;//秒杀11
        $active_id2 = 2;//秒杀14
        $active_id3 = 3;//秒杀16
        $active_id4 = 4;//猴年下单立减专区
        $active_id5 = 5;//猴年阶梯定价专区
        $active_id6 = 6;//猴年功能机专区
        $active_id7 = 7;//猴年非手机专区
        $user_id = $user_info['id'];
        $city_id = $user_info['city'];

        $miaosha11 = Yii::$app->cache->get("miaosha11" . $city_id); //产品
        $miaosha14 = Yii::$app->cache->get("miaosha14" . $city_id); //产品
        $miaosha16 = Yii::$app->cache->get("miaosha16" . $city_id); //产品
        $xdlj = Yii::$app->cache->get("xdlj" . $city_id); //产品
        $jtdj = Yii::$app->cache->get("jtdj" . $city_id); //产品
        $gongneng = Yii::$app->cache->get("gongneng" . $city_id); //产品
        $feishouji = Yii::$app->cache->get("feishouji" . $city_id); //产品

        $date1 = strtotime(date('Y-m-d 12:00:00',time()));
        $date2 = strtotime(date('Y-m-d 15:00:00',time()));
        $now = time();
        if (!$miaosha11) {
        	$miaosha11 = ActiveModel::getModelGoods($active_id1,$city_id);
        	Yii::$app->cache->set("miaosha11" . $city_id, $miaosha11, 60);
        }
        if (!$miaosha14) {
        	$miaosha14 = ActiveModel::getModelGoods($active_id2,$city_id);
        	Yii::$app->cache->set("miaosha14" . $city_id, $miaosha14, 60);
        }
        if (!$miaosha16) {
	        $miaosha16 = ActiveModel::getModelGoods($active_id3,$city_id);
	        Yii::$app->cache->set("miaosha16" . $city_id, $miaosha16, 60);
	    }
	    if (!$xdlj) {
	        $xdlj = ActiveModel::getModelGoods($active_id4,$city_id);
	        Yii::$app->cache->set("xdlj" . $city_id, $xdlj, 60);
	    }
	    if (!$jtdj) {
        	$jtdj = ActiveModel::getModelGoods($active_id5,$city_id);
        	Yii::$app->cache->set("jtdj" . $city_id, $jtdj, 60);
        }
        if (!$gongneng) {	
        	$gongneng = ActiveModel::getModelGoods($active_id6,$city_id);
        	Yii::$app->cache->set("gongneng" . $city_id, $gongneng, 60);
	    }
	    if (!$feishouji) {
        	$feishouji = ActiveModel::getModelGoods($active_id7,$city_id);
        	Yii::$app->cache->set("feishouji" . $city_id, $feishouji, 60);
        }
       
        $model_goods = [];
        foreach ($miaosha11 as $key => $value) {
            $model_goods['猴年秒杀11'][] = $value;
        }
        foreach ($miaosha14 as $key => $value) {
            $model_goods['猴年秒杀14'][] = $value;
        }
        foreach ($miaosha16 as $key => $value) {
            $model_goods['猴年秒杀16'][] = $value;
        }
        foreach ($xdlj as $key => $value) {
            $model_goods['猴年下单立减专区'][] = $value;
        }
        foreach ($jtdj as $key => $value) {
            $model_goods['猴年阶梯定价专区'][] = $value;
        }
        foreach ($gongneng as $key => $value) {
            $model_goods['猴年功能机专区'][] = $value;
        }
        foreach ($feishouji as $key => $value) {
            $model_goods['猴年非手机专区'][] = $value;
        }
        return $this->renderPartial("active111_joinmonkey", [
                    'model_goods'=>$model_goods,
                    "city_id" => $city_id,
                    "date1" => $date1,
                    "date2" => $date2,
                    "now" => $now,
        ]);
    }
    
   	/**
   	 * @desc 配件
   	 * @return string
   	 */
   	public function actionParts(){
   		$specialGoods = ActiveModelItems::getActiveGoods(1905, $this->user_info['city']);
   		return $this->renderPartial('active119_parts',['specialGoods'=>$specialGoods]);
   	}
   	
   	/**
   	 * @Description: 猴年活动特价笔记本页面处理
   	 * @return: 渲染后的页面
   	 * @author: yazhou.miao
   	 * @date: 2016-1-13 上午9:45:57
   	 */
   	public function actionLaptop() {
   		// TODO:活动id待定
   		$specialGoods = ActiveModelItems::getActiveGoods(1901, $this->user_info['city']);
   		return $this->renderPartial('active119_laptop',['specialGoods'=>$specialGoods]);
   	}
   	
   	/**
   	 * @Description: 猴年活动特价礼物页面处理
   	 * @return: 渲染后的页面
   	 * @author: yazhou.miao
   	 * @date: 2016-1-13 上午9:54:23
   	 */
   	public function actionGift() {
   		// TODO:活动id待定
   		$specialGoods = ActiveModelItems::getActiveGoods(1904, $this->user_info['city']);
   		return $this->renderPartial('active119_gift',['specialGoods'=>$specialGoods]);
   	}
   	
   	/**
   	 * @Description: 猴年活动特价平板电脑页面处理
   	 * @return: 渲染后的页面
   	 * @author: yazhou.miao
   	 * @date: 2016-1-13 上午9:56:40
   	 */
   	public function actionPad() {
   		// TODO:活动id待定
   		$specialGoods = ActiveModelItems::getActiveGoods(1903, $this->user_info['city']);
   		return $this->renderPartial('active119_pad',['specialGoods'=>$specialGoods]);
   	}
   	
   	/**
   	 * @Description: 猴年活动特价小家电页面处理
   	 * @return: 渲染后的页面
   	 * @author: yazhou.miao
   	 * @date: 2016-1-13 上午9:59:03
   	 */
   	public function actionHomeAppliance() {
   		// TODO:活动id待定
   		$specialGoods = ActiveModelItems::getActiveGoods(1902, $this->user_info['city']);
   		return $this->renderPartial('active119_home_appliance',['specialGoods'=>$specialGoods]);
   	}
   	
   	/**
   	 * @Description: 猴年活动特价智能机页面处理
   	 * @return: return_type
   	 * @author: yazhou.miao
   	 * @date: 2016-1-13 上午10:02:35
   	 */
   	public function actionSmartphone() {
   		// TODO:活动id待定
   		$specialGoods = ActiveModelItems::getActiveGoods(1906, $this->user_info['city']);
   		return $this->renderPartial('active119_smartphone',['specialGoods'=>$specialGoods]);
   	}
   	
   	/**
   	 * @Description: 红包抽奖页面
   	 * @return: 渲染后的页面
   	 * @author: yazhou.miao
   	 * @date: 2016-1-13 下午4:40:37
   	 */
   	public function actionLottery() {
   		return $this->renderPartial('active119_hongbao',[]);
   	}
   	
   	/**
   	 * @Description: 1.16~1.18号预热活动：处理ajax的抽红包请求
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-1-14 上午10:29:12
   	 */
   	public function actionAjaxLottery() {
		$json = [];
		// pc端预热抽红包活动id
		$activeId = 119;
		
		$user_info = $this->user_info;
		
		// 活动开始时间、活动结束时间
		$activeStartTime = strtotime('2016-1-16 00:00:00');
		$activeEndTime = strtotime('2016-1-18 23:59:59');
		
		// 1、判断当前是否可以参加活动
		// 判断活动时间
		if(time() < $activeStartTime){
			$json['status'] = -1;
			$json['message'] = '预热活动尚未开始！';
		} else if(time() > $activeEndTime) {
			$json['status'] = -1;
			$json['message'] = '预热活动已经结束！';
		}
		
		// 2、判断用户当天是不是已经参与过抽奖
		if(!isset($json['status'])){
		// TODO:测试
		//if(true){
			$winTime = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['active_id' => $activeId])->max('time_added');
			
			if($winTime && $activeStartTime <= $winTime && $winTime <= $activeEndTime){
				// 如果当天已经参与则不在参与转盘抽奖
				$json['status'] = -1;
				$json['message'] = '抱歉，您已参与过此活动！';
			} else {
				// 3.抽奖处理
				$currTime = time();
				
				// 初始化奖品信息
				$prizesInfo = Prizes::find()->where(['active_id' => $activeId])->andWhere(['>','qty',0])->all();
				
				$startNum = 0;
				
				foreach($prizesInfo as $prize){
					// 中奖区间最小随机数
					$min = $startNum + 1;
					// 中奖区间最小随机数
					$max = $startNum + self::BASE_NUMBER * $prize['rate'];
						
					$prizes[$prize['id']] = [
						'id'   => $prize['id'],
						'min'  => $min,
						'max'  => $max,
						'name' => $prize['name'],
						'coupon_id' => $prize['coupon_id'],
					];

					$startNum = $max;
				}
				
				if(isset($prizes)){
					// 必须抽中一个
					do{
						// 中奖的奖品id
						$winPrizeId = $this->getWinPrizeId($prizes);
					} while(!$winPrizeId);
					
					// 发送红包到用户帐下
					$return = CouponUser::addCoupon($prizes[$winPrizeId]['coupon_id'], $user_info['id'], '金猴幸运摇摇乐-PC端', date('Y-m-d H:i:s',strtotime('2016-1-19 00:00:00')), date('Y-m-d H:i:s',strtotime('2016-1-19 23:59:59')),$prizes[$winPrizeId]['name']);
					
					if($return){
						$prize = Prizes::find()->where(['id' => $winPrizeId])->one();
						$prize->qty = $prize->qty > 0 ? $prize->qty - 1 : 0;
						$return = $prize->save();
						if($return){
							$prizesUser = new PrizesUser();
							$prizesUser->user_id = $user_info['id'];
							$prizesUser->active_id = $activeId;
							$prizesUser->prize_id = $winPrizeId;
							$prizesUser->times = 1;
							$prizesUser->time_added = time();
							
							$return = $prizesUser->insert();
						} 
					}
					
					if($return){
						$json['status'] = 0;
						$json['message'] = $prizes[$winPrizeId]['name'];
					} else {
						$json['status'] = -1;
						$json['message'] = '服务端错误！';
					}
				} else {
					$json['status'] = -1;
					$json['message'] = '抱歉，红包已经派送完了！';
				}
			}
		}
	
		return json_encode($json);
   	}
   	
   	/**
   	 * @desc 年终庆典
   	 */
   	public function actionCelebration(){
   		//抽奖
   		$lukey = $this->getLucky();
   		//一元秒杀
   		$oneSpike = $this->getOnespike();
   		//秒杀
   		$yearSpike = $this->getYearspike();
   		//特价
   		$category = $this->getCategory();
   		
   		return $this->renderPartial('active119_index',['lukey'=>$lukey,
										   			   'oneSpike'=>$oneSpike,
										   			   'yearSpike'=>$yearSpike,
										   			   'category'=>$category
   		]);
   	}
   	
   	/**
   	 * @desc 年终抽奖 
   	 */
   	public function getLucky(){
   		return $this->renderPartial('active119_lucky');
   	}
   	
   	/**
   	 * @desc 一元秒杀
   	 */
   	public function getOnespike(){
   		$timeLimit = date('H:i:s');
        $city_id = $this->user_info['city'];
        $miaosha10 = Yii::$app->cache->get("miaosha10" . $city_id);   //10点产品 
        
        $miaosha15 = Yii::$app->cache->get("miaosha15" . $city_id);   //15点产品   
        if (!$miaosha10) {
        	$miaosha10 = ActiveModelItems::getActiveGoods($this->activeId1,$city_id);
        	Yii::$app->cache->set("miaosha10" . $city_id, $miaosha10, 180);
        }     
        if (!$miaosha15) {
        	$miaosha15 = ActiveModelItems::getActiveGoods($this->activeId2,$city_id);
        	Yii::$app->cache->set("miaosha15" . $city_id, $miaosha15, 180);
        }     
        $model_goods = [];
        foreach ($miaosha10 as $key => $value) {
            $model_goods['一元秒杀10'][] = $value;
        }
       
        foreach ($miaosha15 as $key => $value) {
            $model_goods['一元秒杀15'][] = $value;
        }
       
   		  return $this->renderPartial('active119_one',['specialGoods'=>$model_goods,'timeLimit'=>$timeLimit]);
   	}
   	
   	/**
   	 * @desc 年终抢秒专区
   	 */
   	public function getYearspike(){
   		$timeLimit = date('H:i:s');
        $city_id = $this->user_info['city'];
        $specialGoods11 = Yii::$app->cache->get("specialGoods11" . $city_id);   //11点产品                   
        $specialGoods13 = Yii::$app->cache->get("specialGoods13" . $city_id);   //13点产品                   
        $specialGoods15 = Yii::$app->cache->get("specialGoods15" . $city_id);   //15点产品                   
        $specialGoods17 = Yii::$app->cache->get("specialGoods17" . $city_id);   //17点产品                   
        if (!$specialGoods11) {
        	$specialGoods11 = ActiveModelItems::getActiveGoods($this->activeId3,$city_id);
        	Yii::$app->cache->set("specialGoods11" . $city_id, $specialGoods11, 180);
        } 
        if (!$specialGoods13) {
        	$specialGoods13 = ActiveModelItems::getActiveGoods($this->activeId4,$city_id);
        	Yii::$app->cache->set("specialGoods13" . $city_id, $specialGoods13, 180);
        } 
        if (!$specialGoods15) {
        	$specialGoods15 = ActiveModelItems::getActiveGoods($this->activeId5,$city_id);
        	Yii::$app->cache->set("specialGoods15" . $city_id, $specialGoods15, 180);
        } 
        if (!$specialGoods17) {
        	$specialGoods17 = ActiveModelItems::getActiveGoods($this->activeId6,$city_id);
        	Yii::$app->cache->set("specialGoods17" . $city_id, $specialGoods17, 180);
        } 
//        $specialGoods11 = ActiveModelItems::getActiveGoods($this->activeId3, $this->user_info['city']);
//        $specialGoods13 = ActiveModelItems::getActiveGoods($this->activeId4, $this->user_info['city']);
//        $specialGoods15 = ActiveModelItems::getActiveGoods($this->activeId5, $this->user_info['city']);
//        $specialGoods17 = ActiveModelItems::getActiveGoods($this->activeId6, $this->user_info['city']);
   		return $this->renderPartial('active119_spike',['specialGoods11'=>$specialGoods11,
   													   'timeLimit'=>$timeLimit,
										   				'specialGoods13'=>$specialGoods13,
										   				'specialGoods15'=>$specialGoods15,
										   				'specialGoods17'=>$specialGoods17,
   		]);
   	}
   	
   	/**
   	 * @desc 全品类特价
   	 */
   	public function getCategory(){
          $city_id = $this->user_info['city'];
        $specialGoods1 = Yii::$app->cache->get("specialGoods1" . $city_id);   //智能机                   
        $specialGoods2 = Yii::$app->cache->get("specialGoods2" . $city_id);   //功能机                   
        $specialGoods3 = Yii::$app->cache->get("specialGoods3" . $city_id);   //配件                   
        $specialGoods4 = Yii::$app->cache->get("specialGoods4" . $city_id);   //母婴                   
                         
        if (!$specialGoods1) {
        	$specialGoods1 = ActiveModelItems::getActiveGoods($this->activeId7,$city_id);
        	Yii::$app->cache->set("specialGoods1" . $city_id, $specialGoods1, 180);
        }
        if (!$specialGoods2) {
        	$specialGoods2 = ActiveModelItems::getActiveGoods($this->activeId8,$city_id);
        	Yii::$app->cache->set("specialGoods2" . $city_id, $specialGoods2, 180);
        }
        if (!$specialGoods3) {
        	$specialGoods3 = ActiveModelItems::getActiveGoods($this->activeId9,$city_id);
        	Yii::$app->cache->set("specialGoods3" . $city_id, $specialGoods3, 180);
        }
        if (!$specialGoods4) {
        	$specialGoods4 = ActiveModelItems::getActiveGoods($this->activeId10,$city_id);
        	Yii::$app->cache->set("specialGoods4" . $city_id, $specialGoods4, 180);
        }
//        $specialGoods1 = ActiveModelItems::getActiveGoods($this->activeId7, $this->user_info['city']);
//        $specialGoods2 = ActiveModelItems::getActiveGoods($this->activeId8, $this->user_info['city']);
//        $specialGoods3 = ActiveModelItems::getActiveGoods($this->activeId9, $this->user_info['city']);
//        $specialGoods4 = ActiveModelItems::getActiveGoods($this->activeId10, $this->user_info['city']);
   		return $this->renderPartial('active119_category',['specialGoods1'=>$specialGoods1,
										   				  'specialGoods2'=>$specialGoods2,
										   				  'specialGoods3'=>$specialGoods3,
										   				  'specialGoods4'=>$specialGoods4,
   														  'userInfo'=>$this->user_info,
   		]);
   	}
   	
   	/**
   	 * @Description: 1.19活动发送ajax请求抽奖
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-1-18 上午9:56:22
   	 */
   	public function actionAjax119Lucky() {
   		
		$json = [];
		// pc端1.19抽奖活动id
		$activeId = 121;
		
		$user_info = $this->user_info;
		
		// 活动开始时间、活动结束时间
		$activeStartTime = strtotime('2016-1-19 00:00:00');
		$activeEndTime = strtotime('2016-1-19 23:59:59');
		// 当前时间
		$currTime = time();
		
		// 1、判断当前是否可以参加活动
		// 判断活动时间
		if($currTime < $activeStartTime){
			$json['status'] = -1;
			$json['message'] = '抽奖活动尚未开始！';
		} else if($currTime > $activeEndTime) {
			$json['status'] = -1;
			$json['message'] = '抽奖活动已经结束！';
		}
		
		// 2、判断用户活动期间是不是已经参与过抽奖
		if(!isset($json['status'])){
		// TODO:测试
		//if(true){
			$winTime = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['active_id' => $activeId])->max('time_added');
			
			if($winTime && $activeStartTime <= $winTime && $winTime <= $activeEndTime){
				// 如果活动期间已经参与则不在参与抽奖
				$json['status'] = -1;
				$json['message'] = '抱歉，您已参与过此活动！';
			} else {
				// 3.抽奖处理
				
				// 初始化奖品信息
				$prizesInfo = Prizes::find()->where(['active_id' => $activeId])->andWhere(['>','qty',0])->all();
				
				$startNum = 0;
				
				foreach($prizesInfo as $prize){
					// 中奖区间最小随机数
					$min = $startNum + 1;
					// 中奖区间最小随机数
					$max = $startNum + self::BASE_NUMBER * $prize['rate'];
						
					$prizes[$prize['id']] = [
						'id'   => $prize['id'],
						'min'  => $min,
						'max'  => $max,
						'name' => $prize['name'],
						'coupon_id' => $prize['coupon_id'],
					];

					$startNum = $max;
				}
				
				if(isset($prizes)){
					// 必须抽中一个
					if(count($prizes) === 1){
						// 如果只有一个奖，直接抽中该奖品
						$winPrizeId = key($prizes);
					} else {
						// 否则按概率抽奖
						do{
							// 中奖的奖品id
							$winPrizeId = $this->getWinPrizeId($prizes);
						} while(!$winPrizeId);
					}
					
					// 发送红包到用户帐下
					$return = CouponUser::addCoupon($prizes[$winPrizeId]['coupon_id'], $user_info['id'], '1.19大促-PC端', date('Y-m-d H:i:s',$activeStartTime), date('Y-m-d H:i:s',$activeEndTime),$prizes[$winPrizeId]['name']);
					
					if($return){
						$prize = Prizes::find()->where(['id' => $winPrizeId])->one();
						$prize->qty = $prize->qty > 0 ? $prize->qty - 1 : 0;
						$return = $prize->save();
						if($return){
							$prizesUser = new PrizesUser();
							$prizesUser->user_id = $user_info['id'];
							$prizesUser->active_id = $activeId;
							$prizesUser->prize_id = $winPrizeId;
							$prizesUser->times = 1;
							$prizesUser->time_added = time();
							
							$return = $prizesUser->insert();
						} 
					}
					
					if($return){
						$json['status'] = 0;
						$json['message'] = $prizes[$winPrizeId]['name'];
					} else {
						$json['status'] = -1;
						$json['message'] = '服务端错误！';
					}
				} else {
					$json['status'] = -1;
					$json['message'] = '抱歉，红包已经派送完了！';
				}
			}
		}
	
		return json_encode($json);
   	}
   	
   	/**
   	 *
   	 * @Description: 1.25母婴活动页面处理
   	 * @return: 页面渲染结果
   	 * @author: yazhou.miao
   	 * @date: 2016-1-21 上午09:45:46
   	 */
   	public function actionActive125Baby(){
   	
   		// 活动ID
   		$activeId = 125;
   	
   		// 地域限制和时间限制标志
   		$regionFlag = $timeFlag = false;
   	
   		// 参与活动的地区
   		$regions = [4,6,10,11,14,22,24,26,394];
   		$regionFlag = $this->isInActiveRegions($regions);
   	
   		// 参与活动的时间
   		$timeFlag = $this->isInActiveTime('2016-1-25 00:00:00', '2016-02-02 23:59:59');
   	
   		// 特价商品
   		$specialGoods = ActiveModelItems::getActiveGoods($activeId, $this->user_info['city']);
   	
   		// 渲染页面主页面
   		return $this->renderPartial("active125_baby_index", [
   			'specialGoods'=>$specialGoods
   		]);
   	}
   	
   	
   	/**
   	 * @Description:
   	 * @return: return_type
   	 * @author: yazhou.miao
   	 * @date: 2016-1-21 下午5:25:14
   	 */
   	public function actionAjax125Zhuanpan() {
   		// 活动id
   		$activeId = 125;
   		// 前台转盘设置的奖品id,正北方向开始，顺时针排序。
   		$prizeIds = [72,73,74,75,76];
   	
   		// 活动开始时间、活动结束时间
   		$activeStartTime = strtotime('2016-1-25 00:00:00');
   		$activeEndTime = strtotime('2016-02-02 23:59:59');
   		// 参与活动的地区
   		$regions = [4,6,10,11,14,22,24,26,394];
   	
   		// 执行抽奖逻辑
   		$result = $this->runLottery(125, $activeStartTime, $activeEndTime,$regions,'1.25母婴活动');
   	
   		if($result['status'] === 0){
   			$result['message'] = '恭喜您抽中：' . $result['message'];
   				
   			// 确认奖品在转盘中的位置
   			$counter = -1;
   			foreach($prizeIds as $prizeId){
   				$counter++;
   				if($result['prizeId'] === $prizeId){
   					break;
   				}
   			}
   			$result['rotate'] = (360/count($prizeIds)) * $counter;
   		}
   	
   		return json_encode($result);
   	}
   	/**
   	 * @Description: 抽奖逻辑
   	 * @param:
   	 * $activeId :活动ID
   	 * $activeStartTime :活动开始时间(时间戳)
   	 * $activeEndTime: 活动结束时间(时间戳)
   	 * $regions 参加活动的区域 false:不需要限制  array|int:需要限制
   	 * $remark 活动描述 （例如：1.25母婴活动）
   	 * $couponStartTime 红包有效期起始时间(时间戳)
   	 * $couponEndTime 红包有效期终止时间(时间戳)
   	 * $mulipule true：活动期间可多次参加(每天参加一次) false：活动期间只能参加一次   null:不限制参与次数
   	 * $average: true:活动奖品需要每天平均发出 false：活动奖品不需要每天平均发出
   	 * @return: json
   	 * 【status】:抽奖状态,0:抽中;-1:未抽中 【message】:返回消息 【prizeId】:抽取产品ID(没抽到返回-1)
   	 * @author: yazhou.miao
   	 * @date: 2016-1-21 上午09:45:46
   	 */
   	public function runLottery($activeId,$activeStartTime,$activeEndTime,$regions = false,$remark = '',$couponStartTime = null,$couponEndTime = null,$multiple = null,$average = true) {
   		$data = [];

   		$user_info = $this->user_info;

   		// 当天的起始、截止时间
   		$currStartTime = mktime(0,0,0,date('m'),date('d'),date('Y'));
   		$currEndTime   = mktime(23,59,59,date('m'),date('d'),date('Y'));

   		// 1、判断当前是否可以参加活动

   		if (!$this->isInActiveRegions($regions)){
   			$data['status'] = -1;
   			$data['message'] = '抱歉，您所在区域不可参加活动！';
   			$data['prizeId'] = -1;
   		} else {
   			// 判断活动时间
   			$flag = $this->isInActiveTime($activeStartTime, $activeEndTime, true);

   			if(-1 === $flag){
   				$data['status'] = -1;
   				$data['message'] = '活动尚未开始！';
   				$data['prizeId'] = -1;
   			} else if(1 === $flag) {
   				$data['status'] = -1;
   				$data['message'] = '活动已经结束！';
   				$data['prizeId'] = -1;
   			}
   		}

   		// 2、判断用户当天是不是已经参与过抽奖
   		if(self::DEBUG_FLAG || !isset($data['status'])){

   			$winTime = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['active_id' => $activeId])->max('time_added');

   			if($winTime && $multiple===false){
   				// 如果已经参与则不在参与转盘抽奖
   				$data['status'] = -1;
   				$data['message'] = '抱歉，您已参加过此活动！';
   				$data['prizeId'] = -1;
   			} else if($winTime && $multiple && $currStartTime <= $winTime && $winTime <= $currEndTime){
   				// 如果当天已经参与则不在参与转盘抽奖
   				$data['status'] = -1;
   				$data['message'] = '抱歉，此活动每天只能参加一次！';
   				$data['prizeId'] = -1;
   			} else {
   				// 3.抽奖处理
   				$currTime = time();
   				// 活动天数
   				$activeDays = ceil(($activeEndTime - $activeStartTime)/(60*60*24));

   				// 初始化奖品信息
   				$prizesInfo = Prizes::find()->where(['active_id' => $activeId])->andWhere(['>','qty',0])->all();

   				$startNum = 0;

   				foreach($prizesInfo as $prize){
   					// 此产品中奖总次数
   					$winCount = PrizesUser::find()->where(['active_id' => $activeId,'prize_id' => $prize['id']])->count();
   					// 此产品当天中奖次数
   					$winCurrCount = PrizesUser::find()->where(['active_id' => $activeId,'prize_id' => $prize['id']])->andWhere(['>=','time_added',$currStartTime])->andWhere(['<=','time_added',$currEndTime])->count();

   					// 当天中奖次数>=平均每天要中奖的次数，则此产品不会再抽中
   					if($average && ($activeDays > 1) && $winCurrCount >= floor(($winCount + $prize['qty'])/$activeDays)){
   						// 如果中奖次数
   						continue;
   					}
   					// 中奖区间最小随机数
   					$min = $startNum + 1;
   					// 中奖区间最小随机数
   					$max = $startNum + self::BASE_NUMBER * $prize['rate'];

   					$prizes[$prize['id']] = [
   					'id'   => $prize['id'],
   					'min'  => $min,
   					'max'  => $max,
   					'name' => $prize['name'],
   					'coupon_id' => $prize['coupon_id'],
   					];

   					$startNum = $max;
   				}

   				if(isset($prizes)){
   					// 必须抽中一个
   					if(count($prizes) === 1){
   						// 如果只有一个奖，直接抽中该奖品
   						$winPrizeId = key($prizes);
   					} else {
   						// 否则按概率抽奖
   						do{
   							// 中奖的奖品id
   							$winPrizeId = $this->getWinPrizeId($prizes);
   						} while(!$winPrizeId);
   					}

   					// 中奖后的其他处理

   					// 设置红包有效期
   					$startTime = $couponStartTime === null ? $activeStartTime : $couponStartTime;
   					$endTime = $couponEndTime === null ? $activeEndTime : $couponEndTime;
   					$return = CouponUser::addCoupon($prizes[$winPrizeId]['coupon_id'], $user_info['id'], $remark, date('Y-m-d H:i:s',$startTime), date('Y-m-d H:i:s',$endTime),$prizes[$winPrizeId]['name']);

   					if($return){
   						$prize = Prizes::find()->where(['id' => $winPrizeId])->one();
   						$prize->qty = $prize->qty > 0 ? $prize->qty - 1 : 0;
   						$return = $prize->save();
   						if($return){
   							$prizesUser = new PrizesUser();
   							$prizesUser->user_id = $user_info['id'];
   							$prizesUser->active_id = $activeId;
   							$prizesUser->prize_id = $winPrizeId;
   							$prizesUser->times = 1;
   							$prizesUser->time_added = time();

   							$return = $prizesUser->insert();
   						}
   					}

   					if($return){
   						$data['status'] = 0;
   						$data['message'] = $prizes[$winPrizeId]['name'];
   						$data['prizeId'] = $winPrizeId; // 获奖产品id
   					} else {
   						$data['status'] = -1;
   						$data['message'] = '服务端错误！';
   						$data['prizeId'] = -1;
   					}
   				} else {
   					$data['status'] = -1;
   					$data['message'] = '抱歉，没有奖品可抽！';
   					$data['prizeId'] = -1;
   				}
   			}
   		}

   		return $data;
   	}

   	/**
   	 * @Description: 判断用户所在区域（省份、城市）是否可以参加活动
   	 * @param: $regions 可参加活动的区域 （array:多个区域;int:一个区域）
   	 * @return: true:在活动区域 false:在活动区域
   	 * @author: yazhou.miao
   	 * @date: 2016-1-21 下午3:43:25
   	 */
   	public function isInActiveRegions($regions = false) {
   		if($regions === false) return true;
   		
   		if(is_array($regions)){
   			return in_array($this->user_info['province'], $regions) || in_array($this->user_info['city'], $regions);
   		} else if(is_int($regions)){
   			return $this->user_info['province'] === $regions || $this->user_info['city'] === $regions;
   		} else {
   			return false;
   		}
   	}

   	/**
   	 * @Description: 判断当前是不是在活动时间内
   	 * @param: $activeStartTime 活动开始时间
   	 * @param: $activeEndTime 活动结束时间
   	 * @param: $flag true:返回int类型;false：返回boolean类型
   	 * @return:
   	 * int：-1 活动未开始 0 活动进行中 1 活动已结束 ;
   	 * boolean：true 在活动时间内  false 不在活动时间内
   	 * @author: yazhou.miao
   	 * @date: 2016-1-21 下午3:53:54
   	 */
   	public function isInActiveTime($activeStartTime, $activeEndTime, $flag = false) {
   		if(is_int($activeStartTime) && is_int($activeEndTime)){
   			if($flag){
   				if(time() < $activeStartTime){
   					return -1;
   				} else if(time() > $activeEndTime){
   					return 1;
   				} else {
   					return 0;
   				}
   			} else {
   				return time() >= $activeStartTime && time() <= $activeEndTime;
   			}
   		} else if(is_string($activeStartTime) && is_string($activeEndTime)){
   			if($flag){
   				if(time() < strtotime($activeStartTime)){
   					return -1;
   				} else if(time() > strtotime($activeEndTime)){
   					return 1;
   				} else {
   					return 0;
   				}
   			} else {
   				return time() >= strtotime($activeStartTime) && time() <= strtotime($activeEndTime);
   			}
   		} else {
   			return false;
   		}
   	}
   	
   	/**
   	 * @Description: 1.26全品类活动-礼品
   	 * @return: 渲染后页面
   	 * @author: yazhou.miao
   	 * @date: 2016-1-22 下午2:51:08
   	 */
   	public function actionActive126Gift() {
   		// TODO:活动id
   		$giftGoods = ActiveModelItems::getActiveGoods(1264, $this->user_info['city']);
   		return $this->renderPartial('active126_gift',['giftGoods'=>$giftGoods]);
   	}
   	
   	/**
   	 * @Description: 1.26全品类活动-母婴
   	 * @return: 渲染后页面
   	 * @author: yazhou.miao
   	 * @date: 2016-1-22 下午2:53:00
   	 */
   	public function actionActive126Baby() {
   		$specialGoods=ActiveModelItems::getActiveGoods(1262, $this->user_info['city']);
   		return $this->renderPartial('active126_baby',[
                                    'specialGoods'=>$specialGoods,
        ]);
   	}
   	
   	/**
   	 * @Description: 1.26全品类活动-配件
   	 * @return: 渲染后页面
   	 * @author: yazhou.miao
   	 * @date: 2016-1-22 下午2:55:00
   	 */
   	public function actionActive126Parts() {
   		$specialGoods=ActiveModelItems::getActiveGoods(1265, $this->user_info['city']);
   		return $this->renderPartial('active126_parts',[
                                    'specialGoods'=>$specialGoods,
        ]);
   	}
   	
   	/**
   	 * @Description: 1.26全品类活动-小家电
   	 * @return: 渲染后页面
   	 * @author: yazhou.miao
   	 * @date: 2016-1-22 下午2:55:44
   	 */
   	public function actionActive126Appliance() {
        
        $specialGoods=ActiveModelItems::getActiveGoods(1263, $this->user_info['city']);
   		return $this->renderPartial('active126_appliance',[
                                    'specialGoods'=>$specialGoods,
        ]);
   	}
   	
   	/**
   	 * @Description: 1.26全品类活动-智能机
   	 * @return: return_type
   	 * @author: yazhou.miao
   	 * @date: 2016-1-22 下午2:57:13
   	 */
   	public function actionActive126Smartphone() {
   		$specialGoods=ActiveModelItems::getActiveGoods(1261, $this->user_info['city']);
   		return $this->renderPartial('active126_smartphone',[
                                    'specialGoods'=>$specialGoods,
        ]);
   	}
   	
   	/**
   	 * @author jiangtao.ren
   	 * @return string
   	 * @desc 216母婴活动页
   	 */
   	public function actionActive216Baby(){
   	
   		// 活动ID
   		$day = (int)date('d');
   		$month = (int)date('m');
   		$activeId = $month*$day*10+1;
   	
   		// 特价商品
   		$specialGoods = ActiveModelItems::getActiveGoods($activeId, $this->user_info['city']);
   	
   		// 渲染页面主页面
   		return $this->renderPartial("active216_baby_index", [
   				'specialGoods'=>$specialGoods
   		]);
   	}
   	
   	/**
   	 * @Description:
   	 * @return: return_type
   	 * @author: yazhou.miao
   	 * @date: 2016-1-21 下午5:25:14
   	 */
   	public function actionAjax216Zhuanpan() {
   		// 活动id
   		$activeId = 216;
   		// 前台转盘设置的奖品id,正北方向开始，顺时针排序。
   		$prizeIds = [77,78,79,80,81];
   	
   		// 活动开始时间、活动结束时间
   		$activeStartTime = strtotime('2016-2-17 09:00:00');
   		$activeEndTime = strtotime('2016-02-29 23:59:59');
   		// 参与活动的地区
   		$regions = [4,6,10,11,14,22,24,26,394];
   	
   		// 执行抽奖逻辑
   		$currStartTime = mktime(0,0,0,date('m'),date('d'),date('Y'));
		$currEndTime   = mktime(23,59,59,date('m'),date('d'),date('Y'));
		
   		$result = $this->runLottery($activeId, $activeStartTime, $activeEndTime,$regions,'2.17猴年母婴活动',$currStartTime,$currEndTime);

   		if($result['status'] === 0){
   			$result['message'] = '恭喜您抽中：' . $result['message'];

   			// 确认奖品在转盘中的位置
   			$counter = -1;
   			foreach($prizeIds as $prizeId){
   				$counter++;
   				if($result['prizeId'] === $prizeId){
   					break;
   				}
   			}
   			$result['rotate'] = (360/count($prizeIds)) * $counter;
   		}

   		return json_encode($result);
   	}

   	/**
   	 * @Description: 2.17本省母婴活动
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-2-16 下午5:38:08
   	 */
   	public function actionActive217Baby() {
   		// TODO:活动ID待定
   		// 倍康产品
   		$bakenGoods = ActiveModelItems::getActiveGoods(3401, $this->user_info['city']);

   		// 好奇产品
   		$haoqiGoods = ActiveModelItems::getActiveGoods(3402, $this->user_info['city']);

   		// 渲染页面主页面
   		return $this->renderPartial("active217_baby_index", [
   			'bakenGoods' => $bakenGoods,
   			'haoqiGoods' => $haoqiGoods,
   		]);
   	}

   	/**
   	 * @desc 模板活动
   	 */
   	public function actionTemplateActive(){
   		$modelId = f_get('model_id',0);
   		$activeId = ActiveModel::find()->select('active_id')->where(['id'=>$modelId])->asArray()->scalar();
   		$pageStyle = ActivityPageStyle::getPageStyleByModel($modelId);
                if(!empty($activeId)){
        $templateGoods = ActiveModelItems::getActiveGoods($activeId, $this->user_info['city']);
                }else{
                   return $this->redirect('/home/index'); 
                }
        if ($pageStyle['activity_template_id'] == 1){
        	return $this->renderPartial('template-active/template1',[
        			'pageStyle'=>$pageStyle,
        			'templateGoods' => $templateGoods,
        	]);
        }elseif ($pageStyle['activity_template_id'] == 2){
        	return $this->renderPartial('template-active/template2',[
        			'pageStyle'=>$pageStyle,
        			'templateGoods' => $templateGoods,
        	]);
        }elseif ($pageStyle['activity_template_id'] == 3){
        	return $this->renderPartial('template-active/template3',[
        			'pageStyle'=>$pageStyle,
        			'templateGoods' => $templateGoods,
        	]);
        }
   	}
   	
   	/**
   	 * @Description: 2.24预热抽红包活动
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-2-22 下午3:38:08
   	 */
   	public function actionActive224Yure(){
   	
   		// 渲染页面活动主页面
   		return $this->renderPartial("active224_yure_index");
   	}
   	
   	/**
   	 * @Description: 2.24预热抽红包活动
   	 * @return: 抽奖结果
   	 * @author: yazhou.miao
   	 * @date: 2016-2-22 下午3:38:08
   	 */
   	public function actionAjax224Zhuanpan() {
   		// 活动id
   		$activeId = 224;
   		// 前台转盘设置的奖品id,正北方向开始，顺时针排序。
   		$prizeIds = [82,83,84,85,86,87];
   	
   		// 活动开始时间、活动结束时间
   		$activeStartTime = strtotime('2016-2-24 00:00:00');
   		$activeEndTime = strtotime('2016-02-25 23:59:59');
   		
   		// 参与活动的地区
   		$regions = [3,4,6,10,11,13,14,16,17,22,24,26,31,394];
   	
   		// 红包使用时间
   		$startTime = strtotime('2016-02-26 00:00:00');
   		$endTime   = strtotime('2016-02-26 23:59:59');
   	
   		$result = $this->runLottery($activeId, $activeStartTime, $activeEndTime,$regions,'2.26开年中促预热活动',$startTime,$endTime,false,false);
   	
   		if($result['status'] === 0){
   			$result['message'] = '恭喜您抽中：' . $result['message'];
   	
   			// 确认奖品在转盘中的位置
   			$counter = -1;
   			foreach($prizeIds as $prizeId){
   				$counter++;
   				if($result['prizeId'] === $prizeId){
   					break;
   				}
   			}
   			$result['rotate'] = (360/count($prizeIds)) * $counter;
   		}

   		return json_encode($result);
   	}

   	/**
   	 * @Description: 2.26活动-IT商城
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-2-24 下午2:35:14
   	 */
   	public function actionActive226It() {
   		$timeLimit = date('H:i:s');

   		// 秒杀产品
   		// 11点秒杀
   		$seckillGoods1 = ActiveModelItems::getActiveGoods($this->activeId4, $this->user_info['city']);
   		// 16点秒杀
   		$seckillGoods2 = ActiveModelItems::getActiveGoods($this->activeId9, $this->user_info['city']);

   		// 特价产品
   		$specialGoods = ActiveModelItems::getActiveGoods($this->activeId5, $this->user_info['city']);

   		return $this->renderPartial('active226_it',[
   			'seckillGoods1' => $seckillGoods1,
   			'seckillGoods2' => $seckillGoods2,
   			'specialGoods' => $specialGoods,
   			'timeLimit'    => $timeLimit,
   		]);
   	}

   	/**
   	 * @Description: 2.26活动-家电生活
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-2-24 下午2:35:14
   	 */
   	public function actionActive226Home() {
   		$timeLimit = date('H:i:s');

   		// 秒杀产品
   		// 11点秒杀
   		$seckillGoods1 = ActiveModelItems::getActiveGoods($this->activeId7, $this->user_info['city']);
   		// 14点秒杀
   		$seckillGoods2 = ActiveModelItems::getActiveGoods($this->activeId11, $this->user_info['city']);
   		// 16点秒杀
   		$seckillGoods3 = ActiveModelItems::getActiveGoods($this->activeId12, $this->user_info['city']);
   		// 特价产品
   		$specialGoods = ActiveModelItems::getActiveGoods($this->activeId8, $this->user_info['city']);

   		return $this->renderPartial('active226_home',[
   			'seckillGoods1' => $seckillGoods1,
   			'seckillGoods2' => $seckillGoods2,
   			'seckillGoods3' => $seckillGoods3,
   			'specialGoods'  => $specialGoods,
   			'timeLimit'     => $timeLimit,
   		]);
   	}

   	/**
   	 * @Description: 2.26活动-母婴
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-2-24 下午2:35:14
   	 */
   	public function actionActive226Baby() {
   		// TODO:活动ID待定
   		$timeLimit = date('H:i:s');
   		// 秒杀产品
   		$seckillGoods1 = ActiveModelItems::getActiveGoods($this->activeId6, $this->user_info['city']);

   		$seckillGoods2 = ActiveModelItems::getActiveGoods($this->activeId10, $this->user_info['city']);
   		
   		return $this->renderPartial('active226_baby',[
   				'seckillGoods1' => $seckillGoods1,
   				'seckillGoods2' => $seckillGoods2,
   				'timeLimit'    => $timeLimit,
   		]);
   	}

   	/**
   	 * @Description: 2.26活动-通讯商城
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-2-24 下午2:35:14
   	 */
   	public function actionActive226Phone() {
   		// TODO:活动ID待定
   		// 功能机特价产品
        $machineGoods = ActiveModelItems::getActiveGoods($this->activeId1, $this->user_info['city']);
        // 智能机特价产品
        $smartphoneGoods = ActiveModelItems::getActiveGoods($this->activeId2, $this->user_info['city']);
        // 配件产品
        $partsGoods = ActiveModelItems::getActiveGoods($this->activeId3, $this->user_info['city']);

   		return $this->renderPartial('active226_tx',[
            'machineGoods' => $machineGoods,
            'smartphoneGoods' => $smartphoneGoods,
            'partsGoods' => $partsGoods,
        ]);

    }

    /**
   	 * @Description: 2.26活动-通讯商城
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-2-25 下午5:35:14
   	 */
   	public function actionActive301Baby() {
   		// TODO:活动ID待定
        //好奇
        $huggiesGoods = ActiveModelItems::getActiveGoods(3011, $this->user_info['city']);
        //贝亲
        $pigeonGoods = ActiveModelItems::getActiveGoods(3012, $this->user_info['city']);
       //特价
        $specialGoods = ActiveModelItems::getActiveGoods(3013, $this->user_info['city']);

   		return $this->renderPartial('active301_baby',[
            'huggiesGoods' => $huggiesGoods,
            'pigeonGoods' => $pigeonGoods,
            'specialGoods' => $specialGoods,
   		]);

   	}

   	 /**
   	 * @Description: 3.07 活动
   	 * @return: 渲染页面
   	 * @author: 
   	 * @date: 2016-2-25 下午5:35:14
   	 */
   	public function actionActive307Tx() {
    	// 活动ID
    	$active1 = 3071; // 智能机
    	$active2 = 3072; // 功能机
    	$active3 = 3073; // 配件
    	 
    	// 移动用户
    	if ($this->user_info['user_group'] == 1) {
    		$active1 += 10;
    		$active2 += 10;
    		$active3 += 10;
    	}
    	
    	//智能机
    	$smartphoneGoods = ActiveModelItems::getActiveGoods($active1, $this->user_info['city']);
    	//功能机
    	$machineGoods = ActiveModelItems::getActiveGoods($active2, $this->user_info['city']);
    	//配件
    	$partsGoods = ActiveModelItems::getActiveGoods($active3, $this->user_info['city']);
    	
    	return $this->renderPartial('active307_tx',[
    		'smartphoneGoods' => $smartphoneGoods,
    		'machineGoods' => $machineGoods,
    		'partsGoods' => $partsGoods,
    	]);
    }
    
    /**
     * @Description: 3.12活动(3.15预热活动)
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-3-7 下午3:09:29
     */
    public function actionActive312() {
    	// 排行榜
    	$rankPage = $this->getScoreRank();
    	
    	// 初期化显示提示语的标识
    	$flag = false;
    	$result = PrizesUser::find()->where(['user_id'=>$this->user_info['id'],'active_id'=>31200])->one();
    	($result && $result['times']>=3) && $flag = true;
    	
    	return $this->renderPartial('active312_index',[
    		'rankPage' => $rankPage,
    		'flag' => $flag,
		]);
    }
    
    protected function getScoreRank() {
    	$activeId = 31200;
    	
    	$rankList = f_c('hit-mouse-rank');
    	
    	if(!$rankList){
    		// 获取前20名
    		$query = (new Query())->select('pu.spare AS score,um.user_name AS userName')->from('{{other_prizes_user}} AS pu')
    		->leftJoin('{{user_member}} AS um','pu.user_id = um.id')
    		->where(['pu.active_id' => $activeId])
    		->orderBy('(pu.spare + 0) DESC') // varchar转int后排序
    		->limit(20);
    		 
    		$rankList = $query->createCommand(Yii::$app->get('db'))->queryAll();
    		f_c('hit-mouse-rank',$rankList,60); //失效时间1分钟
    	}
    	
    	return $this->renderPartial('active312_rank_list',['rankList' => $rankList]);
    }
    
    /**
     * @Description: 处理打地鼠请求
     * @return: json
     * @author: yazhou.miao
     * @date: 2016-3-8 下午4:50:23
     */
    public function actionAjaxHitMouse() {
    	$json = [];
    	$activeId = 31200;  // 记录打地鼠次数时使用
    	$activeIdLottery = 312; // 记录红包发送情况时使用
    	
    	// 活动时间 TODO:
    	$activeStartTime = strtotime("2016-03-12 00:00:00"); // TODO:开始时间
    	$activeEndTime  = strtotime("2016-03-14 23:59:59");
    	// 红包使用时间
    	$couponStartTime = strtotime("2016-03-15 00:00:00");
    	$couponEndTime = strtotime("2016-03-15 23:59:59");
    	
    	// 获取排名页面
    	if(f_post('type')=='list'){
    		$json['status'] = 0;
    		$json['msg'] = $this->getScoreRank();
    		 
    		echo json_encode($json);
    		exit;
    	}
    	// 活动时间判断
    	$flag = $this->isInActiveTime($activeStartTime, $activeEndTime,true);
    	if($flag === -1){
    		$json['status'] = -1;
    		$json['msg'] = '活动尚未开始！';
    	} else if($flag === 1){
    		$json[status] = -1;
    		$json['msg'] = '活动已经结束！';
    	}
    	
    	if($flag != 0){ // 不是活动时间，返回json结果
    		echo json_encode($json);
    		exit;
    	}
    	
    	$now = time();
    	$score = f_post('score'); // 得分
    	
    	$result = PrizesUser::findOne(['user_id' => $this->user_info['id'], 'active_id' => $activeId]);
    	
    	$result = $result ? $result : new PrizesUser();
    	
    	is_null($result->times) && $result->times = 0;
    	is_null($result->spare) && $result->spare = 0;
    	
    	if($result->times <= 3) {
    		if(is_null($score)) {
    			// 打地鼠开始前发送的请求
    			if($result->times == 3 && !UserMember::getLotteryByApi()){
    				$json['status'] = -1;
    				$json['msg'] = '去“51预存宝”一次性存入超过500元，还可获得1次游戏机会！';
    			} else {
    				$json['status'] = 0;
    				$json['msg'] = '游戏马上开始！';
    			}
    		} else {
    			// 打地鼠结束后发送的请求
    			$result->user_id = $this->user_info['id'];
    			$result->active_id = $activeId;
    			$result->times = $result->times + 1;
    			$result->time_added = time();
    			// 得分比之前的大则存入数据库
    			((int)$score > (int)$result->spare) && $result->spare = $score;
    			 
    			($result->times < 3) && $result->prize_id = -1;
    			 
    			// 如果是第三次打地鼠，则随机发个红包
    			if($result->times == 3){
    				// 发送红包
    				$lottery = $this->runLottery($activeIdLottery, $activeStartTime, $activeEndTime, false, '3.15预热活动',$couponStartTime,$couponEndTime,false,false);
    				
    				$result->prize_id = $lottery['prizeId']; // 设定奖品ID
    				
    				$json['status'] = $lottery['status'];
    				$json['msg'] = $lottery['status']===0  ? '恭喜您获得：' . $lottery['message'] . '!' : '3次游戏已结束，最终您没有获奖！';
    			} else {
    				$times = 3-$result->times;
    				if ($times < 0) {
    					$times = 0;
    				}
    				$json['status'] = 0;
    				$json['msg'] = '本次游戏结束，您还有' . $times . '次游戏机会！';
    			}
    			 
    			if(!$result->save()){
    				$json['status'] = -1;
    				$json['msg'] = '系统内部错误！';
    			}
    		}
    	} else {
    		$json['status'] = -1;
    		$json['msg'] = '您已经参加过打地鼠活动！';
    	}
    	
    	echo json_encode($json);
    	exit;
    }

    /**
     * @Description: 315 --51超市
     * @return: return_type
     * @author: yan.zhang
     * @date: 2016-3-9 上午10:30:23
     */
    public function actionActive315Shop(){
    	
    	$timeLimit = date('H:i:s');
    	// 活动ID
    	$day = (int)date('d');
    	$month = (int)date('m');
    	$activeId1 = $month*$day*100+1;
    	$activeId2 = $month*$day*100+2;
    	$activeId3 = $month*$day*100+3;
    	$activeId4 = $month*$day*100+4;
    	// 秒杀
   		$seckillGoods1 = ActiveModelItems::getActiveGoods($activeId1, $this->user_info['city']);
   		// 10点秒杀
   		$seckillGoods2 = ActiveModelItems::getActiveGoods($activeId2, $this->user_info['city']);
   		// 14点秒杀
   		$seckillGoods3 = ActiveModelItems::getActiveGoods($activeId3, $this->user_info['city']);
   		// 16点秒杀
   		$seckillGoods4 = ActiveModelItems::getActiveGoods($activeId4, $this->user_info['city']);

    	$specialGoods = ActiveModelItems::getActiveGoods(3146, $this->user_info['city']);
    	return $this->renderPartial('active315_shop',[
                      'seckillGoods1'=>$seckillGoods1,
                      'seckillGoods2'=>$seckillGoods2,
                      'seckillGoods3'=>$seckillGoods3,
                      'seckillGoods4'=>$seckillGoods4,
                      'specialGoods'=>$specialGoods,
   			          'timeLimit'     => $timeLimit,
    		]);
     }

     /**
   	 * @Description:315 51超市--挣便宜
   	 * @return: return_type
   	 * @author: yan.zhang
   	 * @date: 2016-3-10 上午10:02:14
   	 */
   	public function actionAjax315Zhuanpan() {
   		// 活动id
   		$activeId = 315;
   		// 前台转盘设置的奖品id,正北方向开始，顺时针排序。
   		$prizeIds = [89,90,91,92,93,94];

   		// 活动开始时间、活动结束时间
   		$activeStartTime = strtotime('2016-03-15 00:00:00');
   		$activeEndTime = strtotime('2016-03-31 23:59:59');
   		
   		// 执行抽奖逻辑
   		$couponStartTime = time();
		$couponEndTime   = strtotime('+7 days');
		$nowTime = date('Y-m-d H:i:s');
		if ($nowTime > '2016-03-16 00:00:00' && $this->user_info['reg_time'] < '2016-03-06 23:59:59'){
			$result['status'] = -1;
			$result['message'] = '无权限抽奖';
			return json_encode($result);
		}
   		$result = $this->runLottery($activeId, $activeStartTime, $activeEndTime,false,'3.15-51超市挣便宜',$couponStartTime,$couponEndTime,false,false);

   		if($result['status'] === 0){
   			$result['message'] = '恭喜您抽中：' . $result['message'];

   			// 确认奖品在转盘中的位置
   			$counter = -1;
   			foreach($prizeIds as $prizeId){
   				$counter++;
   				if($result['prizeId'] === $prizeId){
   					break;
   				}
   			}
   			$result['rotate'] = (360/count($prizeIds)) * $counter;
   		}

   		return json_encode($result);
   	}

   	 /**
   	 * @Description: 3.15活动-母婴商城
   	 * @return: 渲染页面
   	 * @author: yan.zhang
   	 * @date: 2016-3-14 上午9:25:14
   	 */
   	public function actionActive315Baby() {
   		$timeLimit=date('H:i:s');
   		// TODO:活动ID待定
        //00:00秒杀
        $seckillGoods1 = ActiveModelItems::getActiveGoods(3138, $this->user_info['city']);
        //10:00秒杀
        $seckillGoods2 = ActiveModelItems::getActiveGoods(3139, $this->user_info['city']);
       //14：00秒杀
       $seckillGoods3 = ActiveModelItems::getActiveGoods(3140, $this->user_info['city']); 
       //16：00秒杀
       $seckillGoods4 = ActiveModelItems::getActiveGoods(3141, $this->user_info['city']);
       //特价
        $specialGoods = ActiveModelItems::getActiveGoods(3142, $this->user_info['city']);

   		return $this->renderPartial('active315_baby',[
            'seckillGoods1' => $seckillGoods1,
            'seckillGoods2' => $seckillGoods2,
            'seckillGoods3' => $seckillGoods3,
            'seckillGoods4' => $seckillGoods4,
            'specialGoods' => $specialGoods,
            'timeLimit'   =>$timeLimit,
   		]);
   	}
   	 	 /**
   	 * @Description: 3.15活动-家电商城
   	 * @return: 渲染页面
   	 * @author: yan.zhang
   	 * @date: 2016-3-14 上午9:25:14
   	 */
   	public function actionActive315Home() {
   		$timeLimit=date('H:i:s');
   		// TODO:活动ID待定
        //00:00秒杀
        $seckillGoods1 = ActiveModelItems::getActiveGoods(3133, $this->user_info['city']);
        //10:00秒杀
        $seckillGoods2 = ActiveModelItems::getActiveGoods(3134, $this->user_info['city']);
       //14：00秒杀
       $seckillGoods3 = ActiveModelItems::getActiveGoods(3135, $this->user_info['city']); 
       //16：00秒杀
       $seckillGoods4 = ActiveModelItems::getActiveGoods(3136, $this->user_info['city']);
       //特价
        $specialGoods = ActiveModelItems::getActiveGoods(3137, $this->user_info['city']);

   		return $this->renderPartial('active315_home',[
            'seckillGoods1' => $seckillGoods1,
            'seckillGoods2' => $seckillGoods2,
            'seckillGoods3' => $seckillGoods3,
            'seckillGoods4' => $seckillGoods4,
            'specialGoods' => $specialGoods,
            'timeLimit'   =>$timeLimit,
   		]);
   	}
   	 	 /**
   	 * @Description: 3.15活动-通讯商城
   	 * @return: 渲染页面
   	 * @author: yan.zhang
   	 * @date: 2016-3-14 上午9:25:14
   	 */
   	public function actionActive315Tx() {
   		$timeLimit=date('H:i:s');
   		// 活动ID
   		$active1 = 3121; // 智能机
   		$active2 = 3122; // 功能机
   		$active3 = 3123; // 配件
   		$active4 = 3124;
   		$active5 = 3125;
   		$active6 = 3126;
   		$active7 = 3127;
   		// 移动用户
   		if ($this->user_info['user_group'] == 1) {
   			$active1 += 100;
   			$active2 += 100;
   			$active3 += 100;
   			$active4 += 100;
   			$active5 += 100;
   			$active6 += 100;
   			$active7 += 100;
   		}
   		 
   		// TODO:活动ID待定
        //00:00秒杀
        $seckillGoods1 = ActiveModelItems::getActiveGoods($active1, $this->user_info['city']);
        //10:00秒杀
        $seckillGoods2 = ActiveModelItems::getActiveGoods($active2, $this->user_info['city']);
       //14：00秒杀
       $seckillGoods3 = ActiveModelItems::getActiveGoods($active3, $this->user_info['city']); 
       //16：00秒杀
       $seckillGoods4 = ActiveModelItems::getActiveGoods($active4, $this->user_info['city']);
       //智能机
    	$smartphoneGoods = ActiveModelItems::getActiveGoods($active5, $this->user_info['city']);
    	//功能机
    	$machineGoods = ActiveModelItems::getActiveGoods($active6, $this->user_info['city']);
    	//配件
    	$partsGoods = ActiveModelItems::getActiveGoods($active7, $this->user_info['city']);

   		return $this->renderPartial('active315_tx',[
            'seckillGoods1' => $seckillGoods1,
            'seckillGoods2' => $seckillGoods2,
            'seckillGoods3' => $seckillGoods3,
            'seckillGoods4' => $seckillGoods4,
            'smartphoneGoods' => $smartphoneGoods,
            'machineGoods' => $machineGoods,
            'partsGoods' => $partsGoods,
            'timeLimit'   =>$timeLimit,
   		]);
   	}
   	 	 /**
   	 * @Description: 3.15活动-IT商城
   	 * @return: 渲染页面
   	 * @author: yan.zhang
   	 * @date: 2016-3-14 上午9:25:14
   	 */
   	public function actionActive315It() {
   		$timeLimit=date('H:i:s');
   		// TODO:活动ID待定
        //00:00秒杀
        $seckillGoods1 = ActiveModelItems::getActiveGoods(3128, $this->user_info['city']);
        //10:00秒杀
        $seckillGoods2 = ActiveModelItems::getActiveGoods(3129, $this->user_info['city']);
       //14：00秒杀
       $seckillGoods3 = ActiveModelItems::getActiveGoods(3130, $this->user_info['city']); 
       //16：00秒杀
       $seckillGoods4 = ActiveModelItems::getActiveGoods(3131, $this->user_info['city']);
       //特价
        $specialGoods = ActiveModelItems::getActiveGoods(3132, $this->user_info['city']);

   		return $this->renderPartial('active315_it',[
            'seckillGoods1' => $seckillGoods1,
            'seckillGoods2' => $seckillGoods2,
            'seckillGoods3' => $seckillGoods3,
            'seckillGoods4' => $seckillGoods4,
            'specialGoods' => $specialGoods,
            'timeLimit'   =>$timeLimit,
   		]);
   	}
    /**
   	 * @Description: 3.18活动-通讯商城
   	 * @return: 渲染页面
   	 * @author: yan.zhang
   	 * @date: 2016-3-17 上午9:25:14
   	 */
   	public function actionActive318Tx() {
   		
   		// 活动ID
   		$active1 = 3181; // 智能机
   		$active2 = 3182; // 功能机
   		$active3 = 3183; // 配件
   		// 移动用户
   		if ($this->user_info['user_group'] == 1) {
   			$active1 += 100;
   			$active2 += 100;
   			$active3 += 100;
   		}
       //智能机
    	$smartphoneGoods = ActiveModelItems::getActiveGoods($active1, $this->user_info['city']);
    	//功能机
    	$machineGoods = ActiveModelItems::getActiveGoods($active2, $this->user_info['city']);
    	//配件
    	$partsGoods = ActiveModelItems::getActiveGoods($active3, $this->user_info['city']);

   		return $this->renderPartial('active318_tx',[
				            'smartphoneGoods' => $smartphoneGoods,
				            'machineGoods' => $machineGoods,
				            'partsGoods' => $partsGoods,
   		  ]);
   	}
    /**
   	 * @Description: 3.22活动-通讯商城
   	 * @return: 渲染页面
   	 * @author: yan.zhang
   	 * @date: 2016-3-22 上午9:25:14
   	 */
   	public function actionActive322Tx() {
   		//活动id
   		$day1 = (int)date('d');
   		$month1 = (int)date('m');
   		$day2 = (int)date("d",strtotime("+1 day"));
   		$month2 = (int)date("m",strtotime("+1 day"));
   		$activeId1 = $month1*$day1*1000+1;
   		$activeId2 = $month2*$day2*1000+1;
        //当天10点秒杀
    	$seckillGoods1= ActiveModelItems::getActiveGoods($activeId1, $this->user_info['city']);
    	//明天10点秒杀
    	$seckillGoods2= ActiveModelItems::getActiveGoods($activeId2, $this->user_info['city']);
   		return $this->renderPartial('active322_tx',[
				            'seckillGoods1' => $seckillGoods1,
				            'seckillGoods2' => $seckillGoods2,
   		  ]);
   	}
   /**
   	 * @Description: 3.25活动-母婴商城
   	 * @return: 渲染页面
   	 * @author: yan.zhang
   	 * @date: 2016-3-22 上午9:25:14
   	 */
   	public function actionActive325Baby() {
   		//尿不湿精选
    	$diapersGoods= ActiveModelItems::getActiveGoods(32501, $this->user_info['city']);
        //好货精选
    	$cargoGoods= ActiveModelItems::getActiveGoods(32502, $this->user_info['city']);
    	//宝贝踏青有我护航
    	$outingGoods= ActiveModelItems::getActiveGoods(32503, $this->user_info['city']);
   		return $this->renderPartial('active325_baby',[
				            'diapersGoods' => $diapersGoods,
				            'cargoGoods' => $cargoGoods,
				            'outingGoods' => $outingGoods,
   		  ]);
   	}
}