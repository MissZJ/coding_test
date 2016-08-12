<?php

namespace frontend\controllers;

use common\models\afterSales\AfterSalesGoods;
use common\models\afterSales\AfterSalesSerialCode;
use common\models\afterSales\AlienReplacementLog;
use common\models\dh\OmsOrderInfo;
use common\models\goods\SupplierGoods;
use common\models\goods\Type;
use common\models\markting\CouponType;
use common\models\OnlinePayment;
use common\models\order\online\orderLogic\OnlineOrderCommonHandler;
use common\models\order\online\YdOnlinePayment;
use common\models\order\OrderOnlinePayment;
use common\models\other\MallBase;
use common\models\user\StoreMall;
use common\models\user\User;
use common\models\user\UserInvoice;
use Yii;
use frontend\components\Controller2016;
use common\models\user\ReceiveAddress;
use common\models\user\UserMember;
use common\models\other\OtherRegion;
use common\models\order\OmsInfo;
use common\models\order\OmsGoods;
use common\models\markting\CouponInfo;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\web\ForbiddenHttpException;
use common\models\goods\GoodsComment;
use yii\helpers\BaseArrayHelper;
use common\models\user\SiteMessagesInfo;
use common\models\user\SiteMessagesRelated;
use common\models\other\FavoriteGoods;
use common\models\other\FavoriteShop;
use yii\db\Query;
use common\models\user\SignNew;
use common\models\user\SignNewTips;
use common\models\user\SignNewPrize;
use common\models\user\SignNewWin;
use common\models\user\SignNewLottery;
use common\models\user\WealthDetail;
use yii\data\ActiveDataProvider;
use yii\data\Pagination;
use yii\helpers\Json;
use yii\web\HttpException;
use common\models\other\CityFunctionManage;
use common\models\order\OrderPayWay;
use common\models\markting\CouponUser;
use common\models\order\OmsLog;
use common\models\api\OrderStatusInterfaceLog;
use common\models\afterSales\AfterSalesOrder;
use common\models\afterSales\AfterSalesLog;
use common\models\afterSales\AfterSalesStatus;
use common\models\goods\SerialCode;
use common\models\markting\RebateCouponGrant;
use common\models\user\SignTemplate;
use common\models\other\CrowdFundOrder;
use common\util\Curl;
use common\models\seas\SeasUserAddress;
use common\models\order\online\orderLogic\AfterSalesOnlineOrderCommonHandler;
use common\models\order\GiftInfo;

use yii\web\UploadedFile;
use common\models\UploadForm;
use common\models\goods\GoodsRefund;
use common\models\goods\GoodsRefundService;
use common\models\goods\GoodsRefundLog;
use common\models\afterSales\AfterSalesDelivery;
use common\models\order\OmsStatus;
/**
 * 前台-用户中心
 * Class UcenterController
 * @package frontend\controllers
 */
class UcenterController extends Controller2016
{

    public function init(){
        $this->layout = 'ucenter';
        parent::init();
    }
    /**
     * @Title: actionIndex
     * @Description: 个人中心首页
     * @return: return_type
     * @author: zend.wang     * @date: 2015年11月02日上午9:55:12
     */
    public function actionIndex()
    {
        //用户等级描述
        $this->user_info['level_name'] = UserMember::getLevel($this->user_info['level']);
        //获取用户信息完整度得分
        $this->user_info['account_score']= UserMember::getUserAccountScore($this->user_info);
        //获取下一个会员等级还差多少财富值升级
        $this->user_info['upgrade_need_wealth'] = UserMember::getUpgradeNeedWealth($this->user_info);
        //最近5条订单信息
        list($orderList,$pager) = OmsInfo::getOrderListByUserInfo($this->user_info,['limit'=>5]);
// 		f_d($orderList);
        return $this->render('index',['userInfo'=>$this->user_info,'orderList'=>$orderList]);
    }
    
   
   
    
    /**
     * @Title: actionOrder
     * @Description: 我的订单列表及订单详情
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年11月02日上午9:55:12
     * @review_user:zhanghy
     */
    public function actionOrder($id=0)
    {
        $orderStatus = OnlinePayment::$waitOrderStatus;
        $orderId= intval($id,0);
        $condition = [];
        if($orderId>0){//订单详情
            list($orderInfo,$serialCodeList,$orderGoodsInfoList) = OmsInfo::getOrderListByOrderIdAndUserInfo($orderId,$this->user_info);
            //发票信息
            $invoice = [];
            $userInfo = $this->user_info;

            if($orderInfo['special_type'] == 3){
                $invoice = UserInvoice::find()->where(['user_id'=>$userInfo['id'],'status'=>2])->asArray()->one();
            }
            foreach($orderGoodsInfoList as $key => $value){
            	$gift_info = GiftInfo::find()->select('*')->where(['order_code'=>$orderInfo['order_code'],'oms_goods_id'=>$value['oms_goods_id']])->one();
            	if(isset($gift_info['type'])){
            		$orderGoodsInfoList[$key]['type'] = $gift_info['type'];
            		$orderGoodsInfoList[$key]['gift_weight']  = $gift_info['gift_id'];
            		$id = $gift_info['id'];
            	}else{
            		$orderGoodsInfoList[$key]['type'] = 0;
            		$orderGoodsInfoList[$key]['gift_weight'] = 0;
            		$id = 0;
            	}
            	$type[] = $orderGoodsInfoList[$key]['type'];
            	$gift_id[] = $orderGoodsInfoList[$key]['gift_weight'];
            	$ids[] = $id;
            }
            if(isset($orderGoodsInfoList[1])){
            	array_multisort($ids,SORT_ASC,$type,SORT_ASC,$gift_id, SORT_ASC, $orderGoodsInfoList);
            }
            //f_d($orderGoodsInfoList);
            return $this->render('order-detail',['orderInfo'=>$orderInfo,'serialCodeList'=>$serialCodeList,'orderGoodsInfoList'=>$orderGoodsInfoList,'invoice'=>$invoice]);
        }
        
       if (isset($_GET['search'])) {
           $condition = ['search' => f_get('search')];
       }
       if(isset($_GET['search_type'])){
           $condition['search_type'] = f_get('search_type',1);
       }
        //订单列表
        list($orderList,$pager) = OmsInfo::getOrderListByUserInfo($this->user_info,$condition);
        foreach ($orderList as $key => $value) {
        	$type = [];
        	$gift_id = [];
        	$ids = [];
        	foreach ($value['goods_info'] as $k => $v) {
        		$gift_info = GiftInfo::find()->select('*')->where(['order_code'=>$value['order_code'],'oms_goods_id'=>$v['oms_goods_id']])->one();
        		if($gift_info){
        			$orderList[$key]['goods_info'][$k]['type'] = $gift_info['type'];
        			$orderList[$key]['goods_info'][$k]['gift_weight'] = $gift_info['gift_id'];
        			$id = $gift_info['id'];
        		}else{
        			$orderList[$key]['goods_info'][$k]['type'] = 0;
        			$orderList[$key]['goods_info'][$k]['gift_weight'] = 0;
        			$id = 0;
        		}
        		$type[] = $orderList[$key]['goods_info'][$k]['type'];
        		$gift_id[] = $orderList[$key]['goods_info'][$k]['gift_weight'];
        		$ids[] = $id;
        	}
        	if(isset($orderList[$key]['goods_info']) && count($orderList[$key]['goods_info']) > 1){
        		array_multisort($ids,SORT_ASC,$type,SORT_ASC,$gift_id,SORT_ASC,$orderList[$key]['goods_info']);
        		
        	}
        	
        }
       //f_d($orderList);
        $unpayOrderCount = OmsInfo::find()->where(['order_status'=>$orderStatus,'user_id'=>$this->user_info['id']])->count();
        return $this->render('order',[
                'unpayOrderCount' => $unpayOrderCount,
                'orderList'=>$orderList,
                'pager'=>$pager,
            
        ]);
    }
    
    /**
     * @Title: actionUnpayOrder
     * @Description: 代付款订单列表
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年12月21日上午9:55:12
     * @review_user:zhanghy
     */
    public function actionUnpayOrder()
    {
        $orderStatus = OnlinePayment::$waitOrderStatus;
        $condition =['orderStatus'=>$orderStatus];   //待付款订单状态

        if (isset($_GET['search'])) {
            $condition['search'] = f_get('search');
        }

        if(isset($_GET['search_type'])){
            $condition['search_type'] = f_get('search_type',1);
        }
        //订单列表
        list($orderList,$pager) = OmsInfo::getOrderListByUserInfo($this->user_info,$condition);
        $unpayOrderCount = OmsInfo::find()->where(['order_status'=>$orderStatus,'user_id'=>\Yii::$app->user->id])->count();
        // f_d($pager);
        //f_d($orderList);
        return $this->render('order',[
            'unpayOrderCount' => $unpayOrderCount,
            'orderList'=>$orderList,
            'pager'=>$pager,
            'action'=>'unPayOrderList',
            
        ]);
    }
    
    /**
     * @Title: actionCoupon
     * @Description: 我的优惠券
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年11月02日上午9:58:12
     */
    public function actionCoupon()
    {
        $couponType = f_get('couponType','goods');
        $couponStatus = f_get('couponStatus');

        if(!in_array($couponType,['goods','shop'])||(!empty($couponStatus) && !in_array($couponStatus,[1,2,3]))){
            throw new ForbiddenHttpException('请求参数异常');
        }

        $typeList = ['goods'=>[1,2,3,5,6],'shop'=>[4]];//优惠券类型：商品、店铺

        $condition=array('type'=>$typeList[$couponType],'coupon_status'=>$couponStatus);
        
        $couponListDataProvider = CouponInfo::getCouponListByUserInfo($this->user_info,$condition);
        
        $couponList = $couponListDataProvider->getModels();
        
        $pager = $couponListDataProvider->getPagination();

        return $this->render('coupon',[
        		'couponList'=>$couponList,
        		'pager'=>$pager,
                'couponStatus'=>$couponStatus,
        		'couponType'=>$couponType]);
    }
    /**
     * @Title: actionAccount
     * @Description: 账户信息调整
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年11月02日上午9:58:12
     */
    public function actionAccount()
    {

        $defaultAction = f_get('action','base_info');
        $defaultError ='';

        if(Yii::$app->request->isPost){

            $action = f_post('action');
            $result = false;
            switch($action){
                case 'base_info'://更新基本信息
                {
                    $model = UserMember::updateUserInfo($action,$this->user_info['id'],Yii::$app->request->post());
                    break;
                }
                case 'avatar'://更新用户头像
                {
                    $avatarId = intval(f_post("avatarId",0));
                    if($avatarId>0){
                        $model = UserMember::updateUserInfo($action,$this->user_info['id'],['avatarId'=>$avatarId]);
                    }
                    break;
                }
                case 'validate_sms_capcha'://验证短信验证码
                {
                    $serverCode = f_s("passwordCode");
                    $clientCode = f_post("code");
                    $clientPhone = f_post('phoneNum');
                    if(empty($clientPhone)  || strcmp($clientPhone,$this->user_info['phone'])<>0 ) {
                        $action = 'error_validate_sms_capcha';
                        $defaultError="手机号输入有误，请重试！";
                    } else if(empty($serverCode) || empty($clientCode) || strcmp($serverCode,$clientCode)<>0){
                        $action = 'error_validate_sms_capcha';
                        $defaultError="验证码输入有误，请重试！";
                    }
                    break;
                }
                case 'update_password'://更新密码
                {
                    $newPassword = f_post("newPassword");
                    $confirmPassword = f_post("confirmPassword");
//                    $clientCode = f_post("verfyCode");
//                    if(strcmp($confirmPassword,$newPassword)==0 && strcmp(f_s('verifyCode'),$clientCode)==0){
                    if(strcmp($confirmPassword,$newPassword)==0){
                        $model = UserMember::updateUserInfo($action,$this->user_info['id'],['password'=>$newPassword]);
//                    }else if($clientCode !=f_s('verifyCode')){
//                        $action = 'validate_sms_capcha';
//                        $defaultError="验证码错误，请重试！";
                    }else if($newPassword !=$confirmPassword){
                        $action = 'validate_sms_capcha';
                        $defaultError="两次密码不一致！";
                    }
                    else {
                        $action = 'validate_sms_capcha';
                        $defaultError="密码更新有误，请重试！";
                    }
                    break;
                }
                case 'update_phone':
                {
                    $phone = f_post('phone');
                    $code = f_post('codePhone');
                    if(isset($phone) && !empty($phone)&& isset($code)){
                        if($code == f_s('registerCode')){   //  短信 f_s('PhoneCode')  session 里的短信验证码     语音 registerCode
                                $model = UserMember::updateUserInfo($action,$this->user_info['id'],['phone'=>$phone]);
                                if($model){
                                    $user = $this->user_info;
                                    f_msm($user['phone'], '【51订货网】:您的账号 '.$user['login_account'].'所绑定的手机号已由'.$user['phone'].'改为'.$phone.'客服电话：4008105151');
                                    ReceiveAddress::updateAll(['phone'=>$phone],['user_id'=>$user['id']]);
                                }
                        }else{
                            $action = 'error_phone';
                            $defaultError="验证码输入有误，请重试！";
                        }
                    }else{
                        $action = 'error_phone';
                        $defaultError="手机号输入有误，请重试！";
                    }
                    break;
                }
                default:
                    throw new ForbiddenHttpException('请求参数异常');
            }
            $defaultAction = $action;
            if(!empty($model)) {
                $this->user_info =  $model;//更新本次操作用户信息
            }
        }

        $data =UserMember::getUsrInfo($this->user_info['id']);
        if($data)
        {
            $this->user_info['shop_name'] = $data['shop_name'];
            $this->user_info['user_name'] = $data['user_name'];
            $this->user_info['sex'] = $data['sex'];
            $this->user_info['email'] = $data['email'];
            $this->user_info['address'] = $data['address'];
        }
        $this->user_info['address_region'] = OtherRegion::getRegionNameById($this->user_info['province'])."-" . OtherRegion::getRegionNameById($this->user_info['city'])."-" .OtherRegion::getRegionNameById($this->user_info['district']);
        //用户等级描述
        $this->user_info['level_name'] = UserMember::getLevel($this->user_info['level']);
        return $this->render('account',['userInfo'=>$this->user_info,'defaultError'=>$defaultError,'defaultAction'=>$defaultAction]);
    }
    /**
     * @Title: actionMessage
     * @Description: 收藏：店铺收藏|商品收藏
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年11月12日上午12:58:12
     */
    public function actionFavorite(){

        $defaultAction = f_get('action','goods');

        $keywords = f_get('keywords','');//搜索关键字
        $condition=['keywords'=>$keywords];

        if(strcmp($defaultAction,'goods')==0){
            list($favoriteList,$pager)= FavoriteGoods::getFavoriteGoodsListByUserInfo($this->user_info,$condition);
        }else if(strcmp($defaultAction,'shop')==0){
            list($favoriteList,$pager)= FavoriteShop::getFavoriteShopListByUserInfo($this->user_info,$condition);
        }else{
            throw new ForbiddenHttpException('请求参数异常');
        }
        //f_d($favoriteList);
        return $this->render('favorite',['list'=>$favoriteList,'pager'=>$pager, 'defaultAction'=>$defaultAction]);
    }

