<?php

namespace frontend\controllers;

use Yii;
use yii\db\Query;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\helpers\ArrayHelper;
use yii\web\HttpException;

use frontend\components\Controller2016;
use common\models\other\CrowdFund;
use common\models\other\CrowdFundOrder;
use common\models\user\UserMember;
use common\models\user\StoreMall;
use common\models\goods\Photo;
use common\models\user\ReceiveAddress;
use common\models\other\OtherRegion;
use common\models\other\CrowdFundPrice;

use common\models\OnlinePayment;
use common\models\order\online\orderLogic\CrowdOnlineOrderCommonHandler;
use common\models\order\OrderOnlinePayment;
use common\models\markting\CouponUser;

class CrowdFundController extends Controller2016{ 
	
    /**
     * @description:众筹首页
     * @return: return_type
     * @author: wufeng
     * @date: 2016年5月13日 下午4:52:04
     * @review_user:
     */
	public function actionIndex(){
	    $user_id = \Yii::$app->user->id;
        $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
        
        //用户信息
		if(!$user_info) $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
		
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
		
		$city = $user_info['city'];
	    
	    //根据商品获取mall_id，为空则根据用户获取mall_id
	    
	    $StoreMall['mall_id'] = StoreMall::getMainMall($this->user_info['id']);
	    $mall_id = f_get('mall_id',$StoreMall['mall_id']);
	    
	    //获取众筹活动列表
	    
	    $page = f_get('page', 0);
	    $data = CrowdFund::getListOfCrowdFund($city, $page);
	    
		return $this->render('index', [
		    'mall_id' => $mall_id,
		    'user_info' => $user_info,
		    'list' => $data['list'],
		    'pager' => $data['pager'],
		    'now' => date('Y-m-d H:i:s'),
		]);
	}
	
	/**
	 * @description:众筹详情页
	 * @return: return_type
	 * @author: Administrator
	 * @date: 2016年5月13日 下午4:52:18
	 * @review_user:
	 */
	
