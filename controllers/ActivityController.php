<?php
namespace frontend\controllers;

use common\components\CommonInterface;
use frontend\components\Controller2016;
use Yii;
use common\models\other\ActiveModelItems;
use common\models\other\PrizesUser;
use common\models\other\Prizes;
use common\models\markting\CouponUser;
use common\models\user\UserMember;
use common\models\other\Activity;
use common\models\other\ActivityType;
use common\models\user\StoreMall;
use common\models\other\CityFunctionManage;
use common\models\markting\CouponInfo;
use common\models\other\ActivityCity;
use common\models\other\BargainInfo;
use common\models\other\BargainUser;
use common\models\other\ActiveModelItemsWy;
use common\models\goods\DepotCity;
use common\models\other\PrizesOrder;
use yii\db\Query;
use common\models\other\ActivityQuestion;
use common\models\other\MedalRank;

class ActivityController extends Controller2016{
	// 基数: 一百万(抽奖用)
	const BASE_NUMBER = 1000000;

	// debug flag(测试用)
	const DEBUG = true;
	
	// 页面缓存时间
	const CACHE_EXPIRE = 3600;

    //统计活动页pv-uv量   code :wmc
    public function getStatUrlParams(){
        return [
            'actionIndex'=>['id']
        ] ;
    }

	/**
	 *
	 * @Description: PC端活动页面
	 * @param $id 活动ID
	 * @return: 活动页面
	 * @author: yazhou.miao
	 * @date: 2016-3-23 上午10:05:46
	 */
	public function actionIndex(){
		// 活动ID
		$id = f_get('id',0);
		
		// 有效性检测
		$result = $this->validCheck($id,true);
		if((!isset($result['code'])) || ($result['code'] == 1) || (!$result['status'] && $result['code'] == 0)) { // 过期活动无法进入
			return $this->renderPartial('error',['msg'=>$result['message']]);
		}
		
		// 活动设置
 		$activity = Activity::find()->where(['id'=>$id])->one();
		
		$config = unserialize($activity->setting);
		if(!isset($config['pc']))
			return $this->renderPartial('error',['msg'=>'抱歉，找不到活动设置！']);
		
		// 使用页面缓存（缓存key:'activity-pc'+活动ID+用户省份,注意：debug模式不使用缓存）
		$page = f_c('activity-pc-'.$id.'-'.$this->user_info['province']);
		if($page&&!self::DEBUG) return $page;
		
		// 生成页面
		$setting = $config['pc'];
		$setting['title'] = $activity->title;
		
		$floors = isset($config['pc_floor']) ? $config['pc_floor'] : [];
		
		// 活动楼层
		$floorView = [];
		foreach($floors as $key => $floor){
			// 楼层ID
			$floorId = 'floor-' . ($key+1);
			// 活动类型
			$floorView[] = $this->getFloorView($floor,$activity->id,$floorId);
		}
		
		$page = $this->renderPartial('index',[
			'setting'   =>  $setting, // 设置
			'floorView' => $floorView, // 楼层视图
			'startTime' => $activity->start_time, //活动开始时间
			'endTime'   => $activity->end_time, //活动截止时间
		]);
		// 存到缓存（有效期一个小时）
		f_c('activity-pc-'.$id.'-'.$this->user_info['province'],$page,self::CACHE_EXPIRE);
		
		return $page;
	}
	
	/**
	 * @desc 文印活动页面
	 * @return :|unknown
	 */
	public function actionWy(){
		// 活动ID
		$id = f_get('id',0);
	
		// 有效性检测
		$result = $this->validCheck($id,true);
		if(($result['code'] == 1) || (!$result['status'] && $result['code'] == 0)) { // 过期活动无法进入
			return $this->renderPartial('error',['msg'=>$result['message']]);
		}
	
		// 活动设置
		$activity = Activity::find()->where(['id'=>$id])->one();
	
		$config = unserialize($activity->setting);
		if(!isset($config['pc']))
			return $this->renderPartial('error',['msg'=>'抱歉，找不到活动设置！']);
	
		// 使用页面缓存（缓存key:'activity-pc'+活动ID+用户省份,注意：debug模式不使用缓存）
		$page = f_c('activity-pc-'.$id.'-'.$this->user_info['province']);
		if($page&&!self::DEBUG) return $page;
	
		// 生成页面
		$setting = $config['pc'];
		$setting['title'] = $activity->title;
	
		$floors = isset($config['pc_floor']) ? $config['pc_floor'] : [];
	
		// 活动楼层
		$floorView = [];
		foreach($floors as $key => $floor){
			// 楼层ID
			$floorId = 'floor-' . ($key+1);
			// 活动类型
			$floorView[] = $this->getFloorViewWy($floor,$activity->id,$floorId);
		}
	
		$page = $this->renderPartial('index',[
				'setting'   =>  $setting, // 设置
				'floorView' => $floorView, // 楼层视图
				'startTime' => $activity->start_time, //活动开始时间
				'endTime'   => $activity->end_time, //活动截止时间
		]);
		// 存到缓存（有效期一个小时）
		f_c('activity-pc-'.$id.'-'.$this->user_info['province'],$page,self::CACHE_EXPIRE);
	
		return $page;
	}
	
	/**
	 * @Description: 删除某活动的所有页面缓存
	 * @return: return_type
	 * @author: yazhou.miao
	 * @date: 2016-3-26 下午11:37:46
	 */
	public function actionClear() {
		$id = f_get('id');
		if($id === null) return '请指定活动ID!';
		// 获取所有省份ID
		$provinces = CityFunctionManage::find()->select('DISTINCT(province)')->where(['if_goods'=>1])->orderBy('province')->asArray()->all();
		
		foreach($provinces as $value){
			// 删除缓存
			f_c('activity-pc-'.$id.'-'.$value['province'],false);
		}
		
		return "缓存已经清除！";
	}

	/**
	 * @Description: 获取楼层视图
	 * @param: $floor 楼层配置
	 * @param: $activityId 活动ID
	 * @param: $floorId 楼层ID
	 * @return: 楼层视图
	 * @author: yazhou.miao
	 * @date: 2016-3-21 下午7:58:30
	 */
	protected function getFloorView($floor,$activityId,$floorId) {
	
		$acType = ActivityType::findOne(['id'=>$floor['activity_type'],'status'=>1]);

		if($acType && !is_null($floor)){
			// 特价活动
			if(strtolower($acType->ename) == 'special' && ($floor['mobile_station'] == '2' || $this->user_info['user_group'] == $floor['mobile_station'])) {
				// 活动产品
				$products = empty($floor['products_type_id']) ? [] : ActiveModelItems::getActiveGoods($floor['products_type_id'], $this->user_info['city']);
				
				// 模板ID
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
				
				return $this->renderPartial('activity-type/special/template'.$templateId,[
					'products' => $products,
					'setting'  => $floor,
					'floorId'  => $floorId,
				]);
			}
			
			// 秒杀楼层
			if(strtolower($acType->ename) == 'seckill' && ($floor['mobile_station'] == '2' || $this->user_info['user_group'] == $floor['mobile_station'])) {
				$seckills = [];
				foreach($floor['seckill_event'] as $event){
					$seckills[] = [
						'time' => $event['time'], // 秒杀时间
						'products' => ActiveModelItems::getActiveGoods($event['products_type_id'], $this->user_info['city']), // 秒杀产品
					]; 
				}
				
				// 模板ID
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
				
				return $this->renderPartial('activity-type/seckill/template'.$templateId,[
					'seckills' => $seckills,
					'setting'  => $floor,
					'floorId'  => $floorId,
				]);
			}
			
			// 外部链接楼层
			if(strtolower($acType->ename) == 'links') {
				// 模板ID
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
			
				return $this->renderPartial('activity-type/links/template'.$templateId,[
					'setting'  => $floor,
					'floorId'  => $floorId,
				]);
			}
			
			// 转盘楼层
			if(strtolower($acType->ename) == 'zhuanpan') {
				// 模板ID
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
				
				return $this->renderPartial('activity-type/zhuanpan/template'.$templateId,[
					'setting'    => $floor,
					'activityId' => $activityId,
					'floorId'  => $floorId,
				]);;
			}
			
			// 图片展示楼层
			if(strtolower($acType->ename) == 'picture') {
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
				
				return $this->renderPartial('activity-type/picture/template'.$templateId,[
					'setting'    => $floor,
					'activityId' => $activityId,
					'floorId'    => $floorId,
				]);
			}
			
			// 其他楼层
			if(strtolower($acType->ename) == 'other') {
				$code = '';
				// 活动代码
				if(strpos($floor['code'],'-')!==false){
					$names = explode('-', trim($floor['code']));
					foreach($names as $name){
						$code .= ucfirst($name);
					}
				} else {
					$code = ucfirst(trim($floor['code']));
				}
				
				$methodName = 'get'.$code;
				if(method_exists($this, $methodName)){
					return $this->$methodName($floorId,$activityId);
				}
				
				return '';
			}
		}
	
		return '';
	}
	
	/**
	 * @desc 文印楼层
	 * @param unknown $floor
	 * @param unknown $activityId
	 * @param unknown $floorId
	 * @return string
	 */
	protected function getFloorViewWy($floor,$activityId,$floorId) {
	
		$acType = ActivityType::findOne(['id'=>$floor['activity_type'],'status'=>1]);
	
		if($acType && !is_null($floor)){
			// 特价活动
			if(strtolower($acType->ename) == 'special' && ($floor['mobile_station'] == '2' || $this->user_info['user_group'] == $floor['mobile_station'])) {
				// 活动产品
				$products = empty($floor['products_type_id']) ? [] : ActiveModelItemsWy::getActiveGoods($floor['products_type_id'], $this->user_info['city']);
	
				// 模板ID
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
	
				return $this->renderPartial('activity-type/wy/special/template'.$templateId,[
						'products' => $products,
						'setting'  => $floor,
						'floorId'  => $floorId,
				]);
			}
				
			// 秒杀楼层
			if(strtolower($acType->ename) == 'seckill' && ($floor['mobile_station'] == '2' || $this->user_info['user_group'] == $floor['mobile_station'])) {
				$seckills = [];
				foreach($floor['seckill_event'] as $event){
					$seckills[] = [
							'time' => $event['time'], // 秒杀时间
							'products' => ActiveModelItemsWy::getActiveGoods($event['products_type_id'], $this->user_info['city']), // 秒杀产品
					];
				}
	
				// 模板ID
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
	
				return $this->renderPartial('activity-type/wy/seckill/template'.$templateId,[
						'seckills' => $seckills,
						'setting'  => $floor,
						'floorId'  => $floorId,
				]);
			}
				
			// 外部链接楼层
			if(strtolower($acType->ename) == 'links') {
				// 模板ID
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
					
				return $this->renderPartial('activity-type/links/template'.$templateId,[
						'setting'  => $floor,
						'floorId'  => $floorId,
				]);
			}
				
			// 转盘楼层
			if(strtolower($acType->ename) == 'zhuanpan') {
				// 模板ID
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
	
				return $this->renderPartial('activity-type/zhuanpan/template'.$templateId,[
						'setting'    => $floor,
						'activityId' => $activityId,
						'floorId'  => $floorId,
				]);;
			}
				
			// 图片展示楼层
			if(strtolower($acType->ename) == 'picture') {
				$templateId = isset($floor['template_id']) ? $floor['template_id'] : '1';
	
				return $this->renderPartial('activity-type/picture/template'.$templateId,[
						'setting'    => $floor,
						'activityId' => $activityId,
						'floorId'    => $floorId,
				]);
			}
				
			// 其他楼层
			if(strtolower($acType->ename) == 'other') {
				$code = '';
				// 活动代码
				if(strpos($floor['code'],'-')!==false){
					$names = explode('-', trim($floor['code']));
					foreach($names as $name){
						$code .= ucfirst($name);
					}
				} else {
					$code = ucfirst(trim($floor['code']));
				}
	
				$methodName = 'get'.$code;
				if(method_exists($this, $methodName)){
					return $this->$methodName($floorId,$activityId);
				}
	
				return '';
			}
		}
	
		return '';
	}
	