    /**
     * @Title: actionMessage
     * @Description: 消息
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年11月12日上午9:58:12
     */
    public function actionMessage(){

        $defaultAction = f_get('action','all');

        if(Yii::$app->request->isAjax){ //ajax请求

            $id = intval(f_get('id'),0);
            $model = SiteMessagesRelated::findOne($id);
            if($model && strcmp($defaultAction,'read')==0) {
                $model->is_read='1';
                $model->read_time = f_date(time());
                $model->save();

                echo json_encode(['stauts'=>1,'message'=>'success']);exit;

            }else if($model && strcmp($defaultAction,'del')==0){
                $model->delete();
                echo json_encode(['stauts'=>1,'message'=>'success']);exit;
            } else {
                echo json_encode(['stauts'=>0,'message'=>'failed']);exit;
                //throw new ForbiddenHttpException('请求参数异常');
            }
        }

        $condition=[];
        switch($defaultAction){
            case 'all':
                break;
            case 'unread':
            {
                $condition['is_read']='0';
                break;
            }
            case 'read': {
                $condition['is_read'] = '1';
                break;
            }
            default:
                return $this->msg('参数出错','/ucenter/message');
                //throw new ForbiddenHttpException('请求参数异常');
        }
        list($messageList,$pager)= SiteMessagesRelated::getMessageListByUserInfo($this->user_info,$condition);
//        f_d($messageList);
        $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
        $unReadMessageCount = SiteMessagesRelated::getUnReadMessageCountByUserId($user_info['id']);
        return $this->render('message',['messageList'=>$messageList,'pager'=>$pager, 'defaultAction'=>$defaultAction,'unReadCount'=>$unReadMessageCount]);
    }
    /**
     * @Title: actionComment
     * @Description: 订单评价
     * @return: return_type
     * @author: zend.wang
     * @date: 2015年11月02日上午9:58:12
     */
    public function actionComment($id=0)
    {
        $orderId= intval($id,0);
        $orderInfo = OmsInfo::find()->select(['id','order_code','order_time'])->where(['id'=>$orderId])->asArray()->one();

        if(empty($orderInfo)){
            return $this->msg('请求参数异常！','/ucenter/order');
            //throw new ForbiddenHttpException('请求参数异常！');
        }

        $isComment =GoodsComment::isCanCommentOrder($orderInfo['order_code']);//订单产品是否可以评价

        if(!$isComment){
            return $this->msg('该商品已完成评论，不需重复评论！','/ucenter/order');
            //throw new ForbiddenHttpException('该商品已完成评论，不需重复评论！');
        }

        $orderGoodsInfoList = GoodsComment::getUncommentGoodsByOrderCode($orderInfo['order_code']);//获取未被评论的产品信息

        if(Yii::$app->request->isPost){ //保存评论信息

            $comment = f_post('comment');

            $num = 0;
            foreach ($comment as $k=>$v) { //批量保存 待完善
                $model = new GoodsComment();
                $model->user_id = $this->user_info['id'];
                $model->order_code= $orderInfo['order_code'];
                $model->goods_id = $k;
                $model->supplier_id = $v['\'supplier_id\''];
                $model->content = $v['\'content\''];
                $model->add_time = f_date(time());
                $model->score = $v['\'score\''];
                $model->phone = $this->user_info['phone'];
                if($model->save()) {
                    $num+=1;
                    if($model->score == 5) RebateCouponGrant::addCouponOrder($model->order_code, $k,$model->content, $model->add_time, $model->user_id);
                }
                
            }
            //保存成功跳转到订单详情页面
            if($num == count($comment)) {
                OmsGoods::updateAll(["is_comment"=>1],["order_code"=>$orderInfo['order_code']]);
                return $this->msg('评论成功！','/ucenter/order');
            } else {
                return $this->msg('评论失败！','/ucenter/order');
            }
        }

        $orderGoodsNum = array_sum(BaseArrayHelper::getColumn($orderGoodsInfoList,'goods_num'));

        return $this->render('order-comment',['orderInfo'=>$orderInfo,'orderGoodsInfoList'=>$orderGoodsInfoList,'orderGoodsNum'=>$orderGoodsNum]);
    }
    /**
     * @Title: actionAddress
     * @Description: 收货地址管理
     * @return: return_type
     * @author: weilong.yin
     * @date: 2015年10月26日下午3:22:12
    */
    public function actionAddress(){
        $address = ReceiveAddress::find()->where(['user_id'=>$this->user_info['id']])->orderBy('status desc')->asArray()->all();
        $province = OtherRegion::getRegion(1);
       
        return $this->render('address',['address'=>$address,'address_num'=>count($address),'province'=>$province]);
    }
    
    /**
     * @Title: actionDefaultAddress
     * @Description: 设置默认地址
     * @return: return_type
     * @author: weilong.yin
     * @date: 2015年10月26日下午4:59:49
    */
    public function actionDefaultAddress(){
        if(isset($_POST['address_id'])&&!empty($_POST['address_id'])){
            $address = ReceiveAddress::findOne($_POST['address_id']);
            if(!empty($address)){
                ReceiveAddress::updateAll(['status'=>0],'user_id=:user_id',[':user_id'=>$address->user_id]);
                $address->status = 1;
                if($address->save()){
                    echo json_encode(1);exit;
                }else{
                    echo json_encode('修改失败！');exit;
                }
            }else{
                echo json_encode('修改失败！');exit;
            }
        }else {
            echo json_encode('修改失败！');exit;
        }
    }
    
    /**
     * @Title: actionGetCity
     * @Description: 获取城市
     * @return: return_type
     * @author: weilong.yin
     * @date: 2015年10月26日下午7:10:26
    */
    public function actionGetCity(){
        if(isset($_POST['province'])&&!empty($_POST['province'])){
            $data = OtherRegion::getSonRegion($_POST['province']);
            $html = '<option value="">请选择城市</option>';
            foreach ($data as $val){
                $html .='<option value="'.$val['region_id'].'">'.$val['region_name'].'</option>';
            }
            echo $html;exit;
        }
    }
    
    /**
     * @Title: actionGetDistrict
     * @Description: 获取区县
     * @return: return_type
     * @author: weilong.yin
     * @date: 2015年10月26日下午7:18:59
    */
    public function actionGetDistrict(){
        if(isset($_POST['city'])&&!empty($_POST['city'])){
            $data = OtherRegion::getSonRegion($_POST['city']);
            $html = '<option value="">请选择区县</option>';
            foreach ($data as $val){
                $html .='<option value="'.$val['region_id'].'">'.$val['region_name'].'</option>';
            }
            echo json_encode($html);exit;
        }
    }
    
    /**
     * @Title: actionAddAddress
     * @Description: 
     * @return: return_type
     * @author: weilong.yin
     * @date: 2015年10月26日下午8:59:17
    */
    public function actionAddAddress(){
        if(isset($_POST['receive_name'])&&isset($_POST['province'])&&isset($_POST['city'])
           &&isset($_POST['district'])&&isset($_POST['address'])&&isset($_POST['phone'])&&isset($_POST['tel'])){

            $model = new ReceiveAddress();
            $model->name = $_POST['receive_name'];
            $model->phone = $_POST['phone'];
            $model->tel = $_POST['tel'];
            $model->province = $_POST['province'];
            $model->city = $_POST['city'];
            $model->district = $_POST['district'];
            $model->address = $_POST['address'];
            $model->user_id = $this->user_info['id'];
            $model->status = 0;
            $model->save();
          
            $this->redirect('/ucenter/address');
        }
    }

    /**
     * @Title: actionAddressDelete
     * @Description: 删除地址
     * @return: return_type
     * @author: weilong.yin
     * @date: 2015年10月27日下午2:04:19
    */
    public function actionAddressDelete(){
        if(isset($_POST['address_id'])&&!empty($_POST['address_id'])){
            $out = ReceiveAddress::find()->andWhere(['id'=>$_POST['address_id']])->one();
            if($out->delete()){
                echo json_encode('1');exit;
            }
        }
    }
    
    /**
     * @Title: actionGetAddress
     * @Description: 获取地址详细
     * @return: return_type
     * @author: weilong.yin
     * @date: 2015年10月27日下午2:18:25
    */
    public function actionGetAddress(){
        if(isset($_POST['address_id'])&&!empty($_POST['address_id'])){
            $data = ReceiveAddress::findOne($_POST['address_id']);
            if(!empty($data)){
                $province = OtherRegion::getRegion(1);
                $h1 = '<option value="" >请选择省份</option>';
                foreach ($province as $key=>$value){
                    if($key==$data->province){
                        $h1 .='<option value="'.$key.'" selected="selected">'.$value.'</option>';
                    }else{
                        $h1 .='<option value="'.$key.'">'.$value.'</option>';
                    }
                }
                
                $city = OtherRegion::getSonRegion($data->province);
                $h2 = '<option value="" >请选择城市</option>';
                foreach ($city as $val){
                    if($val['region_id']==$data->city){
                        $h2 .='<option value="'.$val['region_id'].'" selected="selected">'.$val['region_name'].'</option>';
                    }else{
                        $h2 .='<option value="'.$val['region_id'].'">'.$val['region_name'].'</option>';
                    }
                }
                
                $district = OtherRegion::getSonRegion($data->city);
                $h3 = '<option value="">请选择区县</option>';
                foreach ($district as $val){
                    if($val['region_id']==$data->district){
                        $h3 .='<option value="'.$val['region_id'].'" selected="selected">'.$val['region_name'].'</option>';
                    }else{
                        $h3 .='<option value="'.$val['region_id'].'">'.$val['region_name'].'</option>';
                    }
                }
                
                $res = [
                    'id' => $data->id,
                    'receive_name' => $data->name,
                    'province' => $h1,
                    'city' => $h2,
                    'district' => $h3,
                    'address' => $data->address,
                    'phone' => $data->phone,
                    'tel' => $data->tel,
                ];
                
                return json_encode($res);exit;
            }
        }
    }
    
    /**
     * @Title: actionUpdateAddress
     * @Description: 修改收货地址
     * @return: return_type
     * @author: weilong.yin
     * @date: 2015年10月27日下午2:41:56
    */
    public function actionUpdateAddress(){
        if(isset($_POST['receive_name'])&&isset($_POST['province'])&&isset($_POST['city'])
            &&isset($_POST['district'])&&isset($_POST['address'])&&isset($_POST['phone'])&&isset($_POST['tel'])&&isset($_POST['address_id'])){

            $model = ReceiveAddress::findOne($_POST['address_id']);
            $model->name = $_POST['receive_name'];
            $model->phone = $_POST['phone'];
            $model->tel = $_POST['tel'];
            $model->province = $_POST['province'];
            $model->city = $_POST['city'];
            $model->district = $_POST['district'];
            $model->address = $_POST['address'];
            $model->user_id = $this->user_info['id'];
            $model->save();
            
            $this->redirect('/ucenter/address');
        }
    }

    /**
     *
     * @Title: actionFollow
     * @Description: 关注商品、商铺...
     * @return: bool | true 关注成功 false 关注失败
     * @author: zend.wang
     * @date: 2015-11-16上午09:31:55
     */
    public function actionFollow(){

        if(Yii::$app->request->getIsAjax()){

            $result = [];
            $action = trim(f_get('action','goods'));

            if(strcmp($action,'shop') == 0) {

                $shopId = intval(f_get('shop_id'),0);

                if(empty($shopId)){
                    throw new ForbiddenHttpException('请求参数异常！');
                }

                $result = FavoriteShop::followShop($this->user_info['id'],$shopId);

            }else if (strcmp($action,'goods') == 0){

                $baseId = intval(f_get('base_id'),0);
                $goodsId = intval(f_get('goods_id'),0);
                $supplierId = intval(f_get('supplier_id'),0);
                $color = intval(f_get('color_id'),0);

                if(empty($baseId)){
                    throw new ForbiddenHttpException('请求参数异常！');
                }

                $result = FavoriteGoods::followGoods($this->user_info['id'],$baseId,$goodsId,$supplierId,$color);
            }
            f_c('home_my_care_'.$this->user_info['id'],false);//clear cache
            echo Json::encode($result);
            Yii::$app->end();
        }
    }
    /**
     *
     * @Title: actionUnFollow
     * @Description: 取消关注商品、商铺...
     * @return: bool | true 关注成功 false 关注失败
     * @author: zend.wang
     * @date: 2015-11-16上午09:31:55
     */
    public function actionUnfollow(){
        if(Yii::$app->request->isAjax){
            $ids = trim(f_post('ids',''));
            $ids && $ids =  explode(',',$ids);
            $action = trim(f_post('action','goods'));
            $result = [];
            switch($action){
                case 'goods':
                {
                    $base_id = f_post('base_id','');
                    $result = FavoriteGoods::unFollowGoods($this->user_info['id'],$base_id,$ids);
                    break;
                }
                case 'shop':
                {
                    $shop_id = f_post('shop_id','');
                    $result = FavoriteShop::unFollowShop($this->user_info['id'],$shop_id,$ids);
                    break;
                }
                default:
                    throw new ForbiddenHttpException('请求参数异常');
            }
            f_c('home_my_care_'.$this->user_info['id'],false);//clear cache
            return json_encode($result);exit;
        }
    }
    /**
     * @Title: actionSignNew
     * @Description: 新版签到页面
     * @return: return_type
     * @author: wei.xie honglang.shen
     * @date: 2015-11-25
     */
    public function actionSignNew(){
        header("Content-type:text/html;charset=utf-8");
        $this->layout="sign_new";
        $user_name = $this->user_info['user_name'];
        $user_id = $this->user_info['id'];
        $templateInfo = SignTemplate::find()->select('*')->asArray()->one();
        //当前生效的活动
        $active_info = SignNew::find()->where(['status'=>1])->asArray()->one();
        if($active_info){
            $active_name = $active_info['active_name'];            $active_id = $active_info['id'];
            $active_range = $active_info['start_range'];   
        }else{
			$active_name = '';
            $active_id = '';
            $active_range = '';   
        }
        //活动须知
        $active_key_x = "active_xz";
        $active_tips_x = Yii::$app->cache->get($active_key_x);
        if(!$active_tips_x){
            $active_tips_x = SignNewTips::find()->where(['active_id'=>$active_id,'type_id'=>1])->asArray()->all();
            Yii::$app->cache->set($active_key_x,$active_tips_x,3600*24);
        }
        //近期公告
        $active_tips_g = SignNewTips::find()->where(['active_id'=>$active_id,'type_id'=>2])->asArray()->all();
        //奖项设置
        $active_prize = SignNewPrize::find()->where(['active_id'=>$active_id])->asArray()->all();
        //前几期奖项情况
        $win_info = SignNewWin::find()->orderBy('id desc')->asArray()->all();
        //当前用户在本次活动的抽奖码
        //活动天数活动连续签到天数
        $k = 0;
        $data = [];
        $days = ceil(($active_info['end_time']-$active_info['start_time'])/(3600*24));
        for($i=0;$i<$days;$i++){
            $start_time = date("Y-m-d H:i:s",$active_info['start_time']+($i*3600*24));
            $end_time = date("Y-m-d H:i:s",strtotime($start_time)+3600*24);
            $sql = "SELECT * FROM user_wealth_detail WHERE user_id = $user_id AND type = 2 AND add_time > '$start_time' AND add_time < '$end_time'";
            $sign_info = \Yii::$app->db->createCommand($sql)->queryOne();
            //拼接日期
            $sign_day = date("Y-m-d",$active_info['start_time']+($i*3600*24));
            $mouth = (int)substr($sign_day,5,2);
            $now_day = (int)substr($sign_day,8,2);
            $sign_day2 = $mouth."月".$now_day."日";
            $data[$i]['sign_day'] = $sign_day2;
            if($sign_info){
                $data[$i]['sign_info'] = $sign_info;
                $k++;
            }else{
                $data[$i]['sign_info'] = 0;
            }
        }
        //获取抽奖码
        $open_status = 0;
        if(!empty($data)){
            $last_sign = $data[$days-1]['sign_info'];
        }else{
            $last_sign = '';
        }
        if($last_sign && $k==$days){
            $open_status = 1;
            $user_sign = SignNewLottery::find()->where(['user_id'=>$user_id,'active_id'=>$active_id])->asArray()->one();
            if($user_sign){
                $win_num = $user_sign['lottery_code'];
            }else{
                $all_sign = SignNewLottery::find()->where(['active_id'=>$active_id])->orderBy('id desc')->limit(1)->asArray()->one();
                if($all_sign){
                    $win_num = $all_sign['lottery_code']+1;
                }else{
                    $win_num = $active_range;
                }
                $model = new SignNewLottery();
                $model->user_id = $user_id;
                $model->user_name = $user_name;
                $model->lottery_code = $win_num;
                $model->active_id = $active_id;
                $model->save();
            }
        }else{
            $win_num = "";
        }
        //今日签到排名
        $now_dd = date("Y-m-d 00:00:00",time());
        $end_dd = date("Y-m-d 23:59:59",time());
        $my_sign_time = WealthDetail::findBySql("select add_time from user_wealth_detail where user_id = '$user_id' && type=2 && add_time > '$now_dd' && add_time < '$end_dd'")->asArray()->one();
       // f_d($my_sign_time['add_time']);
        $sql = "select * from user_wealth_detail where type=2 && add_time > '$now_dd' && add_time < '$my_sign_time[add_time]'";
        $result = WealthDetail::findBySql($sql)->asArray()->all();
        $count = count($result)+1;
        //获取用户在这个月的签到记录---[1.3.5,7,8,10,12]-[4,6,9,11]-[2]
        $m = date('m',time());
        $y = date('Y',time());
        $start_d = "".$y."-".$m."-1 00:00:00";
        if(in_array($m,[1,3,5,7,8,10,12])){
          $end_d = "".$y."-".$m."-31 23:59:59"; 
        }elseif(in_array($m,[4,6,9,11])){
          $end_d = "".$y."-".$m."-30 23:59:59"; 
        }elseif(($y%4)==0){
          $end_d = "".$y."-".$m."-29 23:59:59"; 
        }else{
          $end_d = "".$y."-".$m."-28 23:59:59"; 
        }
            $sql = "select * from user_wealth_detail where user_id = '$user_id' && type=2 && add_time > '$start_d' && add_time < '$end_d'";
            $res = WealthDetail::findBySql($sql)->asArray()->all();
            $each_sign = [];
            foreach($res as $val){
                $dd = (int)substr($val['add_time'],8,2);
                $each_sign[]= $dd ;
            }
            $each_sign = json_encode($each_sign);
            //获取宣传图片
            $img_data = [];
            $win_img = (new Query())->from("{{%other_ad_position}} a")
                ->leftJoin("{{%other_ad_info}} b","b.position_id=a.id")
                ->where(['a.id'=>77,'a.is_deleted'=>0])
                ->orderBy('b.sort asc')
                ->all();
            for($i=0;$i<ceil(count($win_img)/3);$i++){
                $img_data[] =  array_slice($win_img,$i*3,3);
            }
            if (empty($templateInfo)){
            	$view = 'sign_new';
            }else {
            	$view = 'sign_new2';
            }
            return $this->render("$view",[
                    'sign_data'=>$data,
                    'active_name'=>$active_name,                    'active_tips_x'=>$active_tips_x,
                    'active_tips_g'=>$active_tips_g,
                    'active_prize'=>$active_prize,
                    'user_name'=>$user_name,
                    'win_info'=>$win_info,
                    'days_k'=>$days,
                    'open_status'=>$open_status,
                    'win_num'=>$win_num,
                    'continue'=>$k,
                    'img_data'=>$img_data,
                    'each_sign'=>$each_sign,
                    'count'=>$count,
            		'templateInfo'=>$templateInfo
            ]);
        }


