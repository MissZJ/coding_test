<?php
namespace frontend\controllers;

use common\models\order\OmsInfo;
use common\models\user\MemberUpdate;
use common\models\user\LogisticsDistrict;
use common\models\user\Pusher;
use common\models\user\UserMember;
use common\models\user\WealthDetail;
use Yii;
use yii\base\Exception;
use yii\db\Query;
use yii\helpers\Json;
use yii\web\Controller;
use common\models\user\Logisticser;
use common\models\other\Log;
/**
 * Description: 巡店API接口控制器
 * User: wangmingcha
 * Date: 15/12/1
 * Time: 09:09
 * @property integer $logisticsId
 * @property string $offset
 * @property string $limit
 * @property integer $status
 * @property integer $startTime
 * @property integer $endTime
 * @property integer $userId
 * @property string $mark
 * @property string $mobile
 * @property string $userName
 * @property string $province
 * @property string $city
 * @property string $district
 * @property integer $level
 * @property integer $date
 * @property integer $shopName
 * @property integer $phone
 * @property integer $address
 * @property integer $password
 * @property integer $orderCode
 * @property integer $isUrgent
 */

class WlxundianInterfaceController extends Controller{
    public $requestContent;
    public $actionResult;

    public function beforeAction($action){
        if (parent::beforeAction($action)) {
            if($this->apiTokenAuthorized()){
                return true;
            }else{
                echo Json::encode($this->actionResult);
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @desc 重写__get魔术方法，为方便取出post过来的requestContent中得属性值
     * @param string $name
     * @return string
     */
    public function __get($name)
    {
        $ret = "";
        if(isset($this->requestContent[$name])){
            $ret =  $this->requestContent[$name];
        }
        return $ret;
    }

    public function  __set($name,$value){
        if(isset($this->requestContent[$name])){
            $this->requestContent[$name] = $value;
        }
    }

    public function __isset($name)
    {
        $ret = false;
        if(isset($this->requestContent[$name])){
            $ret =  true;
        }
        return $ret;
    }

    /**
     *  description: 接口token验证
     */
    protected function apiTokenAuthorized(){
        $time = Yii::$app->request->post('time');
        $token = Yii::$app->request->post('token');
        $this->requestContent = Json::decode(Yii::$app->request->post('content'));
        /*if(isset($_GET['test'])){
             $time = Yii::$app->request->post('time','20151203141842');
             $token = Yii::$app->request->post('token','0b38a40bb6cdece4bc35c35ee9a3c6a9');
             $content = [
                 'logisticsId'=>0,
               //  'city'=>'',
              //   'district'=>'1834',
                 'offset'=>0,
                 'limit'=>20,
                 'status'=>1,
                 'startTime'=>'',
                 'endTime'=>'',
                 'userId'=>37,
                 'userName'=>'',
                 'isUrgent'=>1,
                 'orderCode'=>'144582546229399',
                 'level'=>'1',
                 'date'=>'1',
                 'password'=>1234561,
                 'province'=>[17],
                 'city'=>[],
                 'district'=>[]
              //   'status'=>4,
                 //'shopName'=>'测试',
                 //'phone'=>'75',
             ];
             $this->requestContent = Json::decode(Yii::$app->request->post('content',json_encode($content)));
        }*/


        $encodeTime = md5(md5($time)."5bbfd68e674314de6775c6efb3ee9d02");
        if($token==$encodeTime){
            return true;
        }else{
            $this->actionResult['res'] = 104;
            $this->actionResult['msg'] = "token校验失败";
            return false;
        }
    }

    /**
     *  接口状态码
     */
    const SUCCESS = 0;
    const PARAM_EMPTY = 101;
    const DB_ERROR = 103;

    static $statusMap = [
        0 => '成功',
        101 => '请求参数为空',
        103 => '数据库操作失败'
    ];


    public function afterAction($action,$result){
        $result = parent::afterAction($action,$result);
        if(array_key_exists($result['res'],self::$statusMap) && (!isset($result['msg']))){
            $result['msg'] = self::$statusMap[$result['res']];
        }
        echo Json::encode($result);
        Yii::$app->end();
    }

    //判断是否为地推管理员权限  return  true|false
    protected function getIsRootPusher(){
        return isset($this->logisticserId) && $this->logisticserId === 0;
    }

    /**
     * @description : 重置推广密码接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionChangePusher(){
        if($this->logisticserId && $this->password){
            try{
                $model = Logisticser::find()->where(['id'=>$this->logisticserId])->one();
                if($model){
                    $model->password = md5($this->password);
                    if($model->validate(['phone','password','id'])){
                        $model->save(false);
                    }else{
                        throw new Exception('db attr unvalid');
                    }
                    $this->actionResult['res'] = self::SUCCESS;
                }else{
                    $this->actionResult['res'] = 111;
                    $this->actionResult['msg'] = "地推人员Id不存在";
                }
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }

    public function actionGetUser(){
        if(isset($this->offset) && isset($this->limit) && isset($this->status)){
            try{
                $query = new Query();
                $query ->select('id,user_name,user_status,district,shop_name')->from('user_member');

                //  地区条件
                if(isset($this->district) && $this->district){
                    $query->andWhere(['in','district',$this->district]);
                }elseif(isset($this->city) && $this->city){
                    $query->andWhere(['in','city',$this->city]);
                }elseif(isset($this->province) && $this->province){
                    $query->andWhere(['in','province',$this->province]);
                }else{}

                //  用户状态条件
                $query->andWhere(['user_status'=>$this->status]);


                // limit 条件
                $query->offset($this->offset);
                $query->limit($this->limit);

              //  echo $query->createCommand()->sql;exit;
                $userData = $query->all();
                $this->actionResult['count'] = $query->count();
                $this->actionResult['data'] = $userData;
                $this->actionResult['res'] = self::SUCCESS;
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }


    /**
     * @description : 获取用户列表接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionGetPushUser(){
        if(isset($this->logisticserId) && isset($this->offset) && isset($this->limit) && isset($this->status)){
            $district_arr = LogisticsDistrict::getDistrictBylogisticsId($this->logisticserId);
            if($district_arr){
                try{
                    $query = new Query();
                    $query ->select('id,user_name,user_status,district,shop_name')->from('user_member');

                    //  推广人员区域条件
                    $query->andWhere(['district'=>$district_arr]);

                    //  地区条件
                    if(isset($this->district) && $this->district){
                        $query->andWhere(['district'=>$this->district]);
                    }

                    //  用户状态条件
                    $query->andWhere(['user_status'=>$this->status]);

                    //*****  start    当startTime 和 endTime存在时，需要查询时间区间内没有交易的用户ids，作为query附加条件*/
                    if($this->startTime && $this->endTime){
                        $hasOrderUserId = OmsInfo::getUserIdsOrderByTime($this->startTime,$this->endTime);
                        if($hasOrderUserId)
                            $query->andWhere("id not in ($hasOrderUserId)");
                            $query->andWhere('<','reg_time',$this->startTime);
                    }
                    // *****  end

                    // limit 条件
                    $query->offset($this->offset);
                    $query->limit($this->limit);
                    $query->orderBy('reg_time desc');
                    $userData = $query->all();
                    $this->actionResult['count'] = $query->count();
                    $this->actionResult['data'] = $userData;
                    $this->actionResult['res'] = self::SUCCESS;
                }catch (Exception $e){
                    $this->actionResult['res'] = self::DB_ERROR;
                }
            }else{
                $this->actionResult['res'] = 200;
                $this->actionResult['msg'] = "地推人员对应区域为空";
            }

        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }

