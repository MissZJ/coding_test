<?php
namespace frontend\controllers;

use common\models\goods\GoodsSales;
use common\models\user\SiteMessagesRelated;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use common\models\user\form\FrontendLoginForm;
use frontend\components\Controller2016;
use common\models\user\UserMember;
use common\models\other\OtherRegion;
use common\models\user\Pusher;
use common\models\user\UserStoreType;
use yii\db\Query;
use common\models\goods\DepotCity;
use common\models\goods\SupplierGoods;
use common\models\goods\Photo;
use common\models\user\UserShopsSupplier;
use common\models\goods\searchs\AttrExtend;
use common\models\goods\GoodsComment;
use common\models\user\ShoppingCart;
use common\models\other\FavoriteGoods;
use common\models\other\FavoriteShop;
use yii\web\ForbiddenHttpException;
use yii\helpers\Json;
use common\models\order\OrderMinusGoods;
use common\models\other\OtherTelMsgLog;
use common\util\Ucpaas;
use common\models\sales\GoodsSalesStatics;
use common\models\markting\MarktingCouponBySupplier;
use common\models\goods\BaseGoods;
use common\models\user\ReceiveAddress;
use common\models\shopPrice\ShopPrice;
use common\models\user\SupplierCityScreen;
use common\models\user\StoreMall;
use common\models\markting\LadderGroup;
use common\models\markting\LadderGroupGoods;
use common\models\markting\LadderGroupPrice;
use common\models\order\OrderNoticeGoods;
use common\models\order\OrderNoticeGroup;
use common\models\other\GoodsMallInfo;
use common\models\goods\Type;
use common\models\user\GoodsPrivilegeUser;
use common\models\lowest\BaseLowestQuotation;
use common\models\lowest\BaseLowestQuotationModule;
use yii\helpers\ArrayHelper;
use common\models\other\ExportLog;
/**
 * Site controller
 */
class SiteController extends Controller2016
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
        $freeArr = [
            'logout','login','veriy-code',
            'register-login','register-shop','register','register-status',
            'forget-pwd-check-phone','forget-pwd-new-pwd','forget-pwd-update-ok','update-pwd-status','send-code',
            'check-phone','check-code','check-login-account','get-region','send-voice',
            'error','check-phone-pwd','check-phone-pic','send-coder','send-voicer','check-yi','login-dialog',
            'lowest-quotation',
        ];
        if(in_array($action->id, $freeArr)){
            return true;
        }else{
            if ($this->user_info) {
                return true;
            }else{
                header("Location: /site/login"); exit;
                return FALSE;
            }
            
        }
    }
    
    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
