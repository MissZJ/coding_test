<?php 
namespace frontend\controllers;

use common\models\OnlinePayment;
use common\models\order\online\orderLogic\OnlineOrderHandlerBase;
use common\models\order\OrderOnlineCode;
use common\models\order\OrderOnlinePayment;
use common\models\user\Supplier;
use common\models\user\UserShopsSupplier;
use frontend\components\Controller2016;
use common\models\user\User;
use common\models\other\OtherRegion;
use yii\db\Query;
use yii\helpers\Json;
use common\models\user\UserMember;
use common\models\order\OmsInfo ;
use common\models\order\OmsGoods ;

class YfbController extends Controller2016
{
    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [];
    }
    
    /**
     * @Title:beforeAction
     * @Description:复写beforeAction
     * @return:boolean || redirect
     * @author:huaiyu.cui
     * @date:2015-12-2 下午4:54:05
     */
    public function beforeAction($action) {
        return true;
    }

    
    /**
     *
     * @Title: actionYfbUserMes
     * @Description:预付宝会员信息
     * @return: string
     * @author: yulong.wang
     * @date: 2016-1-1下午3:31:49
     */
    public function actionYfbUserMes(){
        $out = [];
        if (isset($_POST['time']) && isset($_POST['token']) && isset($_POST['content'])) {
            $time = $_POST['time'];
            $token = $_POST['token'];
            $content = json_decode(stripslashes($_POST['content']),true);
            //$content = json_decode($_POST['content'],true);
            $token_str = md5($time).'5bbfd68e674314de6775c6efb3ee9d02';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                $user_arr = UserMember::find()->select('id,login_account,user_name,shop_name,sex,password,email,phone,level,integral,user_status,province,city,district,address,last_ip,last_login_time')->where(['login_account'=>$content['loginAccount']])->asArray()->one();
                $user_mess = [];
                if (!empty($user_arr)) {
                    $address =  OtherRegion::getRegionNameByCache($user_arr['province']).'-'.OtherRegion::getRegionNameByCache($user_arr['city']).'-'.OtherRegion::getRegionNameByCache($user_arr['district']).'-'.$user_arr['address'];
                    $user_mess = [
                        'sys_member_id' => $user_arr['id'],
                        'login_name' => $user_arr['login_account'],
                        'name' => $user_arr['user_name'],
                        'shop_name' => $user_arr['shop_name'],
                        'sex' => $user_arr['sex'],
                        'password' => $user_arr['password'],
                        'email' => $user_arr['email'],
                        'mobile' => $user_arr['phone'],
                        'customer_grade' => $user_arr['level'],
                        'customer_score' => $user_arr['integral'],
                        'status' => $user_arr['user_status'],
                        'province'=>$user_arr['province'],
                        'city'=>$user_arr['city'],
                        'district'=>$user_arr['district'],
                        'address' => $address,
                        'last_ip' => $user_arr['last_ip'],
                        'last_login_time'=> $user_arr['last_login_time'],
                    ];
                    $out['res'] = 0;
                    $out['msg'] = '成功';
                    $out['data'] = $user_mess;
                } else {
                    $out['res'] = 100;
                    $out['msg'] = '该用户在51平台不存在';
                    $out['data'] = [];
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
                $out['data'] = [];
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺少';
            $out['data'] = [];
        }
        return json_encode($out);
    }
    
    
    
    /**
     * 
     * @Title: actionActiveUser
     * @Description: 预付宝激活会员
     * @return: return_type
     * @author: yulong.wang
     * @date: 2016-1-1下午7:54:08
     */
    public function actionActiveUser(){
        $user_name = $_POST['user_name'];
        $token = $_POST['token'];
        $salt = '51dh';
        $md5 = md5($user_name.$salt);
        if ($md5 === $token) {
            $user = UserMember::findOne(['login_account' => $user_name]);
            //刷新预存宝用户开通缓存状态  code:wmc
            if($user){
                $userId = $user->id;
                $key = 'frontend-'.$userId.'-new_51dh3.0';
                $userInfo = f_c($key);
                $userInfo['yfb_open'] = true;
                f_c($key,$userInfo);
            }


            if (!empty($user)) {
                if ($user->active_pay == 1) {
                    return Json::encode([
                        'code' => 300,
                        'status' => 'error',
                        'msg' => '该用户已激活'
                    ]);
                }else{
                    $user->active_pay = 1;
                    if ($user->save()) {
                        return Json::encode([
                            'code' => 200,
                            'status' => 'success',
                            'msg' => 'OK'
                        ]);
                    }else{
                        return Json::encode([
                            'code' => 500,
                            'status' => 'error',
                            'msg' => '激活失败'
                        ]);
                    }
                }
            }else{
                return Json::encode([
                    'code' => 400,
                    'status' => 'error',
                    'msg' => '该用户不存在'
                ]);
            }
        }else{
            return Json::encode([
                'code' => 100,
                'status' => 'error',
                'msg' => '验证不通过'
            ]);
        }
    }

    /*
    *  根据支付流水号获取订单状态接口
    */
    public function actionGetOrderStatusByOnlineCode(){
        $request = \Yii::$app->request->post() ;
        $r = ['res'=>100 , 'msg'=>''] ;
        if (isset($request['time']) && isset($request['token']) && isset($request['content'])){
            $n_token = md5(md5($request['time']) . '5bbfd68e674314de6775c6efb3ee9d02') ;
            if ($n_token == $request['token']){
                $content = json_decode($request['content'],1) ;
                if (isset($content['online_code']) && $content['online_code']){
                    //根据流水号获取orderIds
                    $orderCode = OrderOnlineCode::find()->andWhere(['online_code'=>$content['online_code']])->asArray()->all();
                    $orderList = [];
                    foreach($orderCode as $order){
                        $orderList[] = $order['order_id'];
                    }

                    //根据pay_port获取对应的$handler
                    $orderOnlinePayment = OrderOnlinePayment::find()->andWhere(['online_code'=>$content['online_code']])->asArray()->one();
                    $handler = OnlineOrderHandlerBase::createOrderHandler($orderOnlinePayment['order_type']);
                    $handler->setOrderIds($orderList);
                    $waitOrderStatus = $handler->getWaitOrderStatus();
                    $is_lock = (isset($content['is_lock']) && $content['is_lock'])?true:false;
                    $orderList = $handler->getOrderInfoData();
                    if($orderList){
                        $r = ['res'=>1,'msg'=>'OK'];
                        foreach($orderList as $val){
                            $orderStatus = (isset($handler->statusMap['order_status']))?$handler->statusMap['order_status']:'order_status';
                            if(!in_array($val[$orderStatus],$waitOrderStatus)){
                                $r = ['res'=>0,'msg'=>'have invalid order_status'];
                            }
                        }
                        if($r['res']==1){
                            if($is_lock){
                                foreach($orderList as $val){
                                    //支付订单开启锁定
                                    $lockPrefix = $handler->getOnlineLockPrefix();
                                    $orderLockKey = $lockPrefix.$val['id'];
                                    $orderLockData = f_c($orderLockKey);
                                    if($orderLockData){
                                        //$r = ['res'=>105,'msg'=>'订单支付完成，系统异步通知锁定中。'];
                                        //break;
                                    }else{
                                        f_c($orderLockKey,true,1800); //锁定订单号
                                    }
                                }

                            }
                        }
                    }else{
                        $r = ['res'=>104,'msg'=>'订单不存在'];
                    }
                }else{
                    $r = ['res'=>103 ,'msg'=>'缺少参数'] ;
                }
            }else{
                $r = ['res'=>102 ,'msg'=>'密钥验证失败'] ;
            }
        }else{
            $r = ['res'=>101 ,'msg'=>'缺少参数'] ;
        }
        return json_encode($r) ;
    }

	/*
	* 获取金融订单信息接口
	*/
	public function actionGetOrderInfo(){
		$request = \Yii::$app->request->post() ;
		$r = ['res'=>100 , 'msg'=>''] ;
		if (isset($request['time']) && isset($request['token']) && isset($request['content'])){
			$n_token = md5(md5($request['time']) . '5bbfd68e674314de6775c6efb3ee9d02') ;
			if ($n_token == $request['token']){
				$content = json_decode($request['content'],1) ;
				if (isset($content['order_code'])){
					//查找金融订单
					$info = OmsInfo::find()->select('a.*,b.login_account,b.user_name,b.phone as user_phone')->from('order_oms_info as a')
						->leftJoin('user_member as b','a.user_id=b.id')
						->where(['a.order_code'=>$content['order_code'] , 'a.special_type'=>2])
						->asArray()->one() ;
					if (!empty($info)){
						if ($goods = OmsGoods::find()->where(['order_code'=>$info['order_code']])->asArray()->all()){
							$info['goods'] = $goods ;
						}
						$r = ['res'=>1,'msg'=>'成功','data'=>$info] ;
					}else{
						$r = ['res'=>0,'msg'=>'不存在订单号为' .$content['order_code']. '的金融订单'] ;
					}
				}else{
					$r = ['res'=>103 ,'msg'=>'缺少参数'] ;
				}
			}else{
				$r = ['res'=>102 ,'msg'=>'密钥验证失败'] ;
			}
		}else{
			$r = ['res'=>101 ,'msg'=>'缺少参数'] ;
		}
		return json_encode($r) ;
	}

    //金融查询接口
    public function actionGetELoanOrderData()
    {
        $request = \Yii::$app->request->post();
        $r = ['res'=>100 , 'msg'=>''] ;
        if (isset($request['time']) && isset($request['token']) && isset($request['content'])){
            $n_token = md5(md5($request['time']) . '5bbfd68e674314de6775c6efb3ee9d02');
            if ($n_token == $request['token']){
                $content = json_decode($request['content'],true);
		
                if (isset($content['supplier_code']) && isset($content['begin_time']) && isset($content['end_time']) && isset($content['row']) && isset($content['page'])){
                    //查找金融订单
                    $content['page']-=1;
                    $query = (new Query())->select('a.order_code,c.name,b.goods_name,b.goods_price,b.goods_num,a.order_price,a.order_time,d.status_name,a.supplier_id')->from('order_oms_info as a')
                        ->leftJoin('order_oms_goods as b','b.order_code = a.order_code')
                        ->leftJoin('user_supplier as c','c.id = a.supplier_id')
                        ->leftJoin('order_oms_status as d','d.status = a.order_status')
                        ->where(['c.login_account'=>$content['supplier_code']]);
                    //缓存key
                    $key= 'loan_order'.'-'.$content['supplier_code'];
                    if(!empty($content['begin_time']) && !empty($content['end_time'])){
                        $key = 'loan_order'.'-'.$content['supplier_code'].'-'.$content['begin_time'].'-'.$content['end_time'].'-'.$content['row'].'-'.$content['page'];
                        $query->andWhere(['>','a.order_time',$content['begin_time']])->andWhere(['<','a.order_time',$content['end_time']]);
                    } elseif (!empty($content['begin_time']) && empty($content['end_time'])){
                        $key = 'loan_order'.'-'.$content['supplier_code'].'-'.$content['begin_time'].'-'.$content['row'].'-'.$content['page'];
                        $query->andWhere(['>','a.order_time',$content['begin_time']]);
                    } elseif (empty($content['begin_time']) && !empty($content['end_time'])){
                        $key = 'loan_order'.'-'.$content['supplier_code'].'-'.$content['end_time'].'-'.$content['row'].'-'.$content['page'];
                        $query->andWhere(['<','a.order_time',$content['end_time']]);
                    }
                    $history = f_c($key);
                    if(!$history){
                        set_time_limit(0);
                        $total = $query->count();
                        $info = $query->offset($content['page']*$content['row'])->limit($content['row'])->all();
                        if (!empty($info)){
                            $r = ['res'=>0,'msg'=>'成功','data'=>$info,'total'=>$total];
                        }else{
                            $r = ['res'=>104,'msg'=>'不存在供应商为' .$content['supplier_code']. '或不在'.$content['begin_time'].'-'.$content['end_time'].'的时间范围内的金融订单','data'=>[],'total'=>$total];
                        }
                        f_c($key,$r,12*3600);
                    }else{
                        $r = $history;
                    }
                }else{
                    $r = ['res'=>103 ,'msg'=>'缺少参数'];
                }
            }else{
                $r = ['res'=>102 ,'msg'=>'密钥验证失败'];
            }
        }else{
            $r = ['res'=>101 ,'msg'=>'缺少参数'];
        }
        return json_encode($r);
    }
}
?>