    /**
     * @description : 审核用户接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionSynchronizationSysUser(){
        if($this->status == 2 && !$this->mark){
            $this->actionResult['res'] = 201;
            $this->actionResult['msg'] = "审核不通过时mark字段参数必填";
        }elseif(!($this->userId && isset($this->status))){
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }elseif(!array_key_exists($this->status,UserMember::$userStatusMap)){
            $this->actionResult['res'] = 206;
            $this->actionResult['msg'] = "状态值status不符合要求";
        }else{
            try{
                $userModel = UserMember::find()->andWhere(['id'=>$this->userId])->one();
                if($userModel){
                    $district_arr = LogisticsDistrict::getDistrictBylogisticsId($this->logisticserId);
                    if($this->getIsRootPusher() || in_array($userModel->district,$district_arr)){
                        $userModel->user_status = $this->status;
                        $userModel->save();
                        //添加日志；
                        Log::addData([
                        	'foreign_key' => $userModel->id,
							'identity' => 'user_member',
							'operator' => '巡店',
							'content' => '状态修改为'.UserMember::getStatus($this->status),
                        ]);
                        if($this->mark){
                            MemberUpdate::add($userModel->id,urldecode($this->mark));
                        }
                        $this->actionResult['res'] = self::SUCCESS;
                    }else{
                        $this->actionResult['res'] = 203;
                        $this->actionResult['msg'] = "该logisticsId没有权限修改此userID信息";
                    }
                }else{
                    $this->actionResult['res'] = 202;
                    $this->actionResult['msg'] = "用户UserId不存在";
                }
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }
        return $this->actionResult;
    }

    /**
     * @description : 搜索用户接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionSearchUser(){
        if(($this->phone) || ($this->userName) || ($this->shopName) || ($this->getIsRootPusher() || $this->logisticserId)){
            try{

                $query = (new Query())->select('id,user_name,user_status,district,shop_name')->from('user_member');
                $query->andFilterWhere(['like','phone',$this->phone]);

                if(isset($this->userName)){
                    $this->userName = urldecode($this->userName);
                    $query->andFilterWhere(['like','user_name',$this->userName]);
                }

                if(isset($this->shopName)){
                    $this->shopName = urldecode($this->shopName);
                    $query->andFilterWhere(['like','shop_name',$this->shopName]);
                }

                if(!$this->getIsRootPusher()){
                    $district_arr = LogisticsDistrict::getDistrictBylogisticsId($this->logisticserId);
                    $query->andWhere(['district'=>$district_arr]);
                }
                $userData = $query->all();
                $this->actionResult['count'] = $query->count();
                $this->actionResult['data'] = $userData;
                $this->actionResult['res'] = self::SUCCESS;
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }

    /**
     * @description : 用户详情接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionGetUserMes(){
        if($this->userId){
            try{
                $query = (new Query())->select('id,user_name,store_type,user_status,district,level,exp_value,shop_name,login_account,month_sale_money,store_property,denied_num,address,phone')->from('user_member');
                /*if(!$this->getIsRootPusher()){
                    $district_arr = LogisticsDistrict::getDistrictBylogisticsId($this->logisticserId);
                    $query->andWhere(['district'=>$district_arr]);
                }*/
                $query->andWhere(['id'=>$this->userId]);
                $userData = $query->one();
                $userData['last_order_time'] = OmsInfo::getLastOrderTimeByUserId($this->userId);
                $this->actionResult['data'] = $userData;
                $this->actionResult['res'] = self::SUCCESS;
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }

    /**
     * @description : 重置用户密码接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionEditUserPwd(){
        if($this->userId && $this->password){
            try{
                $userModel =  UserMember::find()->andWhere(['id'=>$this->userId])->one();
                if($userModel){
                    $userModel->password = md5($this->password);
                    $userModel->save();
                    //添加日志；
                    Log::addData([
	                    'foreign_key' => $userModel->id,
	                    'identity' => 'user_member',
	                    'operator' => '巡店',
	                    'content' => '密码改为'.$this->password,
                    ]);
                    $this->actionResult['res'] = self::SUCCESS;
                }else{
                    $this->actionResult['res'] = 202;
                    $this->actionResult['msg'] = "用户UserId不存在";
                }
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }

    /**
     * @description : 查询推广人员所辖区县接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionGetPusherDistrict(){
        if($this->logisticserId){
            try{
                $district_arr = LogisticsDistrict::getDistrictBylogisticsId($this->logisticserId);
                if($district_arr){
                    $this->actionResult['data'] = $district_arr;
                    $this->actionResult['res'] = self::SUCCESS;
                }else{
                    $this->actionResult['res'] = 210;
                    $this->actionResult['msg'] = "推广人员并未配置推广区域";
                }
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }

    /**
     * @description : 查询推广人员所辖区县下用户接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionGetUserByDistrict(){
        if($this->district && isset($this->status)){
            try{
                $query = (new Query())->select('id,user_name,user_status,district,shop_name')->from('user_member');
                $query->andWhere(['district'=>$this->district]);
                $query->andWhere(['user_status'=>$this->status]);
                $userData = $query->all();
                $this->actionResult['count'] = $query->count();
                $this->actionResult['data'] = $userData;
                $this->actionResult['res'] = self::SUCCESS;
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }


    /**
     * @description : 查询推广人员发展用户流失信息
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionGetUserByPusher(){
        if(isset($this->logisticserId) && isset($this->level) && isset($this->date) && isset($this->offset) && isset($this->limit)){
            try{
                $str = "1=1";

                //推广人员地域
                if(!$this->getIsRootPusher()){
                    $district_arr = LogisticsDistrict::getDistrictBylogisticsId($this->logisticserId);
                    if($district_arr){
                        $str.= " and district in (".implode(',',$district_arr).")";
                    }else{
                        $str.= " and 1=0";
                    }
                }

                //date条件
                $time = time() - 24*3600*$this->date;
                $str .= " and add_time <='".date('Y-m-d H:i:s',$time)."'";

                $sql = "SELECT *
                            FROM (SELECT t1.user_id,MAX(t1.`add_time`) AS add_time,t2.user_name,t2.login_account,t2.shop_name,t2.province,t2.city,t2.phone,t2.level,t2.exp_value,t2.district
                            FROM user_wealth_detail AS t1
                            LEFT JOIN user_member AS t2 ON t1.user_id = t2.id
                            WHERE t1.type = 1 and t2.level = $this->level GROUP BY t1.`user_id`) AS a
                            WHERE $str";

                $user_count = WealthDetail::findBySql($sql)->asArray()->all();
                $this->actionResult['count'] = count($user_count);

                $sql .= " order by add_time asc limit ".$this->offset." , ".$this->limit;
                $this->actionResult['data'] = WealthDetail::findBySql($sql)->asArray()->all();

                $this->actionResult['res'] = self::SUCCESS;
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }


    /**
     * @description : 查询后台用户流失信息
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionGetLostUser(){
        if(isset($this->level) && isset($this->date) && isset($this->offset) && isset($this->limit)){
            try{
                $str = "1=1";

                //推广人员地域
                if(isset($this->district) && $this->district){
                    $str.= " and district in (".$this->district.")";
                }elseif(isset($this->city) && $this->city){
                    $str.= " and city in (".$this->city.")";
                }elseif(isset($this->province) && $this->province){
                    $str.= " and province in (".$this->province.")";
                }else{}

                //date条件
                $time = time() - 24*3600*$this->date;
                $str .= " and add_time <='".date('Y-m-d H:i:s',$time)."'";

                $sql = "SELECT *
                            FROM (SELECT t1.user_id,MAX(t1.`add_time`) AS add_time,t2.user_name,t2.login_account,t2.shop_name,t2.province,t2.city,t2.phone,t2.level,t2.exp_value,t2.district
                            FROM user_wealth_detail AS t1
                            LEFT JOIN user_member AS t2 ON t1.user_id = t2.id
                            WHERE t1.type = 1 and t2.level = $this->level GROUP BY t1.`user_id`) AS a
                            WHERE $str";

                $user_count = WealthDetail::findBySql($sql)->asArray()->all();
                $this->actionResult['count'] = count($user_count);

                $sql .= " order by add_time asc limit ".$this->offset." , ".$this->limit;
                $this->actionResult['data'] = WealthDetail::findBySql($sql)->asArray()->all();

                $this->actionResult['res'] = self::SUCCESS;
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }

    /**
     * @description : 巡店推广人员修改所辖区域内客户的资料
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionEditUserByPusher(){
        if($this->userId && $this->userName && $this->phone && $this->address){
            try{
                $userModel = UserMember::find()->andWhere(['id'=>$this->userId])->one();
                if($userModel){
                    $district_arr = LogisticsDistrict::getDistrictBylogisticsId($this->logisticserId);
                    if($this->getIsRootPusher() || in_array($userModel->district,$district_arr)){
                        $userModel->userName = urldecode($this->userName);
                        $userModel->address = urldecode($this->address);
                        $userModel->phone = $this->phone;
                        if(!$userModel->save()){
                            throw new Exception("db save fail");
                        }
                        $this->actionResult['res'] = self::SUCCESS;
                    }else{
                        $this->actionResult['res'] = 203;
                        $this->actionResult['msg'] = "该logisticsId没有权限修改此userID信息";
                    }
                }else{
                    $this->actionResult['res'] = 202;
                    $this->actionResult['msg'] = "用户UserId不存在";
                }
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }


    /**
     * @description : 获取订单详情接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionGetOrderInfo(){
        if($this->orderCode){
            try{
                $orderData = OmsInfo::find()->andWhere(['order_code'=>$this->orderCode])->asArray()->one();
                $this->actionResult['data'] = $orderData;
                $this->actionResult['res'] = self::SUCCESS;
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }


    /**
     * @description : 获取订单是否加急接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    static $orderStatusMap = [
        'success'=>[
            'status'=>[10,75,140,5,60,125],
        ],
        'error'=>[
            [
                'status'=>[35,96,160],
                'msg'=>'无理由拒签,加急失败'
            ],
            [
                'status'=>[100,165,40],
                'msg'=>'供应商原因拒签，加急失败'
            ],
            [
                'status'=>[45,105,170],
                'msg'=>'产品原因拒签，加急失败'
            ],
            [
                'status'=>[175,110,50],
                'msg'=>'其他原因拒签，加急失败'
            ],
            [
                'status'=>[137,63,15,130,65],
                'msg'=>'用户取消，加急失败'
            ],
            [
                'status'=>[135,70,20],
                'msg'=>'取货取消，加急失败'
            ],
            [
                'status'=>[55,120],
                'msg'=>'待付款，加急失败'
            ],
            [
                'status'=>[30,90,155],
                'msg'=>'订单已交易成功，加急失败'
            ],
        ]
    ];

    public function actionGetOrderIsUrgent(){
        if($this->orderCode && isset($this->isUrgent)){
            try{

                $model = OmsInfo::find()->andWhere(['order_code'=>$this->orderCode])->one();
                if($model){
                    if(in_array($model->order_status,self::$orderStatusMap['success']['status'])){
                        $model->is_urgent = $this->isUrgent;
                        $model->save();
                        $this->actionResult['res'] = self::SUCCESS;
                    }else{
                        foreach(self::$orderStatusMap['error'] as $k=>$orderList){
                            if(in_array($model->order_status,$orderList['status'])){
                                $this->actionResult['res'] = 150+$k;
                                $this->actionResult['msg'] = $orderList['status'];
                                break;
                            }
                        }
                        if(!isset($this->actionResult['res'])){
                            $this->actionResult['res'] = 199;
                            $this->actionResult['msg'] = "订单order_status未知状态";
                        }
                    }
                }else{
                    $this->actionResult['res'] = 110;
                    $this->actionResult['msg'] = "订单号不存在";
                }
            }catch (Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;

            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }
}