	public function actionDetail(){
	    
	    $user_id = \Yii::$app->user->id;
	    $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
	    
	    //用户信息
	    if(!$user_info) $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
	    
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
	    
	    $city = $user_info['city'];
	     
	    //根据商品获取mall_id，为空则根据用户获取mall_id
	     
	    $StoreMall['mall_id'] = StoreMall::getMainMall($this->user_info['id']);
	    $mall_id = f_get('mall_id',$StoreMall['mall_id']);
	    
	    $id = f_get('id', '');
	        
	    $crowd_fund = CrowdFund::getDetailOfCrowdFund($id);
	    $crowd_fund['status'] = CrowdFund::checkStatusOfCrwodFund($id);
	    
	    $address = [];
		$address = ReceiveAddress::find()->where(['user_id'=>$user_id])->orderBy('status desc')->asArray()->all();
		$address_id = 0;
		if($address) $address_id = $address[0]['id'];
		foreach ($address as $key=>$value) {
			$address[$key]['details'] = OtherRegion::getRegionName($value['province'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['city'])['region_name'];
			$address[$key]['details'] .= OtherRegion::getRegionName($value['district'])['region_name'];
			$address[$key]['details'] .= $value['address'];
			
			if($value['status'] == 1) {
			    $address_id = $value['id'];
			}
		}
	    
		$provinces = OtherRegion::getAllProvince();
		
	    return $this->render('detail', [
	        'mall_id' => $mall_id,
	        'user_info' => $user_info,
	        'crowd_fund' => $crowd_fund,
	        'address' => $address,
	        'address_id' => $address_id,
	        'provinces' => $provinces,
	    ]);
	}
	
	/**
	 * @description:添加订单
	 * @return: return_type
	 * @author: Administrator
	 * @date: 2016年5月17日 下午4:33:07
	 * @review_user:
	 */
	
	public function actionAddOrder(){
	    $return = ['success' => false, 'message' => ''];
	    
	    $user_id = \Yii::$app->user->id;
	    $user_info = f_c('frontend-'.$user_id.'-new_51dh3.0');
	     
	    //用户信息
	    if(!$user_info) $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
	    
	    $address_id = f_post('address_id', 0);
	    $crowd_fund_id = f_post('crowd_fund_id', 0);
	    $book_num = f_post('book_num', 0);
	    
	    $now = date('Y-m-d H:i:s', time());
	    
	    $crowd_fund = (new Query())->select('*')
	           ->from('crowd_fund')
	           ->andWhere(['enable' => 1, 'id' => $crowd_fund_id])
               ->andWhere(['<', 'starttime', $now])
               ->andWhere(['>', 'endtime', $now])
	           ->one();

	    if(!$crowd_fund) {
	        $return['message'] = '众筹活动已结束！<br/>请试一试其它的众筹项目';
	        return json_encode($return);
	    }
	    
	    if(!$book_num) {
	        $return['message'] = '众筹的商品数量必须大于0!';
	        return json_encode($return);
	    }
	    
	    
        $address = ReceiveAddress::find()->where(['id'=>$address_id])->asArray()->one();
         
        if($address) {
            $address['details'] = OtherRegion::getRegionName($address['province'])['region_name'];
            $address['details'] .= OtherRegion::getRegionName($address['city'])['region_name'];
            $address['details'] .= OtherRegion::getRegionName($address['district'])['region_name'];
            $address['details'] .= $address['address'];  	
        }else {
            $return['message'] = '请选择一个配送地址！';
            return json_encode($return);
        }
        
        $id = CrowdFundOrder::addOrder($user_info['id'], $address, $book_num, $crowd_fund_id);

        if($id > 0) return $this->redirect(['/crowd-fund/online-payment', 'order_id' => $id,]);
        
	}
	
	/**
	 * @description:添加订单
	 * @return: return_type
	 * @author: wufeng
	 * @date: 2016年5月17日 下午4:33:07
	 * @review_user:
	 */
	
	public function actionGetDetail(){
	    $id = f_post('id', 0);
	    
        $address = ReceiveAddress::find()->where(['id' => $id])->asArray()->one();
        $provinces = OtherRegion::getAllProvince();
        
        if($address['province']) $citys = OtherRegion::getRegion($address['province']);
        else $citys = [];
        
        if($address['city']) $districts = OtherRegion::getRegion($address['city']);
        else $districts = [];
        
        $this->layout = false;
        
	    return $this->renderPartial('address', [
	        'address' => $address,
	        'provinces' => $provinces,
	        'citys' => $citys,
	        'districts' => $districts,
	    ]);
	}
	
	/**
	 * @description:保存地址
	 * @return: return_type
	 * @author: wufeng
	 * @date: 2016年5月18日 上午11:16:01
	 * @review_user:
	 */
	
	public function actionSetAddress(){
	    if($_POST['id']) {
	        $model = ReceiveAddress::find()->where(['id'=>$_POST['id']])->one();
	    } else {
	        $model = new ReceiveAddress();
	    }
	    $model->user_id = \Yii::$app->user->id;
	
	    $model->name = $_POST['name'];
	    $model->province = $_POST['province'];
	    $model->city = $_POST['city'];
	    $model->district = $_POST['district'];
	    $model->phone = $_POST['phone'];
	    $model->address = $_POST['address'];
	    if($_POST['id'] == 0) {
	        //对于新收获地址，将默认地址列表status都置为空，将最新设置的收获地址置为默认地址  code by :wmc
	        ReceiveAddress::updateAll(['status'=>'0'],'user_id=:user_id',[':user_id'=>$model->user_id]);
	        $model->status = 1;
	    }
	    $user_info = UserMember::find()->where(['id'=>$model->user_id])->one();
	    
	    if($user_info->province == $model->province) {
	        if($model->save()) {
	            return $model->id;
	        } else {
	            return 0;
	        }
	    } else {
	        return -1;
	    }
	}
	
	/**
	 * @description:设为默认地址  删除地址
	 * @return:
	 * @author: sunkouping
	 * @date: 2015年11月3日上午9:38:20
	 * @modified_date: 2015年11月3日上午9:38:20
	 * @modified_user: wufeng
	 * @review_user:
	 */
	public function actionDelAddress(){
	    //$return = ['success' => false, 'message' => ''];
	    $return = false;
	    
	    $type = $_POST['type'];
	    $id = $_POST['id'];
	    $user_id = \Yii::$app->user->id;
	    if($type == 1) { //设为默认
	        ReceiveAddress::updateAll(['status'=>'0'],'user_id=:user_id',[':user_id'=>$user_id]);
	        ReceiveAddress::updateAll(['status'=>1],'id=:id',[':id'=>$id]);
	        
	        //$return['success'] = true;
	        $return = true;
	    } else if($type == 2) { //删除
	        $model = ReceiveAddress::find()->andWhere(['id'=>$id])->one();
	        if($model){
	            $model->delete();
	        }
	    }
	    
	    return json_encode($return);
	}
	
	/**
	 * @description:在线支付跳转页面
	 * @return: return_type
	 * @author: Administrator
	 * @date: 2016年5月20日 下午5:01:36
	 * @review_user:
	 */
	public function actionOnlinePayment(){
	    $order_id = explode('-',f_get('order_id'));
	    
	    $status = 2;
	    $crowdFundOrder = CrowdFundOrder::find()->where(['id' => $order_id])->one();
	    if($crowdFundOrder) {
	        $status = CrowdFund::checkStatusOfCrwodFund($crowdFundOrder['crowd_fund_id']);
	    }
	    
	    //用户状态校验
	    if($status != 1){
	        $view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
	            'type'=>'warn',
	            'message'=> '该众筹活动已关闭，不能支付！',
	            'is_next'=>'yes',
	            'next_url'=>'/ucenter/crowd-fund',
	        ]);
	        return $view;exit;
	    }
	    
