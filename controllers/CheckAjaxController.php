<?php
namespace frontend\controllers;

use common\models\user\UserInvoice;
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use common\models\user\UserMember;
use common\models\goods\SupplierGoods;
use common\models\markting\SeckillGoods;
use common\models\goods\Photo;
use common\models\goods\BaseGoods;
use common\models\markting\SeckillActive;
use common\models\markting\SeckillOrder;
use common\models\markting\SeckillCity;
use common\models\goods\Unit;
use common\models\user\ReceiveAddress;
use common\models\other\OtherRegion;
use yii\base\Action;
use frontend\components\Controller2016;

class CheckAjaxController extends Controller2016{ 
	
	/**
	 * @description:设为默认地址  删除地址
	 * @return: 
	 * @author: sunkouping
	 * @date: 2015年11月3日上午9:38:20
	 * @modified_date: 2015年11月3日上午9:38:20
	 * @modified_user: sunkouping
	 * @review_user:
	*/
	public function actionUpaddress(){
		$type = $_POST['type'];
		$id = $_POST['id'];
		$user_id = \Yii::$app->user->id;
		if($type == 1) { //设为默认
			ReceiveAddress::updateAll(['status'=>'0'],'user_id=:user_id',[':user_id'=>$user_id]);
			ReceiveAddress::updateAll(['status'=>1],'id=:id',[':id'=>$id]);
		} else if($type == 2) { //删除
			ReceiveAddress::deleteAll('id='.$id);
		}
	}
	/**
	 * @description:
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年11月3日上午11:19:27
	 * @modified_date: 2015年11月3日上午11:19:27
	*/
	public function actionAddAddress(){
		$type = $_POST['type'];
		$id = $_POST['id'];
		$data = [];
		if($type == 0) {
			$data['title'] = '添加收货地址';
			$data['name'] = '';
			$data['id'] = 0;
			$data['provinces'] = OtherRegion::getAllProvince();
			$data['citys'] = [0=>'请选择'];
			$data['districts'] = [0=>'请选择'];
			$data['province'] = 0;
			$data['city'] = 0;
			$data['district'] = 0;
			$data['address'] = '';
			$data['phone'] = '';
		} else if($type == 1) {
			
			$data = ReceiveAddress::find()->where(['id'=>$id])->asArray()->one();
			$data['title'] = '编辑收货地址';
			$data['provinces'] = OtherRegion::getAllProvince();
			$data['citys'] = OtherRegion::getRegion($data['province']);
			$data['districts'] = OtherRegion::getRegion($data['city']);
		}
		return $this->renderAjax('/common/_address.php',[
				'data'=>$data,
		]);
	}
	/**
	 * @description:获取子区域
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年10月17日下午1:47:45
	 * @modified_date: 2015年10月17日下午1:47:45
	 * @modified_user: sunkouping
	 * @review_user:
	 */
	public function actionArea(){
		if(isset($_POST['id'])){
			$id = $_POST['id'];
		} else {
			$id = 1;
		}
		if($id){
			$province = OtherRegion::getSonRegion($id);
			return json_encode($province);
		}
	}
	/**
	 * @description:设置收货地址
	 * @return: return_type
	 * @author: sunkouping
	 * @date: 2015年11月3日下午3:26:05
	 * @modified_date: 2015年11月3日下午3:26:05
	 * @modified_user: sunkouping
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
				return 1;
			} else {
				return 0;
			}
		} else {
			return 2;
		}
	}

	/*
	 * @description:开票信息弹窗
	 * @author: jr
	 * @date:16-3-15
	 */
	public function actionAddInvoice(){
		$id = $_POST['id'];
		$data = [];
		if($id == 0) {
			$data['company_name'] = '';
			$data['taxpayer_identity'] = '';
			$data['register_address'] = '';
			$data['register_telephone'] = '';
			$data['bank'] = '';
			$data['account_number'] = '';
			$data['name'] = '';
			$data['provinces'] = OtherRegion::getAllProvince();
			$data['citys'] = [0=>'请选择'];
			$data['districts'] = [0=>'请选择'];
			$data['province'] = 0;
			$data['city'] = 0;
			$data['district'] = 0;
			$data['address'] = '';
			$data['phone'] = '';
			$data['id'] = 0;
		} else {
			$data = UserInvoice::find()->where(['id'=>$id])->asArray()->one();
			$data['provinces'] = OtherRegion::getAllProvince();
			$data['citys'] = OtherRegion::getRegion($data['province']);
			$data['districts'] = OtherRegion::getRegion($data['city']);
		}
		return $this->renderAjax('/common/__invoice.php',['data'=>$data]);
	}

	/**
	 * @description:添加、编辑开票信息
	 * @author: jr
	 * @date:16-3-15
	 */
	public function actionSetInvoice(){
		$id = f_post('id');
		if($id){
			$model = UserInvoice::find()->where(['id'=>$id])->one();
			$model->edit_time = date('Y-m-d H;i:s',time());
		}else{
			$model = new UserInvoice();
			$model->add_time = date('Y-m-d H;i:s',time());
		}
		$model->user_id = \Yii::$app->user->id;
		$model->type = 1;
		$model->company_name = f_post('company_name');
		$model->taxpayer_identity = f_post('taxpayer_identity');
		$model->register_address = f_post('register_address');
		$model->register_telephone = f_post('telephone');
		$model->bank = f_post('bank');
		$model->account_number = f_post('account_number');
		$model->province = f_post('province');
		$model->city = f_post('city');
		$model->district = f_post('district');
		$model->name = f_post('name');
		$model->phone = f_post('phone');
		$model->address = f_post('address');
		if($model->save()){
			return 1;
		}else{
			return -1;
		}
	}

	public function actionDelInvoice(){
		$id = f_post('id');
		$type = f_post('style'); //1为设默认  2为删除
		if($type == 2){
			UserInvoice::deleteAll('id='.$id);

		}else{
			$user_id = \Yii::$app->user->id;
			UserInvoice::updateAll(['status'=>'1'],'user_id=:user_id',[':user_id'=>$user_id]);
			UserInvoice::updateAll(['status'=>2],'id=:id',[':id'=>$id]);
		}
	}
}