//             'error' => [
//                 'class' => 'yii\web\ErrorAction',
//             ],
        ];
    }

    /**
      * @Title:actionIndex
      * @Description:首页
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:38:24
     */
    public function actionIndex()
    {
    	$this->layout = '_blank';
//        f_d($this->user_info);
        return $this->render('index');
    }

    /**
      * @Title:actionLogin
      * @Description:登录
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:38:27
     */
    public function actionLogin()
    {
        $this->layout = false;
//        if (!\Yii::$app->user->isGuest) {
//            return $this->goHome();
//        }
            //to-do 待观察重定向问题 --zend.wang 2015 12.22 9:26
          if(f_isMobile()){
              if(strpos($_SERVER['HTTP_HOST'],'www.51dh.com.cn')!==false){
                header("Location:http://wsc.51dh.com.cn/site/login"); exit;
              }
          }
            if ($this->user_info) {
                return $this->goHome();
            }
          $ad_info_main = f_c('ad_info_login');
          if(!$ad_info_main) {  
            $ad_info_main = [];
            //已经登陆页面广告
            $ad_info_main= (new Query())->from("{{other_ad_position}} a")
              ->leftJoin("{{other_ad_info}} b","b.position_id=a.id")
              ->where(['a.id'=>56,'a.is_deleted'=>0,'b.status'=>1])
              ->orderBy('sort asc')
              ->all();
            f_c('ad_info_login',$ad_info_main,3600*12);
          }
          //取出最新发布峰云快讯
          $new_article = ( new Query())->from("{{other_article_nav}} a")
            ->leftJoin("{{other_article_info}} b","b.nav_id=a.id")
            ->where(['a.sort'=>2,'b.status'=>1,'a.status'=>1])
            ->orderBy('publish_time desc')
            ->one();
          //随机取出5条数据
          $show_article = (new \yii\db\query())->from("{{other_article_nav}} a")
            ->leftJoin("{{other_article_info}} b","b.nav_id=a.id")
            ->where(['a.sort'=>2,'b.status'=>1,'a.status'=>1])
            ->orderBy('publish_time desc')
            ->offset(1)
            ->limit(5)
            ->all();
        $model = new FrontendLoginForm();
        
        $message = '';
        if ($model->load(Yii::$app->request->post())) {
            $verifyCode = $_POST['FrontendLoginForm']['verifycode'];
            
            if($verifyCode == f_s('verifyCode')){
                if ($model->login()) {
                    return $this->goBack();
                }else{
                    if($model->hasErrors()){
                        foreach ($model->getFirstErrors() as $value){
                            $message = $value;
                        }
                    }
                    return $this->render('login', [
                        'model' => $model,
                        'message' => $message,
                        'new_article'=>$new_article,
                        'show_article'=>$show_article,
                        'ad_info_main'=>$ad_info_main,
                    ]);
                }
            }else{
                $message = '验证码输入不正确！';
                return $this->render('login', [
                    'model' => $model,
                    'message' => $message,
                    'new_article'=>$new_article,
                    'show_article'=>$show_article,
                    'ad_info_main'=>$ad_info_main,
                ]);
            }
            
        } else {

            if($model->hasErrors()){
                foreach ($model->getFirstErrors() as $value){
                    $message = $value;
                }
            }
            
            return $this->render('login', [
                'model' => $model,
                'message' => $message,
                'new_article'=>$new_article,
                'show_article'=>$show_article,
                'ad_info_main'=>$ad_info_main,
            ]);
        }
    }

    /**
      * @Title:actionLogout
      * @Description:退出
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:38:36
     */
    public function actionLogout()
    {
        $user_id = \Yii::$app->user->id;
        $key = 'frontend-'.$user_id.'-new_51dh3.0';
        $deleteBoolean = f_c($key,false);

        Yii::$app->user->logout();
        
        return $this->goHome();
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
      * @Title:actionRegisterLogin
      * @Description:注册第一步
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:38:06
     */
    public function actionRegisterLogin() {
        
    	$type = f_get('type',55);
    	
    	$this->layout = '_blank';
        $provinceArr = OtherRegion::getRegion('1');
        $store_property = UserMember::storePropertys();
        $store_type = UserStoreType::getStoreInfo();
        if($type == 55) {
        	return $this->render('register-login');
        } else {
        	return $this->render('region',[
        			'provinceArr' => $provinceArr,
        			'store_property' => $store_property,
        			'store_type' => $store_type,
        	]);
        }
       	
    }
    
    /**
      * @Title:actionRegisterShop
      * @Description:注册第二步
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:38:09
     */
    public function actionRegisterShop() {
        $this->layout = false;
        $model = new UserMember();
        $params = Yii::$app->request->post();
        if(empty($params)){
          return f_msg('您填写的数据不全', '/site/register-login');  
        }
//         if($model->load($params)){
    
            $provinceArr = OtherRegion::getRegion('1');
            $store_property = UserMember::storePropertys();
            $store_type = UserStoreType::getStoreInfo();
            
            return $this->render('register-shop',[
                'shopInfo' => json_encode($params),
                'provinceArr' => $provinceArr,
                'store_property' => $store_property,
                'store_type' => $store_type
            ]);
//         }else{
//             return $this->render('register-login');
//         }
    }
    
    /**
      * @Title:actionRegister
      * @Description:注册插入数据库
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:38:02
     */
    public function actionRegister() {
        $model = new UserMember();
        $params = Yii::$app->request->post();
        if(!isset($params['shopInfo'])) {
           return f_msg('您填写的数据不全', '/site/register-login');
        }
        $shopInfo = json_decode($params['shopInfo'],true);
        if(empty($shopInfo)){
           return f_msg('登录帐号不能为空', '/site/register-login');  
        }
        unset($params['shopInfo']);
        $params['UserMember'] = array_merge($params['UserMember'],$shopInfo['UserMember']);
 
        if($model->load($params)){
        	
            $params = $params['UserMember'];
            //首先判断各自段是存在
            if(isset($params['phone']) && isset($params['shop_name']) && isset($params['user_name']) && isset($params['password']) && isset($params['rePassword']) && isset($params['province']) && isset($params['city']) && isset($params['district']) && isset($params['address'])){
            
                if(empty($params['password']) || empty($params['user_name']) || empty($params['phone']) || empty($params['shop_name']) || empty($params['address'])) {
                    return f_msg('必填参数不能为空', '/site/register-login');
                }
               
                $userInfo = $model->findOne(['login_account' => $params['phone']]);
                if($userInfo) {
                   return f_msg('您的帐号已经被注册', '/site/register-login');
                }
                
                $phoneInfo = $model->findOne(['phone' => $params['phone']]);
                
                $codeInfo = f_s('registerCode') == $params['checkCode'] ? true : false;
                if(!$codeInfo) {
                    return f_msg('您的手机验证码错误', '/site/register-login');
                }
               
                $pwdInfo = $params['password'] == $params['rePassword'] ? true : false;
                if(!$pwdInfo) {
                    return f_msg('两次输入密码不一样', '/site/register-login');
                }

                if(!$userInfo && $codeInfo && $pwdInfo){
                	
                    $model->login_account = $params['phone'];
                    $model->user_name = trim($params['user_name']);
                    $model->shop_name = trim($params['shop_name']);
                    $model->password = md5($params['password']);
                    $model->province = $params['province'];
                    $model->city = $params['city'];
                    $model->district = $params['district'];
                    $model->address = trim($params['address']);
                    $model->phone = $params['phone'];
                    $model->user_group = 0;
                   
                    //用户状态，默认状态待审核,如果地推人员信息校验通过，默认开通
                    $model->user_status = Pusher::checkPusher($params['pusher_id'], md5($params['pusher_pwd'])) ? 1 : 2; 
                    $model->month_sale_money = isset($params['month_sale_money'])&&!empty($params['month_sale_money']) ? $params['month_sale_money'] : '0.00';
                    $model->is_service = 0; //默认非客户账号，客服帐号是超级帐号，可以切换所有站点
                    $model->store_type = $params['store_type'];
                    if($model->store_type == 1){
                        $model->store_property = $params['store_property'];
                    }else{
                        $model->store_property = 8;//其他
                    }
                    
                    $model->email = trim($params['email']);
                    $model->qq = trim($params['qq']);
                    $model->reg_time = date('Y-m-d H:i:s');//注册时间
                    
                    $model->pusher_id = !empty($params['pusher_id']) ? $params['pusher_id'] : 0;
                    $model->month_sale_num = isset($params['month_sale_num'])&&!empty($params['month_sale_num']) ? $params['month_sale_num'] : '0';
                    $model->user_group = 0;//默认为0 ，移动用户为1
                    $connection = Yii::$app->db;//事务开始
                    $transaction = $connection->beginTransaction();
                 
                    try {
                        if($model->save()){
                            $transaction->commit();
                            
                            //同步收货地址begin
                            $receiveAddress = new ReceiveAddress();
                            
                            $receiveAddress->name = $model->user_name;
                            $receiveAddress->phone = $model->phone;
                            $receiveAddress->tel = $model->tel;
                            $receiveAddress->province = $model->province;
                            $receiveAddress->city = $model->city;
                            $receiveAddress->district = $model->district;
                            $receiveAddress->address = $model->address;
                            $receiveAddress->user_id = $model->id;
                            $receiveAddress->status = 1;
                            
                            $receiveAddress->save();
                            //同步收货地址end
                            
                            $message = $model->user_status == 2 ? '注册成功,等待审核!' : '注册成功,立即登录!';
                            f_msg($message,f_url(['site/register-status']));
                        }else{
                             $message = '注册失败,请重新注册!';
                             f_msg($message,f_url(['site/register-login']));
                        }
                    } catch (\Exception $e) {
                        f_d($e->getMessage());
                        $transaction->rollBack();
                    }
                }else{
                	$model->save();
                	
                	f_d($model->errors);
                    $message = '注册失败,信息校验不通过，请重新注册!';
                    f_msg($message,f_url(['site/register-login']));
                }
            }else{
                $message = '注册失败,信息校验不通过，请重新注册!';
                f_msg($message,f_url(['site/register-login']));
            }
            
        }else{
            return $this->redirect('register-login');
        }
    }
    
    /**
      * @Title:actionRegisterStatus
      * @Description:注册成功页面
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:37:49
     */
    public function actionRegisterStatus() {
        $this->layout = false;
        return $this->render('register-status');
    }
    
    /**
      * @Title:actionForgetPwdCheckPhone
      * @Description:找回密码第一步
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:38:48
     */
    public function actionForgetPwdCheckPhone() {
        $this->layout = false;
        
        return $this->render('check-phone');
    }
    
    /**
      * @Title:actionForgetPwdNewPwd
      * @Description:找回密码第二步
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午6:49:38
     */
    public function actionForgetPwdNewPwd() {
        $this->layout = false;
        
        if(isset($_POST['login_account'])) {
        	$login_account = $_POST['login_account'];
        	$info = UserMember::find()->where(['login_account'=>$login_account])->asArray()->one();
            return $this->render('new-pwd',[
                'phone' => $_POST['phone'],
                'login_account' => $_POST['login_account'],
            	'user_id'=>$info['id'],
            ]);
        }else{
//             f_msg('数据不完整','forget-pwd-check-phone');
			exit('数据不完整');
            return false;
        }
        
    }
    
    /**
      * @Title:actionForgetPwdUpdateOk
      * @Description:找回密码插入数据库&成功页面
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午6:49:40
     */
    public function actionForgetPwdUpdateOk() {
        
        if(isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'POST'){
            if(isset($_POST['password']) && isset($_POST['rePassword']) && isset($_POST['user_id'])) {
                if(!empty($_POST['user_id'])){
                  $user_id = $_POST['user_id'];
                  $user_info = UserMember::find()->where(['id'=>$user_id])->one();  
                }else{
                  f_msg('数据不完整','forget-pwd-check-phone');  
                }
                
                
                if(!empty($_POST['password']) && ($_POST['password'] == $_POST['rePassword'])) {
                   $user_info->password = md5($_POST['rePassword']);
                    
                    if($user_info->save()){
                        return $this->redirect(['update-pwd-status']);
                    } else {
                        f_msg('更新失败','forget-pwd-check-phone');
                    }
                }else{
                    f_msg('两次密码输入不一致','forget-pwd-check-phone');
                    return false;
                }
            }else{
                f_msg('数据不完整','forget-pwd-check-phone');
                return false;
            }
        }else{
            f_msg('提交方式不对','forget-pwd-check-phone');
            return false;
        }
    }
    
    public function actionUpdatePwdStatus(){
        $this->layout = false;
        return $this->render('update-ok');
    }
    /**
      * @Title:actionCheckPhone
      * @Description:验证手机号是否注册
      * @return:json of object
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:38:56
     */
    public function actionCheckPhone(){
        $phone = f_post('phone');
        
        $returnVal = ['status' => 0, 'message' => ''];
        if(!empty($phone)) {
            $info = UserMember::find()->where(['login_account'=>$phone])->one();
            if($info) {
                $returnVal['message'] = '此手机号已注册';
            } else {
                $returnVal['status'] = 1;
            }
        }else{
            $returnVal['message'] = '请填写正确的手机号';
        }
        
        echo json_encode($returnVal);
    }
    
    /**
      * @Title:actionCheckCode
      * @Description:ajax验证验证码
      * @param code 用户输入验证码
      * @param name session中验证码名字
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:39:00
     */
    public function actionCheckCode() {
        $code = f_post('code');
        $name = f_post('name');
        $sessionCode = f_s($name);
        
        $returnVal = ['status' => 0, 'message' => ''];
       
        if(!empty($code) && !empty($sessionCode)) {
            if($sessionCode != $code) {
                $returnVal['message'] = '验证码不正确';
            } else {
                $returnVal['status'] = 1;
            }
        }else{
            $returnVal['message'] = '未获取到参数值';
        }
        
        echo json_encode($returnVal);
    }
    
    /**
      * @Title:actionCheckLoginAccount
      * @Description:校验登录名是否已注册
      * @return:
      * @author:huaiyu.cui
      * @date:2015-10-30 下午4:39:04
     */
    public function actionCheckLoginAccount() {
        $login_account = f_post('login_account');
        
        $returnVal = ['status' => 0, 'message' => ''];
        if(!empty($login_account)) {
            //$info = UserMember::findOne(['login_account'=>$login_account]);
            $info = UserMember::findOne(['login_account'=>$login_account]);  //电话号码
            if($info) {
                $returnVal['message'] = '此手机号已注册';
            } else {
                $returnVal['status'] = 1;
            }
        }else{
            $returnVal['message'] = '未获取到参数值';
        }
        
        echo json_encode($returnVal);
    }
    
    /**
     * @Title:actionGetProvince
     * @Description:获取地区信息
     * @return:
     * @author:huaiyu.cui
     * @date:2015-10-20 下午7:18:57
     */
    public function actionGetRegion() {
        $parent_id = f_post('parent_id',0);
        $provinceInfo = OtherRegion::getRegion($parent_id);
        echo count($provinceInfo)!=0 ? json_encode($provinceInfo) : 0;
    }
    
    /**
      * @Title:actionSendCode
      * @Description:ajax发送验证码
      * @param phone手机号
      * @param name验证码名字
      * @return: 
      * @author:huaiyu.cui
      * @date:2015-11-3 下午2:17:55
     */
    public function actionSendCode() {
        $phone = f_post('phone');
        $name = f_post('name');

        $returnVal = ['status' => 0, 'message' => '','code'=>''];
        if($phone) {
            if(!preg_match("/^(0|86|17951)?(13[0-9]|15[012356789]|17[0-9]|18[0-9]|14[0-9])[0-9]{8}$/",$phone)){
                $returnVal['message'] = '请输入正确的手机号!';
            }else{
                
                $ip = \common\util\F::IP();
                
                $start = date('Y-m-d 00:00:00',time());
                $end = date('Y-m-d 23:59:59',time());
                
                $sum = OtherTelMsgLog::find()->select('count(id)')->where(['between','operate_time',$start,$end])->andWhere(['ip'=>$ip])->scalar();
                
//                 $key = "smscode{$phone}";
//                 $smsCounter = f_c($key);
//                 if($smsCounter){
//                     $smsCounter = intval(f_c($key));
//                 }else{
//                     $smsCounter=0;
//                 }

//                 if ($smsCounter && $smsCounter >= 5) {
//                     $returnVal['message'] = '您今天已获取5次验证码，请于明天再操作！';
//                 }else {
                if($sum >= 20){
                    $returnVal['message'] = '您今天已获取20次验证码，请于明天再操作！';
                }else{
                    $come_from = isset($_POST['come_from']) ? $_POST['come_from'] : 2; //1是注册 2忘记密码 3修改手机号码
                    $code = rand(1000,9999);
                    $content = sprintf(Yii::$app->params['smsValidateTemplate'],$code);
                    f_s($name,$code);
                    f_msm($phone, $content);

                    $model = new OtherTelMsgLog();
                     
                    $model->InsertLog($phone, 1, 1,$come_from,$ip);
                    
//                     $smsCounter++;
//                     $life_time = strtotime(date('Y-m-d 23:59:59',time())) - time();
//                     f_c($key,$smsCounter,$life_time);
                    $returnVal['status'] = 1;
                    $returnVal['code'] = $code;
                }

            }
        }else{
            $returnVal['message'] = '未获取到参数值';
        }
        
        echo json_encode($returnVal);
    }
    
    /**
      * @Title:actionSendVoice
      * @Description:语音验证码(24小时限制的只能获取5次)
      * @return:
      * @author:huaiyu.cui
      * @date:2015-11-26 下午2:37:42
     */
    public function actionSendVoice() {
        $ip = \common\util\F::IP();
        
        $start = date('Y-m-d 00:00:00',time());
        $end = date('Y-m-d 23:59:59',time());
        
        $sum = OtherTelMsgLog::find()->select('count(id)')->where(['between','operate_time',$start,$end])->andWhere(['ip'=>$ip])->scalar();
        
        $returnVal = ['status' => 0,'message'=>'','code'=>''];
        if($sum >= 20){
            $returnVal['message'] = '您今天已获取20次验证码，请于明天再操作！';
        }else{
            
            //初始化必填     09555805dfde63af1a322c9e87cd5aad
            $options['accountsid']='09555805dfde63af1a322c9e87cd5aad';
            $options['token']='691141ecbd3aa3b8bc0e5227fb16caf1';
            //初始化 $options必填
            $ucpass = new Ucpaas($options);
        
            $appId = "248a800c5c2740668344744f9c0e00ae";
            
            $code = rand(1000,9999);
           
            $to = $_POST['tel'];
            if(!preg_match("/^(0|86|17951)?(13[0-9]|15[012356789]|17[678]|18[0-9]|14[57])[0-9]{8}$/",$to)){
                $returnVal['message'] = '请输入正确的手机号!';
            }else {
                $come_from = $_POST['come_from'];
                $msg_type = $_POST['msg_type'];
                $type = $_POST['type'];
                if($come_from == 1) {
                    $res = UserMember::find()->where(['phone' => $to])->one();
                    if ($res) {
                        $returnVal['message'] = '手机号已存在！';
                    }else{
                        $model = new OtherTelMsgLog();
                       
                        $name = f_post('name');
                        f_s($name,$code);
                        
                        if ($model->InsertLog($to, $msg_type, $type,$come_from,$ip)) {
                            $data = $ucpass->voiceCode($appId, $code, $to);
                            $returnVal['status'] = 1;
                            $returnVal['message'] = $data;
                            $returnVal['code'] = $code;
                        } else {
                            $returnVal['message'] = '日志插入失败';
                        }
                    }
                }
            }
        }
        
        echo json_encode($returnVal);
    }

    
    
    /***
     * 要展示的分类的具体相关信息[目前分类写死]
     *  actionCateInfo
     *  wanmin
     */
    public function cateInfo()
    {
        ///需要展示的分类
        $cateId = array(
            '2' => "功能手机",
            '1' => "智能手机",
            '59' => "移动电源"
        );
        //城市可以根据用户信息获取城市ID;//默认走offset,limit参数
        $city = 220;
        $info = array();
        $records = array();
        $model =  new GoodsSalesStatics();
        foreach($cateId as $key => $v)
        {
            $res = $model->recommGoods($key,$city);
            if(is_array($res) && $res['status'] === true)
            {
                $records[$key] = $res['data'];
            }
        }
        if(count($records) > 0)
        {
            foreach($records  as $key =>$v)
            {
                foreach($v as $k1 => $v1)
                {
//                $info[$key]['goods_id'] = $v['goods_id'];
                    $info[$key][$k1] = SupplierGoods::getAllInfoById($v1['goods_id']);
                    $info[$key][$k1]['goods_id'] = $v1['goods_id'];
                }
            }
        }

        return $info;

    }



/**
 * 
 * @Title: actionDetail
 * @Description: 商品详情页
 * @return: Ambigous <string, string>
 * @author: yulong.wang
 * @date: 2015-12-14下午2:31:58
 */
    public function actionDetail() {
        $goods_id = $_GET['id'];
        $goodsId = $goods_id;
        $userInfo = $this->user_info;

        return $this->redirect('/goods/detail?id='.$goods_id);   //重定向 到 goods 控制器
    }

    /**
     * @description:大家在看按同品类月销量,同城覆盖仓库上架的商品中前9
     * @return:
     * @author: jr
     * @date:16-2-18
     * @modified_date:
     * @modified_user: jr
     * @review_user:
     */
    public function getTypeSaleGoods($good_id,$city){
        $key = 'type_sale_goods_'.$good_id;
        $re = f_c($key);
        if($re === false){
            $type_id = SupplierGoods::find()->select(['type_id'])->where(['id'=>$good_id])->scalar();

            //同城市覆盖仓库
            $depots = [];
            $depots = DepotCity::find()->select(['depot_id'])->where(['city'=>$city])->asArray()->all();
            $depots_id = array_column($depots,'depot_id');

            $month = date('Y-m');
            $arr = GoodsSalesStatics::find()->select(['goods_id'])
                ->where(['type_id'=>$type_id,'static_month'=>$month])
                ->orderBy('statics_totals desc')
                ->limit(9)
                ->asArray()
                ->all();
            $id_arr = array_column($arr,'goods_id');
            $goods = (new Query)->select('t1.id as goods_id,t1.base_id,t1.type_id,t1.brand_id,t1.unit_id,t1.goods_name,t1.color_id,t1.price,t1.sale_num,t1.supplier_id,t2.img_url')
                ->from('goods_supplier_goods as t1')
                ->leftJoin('goods_photo as t2','t1.cover_id=t2.id')
                ->where(['t1.status' => 1, 't1.enable' => 1, 't1.is_deleted' => 0, 't2.status' => 1])
                ->andWhere(['in','t1.depot_id',$depots_id])
                ->where(['in','t1.id',$id_arr])
                ->all();
            //按月销量取值不足9条时
            if(count($goods) < 9){
                $goodsInfo = (new Query())->select('t1.id as goods_id,t1.base_id,t1.type_id,t1.brand_id,t1.unit_id,t1.goods_name,t1.color_id,t1.price,t1.sale_num,t1.supplier_id,t4.img_url')
                    ->from('goods_supplier_goods as t1')
                    ->leftJoin('goods_photo as t4','t1.cover_id=t4.id')
                    ->where(['t1.type_id'=>$type_id])
                    ->andWhere(['not in','t1.id',$id_arr])
                    ->andWhere(['in','t1.depot_id',$depots_id])
                    ->orderBy('t1.sale_num desc')
                    ->limit(9-count($goods))
                    ->all();
            }
            $num = count($goods);
            foreach($goodsInfo as $key=>$vo){
                $goods[$num+$key+1]=$vo;
            }
            f_c($key,$goods,3600);
        }else{
            $goods = f_c($key);
        }
        return $goods;


    }

    /**
     * @description:同商城、同城市所覆盖仓库中同类型销量前10
     * @return:
     * @author: jr
     * @date:
     * @modified_date:
     * @modified_user: jr
     * @review_user:
     */
    public function getTypeGoods($good_id,$city){
        $key = 'type_goods_'.$good_id;
        $re = f_c($key);
        if($re === false){
            $arr =[];
            $type = SupplierGoods::find()->select(['type_id'])->where(['id'=>$good_id])->scalar();
            //根据城市找出所有覆盖的仓库 $depots[]
            $depots = [];
            $depots = DepotCity::find()->select(['depot_id'])->where(['city'=>$city])->asArray()->all();
            $depots_id = [];
            foreach($depots as $vo){
                $depots_id[] = $vo['depot_id'];
            }

            $goodsInfo = (new Query())->select('t1.id as goods_id,t1.base_id,t1.type_id,t1.brand_id,t1.unit_id,t1.goods_name,t1.color_id,t1.price,t1.sale_num,t1.supplier_id,t4.img_url')
                ->from('goods_supplier_goods as t1')
                ->leftJoin('goods_photo as t4','t1.cover_id=t4.id')
                ->where(['t1.type_id'=>$type,'t1.status'=>1,'t1.enable'=>1,'t1.is_deleted'=>0])
                ->andWhere(['>','t1.price',0])
                ->andWhere(['>','t1.num_avai',0])
                ->andWhere(['in','t1.depot_id',$depots_id])
                ->orderBy('t1.sale_num desc')
                ->limit(10)
                ->all();
            f_c($key,$goodsInfo,3600*8);
        }else{
            $goodsInfo = f_c($key);
        }

        return $goodsInfo;
    }

    public function getGoodsInfo($good_id)
    {
        $returnVal = [];
        $key = 'detail_rank_'.$good_id;
        $res = f_c($key);
        if($res === false){
            $goodsId_arr = GoodsSales::find()->select(['goods_id'])->where(['good_id' => $good_id, 'position_id' => 2])->asArray()->all();
            if ($goodsId_arr) {
                $id_arr = [];
                foreach ($goodsId_arr as $id) {
                    $id_arr[] = $id['goods_id'];
                }
                $returnVal = (new Query())->select('t1.id as goods_id,t1.base_id,t1.type_id,t1.brand_id,t1.unit_id,t1.goods_name,t1.color_id,t1.price,t1.sale_num,t1.supplier_id,t4.img_url')
                    ->from('goods_supplier_goods as t1')
                    ->leftJoin('goods_depot as t2', 't1.depot_id=t2.id')
                    ->leftJoin('goods_photo as t4', 't1.cover_id=t4.id')
                    ->leftJoin('goods_sales as t3', 't3.good_id=t1.id')
                    ->where(['t1.status'=>1,'t1.enable'=>1,'t1.is_deleted'=>0,'t2.status'=>1,'t3.position_id'=>2])
                    ->andWhere(['>','t1.price',0])
                    ->andWhere(['>','t1.num_avai',0])
                    ->where(['in', 't1.id', $id_arr])
                    ->orderBy('t3.weight asc,')
                    ->limit(10)
                    ->all();
                f_c($key,$returnVal,3600*8);
            }
        }else{
            $returnVal = f_c($key);
        }
        return $returnVal;

    }

    public function getSeeAngin($good_id){
        $returnVal=[];
        $key = 'details_see_'.$good_id;
        $re = f_c($key);
        if($re === false) {
            $returnVal = (new Query())->select('t1.id as goods_id,t1.base_id,t1.type_id,t1.brand_id,t1.unit_id,t1.goods_name,t1.color_id,t1.price,t1.sale_num,t1.supplier_id,t4.img_url')
                ->from('goods_supplier_goods as t1')
                ->leftJoin('goods_depot as t2', 't1.depot_id=t2.id')
                ->leftJoin('goods_photo as t4', 't1.cover_id=t4.id')
                ->leftJoin('goods_sales as t3', 't3.goods_id=t1.id')
                ->where(['t1.status' => 1, 't1.enable' => 1, 't1.is_deleted' => 0, 't2.status' => 1])
                ->where(['t3.good_id' => $good_id, 't3.position_id' => 1])
                ->andWhere(['>', 't1.price', 0])
                ->andWhere(['>', 't1.num_avai', 0])
                ->orderBy('t3.weight asc')
                ->limit(9)->all();
            f_c($key,$returnVal,3600*8);
        } else {
            $returnVal = $re;
        }
        //f_d($returnVal);
        return $returnVal;
    }
    
    
    /**
     * 
     * @Title: actionAddShoppingCar
     * @Description: 添加购物车
     * @return: multitype:number string 
     * @author: yulong.wang
     * @date: 2015-11-6下午2:31:55
     */
    public function actionAddShoppingCar(){
        $out = 0;
        $user_info = $this->user_info;
        $sign = SupplierGoods::checkShoppingCarGoods($_POST['goods_id'],$_POST['city']);
        if ($sign == 1) {
            $dateTime = date('Y-m-d H:i:s',time());
            $shoppingCar = ShoppingCart::findOne(['user_id'=>$_POST['user_id'],'goods_id'=>$_POST['goods_id'],'create_time'=>$dateTime,'time_line'=>$_POST['time_line']]);
            if ($shoppingCar) {
                $shoppingCar->goods_num = $shoppingCar->goods_num + $_POST['num'];
                if ($shoppingCar->save()) {
                    $out = 1;
                } 
            } else {
                $shoppingCarMod = new ShoppingCart();
                $shoppingCarMod->user_id = $_POST['user_id'];
                $shoppingCarMod->goods_id = $_POST['goods_id'];
                $shoppingCarMod->goods_num = $_POST['num'];
                $shoppingCarMod->create_time = $dateTime;
                $shoppingCarMod->time_line = $_POST['time_line'];
                if ($shoppingCarMod->save()) {
                    $out = 1;
                }
            }
            
            if ($out == 1) {
                $shoppingGoods = ShoppingCart::find()->where(['user_id'=>$_POST['user_id']])->asArray()->all();
                if (!empty($shoppingGoods)) {
                    $total_goods_num = '';
                    $total_goods_price = '';
                    foreach ($shoppingGoods as $value) {
                         $goods_price = f_price($value['goods_id'],$value['goods_num'],$user_info['city'],1);
                         $total_goods_num += $value['goods_num'];
                         $total_goods_price += $goods_price;
                    }
                    
                    $returnVal['status'] = 1;
                    $returnVal['msg'] = '<p>购物车共有<span>'.$total_goods_num.'</span>件有效商品 合计：<span>￥'.$total_goods_price.'</span></p>';
                }
            } else {
                $returnVal['status'] = 0;
                $returnVal['msg'] = '加入购物车失败';
            }
        } else {
            $returnVal = ['status'=>0,'msg'=>'商品已下架'];
        }
        return json_encode($returnVal);
    }
    
    /**
     * @description:ajax获取头部信息
     * @return: return_type
     * @author: leo
     * @date: 2015年11月20日下午2:35:09
     * @review_user:
    */
    public function actionGetHeaderMes(){
        $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
        if(!empty($user_info)){
            $goods_viewd = SupplierGoods::getViewGoods();
            ksort($goods_viewd);
            
            $goods_view_html = '';
            foreach ($goods_viewd as $key=>$val){
                if($key < 4){
                    $supplierGoodsList = SupplierGoods::find()->where(['id' => $val])->asArray()->one();
                    $goods_view_html .='<a href="/site/detail?id='.$val.'"><img title="'.$supplierGoodsList['goods_name'].'" src="'.Photo::getImgLink($val).'"></a>';
                }
            }
            
            $result = [
                        'login_account'=>$user_info['login_account'],
                        'level_name'=>UserMember::getLevel($user_info['level']),
                        'goods_view_html'=>$goods_view_html,
                        'touxiang'=>UserMember::getUserAvatar($user_info['touxiang']),
                      ];
            
            return Json::encode($result);
        }
    }
    
    /**
     * @description:获取购物车数据
     * @return: return_type
     * @author: leo
     * @date: 2015年11月23日上午10:08:02
     * @review_user:
    */
    public function actionGetCartInfo(){
        $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
        //$cart_data = ShoppingCart::find()->where(['user_id'=>$user_info['id']])->asArray()->all();
        $cart_data1 = ShoppingCart::getAllGoods($user_info['id'],1);
        $cart_data = $cart_data1;
        $count = 0;
        if(!empty($cart_data)){
            $total_price = 0;
            $html = '<p class="cart_tl">最新加入的商品</p><div class="add_product"><ul>';
            foreach ($cart_data as $val){
                //$goods_data = SupplierGoods::findOne($val['goods_id']);
                $html .='<li><a class="prd_img" href="/site/detail?id='.$val['goods_id'].'" title="'.$val['goods_name'].'"><img src="'.Photo::getImgLink($val['goods_id']).'">
                     </a><a class="prd_tl" href="/site/detail?id='.$val['goods_id'].'" title="'.$val['goods_name'].'"><p>'.$val['goods_name'].'</p></a> <div class="prd_pnd">
                     <span>￥'.$val['price'].'</span><span class="multiply">*</span><span>'.$val['goods_num'].'</span>
                     <a class="prd_delete" href="javascript:void(0)" data-id="'.$val['id'].'">删除</a></div></li>';
                $count +=$val['goods_num'];
                $total_price += $val['price']*$val['goods_num'];
            }
            
            $html .= '</ul></div><div class="prd_total"><p>共<span class="orange">'.$count.'</span>件商品&nbsp;总计：￥<span class="orange total_num">'.$total_price.'</span></p>
                            <a class="go_cart" href="/cart/index">去购物车</a>
                        </div>';
            $result = ['html'=>$html,'count'=>$count];
            
            return Json::encode($result);
        }else{
            $html = '<div class="nogoods"><b></b>购物车中还没有商品，赶紧选购吧！</div>';
            $result = ['html'=>$html,'count'=>$count];
            return Json::encode($result);
        }
        
    }
    
    /**
     * @description:头部购物车删除
     * @return: return_type
     * @author: leo
     * @date: 2015年11月23日上午11:12:07
     * @review_user:
    */
    public function actionDeleteHeaderCart(){
        if(isset($_POST['cart_id'])&&!empty($_POST['cart_id'])){
            $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
            $model = ShoppingCart::findOne($_POST['cart_id']);
            if($model){
                $out = ShoppingCart::deleteAll(['id'=>$model->id,'user_id'=>$user_info['id']]);
                if($out){
                    return 1;
                }else{
                    return Json::encode('数据删除错误！');
                }
            }else{
                return Json::encode('未找到数据！');
            }
        }else{
            return Json::encode('数据发生错误！');
        }
    }


    /**
     * @description:更改城市缓存信息
     * @return:
     * @author: wanmin
     * @date: 2015年12月8日上午11:12:07
     *
     */
    public function actionChangeCity()
    {

        $city = \Yii::$app->request->getQueryParam("city");
        $province = \Yii::$app->request->getQueryParam("pro");
        $user_id = \Yii::$app->user->id;
        if(!empty($city) && !empty($province))
        {
            // 更改省市信息  省市ID
            $userInfo = f_c('frontend-'.$user_id.'-new_51dh3.0');
            if(!empty($userInfo))
            {
                $province_id= OtherRegion::getRegionIdViaType($province,1);
                $city_id= OtherRegion::getRegionIdViaType($city,2);
                $district = OtherRegion::getDistrict($city_id);

//                $province_id= OtherRegion::getRegionId($province);
//                $city_id= OtherRegion::getRegionId($city);

                if(!empty($city_id) && !empty($province_id))
                {
                    $temp = array();
                    foreach($userInfo as $k => $v)
                    {
                        if($k == "province")
                        {
                            $temp[$k] = $province_id;
                        }
                        elseif($k == "city")
                        {
                            $temp[$k] = $city_id;
                        }
                        elseif($k == "district")
                        {
                            $temp[$k] = $district;
                        }
                        else
                        {
                            $temp[$k] = $userInfo[$k];
                        }
                    }
                    $model = UserMember::find()->where(['id'=>$user_id])->one();
                    $model->city = $city_id;
                    $model->province = $province_id;
                    $model->district = $district;
                    $model->save();
                    
                    if(is_array($temp) && count($temp) >0)
                    {
                        f_c('frontend-'.\Yii::$app->user->id.'-new_51dh3.0',$temp);
                    }
                }

                return $this->redirect('/home/index');

            }

        }
    }


    /**
     * @description:异步获取价格
     * @return: return_type
     * @author: leo
     * @date: 2015年12月30日下午4:35:17
     * @review_user:
    */
    public function actionGetPrice(){
        $return = [];
        if(isset($_POST['goods_id'])&&isset($_POST['num'])){
            $price = f_price($_POST['goods_id'], $_POST['num'], $this->user_info['city'], 1);
            $return = ['code'=>'200','mes'=>'ok','price'=>$price];
        }else{
            $return = ['code'=>'101','mes'=>'数据传输错误！'];
        }
        return json_encode($return);
    }
    /**
     * @description:
     * @return: 
     * @author: sunkouping
     * @date: 2015年12月31日下午9:21:45
     * @modified_date: 2015年12月31日下午9:21:45
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionError(){
    	return $this->renderPartial('error',[
    			
    	]);
    }
    /**
     * @description:找回密码
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年1月5日下午2:50:11
     * @modified_date: 2016年1月5日下午2:50:11
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionCheckPhonePwd(){
    	$login_account = f_post('login_account');
    
    	$returnVal = ['status' => 0, 'message' => '','phone'=>'0'];
    	if(!empty($login_account)) {
    		$info = UserMember::find()->where(['login_account'=>$login_account])->asArray()->one();
    		if($info) {
    			$returnVal['status'] = 1;
    			$returnVal['message'] = '注册手机号'.$info['phone'].',如无法使用请联系客服';
    			$returnVal['phone'] = $info['phone'];
    		} else {
    			$returnVal['status'] = 0;
    			$returnVal['message'] = '帐号不存在';
    		}
    	}else{
    		$returnVal['message'] = '请输入登录帐号';
    		$returnVal['status'] = 0;
    	}
    
    	echo json_encode($returnVal);
    }
    /**
     * @description:找回密码
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年1月5日下午2:50:11
     * @modified_date: 2016年1月5日下午2:50:11
     * @modified_user: sunkouping
     * @review_user:
     */
    public function actionCheckPhonePic(){
    	$code = f_post('code');
    
    	$s_code = f_s('verifyCode');
		if($code == $s_code) {
			return 1;
		} else {
			return 2;
		}
    }
    /**
     * @Title:actionSendCode
     * @Description:ajax发送验证码 找回密码
     * @param phone手机号
     * @param name验证码名字
     * @return:
     * @author:huaiyu.cui
     * @date:2015-11-3 下午2:17:55
     */
    public function actionSendCoder() {
    	$phone = f_post('phone');
    	$name = f_post('name');
    
    	$returnVal = ['status' => 0, 'message' => '','code'=>'0'];
    	if($phone) {
    		if(!preg_match("/^(0|86|17951)?(13[0-9]|15[012356789]|17[0-9]|18[0-9]|14[0-9])[0-9]{8}$/",$phone)){
    			$returnVal['message'] = '请输入正确的手机号!';
    		}else{
    			$key = "smscode{$phone}";
    			$smsCounter = f_c($key);
    			if($smsCounter){
    				$smsCounter = intval(f_c($key));
    			}else{
    				$smsCounter=0;
    			}
    
    			if ($smsCounter && $smsCounter >= 20) {
    				$returnVal['message'] = '您今天已获取10次验证码，请于明天再操作！';
    			}else {
    				$code = rand(1000,9999);
    				$content = sprintf(Yii::$app->params['smsValidateTemplate'],$code);
    				f_s($name,$code);
    				f_msm($phone, $content);
    
    				$smsCounter++;
    				$life_time = strtotime(date('Y-m-d 23:59:59',time())) - time();
    				f_c($key,$smsCounter,$life_time);
    				$returnVal['status'] = 1;
    				$returnVal['code'] = $code;
    			}
    
    		}
    	}else{
    		$returnVal['message'] = '未获取到手机号';
    	}
    
    	echo json_encode($returnVal);
    }
    
    /**
     * @Title:actionSendVoice
     * @Description:语音验证码(24小时限制的只能获取5次) 找回密码
     * @return:
     * @author:huaiyu.cui
     * @date:2015-11-26 下午2:37:42
     */
    public function actionSendVoicer() {
    	$ip = \common\util\F::IP();
    
    	$start = date('Y-m-d 00:00:00',time());
    	$end = date('Y-m-d 23:59:59',time());
    
    	$sum = OtherTelMsgLog::find()->select('count(id)')->where(['between','operate_time',$start,$end])->andWhere(['ip'=>$ip])->scalar();
    
    	$returnVal = ['status' => 0,'message'=>'','code'=>'0'];
    	if($sum >= 20){
    		$returnVal['message'] = '您今天已获取20次验证码，请于24小时后再操作！';
    	}else{
    
    		//初始化必填
    		$options['accountsid']='09555805dfde63af1a322c9e87cd5aad';
    		$options['token']='691141ecbd3aa3b8bc0e5227fb16caf1';
    		//初始化 $options必填
    		$ucpass = new Ucpaas($options);
    
    		$appId = "248a800c5c2740668344744f9c0e00ae";
    		//248a800c5c2740668344744f9c0e00ae
    		$code = rand(1000,9999);
    		 
    		$to = $_POST['tel'];
    		if(!preg_match("/^(0|86|17951)?(13[0-9]|15[012356789]|17[678]|18[0-9]|14[57])[0-9]{8}$/",$to)){
    			$returnVal['message'] = '请输入正确的手机号!';
    		}else {
    			$come_from = $_POST['come_from'];
    			$msg_type = $_POST['msg_type'];
    			$type = $_POST['type'];
    			if($come_from == 1) {
    				$res = UserMember::find()->where(['phone' => $to])->one();
    				if (!$res) {
    					$returnVal['message'] = '手机号不存在！';
    				}else{
    					$model = new OtherTelMsgLog();
    					 
    					$name = f_post('name');
    					f_s($name,$code);
    
    					if ($model->InsertLog($to, $msg_type, $type,$come_from,$ip)) {
    						$data = $ucpass->voiceCode($appId, $code, $to);
    						$returnVal['status'] = 1;
    						$returnVal['message'] = $data;
    						$returnVal['code'] = $code;
    					} else {
    						$returnVal['message'] = '日志插入失败';
    					}
    				}
    			}
    		}
    	}
    
    	echo json_encode($returnVal);
    }
    
    
    
        /**
     * @description:验证易达短信客户
     * @return: return_type
     * @author: leo
     * @date: 2015年8月10日上午11:30:48
     * @modified_date: 2015年8月10日上午11:30:48
     * @modified_user: leo
     * @review_user:
    */
    public function actionCheckYi(){
        if(isset($_POST['username'])&&isset($_POST['spwd'])){
            $user_data = \common\models\user\UserMember::find()->where(['login_account'=>$_POST['username']])->asArray()->all();
            if(!empty($user_data)){
                if(md5($user_data[0]['password'])==$_POST['spwd']){
                    return 'ok';
                }else{
                    return 'error1';
                }
            }else{
                return 'error2';
            }
        }else{
            return 'error3';
        }
    }



    /**
     *  异步获取报表的地址
     *  wanmin
     */
    public function actionAjaxPrice()
    {
        //获取用户的城市以及店铺类型信息；
        $user_info = f_c('frontend-'.\Yii::$app->user->id.'-new_51dh3.0');

        if(is_array($user_info) && count($user_info) > 0)
        {
            $city_id = $user_info['city'];

            $shop_type = $user_info['store_type'];

            if(in_array($shop_type,[1,5]))
            {
                //通讯商城 [可以查看1、2商城]
                $mall_shop = 1;
            }
            elseif(in_array($shop_type,[2,6,7]))
            {
                //家电商城
                $mall_shop = 2;
            }
            else
            {
                //其他都是母婴商城 [3,4]
                $mall_shop = 3;
            }

//            $data['href'] = ShopPrice::getLink($city_id,$shop_type);
            $data['href'] = ShopPrice::getLink($city_id,$mall_shop);

            return json_encode($data);
        }

    }
    /**
   * @Title: actionJtallmoney
   * @Description: 阶梯定价的详情页数量修改
   * @return: return_type
   * @author: honglang.shen xiewei
   * @date: 2016-1-13
  */
  public function actionEjtallmoney(){
    $num = $_POST['bnum'];
    $goods_id = $_POST['goods_id'];
    $goods_info = SupplierGoods::findOne($goods_id);
    $group_id = $_POST['group_id'];
    //阶梯定价
    $all_num = $_POST['all_num'];
    $ladder_info = LadderGroupPrice::find()->select('num_range,ladder_price')->where(['group_id'=>$group_id])->asArray()->all();
    $data=[];
    $ladder_price = 0;
    foreach($ladder_info as $key=>$value){
      if($key==0){
        if($all_num<$value['num_range']){
          $ladder_price = $goods_info->price;
          break;
        } 
      }
      if($all_num >= $value['num_range']){
        $ladder_price = $value['ladder_price'];
      }
    }
    $data['ladder_price'] = $ladder_price;
    $ladder_each = 0;
    foreach($ladder_info as $key=>$value){
      if($key==0){
        if($num<$value['num_range']){
          $ladder_each = $goods_info->price;
          break;
        }
      }
      if($num >= $value['num_range']){
        $ladder_each = $value['ladder_price'];
      }
    }
    $data['ladder_each'] = $ladder_each;
    return json_encode($data);exit;
  }
  /**
   * @Title: actionGetOrderNotice
   * @Description: 下单通知系统
   * @return: return_type
   * @author: honglang.shen 
   * @date: 2016-1-21
  */
  public function actionGetOrderNotice(){
    $goods_id = f_post('goods_id',0);
    $data = [];
    foreach ($goods_id as $key => $value) {
      $data[] = OrderNoticeGoods::getOrderNoticeGoods($value);
    }
    
    if(empty($data)){
      $return = '';
    }else{
      $return = $data[0];
    }
    return json_encode($return);
  }
  /**
   * @Title: actionUpdateOrderNotice
   * @Description: 更新下单通知点击数
   * @return: return_type
   * @author: honglang.shen 
   * @date: 2016-1-25
  */
  public function actionUpdateOrderNotice(){
    $goods_id = $_POST['goods_id'];
    $click = $_POST['click'];
    $data = [];
    foreach ($goods_id as $key => $value) {
      $data[] = OrderNoticeGoods::getOrderNoticeGoods($value);
    }
    if(empty($data)){
      $return = 0;
    }else{
      $return = $data[0];
    }
    $connection = \Yii::$app->db;
    foreach ($return as $key => $value) {
      if($click == 'sure'){
        $sql = "update order_notice_group set `confirm_click`=`confirm_click`+1 where id=".$value['id'];
      }elseif($click == 'no'){
        $sql = "update order_notice_group set `cancel_click`=`cancel_click`+1 where id=".$value['id'];
      }
      $data = $connection->createCommand($sql)->execute(); 
    }
  }

    /*引导页面cookie*/
    public function actionLoginDialog(){
        f_ck('login_dialog',1,7200*6);
    }
    
    /**
        * @return string
        * @description: 最低报价
        * @date: 2015-12-28 14:44:51
        * @author: qiang.zuo
        * @modify:lxzmy 2016-2-17 10:07 
        */
	public function actionLowestQuotation(){
		$colors_nav = ['a','b','c','d'];
		$ModuleGoodsDetails = 'moduleGoodsDetails';
		$moduleInfo = "moduleInfo";
		$moduleGoodsDetails = Yii::$app->cache->get($ModuleGoodsDetails);
		$ModuleInfo = Yii::$app->cache->get($moduleInfo);
		if(!$ModuleInfo){
			$ModuleInfo = BaseLowestQuotationModule::find()->select('id,name')->where(['status'=>1])->orderBy("sort ASC")->asArray()->all();
			$ModuleInfo = ArrayHelper::map($ModuleInfo,"id","name");
			Yii::$app->cache->set($moduleInfo, $ModuleInfo, 43200);
		}
		if(!$moduleGoodsDetails){
			foreach($ModuleInfo as $key=>$vo){
				$info = BaseLowestQuotation::find()->select("keywords,type")->where(['module_id'=>$key,'status'=>1])->asArray()->all();
				foreach($info as $k=>$v){
					$arr = SupplierGoods::find()->select('id')->where(['like','keywords',$v['keywords']])->andWhere(['type_id'=>$v['type']])->asArray()->all();
					if($arr){
						foreach($arr as $vo1){
							$arr1[] = $vo1['id'];
						}
						$min = (new Query())->select('t1.name,t1.id,t2.price as price,t1.cover_id as img,t2.tips,t2.supplier_id,t2.id as supplier_goods_id')->from('goods_base_goods as t1')->leftJoin('goods_supplier_goods as t2','t1.id = t2.base_id')->where(['t2.id'=>$arr1])->andWhere('t1.status = 1  AND t2.status = 1 ')->andWhere(['>','t2.num_avai',0])->orderBy('t2.price ASC')->one();
						if($min){
							$info[$k]['base_id'] = $min['id'];
							$info[$k]['base_name'] = $min['name'];
							$info[$k]['base_price'] = $min['price'];
							$info[$k]['base_img'] = $min['img'];
							$info[$k]["tips"] = $min['tips'];
							$info[$k]['supplier_id'] = $min['supplier_id'];
							$info[$k]['supplier_goods_id'] = $min['supplier_goods_id'];
                                                        
						}else{
							$info[$k][] = [];
						}
					}else{
						$info[$k] = [];
					}
					unset($arr1);
				}
				$moduleGoodsDetails[$key] = $info;
				unset($info);
			}
			Yii::$app->cache->set($ModuleGoodsDetails, $moduleGoodsDetails, 43200);
		}
		$url = "";
//		$site_id = $this->user_info['city'];
//		if(isset($site_id)){
//			$url = SysSiteLowPrice::getLink($site_id);
//		}
		$error = '';
		$model =new FrontendLoginForm();
		if ($model->load(Yii::$app->request->post())) {
                    $verifyCode = $_POST['FrontendLoginForm']['verifycode'];
			if(f_isMobile()){
				if ($model->login()) {
					return $this->goBack();
				}
			}else{
				if($verifyCode == f_s('verifyCode')){
					if ($model->login()) {
						return $this->redirect('/quotation/lowest-quotation');
					}
				}else{
					header("Content-type:text/html;charset=utf-8");
					return $this->msg("验证码输入不正确！","lowest-quotation");
				}
			}
		}
                
		return $this->renderPartial('lowest_quotation',[
			'moduleInfo' => $ModuleInfo,
			'colors_nav' => $colors_nav,
			'moduleGoodsDetails' => $moduleGoodsDetails,
			'url' => $url,
                       // 'like'=>$like,
		]);
	}
        
        /*
         * @description : 导出当前城市最低报价   用户的当前城市报价
         * @author : lxzmy
         * @date : 2016-2-18 9:37
         */
        public function actionExportLowGoodsPrices(){
            if(isset($this->user_info)){
                $provice = $this->user_info['province'];
                $city = $this->user_info['city'];
                $user_id = $this->user_info['id'];
                $sql = "select t1.base_id 基础商品编号,t2.name 基础商品名称,t3.region_name 省份,t4.region_name 城市,t1.bottom_price 最低价  from goods_base_goods_city as t1 LEFT JOIN goods_base_goods as t2 on t1.base_id=t2.id LEFT JOIN other_region as t3 on t1.province=t3.region_id LEFT JOIN other_region as t4 on t1.city=t4.region_id where 1=1";
                if($provice){
                    $sql .= " and t1.province=".$provice;
                }
                if($city){
                    $sql .= " and t1.city=".$city;
                }
                $key = md5('51dh_news'.date('d', time()) * date('Y', time()));
                $url = file_get_contents(FAST_EXPORT_URL."/shell/mysql_excel3.php?word=".urlencode($sql)."&key=$key");
                header("Location:$url");
                ExportLog::createExportlog(\Yii::$app->user->id, '导出城市内商品最低价');
                exit();
            }
        }
        
       public function actionAddToDesktop () {
           
           $Shortcut = "[InternetShortcut]
                URL=http://www.51dh.com.cn/
                IDList=
                conFile=http://www.51dh.com.cn/images/appliance/logo.png
                IconIndex=1
                [{000214A0-0000-0000-C000-000000000046}]
                Prop3=19,2
               ";
           
            header("Content-Type:application/octet-stream");
            //header("Content-Disposition: attachment; filename=我要订货网.url;");
            
            // 获取用户浏览器
            $filename = '我要订货网.url';
            
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $encode_filename = rawurlencode($filename);
            
            // 不同浏览器使用不同编码输出
            if(preg_match("/MSIE/", $user_agent)){
                header('content-disposition:attachment; filename="'.$encode_filename.'"');
            }else if(preg_match("/Firefox/", $user_agent)){
                header("content-disposition:attachment; filename*=\"utf8''".$filename.'"');
            }else{
                header('content-disposition:attachment; filename="'.$filename.'"');
            }
            
        	echo $Shortcut;
        	
       }
    /*
     * @description : 获取用户未读消息
     * @author : zhanghy
     * @date : 2016年5月9日14:42:19
     */
    public function actionUnreadMessage(){
        $user_info = f_c('frontend-'.Yii::$app->user->id.'-new_51dh3.0');
        $count = SiteMessagesRelated::getUnReadMessageCountByUserId($user_info['id']);
        return json_encode($count);
    }
}