    /**
     * @Title: actionSignNew
     * @Description: 新版签到页面
     * @return: return_type
     * @author: wei.xie honglang.shen
     * @date: 2015-11-25
     */
    public function actionTempSign1(){
        header("Content-type:text/html;charset=utf-8");
        $this->layout="sign_new";
        $user_name = $this->user_info['user_name'];
        $user_id = $this->user_info['id'];
        //当前生效的活动
        $active_info = SignNew::find()->where(['status'=>1])->asArray()->one();
        if($active_info){
            $active_name = $active_info['active_name'];
            $active_id = $active_info['id'];
            $active_range = $active_info['start_range'];
        }else{
            $active_name = '';
            $active_id = '';
            $active_range = '';
        }
        //活动须知
        $active_key_x = "active_xz";
        $active_tips_x = Yii::$app->cache->get($active_key_x);
        if(!$active_tips_x){
            $active_tips_x = SignNewTips::find()->where(['active_id'=>$active_id,'type_id'=>1])->asArray()->all();
            Yii::$app->cache->set($active_key_x,$active_tips_x,3600*24);
        }
        //近期公告
        $active_tips_g = SignNewTips::find()->where(['active_id'=>$active_id,'type_id'=>2])->asArray()->all();
        //奖项设置
        $active_prize = SignNewPrize::find()->where(['active_id'=>$active_id])->asArray()->all();
        //前几期奖项情况
        $win_info = SignNewWin::find()->orderBy('id desc')->asArray()->all();
        //当前用户在本次活动的抽奖码
        //活动天数活动连续签到天数
        $k = 0;
        $data = [];
        $days = ceil(($active_info['end_time']-$active_info['start_time'])/(3600*24));
        for($i=0;$i<$days;$i++){
            $start_time = date("Y-m-d H:i:s",$active_info['start_time']+($i*3600*24));
            $end_time = date("Y-m-d H:i:s",strtotime($start_time)+3600*24);
            $sql = "SELECT * FROM user_wealth_detail WHERE user_id = $user_id AND type = 2 AND add_time > '$start_time' AND add_time < '$end_time'";
            $sign_info = \Yii::$app->db->createCommand($sql)->queryOne();
            //拼接日期
            $sign_day = date("Y-m-d",$active_info['start_time']+($i*3600*24));
            $mouth = (int)substr($sign_day,5,2);
            $now_day = (int)substr($sign_day,8,2);
            $sign_day2 = $mouth."月".$now_day."日";
            $data[$i]['sign_day'] = $sign_day2;
            if($sign_info){
                $data[$i]['sign_info'] = $sign_info;
                $k++;
            }else{
                $data[$i]['sign_info'] = 0;
            }
        }
        //获取抽奖码
        $open_status = 0;
        if(!empty($data)){
            $last_sign = $data[$days-1]['sign_info'];
        }else{
            $last_sign = '';
        }
        if($last_sign && $k==$days){
            $open_status = 1;
            $user_sign = SignNewLottery::find()->where(['user_id'=>$user_id,'active_id'=>$active_id])->asArray()->one();
            if($user_sign){
                $win_num = $user_sign['lottery_code'];
            }else{
                $all_sign = SignNewLottery::find()->where(['active_id'=>$active_id])->orderBy('id desc')->limit(1)->asArray()->one();
                if($all_sign){
                    $win_num = $all_sign['lottery_code']+1;
                }else{
                    $win_num = $active_range;
                }
                $model = new SignNewLottery();
                $model->user_id = $user_id;
                $model->user_name = $user_name;
                $model->lottery_code = $win_num;
                $model->active_id = $active_id;
                $model->save();
            }
        }else{
            $win_num = "";
        }
        //今日签到排名
        $now_dd = date("Y-m-d 00:00:00",time());
        $end_dd = date("Y-m-d 23:59:59",time());
        $my_sign_time = WealthDetail::findBySql("select add_time from user_wealth_detail where user_id = '$user_id' && type=2 && add_time > '$now_dd' && add_time < '$end_dd'")->asArray()->one();
        // f_d($my_sign_time['add_time']);
        $sql = "select * from user_wealth_detail where type=2 && add_time > '$now_dd' && add_time < '$my_sign_time[add_time]'";
        $result = WealthDetail::findBySql($sql)->asArray()->all();
        $count = count($result)+1;
        //获取用户在这个月的签到记录---[1.3.5,7,8,10,12]-[4,6,9,11]-[2]
        $m = date('m',time());
        $y = date('Y',time());
        $start_d = "".$y."-".$m."-1 00:00:00";
        if(in_array($m,[1,3,5,7,8,10,12])){
            $end_d = "".$y."-".$m."-31 23:59:59";
        }elseif(in_array($m,[4,6,9,11])){
            $end_d = "".$y."-".$m."-30 23:59:59";
        }elseif(($y%4)==0){
            $end_d = "".$y."-".$m."-29 23:59:59";
        }else{
            $end_d = "".$y."-".$m."-28 23:59:59";
        }
        $sql = "select * from user_wealth_detail where user_id = '$user_id' && type=2 && add_time > '$start_d' && add_time < '$end_d'";
        $res = WealthDetail::findBySql($sql)->asArray()->all();
        $each_sign = [];
        foreach($res as $val){
            $dd = (int)substr($val['add_time'],8,2);
            $each_sign[]= $dd ;
        }
        $each_sign = json_encode($each_sign);
        //获取宣传图片
        $img_data = [];
        $win_img = (new Query())->from("{{%other_ad_position}} a")
            ->leftJoin("{{%other_ad_info}} b","b.position_id=a.id")
            ->where(['a.id'=>77,'a.is_deleted'=>0])
            ->orderBy('b.sort asc')
            ->all();
        for($i=0;$i<ceil(count($win_img)/3);$i++){
            $img_data[] =  array_slice($win_img,$i*3,3);
        }
        return $this->render('temp_sign',[
            'sign_data'=>$data,
            'active_name'=>$active_name,
            'active_tips_x'=>$active_tips_x,
            'active_tips_g'=>$active_tips_g,
            'active_prize'=>$active_prize,
            'user_name'=>$user_name,
            'win_info'=>$win_info,
            'days_k'=>$days,
            'open_status'=>$open_status,
            'win_num'=>$win_num,
            'continue'=>$k,
            'img_data'=>$img_data,
            'each_sign'=>$each_sign,
            'count'=>$count,
        ]);
    }


    /**
     * @Title: actionSignNew
     * @Description: 新版签到页面
     * @return: return_type
     * @author: wei.xie honglang.shen
     * @date: 2015-11-25
     */
    public function actionTempSign2(){
        header("Content-type:text/html;charset=utf-8");
        $this->layout="sign_new";
        $user_name = $this->user_info['user_name'];
        $user_id = $this->user_info['id'];
        //当前生效的活动
        $active_info = SignNew::find()->where(['status'=>1])->asArray()->one();
        if($active_info){
            $active_name = $active_info['active_name'];
            $active_id = $active_info['id'];
            $active_range = $active_info['start_range'];
        }else{
            $active_name = '';
            $active_id = '';
            $active_range = '';
        }
        //活动须知
        $active_key_x = "active_xz";
        $active_tips_x = Yii::$app->cache->get($active_key_x);
        if(!$active_tips_x){
            $active_tips_x = SignNewTips::find()->where(['active_id'=>$active_id,'type_id'=>1])->asArray()->all();
            Yii::$app->cache->set($active_key_x,$active_tips_x,3600*24);
        }
        //近期公告
        $active_tips_g = SignNewTips::find()->where(['active_id'=>$active_id,'type_id'=>2])->asArray()->all();
        //奖项设置
        $active_prize = SignNewPrize::find()->where(['active_id'=>$active_id])->asArray()->all();
        //前几期奖项情况
        $win_info = SignNewWin::find()->orderBy('id desc')->asArray()->all();
        //当前用户在本次活动的抽奖码
        //活动天数活动连续签到天数
        $k = 0;
        $data = [];
        $days = ceil(($active_info['end_time']-$active_info['start_time'])/(3600*24));
        for($i=0;$i<$days;$i++){
            $start_time = date("Y-m-d H:i:s",$active_info['start_time']+($i*3600*24));
            $end_time = date("Y-m-d H:i:s",strtotime($start_time)+3600*24);
            $sql = "SELECT * FROM user_wealth_detail WHERE user_id = $user_id AND type = 2 AND add_time > '$start_time' AND add_time < '$end_time'";
            $sign_info = \Yii::$app->db->createCommand($sql)->queryOne();
            //拼接日期
            $sign_day = date("Y-m-d",$active_info['start_time']+($i*3600*24));
            $mouth = (int)substr($sign_day,5,2);
            $now_day = (int)substr($sign_day,8,2);
            $sign_day2 = $mouth."月".$now_day."日";
            $data[$i]['sign_day'] = $sign_day2;
            if($sign_info){
                $data[$i]['sign_info'] = $sign_info;
                $k++;
            }else{
                $data[$i]['sign_info'] = 0;
            }
        }
        //获取抽奖码
        $open_status = 0;
        if(!empty($data)){
            $last_sign = $data[$days-1]['sign_info'];
        }else{
            $last_sign = '';
        }
        if($last_sign && $k==$days){
            $open_status = 1;
            $user_sign = SignNewLottery::find()->where(['user_id'=>$user_id,'active_id'=>$active_id])->asArray()->one();
            if($user_sign){
                $win_num = $user_sign['lottery_code'];
            }else{
                $all_sign = SignNewLottery::find()->where(['active_id'=>$active_id])->orderBy('id desc')->limit(1)->asArray()->one();
                if($all_sign){
                    $win_num = $all_sign['lottery_code']+1;
                }else{
                    $win_num = $active_range;
                }
                $model = new SignNewLottery();
                $model->user_id = $user_id;
                $model->user_name = $user_name;
                $model->lottery_code = $win_num;
                $model->active_id = $active_id;
                $model->save();
            }
        }else{
            $win_num = "";
        }
        //今日签到排名
        $now_dd = date("Y-m-d 00:00:00",time());
        $end_dd = date("Y-m-d 23:59:59",time());
        $my_sign_time = WealthDetail::findBySql("select add_time from user_wealth_detail where user_id = '$user_id' && type=2 && add_time > '$now_dd' && add_time < '$end_dd'")->asArray()->one();
        // f_d($my_sign_time['add_time']);
        $sql = "select * from user_wealth_detail where type=2 && add_time > '$now_dd' && add_time < '$my_sign_time[add_time]'";
        $result = WealthDetail::findBySql($sql)->asArray()->all();
        $count = count($result)+1;
        //获取用户在这个月的签到记录---[1.3.5,7,8,10,12]-[4,6,9,11]-[2]
        $m = date('m',time());
        $y = date('Y',time());
        $start_d = "".$y."-".$m."-1 00:00:00";
        if(in_array($m,[1,3,5,7,8,10,12])){
            $end_d = "".$y."-".$m."-31 23:59:59";
        }elseif(in_array($m,[4,6,9,11])){
            $end_d = "".$y."-".$m."-30 23:59:59";
        }elseif(($y%4)==0){
            $end_d = "".$y."-".$m."-29 23:59:59";
        }else{
            $end_d = "".$y."-".$m."-28 23:59:59";
        }
        $sql = "select * from user_wealth_detail where user_id = '$user_id' && type=2 && add_time > '$start_d' && add_time < '$end_d'";
        $res = WealthDetail::findBySql($sql)->asArray()->all();
        $each_sign = [];
        foreach($res as $val){
            $dd = (int)substr($val['add_time'],8,2);
            $each_sign[]= $dd ;
        }
        $each_sign = json_encode($each_sign);
        //获取宣传图片
        $img_data = [];
        $win_img = (new Query())->from("{{%other_ad_position}} a")
            ->leftJoin("{{%other_ad_info}} b","b.position_id=a.id")
            ->where(['a.id'=>77,'a.is_deleted'=>0])
            ->orderBy('b.sort asc')
            ->all();
        for($i=0;$i<ceil(count($win_img)/3);$i++){
            $img_data[] =  array_slice($win_img,$i*3,3);
        }
        return $this->render('temp_sign1',[
            'sign_data'=>$data,
            'active_name'=>$active_name,
            'active_tips_x'=>$active_tips_x,
            'active_tips_g'=>$active_tips_g,
            'active_prize'=>$active_prize,
            'user_name'=>$user_name,
            'win_info'=>$win_info,
            'days_k'=>$days,
            'open_status'=>$open_status,
            'win_num'=>$win_num,
            'continue'=>$k,
            'img_data'=>$img_data,
            'each_sign'=>$each_sign,
            'count'=>$count,
        ]);
    }


    /**
     * @Title: actionDetail
     * @Description: 退货详情页
     * @return: return_type
     * @author: min.wan
     * @date: 2015-12-03 上午12:01:15
     */

    public function actionDetails()
    {

        $sales_code = Yii::$app->request->getQueryParam("sales_order","");

        if($sales_code)
        {
            //取出对应数据
            $data = (new Query())->select("t1.*,t2.*")->from("after_sales_order as t1")->leftJoin("{{after_sales_goods}} as t2","t1.sales_order = t2.sales_order")
//                ->leftJoin("{{after_sales_log}} as t3", "t3.sales_order = t2.sales_order")
                ->where(['t1.sales_order' => $sales_code])
                ->all();

            $logs = (new Query())->select("t1.*")->from("after_sales_log as t1")
                ->where(['t1.sales_order' => $sales_code])->orderBy(" operate_time desc ")
                ->all();

        }

        return $this->render("detail",[
            "items"=> $data,"logs"=> $logs
        ]);
    }

    /**
     * @Title: addComment
     * @Description: 添加评论
     * @return: return_type
     * @author: min.wan
     * @date: 2015-12-03 上午12:01:15
     */
    public function addComment()
    {
        $score = Yii::$app->request->post('score');
        $userid = $this->user_info['id'];
        $content = Yii::$app->request->post('content');
        $order_code = Yii::$app->request->post('order_code');
        $goods_id = Yii::$app->request->post('goods_id');
        $supplier_id  = Yii::$app->request->post("supplier_id");

        $connection = Yii::$app->db;
        $transaction = $connection->beginTransaction();
        try {

            $model = new GoodsComment();
            $model->score =$score;
            $model->user_id = $userid;
            $model->content = $content;
            $model->order_code =$order_code;
            $model->goods_id = $goods_id;
            $model->add_time = date("Y-m-d H:i:s",time());
            $model->supplier_id = $supplier_id;
            $model->phone = $this->user_info['phone'];
            $res = $model->save();

            $rs = OmsGoods::updateAll(["is_comment"=>1],["order_code"=>$order_code]);

            $transaction->commit();
            return json_encode(array('code' => "200", "message" => "评论成功"));

        } catch (Exception $e) {
            $transaction->rollBack();
            return json_encode(array('code' => "400", "message" => "评论失败"));

        }

    }