	    $params = [
	        'userInfo'=>$this->user_info,
	        'pay_type'=>OnlinePayment::$payPortMap[1]
	    ];
	    $handler = new CrowdOnlineOrderCommonHandler($order_id);
	    $onlinePaymentData = OrderOnlinePayment::getOnlinePaymentParams($handler,$params);
	    return $this->renderPartial('online-payment-form',$onlinePaymentData);
	}
	
	/**
	 * @description:在线支付确认页面
	 * @return: return_type
	 * @author: wufeng
	 * @date: 2016年5月20日 下午5:42:49
	 * @review_user:
	 */
	public function actionOnlineConfirm(){
	    $csrfToken = Yii::$app->request->post('_csrf');
	    
	    if(Yii::$app->request->validateCsrfToken($csrfToken)){
	        return $this->renderPartial('online-confirm',[
	            'requestParam'=>$_POST,
	        ]);
	    }else{
	        throw new HttpException(404);
	    }
	}
	
	/**
	 * @description:订单成功后的回调地址
	 * @return: return_type
	 * @author: wufeng
	 * @date: 2016年5月24日 下午2:41:00
	 * @review_user:
	 */
    public function actionOrderSuccess(){
        
        $order_id = f_get('order_id', 0);
        
        $orders = (new Query())->select('t1.*, t2.title, t2.cost, t2.price, t2.coupon_id, t2.starttime, t2.endtime, t3.goods_name')
            ->from('crowd_fund_order as t1')
            ->leftJoin('crowd_fund AS t2', 't1.crowd_fund_id = t2.id')
            ->leftJoin('goods_supplier_goods AS t3', 't2.goods_id = t3.id')
            ->andWhere(['t1.id' => $order_id])->one();
        
        //如果众筹红包存在，付款完成后就给它发红包
        if($orders['coupon_id']) {
            $user_id = \Yii::$app->user->id;
            $remark = '众筹红包_'.$orders['crowd_fund_id'];
            
            $coupon = CouponUser::find()->where(['coupon_id' => $orders['coupon_id']])->andWhere(['like', 'remark', $remark])->one();
            if(!$coupon) CouponUser::addCoupon($orders['coupon_id'], $user_id, '系统设置', $orders['starttime'], $orders['endtime'], $remark);
        }
        
        $ad_data = [];
        
        $this->layout = '_blank';
        
	    return $this->render('order-success', [
	        'value' => $orders,
	        'ad_data' => $ad_data,
	    ]);
	}
}