   	/**
   	 * @Description: 抽奖逻辑
   	 * @param:
   	 * $activityId： 活动ID
   	 * $prizeIds： 奖池(奖品ID)
   	 * $provideRule: '0':按量发放，发完即止; '1':每天平均发放
   	 * $triesLimit: '0':不限次数; '1':只能抽一次; '2':PC端/手机端各抽一次。
   	 * @return: json
   	 * 【status】:抽奖状态,0:抽中;-1:未抽中 【message】:返回消息 【prizeId】:抽取产品ID(没抽到返回-1);
   	 * @author: yazhou.miao
   	 * @date: 2016-4-7 上午09:45:46
   	 */
   	protected function runLottery($activityId,$prizeIds,$provideRule = '0',$triesLimit = '0') {
   		$data = [];
   		$user_info = $this->user_info;

   		// 活动时间
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$activityStartTime = strtotime($activity->start_time);
   		$activityEndTime = strtotime($activity->end_time);
   		
   		// 当天的起始、截止时间
   		$currStartTime = date("Y-m-d H:i:s",mktime(0,0,0,date('m'),date('d'),date('Y')));
   		$currEndTime   = date("Y-m-d H:i:s",mktime(23,59,59,date('m'),date('d'),date('Y')));

   		// PC端抽奖次数
	   	$pcWinTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['activity_id' => $activityId,'is_mobile'=>0])->andWhere(['in','prize_id',$prizeIds])->count();
	   	// 手机端抽奖次数
	   	$mobileWinTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['activity_id' => $activityId,'is_mobile'=>1])->andWhere(['in','prize_id',$prizeIds])->count();
	
	   	$allTimes = $pcWinTimes + $mobileWinTimes;
	   	if('1'===$triesLimit && ($pcWinTimes || $mobileWinTimes)){
	   		// 如果已经参与则不在参与转盘抽奖
	   		$data['status'] = -1;
	   		$data['message'] = '抱歉，您已参加过此活动！';
	   		$data['prizeId'] = -1;
	   	} else if('2'===$triesLimit && $pcWinTimes>=1) {
	   		// 如果在PC端已经参与则不在参与转盘抽奖
	   		$data['status'] = -1;
	   		$data['message'] = '抱歉，您已在电脑端参加过此活动，<br/>请尝试用手机参与活动！';
	   		$data['prizeId'] = -1;
	   	} elseif ('3'===$triesLimit && $allTimes >2){
	   		$data['status'] = -1;
	   		$data['message'] = '抱歉，您已参加过此活动！';
	   		$data['prizeId'] = -1;
	   	}elseif ('21'===$triesLimit && $allTimes >1){
	   		$data['status'] = -1;
	   		$data['message'] = '抱歉，您已参加过此活动！';
	   		$data['prizeId'] = -1;
	   	}else {
	   		// 抽奖处理
	   		$currTime = time();
	   		// 活动天数
	   		$activityDays = ceil(($activityEndTime - $activityStartTime)/(60*60*24));
	
	   		// 初始化奖品信息
	   		$prizesInfo = [];
	   		foreach ($prizeIds as $prizeId) {
	   			$prize = Prizes::find()->where(['id' => $prizeId])->andWhere(['activity_id' => $activityId])->andWhere(['>','qty',0])->one();
	   			
	   			$prize && array_push($prizesInfo, $prize);
	   		}
	
	   		// 设置奖池
	   		$startNum = 0;
	
	   		foreach($prizesInfo as $prize){
	   			// 此产品中奖总次数
	   			$winCount = PrizesUser::find()->where(['activity_id' => $activityId,'prize_id' => $prize->id])->count();
	   			// 此产品当天中奖次数
	   			$winCurrCount = PrizesUser::find()->where(['activity_id' => $activityId,'prize_id' => $prize->id])->andWhere(['>=','time_added',$currStartTime])->andWhere(['<=','time_added',$currEndTime])->count();
	
	   			// 当天中奖次数>=平均每天要中奖的次数，则此产品不放入奖池
	   			if('1'===$provideRule && ($activityDays > 1) && $winCurrCount >= floor(($winCount + $prize->qty)/$activityDays)){
	   				continue;
	   			}
	   			// 中奖区间最小随机数
	   			$min = $startNum + 1;
	   			// 中奖区间最大随机数
	   			$max = $startNum + self::BASE_NUMBER * $prize->rate;
	
	   			$prizes[$prize->id] = [
		   			'id'   => $prize->id,
		   			'min'  => $min,
		   			'max'  => $max,
		   			'name' => $prize->name,
		   			'coupon_id' => $prize->coupon_id,
	   			];
	
	   			$startNum = $max;
	   		}
	   		if(isset($prizes)){
	   			// 必须抽中一个
	   			if(1 === count($prizes)){
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
	   			$prize = Prizes::findOne(['id'=>$winPrizeId]);
	   			
	   			//事务开始
	   			$connection = Yii::$app->db;
	   			$transaction = $connection->beginTransaction();
	
	   			try{
		   			// 如果奖品是红包，则发送给用户
		   			if($prize->coupon_id > 0 && CouponInfo::findOne(['id'=>$prize->coupon_id])) {
		   				// 设置红包有效期
		   				$startTime = $prize->start_time === null ? $activityStartTime : $prize->start_time;
		   				$endTime = $prize->end_time === null ? $activityEndTime : $prize->end_time;
		   				$return = CouponUser::addCoupon($prizes[$winPrizeId]['coupon_id'], $user_info['id'], $activity->title,$startTime,$endTime,$prizes[$winPrizeId]['name']);
		   			}
		
		   			if(!isset($return) || $return){
		   				$prize->qty = $prize->qty > 0 ? $prize->qty - 1 : 0;
		   				if($prize->save()){
		   					$prizesUser = new PrizesUser();
		   					$prizesUser->user_id = $user_info['id'];
		   					$prizesUser->activity_id = $activityId;
		   					$prizesUser->prize_id = $winPrizeId;
		   					$prizesUser->is_mobile = 0; // PC端抽奖
		   					$prizesUser->time_added = date('Y-m-d H:i:s');
		
		   					$return = $prizesUser->save();
		   				}
		   			}
		   			
		   			if(isset($return) && $return){
		   				$transaction->commit();
		   				
		   				$data['status'] = 0;
		   				$data['message'] = $prizes[$winPrizeId]['name'];
		   				$data['prizeId'] = $winPrizeId; // 获奖产品id
		   			} else {
		   				$transaction->rollBack();
		   				
		   				$data['status'] = -1;
		   				$data['message'] = '服务端错误！';
		   				$data['prizeId'] = -1;
		   			}
	   			} catch (\Exception $e){
	   				$transaction->rollBack();
	   				
	   				$data['status'] = -1;
	   				$data['message'] = '服务端错误！';
	   				$data['prizeId'] = -1;
	   			}
	   		} else {
	   			$data['status'] = -1;
	   			$data['message'] = '抱歉，奖品已被领完！';
	   			$data['prizeId'] = -1;
	   		}
	   	}

   		return $data;
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
   	protected function getWinPrizeId($prizes = []) {
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
   	 * @Description: 判断用户所在区域（省份、城市）是否可以参加活动
   	 * @param: $regions 可参加活动的区域 （array:多个区域;int:一个区域）
   	 * @return: true:在活动区域 false:在活动区域
   	 * @author: yazhou.miao
   	 * @date: 2016-1-21 下午3:43:25
   	 */
   	protected function isInRegions($regions = false) {
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
   	protected function isInTime($activeStartTime, $activeEndTime, $flag = false) {
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
   	 * @Description: 判断用户是否可以参加某商城的活动
   	 * @param: $mallId 商城ID 0:所有商城
   	 * @return: true:可以参加 false:不可以参加
   	 * @author: yazhou.miao
   	 * @date: 2016-3-23 下午2:34:30
   	 */
   	protected function isInMalls($mallId = 0) {
   		if($mallId == 0) return true;
   		
   		// 用户的所有商城
   		$malls = StoreMall::getMalls($this->user_info['id']);
   		
   		foreach($malls['yes_malls'] as $mall){
   			if($mall['id'] == $mallId) return true;
   		}
   		
   		return false;
   	}

   	/**
   	 * @Description: 活动有效性检测（检测内容包括活动是否存在，商城权限，活动时间，活动地域）
   	 * @param: $id:活动ID;$timeCheck:是否检测活动时间
   	 * @return: array status:检测结果 true 通过 false 不通过; message: 附带信息
   	 * @author: yazhou.miao
   	 * @date: 2016-4-13 下午5:31:07
   	 */
   	protected function validCheck($id,$timeCheck = false) {
   		
   		$activity = Activity::find()->where(['id'=>$id,'status'=>1])->andWhere(['in','is_mobile',[0,2]])->one();
   		 
   		if ($activity) { // 1、活动是否存在
   			$activityStartTime = strtotime($activity->start_time);
   			$activityEndTime = strtotime($activity->end_time);
   	
   			$result = [];
   	
   			if($timeCheck) { // 2、活动时间限制
   				// 判断活动时间
   				$flag = $this->isInTime($activityStartTime, $activityEndTime, true);
   					
   				if(-1 === $flag){
   					$result['status'] = false;
   					$result['message'] = '抱歉，活动尚未开始！';
   				} else if(1 === $flag) {
   					$result['status'] = false;
   					$result['message'] = '抱歉，活动已经结束！';
   				} else {
   					$result['status'] = true;
   					$result['message'] = '通过检测！';
   				}
   				
   				$result['code'] = $flag; // 活动时间状态码
   			}
   	
   			if(!isset($result['status']) || $result['status']) {
   				// 3、商城权限
   				if(!$this->isInMalls($activity->mall_id)){
   					$result['status'] = false;
   					$result['message'] = '抱歉，您没有参加此商城活动的权限！';
   				} else {
   					// 4、活动区域
   					$query = ActivityCity::findAll(['activity_id'=>$activity->id]);
   					foreach ($query as $value) {
   						$cities[] = $value->city;
   					}
   						
   					$cities = isset($cities) ? $cities : false;
   					if (!$this->isInRegions($cities)){
   						$result['status'] = false;
   						$result['message'] = '抱歉，您所在区域不可参加活动！';
   					} else {
   						$result['status'] = true;
   						$result['message'] = '通过检测！';
   					}
   				}
   			}
   		} else {
   			$result['status'] = false;
   			$result['message'] = '抱歉，找不到此次活动！';
   		}
   		 
   		return $result;
   	}

   	/**
   	 * @Description: 获取当前系统时间
   	 * @return: json array ['y']:年 ['m']:月 ['d']:日 ['h']:时 ['i']:分 ['s']:秒
   	 * @author: yazhou.miao
   	 * @date: 2016-3-25 上午10:07:54
   	 */
   	public function actionAjaxSysTime() {
   		$json = [];
   		
   		$json['y'] = date("Y");// 年
   		$json['m'] = date("m");// 月
   		$json['d'] = date("d");// 日
   		$json['h'] = date("H");// 时
   		$json['i'] = date("i");// 分
   		$json['s'] = date("s");// 秒
   		
   		return json_encode($json);
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
    	
    	// 活动时间
    	$activeStartTime = strtotime("2016-03-12 00:00:00");
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
    	
    	$result = PrizesUser::findOne(['user_id' => $this->user_info['id'], 'activity_id' => $activeId]);
    	
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
    			$result->activity_id = $activeId;
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
   	 * @Description:转盘抽奖
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-4-6 上午10:02:14
   	 */
   	public function actionAjaxZhuanpan() {
   		// 是否是ajax请求
   		if(!Yii::$app->request->getIsAjax()) return false;
   		
   		$activeId = f_post('activity_id');    // 活动id
   		$triesLimit = f_post('tries_limit');  // 抽奖次数限制
   		$provideRule = f_post('provide_rule'); // 奖品发送规则
   		$prizes = f_post('prizes');           // 奖品(字符串)
   		
   		$prizeIds = explode('@',$prizes);
   		foreach ($prizeIds as $key=>$id) {
   			$prizeIds[$key] = (int)$id;
   		}

   		// 有效性检测
   		$check = $this->validCheck($activeId,true);
   		
   		if($check['status']) {
   			
   			// 执行抽奖逻辑
   			$result = $this->runLottery($activeId,$prizeIds,$provideRule,$triesLimit);
   			
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
   		} else {
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}

   		return json_encode($result);
   	}
   	
   	/**
   	 * @Description: 4.18预热活动
   	 * @return: return_type
   	 * @author: yazhou.miao
   	 * @date: 2016-4-13 下午5:07:10
   	 */
   	protected function get415Yure() {
   		$activeId = 80;  // 活动ID
   		$couponPrizes = [18,19,20,21];   // 红包奖品
   		 
   		// 有效性检测
   		$check = $this->validCheck($activeId,true);
   		 
   		if ($check['status']) {
   		
   			// 执行红包抽奖逻辑
   			$result = $this->runLottery($activeId,$couponPrizes,'0','1');
   		
   			if($result['status'] === 0){
   				$coupon = '恭喜您获得：' . $result['message'];
   			}
   		}
   		
   		return $this->renderPartial('415/yure',[
			'coupon' => isset($coupon) ? $coupon : '',  // 抽中红包
   		]);
   	}
   	
   	/**
   	 * @Description: 4.18预热活动转盘抽奖
   	 * @return: return_type
   	 * @author: yazhou.miao
   	 * @date: 2016-4-13 下午5:08:00
   	 */
   	public function actionAjax415Zhuanpan() {
   		$activityId = 80;  // 活动ID
   		
   		$prizes = [22,23,24,25,26,27];   // 转盘奖品
   		// 有效性检测
   		$check = $this->validCheck($activityId,true);
   		 
   		if ($check['status']) {
   			// 执行抽奖逻辑
   			$result = $this->run415Lottery($activityId,$prizes);
   		
   			if($result['status'] === 0){
   				$result['message'] = '恭喜您抽中：' . $result['message'];
   			
   				// 确认奖品在转盘中的位置
   				$counter = -1;
   				foreach($prizes as $prizeId){
   					$counter++;
   					if($result['prizeId'] === $prizeId){
   						break;
   					}
   				}
   				$result['rotate'] = (360/count($prizes)) * $counter;
   			}
   		} else {
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}
   		
   		return json_encode($result);
   	}
   	
   	/**
   	 * @Description: 4.15活动抽奖逻辑
   	 * @param:
   	 * $activityId： 活动ID
   	 * $prizeIds： 奖池(奖品ID)
   	 * @return: json
   	 * 【status】:抽奖状态,0:抽中;-1:未抽中 【message】:返回消息 【prizeId】:抽取产品ID(没抽到返回-1);
   	 * @author: yazhou.miao
   	 * @date: 2016-4-7 上午09:45:46
   	 */
   	protected function run415Lottery($activityId,$prizeIds) {
   		$data = [];
   		$user_info = $this->user_info;
   	
   		// 活动时间
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$activityStartTime = strtotime($activity->start_time);
   		$activityEndTime = strtotime($activity->end_time);
   		 
   		// 当天的起始、截止时间
   		$currStartTime = date("Y-m-d H:i:s",mktime(0,0,0,date('m'),date('d'),date('Y')));
   		$currEndTime   = date("Y-m-d H:i:s",mktime(23,59,59,date('m'),date('d'),date('Y')));
   	
   		// 活动期间抽奖总次数
   		$winTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['in','prize_id',$prizeIds])->count();
   		// 活动当前抽奖总次数
   		$curWinTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['in','prize_id',$prizeIds])->andWhere(['>=','time_added',$currStartTime])->andWhere(['<=','time_added',$currEndTime])->count();
   		
   		if(0 == $winTimes || (UserMember::getLotteryByApi() && (0 == $curWinTimes || (1 == $curWinTimes && 1 == $winTimes)))) {
   			// 抽奖处理
   			$currTime = time();
   			// 活动天数
   			$activityDays = ceil(($activityEndTime - $activityStartTime)/(60*60*24));
   	
   			// 初始化奖品信息
   			$prizesInfo = [];
   			foreach ($prizeIds as $prizeId) {
   				$prize = Prizes::find()->where(['id' => $prizeId])->andWhere(['activity_id' => $activityId])->andWhere(['>','qty',0])->one();
   		   
   				$prize && array_push($prizesInfo, $prize);
   			}
   	
   			// 设置奖池
   			$startNum = 0;
   	
   			foreach($prizesInfo as $prize){
   				// 中奖区间最小随机数
   				$min = $startNum + 1;
   				// 中奖区间最大随机数
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
   				if(1 === count($prizes)){
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
   				$prize = Prizes::findOne(['id'=>$winPrizeId]);
   	
   				// 如果奖品是红包，则发送给用户
   				if($prize->coupon_id > 0 && CouponInfo::findOne(['id'=>$prize->coupon_id])) {
   					// 设置红包有效期
   					$startTime = $prize->start_time === null ? $activityStartTime : $prize->start_time;
   					$endTime = $prize->end_time === null ? $activityEndTime : $prize->end_time;
   					$return = CouponUser::addCoupon($prizes[$winPrizeId]['coupon_id'], $user_info['id'], $activity->title,$startTime,$endTime,$prizes[$winPrizeId]['name']);
   				}
   				
   				// 预付宝体验金处理
   				if(24 == $prize->id) { // 1万元体验金
   					UserMember::rewardFreeAmount(10000);
   				} elseif (26 == $prize->id) { // 50万体验金
   					UserMember::rewardFreeAmount(500000);
   				} elseif (27 == $prize->id) { // 200万体验金
   					UserMember::rewardFreeAmount(2000000);
   				}
   	
   				if(!isset($return) || $return){
   					$prize->qty = $prize->qty > 0 ? $prize->qty - 1 : 0;
   					if($prize->save()){
   						$prizesUser = new PrizesUser();
   						$prizesUser->user_id = $user_info['id'];
   						$prizesUser->activity_id = $activityId;
   						$prizesUser->prize_id = $winPrizeId;
   						$prizesUser->is_mobile = 0; // PC端抽奖
   						$prizesUser->time_added = date('Y-m-d H:i:s');
   	
   						$return = $prizesUser->save();
   					}
   				}
   	
   				if(isset($return) && $return){
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
   		} else {
   			$data['status'] = -1;
   			$data['message'] = '抱歉，您已参加过此活动！';
   			$data['prizeId'] = 0;
   		}
   	
   		return $data;
   	}
   	
   	/**
   	 * @Description: 4.18母婴活动
   	 * @return: return_type
   	 * @author: zy
   	 * @date: 2016-4-14 下午5:08:00
   	 */
   	
   	protected function get418Baby($floorId){
   	    return $this->renderPartial('418/baby',['floorId'=>$floorId]);
   	}
   	
   	/**
   	 * @Description: 4.18IT活动
   	 * @return: return_type
   	 * @author: zy
   	 * @date: 2016-4-14 下午5:08:00
   	 */
   	
   	protected function get418It($floorId){
   	    return $this->renderPartial('418/it',['floorId'=>$floorId]);
   	}
   	
   	/**
   	 * @Description: 4.18TX活动
   	 * @return: return_type
   	 * @author: zy
   	 * @date: 2016-4-14 下午5:08:00
   	 */
   	
   	protected function get418Tx($floorId){
   	    return $this->renderPartial('418/tx',['floorId'=>$floorId]);
   	}
   	
   	/**
   	 * @Description: 4.18超市活动
   	 * @return: return_type
   	 * @author: zy
   	 * @date: 2016-4-14 下午5:08:00
   	 */
   	protected function get418Shop($floorId){
   	    return $this->renderPartial('418/shop',['floorId'=>$floorId]);
   	}
   	
   	/**
   	 * @Description: 4.18家电活动
   	 * @return: return_type
   	 * @author: zy
   	 * @date: 2016-4-14 下午5:08:00
   	 */
   	protected function get418Appliance($floorId){
   	    return $this->renderPartial('418/appliance',['floorId'=>$floorId]);
   	}
   	
   	/**
   	 * @desc 4.18IT秒杀
   	 * @return string
   	 */
   	protected function get418itSeckill($floorId){
   		$timeLimit = date('H:i:s');
   		$seckillGoods1 = ActiveModelItems::getActiveGoods(1460436532, $this->user_info['city']);
   		$seckillGoods2 = ActiveModelItems::getActiveGoods(1460436627, $this->user_info['city']);
   		return $this->renderPartial('418/it_seckill',['seckillGoods1'=>$seckillGoods1,
   													 'seckillGoods2'=>$seckillGoods2,
   													 'timeLimit'=>$timeLimit,
   				                                     'floorId'=>$floorId
   																					]);
   	}
   	
   	/**
   	 * @desc 4.18tx秒杀
   	 * @return string
   	 */
   	protected function get418txSeckill($floorId){
   	    $timeLimit = date('H:i:s');
   	    $seckillGoods1 = ActiveModelItems::getActiveGoods(1460528632, $this->user_info['city']);
   	    $seckillGoods2 = ActiveModelItems::getActiveGoods(1460528760, $this->user_info['city']);
   	    return $this->renderPartial('418/tx_seckill',['seckillGoods1'=>$seckillGoods1,
   	        'seckillGoods2'=>$seckillGoods2,
   	        'timeLimit'=>$timeLimit,
   	    	'floorId'=>$floorId
   	    ]);
   	}
   	
   	protected function get418Footer($floorId){
   		return $this->renderPartial('418/footer',['floorId'=>$floorId]);
   	}
   	
   	/**
   	 * @desc IT悬浮框
   	 * @return string
   	 */
   	protected function get418itXuan(){
   		return $this->renderPartial('418/it_xuan');
   	}
   	
   	/**
   	 * @desc 通讯悬浮框
   	 * @return string
   	 */
   	protected function get418txXuan(){
   		return $this->renderPartial('418/tx_xuan');
   	}
   	
   	/**
   	 * @desc 母婴悬浮框
   	 * @return string
   	 */
   	protected function get418myXuan(){
   		return $this->renderPartial('418/my_xuan');
   	}
   	
   	/**
   	 * @desc 超市悬浮框
   	 * @return string
   	 */
   	protected function get418shopXuan(){
   		return $this->renderPartial('418/shop_xuan');
   	}
   	
   	/**
   	 * @desc 家电悬浮框
   	 * @return string
   	 */
   	protected function get418jdXuan(){
   		return $this->renderPartial('418/jd_xuan');
   	}
   	
   	/**
   	 * @Description: 4.28预热活动(IT商城)
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-4-25 下午2:49:11
   	 */
   	protected function get428YureIt() {
   		// TODO:处理
   		$group1 = ActiveModelItems::getBargainInfo(173, $this->user_info['province']);
   		$group2 = [859,962];
   		$products1 = ActiveModelItems::getBargainGoodsInfo($group1,$this->user_info['id']);
   		$products2 = ActiveModelItems::getBargainGoodsInfo($group2,$this->user_info['id']);
   		return $this->renderPartial('428/yure',[
			'products1' => $products1,
   			'products2' => $products2,
   		]);
   	}
   	
   	/**
   	 * @desc 通讯
   	 * @return string
   	 */
   	protected function get428YureTx() {
   		// TODO:处理
   		$group1 = ActiveModelItems::getBargainInfo(172, $this->user_info['province']);
   		$group2 = [859,962];
   		$products1 = ActiveModelItems::getBargainGoodsInfo($group1,$this->user_info['id']);
   		$products2 = ActiveModelItems::getBargainGoodsInfo($group2,$this->user_info['id']);
   		return $this->renderPartial('428/yure',[
   				'products1' => $products1,
   				'products2' => $products2,
   		]);
   	}
   	
   	/**
   	 * @desc 母婴
   	 * @return string
   	 */
   	protected function get428YureBaby() {
   		// TODO:处理
   		$group1 = ActiveModelItems::getBargainInfo(174, $this->user_info['province']);
   		$group2 = [859,962];
   		$products1 = ActiveModelItems::getBargainGoodsInfo($group1,$this->user_info['id']);
   		$products2 = ActiveModelItems::getBargainGoodsInfo($group2,$this->user_info['id']);
   		return $this->renderPartial('428/yure',[
   				'products1' => $products1,
   				'products2' => $products2,
   		]);
   	}
   	
   	/**
   	 * @desc 超市
   	 * @return string
   	 */
   	protected function get428YureShop() {
   		// TODO:处理
   		$group1 = ActiveModelItems::getBargainInfo(175, $this->user_info['province']);
   		$group2 = [859,962];
   		$products1 = ActiveModelItems::getBargainGoodsInfo($group1,$this->user_info['id']);
   		$products2 = ActiveModelItems::getBargainGoodsInfo($group2,$this->user_info['id']);
   		return $this->renderPartial('428/yure',[
   				'products1' => $products1,
   				'products2' => $products2,
   		]);
   	}
   	
   	/**
   	 * @desc 家电
   	 * @return string
   	 */
   	protected function get428YureJd() {
   		// TODO:处理
   		$group1 = ActiveModelItems::getBargainInfo(176, $this->user_info['province']);
   		$group2 = [859,962];
   		$products1 = ActiveModelItems::getBargainGoodsInfo($group1,$this->user_info['id']);
   		$products2 = ActiveModelItems::getBargainGoodsInfo($group2,$this->user_info['id']);
   		return $this->renderPartial('428/yure',[
   				'products1' => $products1,
   				'products2' => $products2,
   		]);
   	}
   	
   	/**
   	 * @Description: 4.28砍价ajax请求处理
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-4-25 下午2:55:10
   	 */
   	public function actionAjax428Yure() {
   		$userInfo = $this->user_info;
   		$goodsId = f_post("goods_id",0);
   		
   		if($goodsId){
   			$barInfo = BargainInfo::find()->select('*')->where(['goods_id'=>$goodsId])->one();
   			$barInfo = $barInfo ? $barInfo : new BargainInfo();
   			
   			$barInfo->num = $barInfo->num ? $barInfo->num + 1 : 1;
   			$barInfo->goods_id = $goodsId;
   			$status = $barInfo->save();
   			if($status){
   				$barUser = new BargainUser();
   				$barUser->bar_id = $barInfo->id;
   				$barUser->user_id = $userInfo['id'];
   				$status = $barUser->save();
   			}
   			 
   			if($status){
   				$json['status'] = 1;
   			} else {
   				$json['status'] = 0;
   			}
   		} else {
   			$json['status'] = 0;
   		}
	   
   		return json_encode($json);
   	}
   	
   	/**
   	 * @Description: 4.28活动红包（IT商城）
   	 * @param: $floorId 楼层Id
   	 * @param: $activityId 活动ID
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-4-27 上午9:54:57
   	 */
   	public function get428CouponIt($floorId,$activityId) {
   		// 红包Id
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
   		return $this->renderPartial('428/coupon-it',[
   			'prizes' => $prizes,
   			'activityId' => $activityId,
   			'floorId'  => $floorId,
   		]);
   	}
   	
   	/**
   	 * @Description: 4.28活动红包（通讯商城）
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-4-27 上午9:54:57
   	 */
   	public function get428CouponTx($floorId,$activityId) {
   		// 红包Id
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
   		return $this->renderPartial('428/coupon-tx',[
   			'prizes' => $prizes,
   			'activityId' => $activityId,
   			'floorId'  => $floorId,
   		]);
   	}
   	
   	/**
   	 * @Description: 4.28活动红包（家电商城）
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-4-27 上午9:54:57
   	 */
   	public function get428CouponJd($floorId,$activityId) {
   		// 红包Id
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
   		return $this->renderPartial('428/coupon-jd',[
   				'prizes' => $prizes,
   				'activityId' => $activityId,
   				'floorId'  => $floorId,
   		]);
   	}
   	
   	/**
   	 * @Description: 4.28活动红包（母婴-省内）
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-4-27 上午9:54:57
   	 */
   	public function get428CouponBabyIn($floorId,$activityId) {
   		// 红包Id
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
   		return $this->renderPartial('428/coupon-baby-in',[
   				'prizes' => $prizes,
   				'activityId' => $activityId,
   				'floorId'  => $floorId,
   		]);
   	}
   	
   	/**
   	 * @Description: 4.28活动红包（母婴-省外）
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-4-27 上午9:54:57
   	 */
   	public function get428CouponBabyOut($floorId,$activityId) {
   		// 红包Id
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
   		return $this->renderPartial('428/coupon-baby-out',[
   				'prizes' => $prizes,
   				'activityId' => $activityId,
   				'floorId'  => $floorId,
   		]);
   	}
   	
   	/**
   	 * @Description: 4.28活动红包（通讯商城）
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-4-27 上午9:54:57
   	 */
   	public function get428CouponShop($floorId,$activityId) {
   		// 红包Id
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
   		return $this->renderPartial('428/coupon-shop',[
   				'prizes' => $prizes,
   				'activityId' => $activityId,
   				'floorId'  => $floorId,
   		]);
   	}
   	
   	/**
   	 * @Description: 
   	 * @return: return_type
   	 * @author: yazhou.miao
   	 * @date: 2016-4-27 上午11:12:58
   	 */
   	public function actionAjax428Coupon() {
   		$user_info = $this->user_info;
   		if(Yii::$app->request->getIsAjax()){
   			$activityId = (int)f_post("activity_id",0);
   			$prizeId = (int)f_post("prize_id",0);
   			
   			$result = $this->validCheck($activityId,true);
   			if($result['status']){
   				$prize = Prizes::find()->where(['id' => $prizeId])->andWhere(['activity_id' => $activityId])->andWhere(['>','qty',0])->one();
   				// 奖品有没有被抽过
   				$winTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['activity_id' => $activityId])->andWhere(['prize_id'=>$prizeId])->count();
   				
   				// 全品类红包限制
   				$quanpin = [54,65,42,55,56,66];
   				$quanpinTimes = 0; // 全品类抽取次数
   				if(in_array($prizeId,$quanpin)){
   					$quanpinTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['in','prize_id',$quanpin])->count();
   				}
   				
   				if(!$prize){
   					// 红包不存在
   					$json['status'] = 0;
   					$json['message'] = '抱歉，此红包已经领完了！';
   				} else if($winTimes || $quanpinTimes) {
   					// 已经领取
   					$json['status'] = 2;
   					$json['message'] = '抱歉，此红包已经被领取！';
   				} else {
   					$activity = Activity::findOne(['id'=>$activityId]);
   					
   					$prizeUser = new PrizesUser();
   					$prizeUser->user_id = $user_info['id'];
   					$prizeUser->activity_id = $activity->id;
   					$prizeUser->prize_id = $prizeId;
   					$prizeUser->time_added = date('Y-m-d H:i:s');
   					$prizeUser->is_mobile = 0;
   					$status = $prizeUser->save();
   					if($status){
   						$prize->qty = $prize->qty-1; // 奖品数量减1
   						$status = $prize->save();
   						$status && $status = CouponUser::addCoupon($prize->coupon_id, $user_info['id'], $activity->title, $prize->start_time, $prize->end_time);
   					}
   					
   					if(!$status){
   						$json['status'] = 0;
   						$json['message'] = "红包领取失败！";
   					} else {
   						$json['status'] = 1;
   						$json['message'] = "红包领取成功！";
   					}
   				}
   			} else {
   				// 活动报错
   				$json['status'] = 0;
   				$json['message'] = $result['message'];
   			}
   			
   			return json_encode($json);
   		}
   	}

   	
   	/**
   	 * @desc 图片热点
   	 * @return string
   	 */
   	
   	protected function get428Hot($floorId){
   	    return $this->renderPartial('428/hot',['floorId'=>$floorId]);
   	}

   	
   	/**
   	 * @desc 428活动头部
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	protected function get428Top($floorId){
   		return $this->renderPartial('428/top',['floorId'=>$floorId]);
   	}
   	
   	/**
   	 * @desc 504 眼镜商城
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	protected function get504Coupon($floorId){ 

   	    return $this->renderPartial('504/coupon',['floorId'=>$floorId]);
   	}
   	
   	/**
   	 * @desc 504 眼镜商城红包
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	
   	public function actionAjax504Coupon() {
   	    // 活动id
   	    $activityId = 228;
   	    $prizeIds = [67];
   	    //有效检测
   	    $check = $this->validCheck($activityId,true);
   	    //    	    $nowTime = date('Y-m-d H:i:s');
   	    if( $this->user_info['reg_time'] < '2016-05-04 00:00:00'){
   	        $result['status'] = -1;
   	        $result['message'] = '没有权限参加此活动';
   	        return json_encode($result);
   	    }
   	    if($check['status']){
   	        $result = $this->runLottery($activityId,$prizeIds,'0','1');
   	        if($result['status'] === 0){
   	            $result['message'] = $result['message'];
   	        }
   	    }else{
   	        $result['status'] = -1;
   	        $result['message'] = $check['message'];
   	    }
   	    return  json_encode($result);
   	}
   	
   	/**
   	 * @desc 505 文体商城
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	
   	protected function get505OfficeCoupon($floorId){
   	    return $this->renderPartial('505/office-coupon',['floorId'=>$floorId]);
   	}
   	/**
   	 * @desc 505 文体商城红包
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	
   	public function actionAjax505Coupon() {
   	    // 活动id
   	    $activityId = 231;
   	    $prizeIds = [68];
   	    //有效检测
   	    $check = $this->validCheck($activityId,true);
   	   
   	    if($check['status']){
   	        $result = $this->runLottery($activityId,$prizeIds,'0','2');
   	        if($result['status'] === 0){
   	            $result['message'] = $result['message'];
   	        }
   	    }else{
   	        $result['status'] = -1;
   	        $result['message'] = $check['message'];
   	    }
   	    return  json_encode($result);
   	}   	
   	
   	/**
   	 * @Description: 5.8号母亲节活动(超市)
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-5-5 上午9:51:25
   	 */
   	protected function get508Top() {
   		return $this->renderPartial('508/index');
   	}
   	
   	/**
   	 * @Description: 5.9活动红包（通讯商城）
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-5-5 上午9:54:57
   	 */
   	public function get509CouponTx($floorId,$activityId) {
   		// 红包Id
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
   		return $this->renderPartial('509/coupon-tx',[
   				'prizes' => $prizes,
   				'activityId' => $activityId,
   				'floorId'  => $floorId,
   		]);
   	}
   	
   	/**
   	 * @Description: 抽奖结果
   	 * @return: json
   	 * @author: yazhou.miao
   	 * @date: 2016-4-27 上午11:12:58
   	 */
   	public function actionAjax509Coupon() {
   		$user_info = $this->user_info;
   		if(Yii::$app->request->getIsAjax()){
   			$activityId = (int)f_post("activity_id",0);
   			$prizeId = (int)f_post("prize_id",0);
   	
   			$result = $this->validCheck($activityId,true);
   			if($result['status']){
   				$prize = Prizes::find()->where(['id' => $prizeId])->andWhere(['activity_id' => $activityId])->andWhere(['>','qty',0])->one();
   				// 奖品有没有被抽过
   				$winTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['activity_id' => $activityId])->andWhere(['prize_id'=>$prizeId])->count();
   					
   				if(!$prize){
   					// 红包不存在
   					$json['status'] = 0;
   					$json['message'] = '抱歉，此红包已经领完了！';
   				} else if($winTimes) {
   					// 已经领取
   					$json['status'] = 2;
   					$json['message'] = '抱歉，此红包已经被领取！';
   				} else {
   					$activity = Activity::findOne(['id'=>$activityId]);
   	
   					$prizeUser = new PrizesUser();
   					$prizeUser->user_id = $user_info['id'];
   					$prizeUser->activity_id = $activity->id;
   					$prizeUser->prize_id = $prizeId;
   					$prizeUser->time_added = date('Y-m-d H:i:s');
   					$prizeUser->is_mobile = 0;
   					$status = $prizeUser->save();
   					if($status){
   						$prize->qty = $prize->qty-1; // 奖品数量减1
   						$status = $prize->save();
   						// 红包有效期
   						$startTime = $prize->start_time ? $prize->start_time : $activity->start_time;
   						$endTime = $prize->end_time ? $prize->end_time : $activity->end_time;
   						$status && $status = CouponUser::addCoupon($prize->coupon_id, $user_info['id'], $activity->title, $startTime, $endTime);
   					}
   	
   					if(!$status){
   						$json['status'] = 0;
   						$json['message'] = "红包领取失败！";
   					} else {
   						$json['status'] = 1;
   						$json['message'] = "红包领取成功！";
   					}
   				}
   			} else {
   				// 活动报错
   				$json['status'] = 0;
   				$json['message'] = $result['message'];
   			}
   	
   			return json_encode($json);
   		}
   	}
   	
   	/**
   	 * @Description: 5.10号活动
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-5-9 上午9:51:25
   	 */
   	protected function get510GlassTop($floorId) {
   		return $this->renderPartial('510/index',['floorId'=>$floorId]);
   	}

    
    /**
   	 * @desc 510 眼镜商城
   	 * @param unknown $floorId
   	 * @return string
   	 */
    protected function get510Glass($floorId){
    	return $this->renderPartial('510/glass',['floorId'=>$floorId]);
    }
       
    /**
   	 * @desc 510 眼镜商城红包
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	public function actionAjax510Coupon() {
           // 活动id
           $activityId = 277;
           $prizeIds = [73];
           //有效检测
           $check = $this->validCheck($activityId,true);

           if($check['status']){
               $result = $this->runLottery($activityId,$prizeIds,'0','1');
               if($result['status'] === 0){
                   $result['message'] = $result['message'];
               }
           }else{
               $result['status'] = -1;
               $result['message'] = $check['message'];
           }
           return  json_encode($result);
    } 

    /**
     * @desc 活动弹框
     */
    public function actionAjaxCookie(){
       $user_id = $this->user_info['id'];
       $mall = f_post('mall');
       $key = $mall."_".$user_id;
       $re = f_ck($key,1,86400);
       return json_encode(f_ck($key));
    }

    /**
	 * @desc 预付宝活动
	 * @author xin.zhang
	 */
	public function actionYfb(){
		$login_account = $this->user_info['login_account'];
		$yfb_url = 'http://120.55.137.181:5100/yfpay_member_api/accountJudge.do';
		$content=['application'=>'app_account_jurisdiction','entrance'=>'ent_account_jurisdiction','parameters'=>['loginName'=>$login_account]];
		$a  = new CommonInterface();
		$result = $a->useInterface($yfb_url,$content)['return_code'];
		$data=0;
		switch($result){
			case '0000':$data='02.jpg';break;
			case '5001':$data='01.jpg';break;
			case '5002':$data='03.jpg';break;
		}
		return $data;
	}

    /**
     * @desc 515  预热
     * @param unknown $floorId
     * @return string
     */
    protected function get515ChaiCoupon($floorId){
    	
        return $this->renderPartial('515/chai-coupon',[
			'floorId' => $floorId,
        ]);
    }

    /**
     * @desc 515  预热
     * @return string
     */
    public function actionAjax515ChaiCoupon(){
        // 活动id
        $activityId = 286;
        //$prizeIds = [1,2,3,4];
        $prizeIds = [74,75,76,77];
        //有效检测
        $check = $this->validCheck($activityId,true);

        if($check['status']){
            $result = $this->run515Lottery($activityId,$prizeIds);
            if($result['status'] === 0){
                $result['message'] = "亲，" . $result['message'] . "<br />已双手奉上，记得使用哦~~~";
                // 确认奖品在转盘中的位置
                $counter = 0;
                foreach($prizeIds as $prizeId){
                	$counter++;
                	if($result['prizeId'] === $prizeId){
                		break;
                	}
                }
                // 红包的位置
                $result['position'] = $counter;
            }
        }else{
            $result['status'] = -1;
            $result['message'] = $check['message'];
        }
        return  json_encode($result);
    }
    
    /**
     * @Description: 5.15拆红包抽奖逻辑
     * @param:
     * $activityId： 活动ID
     * $prizeIds： 奖池(奖品ID)
     * @return: json
     * 【status】:抽奖状态,0:抽中;-1:未抽中 【message】:返回消息 【prizeId】:抽取产品ID(没抽到返回-1);
     * @author: yazhou.miao
     * @date: 2016-4-7 上午09:45:46
     */
    protected function run515Lottery($activityId,$prizeIds) {
    	$data = [];
    	$user_info = $this->user_info;
    
    	// 活动时间
    	$activity = Activity::findOne(['id'=>$activityId]);
    	$activityStartTime = strtotime($activity->start_time);
    	$activityEndTime = strtotime($activity->end_time);
    
    	// 当天的起始、截止时间
    	$currStartTime = date("Y-m-d H:i:s",mktime(0,0,0,date('m'),date('d'),date('Y')));
    	$currEndTime   = date("Y-m-d H:i:s",mktime(23,59,59,date('m'),date('d'),date('Y')));
    
    	// 活动期间抽奖总次数
    	$winTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['in','prize_id',$prizeIds])->count();
    	
    	// 最多抽两次
    	if(0 == $winTimes || ($winTimes < 2 && UserMember::getLotteryByApi())) {
    		// 抽奖处理
    		$currTime = time();
    
    		// 初始化奖品信息
    		$prizesInfo = [];
    		foreach ($prizeIds as $prizeId) {
    			$prize = Prizes::find()->where(['id' => $prizeId])->andWhere(['activity_id' => $activityId])->andWhere(['>','qty',0])->one();
    
    			$prize && array_push($prizesInfo, $prize);
    		}
    
    		// 设置奖池
    		$startNum = 0;
    
    		foreach($prizesInfo as $prize){
    			// 中奖区间最小随机数
    			$min = $startNum + 1;
    			// 中奖区间最大随机数
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
    			if(1 === count($prizes)){
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
    			$prize = Prizes::findOne(['id'=>$winPrizeId]);
    
    			//事务开始
    			$connection = Yii::$app->db;
    			$transaction = $connection->beginTransaction();
    			try{
    				// 如果奖品是红包，则发送给用户
    				if($prize->coupon_id > 0 && CouponInfo::findOne(['id'=>$prize->coupon_id])) {
    					// 设置红包有效期
    					$startTime = $prize->start_time === null ? $activityStartTime : $prize->start_time;
    					$endTime = $prize->end_time === null ? $activityEndTime : $prize->end_time;
    					$return = CouponUser::addCoupon($prizes[$winPrizeId]['coupon_id'], $user_info['id'], $activity->title,$startTime,$endTime,$prizes[$winPrizeId]['name']);
    				}
    				
    				if(!isset($return) || $return){
    					$prize->qty = $prize->qty > 0 ? $prize->qty - 1 : 0;
    					if($prize->save()){
    						$prizesUser = new PrizesUser();
    						$prizesUser->user_id = $user_info['id'];
    						$prizesUser->activity_id = $activityId;
    						$prizesUser->prize_id = $winPrizeId;
    						$prizesUser->is_mobile = 0; // PC端抽奖
    						$prizesUser->time_added = date('Y-m-d H:i:s');
    				
    						$return = $prizesUser->save();
    					}
    				}
    				
    				if(isset($return) && $return){
    					$data['status'] = 0;
    					$data['message'] = $prizes[$winPrizeId]['name'];
    					$data['prizeId'] = $winPrizeId; // 获奖产品id
    					
    					$transaction->commit();
    				} else {
    					$data['status'] = -1;
    					$data['message'] = '服务端错误！';
    					$data['prizeId'] = -1;
    					
    					$transaction->rollBack();
    				}
    				
    			}catch (\Exception $e){
    				$transaction->rollBack();
    				$data['status'] = -1;
    				$data['message'] = '服务端错误！';
    				$data['prizeId'] = -1;
    			}
    		} else {
    			$data['status'] = -1;
    			$data['message'] = '抱歉，奖品已经被抽完了！';
    			$data['prizeId'] = -1;
    		}
    	} else {
    		$data['status'] = -1;
    		$data['message'] = '抱歉，您已参加过此活动！';
    		$data['prizeId'] = 0;
    	}
    
    	return $data;
    }
    
    /**
     * @Description: 通讯商城销量排行榜
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-5-12 下午4:13:36
     */
    protected function get518TxRank($floorId) {
    	$mallId = 1;
    	$limit  = 20;
    	$result = f_c('activity-518-tx-rank');
    	if($result == null || empty($result)){
    		// TODO:订单起始时间
    		$startTime = '2016-05-18 00:00:00';
    		$endTime   = '2016-05-18 23:59:59';
    		$result = ActiveModelItems::getSalesRankByMall($mallId, $limit, $startTime, $endTime);
    		f_c('activity-518-tx-rank',$result,30*60);
    	}
    	
    	// 页面展示
    	return $this->renderPartial('518/tx-rank',[
    		'floorId'  => $floorId,
    		'list'     => $result,
    	]);
    }
    
    /**
     * @Description: 母婴商城销量排行榜
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-5-12 下午4:13:36
     */
    protected function get518BabyInRank($floorId) {
    	$mallId = 3;
    	$limit  = 15;
    	
    	$result = f_c('activity-518-baby-rank');
    	if($result == null || empty($result)){
    		// TODO:订单起始时间
    		$startTime = '2016-05-18 00:00:00';
    		$endTime   = '2016-05-18 23:59:59';
    		$provinces = [3,13,16,17,31];
    		$result = ActiveModelItems::getSalesRankByMall($mallId, $limit, $startTime, $endTime, $provinces);
    		f_c('activity-518-baby-rank',$result,30*60);
    	}
    	
    	// 页面展示
    	return $this->renderPartial('518/baby-in-rank',[
    		'floorId'  => $floorId,
    		'list' => $result,
    	]);
    }
    
    /**
     * @Description: 获取排行信息
     * @return: json
     * @author: yazhou.miao
     * @date: 2016-5-12 下午4:16:11
     */
    public function actionAjax518GetRank() {
    	$mallId = f_get('mall_id',0);
    	$limit  = f_get('limit',20);
    	
    	$mall = 'tx';  // 商城简称
    	if($mallId == 1){
    		$mall = 'tx';
    	} else if($mallId == 3){
    		$mall = 'baby';
    	}
    	// TODO:订单起始时间
    	$startTime = '2016-05-18 00:00:00';
    	$endTime   = '2016-05-18 23:59:59';
    	$result = f_c('activity-518-'.$mall.'-rank');
    	if($result == null){
    		$result = ActiveModelItems::getSalesRankByMall($mallId, $limit, $startTime, $endTime);
    		f_c('activity-518-'.$mall.'-rank',$result,30*60);
    	}
    	 
    	return json_encode(['list' => $result]);
    }
    
    /**
     * @Description: 
     * @return: return_type
     * @author: yazhou.miao
     * @date: 2016-5-12 下午4:16:11
     */
    public function get515PictureShow($floorId) {
    	$depot = DepotCity::find()->where(['province'=>$this->user_info['province']])->min('depot_id');
    	return $this->renderPartial('515/picture-show',[
    			'depot' => $depot ? $depot : 0,
    			'floorId' => $floorId,
		]);
    } 
    /**
     * @Description: 
     * @return: return_type
     * @author: yazhou.miao
     * @date: 2016-5-12 下午4:16:11
     */
    public function get515GiftPackage() {
    	$depot = DepotCity::find()->where(['province'=>$this->user_info['province']])->min('depot_id');
    	return $this->renderPartial('515/gift-package',['depot' => $depot ? $depot : 0]);
    }
        /**
     * @Description: 
     * @return: return_type
     * @author: yazhou.miao
     * @date: 2016-5-12 下午4:16:11
     */
    public function get515GiftPackageTop() {
    	return $this->renderPartial('515/gift-package-top');
    }
    /**
     * @Description: 518 超市红包活动
     * @return: return_type
     * @author: 
     * @date: 2016-5-16 
     */
    protected function get516GlassCoupon(){
        return $this->renderPartial('516/glass-coupon');
    }
    /**
   	 * @desc 505 文体商城红包
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	public function actionAjax516Coupon() {
   	    // 活动id
   	    $activityId = 322;
   	    $prizeIds = [81];
   	    //有效检测
   	    $check = $this->validCheck($activityId,true);
   	    $res = ActiveModelItems::isCheckCoupon(8,'2016-05-10 00:00:00',$this->user_info['id']);
   	    if( $res !== false){
   	    	$result['status'] = -1;
   	    	$result['message'] = '没有权限参加此活动';
   	    	return json_encode($result);
   	    }
   	    if($check['status']){
   	        $result = $this->runLottery($activityId,$prizeIds,'0','1');
   	        if($result['status'] === 0){
   	            $result['message'] = $result['message'];
   	        }
   	    }else{
   	        $result['status'] = -1;
   	        $result['message'] = $check['message'];
   	    }
   	    return  json_encode($result);
   	}   	
   	
    /**
     * @Description: 518 云店其他活动
     * @return: return_type
     * @author: 
     * @date: 2016-5-16 
     */
    protected function get518CloudStore($floorId){
        return $this->renderPartial('518/cloud-store',['floorId'=>$floorId]);
    }
    
    /**
     * @Description: 518 超市红包活动
     * @return: return_type
     * @author: 
     * @date: 2016-5-16 
     */
    protected function get518ShopCoupon($floorId){
        return $this->renderPartial('518/shop-coupon',['floorId'=>$floorId]);
    }
    /**
   	 * @desc 518 超市红包
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	public function actionAjax518ShopCoupon() {
   	    // 活动id
   	    $activityId = 330;
   	    $prizeIds = [88];
   	    //有效检测
   	    $check = $this->validCheck($activityId,true);
   	    if($check['status']){
   	        $result = $this->runLottery($activityId,$prizeIds,'0','2');
   	        if($result['status'] === 0){
   	            $result['message'] = $result['message'];
   	        }
   	    }else{
   	        $result['status'] = -1;
   	        $result['message'] = $check['message'];
   	    }
   	    return  json_encode($result);
   	}

   	/**
   	 * @Description: 518 超市红包活动
   	 * @return: return_type
   	 * @author:
   	 * @date: 2016-5-16
   	 */
   	protected function get518ShopCoupon2($floorId){
   		return $this->renderPartial('518/shop-coupon2',['floorId'=>$floorId]);
   	}
   	/**
   	 * @desc 518 超市红包
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	public function actionAjax518ShopCoupon2() {
   		// 活动id
   		$activityId = 331;
   		$prizeIds = [89];
   		//有效检测
   		$check = $this->validCheck($activityId,true);
   		if($check['status']){
   			$result = $this->runLottery($activityId,$prizeIds,'0','2');
   			if($result['status'] === 0){
   				$result['message'] = $result['message'];
   			}
   		}else{
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}
   		return  json_encode($result);
   	}
    
    /**
     * @Description: 518 超市红包活动
     * @return: return_type
     * @author: 
     * @date: 2016-5-16 
     */
    protected function get518BabyCoupon($floorId){
        return $this->renderPartial('518/baby-coupon',['floorId'=>$floorId]);
    }
    
    /**
   	 * @desc 518 超市红包
   	 * @param unknown $floorId
   	 * @return string
   	 */
   	public function actionAjax518BabyCoupon() {
   	    // 活动id
   	    $activityId = f_post('id');
   	    $prizeIds = [-1000];
   	    //有效检测
   	    $check = $this->validCheck($activityId,true);
        $res = ActiveModelItems::isCheckCoupon(3, '2016-01-11 00:00:00', $this->user_info['id']);
        $lotCheck = PrizesUser::find()->select('id')->where(['user_id'=>$this->user_info['id'],'prize_id'=>$prizeIds[0]])->asArray()->scalar();
        if($res !==false){
            $result['status'] = -1;
            $result['message'] = '老会员请参加商城其他活动';
            return json_encode($result);
        }
   	    if($check['status']){
   	    	if ($lotCheck === false){
   	    		$result1 = CouponUser::addCoupon(2031,$this->user_info['id'],'10元母婴全品类红包',date('Y-m-d H:i:s'),'2016-05-19 23:59:59');
   	    		$result2 = CouponUser::addCoupon(2188,$this->user_info['id'],'10元玩具品类红包',date('Y-m-d H:i:s'),'2016-05-19 23:59:59');
   	    		$result3 = CouponUser::addCoupon(2189,$this->user_info['id'],'10元洗护品类红包',date('Y-m-d H:i:s'),'2016-05-19 23:59:59');
   	    		$result4 = CouponUser::addCoupon(2190,$this->user_info['id'],'10元纸尿裤品类红包',date('Y-m-d H:i:s'),'2016-05-19 23:59:59');
   	    		$result5 = CouponUser::addCoupon(2191,$this->user_info['id'],'6元贝亲品牌红包',date('Y-m-d H:i:s'),'2016-05-19 23:59:59');
   	    		$result6 = CouponUser::addCoupon(2192,$this->user_info['id'],'5元辅食品类红包',date('Y-m-d H:i:s'),'2016-05-19 23:59:59');
   	    		if ($result1 || $result2 || $result3 || $result4 || $result5 || $result6){
   	    			$prizesUser = new PrizesUser();
   	    			$prizesUser->user_id = $this->user_info['id'];
   	    			$prizesUser->activity_id = $activityId;
   	    			$prizesUser->prize_id = $prizeIds[0];
   	    			$prizesUser->is_mobile = 0; // PC端抽奖
   	    			$prizesUser->time_added = date('Y-m-d H:i:s');
   	    			$prizesUser->save();
   	    			$result['status'] = 0;
   	    			$result['message'] = 'OK';
   	    		}
   	    	}else {
   	    		$result['status'] = -1;
   	    		$result['message'] = '抱歉，您已参加过此活动！';
   	    	}
   	    }else{
   	        $result['status'] = -1;
   	        $result['message'] = $check['message'];
   	    }
   	    return  json_encode($result);
   	} 
    
    /**
     * @desc 母婴悬浮框
     * @return string
     */
    protected function get518myXuan(){
    	return $this->renderPartial('518/my_xuan');
    }
    
    /**
     * @desc 家电商城
     * @return string
     */
    protected function get525JdCoupon($floorId){
    	return $this->renderPartial('525/jd-coupon',['floorId'=>$floorId]);
    }
    
    /**
     * @desc 家电商城
     * @return string
     */
    public function actionAjax525JdCoupon(){
        //活动id
        $activityId = f_post('id');
        $prizeIds = [-2000];
        //有效检测
        $check = $this->validCheck($activityId,true);
        $lotCheck = PrizesUser::find()->select('id')->where(['user_id'=>$this->user_info['id'],'prize_id'=>$prizeIds[0]])->scalar();
        if($check['status']){
              if($lotCheck === false){
                  $result1 = CouponUser::addCoupon(2102, $this->user_info['id'], '10元家电类红包', date('Y-m-d H:i:s'), '2016-05-31 23:59:59');
                  $result2 = CouponUser::addCoupon(2101, $this->user_info['id'], '5元礼品类红包', date('Y-m-d H:i:s'), '2016-05-31 23:59:59');
               if($result1 || $result2){
                    $prizesUser = new PrizesUser();
   	    			$prizesUser->user_id = $this->user_info['id'];
   	    			$prizesUser->activity_id = $activityId;
   	    			$prizesUser->prize_id = $prizeIds[0];
   	    			$prizesUser->is_mobile = 0; // PC端抽奖
   	    			$prizesUser->time_added = date('Y-m-d H:i:s');
   	    			$prizesUser->save();
   	    			$result['status'] = 0;
   	    			$result['message'] = 'OK';
                  }
              }else{
                    $result['status'] = -1;
                    $result['message'] ='抱歉，已参加过此活动';
             }
        }else{
            $result['status'] = -1;
            $result['message'] = $check['message'];
        }
        return  json_encode($result);
    }
    
    /**
     * @Description: 5.25家电全国排行榜
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-5-24 下午3:09:27
     */
    protected function get525JdRank($floorId) {
    	$mallId = 4; // 家电商城
    	$limit  = 10;
    	$result = f_c('activity-525-jd-rank');
    	if(empty($result)){
    		// 订单起始时间
    		$startTime = '2016-05-25 00:00:00';
    		$endTime   = '2016-05-31 23:59:59';
    		$result = ActiveModelItems::getSalesRankByMall($mallId, $limit, $startTime, $endTime);
    		f_c('activity-525-jd-rank',$result,30*60); // 缓存30分钟
    	}
    	
    	// 页面展示
    	return $this->renderPartial('525/jd-rank',[
    		'floorId'  => $floorId,
    		'list'     => $result,
    	]);
    }
    
    /**
     * @Description: 5.25IT商城全国排行榜/赛区排行榜
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-5-24 下午3:09:27
     */
    protected function get525ItRank($floorId) {
    	$mallId = 2; // IT商城
    	$types = [6]; // 商品品类:平板电脑
    	$brands = [1,2,3,5,6,8,157]; // 商品品牌
    	// 订单起始时间
    	$startTime = '2016-05-23 00:00:00';
    	$endTime   = '2016-05-31 23:59:59';
    	
    	// 全国排行榜
    	$allResult = f_c('activity-525-it-nation');
    	if(empty($allResult)){
    		$allResult = ActiveModelItems::getSalesRankByMall($mallId, 3, $startTime, $endTime, [],$types, $brands);
    		f_c('activity-525-it-nation',$allResult,30*60); // 缓存30分钟
    	}
    	
    	// 赛区排行榜
    	$provincesIn = [16,22,3,31,13,4,11]; // 前七个赛区（省）
    	$provincesOut = [10,6,14,17,24,26,394]; // 第八个赛区
    	
    	$cacheKey = in_array($this->user_info['province'], $provincesIn) ? $this->user_info['province'] : 0;
    	$areaResult = f_c('activity-525-it-province-'.$cacheKey);
    	
    	if(empty($areaResult)){
    		// 省份限制
    		$provinces = in_array($this->user_info['province'], $provincesIn) ? [$this->user_info['province']] : $provincesOut;
    		
    		$areaResult = ActiveModelItems::getSalesRankByMall($mallId, 10, $startTime, $endTime, $provinces, $types, $brands);
    		f_c('activity-525-it-province-'.$cacheKey,$areaResult,30*60); // 缓存30分钟
    	}
    	// 页面展示
    	return $this->renderPartial('525/it-rank',[
    		'floorId'  => $floorId,
    		'allList'  => $allResult,
    		'areaList' => $areaResult,
    	]);
    }
    
    protected function get525JdTitle($floorId) {
    	return $this->renderPartial('525/top',['floorId'=>$floorId]);
    }
    
    protected function get526GlassTitle($floorId) {
    	return $this->renderPartial('525/glass_top',['floorId'=>$floorId]);
    }
    
    /**
     * @Description: 5.28预热
     * @return: 渲染页面
     * @date: 2016-5-25 上午09:40:27
     */
    protected function get528WarmUp($floorId){
    	$activeId = 387;  // 活动ID
    	$couponPrizes = [107,108,109,110];   // 红包奖品
    	
    	// 有效性检测
    	$check = $this->validCheck($activeId,true);
    	
    	if ($check['status']) {
    		// 执行红包抽奖逻辑
    		$result = $this->runLottery($activeId,$couponPrizes,'0','1');
    		
    		if($result['status'] === 0){
    			$coupon = '恭喜您获得：' . $result['message'];
    		}
    	}
    	 
        $depot = DepotCity::find()->where(['province'=>$this->user_info['province']])->min('depot_id');
        return $this->renderPartial('528/warm-up',[
                   'depot'=>$depot ? $depot: 0,
                   'floorId'=>$floorId,
        		   'coupon' => isset($coupon) ? $coupon : '',  // 抽中红包
            ]);
    }
    
    
    /**
     * @Description: 5.28预热老虎机处理
     * @return: json
     * @author: yazhou.miao
     * @date: 2016-5-25 下午3:25:00
     */
    public function actionAjax526Slots() {
   		// 是否是ajax请求
   		if(!Yii::$app->request->getIsAjax()) return false;
   		
   		$activityId = 387;
   		$prizes = [98,99,100,102,103,104,105,106];// 奖品
   		
   		// 有效性检测
   		$check = $this->validCheck($activityId,true);
   		
   		if($check['status']) {
   			
   			// 执行抽奖逻辑
   			$result = $this->run526Lottery($activityId,$prizes);
   			
   			if($result['status'] === 0){
   				$result['message'] = '恭喜您获得：' . $result['message'];
   			
   				// 确认奖品的位置
   				$counter = -1;
   				foreach($prizes as $prizeId){
   					$counter++;
   					if($result['prizeId'] === $prizeId){
   						break;
   					}
   				}
   				$result['position'] = $counter;
   			}
   		} else {
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}

   		return json_encode($result);
   	}
   	
   	/**
   	 * @Description: 5.28老虎机
   	 * @param:
   	 * $activityId： 活动ID
   	 * $prizeIds： 奖池(奖品ID)
   	 * @return: json
   	 * 【status】:抽奖状态,0:抽中;-1:未抽中 【message】:返回消息 【prizeId】:抽取产品ID(没抽到返回-1);
   	 * @author: yazhou.miao
   	 * @date: 2016-4-7 上午09:45:46
   	 */
   	protected function run526Lottery($activityId,$prizeIds) {
   		$data = [];
   		$user_info = $this->user_info;
   	
   		// 活动时间
   		$activity = Activity::findOne(['id'=>$activityId]);
   		$activityStartTime = strtotime($activity->start_time);
   		$activityEndTime = strtotime($activity->end_time);
   	
   		// 活动期间抽奖总次数
   		$winTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['in','prize_id',$prizeIds])->count();
   		 
   		// 最多抽两次
   		if(0 == $winTimes || ($winTimes < 2 && UserMember::getLotteryByApi())) {
   			// 抽奖处理
   			$currTime = time();
   	
   			// 初始化奖品信息
   			$prizesInfo = [];
   			foreach ($prizeIds as $prizeId) {
   				$prize = Prizes::find()->where(['id' => $prizeId])->andWhere(['activity_id' => $activityId])->andWhere(['>','qty',0])->one();
   	
   				$prize && array_push($prizesInfo, $prize);
   			}
   	
   			// 设置奖池
   			$startNum = 0;
   	
   			foreach($prizesInfo as $prize){
   				// 中奖区间最小随机数
   				$min = $startNum + 1;
   				// 中奖区间最大随机数
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
   				if(1 === count($prizes)){
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
   				$prize = Prizes::findOne(['id'=>$winPrizeId]);
   	
   				//事务开始
   				$connection = Yii::$app->db;
   				$transaction = $connection->beginTransaction();
   				try{
   					// 如果奖品是红包，则发送给用户
   					if($prize->coupon_id > 0 && CouponInfo::findOne(['id'=>$prize->coupon_id])) {
   						// 设置红包有效期
   						$startTime = $prize->start_time === null ? $activityStartTime : $prize->start_time;
   						$endTime = $prize->end_time === null ? $activityEndTime : $prize->end_time;
   						$return = CouponUser::addCoupon($prizes[$winPrizeId]['coupon_id'], $user_info['id'], $activity->title,$startTime,$endTime,$prizes[$winPrizeId]['name']);
   					}
   	
   					if(!isset($return) || $return){
   						$prize->qty = $prize->qty > 0 ? $prize->qty - 1 : 0;
   						if($prize->save()){
   							$prizesUser = new PrizesUser();
   							$prizesUser->user_id = $user_info['id'];
   							$prizesUser->activity_id = $activityId;
   							$prizesUser->prize_id = $winPrizeId;
   							$prizesUser->is_mobile = 0; // PC端抽奖
   							$prizesUser->time_added = date('Y-m-d H:i:s');
   	
   							$return = $prizesUser->save();
   						}
   					}
   	
   					if(isset($return) && $return){
   						$data['status'] = 0;
   						$data['message'] = $prizes[$winPrizeId]['name'];
   						$data['prizeId'] = $winPrizeId; // 获奖产品id
   							
   						$transaction->commit();
   					} else {
   						$data['status'] = -1;
   						$data['message'] = '服务端错误！';
   						$data['prizeId'] = -1;
   							
   						$transaction->rollBack();
   					}
   	
   				}catch (\Exception $e){
   					$transaction->rollBack();
   					$data['status'] = -1;
   					$data['message'] = '服务端错误！';
   					$data['prizeId'] = -1;
   				}
   			} else {
   				$data['status'] = -1;
   				$data['message'] = '抱歉，奖品已经被抽完了！';
   				$data['prizeId'] = -1;
   			}
   		} else {
   			$data['status'] = -1;
   			$data['message'] = '抱歉，您已参加过此活动！';
   			$data['prizeId'] = 0;
   		}
   	
   		return $data;
   	}
    
    /**
   	 * @Description: 5.28云店
   	 * @param:
   	 * @date: 2016-05-26 下午16:02:46
   	 */
    protected function get528CloudStore($floorId){
        return $this->renderPartial('528/cloud-store',['floorId'=>$floorId]);
    }

   	/**
   	 * @Description: 5.28通讯排行榜
   	 * @return: 渲染页面
   	 * @author: yazhou.miao
   	 * @date: 2016-5-27 上午9:48:53
   	 */
   	protected function get528TxRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-05-28 00:00:00';
    	$endTime   = '2016-05-28 23:59:59';
    	
    	$result = f_c('activity-528-tx-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime);
    		f_c('activity-528-tx-rank',$result,10*60); // 十分钟缓存
    	}
    	
    	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    	
    	$list = array_slice($result,0,20); // 取前20个
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前20名
    	if($counter > 20){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    	
    	// 页面展示
    	return $this->renderPartial('528/tx-rank',[
    		'floorId'  => $floorId,
    		'list'     => $list, 
    		'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }
        
    /**
   	 * @Description: 5.28眼镜红包
   	 * @param:
   	 * @date: 2016-05-27 下午14:02:46
   	 */
    protected function get528GlassCoupon($floorId){
        return $this->renderPartial('528/glass-coupon',['floorId'=>$floorId]);
    }
            
    /**
   	 * @Description: 5.28眼镜红包
   	 * @param:
   	 * @date: 2016-05-27 下午14:02:46
   	 */
    public function actionAjax528GlassCoupon(){
       // 活动id
   		$activityId = 383;
   		$prizeIds = [97];
   		//有效检测
   		$check = $this->validCheck($activityId,true);
   		if($check['status']){
   			$result = $this->runLottery($activityId,$prizeIds,'0','1');
   			if($result['status'] === 0){
   				$result['message'] = $result['message'];
   			}
   		}else{
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}
   		return  json_encode($result);
    }
    
    /**
   	 * @Description: 5.28眼镜红包
   	 * @param:
   	 * @date: 2016-05-27 下午14:02:46
   	 */
    protected function get528ItCoupon($floorId){
        return $this->renderPartial('528/it-coupon',['floorId'=>$floorId]);
    }
            
    /**
   	 * @Description: 5.28it红包
   	 * @param:
   	 * @date: 2016-05-27 下午14:02:46
   	 */
    public function actionAjax528ItCoupon1(){
       // 活动id
   		$activityId = 410;
   		$prizeIds = [116];
   		//有效检测
   		$check = $this->validCheck($activityId,true);
   		if($check['status']){
   			$result = $this->runLottery($activityId,$prizeIds,'0','1');
   			if($result['status'] === 0){
   				$result['message'] = $result['message'];
   			}
   		}else{
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}
   		return  json_encode($result);
    }
    
    /**
   	 * @Description: 5.28it红包
   	 * @param:
   	 * @date: 2016-05-27 下午14:02:46
   	 */
    public function actionAjax528ItCoupon2(){
       // 活动id
   		$activityId = 410;
   		$prizeIds = [116];
   		//有效检测
   		$check = $this->validCheck($activityId,true);
   		if($check['status']){
   			$result = $this->runLottery($activityId,$prizeIds,'0','1');
   			if($result['status'] === 0){
   				$result['message'] = $result['message'];
   			}
   		}else{
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}
   		return  json_encode($result);
    }
    
    /**
     * @Description: 6月大促抽取卡牌
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-6-3 下午4:29:34
     */
    public function get606Card($floorId){
    	$activityId = 511; // 活动id
    	$prizeIds = [127,128,129,130,131]; // 卡牌奖品
        
    	$couponPrizeIds = [152,153,154,155]; // 红包奖品
        //有效检测
        $check =$this->validCheck($activityId ,true);
       	if ($check['status']) {
    		// 执行红包抽奖逻辑
    		$result = $this->runLottery($activityId,$couponPrizeIds,'0','1');
    		
    		if($result['status'] === 0){
    			$coupon = '恭喜您获得：' . $result['message'];
    		}
    	}
    	
    	// 抽奖总次数
    	$total = 4 + ActiveModelItems::getCardNumByUserId($this->user_info['id']);
    	//$total = 2;
    	// 已抽奖次数
    	$winTimes = PrizesUser::find()->where(['activity_id'=>$activityId,'user_id'=>$this->user_info['id']])->andWhere(['in','prize_id',$prizeIds])->count();
    	
    	$fourCardUsers = PrizesUser::getListByDistinct($activityId,4,$prizeIds); // 已经抽中4张卡牌的用户
    	$fiveCardUsers = PrizesUser::getListByDistinct($activityId,5,$prizeIds); // 已经抽中5张卡牌的用户
    	
    	return $this->renderPartial('606/card',[
			'floorId'      =>$floorId,
			'times'        =>$total-$winTimes>0?$total-$winTimes:0,
    		'coupon'       =>isset ($coupon) ? $coupon: '',
			'fourCardUsers'=>$fourCardUsers,
			'fiveCardUsers'=>$fiveCardUsers,
		]);
    }
    
    /**
     * @Description: 6月大促抽取卡牌(抽奖)
     * @return: 抽取结果
     * @author: yazhou.miao
     * @date: 2016-6-3 下午3:29:34
     */
    public function actionAjax606PlayCard() {
    	$activityId = 511; // 活动id
    	$prizeIds = [127,128,129,130,131]; // 卡牌奖品
    	
    	//有效检测
    	$check = $this->validCheck($activityId,true);
    	
    	if($check['status']){
    		// TODO: 抽奖总次数
    		$total = 4 + ActiveModelItems::getCardNumByUserId($this->user_info['id']);
    		//$total = 2;
    		
    		// 已抽奖次数
    		$winTimes = PrizesUser::find()->where(['activity_id'=>$activityId,'user_id'=>$this->user_info['id']])->andWhere(['in','prize_id',$prizeIds])->count();
    		
	    	if($total > $winTimes){
	    		$prizes = $this->get606Prizes();
	    		// 执行抽奖逻辑
	    		$result = $this->runLottery($activityId,$prizes,'0','0');
	    
	    		if($result['status'] === 0){
	    			$result['message'] = '恭喜您获得：' . $result['message'];
	    
	    			// 确认奖品的位置
	    			$counter = -1;
	    			foreach($prizeIds as $prizeId){
	    				$counter++;
	    				if($result['prizeId'] === $prizeId){
	    					break;
	    				}
	    			}
	    			$result['position'] = $counter;
	    			$result['total'] = $total-$winTimes-1;
	    		}
	    	} else {
    			$result['status'] = -1;
    			$result['message'] = '抱歉，你的抽奖机会已用完！';
    		}
    	}else{
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    	return  json_encode($result);
    }
    
    /**
     * @Description: 606卡牌，获取可抽奖品
     * @return: array
     * @author: yazhou.miao
     * @date: 2016-6-21 上午10:34:15
     */
    public function get606Prizes() {
    	$prizeIds = [127,128,129,130,131]; // 卡牌奖品
    	
    	$userIds = [27837,15407,146649,102338,172550,161642,16942,1268,98969,166808,17122,92903,266130,169218,105375,32026,15407,222599];
    	if(in_array($this->user_info['id'], $userIds)){
    		$prizes = [];
    		$userPrizes = PrizesUser::find()->select("prize_id")->where(['activity_id'=>511])->andWhere(['user_id'=>$this->user_info['id']])->andWhere(['in','prize_id',$prizeIds])->distinct()->asArray()->all();
    		$values = [];
    		foreach($userPrizes as $value){
    			array_push($values,$value['prize_id']);
    		}
    		foreach($prizeIds as $prizeId){
    			if(!in_array($prizeId,$values)) array_push($prizes, $prizeId);
    		}
    		
    		// 集齐5张发前三张
    		if(empty($prizes)) $prizes = [127,128,129];
    		
    		return $prizes;
    	} else {
    		return $prizeIds;
    	}
    }
    
    /**
     * @Description: 获取我的卡牌
     * @return: return_type
     * @author: yazhou.miao
     * @date: 2016-6-4 下午1:52:12
     */
    public function actionAjax606Mycard() {
    	$activityId = 511; // 活动id
    	$prizeIds = [127,128,129,130,131]; // 卡牌奖品
    	
    	$winPrizes = PrizesUser::getPrizesByUserId($this->user_info['id'], $activityId);
    	
    	$prizes = [];
    	foreach($prizeIds as $id){
    		if(key_exists($id, $winPrizes)) {
    			array_push($prizes, $winPrizes[$id]['num']);
    		} else {
    			array_push($prizes, 0);
    		}
    	}
    	
    	return json_encode($prizes);
    }
    
    /**
     * @desc 608活动头部
     * @param unknown $floorId
     * @return string
     */
    public function get608Top($floorId){
    	return $this->renderPartial('608/top',['floorId'  => $floorId,]);
    }

    
    /**
     * @Description: 6.8大促通讯排行榜
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-6-7 下午2:28:29
     */
    protected function get608TxRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-06-08 00:00:00';
    	$endTime   = '2016-06-08 23:59:59';
    	
    	$result = f_c('activity-608-tx-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime);
    		f_c('activity-608-tx-rank',$result,10*60); // 十分钟缓存
    	}
    	
    	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    	
    	$list = array_slice($result,0,20); // 取前20个
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前20名
    	if($counter > 20){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    	
    	// 页面展示
    	return $this->renderPartial('608/tx-rank',[
    		'floorId'  => $floorId,
    		'list'     => $list, 
    		'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }
    
    /**
     * @Description: 6.8大促IT家电排行榜
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-6-7 下午2:28:29
     */
    protected function get608JdRank($floorId) {
    	$mallId = 4;
    	// 订单起止时间
    	$startTime = '2016-06-08 00:00:00';
    	$endTime   = '2016-06-08 23:59:59';
    	 
    	$result = f_c('activity-608-jd-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime);
    		f_c('activity-608-jd-rank',$result,10*60); // 十分钟缓存
    	}
    	 
    	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    	 
    	$list = array_slice($result,0,10); // 取前10个
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前10名
    	if($counter > 10){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    	 
    	// 页面展示
    	return $this->renderPartial('608/jd-rank',[
    			'floorId'  => $floorId,
    			'list'     => $list,
    			'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }
    
    /*
     * 608 文体coupon
     */
    protected function get608WtCoupon($floorId,$activityId){
         // 红包Id
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
        return $this->renderPartial('608/wt-coupon',[
                                'prizes' => $prizes,
                                'activityId' => $activityId,
                                'floorId'  => $floorId,
                ]);
    }
     /*
     * 608 文体coupon
     */
    public function actionAjax608WtCoupon() {
   		$user_info = $this->user_info;
   		if(Yii::$app->request->getIsAjax()){
   			$activityId = (int)f_post("activity_id",0);
   			$prizeId = (int)f_post("prize_id",0);
   			
   			$result = $this->validCheck($activityId,true);
            
   			if($result['status']){
   				$prize = Prizes::find()->where(['id' => $prizeId])->andWhere(['activity_id' => $activityId])->andWhere(['>','qty',0])->one();
   				// 奖品有没有被抽过
   				$winTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['activity_id' => $activityId])->andWhere(['prize_id'=>$prizeId])->count();
   				
   				if(!$prize){
   					// 红包不存在
   					$json['status'] = 0;
   					$json['message'] = '抱歉，此红包已经领完了！';
   				} else if($winTimes ) {
   					// 已经领取
   					$json['status'] = 2;
   					$json['message'] = '抱歉，此红包已经被领取！';
   				} else {
   					$activity = Activity::findOne(['id'=>$activityId]);
   					
   					$prizeUser = new PrizesUser();
   					$prizeUser->user_id = $user_info['id'];
   					$prizeUser->activity_id = $activity->id;
   					$prizeUser->prize_id = $prizeId;
   					$prizeUser->time_added = date('Y-m-d H:i:s');
   					$prizeUser->is_mobile = 0;
   					$status = $prizeUser->save();
   					if($status){
   						$prize->qty = $prize->qty-1; // 奖品数量减1
   						$status = $prize->save();
   						$status && $status = CouponUser::addCoupon($prize->coupon_id, $user_info['id'], $activity->title, $prize->start_time, $prize->end_time);
   					}
   					
   					if(!$status){
   						$json['status'] = 0;
   						$json['message'] = "红包领取失败！";
   					} else {
   						$json['status'] = 1;
   						$json['message'] = "红包领取成功！";
   					}
   				}
   			} else {
   				// 活动报错
   				$json['status'] = 0;
   				$json['message'] = $result['message'];
   			}
   			
   			return json_encode($json);
   		}
        
   	}

    /*
     * 608 it家电coupon
     */
    protected function get608ItCoupon($floorId,$activityId){
         // 红包Id
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
        return $this->renderPartial('608/it-coupon',[
                                'prizes' => $prizes,
                                'activityId' => $activityId,
                                'floorId'  => $floorId,
                ]);
    }
     /*
     * 608 it家电coupon
     */
    public function actionAjax608ItCoupon() {
   		$user_info = $this->user_info;
   		if(Yii::$app->request->getIsAjax()){
   			$activityId = (int)f_post("activity_id",0);
   			$prizeId = (int)f_post("prize_id",0);
   			
   			$result = $this->validCheck($activityId,true);
            
   			if($result['status']){
   				$prize = Prizes::find()->where(['id' => $prizeId])->andWhere(['activity_id' => $activityId])->andWhere(['>','qty',0])->one();
   				// 奖品有没有被抽过
   				$winTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['activity_id' => $activityId])->andWhere(['prize_id'=>$prizeId])->count();
   				
   				if(!$prize){
   					// 红包不存在
   					$json['status'] = 0;
   					$json['message'] = '抱歉，此红包已经领完了！';
   				} else if($winTimes ) {
   					// 已经领取
   					$json['status'] = 2;
   					$json['message'] = '抱歉，此红包已经被领取！';
   				} else {
   					$activity = Activity::findOne(['id'=>$activityId]);
   					
   					$prizeUser = new PrizesUser();
   					$prizeUser->user_id = $user_info['id'];
   					$prizeUser->activity_id = $activity->id;
   					$prizeUser->prize_id = $prizeId;
   					$prizeUser->time_added = date('Y-m-d H:i:s');
   					$prizeUser->is_mobile = 0;
   					$status = $prizeUser->save();
   					if($status){
   						$prize->qty = $prize->qty-1; // 奖品数量减1
   						$status = $prize->save();
   						$status && $status = CouponUser::addCoupon($prize->coupon_id, $user_info['id'], $activity->title, $prize->start_time, $prize->end_time);
   					}
   					
   					if(!$status){
   						$json['status'] = 0;
   						$json['message'] = "红包领取失败！";
   					} else {
   						$json['status'] = 1;
   						$json['message'] = "红包领取成功！";
   					}
   				}
   			} else {
   				// 活动报错
   				$json['status'] = 0;
   				$json['message'] = $result['message'];
   			}
   			
   			return json_encode($json);
   		}
        
   	}
    
  	/**
   	 * @Description: 5.28眼镜红包
   	 * @param:
   	 * @date: 2016-05-27 下午14:02:46
   	 */
    protected function get608GlassCoupon($floorId){
        return $this->renderPartial('608/glass-coupon',['floorId'=>$floorId]);
    }
            
    /**
   	 * @Description: 5.28it红包
   	 * @param:
   	 * @date: 2016-05-27 下午14:02:46
   	 */
    public function actionAjax608GlassCoupon(){
       // 活动id
   		$activityId = 520;
   		$prizeIds = [142];
   		//有效检测
   		$check = $this->validCheck($activityId,true);
   		if($check['status']){
   			$result = $this->runLottery($activityId,$prizeIds,'0','1');
   			if($result['status'] === 0){
   				$result['message'] = $result['message'];
   			}
   		}else{
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}
   		return  json_encode($result);
    } 
    
    /*
     * 608 it家电coupon
     */
    protected function get608TxCoupon($floorId,$activityId){
         // 红包Id
   		$prizes = Prizes::find()->where(['activity_id'=>$activityId])->orderBy('sort_order')->all();
        return $this->renderPartial('608/tx-coupon',[
                                'prizes' => $prizes,
                                'activityId' => $activityId,
                                'floorId'  => $floorId,
                ]);
    }
     /*
     * 608 it家电coupon
     */
    public function actionAjax608TxCoupon() {
   		$user_info = $this->user_info;
   		if(Yii::$app->request->getIsAjax()){
   			$activityId = (int)f_post("activity_id",0);
   			$prizeId = (int)f_post("prize_id",0);
   			
   			$result = $this->validCheck($activityId,true);
            
   			if($result['status']){
   				$prize = Prizes::find()->where(['id' => $prizeId])->andWhere(['activity_id' => $activityId])->andWhere(['>','qty',0])->one();
   				// 奖品有没有被抽过
   				$winTimes = PrizesUser::find()->where(['user_id' => $user_info['id']])->andWhere(['activity_id' => $activityId])->andWhere(['prize_id'=>$prizeId])->count();
   				
   				if(!$prize){
   					// 红包不存在
   					$json['status'] = 0;
   					$json['message'] = '抱歉，此红包已经领完了！';
   				} else if($winTimes ) {
   					// 已经领取
   					$json['status'] = 2;
   					$json['message'] = '抱歉，此红包已经被领取！';
   				} else {
   					$activity = Activity::findOne(['id'=>$activityId]);
   					
   					$prizeUser = new PrizesUser();
   					$prizeUser->user_id = $user_info['id'];
   					$prizeUser->activity_id = $activity->id;
   					$prizeUser->prize_id = $prizeId;
   					$prizeUser->time_added = date('Y-m-d H:i:s');
   					$prizeUser->is_mobile = 0;
   					$status = $prizeUser->save();
   					if($status){
   						$prize->qty = $prize->qty-1; // 奖品数量减1
   						$status = $prize->save();
   						$status && $status = CouponUser::addCoupon($prize->coupon_id, $user_info['id'], $activity->title, $prize->start_time, $prize->end_time);
   					}
   					
   					if(!$status){
   						$json['status'] = 0;
   						$json['message'] = "红包领取失败！";
   					} else {
   						$json['status'] = 1;
   						$json['message'] = "红包领取成功！";
   					}
   				}
   			} else {
   				// 活动报错
   				$json['status'] = 0;
   				$json['message'] = $result['message'];
   			}
   			
   			return json_encode($json);
   		}
        
   	}
    
     /**
     * @desc 615 预热活动头部
     * @param unknown $floorId
     * @return string
     */
    public function get615Top($floorId){
    	return $this->renderPartial('615/top',['floorId'  => $floorId,]);
    }
    
    /*
     * 
     * 
     */
    protected function get618Tree($floorId){
        return $this->renderPartial('618/tree',['floorId'  => $floorId,]);
    }
    
    /**
     * @Description: 618 摇钱树
     * @return: json
     * @author: 
     * @date: 2016-6-16 上午09:25:00
     */
    public function actionAjax618Tree() {
   		// 是否是ajax请求
   		if(!Yii::$app->request->getIsAjax()) return false;
   		
   		$activityId = 572;
   		$prizes = [182,184,186];// 奖品
   		
   		// 有效性检测
   		$check = $this->validCheck($activityId,true);
   		
   		if($check['status']) {
   			
   			// 执行抽奖逻辑
   			$result = $this->runLottery($activityId,$prizes,'0','3');
   			
   			if($result['status'] === 0){
   				$result['message'] = $result['message'];
   			}
   		} else {
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}

   		return json_encode($result);
        
   	}
    
     /**
     * @desc 618 活动头部
     * @param unknown $floorId
     * @return string
     */
     protected function get618Top($floorId){
         return $this->renderPartial('618/top',['floorId' => $floorId]);
     }
  
     
     /**
      * @Description: 6.18大促通讯排行榜
      * @return: 渲染页面
      * @author: yazhou.miao
      * @date: 2016-6-17 下午2:28:29
      */
     protected function get618TxRank($floorId) {
     	$mallId = 1;
     	// 订单起止时间
     	$startTime = '2016-06-18 00:00:00';
     	$endTime   = '2016-06-18 23:59:59';
     	 
     	$result = f_c('activity-618-tx-rank');
     	if(empty($result)){
     		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime);
     		f_c('activity-618-tx-rank',$result,10*60); // 十分钟缓存
     	}
     	 
     	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime);
     	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
     	 
     	$list = array_slice($result,0,20); // 取前20个
     	$counter = 1;
     	foreach($list as $value){
     		if($value['user_id'] == $this->user_info['id']) break;
     		$counter++;
     	}
     	// 此用户不在前20名
     	if($counter > 20){
     		$rank = 1;
     		foreach($result as $v){
     			if($userAmount >= $v['amount']) break;
     			$rank++;
     		}
     	} else {
     		$rank = $counter; // 排名
     	}
     	 
     	// 页面展示
     	return $this->renderPartial('618/tx-rank',[
     			'floorId'  => $floorId,
     			'list'     => $list,
     			'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
     	]);
     }
     
     /**
      * @desc 618母婴转盘
      * @return boolean|string
      */
     public function actionAjax618BabyZhuanpan() {
     	// 是否是ajax请求
     	if(!Yii::$app->request->getIsAjax()) return false;
     	$activeId = 590;    // 活动id
     	$prizeIds = [161,166,165,164,163,162];
     	if (in_array($this->user_info['province'],[16,3,31,13,17])){
     		$activeId = 590;
     		$prizeIds = [161,166,165,164,163,162];
     	}elseif (in_array($this->user_info['province'],[22])){
     		$activeId = 591;
     		$prizeIds = [167,172,171,170,169,168];
     	}
     
     	// 有效性检测
     	$check = $this->validCheck($activeId,true);
     	$checkStatus = PrizesOrder::find()->select('id')->where(['user_id'=>$this->user_info['id'],'mall_id'=>3,'type'=>1])->asArray()->scalar();
     	if(!$checkStatus){
     		$result['status'] = -1;
     		$result['message'] = '欢迎您参加628转盘!';
     		return json_encode($result);
     	}
     	if($check['status']) {
     
     		// 执行抽奖逻辑
     		$result = $this->runLottery($activeId,$prizeIds,'0','1');
     
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
     	} else {
     		$result['status'] = -1;
     		$result['message'] = $check['message'];
     	}
     
     	return json_encode($result);
     }
     
    /**
     * @desc 618 活动头部
     * @param unknown $floorId
     * @return string
     */
     protected function get618BabyLottery($floorId){
         return $this->renderPartial('618/baby-lottery',['floorId' => $floorId]);
     }

     
    /**
     * @desc 618 云店
     * @param unknown $floorId
     * @return string
     * @date: 2016-6-17 下午2:28:29
     */
     protected function get618CloudStore($floorId){
         return $this->renderPartial('618/cloud-store',['floorId' => $floorId]);
     }
     
    /**
     * @desc 618 活动头部
     * @param unknown $floorId
     * @return string
     * @date: 2016-6-17 下午2:28:29
     */
     protected function get618GlassCoupon($floorId){
         return $this->renderPartial('618/glass-coupon',['floorId' => $floorId]);
     }
     
     /**
   	 * @Description: 618 glass红包
   	 * @param:
   	 * @date: 2016-06-17 下午14:02:46
   	 */
    public function actionAjax618GlassCoupon(){
       // 活动id
   		$activityId = 557;
   		$prizeIds = [160];
   		//有效检测
   		$check = $this->validCheck($activityId,true);
   		if($check['status']){
   			$result = $this->runLottery($activityId,$prizeIds,'0','1');
   			if($result['status'] === 0){
   				$result['message'] = $result['message'];
   			}
   		}else{
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}
   		return  json_encode($result);
    }      

     
     /**
      * @Description: 6.18大促母婴排行榜
      * @return: 渲染页面
      * @author: yazhou.miao
      * @date: 2016-6-17 下午2:28:29
      */
     protected function get618BabyRank($floorId) {
     	$mallId = 3;
     	// 订单起止时间
     	$startTime = '2016-06-18 00:00:00';
     	$endTime   = '2016-06-18 23:59:59';
     	 
     	$result = f_c('activity-618-baby-rank');
     	if(empty($result)){
     		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime);
     		f_c('activity-618-baby-rank',$result,10*60); // 十分钟缓存
     	}
     	 
     	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime);
     	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
     	 
     	$list = array_slice($result,0,8); // 取前8个
     	$counter = 1;
     	foreach($list as $value){
     		if($value['user_id'] == $this->user_info['id']) break;
     		$counter++;
     	}
     	// 此用户不在前8名
     	if($counter > 8){
     		$rank = 1;
     		foreach($result as $v){
     			if($userAmount >= $v['amount']) break;
     			$rank++;
     		}
     	} else {
     		$rank = $counter; // 排名
     	}
     	 
     	// 页面展示
     	return $this->renderPartial('618/baby-rank',[
     			'floorId'  => $floorId,
     			'list'     => $list,
     			'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
     	]);
     }
     
     /**
      * @desc 眼镜天天秒杀
      * @return string
      */
     protected function getDayGlass(){
     	$goodsInfo = ActiveModelItems::getActiveGoods(1465881829, $this->user_info['city']);
     	return $this->renderPartial('623/glass',['goodsInfo'=>$goodsInfo]);
     }

     
     /**
      * @Description: 6.28预热抽中5张卡牌的用户
      * @return: return_type
      * @author: yazhou.miao
      * @date: 2016-6-24 上午11:09:11
      */
     public function get626CardList() {
     	$activityId = 511;
     	$prizeIds = [127,128,129,130,131];
     	$result = f_c('activity-606-card-list');
     	if(empty($result)){
     		$result = PrizesUser::getListByDistinct($activityId,5,$prizeIds);
     		f_c('activity-606-card-list',$result,60*60); // 1小时缓存
     	}
     	
     	return $this->renderPartial('626/card_list',['results'=>$result]);
     } 
     
     /*
      * @Description: 6.26预热
      * @return: 渲染页面
      * @author: zy
      * @date: 2016-6-24 上午10:00:29
      */
     protected function get626Lottery($floorId){
         return $this->renderPartial('626/lottery',[
                        'floorId' => $floorId,
                 ]);
     }
     
     /**
      * @desc 626 转盘
      * @return boolean|string
      */
     public function actionAjax626Zhuanpan() {
     	// 是否是ajax请求
     	if(!Yii::$app->request->getIsAjax()) return false;
     	$activeId = 654;    // 活动id
     	$prizeIds = [199,195,196,198,197];
        $prizes = [127,128,129,130,131] ;
        $activityId = 511;
     	// 有效性检测
     	$check = $this->validCheck($activeId,true);   
        //获得卡牌张数
        $carkInfo = PrizesUser::getPrizesUserNum($this->user_info['id'], $activityId,$prizes);
        
        $num = count($carkInfo);

        if ($num > 4) {
            if ($check['status']) {

                // 执行抽奖逻辑
                $result = $this->runLottery($activeId, $prizeIds, '0', '1');

                if ($result['status'] === 0) {
                    $result['message'] = '恭喜您抽中：' . $result['message'];

                    // 确认奖品在转盘中的位置
                    $counter = -1;
                    foreach ($prizeIds as $prizeId) {
                        $counter++;
                        if ($result['prizeId'] === $prizeId) {
                            break;
                        }
                    }
                    $result['rotate'] = (360 / count($prizeIds)) * $counter;
                }
            } else {
                $result['status'] = -1;
                $result['message'] = $check['message'];
            }
        } else {
            $result['status'] = -1;
            $result['message'] = '对不起，您未集齐5张不同卡牌';
        }
        return json_encode($result);
    }

	/**
	 * @desc 体验金
	 * @return string
	 */
	protected function getBbinByUser(){
		return $this->renderPartial('626/bbin');
	}
 
	/**
	 * @desc 领取体验金
	 */
	public function action626AjaxBbin(){
		//活动id
		$activityId = 654;
		$prizeIds = [-1111];
		$prizes = [127,128,129,130,131] ;
		$activeId = 511;
		//有效检测
		$check = $this->validCheck($activityId,true);
		$lotCheck = PrizesUser::find()->select('id')->where(['user_id'=>$this->user_info['id'],'prize_id'=>$prizeIds[0]])->scalar();
		$carkInfo = PrizesUser::getPrizesUserNum($this->user_info['id'], $activeId,$prizes);
		$num = count($carkInfo);
		if ($num > 3) {
			if($check['status']){
				if($lotCheck === false){
					$result1 = UserMember::rewardFreeAmount(230000);
					if($result1){
						$prizesUser = new PrizesUser();
						$prizesUser->user_id = $this->user_info['id'];
						$prizesUser->activity_id = $activityId;
						$prizesUser->prize_id = $prizeIds[0];
						$prizesUser->is_mobile = 0; // PC端抽奖
						$prizesUser->time_added = date('Y-m-d H:i:s');
						$prizesUser->save();
						$result['status'] = 0;
						$result['message'] = 'OK';
					}else {
						$result['status'] = -1;
						$result['message'] = '抱歉，请查看是否已开通51预存宝';
					}
				}else{
					$result['status'] = -1;
					$result['message'] ='抱歉，已参加过此活动';
				}
			}else{
				$result['status'] = -1;
				$result['message'] = $check['message'];
			}
		}else {
			$result['status'] = -1;
			$result['message'] = '对不起，您未集齐4张不同卡牌';
		}
		return  json_encode($result);
	}

	/**
	 * @desc 预热头部
	 * @return string
	 */
	protected function get626Top(){
		$activityId = 654; // 活动id
	
		$couponPrizeIds = [190,191,192,193]; // 红包奖品
		//有效检测
		$check =$this->validCheck($activityId ,true);
		if ($check['status']) {
			// 执行红包抽奖逻辑
			$result = $this->runLottery($activityId,$couponPrizeIds,'0','1');
	
			if($result['status'] === 0){
				$coupon = '恭喜您获得：' . $result['message'];
			}
		}
		return $this->renderPartial('626/top',[
                        'coupon' => isset($coupon) ? $coupon : '',
                 ]);
	}

	/**
	 * @Description: 6.28通讯排行榜
	 * @return: return_type
	 * @author: yazhou.miao
	 * @date: 2016-6-27 下午2:38:09
	 */
	protected function get628TxRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-06-28 00:00:00';
    	$endTime   = '2016-06-28 23:59:59';
    	
    	$result = f_c('activity-628-tx-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime);
    		f_c('activity-628-tx-rank',$result,10*60); // 十分钟缓存
    	}
    	
    	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    	
    	$list = array_slice($result,0,20); // 取前20个
    	$newList = [
    			['user_id'=>'87690','phone'=>'13305176728','amount'=>'642687'],
    			['user_id'=>'146682','phone'=>'15951862021','amount'=>'425689'],
    			['user_id'=>'175411','phone'=>'15366163873','amount'=>'256378'],
    	];
    	$list1 = [];
    	$list = array_merge($list,$newList);
    	foreach ($list as $key=>$val){
    		$list1[$val['amount']] = $list[$key];
    	}
    	krsort($list1);
    	$list1 = array_values($list1);
    	$list = array_slice($list1,0,20);
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前20名
    	if($counter > 20){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    	
    	// 页面展示
    	return $this->renderPartial('628/tx-rank',[
    		'floorId'  => $floorId,
    		'list'     => $list, 
    		'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }
    
    /**
     * @Description: 6.28文体转盘
     * @return: return_type
     * @author: yazhou.miao
     * @date: 2016-6-27 下午3:06:28
     */
    public function get628WtZhuanpan($floorId) {
    	$activityId = 624;
    	// 转盘中奖情况
    	$result = f_c('activity-628-wt-zhuanpn');
    	if(empty($result)){
    		$query = (new Query())->select('t1.user_id,t2.user_name,t2.phone,t1.prize_id')->from('{{other_prizes_user}} AS t1')
	    		->leftJoin('{{user_member}} AS t2','t1.user_id = t2.id')
	    		->where(['t1.activity_id' => $activityId])
	    		->orderBy('t1.prize_id')
	    		->limit(50);
    		
    		$result = $query->createCommand(Yii::$app->get('db'))->queryAll();
    		f_c('activity-628-wt-zhuanpn',$result,10*60); // 十分钟缓存
    	}
    	
    	$userList = [];
    	foreach($result as $value){
    		$prize = Prizes::findOne(['id'=>$value['prize_id']]);
    		$userList[] = [
				'phone' => $value['phone'],
				'prize' => $prize ? $prize->name : '',
    		];
    	}
    	return $this->renderPartial('628/wt-zhuanpan', [
    		'floorId'=>$floorId,
    		'userList' => $userList
    	]);
    }
    

    /**
     * @Description: 
     * @return: return_type
     * @author: yazhou.miao
     * @date: 2016-6-27 下午4:54:16
     */
    public function actionAjax628WtZhuanpan() {
   		// 是否是ajax请求
   		if(!Yii::$app->request->getIsAjax()) return false;
   		
   		$user_info = $this->user_info;
   		$activeId = 624;                            // 活动id
   		$prizeIds = [200,205,204,203,202,201];   // 奖品(字符串)
   		$startTime = '2016-06-28 00:00:00';
   		$endTime = '2016-06-29 00:00:00';
   		// 有效性检测
   		$check = $this->validCheck($activeId,true);
   		
   		if($check['status']) {
   			
   			// 执行抽奖逻辑
   			$result = $this->runLottery($activeId,$prizeIds,'0','1');
   			
   			if($result['status'] === 0){
   				if(204 == $result['prizeId']){ // 发送大礼包
   					// 点钞机30元劵
   					CouponUser::addCoupon(2354, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'	点钞机30元劵');
   					CouponUser::addCoupon(2354, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'	点钞机30元劵');
   					// 计算器5元劵
   					CouponUser::addCoupon(2355, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'	计算器5元劵');
   					CouponUser::addCoupon(2355, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'	计算器5元劵');
   					// 学习文具3元劵
   					CouponUser::addCoupon(2356, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'学习文具3元劵');
   					CouponUser::addCoupon(2356, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'	学习文具3元劵');
   					CouponUser::addCoupon(2356, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'	学习文具3元劵');
   					// 复印纸3元劵
   					CouponUser::addCoupon(2357, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'	复印纸3元劵');
   					CouponUser::addCoupon(2357, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'	复印纸3元劵');
   					CouponUser::addCoupon(2357, $user_info['id'], '文体办公商城628大促',$startTime,$endTime,'	复印纸3元劵');
   				}
   				
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
   		} else {
   			$result['status'] = -1;
   			$result['message'] = $check['message'];
   		}

   		return json_encode($result);
   	}
    
    /**
     * @desc 家电商城
     * @return string
     */
    protected function get628JdCoupon($floorId){
    	return $this->renderPartial('628/jd-coupon',['floorId'=>$floorId]);
    }
    
    /**
     * @desc 家电商城
     * @return string
     */
    public function actionAjax628JdCoupon(){
    	//活动id
    	$activityId = 660;
    	$prizeIds = [-1112];
    	//有效检测
    	$check = $this->validCheck($activityId,true);
    	$lotCheck = PrizesUser::find()->select('id')->where(['user_id'=>$this->user_info['id'],'prize_id'=>$prizeIds[0],'activity_id'=>$activityId])->scalar();
    	if($check['status']){
    		if($lotCheck === false){
    			$result1 = CouponUser::addCoupon(2365, $this->user_info['id'], 'IT家电628家电类10元红包', date('Y-m-d H:i:s'), '2016-06-28 23:59:59');
    			$result2 = CouponUser::addCoupon(2368, $this->user_info['id'], 'IT家电628通用类8元红包', date('Y-m-d H:i:s'), '2016-06-28 23:59:59');
    			if($result1 || $result2){
    				$prizesUser = new PrizesUser();
    				$prizesUser->user_id = $this->user_info['id'];
    				$prizesUser->activity_id = $activityId;
    				$prizesUser->prize_id = $prizeIds[0];
    				$prizesUser->is_mobile = 0; // PC端抽奖
    				$prizesUser->time_added = date('Y-m-d H:i:s');
    				$prizesUser->save();
    				$result['status'] = 0;
    				$result['message'] = 'OK';
    			}
    		}else{
    			$result['status'] = -1;
    			$result['message'] ='抱歉，已参加过此活动';
    		}
    	}else{
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    	return  json_encode($result);
    }
    
    /**
     * @desc 家电商城
     * @return string
     */
    protected function get628JdCoupon2($floorId){
    	return $this->renderPartial('628/jd-coupon2',['floorId'=>$floorId]);
    }
    
    /**
     * @desc 家电商城
     * @return string
     */
    public function actionAjax628JdCoupon2(){
    	//活动id
    	$activityId = 659;
    	$prizeIds = [-1112];
    	//有效检测
    	$check = $this->validCheck($activityId,true);
    	$lotCheck = PrizesUser::find()->select('id')->where(['user_id'=>$this->user_info['id'],'prize_id'=>$prizeIds[0],'activity_id'=>$activityId])->scalar();
    	if($check['status']){
    		if($lotCheck === false){
    			$result1 = CouponUser::addCoupon(2365, $this->user_info['id'], 'IT家电628家电类10元红包', date('Y-m-d H:i:s'), '2016-06-28 23:59:59');
    			$result2 = CouponUser::addCoupon(2368, $this->user_info['id'], 'IT家电628通用类8元红包', date('Y-m-d H:i:s'), '2016-06-28 23:59:59');
    			$result3 = CouponUser::addCoupon(2366, $this->user_info['id'], 'IT家电628超市品类5元红包', date('Y-m-d H:i:s'), '2016-06-28 23:59:59');
    			$result4 = CouponUser::addCoupon(2367, $this->user_info['id'], 'IT家电628眼镜品类10元红包', date('Y-m-d H:i:s'), '2016-06-28 23:59:59');
    			if($result1 || $result2){
    				$prizesUser = new PrizesUser();
    				$prizesUser->user_id = $this->user_info['id'];
    				$prizesUser->activity_id = $activityId;
    				$prizesUser->prize_id = $prizeIds[0];
    				$prizesUser->is_mobile = 0; // PC端抽奖
    				$prizesUser->time_added = date('Y-m-d H:i:s');
    				$prizesUser->save();
    				$result['status'] = 0;
    				$result['message'] = 'OK';
    			}
    		}else{
    			$result['status'] = -1;
    			$result['message'] ='抱歉，已参加过此活动';
    		}
    	}else{
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    	return  json_encode($result);
    }
    
    /**
     * @desc 预热头部
     * @return string
     */
    protected function get628Top($floorId){
    	return $this->renderPartial('628/top',[
    			'floorId' => $floorId,
    	]);
    }
    
    /**
     * @desc 628 
     * @param unknown $floorId
     * @return string
     * @date: 2016-6-27 下午2:28:29
     */
    protected function get628CloudStore($floorId){
    	return $this->renderPartial('628/cloud-store',['floorId' => $floorId]);
    }
    
    /**
     * @desc 628 
     * @param unknown $floorId
     * @return string
     * @date: 2016-6-27 下午2:28:29
     */
    protected function get628BabyLottery($floorId){
        return $this->renderPartial('628/baby-lottery',['floorId' => $floorId]);
    }

        /**
      * @desc 628母婴转盘
      * @return boolean|string
      */
     public function actionAjax628BabyZhuanpan() {
     	// 是否是ajax请求
     	if(!Yii::$app->request->getIsAjax()) return false;
     	$activeId = 656;    // 活动id
     	$prizeIds = [206,207,208,209,210,211];

        // 有效性检测
     	$check = $this->validCheck($activeId,true);
     	$checkStatus = PrizesOrder::find()->select('id')->where(['user_id'=>$this->user_info['id'],'mall_id'=>3,'type'=>2])->asArray()->scalar();
     	if(!$checkStatus){
     		$result['status'] = -1;
     		$result['message'] = '对不起，您未获得抽奖资格';
     		return json_encode($result);
     	}
     	if($check['status']) {
     
     		// 执行抽奖逻辑
     		$result = $this->runLottery($activeId,$prizeIds,'0','1');
     
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
     	} else {
     		$result['status'] = -1;
     		$result['message'] = $check['message'];
     	}
     
     	return json_encode($result);
     }
     
     /**
      * @desc 手拎袋
      */
     protected function getHandBag(){
     	$appNum = ActiveModelItems::getUserInfoByGoodsId(511658) + 1293;
     	$miNum = ActiveModelItems::getUserInfoByGoodsId(511657) + 1058;
     	$huaNum = ActiveModelItems::getUserInfoByGoodsId(511656) + 985;
     	$orderInfo = f_c('hand-bag-order-info');
     	if(empty($orderInfo)){
     		$orderInfo = ActiveModelItems::getOrderInfo();
     		f_c('hand-bag-order-info',$orderInfo,300); // 十分钟缓存
     	}
      	return $this->renderPartial('630/bag',['appNum'=>$appNum,
      										   'miNum'=>$miNum,
      										   'huaNum'=>$huaNum,
      										   'orderInfo'=>$orderInfo
      	]);
     }
     
    /**
     * @desc 706 
     * @param unknown $floorId
     * @return 领取红包
     * @date: 2016-7-6 上午10:28:29
     */
     protected function get706WtCoupon($floorId){
         return $this->renderPartial('706/wt-coupon',['floorId'=>$floorId]);
     }
     
    /**
     * @desc 706 
     * @param unknown $floorId
     * @return 领取红包
     * @date: 2016-7-6 下午2:28:29
     */
     public function actionAjax706WtCoupon(){
         $activityId = 725;
         $prizeIds = [-2222];
         $check = $this->validCheck($activityId,true); //有效检测
         $lotcheck = PrizesUser::find()->select('id')->where(['activity_id'=>$activityId,'user_id'=>$this->user_info['id']])->andWhere(['in','prize_id',$prizeIds])->scalar();
         if($check['status']){
                   if($lotcheck ===false){
                       $result1 =  CouponUser::addCoupon(2394, $this->user_info['id'], '8元文体办公商城全品类红包', date('Y-m-d H:i:s'), '2016-07-07 23:59:59') ;
                       $result2 =  CouponUser::addCoupon(2393, $this->user_info['id'], '10元复印纸红包', date('Y-m-d H:i:s'), '2016-07-07 23:59:59') ;
                       $result3 =  CouponUser::addCoupon(2392, $this->user_info['id'], '30元验钞机红包', date('Y-m-d H:i:s'), '2016-07-07 23:59:59') ;
                       if($result1 || $result2 || $result3  ){
                                $prizesUser = new PrizesUser();
   	    			$prizesUser->user_id = $this->user_info['id'];
   	    			$prizesUser->activity_id = $activityId;
   	    			$prizesUser->prize_id = $prizeIds[0];
   	    			$prizesUser->is_mobile = 0;  // PC端抽奖
   	    			$prizesUser->time_added = date('Y-m-d H:i:s');
   	    			$prizesUser->save();
   	    			$result['status'] = 0;
   	    			$result['message'] = 'OK';
                        }
                   }else{
                       $result['status'] = -1;
                       $result['message'] ='对不起，您已经参与过此活动';
                   } 
             }else{
                 $result['status'] = -1;
                 $result['message'] =$check['message'];
             }
             return json_encode($result);
     }
     
     /**
      * @desc 706母婴动态头部
      */
     protected function get706BabyTop($floorId){
     	return $this->renderPartial('706/top',[
     			'floorId' => $floorId,
     	]);
     }
     
     /**
      * @desc 706通讯动态头部
      */
     protected function get706TxTop($floorId){
     	return $this->renderPartial('706/tx_top',[
     			'floorId' => $floorId,
     	]);
     }
     
     /**
      * @desc 706家电动态头部
      */
     protected function get706JdTop($floorId){
     	return $this->renderPartial('706/jd_top',[
     			'floorId' => $floorId,
     	]);
     }
     
     /**
      * @desc 706文体动态头部
      */
     protected function get706WtTop($floorId){
     	return $this->renderPartial('706/wt_top',[
     			'floorId' => $floorId,
     	]);
     }
             
     
     /**
      * @Description: 7.6/7.7号活动领红包活动
      * @return: 红包领取结果
      * @author: yazhou.miao
      * @date: 2016-7-5 下午1:51:58
      */
     public function actionAjax7067GetCoupon() {
     	// 是否是ajax请求
     	if(!Yii::$app->request->getIsAjax()) return false;
     	
     	$activityId = 717;
     	$now = date('Y-m-d H:i:s');
     	if($now < '2016-07-07 00:00:00'){
     		$prizeIds = [218,219,220];
     	} else {
     		$prizeIds = [222,223,224];
     	}
     	
     	// 有效性检测
     	$check = $this->validCheck($activityId,true);
     	
     	if($check['status']) {
     		// 执行抽奖逻辑
     		$result = $this->runLottery($activityId,$prizeIds,'0','1');
     
     		if($result['status'] === 0){
     			$result['message'] = '恭喜您获得：' . $result['message'];
     		}
     	} else {
     		$result['status'] = -1;
     		$result['message'] = $check['message'];
     	}
     
     	return json_encode($result);
     }
     
     /**
      * @Description: 7.13/7.14号活动领红包活动
      * @return: 归巢行好运 求签
      * @author: yan.zhang
      * @date: 2016-7-12 上午10:18:58
      */
     protected function get713Divination(){
         return $this->renderPartial('713/divination');
     }
     
     /**
      * @Description: 7.13/7.14号活动领红包活动
      * @return: 归巢行好运 求签
      * @author: yan.zhang
      * @date: 2016-7-12 上午10:18:58
      */
     public function actionAjax713Divination(){
         $activityId = 773 ;
         $prizeIds = [237,239,240];
         $check =$this->validCheck($activityId,true);
         $res = ActiveModelItems::isCheckCoupon(3, '2016-05-12 00:00:00', $this->user_info['id']);
         
         if($res !==false){
            $result['status'] = -1;
            $result['message'] = '老会员请参加商城其他活动';
            return json_encode($result);
        }
        if($check['status']){
            $result = $this->runLottery($activityId, $prizeIds ,'0' ,'1');
                if($result['status'] === 0){
                    $result['message'] =$result['message'];
                    // 确认奖品在转盘中的位置
                    $counter = 0;
                    foreach($prizeIds as $prizeId){
                            $counter++;
                            if($result['prizeId'] === $prizeId){
                                    break;
                            }
                    }
                    // 红包的位置
                    $result['position'] = $counter;
                }
        }else{
            $result['message'] = $check['message'];
            $result['status'] = -1;
        }
        return json_encode($result);
     }
     
     /**
      * @Description: 7.13/7.14号活动领红包活动
      * @return: 归巢行好运 求签
      * @author: yan.zhang
      * @date: 2016-7-12 上午10:18:58
      */
     protected function get713Divination2(){
     	return $this->renderPartial('713/divination2');
     }
      
     /**
      * @Description: 7.13/7.14号活动领红包活动
      * @return: 归巢行好运 求签
      * @author: yan.zhang
      * @date: 2016-7-12 上午10:18:58
      */
     public function actionAjax713Divination2(){
     	$activityId = 781 ;
     	$prizeIds = [241,242,243];
     	$check =$this->validCheck($activityId,true);
     	$res = ActiveModelItems::isCheckCoupon(3, '2016-05-12 00:00:00', $this->user_info['id']);
     	 
     	if($res !==false){
     		$result['status'] = -1;
     		$result['message'] = '老会员请参加商城其他活动';
     		return json_encode($result);
     	}
     	if($check['status']){
     		$result = $this->runLottery($activityId, $prizeIds ,'0' ,'1');
     		if($result['status'] === 0){
     			$result['message'] =$result['message'];
     			// 确认奖品在转盘中的位置
     			$counter = 0;
     			foreach($prizeIds as $prizeId){
     				$counter++;
     				if($result['prizeId'] === $prizeId){
     					break;
     				}
     			}
     			// 红包的位置
     			$result['position'] = $counter;
     		}
     	}else{
     		$result['message'] = $check['message'];
     		$result['status'] = -1;
     	}
     	return json_encode($result);
     }
     
     /**
      * @Description: 7.13号活动领红包活动
      * @return: 红包领取结果
      * @author: yazhou.miao
      * @date: 2016-7-12 下午1:51:58
      */
     public function actionAjax713GetCoupon() {
     	// 是否是ajax请求
     	if(!Yii::$app->request->getIsAjax()) return false;
     
     	// TODO:修改活动ID/红包ID
     	$activityId = 775;
     	
     	if(f_get('type',1) == 1){
     		$prizeIds = [234,235,236];
     	} else {
     		$prizeIds = [230,231,232];
     	}
     
     	// 有效性检测
     	$check = $this->validCheck($activityId,true);
     
     	if($check['status']) {
     		// 执行抽奖逻辑
     		$result = $this->runLottery($activityId,$prizeIds,'0','1');
     		 
     		if($result['status'] === 0){
     			$result['message'] = '恭喜您获得：' . $result['message'];
     		}
     	} else {
     		$result['status'] = -1;
     		$result['message'] = $check['message'];
     	}
     	
     	return json_encode($result);
     }
     
     /**
      * @desc 706文体动态头部
      */
     protected function get713BabyTop($floorId){
     	return $this->renderPartial('713/baby_top',[
     			'floorId' => $floorId,
     	]);
     }

     
     /**
      * @Description: 7.14号 文体开箱寻宝送红包活动
      * @return: 红包领取结果
      * @author: yan.zhang
      * @date: 2016-7-13 下午1:44:58
      */ 
     protected function get713WtCoupon(){
         return $this->renderPartial('713/wt-coupon');
     }
     
     /**
      * @Description: 7.14号 文体开箱寻宝送红包活动
      * @return: 红包领取结果
      * @author: yan.zhang
      * @date: 2016-7-13 下午1:44:58
      */
     public function actionAjax713WtCoupon(){
         if(!Yii::$app->request->getIsAjax()) return false;
         $user_info = $this->user_info;
         $activityId = 768 ;
         $prizeIds = [244,245];
         $startTime = '2016-07-14 00:00:00';
         $endTime = '2016-07-15 00:00:00';
         $check =$this->validCheck($activityId,true);
         
        if($check['status']){
            $result = $this->runLottery($activityId, $prizeIds ,'0' ,'1');
                if($result['status'] === 0){
                    
                        if(244 == $result['prizeId']){ // 45发送大礼包
                                // 30元验钞机红包
                                CouponUser::addCoupon(2420, $user_info['id'], '文体办公商城714大促',$startTime,$endTime,'	点钞机30元劵');
                                // 10元复印纸红包
                                CouponUser::addCoupon(2393, $user_info['id'], '文体办公商城714大促',$startTime,$endTime,'	复印纸10元劵');
                                // 5元文体全品类红包
                                CouponUser::addCoupon(2345, $user_info['id'], '文体办公商城714大促',$startTime,$endTime,'文体全品类5元劵');
                        }else if(245 == $result['prizeId']){ // 18发送大礼包
                                // 10元复印纸红包
                                CouponUser::addCoupon(2393, $user_info['id'], '文体办公商城714大促',$startTime,$endTime,'	复印纸10元劵');
                                // 8元文体全品类红包
                                CouponUser::addCoupon(2394, $user_info['id'], '文体办公商城714大促',$startTime,$endTime,'	文体全品类8元劵');
                        }
                    
                    $result['message'] =$result['message'];
                    // 确认奖品在转盘中的位置
                    $counter = 0;
                    foreach($prizeIds as $prizeId){
                            $counter++;
                            if($result['prizeId'] === $prizeId){
                                    break;
                            }
                    }
                    // 红包的位置
                    $result['position'] = $counter;
                }
        }else{
            $result['message'] = $check['message'];
            $result['status'] = -1;
        }
        return json_encode($result);
     }

     
     /**
      * @desc 706文体动态头部
      */
     protected function get713ItTop($floorId){
     	return $this->renderPartial('713/it_top',[
     			'floorId' => $floorId,
     	]);
     }
     
     /**
      * @desc 706文体动态头部
      */
     protected function get713WtTop($floorId){
     	return $this->renderPartial('713/wt_top',[
     			'floorId' => $floorId,
     	]);
     }
     
     /**
      * @desc 706文体动态头部
      */
     protected function get713TxTop($floorId){
     	return $this->renderPartial('713/tx_top',[
     			'floorId' => $floorId,
     	]);
     }

     /**
      * @Description: 
      * @return: return_type
      * @author: yazhou.miao
      * @date: 2016-7-14 下午1:39:31
      */
     protected function get715TxRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-07-15 00:00:00';
    	$endTime   = '2016-07-17 23:59:59';
    	
    	$result = f_c('activity-715-tx-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime,[], [1], [22,321]);
    		f_c('activity-715-tx-rank',$result,10*60); // 十分钟缓存
    	}
    	
    	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime,[], [1], [22,321]);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    	
    	$list = array_slice($result,0,20); // 取前20个
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前20名
    	if($counter > 20){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    	
    	// 页面展示
    	return $this->renderPartial('715/tx-rank',[
    		'floorId'  => $floorId,
    		'list'     => $list, 
    		'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }

    /**
     * @Description: 老虎机页面
     * @return: 页面渲染
     * @author: yazhou.miao
     * @date: 2016-7-18 下午1:58:19
     */
    public function get719Slots($floorId) {
    	// 页面展示
    	return $this->renderPartial('718/slots',[
    		'floorId'  => $floorId,
    	]);
    }
    
    /**
     * @Description: 7.21预热老虎机处理
     * @return: json
     * @author: yazhou.miao
     * @date: 2016-7-18 下午3:25:00
     */
    public function actionAjax719Slots() {
    	// 是否是ajax请求
    	if(!Yii::$app->request->getIsAjax()) return false;
    	
    	$activityId = 828;
    	$prizes = [258,251,252,253,254,255,256,257];// 奖品
    	 
    	// 有效性检测
    	$check = $this->validCheck($activityId,true);
    	
    	if($check['status']) {
    
    		// 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizes,'0','1');
    
    		if($result['status'] === 0){
    			$result['message'] = '恭喜您获得：' . $result['message'];
    
    			// 确认奖品的位置
    			$counter = -1;
    			foreach($prizes as $prizeId){
    				$counter++;
    				if($result['prizeId'] === $prizeId){
    					break;
    				}
    			}
    			$result['position'] = $counter;
    		}
    	} else {
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    
    	return json_encode($result);
    }
    
    /*
     * 718 预热头部动态
     */
    protected function get718Top($floorId){
        $activityId = 828;
        $prizeIds = [246,247,248,249];
        $check = $this->validCheck($activityId,true);
        if($check['status']){
            $result = $this->runLottery($activityId, $prizeIds,'0','1');
            if($result['status'] === 0){
                $coupon = '恭喜您获得:' . $result['message'];
            }
        }
        return $this->renderPartial('718/top',[
                                    'floorId'=>$floorId,
                                    'coupon'=>  isset($coupon) ? $coupon:'',
                ]);
    }
    
    /**
     * @Description: 7.18 云店预热
     * @return: 渲染页面
     * @date: 2016-7-18 上午09:40:27
     */
    protected function get718Cloud(){
    	$province = $this->user_info['province'];
    	$provinces = [16,31,3,13];
    	return $this->renderPartial('718/cloud',[
    			'province'=>$province,
    			'provinces'=>$provinces,
    	]);
    }

    /**
     * @Description: 7.21通讯排行榜(智能机)
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-7-20 上午9:48:53
     */
    protected function get721TxSmartRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-07-21 00:00:00';
    	$endTime   = '2016-07-21 23:59:59';
    	 
    	$result = f_c('activity-721-tx-smart-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getAmountRankByMall($mallId, 0, $startTime, $endTime, [], [1]);
    		f_c('activity-721-tx-smart-rank',$result,10*60); // 十分钟缓存
    	}
    	 
    	$userResult = ActiveModelItems::getAmountByUserId($this->user_info['id'], $mallId, $startTime, $endTime, [1]);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    	 
    	$list = array_slice($result,0,10); // 取前10个
    	$newList = [
    			['user_id'=>'87690','phone'=>'13305176728','amount'=>'100'],
     			['user_id'=>'146682','phone'=>'15951862021','amount'=>'80'],
     			['user_id'=>'175411','phone'=>'15366163873','amount'=>'48'],
    	];
    	$list1 = [];
    	$list = array_merge($list,$newList);
    	foreach ($list as $key=>$val){
    		$list1[$val['amount']] = $list[$key];
    	}
    	krsort($list1);
    	$list1 = array_values($list1);
    	$list = array_slice($list1,0,10);
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前10名
    	if($counter > 10){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    	 
    	// 页面展示
    	return $this->renderPartial('721/tx-smart-rank',[
	    	'floorId'  => $floorId,
	    	'list'     => $list,
	    	'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }
    
    /**
     * @Description: 7.21通讯排行榜（功能机）
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-7-20 上午9:48:53
     */
    protected function get721TxFeatureRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-07-21 00:00:00';
    	$endTime   = '2016-07-21 23:59:59';
    
    	$result = f_c('activity-721-tx-feature-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getAmountRankByMall($mallId, 0, $startTime, $endTime, [], [2]);
    		f_c('activity-721-tx-feature-rank',$result,10*60); // 十分钟缓存
    	}
    
    	$userResult = ActiveModelItems::getAmountByUserId($this->user_info['id'], $mallId, $startTime, $endTime, [2]);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    
    	$list = array_slice($result,0,10); // 取前10个
    	$newList = [
    			['user_id'=>'264358','phone'=>'18626464879','amount'=>'220'],
    			['user_id'=>'146682','phone'=>'15951862021','amount'=>'150'],
    			['user_id'=>'175411','phone'=>'15366163873','amount'=>'91'],
    	];
    	$list1 = [];
    	$list = array_merge($list,$newList);
    	foreach ($list as $key=>$val){
    		$list1[$val['amount']] = $list[$key];
    	}
    	krsort($list1);
    	$list1 = array_values($list1);
    	$list = array_slice($list1,0,10);
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前10名
    	if($counter > 10){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    
    	// 页面展示
    	return $this->renderPartial('721/tx-feature-rank',[
    			'floorId'  => $floorId,
    			'list'     => $list,
    			'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }

    /**
     * @Description: 7.21通讯排行榜（配件）
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-7-20 上午9:48:53
     */
    protected function get721TxPartsRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-07-21 00:00:00';
    	$endTime   = '2016-07-21 23:59:59';
    
    	// 配件类型
    	$types = [27,29,33,35,37,42,48,52,56,58,59,62,67,69,72,73,74,75,76,77];
    	
    	$result = f_c('activity-721-tx-parts-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime, [], $types);
    		f_c('activity-721-tx-parts-rank',$result,10*60); // 十分钟缓存
    	}
    
    	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime,$types);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    
    	$list = array_slice($result,0,10); // 取前10个
    	$newList = [
    			['user_id'=>'169582','phone'=>'15850728260','amount'=>'8653'],
    			['user_id'=>'146682','phone'=>'15951862021','amount'=>'4985'],
    			['user_id'=>'175411','phone'=>'15366163873','amount'=>'3526'],
    	];
    	$list1 = [];
    	$list = array_merge($list,$newList);
    	foreach ($list as $key=>$val){
    		$list1[$val['amount']] = $list[$key];
    	}
    	krsort($list1);
    	$list1 = array_values($list1);
    	$list = array_slice($list1,0,10);
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前10名
    	if($counter > 10){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    
    	// 页面展示
    	return $this->renderPartial('721/tx-parts-rank',[
    			'floorId'  => $floorId,
    			'list'     => $list,
    			'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }
    
    /*
     * 721 it家电预热头部动态
     */
    protected function get721JdTop($floorId){
       
        return $this->renderPartial('721/jd-top',[
                                    'floorId'=>$floorId,
                ]);
    }
    
    /**
     * @Description: 7.21 it家电意见领取红包  南京仓
     * @return: 渲染页面
     * @date: 2016-7-19 下午14:23:27
     */
    protected function get721JdCoupon1($floorId){
        return $this->renderPartial('721/jd-coupon1',['floorId'=>$floorId]);
    }
    
    /**
     * @Description: 7.21 it家电意见领取红包 南京仓
     * @return: 渲染页面
     * @date: 2016-7-19 下午14:23:27
     */
    public function actionAjax721JdCoupon1(){
       if(!Yii::$app->request->getIsAjax()) return false;
        $activityId = 842 ;
        $prizeIds = [-3333];
        $check = $this->validCheck($activityId , true);
        $lotCheck = PrizesUser::find()->select('id')->where(['user_id'=>$this->user_info['id'],'activity_id'=>$activityId])->andWhere(['in','prize_id',$prizeIds])->scalar();
        if($check['status']){
            if($lotCheck === false){
                $result1 = CouponUser::addCoupon(2454, $this->user_info['id'], 'IT家电全场通用8元券', date('Y-m-d H:i:s'), '2016-07-21 23:59:59','IT家电全场通用8元券');
                $result2 = CouponUser::addCoupon(2453, $this->user_info['id'], '51优品通用10元券', date('Y-m-d H:i:s'), '2016-07-21 23:59:59','51优品通用10元券');
                if($result1 || $result2){
                    $prizeUser = new PrizesUser();
                    $prizeUser->user_id = $this->user_info['id'];
                    $prizeUser->activity_id = $activityId;
                    $prizeUser->prize_id = $prizeIds[0];
                    $prizeUser->is_mobile = 0 ;
                    $prizeUser->time_added = date('Y-m-d H:i:s');
                    $prizeUser->save();
                    $result['status'] = 0;
                    $result['message'] ='OK';
                }
            }else{
                $result['status'] = -1;
                $result['message'] = '对不起，您已参加过此活动';
            }
        }else{
            $result['status'] = -1;
            $result['message'] = $check['message'];
        }
        return json_encode($result);
    }
    
    /**
     * @Description: 7.21 it家电意见领取红包  其它仓
     * @return: 渲染页面
     * @date: 2016-7-19 下午14:23:27
     */
    protected function get721JdCoupon2($floorId){
        return $this->renderPartial('721/jd-coupon2',['floorId'=>$floorId]);
    }
    
    /**
     * @Description: 7.21 it家电意见领取红包 南京仓
     * @return: 渲染页面
     * @date: 2016-7-19 下午14:23:27
     */
    public function actionAjax721JdCoupon2(){
       if(!Yii::$app->request->getIsAjax()) return false;
        $activityId = 843 ;
        $prizeIds = [-3333];
        $check = $this->validCheck($activityId , true);
        $lotCheck = PrizesUser::find()->select('id')->where(['user_id'=>$this->user_info['id'],'activity_id'=>$activityId])->andWhere(['in','prize_id',$prizeIds])->scalar();
        if($check['status']){
            if($lotCheck === false){
                $result1 = CouponUser::addCoupon(2454, $this->user_info['id'], 'IT家电全场通用8元券', date('Y-m-d H:i:s'), '2016-07-21 23:59:59','IT家电全场通用8元券');
                $result2 = CouponUser::addCoupon(2453, $this->user_info['id'], '51优品通用10元券', date('Y-m-d H:i:s'), '2016-07-21 23:59:59','51优品通用10元券');
                if($result1 || $result2){
                    $prizeUser = new PrizesUser();
                    $prizeUser->user_id = $this->user_info['id'];
                    $prizeUser->activity_id = $activityId;
                    $prizeUser->prize_id = $prizeIds[0];
                    $prizeUser->is_mobile = 0 ;
                    $prizeUser->time_added = date('Y-m-d H:i:s');
                    $prizeUser->save();
                    $result['status'] = 0;
                    $result['message'] ='OK';
                }
            }else{
                $result['status'] = -1;
                $result['message'] = '对不起，您已参加过此活动';
            }
        }else{
            $result['status'] = -1;
            $result['message'] = $check['message'];
        }
        return json_encode($result);
    }
    
    /*
     * tx 721头部
     */
    protected function get721TxTop($floorId){
        return $this->renderPartial('721/tx-top',['floorId'=>$floorId]);
    }

    /*
     * tx 721头部
     */
    protected function get721WtTop($floorId){
        return $this->renderPartial('721/wt-top',['floorId'=>$floorId]);
    }
    
    /*
     * baby 721头部
     */
    protected function get721BabyTop($floorId){
        return $this->renderPartial('721/baby-top',['floorId'=>$floorId]);
    }
    
    /* 
     * 海购领红包
     */
    protected function get721SeasCoupon(){
        $user_info = $this->user_info;
        $activityId = 836 ;
        $prizeIds = [263];
        $startTime = '2016-07-21 00:00:00';
        $endTime = '2016-08-11 00:00:00';
        $check = $this->validCheck($activityId,true);
        if($check['status']) {
                    // 执行抽奖逻辑
                    $result = $this->runLottery($activityId,$prizeIds,'0','1');

                    if($result['status'] === 0){
                            if(263 == $result['prizeId']){ // 发送大礼包
                                    // 母婴全品类券10元
                                    CouponUser::addCoupon(2410, $user_info['id'], '母婴全品类券10元',$startTime,$endTime,'母婴全品券10元');
                                    // 母婴51优品券28元
                                    CouponUser::addCoupon(2447, $user_info['id'], '母婴51优品券28元',$startTime,$endTime,'母婴51优品券28元');
                                    // 海购商品券30元
                                    CouponUser::addCoupon(2448, $user_info['id'], '海购商品券30元',$startTime,$endTime,'海购商品券30元');
                                    // 海购商品券20元
                                    CouponUser::addCoupon(2449, $user_info['id'], '海购商品券20元',$startTime,$endTime,'海购商品券20元');
                                    // 海购商品券10元
                                    CouponUser::addCoupon(2450, $user_info['id'], '海购商品券10元',$startTime,$endTime,'海购商品券10元');
                            } 

                            $coupon = '恭喜您获得：海购红包128元！！';
                    }
            } else {
                    $coupon = $check['message'];
            }
            
            return $this->renderPartial('721/seas-coupon',[
                   'coupon' => isset($coupon) ? $coupon : '',
                ]);
    }

    
    /**
     * @desc 文体砸金蛋活动
     * @param unknown $floorId
     * @return string
     */
    protected function get721WtEgg($floorId){
    	return $this->renderPartial('721/wt-egg',['floorId'=>$floorId]);
    }
    
    public function actionAjax721WtEgg() {
    	// 是否是ajax请求
    	if(!Yii::$app->request->getIsAjax()) return false;

    	$user_info = $this->user_info;
    	$activityId = 846;
    	$prizes = [259,260,261,262];// 奖品
    	$startTime = '2016-07-21 00:00:00';
    	$endTime = '2016-07-22 00:00:00';
    	// 有效性检测
    	$check = $this->validCheck($activityId,true);
    	 
    	if($check['status']) {
    
    		// 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizes,'0','1');
    
    		if($result['status'] === 0){
    			if(262 == $result['prizeId']){ // 发送大礼包
    				// 点钞机30元劵
    				CouponUser::addCoupon(2393, $user_info['id'], '10元复印纸红包',$startTime,$endTime);
    				CouponUser::addCoupon(2394, $user_info['id'], '文体办公商城628大促',$startTime,$endTime);
    			}
    			$result['message'] = '恭喜您获得：' . $result['message'];
    
    			// 确认奖品的位置
    			$counter = -1;
    			foreach($prizes as $prizeId){
    				$counter++;
    				if($result['prizeId'] === $prizeId){
    					break;
    				}
    			}
    			$result['position'] = $counter;
    		}
    	} else {
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    
    	return json_encode($result);
    }

    /**
     * @Description: 剪红包页面
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-7-25 下午5:14:34
     */
    public function get726CutCoupon($floorId) {
    	return $this->renderPartial('725/cut-coupon',['floorId'=>$floorId]);
    }
    
    /**
     * @Description: 7.26剪红包活动
     * @return: return_type
     * @author: yazhou.miao
     * @date: 2016-7-25 下午3:24:35
     */
    public function actionAjax726CutCoupon() {
    	// 是否是ajax请求
    	if(!Yii::$app->request->getIsAjax()) return false;
    	
    	// TODO:
    	$activityId = 886;
    	$prizes = [274,275,276,277,278];// 奖品

//     	$activityId = 5;
//     	$prizes = [11,13,39,40,41];// 奖品
    	 
    	// 有效性检测
    	$check = $this->validCheck($activityId,true);
    	
    	if($check['status']) {
    		// 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizes,'0','1');
    
    		if($result['status'] === 0){
    			$result['message'] = '恭喜您获得：' . $result['message'];
    		}
    	} else {
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    
    	return json_encode($result);
    }
    
    /**
     * @Description:51优品
     * @return: return_type
     * @author: yan.zhang
     * @date: 2016-7-25 下午15:25:35
     */
    protected function get725YouPin(){
    	$activityId = 886;
    	$prizeIds = [268,269,270];
    	$check = $this->validCheck($activityId,true);
    	if($check['status']){
    		$result = $this->runLottery($activityId, $prizeIds,'0','1');
    		if($result['status'] === 0){
    			$coupon = '恭喜您获得:' . $result['message'];
    		}
    	}
        $main_mall = StoreMall::getMainMall($this->user_info['id']);
        return $this->renderPartial('725/you-pin',['mainMall'=>$main_mall,'coupon'=>  isset($coupon) ? $coupon:'']);
    }
    
    
    /**
     * @Description: 7.28通讯排行榜(手机)
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-7-27 上午9:48:53
     */
    protected function get728TxPhoneRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-07-28 00:00:00';
    	$endTime   = '2016-07-29 00:00:00';
    
    	$result = f_c('activity-728-tx-phone-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getAmountRankByMall($mallId, 0, $startTime, $endTime, [], [1,2]);
    		f_c('activity-728-tx-phone-rank',$result,10*60); // 十分钟缓存
    	}
    
    	$userResult = ActiveModelItems::getAmountByUserId($this->user_info['id'], $mallId, $startTime, $endTime, [1,2]);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    
    	$list = array_slice($result,0,10); // 取前10个
    	$newList = [
    			['user_id'=>'264358','phone'=>'18626464879','amount'=>'264'],
    			['user_id'=>'146682','phone'=>'15951862021','amount'=>'181'],
    			['user_id'=>'175411','phone'=>'15366163873','amount'=>'91'],
    	];
    	$list1 = [];
    	$list = array_merge($list,$newList);
    	foreach ($list as $key=>$val){
    		$list1[$val['amount']] = $list[$key];
    	}
    	krsort($list1);
    	$list1 = array_values($list1);
    	$list = array_slice($list1,0,10);
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前10名
    	if($counter > 10){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    
    	// 页面展示
    	return $this->renderPartial('728/tx-phone-rank',[
    			'floorId'  => $floorId,
    			'list'     => $list,
    			'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }
    
    /**
     * @Description: 7.28通讯排行榜（配件）
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-7-27 上午9:48:53
     */
    protected function get728TxPartsRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-07-28 00:00:00';
    	$endTime   = '2016-07-29 00:00:00';
    
    	// 配件类型
    	$types = [27,29,33,35,37,42,48,52,56,58,59,62,67,69,72,73,74,75,76,77];
    	 
    	$result = f_c('activity-728-tx-parts-rank');
    	if(empty($result)){
    		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime, [], $types);
    		f_c('activity-728-tx-parts-rank',$result,10*60); // 十分钟缓存
    	}
    
    	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime,$types);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    
    	$list = array_slice($result,0,10); // 取前10个
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前10名
    	if($counter > 10){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    
    	// 页面展示
    	return $this->renderPartial('728/tx-parts-rank',[
    			'floorId'  => $floorId,
    			'list'     => $list,
    			'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }

    /**
     * @desc 刮刮乐
     */
    protected function get728Gua($floorId){
            $user_id = $this->user_info['id'];
            $activityId = 890;
            $prizesIds = [279];
    	return $this->renderPartial('728/gua1',['floorId'=>$floorId,'user_id'=>$user_id,'activityId'=>$activityId,'prizeIds'=>$prizesIds]);
    }
    
    public function actionAjax728Gua() {
    	// 是否是ajax请求
    	if(!Yii::$app->request->getIsAjax()) return false;
        
    	$user_info = $this->user_info;
    	$activityId = 890;
    	$prizes = [279];// 奖品
    	$startTime = '2016-07-28 00:00:00';
    	$endTime = '2016-07-29 00:00:00';
    	// 有效性检测
    	$check = $this->validCheck($activityId,true);
    
    	if($check['status']) {
    
    		// 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizes,'0','1');
    
    		if($result['status'] === 0){
    			if(279 == $result['prizeId']){ // 发送大礼包
    				// 点钞机30元劵
    				CouponUser::addCoupon(2454, $user_info['id'], 'IT家电通用类8元红包',$startTime,$endTime);
    				CouponUser::addCoupon(2477, $user_info['id'], 'IT家电51优品10元红包',$startTime,$endTime);
    			}
    		}
    	} else {
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    
    	return json_encode($result);
    }
    
    /**
     * @desc 刮刮乐
     */
    protected function get728Gua2($floorId){
    	$user_id = $this->user_info['id'];
    	$activityId = 891;
    	$prizesIds = [280];
    	return $this->renderPartial('728/gua22',['floorId'=>$floorId,'user_id'=>$user_id,'activityId'=>$activityId,'prizeIds'=>$prizesIds]);
    }
    
    public function actionAjax728Gua2() {
    	// 是否是ajax请求
    	if(!Yii::$app->request->getIsAjax()) return false;
    
    	$user_info = $this->user_info;
    	$activityId = 891;
    	$prizes = [280];// 奖品
    	$startTime = '2016-07-28 00:00:00';
    	$endTime = '2016-07-29 00:00:00';
    	// 有效性检测
    	$check = $this->validCheck($activityId,true);
    
    	if($check['status']) {
    
    		// 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizes,'0','1');
    
    		if($result['status'] === 0){
    			if(280 == $result['prizeId']){ // 发送大礼包
    				// 点钞机30元劵
    				CouponUser::addCoupon(2454, $user_info['id'], 'IT家电通用类8元红包',$startTime,$endTime);
    				CouponUser::addCoupon(2477, $user_info['id'], 'IT家电51优品10元红包',$startTime,$endTime);
    			}
    		}
    	} else {
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    
    	return json_encode($result);
    }
    
    /**
     * @desc 801预热
     * @return string
     */
    protected function get801WarmUp(){
    	$activityId = 941;
        $now = date('Y-m-d H:i:s');
        if($now >= '2016-08-02 00:00:00' && $now <= '2016-08-08 23:59:59'){
            $prizeIds = [289,290,291];
            
        }else if($now >= '2016-08-09 00:00:00' && $now <='2016-08-10 23:59:59'){
            $prizeIds = [308,309,310];
        }else{
            $prizeIds = [];
        }
    	
    	$check = $this->validCheck($activityId,true);
    	if($check['status']){
    		$result = $this->runLottery($activityId, $prizeIds,'0','1');
    		if($result['status'] === 0){
    			$coupon = '恭喜您获得:' . $result['message'];
    		}
    	}
       
    	// 判断属于哪个阶段的问题
    	$question = 0;
    	$now = date('Y-m-d H:i:s');
    	if($now >= '2016-08-02 00:00:00' && $now < '2016-08-05 00:00:00'){//第一阶段竞猜
    		$question = 1;
    	} elseif($now >= '2016-08-05 00:00:00' && $now < '2016-08-12 00:00:00') {//第二阶段竞猜
    		$question = 2;
    	} elseif($now >= '2016-08-12 00:00:00'){//第三阶段竞猜
    		$question = 3;
    	}
    	
    	// 用户最后一次竞猜结果
    	$query = ActivityQuestion::findOne(['ques_num'=>$question,'user_id' => $this->user_info['id']]);
    	//大促订货次数
    	$orderNum = ActiveModelItems::getBuyNum($this->user_info['id']);
    	//竞猜次数
    	$guessNum = ActivityQuestion::find()->select('id')->where(['user_id'=>$this->user_info['id']])->asArray()->count();
    	//猜中次数
    	$guessRightNum = ActiveModelItems::getGuessRightNum($this->user_info['id']);
    	//交易金额
    	$orderPrice = ActiveModelItems::getOrderInfoByTime($this->user_info['id'], '2016-08-04 00:00:00', '2016-08-18 00:00:00');
    	
    	return $this->renderPartial('801/warm',[
    			'question' => $question,
    			'result'   => $query ? $query->ans_num : -1,
    			'nowTime'  => $now,
    			'orderNum' => $orderNum,
    			'guessNum' => $guessNum,
    			'guessRightNum' => $guessRightNum,
    			'orderPrice' => $orderPrice,
    			'coupon'=>  isset($coupon) ? $coupon:''
		]);
    }
    

    /**
     * @Description: 红米
     * @return: 渲染页面
     * @author: yan.zhang
     * @date: 2016-8-01 上午10:48:53
     */
    protected function get801RedPro(){
        return $this->renderPartial('801/red-pro');
    }
    
    /**
     * @Description: 红米
     * @return: 渲染页面
     * @author: yan.zhang
     * @date: 2016-8-01 上午10:48:53
     */
    public function actionAjax801RedPro(){
        if(!Yii::$app->request->getIsAjax()) return false;
        $user_info = $this->user_info;
        $activityId = 942;
        $goods_id = '538789';
        $prizeIds = [286,287,288,285];
        $check = $this->validCheck($activityId ,true);
        $lotcheck = ActiveModelItems::getPreSale($user_info['id'], $goods_id);
        if($check['status']) {
                if($lotcheck === false){
                    $result['status'] = -1;
                    $result['message'] = '您未符合抽奖要求';
                }else{
                    // 执行抽奖逻辑
                    $result = $this->runLottery($activityId,$prizeIds,'0','1');

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
                }
     
            } else {
                    $result['status'] = -1;
                    $result['message'] = $check['message'];
            }
     
     	return json_encode($result);
        
    }
    
     /**
     * @Description: 红米预热其他活动
     * @return: 渲染页面
     * @author: yan.zhang
     * @date: 2016-8-01 上午10:48:53
     */
    protected function get801ProLink(){
        
        $user_id = $this->user_info['id'];
        $main_mall = StoreMall::getMainMall($user_id);
        $province = $this->user_info['province'];
        return $this->renderPartial('801/pro-link',['province'=>$province,'main_mall'=>$main_mall]);
    }

    
    /**
     * @Description: 用户提交竞猜答案
     * @return: 提交结果
     * @author: yazhou.miao
     * @date: 2016-8-1 上午11:01:49
     */
    public function actionAjax801Guess() {
    	// 是否是ajax请求
    	if(!Yii::$app->request->getIsAjax()) return false;
    	
//     	$activityId = 941;
    	$activityId = 1;
    	$json = [];
    	$userInfo = $this->user_info;
    	
    	$result   = f_post('result',-1);
    	$question = f_post('question',0);
    	
    	$now = date('Y-m-d H:i:s');
    	if(!($now >= '2016-08-02 00:00:00' && $now < '2016-08-04 00:00:00') //第一阶段竞猜
    	  && !($now >= '2016-08-05 00:00:00' && $now < '2016-08-11 00:00:00') //第二阶段竞猜
		  && !($now >= '2016-08-12 00:00:00' && $now < '2016-08-18 00:00:00')){ //第三阶段竞猜
    		$json['status'] = 0;
    		$json['msg'] = '抱歉，当前时间不能参与竞猜！';
    		
    		return json_encode($json);
    	}
    	
    	$query = ActivityQuestion::findOne(['user_id' => $userInfo['id'],'ques_num' => $question]);
    	if($query){ // 已经参与过此次竞猜
    		$json['status'] = 0;
    		$json['msg'] = '您已经参与过此次竞猜！';
    		
    		return json_encode($json);
    	}
    	
    	// 保存用户竞猜答案
    	$acQuestion = new ActivityQuestion();
    	$acQuestion->user_id = $userInfo['id'];
    	$acQuestion->ques_num = $question;
    	$acQuestion->ans_num = $result;
    	$acQuestion->add_time = date('Y-m-d H:i:s');
    	if($acQuestion->save()){
    		// 发红包
    		$mainMall = StoreMall::getMainMall($userInfo['id']); // 主商城
    		
    		if($now >= '2016-08-02 00:00:00' && $now < '2016-08-04 00:00:00'){
    			if(1 == $mainMall){ // 通讯
    				CouponUser::addCoupon(2117, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-02 00:00:00','2016-08-05 00:00:00','智能手机通用10元券');
    				CouponUser::addCoupon(2502, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-02 00:00:00','2016-08-05 00:00:00','国产精品智能手表5元券');
    			} elseif(3 == $mainMall) {// 母婴
    				CouponUser::addCoupon(2489, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-02 00:00:00','2016-08-05 00:00:00','洗护用品5元券');
    			} elseif(4 == $mainMall) {// IT家电
    				CouponUser::addCoupon(2502, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-02 00:00:00','2016-08-05 00:00:00','国产精品智能手表5元券');
    			} elseif(7 == $mainMall) {// 文体办公
    				CouponUser::addCoupon(2496, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-02 00:00:00','2016-08-05 00:00:00','中性笔品类5元劵');
    			}
    		} elseif($now >= '2016-08-05 00:00:00' && $now < '2016-08-11 00:00:00'){
    			if(1 == $mainMall){ // 通讯
    				CouponUser::addCoupon(2117, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-05 00:00:00','2016-08-12 00:00:00','智能手机通用10元券');
    				CouponUser::addCoupon(2503, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-05 00:00:00','2016-08-12 00:00:00','家用电器通用5元券');
    			} elseif(3 == $mainMall) {// 母婴
    				CouponUser::addCoupon(2490, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-05 00:00:00','2016-08-12 00:00:00','奶粉快乐3元券');
    			} elseif(4 == $mainMall) {// IT家电
    				CouponUser::addCoupon(2503, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-05 00:00:00','2016-08-12 00:00:00','家用电器通用5元券');
    			} elseif(7 == $mainMall) {// 文体办公
    				CouponUser::addCoupon(2497, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-05 00:00:00','2016-08-12 00:00:00','学习文具品类5元劵');
    			}
    		} elseif($now >= '2016-08-12 00:00:00' && $now < '2016-08-18 00:00:00'){
    			if(1 == $mainMall){ // 通讯
    				CouponUser::addCoupon(2117, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-12 00:00:00','2016-08-19 00:00:00','智能手机通用10元券');
    				CouponUser::addCoupon(2504, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-12 00:00:00','2016-08-19 00:00:00','路由器通用5元券');
    			} elseif(3 == $mainMall) {// 母婴
    				CouponUser::addCoupon(2491, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-12 00:00:00','2016-08-19 00:00:00','车床乐玩10元券');
    			} elseif(4 == $mainMall) {// IT家电
    				CouponUser::addCoupon(2504, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-12 00:00:00','2016-08-19 00:00:00','路由器通用5元券');
    			} elseif(7 == $mainMall) {// 文体办公
    				CouponUser::addCoupon(2498, $userInfo['id'], '添“金”送豪礼，相约奥运“惠”大促预热','2016-08-12 00:00:00','2016-08-19 00:00:00','美术用品5元劵');
    			}
    		}
    		
    		$json['status'] = 1;
    		$json['msg'] = '提交成功！红包已经奉上喽~~~';
    	}else{
    		$json['status'] = -1;
    		$json['msg'] = '提交失败，请重新提交！';
    	}
    	
    	return json_encode($json);
    }
    
    /**
     * @desc 803大促
     * @return string
     */
    protected function get803ItCoupon($floorId){
            $user_id = $this->user_info['id'];
            $activityId = 954;
        return $this->renderPartial('803/it-coupon',['floorId'=>$floorId,'activityId'=>$activityId,'user_id'=>$user_id]);            
    }
    
    /**
     * @desc 803大促
     * @return string
     */
    public function actionAjax803ItCoupon(){
        if(!Yii::$app->request->getIsAjax()) return false;
        // 活动id
        $activityId = 954;
        $type = f_post('type',0);
        if (1 == $type){
        	$prizeIds = [298];
        }elseif (2 == $type){
        	$prizeIds = [300];
        }elseif (3 == $type){
        	$prizeIds = [302];
        }
        
        //有效检测
        $check = $this->validCheck($activityId,true);

        if($check['status']){
            // 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizeIds,'0','1');
    
    		if($result['status'] === 0){
    		    $result['message'] = '恭喜您获得：' . $result['message'];
    		}
        }else{
            $result['status'] = -1;
            $result['message'] = $check['message'];
        }
        return  json_encode($result);
    }
    
    /**
     * @desc 803大促
     * @return string
     */
    protected function get803ItCoupon2($floorId){
    	$user_id = $this->user_info['id'];
    	$activityId = 955;
    	return $this->renderPartial('803/it-coupon2',['floorId'=>$floorId,'activityId'=>$activityId,'user_id'=>$user_id]);
    }
    
    /**
     * @desc 803大促
     * @return string
     */
    public function actionAjax803ItCoupon2(){
    	if(!Yii::$app->request->getIsAjax()) return false;
    	// 活动id
    	$activityId = 955;
    	$type = f_post('type',0);
    	if (1 == $type){
    		$prizeIds = [299];
    	}elseif (2 == $type){
    		$prizeIds = [301];
    	}elseif (3 == $type){
    		$prizeIds = [303];
    	}
    
    	//有效检测
    	$check = $this->validCheck($activityId,true);
    
    	if($check['status']){
    		// 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizeIds,'0','1');
    
    		if($result['status'] === 0){
    			$result['message'] = '恭喜您获得：' . $result['message'];
    		}
    	}else{
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    	return  json_encode($result);
    }
    
    /**
     * @desc 文体红包
     * @param unknown $floorId
     * @return string
     */
    protected function get803WtCoupon($floorId){
    	return $this->renderPartial('803/wt-coupon',['floorId'=>$floorId]);
    }
     
    /**
     * @desc 文体红包
     * @return string
     */
    public function actionAjax803WtCoupon(){
    	$user_info = $this->user_info;
    	$activityId = 956;
    	$prizes = [295];// 奖品
    	$startTime = '2016-08-04 00:00:00';
    	$endTime = '2016-08-05 00:00:00';
    	// 有效性检测
    	$check = $this->validCheck($activityId,true);
    
    	if($check['status']) {
    
    		// 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizes,'0','1');
    
    		if($result['status'] === 0){
    			if(295 == $result['prizeId']){ // 发送红包
    				
    				CouponUser::addCoupon(2394, $user_info['id'], '8元文体办公全品类红包',$startTime,$endTime);
    				CouponUser::addCoupon(2519, $user_info['id'], '修正用品5元红包',$startTime,$endTime);
    				CouponUser::addCoupon(2393, $user_info['id'], '复印纸10元红包',$startTime,$endTime);
    			}
    		}
    	} else {
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    
    	return json_encode($result);
    }
    
    /**
     * @Description: 8.4通讯排行榜
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-8-3 上午9:48:53
     */
    protected function get803TxRank($floorId) {
    	$mallId = 1;
    	// 订单起止时间
    	$startTime = '2016-08-04 00:00:00';
    	$endTime   = '2016-08-05 00:00:00';
    	
    	// 配件类型
    	$types = [27,29,33,35,37,42,48,52,56,58,59,62,67,69,72,73,74,75,76,77];
    	
    	$result = f_c('activity-803-tx-rank');
    	if(empty($result)){
    		
    		$result = ActiveModelItems::getSalesRankByMall($mallId, 0, $startTime, $endTime, [], $types);
    		f_c('activity-803-tx-rank',$result,10*60); // 十分钟缓存
    	}
    	 
    	$userResult = ActiveModelItems::getSalesByUserId($this->user_info['id'], $mallId, $startTime, $endTime, $types);
    	$userAmount = $userResult['amount'] == null ? 0 : $userResult['amount'];
    	 
    	$list = array_slice($result,0,10); // 取前10个
    	$newList = [
    			['user_id'=>'264358','phone'=>'18626464879','amount'=>'4810'],
    			['user_id'=>'146682','phone'=>'15951862021','amount'=>'3250'],
    			['user_id'=>'175411','phone'=>'15366163873','amount'=>'2482'],
    	];
    	$list1 = [];
    	$list = array_merge($list,$newList);
    	foreach ($list as $key=>$val){
    		$list1[$val['amount']] = $list[$key];
    	}
    	krsort($list1);
    	$list1 = array_values($list1);
    	$list = array_slice($list1,0,10);
    	$counter = 1;
    	foreach($list as $value){
    		if($value['user_id'] == $this->user_info['id']) break;
    		$counter++;
    	}
    	// 此用户不在前10名
    	if($counter > 10){
    		$rank = 1;
    		foreach($result as $v){
    			if($userAmount >= $v['amount']) break;
    			$rank++;
    		}
    	} else {
    		$rank = $counter; // 排名
    	}
    	 
    	// 页面展示
    	return $this->renderPartial('803/tx-rank',[
    			'floorId'  => $floorId,
    			'list'     => $list,
    			'rank'     => $userAmount ? $rank : false, // 没有下单则不排名
    	]);
    }
    
    /**
     * @Description: 8.5通讯“相约里约”活动
     * @return: 页面
     * @author: yazhou.miao
     * @date: 2016-8-4 下午2:13:06
     */
    protected function get805TxProducts($floorId) {
    	// 产品列表
    	$products = [
			3 => [  // 安徽
				1 => [
					'name'     => '维图GT13',
					'title'    => '2+16G超大内存',
	    			'img_url'  => '/images/activity/805/az1.jpg',
	    			'link_url' => '/site/detail?id=502880',
    			],
    			2 => [
	    			'name'     => '荣耀8',
	    			'title'    => '全网通版4GB/32GB ',
	    			'img_url'  => '/images/activity/805/az2.jpg',
	    			'link_url' => '/supplier-goods/index?id=31846&mall_id=1',
    			],
    			3 => [
	    			'name'     => '乐视2(X620)',
	    			'title'    => '全网通版32GB',
	    			'img_url'  => '/images/activity/805/az3.jpg',
	    			'link_url' => '/site/detail?id=349821',
    			],
    			4 => [
	    			'name'     => '红米手机3S',
	    			'title'    => '全网通版2GB+16GB',
	    			'img_url'  => '/images/activity/805/az4.jpg',
	    			'link_url' => '/supplier-goods/index?id=28134&mall_id=1',
    			],
    		],
    		11 => [  // 河南
	    		1 => [
		    		'name'     => '维图GT13',
		    		'title'    => '2+16G超大内存',
		    		'img_url'  => '/images/activity/805/hn1.jpg',
		    		'link_url' => '/site/detail?id=502881',
	    		],
	    		2 => [
		    		'name'     => '乐视2(X620)',
		    		'title'    => '全网通版32GB',
		    		'img_url'  => '/images/activity/805/hn2.jpg',
		    		'link_url' => '/supplier-goods/index?id=19745&mall_id=1',
	    		],
	    		3 => [
		    		'name'     => '红米手机3S',
		    		'title'    => '全网通版2GB+16GB',
		    		'img_url'  => '/images/activity/805/hn3.jpg',
		    		'link_url' => '/supplier-goods/index?id=28134&mall_id=1',
	    		],
	    		4 => [
		    		'name'     => '乐视2(X620)',
		    		'title'    => '全网通版16GB',
		    		'img_url'  => '/images/activity/805/hn4.jpg',
		    		'link_url' => '/supplier-goods/index?id=23768&mall_id=1',
	    		],
    		],
    		13 => [  // 湖北
	    		1 => [
		    		'name'     => '维图GT13',
		    		'title'    => '2+16G超大内存',
		    		'img_url'  => '/images/activity/805/hb1.jpg',
		    		'link_url' => '/site/detail?id=502880',
	    		],
	    		2 => [
		    		'name'     => '瓦戈启凡',
		    		'title'    => '2+16G超大内存',
		    		'img_url'  => '/images/activity/805/hb2.jpg',
		    		'link_url' => '/site/detail?id=398523',
	    		],
	    		3 => [
		    		'name'     => '乐视2(X620)',
		    		'title'    => '全网通版32GB',
		    		'img_url'  => '/images/activity/805/hb3.jpg',
		    		'link_url' => '/site/detail?id=522897',
	    		],
	    		4 => [
		    		'name'     => '荣耀8',
		    		'title'    => '全网通版4GB/32GB ',
		    		'img_url'  => '/images/activity/805/hb4.jpg',
		    		'link_url' => '/supplier-goods/index?id=31846&mall_id=1',
	    		],
    		],
    		14 => [  // 湖南
	    		1 => [
		    		'name'     => '维图GT13',
		    		'title'    => '2+16G超大内存',
		    		'img_url'  => '/images/activity/805/hnn1.jpg',
		    		'link_url' => '/site/detail?id=502883',
	    		],
	    		2 => [
		    		'name'     => '华为 畅享5S',
		    		'title'    => '全网通版(TAG-AL00)-预装版',
		    		'img_url'  => '/images/activity/805/hnn2.jpg',
		    		'link_url' => '/supplier-goods/index?id=27747&mall_id=1',
	    		],
	    		3 => [
		    		'name'     => '红米手机3S',
		    		'title'    => '全网通版2GB+16GB',
		    		'img_url'  => '/images/activity/805/hnn3.jpg',
		    		'link_url' => '/supplier-goods/index?id=28189&mall_id=1',
	    		],
	    		4 => [
		    		'name'     => '乐视1S',
		    		'title'    => '32G 太子妃版',
		    		'img_url'  => '/images/activity/805/hnn4.jpg',
		    		'link_url' => '/site/detail?id=226355',
	    		],
    		],
    		16 => [  // 江苏
	    		1 => [
		    		'name'     => '维图GT13',
		    		'title'    => '2+16G超大内存',
		    		'img_url'  => '/images/activity/805/js1.jpg',
		    		'link_url' => '/site/detail?id=502880',
	    		],
	    		2 => [
		    		'name'     => '红米Pro',
		    		'title'    => '标准版 3GB+32GB',
		    		'img_url'  => '/images/activity/805/js2.jpg',
		    		'link_url' => '/site/detail?id=538077',
	    		],
	    		3 => [
		    		'name'     => '乐视2(X620)',
		    		'title'    => '全网通版32GB',
		    		'img_url'  => '/images/activity/805/js3.jpg',
		    		'link_url' => '/site/detail?id=349821',
	    		],
	    		4 => [
		    		'name'     => '荣耀8',
		    		'title'    => '全网通版4GB/32GB',
		    		'img_url'  => '/images/activity/805/js4.jpg',
		    		'link_url' => '/supplier-goods/index?id=31846&mall_id=1',
	    		],
    		],
    		22 => [  // 山东
	    		1 => [
		    		'name'     => '维图GT13',
		    		'title'    => '2+16G超大内存',
		    		'img_url'  => '/images/activity/805/sd1.jpg',
		    		'link_url' => '/site/detail?id=491512',
	    		],
	    		2 => [
		    		'name'     => '三星GALAXY C5000',
		    		'title'    => '4GB/32GB',
		    		'img_url'  => '/images/activity/805/sd2.jpg',
		    		'link_url' => '/site/detail?id=490383',
	    		],
	    		3 => [
		    		'name'     => '乐视2(X620)',
		    		'title'    => '全网通版32GB',
		    		'img_url'  => '/images/activity/805/sd3.jpg',
		    		'link_url' => '/site/detail?id=349836',
	    		],
	    		4 => [
		    		'name'     => '荣耀8',
		    		'title'    => '全网通版4GB/32GB',
		    		'img_url'  => '/images/activity/805/sd4.jpg',
		    		'link_url' => '/supplier-goods/index?id=31846&mall_id=1',
	    		],
    		],
    		31 => [  // 浙江
	    		1 => [
		    		'name'     => '维图GT13',
		    		'title'    => '2+16G超大内存',
		    		'img_url'  => '/images/activity/805/az1.jpg',
		    		'link_url' => '/site/detail?id=502880',
	    		],
	    		2 => [
		    		'name'     => '荣耀8',
		    		'title'    => '全网通版4GB/32GB ',
		    		'img_url'  => '/images/activity/805/az2.jpg',
		    		'link_url' => '/supplier-goods/index?id=31846&mall_id=1',
	    		],
	    		3 => [
		    		'name'     => '乐视2(X620)',
		    		'title'    => '全网通版32GB',
		    		'img_url'  => '/images/activity/805/az3.jpg',
		    		'link_url' => '/site/detail?id=349821',
	    		],
	    		4 => [
		    		'name'     => '红米手机3S',
		    		'title'    => '全网通版2GB+16GB',
		    		'img_url'  => '/images/activity/805/az4.jpg',
		    		'link_url' => '/supplier-goods/index?id=28134&mall_id=1',
	    		],
    		],
    	];
    	
    	return $this->renderPartial('805/tx_product',[
    			'floorId'  => $floorId,
    			'products' => isset($products[$this->user_info['province']]) ? $products[$this->user_info['province']] : []]);
    }
    
    /**
     * @desc 奖牌榜
     */
    protected function getMedalRank(){
    	$rankInfo = MedalRank::find()->select('*')->orderBy('gold DESC')->limit(5)->asArray()->all();
    	return $this->renderPartial('805/rank',['rankInfo'=>$rankInfo]);
    }
    
    /**
     * @Description: 8.4通讯排行榜
     * @return: 渲染页面
     * @author: yazhou.miao
     * @date: 2016-8-3 上午9:48:53
     */
    protected function get811Top($floorId,$activityId){
    	 
    	return $this->renderPartial('811/top',[
    		'floorId'    => $floorId,
    		'activityId' => $activityId
    	]);
    }
    
    /**
     * @Description: 8.10IT家电生活馆 中秋礼盒
     * @return: 页面
     * @author: yan.zhang
     * @date: 2016-8-10 上午9:15:06
     */
    protected function get811FesGift($floorId){
        $province = $this->user_info['province'];
    	$provinces = [16,31,3,13,22];
        if(!in_array($province, $provinces)){
            return false;
        }
        return $this->renderPartial('811/fes-gift',[
                         'floorId'=>$floorId,
                ]);
    }
    
    /**
     * @desc 家电红包
     * @param unknown $floorId
     * @return string
     */
    protected function get811ItCoupon($floorId){
    	return $this->renderPartial('811/it-coupon',['floorId'=>$floorId]);
    }
     
    /**
     * @desc 家电红包
     * @return string
     */
    public function actionAjax811ItCoupon(){
    	$user_info = $this->user_info;
    	$activityId = 1033;
    	$prizes = [314];// 奖品
    	$startTime = '2016-08-11 00:00:00';
    	$endTime = '2016-08-12 00:00:00';
    	// 有效性检测
    	$check = $this->validCheck($activityId,true);
    
    	if($check['status']) {
    
    		// 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizes,'0','1');
    
    		if($result['status'] === 0){
    			if(314 == $result['prizeId']){ // 发送红包
    
    				CouponUser::addCoupon(2567, $user_info['id'], '10元51优品专用红包',$startTime,$endTime);
    				CouponUser::addCoupon(2454, $user_info['id'], '8元IT家电全品类红包',$startTime,$endTime);
    				CouponUser::addCoupon(2568, $user_info['id'], '10元中秋礼盒专用红包',$startTime,$endTime);
    			}
    		}
    	} else {
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    
    	return json_encode($result);
    }
    
    /**
     * @desc 家电红包
     * @param unknown $floorId
     * @return string
     */
    protected function get811ItCoupon2($floorId){
    	return $this->renderPartial('811/it-coupon2',['floorId'=>$floorId]);
    }
     
    /**
     * @desc 家电红包
     * @return string
     */
    public function actionAjax811ItCoupon2(){
    	$user_info = $this->user_info;
    	$activityId = 1034;
    	$prizes = [315];// 奖品
    	$startTime = '2016-08-11 00:00:00';
    	$endTime = '2016-08-12 00:00:00';
    	// 有效性检测
    	$check = $this->validCheck($activityId,true);
    
    	if($check['status']) {
    
    		// 执行抽奖逻辑
    		$result = $this->runLottery($activityId,$prizes,'0','1');
    
    		if($result['status'] === 0){
    			if(315 == $result['prizeId']){ // 发送红包
    
    				CouponUser::addCoupon(2567, $user_info['id'], '10元51优品专用红包',$startTime,$endTime);
    				CouponUser::addCoupon(2454, $user_info['id'], '8元IT家电全品类红包',$startTime,$endTime);
    			}
    		}
    	} else {
    		$result['status'] = -1;
    		$result['message'] = $check['message'];
    	}
    
    	return json_encode($result);
    }
    
}