    /**
     * @Title: actionNoComment
     * @Description: 待评价
     * @return: return_type
     * @author: min.wan
     * @date: 2015-12-03 上午12:01:15
     * @review_user:zhanghy
     */
    public function actionNoComment()
    {
        $uid = $this->user_info['id'];
        $search = Yii::$app->request->getQueryParam("search","");
        $search_type = f_get('search_type',1);
        if(empty($search))
        {
            //and t1.user_id = .".$uid."
            $sql = "select t2.* from order_oms_info as t1 join order_oms_goods as t2 on t1.order_code = t2.order_code and t2.is_comment = 0
               and t1.user_id = ".$uid." AND t1.order_status IN (45,135,220,655)  ORDER  by t1.id DESC ";

            $items = Yii::$app->db->createCommand($sql)->queryAll();

            //分页对象
            $pagination = new Pagination([
                'defaultPageSize' => 10,
                'totalCount' => count($items)
            ]);
            //'t1.user_id' => $uid,
            $res = (new Query())->select("t2.*")->from("order_oms_info as t1")->leftJoin("{{order_oms_goods}} as t2 ",'t1.order_code = t2.order_code')
                ->where(['t2.is_comment' => 0])
                ->andWhere(['t1.user_id' => $uid])
                ->andWhere(['in','order_status','45,135,220,655'])
                ->limit($pagination->limit)
                ->offset($pagination->offset)->orderBy('t1.id desc')->all();
        }
        else
        {
            $flag = 0;
            $flag_goods_name = 0;
            $flag_order_code = 0;
            $condition = "";
            if($search_type == 1){
                $condition .=" and t2.order_code='".$search."' ";
                $flag_order_code = 1;
            }
            elseif($search_type == 2)
            {
                $flag = 1;
                $order_code = (new Query())->select("order_code")->from("{{goods_serial_code}}")->where(['serial_code' => $search])->scalar();
                if($order_code)//存在该串号
                {
                    $condition .=" and t2.order_code = "."'".$order_code."'";
                }
                else
                {
                    $serial_code = AfterSalesSerialCode::find()->where(['serial_code'=>$search,'flag'=>1])->asArray()->one();
                    if($serial_code)
                    {
                        $order_code = AfterSalesOrder::find()->select('order_code')->where(['sales_order'=>$serial_code['sales_order']])->scalar();
                        if($order_code){
                            $condition .=" and t2.order_code = "."'".$order_code."'";
                        }
                        else
                        {
                            $condition .=" and t2.order_code = '"." '";
                        }
                    }
                    else
                    {
                        $condition .=" and t2.order_code = '"." '";
                    }
                }
            }
            elseif($search_type == 3)
            {
                $flag_goods_name = 1;
                $condition .=" and t2.goods_name like '%".$search."%' ";
            }
            //商品名称 ;  串号; 订单编号

//            if(preg_match("/^\d{15}$/",$search))
//            {
//                $condition .=" and t2.order_code='".$search."' ";
//                $flag_order_code = 1;
//            }
//            elseif(preg_match("/^[a-z\d]*$/i",$search) && strlen($search)<= 16)
//            {
//                //串码 => order_code 根据串码获取order_code
//                $flag = 1;
//                $order_code = (new Query())->select("order_code")->from("{{goods_serial_code}}")
//                    ->where(['serial_code' => $search]);
//                if($order_code)//存在该串号
//                {
//                    $condition .=" and t2.order_code = "."'".$search."'";
//                }
//                else{
//                    $condition .=" and t2.order_code = '"." '";
//                }
//            }
//            else
//            {
//                $flag_goods_name = 1;
//                $condition .=" and t2.goods_name like '%".$search."%' ";
//            }
//
            if($flag)
            {
                //uid
                $sql = "select t2.* from order_oms_info as t1 join order_oms_goods as t2 on t1.order_code = t2.order_code and t2.is_comment = 0
                and t1.user_id = ".$uid.$condition."  AND t1.order_status IN (45,135,220,655) ORDER  by t1.id DESC ";

                $count =  OmsInfo::findBySql($sql)->count();
                //分页对象
                $pagination = new Pagination([
                    'defaultPageSize' => 10,
                    'totalCount' => $count
                ]);

                $data = (new Query())->select("t2.*")->from("order_oms_info as t1")->leftJoin("{{order_oms_goods}} as t2 ",'t1.order_code = t2.order_code')
                    ->where(['t2.is_comment' => 0])
                    ->andWhere(['t1.user_id' => $uid])
                    ->andWhere(['in','order_status','45,135,220,655'])
                    ->limit($pagination->limit)
                    ->offset($pagination->offset)->orderBy('t1.id desc')->andWhere(['t2.order_code'=>$order_code])->all();
                $res = $data;
//                if($order_code)
//                {
//                    $res = $data->andWhere(['t2.order_code'=>$search])->all();
//                }
//                else
//                {
//                    $res = $data->all();
//                }

            }
            else
            {
                $sql = "select t2.* from order_oms_info as t1 join order_oms_goods as t2 on t1.order_code = t2.order_code and t2.is_comment = 0
                 and t1.user_id = ".$uid.$condition."  AND t1.order_status IN (45,135,220,655)  ORDER  by t1.id DESC ";

                $count =  OmsInfo::findBySql($sql)->count();

                //分页对象
                $pagination = new Pagination([
                    'defaultPageSize' => 10,
                    'totalCount' => $count
                ]);

                $data = (new Query())->select("t2.*")->from("order_oms_info as t1")->leftJoin("{{order_oms_goods}} as t2 ",'t1.order_code = t2.order_code')
                    ->where(['t2.is_comment' => 0])
                    ->andWhere(['t1.user_id' => $uid])
                    ->andWhere(['in','order_status','45,135,220,655'])
                    ->limit($pagination->limit)
                    ->offset($pagination->offset)->orderBy('t1.id desc');

                if($flag_order_code)
                {
                    $res = $data->andWhere(['t2.order_code'=>$search])->all();
                }
                elseif($flag_goods_name)
                {
                    $res = $data->andWhere(['like', 't2.goods_name', $search])->all();
                }
                else
                {
                    $res = $data->all();
                }

            }

        }

        return $this->render(
            'no-comment',['items' => $res,'pagination'=>$pagination]
        );


    }



    /**
     * @Title: actionRecord
     * @Description: 查看返修记录
     * @return: return_type
     * @author: min.wan
     * @date: 2015-12-03 上午12:01:15
     */
    public function actionRecord()
    {
        $uid = $this->user_info['id'];
        $search = Yii::$app->request->getQueryParam("search","");
        $search_type = f_get('search_type',1);
        if(empty($search))
        {
            $sql = " SELECT
            t1.sales_time,
            t1.order_code,
            t1.status,
            t2.goods_name,
            t2.goods_id,
            t1.sales_order,
            t1.if_kh
        FROM
            after_sales_order AS t1
        JOIN after_sales_goods AS t2 ON t1.id = t2.sales_id and t1.user_id = ".$uid." order by t1.sales_time DESC";

            $count =  OmsInfo::findBySql($sql)->count();
            //分页对象
            $pagination = new Pagination([
                'defaultPageSize' => 10,
                'totalCount' => $count
            ]);

            $res = (new Query())->select("t1.sales_time,t1.order_code,t1.status, t2.goods_name,t2.goods_id, t2.sales_order,t1.if_kh")->from("{{after_sales_order}} as t1")
                ->rightJoin("{{after_sales_goods}} as t2 ",'t1.id = t2.sales_id')
                ->where(['t1.user_id' => $uid])
                ->limit($pagination->limit)
                ->offset($pagination->offset)->orderBy('t1.sales_time desc')->all();

        }
        else
        {
            $flag = 0;
            $flag_goods_name = 0;
            $flag_order_code = 0;
            $condition = "";
            if($search_type == 1){
                $condition .=" and t1.order_code='".$search."' ";
                $flag_order_code = 1;
            }
            elseif($search_type == 2)
            {
                $flag = 1;
                $order_code = (new Query())->select("order_code")->from("{{goods_serial_code}}")->where(['serial_code' => $search])->scalar();
                if($order_code)//存在该串号
                {
                    $condition .=" and t1.order_code = "."'".$order_code."'";
                }
                else
                {
                    $serial_code = AfterSalesSerialCode::find()->where(['serial_code'=>$search,'flag'=>1])->asArray()->one();
                    if($serial_code)
                    {
                        $order_code = AfterSalesOrder::find()->select('order_code')->where(['sales_order'=>$serial_code['sales_order']])->scalar();
                        if($order_code){
                            $condition .=" and t1.order_code = "."'".$order_code."'";
                        }
                        else
                        {
                            $condition .=" and t1.order_code = '"." '";
                        }
                    }
                    else
                    {
                        $condition .=" and t1.order_code = '"." '";
                    }
                }
            }
            elseif($search_type == 3)
            {
                $flag_goods_name = 1;
                $condition .=" and t2.goods_name like '%".$search."%' ";
            }
//            //商品名称 ;  串号; 订单编号
//            if(preg_match("/^\d{15}$/",$search))
//            {
//                $condition .=" and t1.order_code='".$search."' ";
//                $flag_order_code = 1;
//            }
//            elseif(preg_match("/^[a-z\d]*$/i",$search) && strlen($search)<= 16)
//            {
//                //串码 => order_code 根据串码获取order_code
//                $flag = 1;
//                $order_code = (new Query())->select("order_code")->from("{{goods_serial_code}}")
//                    ->where(['serial_code' => $search]);
//                if($order_code)//存在该串号
//                {
//                    $condition .=" and t1.order_code = "."'".$search."'";
//                }
//                else{
//                    $condition .=" and t1.order_code = '"." '";
//                }
//            }
//            else
//            {
//                $flag_goods_name = 1;
//                $condition .=" and t2.goods_name like '%".$search."%' ";
//            }



            if($flag)
            {

                $sql = " SELECT
                    t1.sales_time,
                    t1.order_code,
                    t1.status,
                    t2.goods_name,
                    t2.goods_id,
                    t2.sales_order,
                    t1.if_kh
                FROM
                    after_sales_order AS t1
                JOIN after_sales_goods AS t2 ON t1.id = t2.sales_id and t1.user_id = ".$uid.$condition." order by t1.sales_time DESC";

                $count =  OmsInfo::findBySql($sql)->count();
                //分页对象
                $pagination = new Pagination([
                    'defaultPageSize' => 10,
                    'totalCount' => $count
                ]);

                $data = (new Query())->select("t1.sales_time,t1.order_code,t1.status, t2.goods_name,t2.goods_id,t2.sales_order,t1.if_kh")->from("{{after_sales_order}} as t1")
                    ->rightJoin("{{after_sales_goods}} as t2 ",'t1.id = t2.sales_id')
                    ->where(['t1.user_id' => $uid])
                    ->limit($pagination->limit)
                    ->offset($pagination->offset)->orderBy('t1.sales_time desc')->andWhere(['t1.order_code'=>$order_code])->all();
                $res = $data;
//                if($order_code)
//                {
//                    $res = $data->andWhere(['t1.order_code'=>$search])->all();
//                }
//                else
//                {
//                    $res = $data->all();
//                }

            }
            else
            {
                $sql = " SELECT
                    t1.sales_time,
                    t1.order_code,
                    t1.status,
                    t2.goods_name,
                    t2.goods_id,
                    t2.sales_order,
                    t1.if_kh
                FROM
                    after_sales_order AS t1
                JOIN after_sales_goods AS t2 ON t1.id = t2.sales_id and t1.user_id = ".$uid.$condition." order by t1.sales_time DESC";

                $count =  OmsInfo::findBySql($sql)->count();

                //分页对象
                $pagination = new Pagination([
                    'defaultPageSize' => 10,
                    'totalCount' => $count
                ]);

                $data = (new Query())->select("t1.sales_time,t1.order_code,t1.status, t2.goods_name,t2.goods_id,t2.sales_order,t1.if_kh")->from("{{after_sales_order}} as t1")
                    ->rightJoin("{{after_sales_goods}} as t2 ",'t1.id = t2.sales_id')
                    ->where(['t1.user_id' => $uid])
                    ->limit($pagination->limit)
                    ->offset($pagination->offset)->orderBy('t1.sales_time desc');
//                echo $data->createCommand()->getSql();die;
                if($flag_order_code)
                {
                    $res = $data->andWhere(['t1.order_code'=>$search])->all();
                }
                elseif($flag_goods_name)
                {
                    $res = $data->andWhere(['like', 't2.goods_name', $search])->all();
                }
                else
                {
                    $res = $data->all();
                }

            }

        }

        $url = $this->getAfterSalesUrl();
       //$this->layout = '_blank';
        return $this->render(
            'record',['items' => $res,'pagination'=>$pagination,'url'=>$url]
        );

    }

    /**
     * @Title: actionAfterSales
     * @Description: 申请售后
     * @return: return_type
     * @author: leo
     * @date: 2015-12-03 上午12:01:15
     * @review_user:zhanghy
     */
    public function actionAfterSales(){
        $search_type = f_get('search_type',1);
        $content = f_get('search','');
        $time_line = f_get('searchTime',1);
        $query = $this->getSearchRes($search_type,$content,'sh',$time_line);
        $url = $this->getAfterSalesUrl();
        if($time_line == 2){
            $pagination = new Pagination(['defaultPageSize' => 10, 'totalCount' => $query->count('*',Yii::$app->get('db2'))]);
            $data = $query->limit($pagination->limit)->offset($pagination->offset)->all(Yii::$app->get('db2'));
            return $this->render('old-after-sales',['items'=>$data,'pagination'=>$pagination,'url'=>$url]);
        }else{
            $pagination = new Pagination(['defaultPageSize' => 10, 'totalCount' => $query->count()]);
            $data = $query->limit($pagination->limit)->offset($pagination->offset)->all();
            return $this->render('after-sales',['items'=>$data,'pagination'=>$pagination,'url'=>$url]);
        }

//        $uid = $this->user_info['id'];
//
//        if (isset($_GET['search'])) {
//            $condition = ['search' => f_get('search')];
//        }
//
//        $search_time = f_get('searchTime',1);
//
//        if($search_time ==1){
//            $query = (new Query())->select("t1.order_time,t1.order_code")
//                                     ->from("{{order_oms_info}} as t1")
//                                     ->leftJoin("{{order_oms_goods}} as t2 ",'t1.order_code = t2.order_code')
//                                     ->leftJoin('{{goods_supplier_goods}} as t4','t2.goods_id = t4.id')
//                                     ->where(['t1.user_id' => $uid])
//                                     ->andWhere(['in','t1.order_status',[45,70,135,160,220,240]])
//                                     ->orderBy('t1.order_time desc')->distinct();
//
//            if (!empty($condition['search'])) {
//
//                if (strlen($condition['search']) >= 15){
//                    if (substr($condition['search'],0,2) == '14' || substr($condition['search'],0,2) == '15' || substr($condition['search'],0,2) == '16' ||substr($condition['search'],0,2) == '17'
//                        ||substr($condition['search'],1,4) == '2015' || substr($condition['search'],0,4) == '2015') {
//                           $query->andWhere(['t2.order_code'=>$condition['search']]);
//                    } else {
//                        $temp = [] ;
//                        $serial_code = SerialCode::find()->select('order_code')->where(['serial_code'=>$condition['search']])->asArray()->all();
//                        if(!empty($serial_code)){
//                            foreach ( $serial_code as $val) {
//                                $temp[] = $val['order_code'];
//                            }
//                        }
//                        $query->andWhere(['in','t1.order_code',$temp]);
//                    }
//                }else{
//                       $query->andWhere(['like','t4.goods_name',$condition['search']]);
//                }
//
//
//            }
//
//            $pagination = new Pagination(['defaultPageSize' => 10, 'totalCount' => $query->count()]);
//            $data = $query->limit($pagination->limit)->offset($pagination->offset)->all();
//
//            return $this->render('after-sales',['items'=>$data,'pagination'=>$pagination]);
//
//        }elseif($search_time==2){
//            $query = (new Query())->select('FROM_UNIXTIME(t1.order_time) as order_time,t1.order_code')
//                                      ->from('{{dh_oms_order_info}} as t1')
//                                      ->leftJoin('{{dh_oms_order_goods}} as t2','t1.order_code=t2.order_code')
//                                      ->leftJoin('{{dh_base_supplier_goods}} as t4','t2.goods_id = t4.id')
//                                      ->where(['t1.user_id' => $uid])
//                                      ->andWhere(['<','t1.order_time','1451577600'])
//                                      ->andWhere(['in','t1.order_status',[30,90,155]])
//                                      ->orderBy('t1.order_time desc')->distinct();
//
//            if (!empty($condition['search'])) {
//                if (strlen($condition['search']) >= 15){
//                    if (substr($condition['search'],0,2) == '14' || substr($condition['search'],0,2) == '15' || substr($condition['search'],0,2) == '16' ||substr($condition['search'],0,2) == '17'
//                        || substr($condition['search'],1,4) == '2015' || substr($condition['search'],0,4) == '2015') {
//                        $query->andWhere(['t2.order_code'=>$condition['search']]);
//                    } else {
//                       $temp = [] ;
//                        $serial_code = (new Query())->select('order_code')->from('{{dh_base_serial_code}}')->where(['serial_code'=>$condition['search']])->all(Yii::$app->get('db2'));
//                        if(!empty($serial_code)){
//                            foreach ( $serial_code as $val) {
//                                $temp[] = $val['order_code'];
//                            }
//                        }
//                        $query->andWhere(['in','t2.order_code',$temp]);
//                    }
//                }else{
//                    $query->andWhere(['like','t4.name',$condition['search']]);
//                }
//            }
//
//            $pagination = new Pagination(['defaultPageSize' => 10, 'totalCount' => $query->count('*',Yii::$app->get('db2'))]);
//
//            $data = $query->limit($pagination->limit)->offset($pagination->offset)->all(Yii::$app->get('db2'));
//
//            return $this->render('old-after-sales',['items'=>$data,'pagination'=>$pagination]);
//        }



    }
    /**
     * @description:搜索公共方法
     * @return: sql
     * @author: zhanghy
     * @date: 2016年2月23日11:17:40
     * @review_user:
     */
    public function getSearchRes($type,$content,$where,$time_line,$other=null){
        $uid = $this->user_info['id'];
        if($where == 'sh'){     //申请售后搜索
            if($time_line == 1){ //今年订单
                $query = AfterSalesOrder::getUserAfterSalesOrderNow($uid,$type,$content);
            }elseif($time_line == 2){ //历史订单
                $query = AfterSalesOrder::getUserAfterSalesOrderLast($uid,$type,$content);
            }
        }
        return $query;
    }

