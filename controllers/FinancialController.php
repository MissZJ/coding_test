<?php
namespace frontend\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use common\models\other\TaskList;
use frontend\components\Controller2016;
use common\models\user\ShopServiceCity;
use common\models\user\UserMember;
use common\util\Aes;
use common\components\CommonInterface;
use common\util\Curl;

class FinancialController extends Controller2016
{
	public function actionIndex(){
		$userInfo=$this->user_info;
		$login_account = $userInfo['login_account'] ? $userInfo['login_account'] : '';
		//将用户加密 
        $e = new CommonInterface();
        $aesLogin_account = $e->encrypt($login_account,'5bhfd68e674j14de');
        $aesLogin_account = urlencode($aesLogin_account);
		//收益
		$profitInfo = UserMember::getProfitByApi();  
		//云贷链接
		$yundai = "http://yloan.51dh.com.cn/easy_loan_member/user/easyLoanLogin?loginName=".$login_account."&loginFrom=51DH&tokenStr=".md5('51DHBJDDH&SFH*M520#'.date('Ymd'));
		$yunPos = '';
		$isCredit = '';
		$amount = '';
		if(f_c('yunPos'.$login_account)){
			$url= "http://120.55.137.181:3002/easy_loan_member/isCredit.do";
			$data = [ 
				'token'=>md5('51DHBJDDH&SFH*M520#'.date('Ymd')),
				'loginName'=>$login_account,
			];
	        //调用接口，获取返回值
	        $commonInter = new CommonInterface();
	        $returnVal = $commonInter->yunInterface($url, $data);
	        if($returnVal['returnCode'] == '0000'){
	        	if($returnVal['returnData']['isCredit'] == 'no'){
	        		$amount = $returnVal['returnData']['totalAmount']; //总额度/可申请额度
	        		$isCredit ='no';
	        	}else{
	        		$amount = $returnVal['returnData']['availableAmount']; //可用额度
	        		$isCredit ='yes';
	        	}
	        }
	        $if_yunPos = 'yes';
		}else{
			f_c('yunPos'.$login_account,'yes');
			$if_yunPos = 'no';
		}
//f_d($yunPos);

		return $this->renderPartial('index2',['profitInfo'=>$profitInfo,
											'userInfo'=>$userInfo,
											'aesLogin_account'=>$aesLogin_account,
											'yundai' => $yundai,
											'isCredit' => $isCredit,
											'amount' => $amount,
											'if_yunPos' => $if_yunPos,
		]);
	}
	
	public function actionYCB(){
		return $this->renderPartial('yucunbao');
	}
}