<?php
namespace frontend\controllers;

use Yii;
use frontend\components\Controller2016;
use common\models\api\LogisticsInterfaceLog;
use common\util\Aes;
use common\models\user\UserMember;

class RetrieveController extends Controller2016{ 
	/**
	 * 
	 * @Title: actionYfb
	 * @Description: 预付宝自动登录
	 * @return: \yii\web\Response|Ambigous <string, string>
	 * @author: yulong.wang
	 * @date: 2015-12-1下午12:03:58
	 */
    public function actionYfb() {
        $userInfo = $this->user_info;
        if (empty($userInfo)) {
            return $this->redirect('/site/login');
        }
        $back_url = isset($_GET['rand'])&&!empty($_GET['rand']) ? base64_decode($_GET['rand']) : 'site/index';
        $login_account = $userInfo['login_account'];
        $sign = LogisticsInterfaceLog::encrypt_yfb($login_account,$back_url);
        return $this->render('yfb',[
            'login_account' => $login_account,
            'sign' =>$sign,
        	'back_url'=>$back_url
        ]);
    }

    /**
     * @desc 51E卡登录
     * @return \yii\web\Response|Ambigous <string, string>
     */
    public function actionEka() {
    	$userInfo = $this->user_info;
    	if (empty($userInfo)) {
    		return $this->redirect('/site/login');
    	}
    	$back_url = 'account/deposit-qdd';
    	$login_account = $userInfo['login_account'];
    	$sign = LogisticsInterfaceLog::encrypt_yfb($login_account,$back_url);
    	return $this->render('yfb',[
    			'login_account' => $login_account,
    			'sign' =>$sign,
    			'back_url'=>$back_url
    	]);
    }
    
    /**
     * @desc 51金融账单分期
     * @return \yii\web\Response|Ambigous <string, string>
     */
    public function actionStage() {
    	$userInfo = $this->user_info;
    	if (empty($userInfo)) {
    		return $this->redirect('/site/login');
    	}
    	$token = 'mNfVEaraoIw9YHu2';
    	$content['login_name'] = $userInfo['login_account'];
    	$timestamp = time();
    	$sign = md5(md5($timestamp).$token);
    	$login_name = json_encode($content);
    	return $this->render('stage',[
    			'login_name' => $login_name,
    			'sign' =>$sign,
    			'timestamp'=>$timestamp
    	]);
    }
	
    /**
      * @Title:actionErchuan
      * @Description:跳转增值服务
      * @return:redirect
      * @author:huaiyu.cui
      * @date:2015-12-9 上午10:54:04
     */
    public function actionErchuan() {
        header("Content-type: text/html; charset=utf-8");
        
        $fwd = $_GET['fwd'];
        
        //获取用户信息
        $userInfo = $this->user_info;
        if(!$userInfo || empty($userInfo)) {
            return $this->redirect('/site/login');
        }
        
        $uid = $userInfo['id'];//用户id
        $aesOpenId = f_open_id($uid);//用户openid
        $fromModel = f_isMobile() ? 1 : 0; //是否手机端
        switch ($fwd){
            //act是一个2.0起的标志  rand是3.0传送open_id的参数名，原则：变量名没有任何意义
            case 1:
                //跳到kc
                header("Location: http://fw.51dh.com.cn/index.php?act=sj&rand=".urlencode($aesOpenId)."&fromModel=".urlencode($fromModel));
                exit;
                break;
            case 2:
                
                //跳到bbs
                $time = time();
                $secret = 'mNfVEaraoIw9YHu2';
                $fromModel = '51';
                //web.51dh.com/retrieve/erchuan?fwd=2
                $token = md5($secret.md5($fromModel).md5($time).$secret);
                header("Location: http://bbs.51dh.com.cn/site/login?rand=".urlencode($aesOpenId)."&fromModel=".urlencode($fromModel)."&time=".$time."&token=".$token);
                exit;
                break;
         }
    }
    
    
    
    /**
     * 
     * @Title: actionESteward
     * @Description: e管家登录接口
     * @return: \yii\web\Response|Ambigous <string, string>
     * @author: yulong.wang
     * @date: 2016-2-23下午5:06:01
     */
    public function actionESteward(){
        $userInfo = $this->user_info;
        $userMenber = UserMember::find()->where(['id'=>$userInfo['id']])->asArray()->one();
        if (!empty($userMenber)){
            $pwd = $userMenber['password'];
        } else {
            $pwd = '';
        }
        if (empty($userInfo)) {
            return $this->redirect('/site/login');
        }
        $timestamp = date('Y-m-d H:i:s',time());
        $sid = '8a808038529a5d4201529abd5df20004';
        $sign = LogisticsInterfaceLog::eSteward($userInfo['login_account'],$pwd,$timestamp,$sid);
        return $this->render('e_steward',[
            'login_account' => $userInfo['login_account'],
            'pwd' =>$pwd,
            'timestamp' => $timestamp,
            'sid' => $sid,
            'sign' => $sign,
        ]);
    }
    /**
     *
     * @Title: actionYd
     * @Description:云店跳转处理
     * @author: xin.zhang
     */
    public function actionYd()
    {
        $userInfo =UserMember::findOne($this->user_info['id']);
        $password = $userInfo['password'];
        $account_id = $userInfo['login_account'];
        $sid = 'd2b739a728a844acdaa1d75ecdd789d7';
        $timestamp = date('Y-m-d H:i:s',time());
//        $timestamp = '2016-05-23 13:46:31';
        $sign = LogisticsInterfaceLog::eSteward($account_id,$password,$timestamp,$sid);

        return $this->render('yd', [
            'password' => $password,
            'account_id' => $account_id,
            'sign' => $sign,
            'sid' => $sid,
            'timestamp'=>$timestamp
        ]);
    }
    /**
     * @description:云店增值，二跳转
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年3月4日下午5:45:43
     * @modified_date: 2016年3月4日下午5:45:43
     * @modified_user: sunkouping
     * @review_user:
    */
    public function  actionYdzz(){
    	$userInfo = $this->user_info;
    	$mallId = f_get('mall_id',1);
    	header("Location: http://10000dp.com/zengzhi/index.html?dhaccount=".$userInfo['login_account']."&mallId=".$mallId);
    	exit;
    	break;
    }
    
    /**
    * @description:跳转到商学院自动登录
    * @author:huaiyu.cui
    * @date:2016-6-7 下午2:51:59
    */
    public function actionBusinessInstitute() {
        header("Content-type: text/html; charset=utf-8");
    
        //获取用户信息
        $userInfo = $this->user_info;
        if(!$userInfo || empty($userInfo)) {
            return $this->redirect('/site/login');
        }
    
        $uid = $userInfo['id'];//用户id
        $open_id = (f_open_id($uid));//用户openid
         
        //将用户openidAES加密
        $aes = new Aes();
        $aes->setKey('vwMxr5tny3b85FVo'); //密码生成器生存token
        $aesOpenId = $aes->aesEncrypt($open_id);//AES加密之后生存的open_id
        
        //urlecnode
        $aesOpenId = urlencode($aesOpenId);
        
        header('Location: http://study.51dh.com.cn?rand='.$aesOpenId);
        exit;
    }
    
}