    /**
     * @Title: actionMyComment
     * @Description: 已经评价
     * @return: return_type
     * @author: min.wan
     * @date: 2015-12-03 上午12:01:15
     * @review_user:zhanghy
     */
    public function actionMyComment()
    {
        $uid = $this->user_info['id'];

        $search = Yii::$app->request->getQueryParam("search","");
        $search_type = f_get('search_type',1);
        //条件查询
        if(empty($search))
        {
            //and t1.is_comment = 1   and t1.user_id = .".$uid."
            $sql = "select t1.content,t1.score,t2.* from goods_comment as t1 left join order_oms_goods as t2 on t1.order_code = t2.order_code
                    and t1.goods_id = t2.goods_id where t1.user_id = ".$uid." ORDER  by t1.id DESC ";

            $items = Yii::$app->db->createCommand($sql)->queryAll();

            //分页对象
            $pagination = new Pagination([
                'defaultPageSize' => 10,
                'totalCount' => count($items)
            ]);
            //'t1.user_id' => $uid,
            $res = (new Query())->select(" t1.content,t1.score,t2.*")->from("{{goods_comment}} as t1")->leftJoin("{{order_oms_goods}} as t2 ",'t1.order_code = t2.order_code and t1.goods_id = t2.goods_id')
                ->where(['t1.user_id' => $uid])
                ->limit($pagination->limit)
                ->offset($pagination->offset)->orderBy('t1.id desc')->all();
        }
        else
        {
              $flag = 0;
              $flag_goods_name = 0;
              $flag_order_code = 0;
              $condition = "";
              if($search_type == 1){
                  $condition .=" and t2.order_code='".$search."' ";
                  $flag_order_code = 1;
              }
              elseif($search_type == 2)
              {
                  $flag = 1;
                  $order_code = (new Query())->select("order_code")->from("{{goods_serial_code}}")->where(['serial_code' => $search])->scalar();
                  if($order_code)//存在该串号
                  {
                      $condition .=" and t2.order_code = "."'".$order_code."'";
                  }
                  else
                  {
                      $serial_code = AfterSalesSerialCode::find()->where(['serial_code'=>$search,'flag'=>1])->asArray()->one();
                      if($serial_code)
                      {
                        $order_code = AfterSalesOrder::find()->select('order_code')->where(['sales_order'=>$serial_code['sales_order']])->scalar();
                        if($order_code){
                            $condition .=" and t2.order_code = "."'".$order_code."'";
                        }
                        else
                        {
                            $condition .=" and t2.order_code = '"." '";
                        }
                      }
                      else
                      {
                          $condition .=" and t2.order_code = '"." '";
                      }
                  }
              }
              elseif($search_type == 3)
              {
                  $flag_goods_name = 1;
                  $condition .=" and t2.goods_name like '%".$search."%' ";
              }
//            $flag = 0;
//            $flag_goods_name = 0;
//            $flag_order_code = 0;
//            $condition = "";
//            //商品名称 ;  串号; 订单编号
//            if(preg_match("/^\d{15}$/",$search))
//            {
//                $condition .=" and t2.order_code='".$search."' ";
//                $flag_order_code = 1;
//            }
//            elseif(preg_match("/^[a-z\d]*$/i",$search) && strlen($search)<= 16)
//            {
//                //串码 => order_code 根据串码获取order_code
//                $flag = 1;
//                $order_code = (new Query())->select("order_code")->from("{{goods_serial_code}}")
//                    ->where(['serial_code' => $search]);
//                if($order_code)//存在该串号
//                {
//                    $condition .=" and t2.order_code = "."'".$search."'";
//                }
//                else{
//                    $condition .=" and t2.order_code = '"." '";
//                }
//
//            }
//            else
//            {
//                $flag_goods_name = 1;
//                $condition .=" and t2.goods_name like '%".$search."%' ";
//            }

            if($flag)
            {
                //and t2.is_comment = 1   and t1.user_id = ".$uid."
                $sql = "select t1.content,t1.score,t2.* from goods_comment as t1 left join order_oms_goods as t2 on t1.order_code = t2.order_code and t1.goods_id = t2.goods_id
                where t1.user_id = ".$uid." ".$condition." ORDER  by t1.id DESC ";

                $count =  OmsInfo::findBySql($sql)->count();
                //分页对象
                $pagination = new Pagination([
                    'defaultPageSize' => 10,
                    'totalCount' => $count
                ]);

                $data = (new Query())->select("t1.content,t1.score,t2.*")->from("{{goods_comment}} as t1")->leftJoin("{{order_oms_goods}} as t2 ",'t1.order_code = t2.order_code and t1.goods_id = t2.goods_id')
                    ->where(['t1.user_id' => $uid])
                    ->limit($pagination->limit)
                    ->offset($pagination->offset)->orderBy('t1.id desc')->andWhere(['t2.order_code'=>$order_code])->all();
                $res = $data;
//                if($order_code)
//                {
//                    $res = $data->andWhere(['t2.order_code'=>$search])->all();
//                }
//                else
//                {
//                    $res = $data->all();
//                }

            }
            else
            {

                $sql = "select t1.supplier_id as supplier_id1, t1.content,t1.score,t2.* from goods_comment as t1 left join order_oms_goods as t2 on t1.order_code = t2.order_code and t1.goods_id = t2.goods_id
               where t1.user_id = ".$uid."  ".$condition." ORDER  by t1.id DESC ";
                $count =  OmsInfo::findBySql($sql)->count();

                //分页对象
                $pagination = new Pagination([
                    'defaultPageSize' => 10,
                    'totalCount' => $count
                ]);

                //'t1.user_id' => $uid,
                $data = (new Query())->select("t1.supplier_id as supplier_id1, t1.content,t1.score,t2.*")->from("{{goods_comment}} as t1")->leftJoin("{{order_oms_goods}} as t2 ",'t1.order_code = t2.order_code and t1.goods_id = t2.goods_id')
                    ->where(['t1.user_id' => $uid])
                    ->limit($pagination->limit)
                    ->offset($pagination->offset)->orderBy('t1.id desc');

                if($flag_order_code)
                {
                    $res = $data->andWhere(['t2.order_code'=>$search])->all();
                }
                elseif($flag_goods_name)
                {
                    $res = $data->andWhere(['like', 't2.goods_name', $search])->all();
                }
                else
                {
                    $res = $data->all();
                }

            }


        }

        return $this->render(
            'my-comment',['items' => $res,'pagination'=>$pagination]
        );

    }

    /**
     *
     * @Title: actionChangeOrderStatus
     * @Description:用户取消订单
     * @return: json
     * @author: yulong.wang
     * @date: 2015-1-7上午1:01:35
     */
    public function actionChangeOrderStatus($order_id){
        $orderInfo = OmsInfo::findOne(['id' => $order_id]);
        $order_code = f_get('order_code','');//需要增加运费的订单号
//         f_d($order_code);
        $model_yunfei_code = OmsInfo::find()->where(['order_code'=>$order_code])->one();
        if($model_yunfei_code && $model_yunfei_code->order_status == 5) {
	        $model_yunfei_code->express_price = 10;
	        $model_yunfei_code->collecting_price = $model_yunfei_code->collecting_price + 10;
	        $model_yunfei_code->order_price = $model_yunfei_code->order_price + 10;
	        $model_yunfei_code->save();
        }
        
        
        if ($orderInfo) {
            $out = 0;
            $pay_type = OrderPayWay::getPayType($orderInfo->pay_way);
            if ($pay_type == 1) {
               if ($orderInfo->order_status == 5) {
                   $to_status = 20;
                   $out = 1;
                }
           } elseif ($pay_type == 2) {
                if ($orderInfo->order_status == 75) {
                    $to_status = 80;
                    $out = 1;
                } elseif ($orderInfo->order_status == 85) {
                    $to_status = 95;
                    $out = 1;
                }
           } elseif ($pay_type == 3) {
                if ($orderInfo->order_status == 165) {
                    $to_status = 170;
                    $out = 1;
                } elseif ($orderInfo->order_status == 175) {
                    $to_status = 185;
                    $out = 1;
                } elseif(omsTpl::isTpl($order_id) && $orderInfo->order_status == 200) {
                    //主要针对第三方物流订单的，如果已发货，那么只能点击确认收货
                    $to_status = 220;
                    $out = 1;
                }
           } elseif ($pay_type == 4) {
               if ($orderInfo->order_status == 600) {
                   $to_status = 605;
                   $out = 1;
               } elseif ($orderInfo->order_status == 610) {
                   $to_status = 620;
                   $out = 1;
               }
           }

          if ($out == 1) {
              $returnVal = OmsInfo::changeOrderStatus($order_id,$to_status,$description='用户前台取消',$operator='用户前台取消');
              if ($returnVal == 1) {
                  /***物流接口*/
                  $order_codes[] = $orderInfo->order_code;
                  $status_sign = 2;
                  OrderStatusInterfaceLog::changeLogisticsOrderStatus($order_codes,$status_sign);
                  $return['status'] = 1;
                  $return['msg'] = "订单取消成功!";
              }
          } else {
               $return['status'] = 4;
               $return['msg'] = "订单状态已变更,正在处理中,请稍等!";
          }
        } else {
           $return['status'] = 5;
           $return['msg'] = "订单异常,取消订单失败";
       }
        return json_encode($return);
    }
    
    /**
     * @description:第三方订单， 确认收货
     * @return: return_type
     * @author: wufeng
     * @date: 2016年7月12日 下午5:35:58
     * @review_user:
     */
    public function actionConfirmOrder($order_id){
        $orderInfo = OmsInfo::findOne(['id' => $order_id]);
        $status = f_get('status', 0);
        
        if ($orderInfo) {
            $out = 0;
            $pay_type = OrderPayWay::getPayType($orderInfo->pay_way);
            if ($pay_type == 3) {
                if($orderInfo->order_status == 190) {
                    //审核通过  => 用户取消，生成退款单
                    $to_status = 185;
                    $out = 1;
                    $message = [
                        0 => '用户前台取消',
                        1 => '用户前台取消',
                    ];
                } elseif($orderInfo->order_status == 200) {
                    //先判断有没有售后单，如果有还未处理的售后单，提示不可以确认收货
                    $refund = GoodsRefund::find()->where(['order_id' => $order_id])->asArray()->all();
                    
                    $is_tpl = 0;
                    foreach($refund as $key => $val) {
                        if($val['status'] && in_array($val['status'], [2,4,5,6])) {
                            $is_tpl = 1;
                            break;
                        }
                    }
                    
                    if($is_tpl) {
                        $return['status'] = 6;
                        $return['msg'] = "您还有售后申请没有处理完<br/>暂时不能确认收货！";
                        return json_encode($return);
                    }
                    
                    if($status == 2) {
                        //主要针对第三方物流订单的，如果已发货，那么只能点击确认收货
                        $to_status = 220;
                        $out = 1;
                        $message = [
                            0 => '用户确认签收',
                            1 => '用户前台确认签收',
                        ];
                    } elseif($status == 3) {
                        $to_status = 185;
                        $out = 1;
                        $message = [
                            0 => '用户退款',
                            1 => '用户退款',
                        ];
                    }
                    
                }
            } 
    
            if ($out == 1) {
                $returnVal = OmsInfo::changeOrderStatus($order_id,$to_status,$description=$message[0],$operator=$message[1]);
                $return['status'] = 1;
                $return['msg'] = "操作成功!";
            } else {
                $return['status'] = 4;
                $return['msg'] = "订单状态已变更,正在处理中,请稍等!";
            }
        } else {
            $return['status'] = 5;
            $return['msg'] = "订单异常,确认签收订单失败";
        }
        return json_encode($return);
    }
    
    
    
    /**
     *
     * @Title: actionOnlinePayment
     * @Description:  在线支付跳转页面
     * @return: json
     * @author: wangmingcha
     * @date: 2015-12-8下午2:05:55
     */
    public function actionOnlinePayment(){
        $order_id = explode('-',f_get('order_id'));
        $params = [
            'userInfo'=>$this->user_info,
            'pay_type'=>OnlinePayment::$payPortMap[1]
        ];
        $handler = new OnlineOrderCommonHandler($order_id);
        $onlinePaymentData = OrderOnlinePayment::getOnlinePaymentParams($handler,$params);
        return $this->renderPartial('online-payment-form',$onlinePaymentData);
    }

    public function actionUnlockPayment(){
        if(Yii::$app->request->getIsAjax()){
            $ajaxRet = [];
            try{
                $hash_key = f_post('hash_key');
                f_c($hash_key,false);
                $ajaxRet['status'] = 'success';
            }catch (Exception $e){

            }
            echo Json::encode($ajaxRet);
            Yii::$app->end();
        }
    }

    /**
     *
     * @Title: actionOnlineConfirm
     * @Description:  在线支付确认页面
     * @return: json
     * @author: wangmingcha
     * @date: 2015-12-8下午2:05:55
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
     * @description:售后在线支付跳转页面
     * @return: return_type
     * @author: leo
     * @date: 2016年6月20日上午9:17:17
     * @review_user:
    */
    public function actionAfterSalesGoPay(){
    	$sales_order = f_get('sales_order');
    	if(!empty($sales_order)){
    		$sales_data = AfterSalesOrder::findOne(['sales_order'=>$sales_order]);
    		$params = [
    		    'userInfo'=>$this->user_info,
    		    'pay_type'=>OnlinePayment::$payPortMap[1]
    		 ];
    		 $handler = new AfterSalesOnlineOrderCommonHandler([$sales_data->id]);
    		 $onlinePaymentData = OrderOnlinePayment::getOnlinePaymentParams($handler,$params);
        	return $this->renderPartial('after-sales-online-payment-form',$onlinePaymentData);
    	}else{
    	   throw new ForbiddenHttpException('请求参数异常！');
    	}
    }
    
    
    /**
     *
     * @Title: actionOnlineConfirm
     * @Description:  在线支付确认页面
     * @return: json
     * @author: wangmingcha
     * @date: 2015-12-8下午2:05:55
     */
    public function actionAfterSalesOnlineConfirm(){
    	$csrfToken = Yii::$app->request->post('_csrf');
    	if(Yii::$app->request->validateCsrfToken($csrfToken)){
    		return $this->renderPartial('after-sales-online-confirm',[
    				'requestParam'=>$_POST,
    		]);
    	}else{
    		throw new HttpException(404);
    	}
    }

    /**
     * @description: 判断是否可以申请售后
     * @return: json
     * @author: wanmin
     * @date: 2015年12月11日上午11:12:07
     *
     */
    public function actionIsCan()
    {

        $pro =   f_get("pro");
        $city =  f_get("city");
        if(!empty($pro) && !empty($city))
        {
            $res = CityFunctionManage::CheckSales($pro, $city);
            if($res)
            {
                return json_encode(array("code" => 200));
            }
            else
            {
                return json_encode(array("code" => 400));
            }
        }
        else
        {
            return json_encode(array("code" => 400));
        }

    }
    
   
    /**
     * @description:取消售后
     * @return: \yii\web\Response
     * @author: leo
     * @date: 2015年12月18日下午2:03:33
     * @review_user:
    */
    public function actionCancelOrder(){
        if(isset($_GET['sales_order'])&&!empty($_GET['sales_order'])){
           $model = AfterSalesOrder::findOne(['sales_order'=>$_GET['sales_order']]);
           if(!empty($model)){
               if($model->status==5 && $this->user_info['id']==$model->user_id){
                   $model->status = 80;
                   if($model->save()){
                       $sales_log = new AfterSalesLog();
                       $sales_log->sales_order = $model->sales_order;
                       $sales_log->description = '客户自己取消';
                       $sales_log->log_type = AfterSalesStatus::getStatusName($model->status);
                       $sales_log->operator = '客户';
                       $sales_log->operate_time = date('Y-m-d H:i:s',time());
                       $sales_log->save();
                   }
                    
                   return $this->redirect('/ucenter/record');
               }else{
                   throw new HttpException(404);
               }
               
           }else {
               throw new HttpException(404);
           }
    	   
        }else{
            throw new HttpException(404);
        }
    }

    /**
     * @description:2015年订单记录
     * @return: \yii\web\Response
     * @author: leo
     * @date: 2015年12月24日 11:17:50
     * @review_user:
     */
    public function actionOldOrder(){
        $user_info = $this->user_info;

        $order_id = f_get('id',0);
        if($order_id > 0){
            //订单详情页
            $query = (new Query())->select('t2.*,t4.name as pay_name,t3.name as goods_name,t1.goods_id,t1.goods_price,t1.goods_num,t1.goods_color,t5.status_name,t6.img_url,t1.order_id,t7.shipping_name,t1.minus_price')
                ->from('{{dh_oms_order_goods}} as t1')
                ->leftJoin('{{dh_oms_order_info}} as t2','t1.order_id=t2.id')
                ->leftJoin('{{dh_base_supplier_goods}} as t3','t1.goods_id=t3.id')
                ->leftJoin('{{dh_sys_pay_way}} as t4','t2.pay_way=t4.id')
                ->leftJoin('{{dh_oms_order_status}} as t5','t2.order_status=t5.status')
                ->leftJoin('{{dh_base_img}} as t6','t3.img_id=t6.id')
                ->leftJoin('{{dh_base_shipping}} as t7','t2.express_way=t7.id')
                ->where(['t2.user_id'=>$user_info['id']])->andWhere(['t1.order_id'=>$order_id]);
            $data = $query->createCommand(Yii::$app->get('db2'))->queryAll();

            return $this->render('old-order-detail',['data'=>$data]);
        }

        if (isset($_GET['search'])) {
            $condition = ['search' => f_get('search')];
        }
        if(isset($_GET['search_type'])){
            $condition['search_type'] = f_get('search_type',1);
        }

        $query = (new Query())->select('t2.order_code,t2.order_time,t4.name as pay_name,t3.name as goods_name,t1.goods_id,t1.goods_price,t1.goods_num,t1.goods_color,
                                        t5.status_name,t6.img_url,t2.order_price,t2.express_price,t1.order_id,t2.order_status,t1.id as goods_id')
                             ->from('{{dh_oms_order_goods}} as t1')
                             ->leftJoin('{{dh_oms_order_info}} as t2','t1.order_id=t2.id')
                             ->leftJoin('{{dh_base_supplier_goods}} as t3','t1.goods_id=t3.id')
                             ->leftJoin('{{dh_sys_pay_way}} as t4','t2.pay_way=t4.id')
                             ->leftJoin('{{dh_oms_order_status}} as t5','t2.order_status=t5.status')
                             ->leftJoin('{{dh_base_img}} as t6','t3.img_id=t6.id')
                             ->where(['t2.user_id'=>$user_info['id']]);


        if (!empty($condition['search'])) {
            $search_type = $condition['search_type'];
            if($search_type == 1){
                $query->andWhere(['t2.order_code'=>$condition['search']]);
            }elseif($search_type == 2){
                $temp = [];
                $serial_code = (new Query())->select('order_code')->from('{{dh_base_serial_code}}')->where(['serial_code' => $condition['search']])->all(Yii::$app->get('db2'));
                if (!empty($serial_code)) {
                    foreach ($serial_code as $val) {
                        $temp[] = $val['order_code'];
                    }
                } else {
                    $serial_code = AfterSalesSerialCode::find()->where(['serial_code' => $condition['search'], 'flag' => 1])->asArray()->all();
                    if ($serial_code) {
                        foreach ($serial_code as $value) {
                            $temp[] = (new Query())->select('order_code')->from('{{dh_after_sales_orders}}')->where(['sales_order' => $value['sales_order']])->scalar(Yii::$app->get('db2'));
                        }
                    }
                }
                $query->andWhere(['in', 't2.order_code', $temp]);
            }elseif($search_type == 3){
                $query->andWhere(['like','t3.name',$condition['search']]);
            }
//            if (strlen($condition['search']) >= 15){
//                if (substr($condition['search'],0,2) == '14' || substr($condition['search'],0,2) == '15' || substr($condition['search'],0,2) == '16' ||substr($condition['search'],0,2) == '17'
//                    || substr($condition['search'],1,4) == '2015' || substr($condition['search'],0,4) == '2015') {
//                    $query->andWhere(['t2.order_code'=>$condition['search']]);
//                } else {
//                    $temp = [] ;
//                    $serial_code = (new Query())->select('order_code')->from('{{dh_base_serial_code}}')->where(['serial_code'=>$condition['search']])->all(Yii::$app->get('db2'));
//                    if(!empty($serial_code)){
//                        foreach ( $serial_code as $val) {
//                            $temp[] = $val['order_code'];
//                        }
//                    }
//                    $query->andWhere(['in','t2.order_code',$temp]);
//                }
//            }else{
//                $query->andWhere(['like','t3.name',$condition['search']]);
//            }
        }


        $pagination = new Pagination([
            'defaultPageSize' => 10,
            'totalCount' => $query->count('*',Yii::$app->get('db2')),
        ]);

        $rows = $query->orderBy('t2.order_time desc')->offset($pagination->offset)->limit($pagination->limit)->all(Yii::$app->get('db2'));

        $data = [];
        $temp = [];
        foreach($rows as $key=>$val){

            $data['orders'][$val['order_code']][] = $val;
            $data['pay_way'][$val['order_code']] =$val['pay_name'];
            $data['order_id'][$val['order_code']] =$val['order_id'];
        }

//        f_d($data);

        $unpayOrderCount = OmsInfo::find()->where(['order_status'=>OnlinePayment::$waitOrderStatus,'user_id'=>\Yii::$app->user->id])->count();

        return $this->render('old-order',['data'=>$data,'pager'=>$pagination,'unpayOrderCount'=>$unpayOrderCount]);
    }
    
    /**
     * @return string
     * @description: 用户立刻升级会员等级
     */
    public function actionSelfUpdate(){
    	if (!empty($_POST['wealth']) && !empty($_POST['userId'])){
    		$userInfo = [];
    		$data = "";
    		$wealth = $_POST['wealth'];
    		$id = $_POST['userId'];
    		$level = UserMember::getLevelByExpValue($wealth);
    		$res = UserMember::updateUserLevel($level,$id);
    		$levelName = UserMember::getUserLevelName($level);
    		$this->user_info['level'] = $level;
    		f_c('frontend-'.\Yii::$app->user->id.'-new_51dh3.0',false);
    		f_c('frontend-'.\Yii::$app->user->id.'-new_51dh3.0',$this->user_info);
    		$data['res'] = $res;
    		$data['levelName'] = $levelName;
    		return json_encode($data);
    	}
    }
    
    //修改用户渠道号
    public function actionAjaxUpdateUserQno(){
        $res = '';
        $user_id = $this->user_info['id'];
        $qno = f_post('no');
        if($user_id && $qno){
            $bool = UserMember::updateAll(['qd_no'=>$qno],['id'=>$user_id]);
            if($bool){
                $res = 1;//成功
            }else{
                $res = 2;
            }
        }
        return $res;
    }


    /**
     * @description:待评价发表评论
     * @return: \yii\web\Response
     * @author: zhanghy
     * @date: 2016年2月24日10:15:29
     * @review_user:
     */
    public function actionPublishComment(){
        if(Yii::$app->request->isPost){ //保存评论信息
            $model = new GoodsComment();
            $model->user_id = $this->user_info['id'];
            $model->order_code= f_post('order_code');
            $model->goods_id = f_post('goods_id');
            $model->supplier_id = f_post('supplier_id');
            $model->content = f_post('content');
            $model->add_time = f_date(time());
            $model->score = intval(f_post('score'));
            $model->phone = $this->user_info['phone'];
            if($model->save()) {
                OmsGoods::updateAll(["is_comment"=>1],["order_code"=>f_post('order_code'),'goods_id'=>f_post('goods_id')]);
                $res = ['code'=>'200','mes'=>'评论成功'];
            }else{
                f_d($model->getErrors());
                $res = ['code'=>'101','mes'=>'评论失败'];
            }
            return json_encode($res);
        }
    }
    /**
     * @description:获取售后直通车主营商城链接
     * @return: \yii\web\Response
     * @author: zhanghy
     * @date: 2016年2月24日10:15:29
     * @review_user:
     */
    public function getAfterSalesUrl(){
        $uid = $this->user_info['id'];
        $url = '';
        $user_info = f_c('frontend-'.$uid.'-new_51dh3.0');
        if(!$user_info) {
            $user_info = UserMember::find()->where(['id'=>$uid])->asArray()->one();
        }
        $main_mall_id = StoreMall::find()->where(['status'=>1,'store_id'=>$user_info['store_type']])->asArray()->one();
        if($main_mall_id) {
            $mall_info = MallBase::find()->where(['id'=>$main_mall_id['mall_id']])->asArray()->one();
        }
        if($mall_info){
            if($mall_info['url'] == '/tx'){
                $url = 'tx';
            }elseif($mall_info['url'] == '/digital'){
                $url = 'it';
            }
            elseif($mall_info['url'] == '/baby'){
                $url = 'baby';
            }elseif($mall_info['url'] == '/appliance'){
                $url = 'jd';
            }
        }
        return $url;
    }

    /**
     * @description:获取带链接的优惠券使用范围
     * @author: jr
     * @date:16-3-4
     */
    public static function actionUrlCoupon($coupon_id,$coupon_type_id){
        $result = '';
        $query = null;

        switch($coupon_type_id){
            case 1:
            {
                $result='全场通用';
                break;
            }

            case 2://限制商品品类
            {
                $query = (new Query())->select('t2.name,t2.id,t2.mall_id')
                    ->from('{{markting_coupon_type}} as t1')
                    ->leftJoin('{{goods_type}} as t2', 't2.id = t1.type_id')
                    ->andWhere(['t1.coupon_id' => $coupon_id])
                    ->all();
                if(count($query)>0){
                    foreach($query as $vo){
                        $result .= "<a href='/list/index?&mall_id=".$vo['mall_id']."&type=".$vo['id']."'>".$vo['name'].'，</a>';
                    }
                }else{
                    $result .='限商品品类';
                }
                break;
            }
            case 3://限制商品品牌
            {
                $query = (new Query())->select('t2.name')
                    ->from('{{markting_coupon_brand}} as t1')
                    ->leftJoin('{{goods_brand}} as t2','t2.id = t1.brand_id')
                    ->andWhere(['t1.coupon_id'=>$coupon_id]);
                if(!empty($query)){
                    $list = $query->all();
                    if(count($list)>0){
                        $result .= join(',',array_column($list,'name'));
                    }else{
                        $result='限制商品品牌';
                    }
                }
                break;
            }
            case 4://限制供应商
            {
                $query = (new Query())->select('t2.name')
                    ->from('{{markting_coupon_supplier}} as t1')
                    ->leftJoin('{{user_supplier}} as t2','t2.id = t1.supplier_id')
                    ->andWhere(['t1.coupon_id'=>$coupon_id]);
                if(!empty($query)){
                    $list = $query->all();
                    if(count($list)>0){
                        $result .= join(',',array_column($list,'name'));
                    }else{
                        $result='限制供应商';
                    }
                }
                break;
            }
            case 5://限制商品ID
            {
                $type_id = CouponType::find()->select('type_id')->where(['id'=>5])->scalar();
                $mall_id = Type::find()->select('mall_id')->where(['id'=>$type_id])->scalar();
                $query = (new Query())->select('t2.goods_name as name,t2.id')
                    ->from('{{markting_coupon_goodsid}} as t1')
                    ->leftJoin('{{goods_supplier_goods}} as t2','t2.id = t1.goods_id')
                    ->andWhere(['t1.coupon_id'=>$coupon_id])
                    ->all();
                if(count($query)>0){
                    foreach($query as $vo){
                        $result .= "<a href='/site/detail?&mall_id=".$mall_id."&id=".$vo['id']."' >".$vo['name'].'，</a>';
                    }
                }else{
                    $result .='限商品';
                }
                break;
            }
            case 6://限制商城
            	{
            		//优惠券类型   优惠券编号两个参数
            		//商城  名称 链接
            		$query = (new Query())->select('t2.name,t2.url')
            		->from('{{markting_coupon_mall}} as t1')
            		->leftJoin('{{other_mall_base}} as t2','t2.id = t1.mall_id')
            		->andWhere(['t1.coupon_id'=>$coupon_id])
            		->all();
            		if(count($query)>0){
            			foreach($query as $vo){
            				$result .= "<a href='".$vo['url']."' >".$vo['name'].'，</a>';
            			}
            		}else{
            			$result .='限商城';
            		}
            		break;
            	}

        }

        echo json_encode($result);
    }

    /**
     * @description:获取用户异性换机数据
     * @return: json
     * @author: zhanghy
     * @date: 2016年3月17日09:51:36
     */
    public function actionGetReplaceGoods(){
        $out = [];
        $data = '';
        if (Yii::$app->request->isPost) {
            $salesOrder = f_post('salesOrder','');
            if (!empty($salesOrder)) {
                $goodsInfo = (new Query())->select('t1.goods_id,t1.supplier_id,t2.depot_id')
                    ->from('after_sales_goods as t1')
                    ->leftJoin('goods_supplier_goods as t2','t1.goods_id = t2.id')
                    ->where(['t1.sales_order'=>$salesOrder])->one();
                if (!empty($goodsInfo['goods_id']) && !empty($goodsInfo['supplier_id']) && !empty($goodsInfo['depot_id'])) {
                    $replaceGoods = AfterSalesOrder::getReplaceGoodsFrom51($goodsInfo['goods_id'],$goodsInfo['supplier_id'],$goodsInfo['depot_id']);
                    if (!empty($replaceGoods)){
                        foreach ($replaceGoods as $key => $val) {
                            $data .= '<tr>';
                            $data .= "<td class=\"first_row\"><a href=\"javascript:void(0)\"><img src=".f_imgUrl($val['img_url'])."></a></td>";
                            $data .= "<td class=\"second_row\">".$val['goods_name']."</td>";
                            $data .= "<td class=\"third_row\">".$val['color_name']."</td>";
                            $data .= "<td class=\"fourth_row\">".$val['price']."</td>";
                            $data .= "<td class=\"fifth_row\"><button class=\"change_btn\" flag=".$val['id'].">立即置换</button></td>";
                            $data .= '</tr>';
                        }
                        $data .= "<input type='hidden' class='sales_order' value='".$salesOrder."'>";
                        $out['code'] = 200;
                        $out['mes'] = $data;
                    } else {
                        $out['code'] = 101;
                        $out['mes'] = '该商品未配置异性换机机型，请联系客服人员~';
                    }
                } else {
                    $out['code'] = 101;
                    $out['mes'] = '售后商品信息获取失败，请重试~';
                }
            } else {
                $out['code'] = 101;
                $out['mes'] = '售后单获取失败，请重试~';
            }
        } else {
            $out['code'] = 101;
            $out['mes'] = '数据传输错误，请重试~';
        }
        return json_encode($out);
    }

    /**
     * @description:申请置换 检测置换商品库存 更改售后单状态
     * @return: json
     * @author: zhanghy
     * @date: 2016年3月17日14:31:06
     */
    public function actionCheckGoodsNumAvai(){
        $out = [];
        if (Yii::$app->request->isPost) {
            $goodsId = f_post('goods_id',0);
            $salesOrder = f_post('salesOrder','');
            if (!empty($goodsId) && !empty($salesOrder)) {
                $salesOrderInfo = AfterSalesOrder::findOne(['sales_order'=>$salesOrder]);
                if ($salesOrderInfo && $salesOrderInfo->status == 110) {
                    $goodsInfo = SupplierGoods::findOne(['id' => $goodsId]);
                    if (!empty($goodsInfo)) {
                        $salesGoods = AfterSalesGoods::findOne(['sales_order' => $salesOrder]);
                        if (!empty($salesGoods)) {
                            $connection = Yii::$app->db;//事务开始
                            $transaction = $connection->beginTransaction();
                            try {
                                SupplierGoods::reduceSupplierGoodsStockForAfterSales($salesOrder,$goodsId, $salesGoods->num, false);    //减库存操作
                                $replaceInfo = AlienReplacementLog::createAlienReplacementLog($goodsId, $goodsInfo->brand_id, $goodsInfo->color_id, $salesGoods->num, $salesOrder); //插入异性换机记录
                                if ($replaceInfo == 1) {    //异性换机成功
                                    $salesInfo = AfterSalesOrder::findOne(['sales_order' => $salesOrder]);
                                    if ($salesInfo) {
                                        $changeOrderStauts = AfterSalesOrder::ChangeOrderStatusFromUser($salesInfo->id, 10, '客户确认异型换机机器');
                                        if ($changeOrderStauts != 1) {
                                            throw new \Exception($changeOrderStauts);
                                        } else {
                                            $out['code'] = 200;
                                            $out['mes'] = '';
                                            $transaction->commit();
                                        }
                                    } else {
                                        throw new \Exception('售后单信息获取失败，请重试~');
                                    }
                                } else {
                                    throw new \Exception('异型换机记录生成失败，请重试~');
                                }
                            } catch (\Exception $e) {
                                $transaction->rollBack();
                                $out['code'] = 101;
                                $out['mes'] = $e->getMessage();
                            }
                        } else {
                            $out['code'] = 101;
                            $out['mes'] = '售后单商品获取失败，请重试~';
                        }
                    } else {
                        $out['code'] = 101;
                        $out['mes'] = '商品信息获取失败，请重试~';
                    }
                } else {
                    $out['code'] = 101;
                    $out['mes'] = '当前售后单状态已经发生变更，请重试~';
                }
            } else {
                $out['code'] = 101;
                $out['mes'] = '数据传输错误，请重试~';
            }
        } else {
            $out['code'] = 101;
            $out['mes'] = '数据传输错误，请重试~';
        }
        return json_encode($out);
    }

    /**
     * @description:个人中心--开票信息
     * @return:
     * @author: jr
     * @date:16-3-24
     */
    public function actionInvoice(){
        $invoice = UserInvoice::find()->where(['user_id'=>$this->user_info['id']])->orderBy('status desc')->asArray()->all();
        $province = OtherRegion::getRegion(1);

        return $this->render('invoice-index',[
            'invoice' => $invoice,
            'province' => $province,
            'count' => count($invoice)
        ]);
    }

    public function actionGetInvoice(){
        $id = f_post('id','');
        if($id){
            $data = UserInvoice::findOne($id);
            if(!empty($data)){
                $province = OtherRegion::getRegion(1);
                $h1 = '<option value="" >请选择省份</option>';
                foreach ($province as $key=>$value){
                    if($key==$data->province){
                        $h1 .='<option value="'.$key.'" selected="selected">'.$value.'</option>';
                    }else{
                        $h1 .='<option value="'.$key.'">'.$value.'</option>';
                    }
                }
                $city = OtherRegion::getSonRegion($data->province);
                $h2 = '<option value="" >请选择城市</option>';
                foreach ($city as $val){
                    if($val['region_id']==$data->city){
                        $h2 .='<option value="'.$val['region_id'].'" selected="selected">'.$val['region_name'].'</option>';
                    }else{
                        $h2 .='<option value="'.$val['region_id'].'">'.$val['region_name'].'</option>';
                    }
                }

                $district = OtherRegion::getSonRegion($data->city);
                $h3 = '<option value="">请选择区县</option>';
                foreach ($district as $val){
                    if($val['region_id']==$data->district){
                        $h3 .='<option value="'.$val['region_id'].'" selected="selected">'.$val['region_name'].'</option>';
                    }else{
                        $h3 .='<option value="'.$val['region_id'].'">'.$val['region_name'].'</option>';
                    }
                }

                $res = [
                    'id' => $data->id,
                    'company_name' => $data->company_name,
                    'taxpayer_identity' => $data->taxpayer_identity,
                    'register_telephone' => $data->register_telephone,
                    'register_address' => $data->register_address,
                    'bank' => $data->bank,
                    'account_number' => $data->account_number,
                    'name'=>$data->name,
                    'phone'=>$data->phone,
                    'province' => $h1,
                    'city' => $h2,
                    'district' => $h3,
                    'address' => $data->address,
                ];
                return json_encode($res);exit;
            }

        }
    }
    
    /**
     * @description:获取验证码
     * @return: return_type
     * @author: leo
     * @date: 2015年8月19日下午12:36:38
     * @modified_date: 2015年8月19日下午12:36:38
     * @modified_user: leo
     * @review_user:
     */
    public function actionVeriyCode(){
        f_code();
    }
    
    /**
     * @description:众筹订单列表
     * @return: return_type
     * @author: wufeng
     * @date: 2016年5月16日 上午10:17:33
     * @review_user:
     */
    
    public function actionCrowdFund($id=0){
        $orderStatus = OnlinePayment::$waitCrowdOrderStatus;
        $orderId= intval($id,0);
        $condition = [];
        $page = f_get('page', 1);
        
        $search = ['search' => '', 'search_type' => 1];
        if (isset($_GET['search'])) {
            $search['search'] = f_get('search', '');
        }
        if(isset($_GET['search_type'])){
            $search['search_type'] = f_get('search_type',1);
        }
        //订单列表
        $data = CrowdFundOrder::getOrderListByUserInfo($this->user_info,$search, $page);
        $unpayOrderCount = CrowdFundOrder::find()->where(['order_status'=>$orderStatus,'user_id'=>$this->user_info['id']])->count();
        
        return $this->render('crowd-fund',[
            'unpayOrderCount' => $unpayOrderCount,
            'orderList' => $data['orderList'],
            'pager' => $data['pager'],
            'search' => $search,
        ]);
    }
    
    /**
     * @description:众筹未支付订单列表
     * @return: return_type
     * @author: wufeng
     * @date: 2016年5月16日 上午10:17:33
     * @review_user:
     */
    
    public function actionCrowdFundUnpay($id=0){
        $orderStatus = OnlinePayment::$waitCrowdOrderStatus;
        $orderId= intval($id,0);
        $condition = [];
        $page = f_get('page', 1);
        
        $search = ['search' => '', 'search_type' => 1, 'orderStatus'=>$orderStatus];
        if (isset($_GET['search'])) {
            $search['search'] = f_get('search', '');
        }
        if(isset($_GET['search_type'])){
            $search['search_type'] = f_get('search_type',1);
        }
        //订单列表
        $data = CrowdFundOrder::getOrderListByUserInfo($this->user_info,$search, $page);
        $unpayOrderCount = CrowdFundOrder::find()->where(['order_status'=>$orderStatus,'user_id'=>$this->user_info['id']])->count();
    
        return $this->render('crowd-fund',[
            'unpayOrderCount' => $unpayOrderCount,
            'orderList' => $data['orderList'],
            'pager' => $data['pager'],
            'search' => $search,
            'action' => true,
        ]);
    }
    
    /**
     * @description:众筹订单详情
     * @return: return_type
     * @author: wufeng
     * @date: 2016年5月16日 上午10:17:33
     * @review_user:
     */
    
    public function actionCrowdFundDetail($id){
        $crowdFundOrder= (new Query())->select('t1.*, t2.title, t2.cost, t2.price, t2.status as crowd_fund_status, t3.goods_name')
            ->from('crowd_fund_order as t1')
            ->leftJoin('crowd_fund AS t2', 't1.crowd_fund_id = t2.id')
            ->leftJoin('goods_supplier_goods AS t3', 't2.goods_id = t3.id')
            ->andWhere(['t1.id' => $id])->one();
        
        if($crowdFundOrder) {
            $crowdFundOrder['status_name'] = CrowdFundOrder::getStatusById($crowdFundOrder['order_status']);
            $crowdFundOrder['province'] = OtherRegion::getRegionNameById($crowdFundOrder['province']);
            $crowdFundOrder['city'] = OtherRegion::getRegionNameById($crowdFundOrder['city']);
            $crowdFundOrder['district'] = OtherRegion::getRegionNameById($crowdFundOrder['district']);
            $crowdFundOrder['pay_way_name'] = '在线支付';
            $crowdFundOrder['express_name'] = '蜂云物流';
        }
        
        $orderId = OmsInfo::find()->andWhere(['order_code' => $crowdFundOrder['order_code']])->scalar();
        if($orderId) {
            list($orderInfo,$serialCodeList,$orderGoodsInfoList) = OmsInfo::getOrderListByOrderIdAndUserInfo($orderId,$this->user_info);
        }else {
            $orderInfo = [];
            $serialCodeList = [];
            $orderGoodsInfoList = [];
        }
        
        $userInfo = $this->user_info;
    
        //f_d($orderGoodsInfoList);
        return $this->render('crowd-fund-detail',[
            'crowdFundOrder' => $crowdFundOrder,
            'orderInfo'=>$orderInfo,
            'serialCodeList'=>$serialCodeList,
            'orderGoodsInfoList'=>$orderGoodsInfoList,
        ]);

    }
    
    /**
     * 消息格式	{
        "partnerShopId": "chy",
        " yjfOpenToken ": "以下加密方式"    
		 }
		名称	说明	类型	长度	可选	备注
		partnerShopId	51钱包登录账号名	varchar		必需	
		yjfOpenToken	验证token	varchar		必需	
		MD5(
		"yyyyMMdd"+
		yjpayToken+
		partnerShopId
		)，其中yjpayToken = "uydekopqvmidaslpursn";
     * @description:
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年5月20日下午4:59:22
     * @modified_date: 2016年5月20日下午4:59:22
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionMyYi() {
        header("Content-type:text/html;charset=utf-8");
        
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
    	
    	//请求地址
     	$post_url = YJF_URL."/yjpay_api/open_bind.do";
    	
     	$partnerShopId = $user_info['login_account']; //51钱包登录账号名
        $yjpayToken = 'uydekopqvmidaslpursn'; 
     	
    	$yjfOpenToken = date('Ymd').$yjpayToken.$partnerShopId;
    	$yjfOpenToken = md5($yjfOpenToken); //验证token
    	
    	$post_url = $post_url.'?partnerShopId='.$partnerShopId.'&yjfOpenToken='.$yjfOpenToken;
    	
    	$curl = new Curl();
    	$output = $curl->get($post_url);
    	
    	$userId = 0; //该值为易极付在银行的用户的ID，不是网站的用户ID
    	$yjfBankCardToken = '';
    	
    	if($output) {
    	    $output = json_decode($output);
    	    $output = (array) $output;
    	    
    	    if(is_object($output['return_data'])) {
    	        $return_data = (array)$output['return_data'];
    	        $userId = $return_data['yjfMember']->userId;
    	        if($userId) $yjfBankCardToken = md5(date('Ymd').$yjpayToken.$userId);
    	    }
    	} else{
    	    $view = Yii::$app->view->render('@common/widgets/alert/Alert.php',[
    	        'type'=>'warn',
    	        'message'=>'易极付接口调用异常，请稍后再试！',
    	        'is_next'=>'yes',
    	        'next_url'=>'/ucenter/index',
    	    ]);
    	    return $view;exit;
    	}
    	//打印获得的数据json_encode
    	//http://120.55.137.181:9603/yjpay_api/open_bind.do?partnerShopId=15195890655&yjfOpenToken=64259233wbc543d12a1cf1c9e6206d914
    	
    	
    	return $this->render('my_yi',[
    		'output'=>$output,
    	    'partnerShopId' => $partnerShopId,
    	    'yjfOpenToken' => $yjfOpenToken,
    	    'userId' => $userId,
    	    'yjfBankCardToken' => $yjfBankCardToken,
    	]);
    }

    /**
     * @description:获取消费者地址详细信息
     * @return: return_type
     * @author: changhaijun
     * @date: 2016年5月31日下午2:50:33
     * @review_user:
     */
    public function actionSeasAddress(){
        $data = array();
        $data = SeasUserAddress::getSeasAddress($this->user_info['id'],6,isset($_GET['search'])?$_GET['search']:'');
        $province = OtherRegion::getRegion(1);
        return $this->render('seas-address',[
            'address'=>$data['address'],
            'address_num'=>$data['count'],
            'province'=>$province,
            'pages' => $data['pages'],
        ]);
    }

    /**
     * @description:增加消费者地址
     * @return: return_type
     * @author: changhaijun
     * @date: 2016年5月31日下午2:50:33
     * @review_user:
     */
    public function actionAddSeasAddress()
    {
        if(isset($_POST['receive_name'])&&isset($_POST['province'])&&isset($_POST['city'])
            &&isset($_POST['district'])&&isset($_POST['address'])&&isset($_POST['phone'])&&isset($_POST['idnum'])){
            $params = array();
            $params['user_name'] = $_POST['receive_name'];
            $params['phone'] = $_POST['phone'];
            $params['identidy'] = $_POST['idnum'];
            $params['province'] = $_POST['province'];
            $params['city'] = $_POST['city'];
            $params['district'] = $_POST['district'];
            $params['address'] = $_POST['address'];
            $params['user_id'] =  $this->user_info['id'];
            SeasUserAddress::addSeasAddress($params);
            $this->redirect('/ucenter/seas-address');
        }
    }

    /**
     * @description:删除消费者地址
     * @return: return_type
     * @author: changhaijun
     * @date: 2016年5月31日下午2:50:33
     * @review_user:
     */
    public function actionSeasAddressDelete(){
        if(isset($_POST['address_id'])&&!empty($_POST['address_id'])){
           $out = SeasUserAddress::delSeasAddress($_POST['address_id']);
            if($out){
                echo json_encode('1');exit;
            }
        }
    }

    /**
     * @description:获取消费者地址详细信息
     * @return: return_type
     * @author: changhaijun
     * @date: 2016年5月31日下午2:50:33
     * @review_user:
     */
    public function actionGetSeasAddress(){
        if(isset($_POST['address_id'])&&!empty($_POST['address_id'])){
            $res = array();
            $res = SeasUserAddress::getDetailSeasAddress($_POST['address_id']);
            echo json_encode($res);exit;

        }
    }

    /**
     * @description:修改消费者地址
     * @return: return_type
     * @author: changhaijun
     * @date: 2016年5月31日下午2:50:33
     * @review_user:
     */
    public function actionUpdateSeasAddress(){
        if(isset($_POST['update_id'])&&isset($_POST['receive_name'])&&isset($_POST['province'])&&isset($_POST['city'])
            &&isset($_POST['district'])&&isset($_POST['address'])&&isset($_POST['phone'])&&isset($_POST['idnum'])&&isset($_POST['idimg0'])&&isset($_POST['idimg1'])){
            $params = array();
            $params['address_id'] = $_POST['update_id'];
            $params['user_name'] = $_POST['receive_name'];
            $params['phone'] = $_POST['phone'];
            $params['identidy'] = $_POST['idnum'];
            $params['province'] = $_POST['province'];
            $params['city'] = $_POST['city'];
            $params['district'] = $_POST['district'];
            $params['address'] = $_POST['address'];
            $params['idimg0'] = $_POST['idimg0'];
            $params['idimg1'] = $_POST['idimg1'];
            $update = SeasUserAddress::updateSeasAddress($params);
            if($update ){
                echo 1;exit;
            }
        }
    }

    /**
     * @description:消费者地址设置为常用地址
     * @return: return_type
     * @author: changhaijun
     * @date: 2016年5月31日下午2:50:33
     * @review_user:
     */
    public function actionSeasAddressSetCommon(){
        if(isset($_POST['address_id'])){
            $model = SeasUserAddress::seasAddressStatus($_POST['address_id'],1);
            if($model){
                echo 1;exit;
            }
        }
    }

    /**
     * @description:消费者地址取消常用地址
     * @return: return_type
     * @author: changhaijun
     * @date: 2016年5月31日下午2:50:33
     * @review_user:
     */
    public function actionSeasAddressCancleCommon(){
        if(isset($_POST['address_id'])){
            $model = SeasUserAddress::seasAddressStatus($_POST['address_id'],0);
            if($model){
                echo 1;exit;
            }
        }
    }

    /**
     * @description:第三方物流售后
     * @return: return_type
     * @author: wufeng
     * @date: 2016年7月8日 上午11:00:00
     * @review_user:
     */
    
    public function actionRefund() {
        //售后单，表明：订单号 order_id,商品id：goods_id
        $order_id = f_get('order_id', 0);
        $goods_id = f_get('goods_id', 0);
        $update = f_post('update', 0); //判断是修改还是创建
        
        if(!$order_id) return f_msg('未找到相关的订单号！', '/ucenter/order');
        if(!$order_id) return f_msg('未找到相关的商品编号！', '/ucenter/order');
        
        $dataOfOrder = GoodsRefund::getDataOfOrder($order_id, $this->user_info, $goods_id); //获取订单数据
        
        $data['type'] = GoodsRefund::getTypes(); //第三方物流售后单服务类型
        $data['refund_reasons'] = GoodsRefund::getRefundReasons(); //退款退货原因
        
        $refund = GoodsRefund::find()->where(['order_id' => $order_id, 'goods_id' => $goods_id])->one();
        if(!$refund) $refund = new GoodsRefund();
        
        if(isset($refund) && $refund->status == 1) return f_msg('您的退款退货申请被拒绝了，不可以再次申请！', '/ucenter/order');
        if(isset($refund) && $refund->if_service == 1) return f_msg('客服正在介入，你不可以进行相关操作!', '/ucenter/order');
        
        if(Yii::$app->request->post() && Yii::$app->request->validateCsrfToken(f_post('_csrf', ''))){
            //提交或修改申请
            $connection = Yii::$app->db;//事务开始
            $transaction = $connection->beginTransaction();
            try {
                $refund->setAttributes(Yii::$app->request->post('GoodsRefund'));
                $refund->order_code = $dataOfOrder['orderInfo']['order_code'];
                $refund->user_id = \Yii::$app->user->id;
                if(!$refund->refund_code) $refund->refund_code = 'TP'.f_orderCode();
                if(!$refund->come_from) $refund->come_from = 'web网站';
                
                if(!$refund->status) $first = 1;
                else $first = 0;
                
                if($update == 0) {
                    $refund->create_time = date('Y-m-d H:i:s', time());
                    $refund->status = 2;
                }
                
                if($refund->save()) {
                    $sales_log = new GoodsRefundLog();
                    $sales_log->refund_code = $refund->refund_code;
                    $sales_log->description = GoodsRefund::getTypeById($refund->type).':'.$refund->refund_remark;
                    
                    if($first) {
                        $sales_log->log_type = '用户申请售后';
                    } else {
                        if($update == 0) {
                            $sales_log->log_type = '用户重新售后申请';
                        } else {
                            $sales_log->log_type = '用户修改售后申请';
                        }
                    }
                    
                    $sales_log->operator = \Yii::$app->user->identity->login_account;
                    $sales_log->operate_time = date('Y-m-d H:i:s', time());
                    $sales_log->type = 1;
                    
                    if($sales_log->save()){
                        $transaction->commit();
                        return $this->redirect(['/ucenter/refund-second', 'id' => $refund->id]);
                    } else {
                        $transaction->rollBack();
                    }
                } else $transaction->rollBack();
                
            } catch (\Exception $e) {
                $transaction->rollBack();
            }
            
            return $this->redirect(['/ucenter/refund', 'order_id' => $order_id, 'goods_id' => $goods_id]);
        }
        
        return $this->render('refund', [
            'order_id' => $order_id,
            'goods_id' => $goods_id,
            'refund' => $refund,
            'dataOfOrder' => $dataOfOrder,
            'data' => $data,
        ]);
    }
    
    /**
     * @description:提交申请以后的操作
     * @return: return_type
     * @author: wufeng
     * @date: 2016年7月8日 下午2:17:22
     * @review_user:
     */
    
    public function actionRefundSecond() {
        $id = f_get('id', 0);
        
        $order_id = f_get('order_id', 0);
        $goods_id = f_get('goods_id', 0);
        if($id) {
            $refund = GoodsRefund::find()->where(['id' => $id])->one();
        } elseif($order_id && $goods_id) {
            if(!$order_id) return f_msg('未找到相关的订单号！', '/ucenter/order');
            if(!$order_id) return f_msg('未找到相关的商品编号！', '/ucenter/order');
            
            $refund = GoodsRefund::find()->where(['order_id' => $order_id, 'goods_id' => $goods_id])->one();
            if(!$refund) return $this->redirect(['/ucenter/refund', 'order_id' => $order_id, 'goods_id' => $goods_id]);
        }
        
        if(!$refund) return f_msg('未找到相关的售后信息！', '/ucenter/order');
        if(!$refund->order_id) return f_msg('未找到相关的订单号！', '/ucenter/order');
        if(!$refund->goods_id) return f_msg('未找到相关的商品编号！', '/ucenter/order');
        
        $dataOfOrder = GoodsRefund::getDataOfOrder($refund->order_id, $this->user_info, $refund->goods_id); //获取订单数据
        
        if($refund->status == 2) {
            $often = GoodsRefundService::find()->where(['refund_id' => $refund->id, 'type' => 1])->andWhere(['>', 'begin_time', $refund->create_time])->sum('often');
            $endtime = date('Y/m/d H:i:s', strtotime($refund->create_time) + 60*60*24*3 + $often); //申请时，3天未处理，就自动退款
            
            return $this->render('refund-second', [
                'refund' => $refund,
                'dataOfOrder' => $dataOfOrder,
                'endtime' => $endtime,
            ]);
        } elseif($refund->status == 4) {
            $delivery = AfterSalesDelivery::find()->asArray()->all();
            if(Yii::$app->request->post() && Yii::$app->request->validateCsrfToken(f_post('_csrf', ''))){
                if($refund->if_service == 1) return f_msg('客服正在介入，你不可以进行相关操作!', '/ucenter/order');
                
                //提交或修改申请
                $connection = Yii::$app->db;//事务开始
                $transaction = $connection->beginTransaction();
                try {
                    $refund->setAttributes(Yii::$app->request->post('GoodsRefund'));
                    if($refund->express_id) $refund->express_name = AfterSalesDelivery::getName($refund->express_id);
                    $refund->status = 5; //商家等待收货
                    $refund->create_time = date('Y-m-d H:i:s', time());
                    $refund->express_time = date('Y-m-d H:i:s', time());
                
                    if($refund->save()) {
                        $sales_log = new GoodsRefundLog();
                        $sales_log->refund_code = $refund->refund_code;
                        $sales_log->description = '快递公司:'.$refund->express_name.' 快递单号:'.$refund->express_no.' 快递备注:'.$refund->express_remark ;
                        $sales_log->log_type = '用户已发货';
                        $sales_log->operator = \Yii::$app->user->identity->login_account;
                        $sales_log->operate_time = date('Y-m-d H:i:s', time());
                        $sales_log->type = 1;
                
                        if($sales_log->save()){
                            $transaction->commit();
                            return $this->redirect(['/ucenter/refund-second', 'id' => $refund->id]);
                        }
                    }
                    $transaction->rollBack();
                } catch (\Exception $e) {
                    $transaction->rollBack();
                }
            }
            
            return $this->render('refund-third', [
                'refund' => $refund,
                'dataOfOrder' => $dataOfOrder,
                'delivery' => $delivery,
            ]);
        } elseif($refund->status == 5) {
            $often = GoodsRefundService::find()->where(['refund_id' => $refund->id, 'type' => 2])->sum('often');
            $endtime = date('Y/m/d H:i:s', strtotime($refund->express_time) + 60*60*24*10 + $often); //申请时，3天未处理，就自动退款
            
            return $this->render('refund-fourth', [
                'refund' => $refund,
                'dataOfOrder' => $dataOfOrder,
                'endtime' => $endtime,
            ]);
        }else {
            return $this->render('refund-status', [
                'refund' => $refund,
                'dataOfOrder' => $dataOfOrder,
            ]);
        }
        
        
    }
    
    /**
     * @description:修改快递信息
     * @return: return_type
     * @author: wufeng
     * @date: 2016年7月8日 下午4:44:53
     * @review_user:
     */
    public function actionRefundThird() {
        $id = f_get('id', 0);
        
        $refund = GoodsRefund::find()->where(['id' => $id])->one();
        
        if(!$refund) return f_msg('未找到相关的售后信息！', '/ucenter/order');
        if(!$refund->order_id) return f_msg('未找到相关的订单号！', '/ucenter/order');
        if(!$refund->goods_id) return f_msg('未找到相关的商品编号！', '/ucenter/order');
        if($refund->status == 1) return f_msg('您的退款退货申请被拒绝了，不可以再次修改退货信息！', '/ucenter/order');
            
        
        $dataOfOrder = GoodsRefund::getDataOfOrder($refund->order_id, $this->user_info, $refund->goods_id); //获取订单数据
        $delivery = AfterSalesDelivery::find()->asArray()->all();
        
        if(Yii::$app->request->post() && Yii::$app->request->validateCsrfToken(f_post('_csrf', ''))){
            //提交或修改申请
            $connection = Yii::$app->db;//事务开始
            $transaction = $connection->beginTransaction();
            try {
                $refund->setAttributes(Yii::$app->request->post('GoodsRefund'));
                if($refund->express_id) $refund->express_name = AfterSalesDelivery::getName($refund->express_id);
                $refund->create_time = date('Y-m-d H:i:s', time());
                
                if($refund->save()) {
                    $sales_log = new GoodsRefundLog();
                    $sales_log->refund_code = $refund->refund_code;
                    $sales_log->description = '快递单号:'.$refund->express_no.' 快递备注:'.$refund->express_remark ;
                    $sales_log->log_type = '修改快递信息';
                    $sales_log->operator = \Yii::$app->user->identity->login_account;
                    $sales_log->operate_time = date('Y-m-d H:i:s', time());
                    $sales_log->type = 1;
            
                    if($sales_log->save()){
                        $transaction->commit();
                        return $this->redirect(['/ucenter/refund-fourth', 'id' => $refund->id]);
                    }
                }
                $transaction->rollBack();
            } catch (\Exception $e) {
                $transaction->rollBack();
            }
        }
        
        return $this->render('refund-third', [
            'refund' => $refund,
            'dataOfOrder' => $dataOfOrder,
            'delivery' => $delivery,
        ]);
    }
    
    /**
     * @description:第三方物流，撤销申请
     * @return: return_type
     * @author: wufeng
     * @date: 2016年7月8日 下午3:23:21
     * @review_user:
     */
    
    public function actionAjaxRevoke() {
        $return = ['success' => 0, 'message' => ''];
    
        $id = f_post('id', 0);
        if($id) {
            $refund = GoodsRefund::findOne($id);
            if(!$refund) $return['message'] = '未找到相关的售后单信息！';

            if(isset($refund) && $refund->if_service == 1) $return['message'] = '客服正在介入，你不可以进行相关操作!';
            
            if(empty($return['message'])) {
                $connection = Yii::$app->db;//事务开始
                $transaction = $connection->beginTransaction();
                try {
                    $refund->create_time = date('Y-m-d H:i:s', time());
                    $refund->status = 10;
                    if($refund->save()) {
                        $sales_log = new GoodsRefundLog();
                        $sales_log->refund_code = $refund->refund_code;
                        $sales_log->description = '用户撤销申请';
                        $sales_log->log_type = '用户撤销申请';
                        $sales_log->operator = \Yii::$app->user->identity->login_account;
                        $sales_log->operate_time = date('Y-m-d H:i:s', time());
                        $sales_log->type = 1;
                        
                        if($sales_log->save()){
                            $return['success'] = 1;
                            $transaction->commit();
                        } else {
                            $transaction->rollBack();
                            $return['message'] = current($sales_log->getFirstErrors());
                        }
                    } else {
                        $transaction->rollBack();
                        $return['message'] = current($refund->getFirstErrors());
                    } 
                } catch (\Exception $e) {
                    $transaction->rollBack();
                    $return['message'] = $e->getMessage();
                }
            }
        } else $return['message'] = '未找到相关的售后单信息！';
        
        return json_encode($return);
    }
    
    /**
     * @description:上传凭证
     * @return: return_type
     * @author: wufeng
     * @date: 2016年7月8日 下午3:23:21
     * @review_user:
     */
    
    public function actionAjaxUpload() {
        $data = ['success' => false, 'message' => '', 'img_url' => ''];
        
        if (!Yii::$app->request->isPost) {
            $data['message'] = '必须为post提交！';
            return json_encode($data);
        }
        
        $model = new UploadForm();
        $img_obj = UploadedFile::getInstance($model, 'img');
        $return = uploadImage($img_obj);
        if(empty($return['error_mes'])){
            $data['success'] = true;
            $data['img_url'] = $return['img_url'];
        }else{
            $data['message'] = $return['error_mes'];
        }
        
        return json_encode($data);
    }
    
    /*
     * @title:个人中心跨境订单确认收货
     * @desc:seasOrderSure
     * @author:lxzmy
     * @date:2016-7-13 17:22
     * return bool
     */
    public function actionSeasOrderSure(){
        $res = 0;
        $order_code = f_post('order_code');
        if($order_code){
//            $arr = OmsInfo::updateAll(['order_status'=>220],['order_code'=>$order_code]);
            $orderStatus = 0;
            $oms_info = OmsInfo::find()->where(['order_code'=>$order_code])->asArray()->one();
            if($oms_info['pay_way'] == 1){
                $orderStatus = 220;  //在线支付  用户签收 待结算
            }
            if($oms_info['pay_way'] == 4){
                $orderStatus = 655;  //云贷  用户签收 待结算
            }
            $description=$this->user_info['user_name'].'在'.  date('Y-m-d H:i:s').'PC前台确认收货，订单状态为'.OmsStatus::getStatusName($orderStatus);
            $arr = OmsInfo::changeOrderStatus($oms_info['id'], $orderStatus, $description,$this->user_info['login_account']);
            if($arr){
                $res = 1; //收货成功
                OmsLog::creatLog($order_code,'用户签收',$this->user_info['user_name'],'跨境订单前台用户签收');
            }else{
                $res = 2;
            }
        }
        return $res;
    }

    /**
     * @description:售后单不修退回
     * @return: return_type
     * @author: leo
     * @date: 2016年7月29日10:42:16
     * @review_user:
     */
    public function actionAfterSalesCancelPay(){
        $sales_order = f_get('sales_order');
        $flag = f_get('flag',1);

        if($flag==1){
            $redirect_url = '/ucenter/record';
        }else{
            $redirect_url = '/ucenter/details?sales_order='.$sales_order;
        }

        $after_sales_data = AfterSalesOrder::find()->where(['sales_order'=>$sales_order,'user_id'=>$this->user_info['id']])->asArray()->one();
        if(!empty($sales_order) && !empty($after_sales_data)){
            $out = AfterSalesOrder::ChangeOrderStatus($after_sales_data['id'],95,'客户不修退回','客户');
            if($out==1){
                return $this->redirect($redirect_url);
            }else{
                return f_alert('wrong',$out,true,$redirect_url);
            }

        }else{
            return f_alert('wrong','数据请求错误！',true,$redirect_url);
        }
    }
}
