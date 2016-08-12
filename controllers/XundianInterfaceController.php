<?php
namespace frontend\controllers;

use common\models\order\OmsInfo;
use common\models\user\MemberUpdate;
use common\models\user\PushDistrict;
use common\models\user\Pusher;
use common\models\user\StoreMall;
use common\models\user\UserApplyMall;
use common\models\user\UserMember;
use common\models\user\UserStoreType;
use common\models\user\WealthDetail;
use common\models\user\ScanActive;
use common\models\user\ScanActiveGoods;
use common\models\user\ScanActiveCity;
use Yii;
use yii\base\Exception;
use yii\db\Query;
use yii\helpers\Json;
use yii\web\Controller;
/**
 * Description: 巡店API接口控制器
 * User: wangmingcha
 * Date: 15/12/1
 * Time: 09:09
 * @property integer $pusherId
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
 * @property integer $applyId
 * @property integer $store_type
 * @property integer $store_property
 */

class XundianInterfaceController extends Controller{
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
                 'pusherId'=>0,
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
    const SAVE_FAIL = 99;

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
        return isset($this->pusherId) && $this->pusherId === 0;
    }

    /**
     * @description : 重置推广密码接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2015.12.1 15:02:11
     */
    public function actionChangePusher(){
        if($this->pusherId && $this->password){
            try{
                $model = Pusher::find()->where(['id'=>$this->pusherId])->one();
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
        ini_set('memory_limit', '1024M');
        if(isset($this->pusherId) && isset($this->offset) && isset($this->limit) && isset($this->status)){
            $district_arr = PushDistrict::getDistrictByPusherId($this->pusherId);
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
        if($this->status == 3 && !$this->mark){
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
                    $district_arr = PushDistrict::getDistrictByPusherId($this->pusherId);
                    if($this->getIsRootPusher() || in_array($userModel->district,$district_arr)){
                        $userModel->user_status = $this->status;
                        $userModel->save();
                        if ($this->mark) {
                            MemberUpdate::addForXd($userModel->id,$this->pusherId,$this->status,$this->mark);
                        } else {
                            MemberUpdate::addForXd($userModel->id,$this->pusherId,$this->status);
                        }
                        $this->actionResult['res'] = self::SUCCESS;
                    }else{
                        $this->actionResult['res'] = 203;
                        $this->actionResult['msg'] = "该pusherId没有权限修改此userID信息";
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
        if(($this->phone) || ($this->userName) || ($this->shopName) || ($this->getIsRootPusher() || $this->pusherId)){
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
                    $district_arr = PushDistrict::getDistrictByPusherId($this->pusherId);
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
                $query = (new Query())->select('id,user_name,user_status,district,level,exp_value,shop_name,login_account,month_sale_money,store_property,denied_num,address,phone,store_type')->from('user_member');
                if(!$this->getIsRootPusher()){
                    $district_arr = PushDistrict::getDistrictByPusherId($this->pusherId);
                    $query->andWhere(['district'=>$district_arr]);
                }
                $query->andWhere(['id'=>$this->userId]);
                $userData = $query->one();
                $userData['last_order_time'] = OmsInfo::getLastOrderTimeByUserId($this->userId);
                $userData['store_type_info'] = [];
                if(isset($userData['store_type'])){
                    $userData['store_type_info'] = StoreMall::getMallListByStoreType($userData['store_type']);
                }
                $userData['apply_info'] = UserApplyMall::getUserAllApplyMallList($this->userId);
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
     * @description : 更新用户申请副营接口
     * @return : mixed
     * @author : wangmingcha
     * @date :  2016.2.1 15:02:11
     */
    public function actionUpdateUserApplyAvo(){
        if($this->userId && $this->applyId && $this->status && $this->pusherId){
            try{
                if(array_key_exists($this->status,UserApplyMall::$statusMap)){
                    $userModel = UserApplyMall::find()->andWhere(['id'=>$this->applyId])->one();
                    if($userModel){
                        $userModel->audit_name = Pusher::getPusherName($this->pusherId);
                        $userModel->status = $this->status;
                        $userModel->audit_time = date('Y-m-d H:i:s');
                        $userModel->save();
                $this->actionResult['res'] = self::SUCCESS;
                    }else{
                        $this->actionResult['res'] = 202;
                        $this->actionResult['msg'] = "查找不到相应申请记录，无法更新";
                    }
                }else{
                    $this->actionResult['res'] = 223;
                    $this->actionResult['msg'] = "状态不正确";
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
        if($this->pusherId){
            try{
                $district_arr = PushDistrict::getDistrictByPusherId($this->pusherId);
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
        if(isset($this->pusherId) && isset($this->level) && isset($this->date) && isset($this->offset) && isset($this->limit)){
            try{
                $str = "1=1";

                //推广人员地域
                if(!$this->getIsRootPusher()){
                    $district_arr = PushDistrict::getDistrictByPusherId($this->pusherId);
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
                    $district_arr = PushDistrict::getDistrictByPusherId($this->pusherId);
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
                        $this->actionResult['msg'] = "该pusherId没有权限修改此userID信息";
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
            'status'=>[5,10,25,85,100,110,175,190,200,610,625,635],
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

    //获取店铺类型
    public function actionGetStoreTypeList(){
        try{
            $data = UserStoreType::find()->select('id,name')->asArray()->all();
            if($data){
                $this->actionResult['res'] = self::SUCCESS;
                $this->actionResult['data'] = $data;
            }
        }catch (\yii\db\Exception $e){
            $this->actionResult['res'] = self::DB_ERROR;
        }
        return $this->actionResult;
    }

    //更新用户店铺性质相关信息
    public function actionUpdateUserStore(){
        if($this->userId && $this->store_type && isset($this->store_property)){
            $userModel = UserMember::find()->andWhere(['id'=>$this->userId])->asArray()->one();
            $updateAttr = ['store_type'=>$this->store_type];
            if($this->store_type==1){
                $updateAttr['store_property'] = $this->store_property;
            }
            try{
                $rs = UserMember::updateAll($updateAttr,'id=:id',[':id'=>$this->userId]);
                if($rs){
                    if($userModel['store_type']!=$this->store_type){
                        UserApplyMall::deleteAll('user_id=:user_id',[':user_id'=>$this->userId]);
                    }
                    $this->actionResult['res'] = self::SUCCESS;
                }else{
                    $this->actionResult['res'] = self::SAVE_FAIL;
                }
            }catch (\yii\db\Exception $e){
                $this->actionResult['res'] = self::DB_ERROR;
            }
        }else{
            $this->actionResult['res'] = self::PARAM_EMPTY;
        }
        return $this->actionResult;
    }
    //巡店获取当前所有生效的扫码活动配置信息
    public function actionGetScanActiveinfo(){
        try{
            $active_info = ScanActive::find()->select('id,tips,active_name,begin_time,end_time,status,type,coupon_id')
            ->where(['status'=>1])->andWhere(['>=','end_time',f_date(time())])->asArray()->all();
            if($active_info){
                foreach($active_info as $value){
                    $active_id = $value['id'];
                    $this->actionResult['data'][$active_id]['active_info'] = $value;//活动配置信息
                    $goods_info = (new Query())->select('t1.goods_id,t2.goods_name')
                        ->from("{{user_scan_active_goods}} as t1")
                        ->leftJoin("{{goods_supplier_goods}} as t2","t1.goods_id = t2.id")
                        ->andFilterWhere(['t1.active_id'=>$active_id])
                        ->all();
                    $this->actionResult['data'][$active_id]['goods_info'] = $goods_info;//活动商品信息
                    $this->actionResult['data'][$active_id]['city_info'] = ScanActiveCity::find()->select('city')->where(['active_id'=>$value['id']])->asArray()->column();//活动城市信息
                }
                $this->actionResult['res'] = self::SUCCESS;
            }else{
                $this->actionResult['res'] = 202;
                $this->actionResult['msg'] = "当前暂无活动";
            }
        }catch (Exception $e){
            $this->actionResult['res'] = self::DB_ERROR;
        }
        return $this->actionResult;
    }



    /**
     *
     * @Title: actionGetYDOrder
     * @Description: 获取云店盒子订单销量以及销售金额
     * @return: return_type
     * @author: yulong.wang
     * @date: 2016-07-21下午1:27:40
     */
    public function actionGetYDOrder() {
        if ($this->start_time && $this->end_time) {
            try {
                $yd_goods = [417923, 416965, 382552, 382551, 382537, 382211, 363316, 92915, 87543, 74402, 73040, 72526, 71146, 70791];
                $orderList = (new Query())->select('t2.goods_num,t2.goods_price')->from('order_oms_info as t1')->leftJoin('order_oms_goods as t2', 't1.id=t2.order_id')->where(['t2.goods_id' => $yd_goods])->andWhere(['>=', 't1.order_time', $this->start_time])->andWhere(['<=', 't1.order_time', $this->end_time])->all();
                if (!empty($orderList)) {
                    $total_num = 0;
                    $total_price = 0;
                    foreach ($orderList as $val) {
                        $total_num += $val['goods_num'];
                        $total_price += $val['goods_num'] * $val['goods_price'];
                    }
					$this->actionResult['data']['total_price'] = $total_price;
                    $this->actionResult['data']['total_num'] = $total_num;
                    $this->actionResult['res'] = self::SUCCESS;
                } else {
                    $this->actionResult['res'] = 104;
                    $this->actionResult['msg'] = "无数据";
                }
            } catch (Exception $e) {
                 $this->actionResult['res'] = self::DB_ERROR;
            }
            return $this->actionResult;
        }
    }

    /**
     * @Title:actionGetPusherTask
     * @Description:获取地推人员业绩排名统计
     * @return: return_type
     * @author: xj
     * @date:2016-8-8 15:45:32
     */
    public function actionGetPusherTask(){
        $su_xiaomi = [538077,538076,538075,538074,538073,538072,538071,538070,538069,518957,518892,518891,518890,401717,286966,286964];

        $su_youp = [538789,538316,538315,538185,538184,538183,538182,538181,538180,538179,538178,538066,538065,538064,534405,534402,534401,534400,533866,533865,533864,528058,525200,514833514360,514359,513045,513044,506268,506267,506053,506051,506050,506048,506047,506045,506044,506042,505142,505140,505139,505137,505136,505134,505133,505131,504925,504923,504922,504921,504920,504919,504917,504706,504702,504700,504699,504698,504695,504694,504693,504692,504691,504690,504688,504687,504685,504684,504682,504680,504679,504677,504676,504674,504673,504672,504671,504670,504669,504668,504666,504665,504663,504662,504660,504659,504016,504013,503964,503961,503960,503957,503956,503953,502883,502882,502881,502880,502879,502853,502852,501507,501506,501503,501502,501499,501498,501495,501494,491512,491511,491476,491475,423092,423084,422084,422083,418831,418830,417498,417497,417496,417495,417493,417492,417491,417490,417489,417488,417487,417486,416574,416573,412513,412512,410558,410557,410556,398523,398522,396328,396327,389827,389826,367629,367628,367627,367626,362073,362072,360888,360887,353548,353547,353546,341680,341679,321332,313671,313670,306965,306964,176173,118280,118279,118278,542122,542121,542120,542119,542117,542115,541727,541726,541725,541724,541723,540831,540830,540829,540828,540827,540826,540825,540824,540431,540430,540429,540428,540427,540426,540425,540424,539353,539352,539351,539350,539349,539313,539312,539311,539310,539309,539308,539175,539174,539173,539172,539165,539164,539123,539122,539121,539120,539119,538956,538955,538954,538953,538952,538862,538861,538860,538859,538858,538857,538856,538855,538854,538853,538852,538851,538844,538843,538778,538777,538776,538775,538774,538773,538772,538754,538168,537560,537559,537558,537557,537556,537555,537554,535831,535830,535829,535828,535825,535824,535823,535822,535821,535820,535819,535818,535817,535816,535815,535814,535813,535812,535811,535810,535809,535808,535807,535499,535498,535497,535496,534830,534781,534502,534501,534500,534497,534496,534111,534055,534054533917,533916,533831,533830,533763,533762,533761,533760,533743,533742,533741,533740,533739,533738,533737,533736,533735,533427,533415,533414,533413,533345,533344,533343,533337,533336,533335,533134,533133,533132,533131,533130,533129,533128,532999,532998,532997,532996,532995,532994,532993,532992,532991,532990,530189,530188,529251,529250,529122,529121,529112,528515,528514,528513,528061,528060,528059,528058,528057,528056,528035,528034,527977,527923,527922,527921,527550,527549,527548,527547,527546,527545,527544,527543,527542,527541,527540,525983,525982,525981,525980,525979,525978,525977,525976,525890,525869,525868,525867,525866,524527,524526,524525,524281,524280,524279,524184,524183,524182,523901,523900,523899,523166,523165,522850,522849,522848,522847,522845,522844,522843,522842,522841,522840,522839,522837,522836,522835,522834,522833,522831,522830,522829,522828,522827,522826,522825,522823,522127,522126,521646,521645,521311,521310,521161,521133,521132,521120,521119,521118,521117,521116,521061,521060,521059,521058,521052,521051,521041,521040,521037,521036,520985,520984,520983,520982,520981,520956,520955,520954,520953,520952,520937,520936,520935,520934,520912,520911,520441,520439,520438,520437,520436,520435,520434,520433,520432,519791,519790,519789,519788,519787,519238,519237,519236,519235,519234,519215,519214,519213,519212,519211,519210,519209,519201,519200,519199,519198,519197,519196,519195,519194,519177,519176,519175,519174,519173,519172,519171,519168,519167,519166,519156,519155,519154,519111,519110,519109,519108,519107,519106,519105,519094,519053,519052,518764,518763,518762,518761,518760,518759,518758,518757,518756,518755,518753,518752,518751,518744,518735,518734,518669,518668,518667,518666,518665,518664,518663,518662,518623,518622,518573,518572,518563,518562,518545,518544,518543,518542,518541,518540,518539,518538,518537,518536,518535,
            518534,518533,518532,518531,518522,518517,518516,518515,518514,518513,518512,518511,518510,518508,518507,518505,518504,518503,518502,518501,518500,518495,518416,518355,518314,518313,518312,515570,515569,515568,515567,515566,515565,515564,515563,515562,515452,515451,515450,515449,515391,515390,515389,515388,515387,515386,515385,515384,515383,515382,515381,515380,515370,515237,515236,515235,515234,515233,515232,515231,515230,515229,515228,515227,515226,515225,515224,515223,515222,515221,515220,515219,515212,515211,515210,515209,515208,515207,515206,515205,515065,515064,514674,514673,514637,514636,514360,514359,514357,514356,514355,514354,514353,514217,
            514216,514215,514028,514027,514026,514025,514023,514022,514021,513931,513925,513924,513923,513922,513921,513427,513426,513425,513424,513114,513113,513091,513090,513045,513044,513043,513042,512640,512627,512626,512625,512623,512622,512621,512620,512619,512618,512617,512616,512615,512614,512613,512612,512611,512610,512609,512608,512606,512605,512604,512603,512602,512601,512600,512599,512598,512597,512596,512594,512593,512592,512591,512590,512589,512588,512587,512586,512585,512584,512583,512582,512581,511956,511955,511954,511951,511950,511949,511941,511940,511915,511914,511913,511912,511911,511910,511909,511908,511907,511906,511905,511904,511202,511156,511155,511154,511146,511145,511144,511143,511142,511141,511140,511139,511138,511137,511136,511135,511134,511133,511132,511131,511036,511035,511034,511033,511032,511031,511030,511029,511028,511027,511026,511025,510789,510788,510787,510786,510778,510777,510776,510775,509403,509402,509401,509400,509399,508915,508914,508885,508884,508883,508882,508881,508880,508879,508878,508662,508661,508660,508659,508657,508656,508654,508653,508651,508650,508649,508647,508646,508645,508643,508642,508641,508626,508625,508620,508619,508618,508617,508616,508169,508168,508167,508107,508106,507459,507458,507434,506924,506923,506268,506267,506262,506261,506137,506136,506033,506032,506031,506030,506029,506028,506019,505078,505077,505076,504100,503682,503681,503068,503067,502778,502777,502776,502775,502762,502755,502754,502748,502747,502746,502738,502737,502701,502700,501405,500630,500629,500628,500627,500626,499041,494383,494382,493926,493667,493666,491512,491511,491476,491475,491462,489514,489513,489512,489511,489510,489509,487633,487632,486596,486595,486580,486579,486578,486577,486555,486554,483956,483955,482176,482175,482174,482173,482172,481323,481322,481321,481320,449620,449619,449618,449617,449616,423488,423487,423486,423485,423087,423086,423082,423081,423080,423079,423078,423077,423076,423075,423074,423073,423072,423071,422637,422636,422635,422085,422084,422083,421815,421814,421776,421775,421774,421773,421772,421247,421246,421245,421244,421243,421183,421182,421181,420824,420823,420822,420821,420613,420612,420611,420610,420608,420606,420605,420598,420593,420592,420565,420561,420560,420559,419934,419933,419932,419931,419930,419929,419907,419906,419904,419903,419902,419901,419900,419228,419227,419226,419225,418949,418948,418947,418946,418945,418944,418943,418942,418941,418940,418939,418938,418937,418936,418935,418934,418933,418932,418931,418930,418929,418928,418926,418925,418924,418923,418922,418921,418920,418919,418918,418917,418916,418915,418914,418913,418912,418911,418910,418909,418908,418907,418906,418905,418904,418903,418902,418901,418900,418899,418898,418897,418896,418831,418830,418829,417575,417574,417572,417570,417568,417178,417177,417175,417172,417169,417167,417166,417165,416957,416956,416785,416764,416763,416762,416761,416760,416441,416440,416379,416345,416343,416341,416267,416266,416102,416101,416100,416099,416098,416097,416096,416095,416094,416093,416092,416091,416090,416089,416088,416087,416086,416085,416084,416083,416082,416081,416080,416079,416078,416077,416076,416075,416074,416073,416072,416071,416070,416069,416068,416067,416066,415627,415492,415491,415490,415485,415484,415483,414900,414899,414803,414802,414801,414800,414799,414798,414794,414793,414792,414791,414790,414789,414788,414787,414786,414785,414784,414783,414782,414777,414776,414775,414774,414773,414772,414771,414770,414769,414308,414307,414306,414305,414304,414303,414302,414301,414300,414128,413977,413976,413975,413790,413789,413788,413219,413218,412853,412852,412655,412654,412653,412633,412632,412631,412511,412502,412501,412476,412475,412298,412297,412296,412295,412294,410605,410604,410603,409839,409838,409837,409836,409835,408225,408207,408206,408205,408204,407032,406273,406272,406271,405947,405943,405942,402481,402480,402479,401841,401798,401797,401794,401793,401458,401297,401155,400444,400443,400442,400268,400267,400266,400229,399356,399355,399354,399353,398768,398767,398766,398765,398764,398763,398762,398761,398760,397552,397551,397550,397549,396424,395821,395819,395815,395313,395260,395232,395062,395061,395060,394979,394379,393757,393515,393514,393513,393512,392903,391808,391807,391768,391767,391766,391765,391764,391763,391762,391761,391743,391742,390846,390753,390739,390738,390722,387286,387285,387246,387226,387225,387180,387179,387133,387132,387131,386833,386832,386831,386830,386662,386661,385485,385484,385483,385482,385286,385285,385272,385271,385270,384686,384672,384671,384670,383031,383028,383016,383015,383014,382754,382753,382752,382751,382750,382550,382549,382548,382547,382546,382545,382544,382543,382023,382022,380486,380485,380484,380483,380459,380458,380457,380392,380391,380390,380389,380305,380304,380303,380302,380189,380188,380187,380074,380073,380072,380071,379844,378429,378428,378427,378426,378425,378424,378423,378422,378338,378327,377909,377889,377888,377887,377886,377869,377868,377867,377866,377865,377864,377863,377862,377861,377860,377791,377790,377789,377788,377781,377780,377779,377778,377777,377776,377775,377774,377758,377757,377756,377747,377693,377692,377691,377690,377689,377688,377687,377686,377678,377677,377676,377670,377669,377668,377667,377666,377665,377664,377663,377662,377661,377660,377659,377658,377657,377656,377655,377654,377653,377650,377649,377648,377643,377642,377641,377636,377635,377634,377633,377632,377631,377625,377624,377623,377622,377621,377620,377619,377616,377613,376873,376872,376869,376868,376867,376866,376865,376654,376418,376417,376416,375890,375889,375888,375538,375537,375536,375535,375534,375533,375478,375477,375457,375456,374818,374817,374796,374750,374735,374731,374730,374729,374728,374727,374726,374725,374714,373964,373963,373089,373088,373087,373086,373085,373084,373081,371580,371579,371578,371328,371327,371326,370902,370901,370900,370899,370898,370897,370896,370895,370833,370832,370831,370554,370537,370506,370505,370504,370503,370502,370501,370449,370448,370447,370446,370445,370444,370443,370442,370371,370370,370369,370368,370367,370366,370364,370351,370350,370332,370331,370330,370329,370328,370314,370313,369453,369452,369204,369203,369202,369201,369200,369199,369198,369197,369168,369167,369163,369162,366049,365200,365199,365198,364583,364582,364581,364580,363672,363671,363670,363631,363308,363307,363306,363305,363303,363302,363301,363300,363299,363298,363297,363296,363295,362615,362611,362610,362609,362542,356176,356175,356139,356132,356131,355720,355719,355601,355600,355467,355466,354987,354986,354985,354984,354983,353474,353473,353472,353471,353470,353469,353468,353325,353324,353323,353317,353316,353261,353260,353259,353258,353249,353248,353247,353246,351811,351806,349727,349726,349725,349332,349331,349330,349329,349328,349327,349326,349325,349324,349323,349322,349321,349320,349319,349318,349317,349316,349315,349314,349313,349312,349311,349310,349309,349308,349307,349306,349305,349304,349303,349302,349301,349300,349299,349298,349297,349295,349294,349293,349292,349291,349290,349289,349288,349287,349286,349285,349284,349283,349282,349281,349280,349279,349278,349277,349276,349275,349274,349273,349272,349271,349270,349269,349268,349267,349173,349172,349171,349170,349169,349168,349167,349166,349165,349164,349163,349162,349161,349160,349159,349158,349157,349156,349155,349154,349118,349117,349098,349092,347162,347161,347160,347159,347158,347157,347156,347155,347154,347153,345852,345851,345810,345809,345808,345807,345806,345805,345804,345803,345802,345801,345738,344714,344713,344712,344711,344710,344709,344708,344707,344706,344705,344704,344703,344702,344701,344700,344699,344210,344209,344208,344207,344206,344205,344204,344203,344202,344201,344200,344199,344198,344197,344196,344195,344194,344193,344192,344191,344190,344189,344188,344187,344186,344185,344184,344183,344182,344181,344180,344179,344178,344177,344176,344175,341944,341943,341942,341889,339833,339823,339593,339591,339590,339583,339578,339577,339572,339552,339550,339103,339102,339101,339100,339076,339075,339074,339064,339043,339042,339041,339040,338295,337991,337990,337989,337988,337987,337986,337985,337594,336554,336553,336552,336549,336548,336540,336535,336534,336533,336526,336525,336523,335534,335533,335532,335531,335530,335529,335528,335527,335526,335525,335524,335516,335515,335513,335512,335511,335284,334206,334205,334177,333477,333476,333475,331195,331194,331193,331192,331094,331093,331092,331091,331089,331088,331087,330872,330871,330772,330771,330770,330082,330081,330080,330079,330078,326594,326593,326592,325383,325382,325173,325172,325171,325170,325169,325168,325167,325166,325165,325164,325163,325162,325161,325160,325159,325158,325157,325142,325141,325140,325139,325138,325129,325128,325127,325126,325125,325124,325123,325122,325121,325120,325003,325002,325001,325000,324999,324998,324997,324996,324995,324994,324993,324992,324991,324990,324989,324988,324987,324986,324985,324984,323411,323298,323249,323248,323184,323178,323169,323154,323117,323116,323115,322146,322145,322144,322143,322142,322141,322140,322139,322138,322137,322136,322135,322132,322131,322130,322129,322128,322127,322126,322099,322017,322016,322015,322014,322013,322012,322011,322010,322009,322008,322007,322006,322005,322004,322003,322002,322001,322000,321979,321978,321977,321976,321975,321974,321973,321972,321971,321970,321969,321968,321967,321966,321965,321964,321963,321962,321961,321960,321959,321938,321937,321936,321935,321934,321933,321932,321931,321930,321929,321928,321927,321926,321925,321924,321923,321922,321921,321920,321919,321918,321917,321916,321915,321914,321913,321912,321911,321910,321909,321908,321907,321906,321905,321904,321903,321902,321901,321900,321899,321898,321897,321896,321895,321894,321893,321892,321891,321890,321889,321888,321887,321886,321885,321884,321883,321882,321881,321880,321879,321878,321877,321876,321875,321874,321873,321872,321871,321870,321869,321868,321867,321866,321865,321864,321863,321862,321861,321860,321859,321858,321857,321856,321855,321854,321853,321852,321851,321850,321849,321848,321847,321846,321845,321844,321843,321842,321841,321840,321839,321838,321837,321836,321835,321834,321833,321832,321831,321830,321829,321828,321824,321823,321822,321821,321820,321819,321755,321754,321753,321752,321751,321750,321749,321748,321747,321746,321745,321744,321743,321742,321741,321740,321739,321738,321737,321736,321735,321734,321733,321732,321731,321730,321729,321728,321727,321726,321725,321724,321723,321722,321721,321720,321719,321718,321717,321716,321715,321714,321713,321712,321711,321710,321709,321708,321707,321706,321705,321704,321703,321702,321701,321700,321699,321698,321697,321696,321695,321694,321693,321692,321691,321690,321689,321688,321687,321686,321685,321684,321683,321682,321681,321680,321679,321678,321677,321676,321675,321674,321673,321672,321671,321670,321669,321668,321667,321666,321665,321664,321663,321662,321661,321660,321659,321658,321657,321656,321655,321654,321653,321652,321651,321650,321649,321648,321647,321646,321645,321644,321643,321642,321641,321640,321639,321638,321637,321636,321635,321634,321633,321632,321631,321630,321629,321628,321627,321626,321625,321624,321623,321622,321621,321620,321619,321618,321617,321616,321615,321614,321613,321612,321611,321610,321609,321608,321607,321606,321605,321604,321603,321602,321601,321600,321599,321598,321597,321596,321595,321594,321593,321592,321591,321590,321589,321588,321587,321586,321585,321584,321583,321582,321581,321580,321579,321578,321577,321576,321575,321574,321573,321333,321332,320359,320358,320357,320356,320355,320354,320353,320352,320351,320345,320344,320343,320341,320340,320336,320335,320334,320333,320332,320327,320326,320325,320324,320323,320322,320321,320320,320319,320318,320317,320316,320315,320314,320313,320312,320311,320083,318930,318929,318919,318869,315669,315668,315667,315666,314976,314830,314829,313755,313646,313645,313644,313594,313593,313592,313591,313412,313411,313375,313374,313373,313355,313354,313353,313352,312965,312964,312963,312962,312698,312584,312583,312582,312581,310213,310053,310052,310051,310050,310049,310048,310047,310046,310045,310038,310037,310036,310035,308424,308423,308422,308421,308420,308396,308395,308371,308370,308369,308368,308108,308107,308106,308105,308104,308103,308102,308101,308100,308099,308098,307970,307268,306709,306708,306707,306706,306705,306704,306683,306682,304229,304228,304227,304226,304222,304218,304217,304216,304204,304203,304202,300849,300848,300847,300835,300834,300833,300832,300831,300830,300829,300828,300827,300822,300821,300820,300819,300818,300817,300816,300810,300809,300804,300803,300802,300801,300785,300784,300783,300782,300132,300131,300130,299758,299756,299751,299750,297791,297790,297789,297788,297760,297759,297758,295924,295923,295922,295921,295911,295860,295859,295852,295851,295850,295849,295848,294440,294298,294297,294296,294295,291685,291684,291683,291600,288651,288650,288649,288648,288647,288645,287944,287943,287942,287694,287693,287692,287691,287690,287689,287688,287687,287686,287104,287096,287093,287092,287090,287089,287070,287065,287061,287056,287055,287054,287053,287052,287051,287050,287049,287048,287047,287046,287045,287024,287021,285378,285377,285376,285375,284795,284788,284786,284489,284488,284477,283960,283919,283918,283917,283916,283915,283914,283913,283912,283735,283734,283733,283732,283635,283630,283629,283590,283589,283588,283587,283586,283585,283584,283583,283582,283581,283580,283579,283578,283577,283576,283575,283574,283573,283572,276640,276639,276638,276637,276636,276635,276634,276633,273809,273808,273807,273806,273805,273804,273549,273548,273399,273398,273397,273392,273391,273390,271664,271663,271662,271661,271660,271659,271658,271657,271656,271655,271654,271653,271652,271651,271650,271649,271648,271647,271646,271645,271644,271643,271642,271641,271640,271639,271638,271637,271636,271635,271634,271633,271632,271631,271630,271629,271628,271627,271626,271625,271624,271623,271622,271621,271620,271619,271618,271617,271616,271615,271614,271613,271612,271611,271610,271609,271608,271607,271606,271605,271604,271603,271602,271218,269952,269951,267426,267420,267416,267414,267413,266745,266739,266735,266707,266444,266443,266442,266414,266413,266412,266411,266053,266052,266051,265993,265957,259502,259501,259500,259499,259498,256758,256756,256755,253099,253098,253001,253000,252999,252998,252997,252996,252995,252994,252993,252992,252991,252990,252989,252988,252987,252986,252985,252984,252983,252982,252981,252980,252979,252978,252977,252976,252952,252951,252930,252929,252928,252927,252919,252918,252917,252916,249534,249533,249532,249516,249515,249514,249508,249507,249506,249502,249501,249500,249499,249498,249497,249496,249495,249494,249493,249492,249491,249490,249489,249488,249487,249486,249485,249484,249483,249482,249481,249480,249479,249478,249477,249476,241213,241190,241189,241188,241187,241186,241185,241184,241183,241182,241181,241180,241179,241178,241177,241176,241173,241172,241171,241145,234683,234682,233864,233863,233862,233861,233860,233859,233683,233682,233681,233676,233675,233674,233673,233605,228270,228269,228268,228267,228266,228265,228264,228263,228262,228261,228260,226747,226746,226745,226744,226743,226267,226266,226265,226264,225955,225930,225555,225209,224147,224146,224145,224144,224022,224021,224020,224019,213318,213317,212208,211654,211611,210837,210836,210835,210755,210754,210738,210731,210728,205293,205292,205289,205288,205287,205286,204189,204188,204187,204186,204185,204184,204183,204182,204181,204180,204179,204178,204160,201499,201498,201497,201462,201461,201460,199208,199207,199206,199205,199204,199203,199202,199201,199200,199199,199198,199197,199141,199140,199139,199138,197743,197741,197740,197697,197696,195502,195501,195500,195499,195498,195497,195449,195448,195447,195446,195440,195439,195438,195437,195436,195435,195434,195424,195423,195422,195421,195258,195257,195256,195255,195244,195243,195242,195241,195236,195235,195222,195221,195220,195219,195218,195204,195203,195202,195201,195191,195190,195189,195188,195187,195186,195185,195184,195183,195182,195181,195180,195179,195178,195177,195176,195175,195174,195173,195172,195171,195170,195169,195168,195167,195166,195165,195164,195163,195162,195161,195160,195159,195158,195157,195156,195155,195154,195153,195152,195151,195150,195149,195148,195147,195146,195145,195144,195143,195142,195141,195140,195139,195138,195137,195136,195135,195134,195133,195132,195131,195130,195129,195128,195127,195126,195125,195124,195123,195122,195121,195120,188425,188424,188423,188184,177941,177939,177722,177721,176303,176302,176301,176300,176299,176295,176290,176288,176287,176286,176285,176284,176180,176179,176178,176177,176176,176175,176174,176173,176172,176113,176112,175911,175910,175909,175908,175797,175796,175795,175794,174848,174847,174846,174845,174844,174843,174839,174838,174837,174836,174835,174834,174833,174653,174652,174651,173277,173276,173275,173274,172930,172929,172801,172800,172799,172798,172797,172796,172795,170520,170519,170518,170517,170496,170495,170494,170493,170052,170051,170050,170049,170048,170047,170046,170045,170044,170043,170042,170041,170040,170039,170038,170037,169998,169997,169996,169995,169994,169993,169992,169991,169990,169989,169988,169987,169986,169985,169984,169983,169982,169981,169980,169979,169978,169977,169976,169975,169974,169973,169972,169971,169970,169969,169968,169967,169966,169965,169964,169963,169962,169961,169960,169959,169958,169957,169956,169955,169954,169944,169943,169942,169632,169631,169630,169629,169628,169627,169626,169625,169624,169623,169622,169621,169620,169619,169618,169617,169616,169615,169614,169613,169612,169570,169569,169568,169567,169566,169565,169564,169563,169562,169561,169560,169559,169558,169523,169522,169521,168500,168499,168498,168465,168464,168463,168458,168444,168443,168442,168441,168440,168439,168438,168437,168088,168087,168086,168085,168084,167678,167677,167676,167675,167674,167673,167672,167671,167670,167669,167668,167667,167666,167665,167660,167659,167658,167657,167656,167655,167654,167653,167652,167651,167650,167649,167648,167647,167646,167645,167644,167643,167642,167453,167360,167359,167358,167357,166834,166396,166395,166394,165935,165741,165599,165279,165278,165277,165276,165272,165230,165229,165228,165227,165226,165225,165224,165223,165222,165221,165220,165219,165218,165217,165216,165215,165214,165213,165212,165211,165210,165209,165208,165207,165206,165205,165204,165203,165202,165201,165200,165199,165198,165197,165196,165195,165194,165193,164729,164728,164727,164726,164725,164451,164450,164449,164448,164447,164446,164445,164444,164443,164442,164441,164440,163226,163190,163189,163173,163172,163171,163170,163073,163072,162993,162992,162991,162990,162989,162988,162987,162986,162985,162984,162983,162982,162981,162980,162979,162978,162977,162976,162971,162970,162969,162968,162967,162966,162965,162964,162963,162962,162961,162960,162959,162958,162615,162614,162613,162612,162594,162593,162592,162591,162590,162589,162498,162496,162344,162343,162339,162335,148633,148038,148037,148036,147910,147909,147880,147879,147878,147877,147876,147875,147873,147872,147871,147870,147869,147868,147867,147866,147858,147857,147856,147855,146891,146889,146888,146589,146588,146587,146586,146585,146584,146583,146582,146571,146570,146569,146567,146566,146565,146563,146562,146561,146546,146545,146544,145258,145257,145256,145255,145254,145253,145252,144550,144549,144548,142089,142088,142087,139321,139320,139319,139134,139133,131908,131907,131906,131905,131904,131903,131902,131901,131900,131899,131898,131897,131896,131895,131894,131893,131673,131672,131671,131670,131669,131598,131597,131596,131595,131594,131501,131278,131276,131275,131124,129674,129673,129643,129235,129234,129233,128770,128769,128391,128390,128389,128384,128383,128382,128381,128380,128379,128378,128377,128376,121008,121007,121006,121005,120967,120966,120965,120964,120583,120582,120581,120580,120579,120578,120577,120576,120553,120549,120548,120547,120546,120545,120544,120543,120542,120541,120540,120539,120538,120151,120150,120149,120148,120147,120146,120145,120144,120143,118858,118857,118856,118792,118153,118152,118151,118150,117658,117657,117656,113915,113914,113913,113880,113879,113651,113641,113640,113639,113628,113627,113626,113625,113624,113623,112356,112354,112353,111846,111845,111844,111843,111842,111841,111840,111839,111278,111277,111276,111275,108757,108452,108451,108450,107961,107960,107959,107958,107957,107956,107955,107954,107953,107952,107951,107950,107949,107948,107947,107946,107945,107944,107943,107942,107941,107940,107939,107938,107937,107936,107935,107934,107929,107928,107927,107926,107925,107924,107923,107922,107913,107912,107911,107910,107905,107904,107903,107902,107901,107900,107899,107898,107889,107888,107887,107886,107885,107884,107883,107882,107877,107876,107875,107874,107865,107864,107863,107862,107853,107852,107851,107850,107849,107848,107847,107846,101883,101882,101881,101880,101879,98268,98267,98266,98265,97213,97212,97211,97210,97194,97193,96432,96431,96430,96429,96428,96427,96426,96425,93308,93307,93306,93224,93223,93222,93221,93220,93219,88135,88134,88133,88132,88131,88130,88129,88128,88127,88126,88125,88124,88115,88114,88113,88112,88111,88110,88109,88108,88083,88082,88081,88076,88075,88057,88055,87702,87671,87532,87523,87522,87521,87520,87511,87510,87509,87448,87447,87446,87445,87444,87443,87442,87441,87440,87439,87438,87437,87426,87425,87424,87423,87422,87421,87420,87419,87418,87417,87416,87415,87414,87413,87412,87411,87410,87409,87408,87407,87406,87405,87404,87403,87402,87401,87400,87399,87398,87397,87396,87395,87394,87393,87392,87391,87390,87389,87388,87387,87386,87385,87384,87383,87382,87381,87378,87377,87376,87375,87374,87362,87361,87360,87359,87358,87357,87356,87355,87354,87353,87352,87351,87350,87349,87348,87347,87346,87345,87344,87343,87342,87341,87340,87339,87338,87337,87336,87335,87334,87333,87332,87331,87330,87329,87328,87327,87326,87325,87324,87323,87322,87321,87320,87319,87318,87317,87316,87315,87313,87312,87311,87310,87309,87302,87301,87300,87299,87298,87297,87296,87295,87294,87290,87289,87288,87191,87056,87055,87054,87037,87036,87033,87028,87027,87026,87025,87022,87021,87020,87019,87018,87017,87016,87015,87014,86998,86997,86996,86995,86994,86993,86992,86991,86974,86973,86964,86963,86946,86945,86944,86943,86907,86906,86905,86904,86903,86902,86901,86900,86899,86898,86897,86892,86881,86880,86879,86878,86877,86876,86875,86874,86873,86872,86871,86867,86866,86865,86864,86860,86859,86857,86721,86623,86622,86621,86306,86305,86083,86082,86081,86080,86079,84421,84420,84419,84369,84235,84234,84233,84229,84228,84227,84226,84225,84224,84220,84219,84218,84217,84216,84215,84204,84203,84202,84201,84200,84199,84198,84197,84196,84195,84194,84193,84192,84191,84190,84189,84188,84187,84186,84185,84184,84183,84182,84181,84180,84179,84178,84177,84176,84175,84174,84173,84172,84171,84170,84169,84168,84167,84166,84165,84164,84163,84162,84161,84160,84159,84158,84157,84156,84155,84154,84153,84152,84151,84150,84149,84148,84147,84146,84145,84144,84143,84142,84141,84140,84139,84138,84137,84136,84135,84134,84133,84132,84131,84130,84129,84128,84127,84126,84125,84124,84123,84122,84121,84120,84119,84118,84117,84116,84115,84114,84113,84112,84111,84110,84109,84108,84107,84106,84105,84104,84103,84102,84101,84100,84099,84098,84097,84096,84095,84094,84093,84092,84091,84090,84089,84088,84087,84086,84085,84084,84083,84082,84081,84080,84079,84078,84077,84076,84075,84074,84073,84072,84071,84070,84069,84068,84067,84066,84065,84064,84063,84062,84061,84060,84029,84027,84026,82130,82129,73315,73314,73313,73312,62827,62826,62815,62814,11196,2242];
        $other_youp = [538789,538316,538315,538185,538184,538183,538182,538181,538180,538179,538178,538077,538066,538065,538064,534405,534402,534401,534400,533866,533865,533864,528058,525200,514833514360,514359,513045,513044,506268,506267,506053,506051,506050,506048,506047,506045,506044,506042,505142,505140,505139,505137,505136,505134,505133,505131,504925,504923,504922,504921,504920,504919,504917,504706,504702,504700,504699,504698,504695,504694,504693,504692,504691,504690,504688,504687,504685,504684,504682,504680,504679,504677,504676,504674,504673,504672,504671,504670,504669,504668,504666,504665,504663,504662,504660,504659,504016,504013,503964,503961,503960,503957,503956,503953,502883,502882,502881,502880,502879,502853,502852,501507,501506,501503,501502,501499,501498,501495,501494,491512,491511,491476,491475,423092,423084,422084,422083,418831,418830,417498,417497,417496,417495,417493,417492,417491,417490,417489,417488,417487,417486,416574,416573,412513,412512,410558,410557,410556,398523,398522,396328,396327,389827,389826,367629,367628,367627,367626,362073,362072,360888,360887,353548,353547,353546,341680,341679,321332,313671,313670,306965,306964,176173,118280,118279,118278,542122,542121,542120,542119,542117,542115,541727,541726,541725,541724,541723,540831,540830,540829,540828,540827,540826,540825,540824,540431,540430,540429,540428,540427,540426,540425,540424,539353,539352,539351,539350,539349,539313,539312,539311,539310,539309,539308,539175,539174,539173,539172,539165,539164,539123,539122,539121,539120,539119,538956,538955,538954,538953,538952,538862,538861,538860,538859,538858,538857,538856,538855,538854,538853,538852,538851,538844,538843,538778,538777,538776,538775,538774,538773,538772,538754,538168,537560,537559,537558,537557,537556,537555,537554,535831,535830,535829,535828,535825,535824,535823,535822,535821,535820,535819,535818,535817,535816,535815,535814,535813,535812,535811,535810,535809,535808,535807,535499,535498,535497,535496,534830,534781,534502,534501,534500,534497,534496,534111,534055,534054533917,533916,533831,533830,533763,533762,533761,533760,533743,533742,533741,533740,533739,533738,533737,533736,533735,533427,533415,533414,533413,533345,533344,533343,533337,533336,533335,533134,533133,533132,533131,533130,533129,533128,532999,532998,532997,532996,532995,532994,532993,532992,532991,532990,530189,530188,529251,529250,529122,529121,529112,528515,528514,528513,528061,528060,528059,528058,528057,528056,528035,528034,527977,527923,527922,527921,527550,527549,527548,527547,527546,527545,527544,527543,527542,527541,527540,525983,525982,525981,525980,525979,525978,525977,525976,525890,525869,525868,525867,525866,524527,524526,524525,524281,524280,524279,524184,524183,524182,523901,523900,523899,523166,523165,522850,522849,522848,522847,522845,522844,522843,522842,522841,522840,522839,522837,522836,522835,522834,522833,522831,522830,522829,522828,522827,522826,522825,522823,522127,522126,521646,521645,521311,521310,521161,521133,521132,521120,521119,521118,521117,521116,521061,521060,521059,521058,521052,521051,521041,521040,521037,521036,520985,520984,520983,520982,520981,520956,520955,520954,520953,520952,520937,520936,520935,520934,520912,520911,520441,520439,520438,520437,520436,520435,520434,520433,520432,519791,519790,519789,519788,519787,519238,519237,519236,519235,519234,519215,519214,519213,519212,519211,519210,519209,519201,519200,519199,519198,519197,519196,519195,519194,519177,519176,519175,519174,519173,519172,519171,519168,519167,519166,519156,519155,519154,519111,519110,519109,519108,519107,519106,519105,519094,519053,519052,518764,518763,518762,518761,518760,518759,518758,518757,518756,518755,518753,518752,518751,518744,518735,518734,518669,518668,518667,518666,518665,518664,518663,518662,518623,518622,518573,518572,518563,518562,518545,518544,518543,518542,518541,518540,518539,518538,518537,518536,518535,
            518534,518533,518532,518531,518522,518517,518516,518515,518514,518513,518512,518511,518510,518508,518507,518505,518504,518503,518502,518501,518500,518495,518416,518355,518314,518313,518312,515570,515569,515568,515567,515566,515565,515564,515563,515562,515452,515451,515450,515449,515391,515390,515389,515388,515387,515386,515385,515384,515383,515382,515381,515380,515370,515237,515236,515235,515234,515233,515232,515231,515230,515229,515228,515227,515226,515225,515224,515223,515222,515221,515220,515219,515212,515211,515210,515209,515208,515207,515206,515205,515065,515064,514674,514673,514637,514636,514360,514359,514357,514356,514355,514354,514353,514217,
            514216,514215,514028,514027,514026,514025,514023,514022,514021,513931,513925,513924,513923,513922,513921,513427,513426,513425,513424,513114,513113,513091,513090,513045,513044,513043,513042,512640,512627,512626,512625,512623,512622,512621,512620,512619,512618,512617,512616,512615,512614,512613,512612,512611,512610,512609,512608,512606,512605,512604,512603,512602,512601,512600,512599,512598,512597,512596,512594,512593,512592,512591,512590,512589,512588,512587,512586,512585,512584,512583,512582,512581,511956,511955,511954,511951,511950,511949,511941,511940,511915,511914,511913,511912,511911,511910,511909,511908,511907,511906,511905,511904,511202,511156,511155,511154,511146,511145,511144,511143,511142,511141,511140,511139,511138,511137,511136,511135,511134,511133,511132,511131,511036,511035,511034,511033,511032,511031,511030,511029,511028,511027,511026,511025,510789,510788,510787,510786,510778,510777,510776,510775,509403,509402,509401,509400,509399,508915,508914,508885,508884,508883,508882,508881,508880,508879,508878,508662,508661,508660,508659,508657,508656,508654,508653,508651,508650,508649,508647,508646,508645,508643,508642,508641,508626,508625,508620,508619,508618,508617,508616,508169,508168,508167,508107,508106,507459,507458,507434,506924,506923,506268,506267,506262,506261,506137,506136,506033,506032,506031,506030,506029,506028,506019,505078,505077,505076,504100,503682,503681,503068,503067,502778,502777,502776,502775,502762,502755,502754,502748,502747,502746,502738,502737,502701,502700,501405,500630,500629,500628,500627,500626,499041,494383,494382,493926,493667,493666,491512,491511,491476,491475,491462,489514,489513,489512,489511,489510,489509,487633,487632,486596,486595,486580,486579,486578,486577,486555,486554,483956,483955,482176,482175,482174,482173,482172,481323,481322,481321,481320,449620,449619,449618,449617,449616,423488,423487,423486,423485,423087,423086,423082,423081,423080,423079,423078,423077,423076,423075,423074,423073,423072,423071,422637,422636,422635,422085,422084,422083,421815,421814,421776,421775,421774,421773,421772,421247,421246,421245,421244,421243,421183,421182,421181,420824,420823,420822,420821,420613,420612,420611,420610,420608,420606,420605,420598,420593,420592,420565,420561,420560,420559,419934,419933,419932,419931,419930,419929,419907,419906,419904,419903,419902,419901,419900,419228,419227,419226,419225,418949,418948,418947,418946,418945,418944,418943,418942,418941,418940,418939,418938,418937,418936,418935,418934,418933,418932,418931,418930,418929,418928,418926,418925,418924,418923,418922,418921,418920,418919,418918,418917,418916,418915,418914,418913,418912,418911,418910,418909,418908,418907,418906,418905,418904,418903,418902,418901,418900,418899,418898,418897,418896,418831,418830,418829,417575,417574,417572,417570,417568,417178,417177,417175,417172,417169,417167,417166,417165,416957,416956,416785,416764,416763,416762,416761,416760,416441,416440,416379,416345,416343,416341,416267,416266,416102,416101,416100,416099,416098,416097,416096,416095,416094,416093,416092,416091,416090,416089,416088,416087,416086,416085,416084,416083,416082,416081,416080,416079,416078,416077,416076,416075,416074,416073,416072,416071,416070,416069,416068,416067,416066,415627,415492,415491,415490,415485,415484,415483,414900,414899,414803,414802,414801,414800,414799,414798,414794,414793,414792,414791,414790,414789,414788,414787,414786,414785,414784,414783,414782,414777,414776,414775,414774,414773,414772,414771,414770,414769,414308,414307,414306,414305,414304,414303,414302,414301,414300,414128,413977,413976,413975,413790,413789,413788,413219,413218,412853,412852,412655,412654,412653,412633,412632,412631,412511,412502,412501,412476,412475,412298,412297,412296,412295,412294,410605,410604,410603,409839,409838,409837,409836,409835,408225,408207,408206,408205,408204,407032,406273,406272,406271,405947,405943,405942,402481,402480,402479,401841,401798,401797,401794,401793,401458,401297,401155,400444,400443,400442,400268,400267,400266,400229,399356,399355,399354,399353,398768,398767,398766,398765,398764,398763,398762,398761,398760,397552,397551,397550,397549,396424,395821,395819,395815,395313,395260,395232,395062,395061,395060,394979,394379,393757,393515,393514,393513,393512,392903,391808,391807,391768,391767,391766,391765,391764,391763,391762,391761,391743,391742,390846,390753,390739,390738,390722,387286,387285,387246,387226,387225,387180,387179,387133,387132,387131,386833,386832,386831,386830,386662,386661,385485,385484,385483,385482,385286,385285,385272,385271,385270,384686,384672,384671,384670,383031,383028,383016,383015,383014,382754,382753,382752,382751,382750,382550,382549,382548,382547,382546,382545,382544,382543,382023,382022,380486,380485,380484,380483,380459,380458,380457,380392,380391,380390,380389,380305,380304,380303,380302,380189,380188,380187,380074,380073,380072,380071,379844,378429,378428,378427,378426,378425,378424,378423,378422,378338,378327,377909,377889,377888,377887,377886,377869,377868,377867,377866,377865,377864,377863,377862,377861,377860,377791,377790,377789,377788,377781,377780,377779,377778,377777,377776,377775,377774,377758,377757,377756,377747,377693,377692,377691,377690,377689,377688,377687,377686,377678,377677,377676,377670,377669,377668,377667,377666,377665,377664,377663,377662,377661,377660,377659,377658,377657,377656,377655,377654,377653,377650,377649,377648,377643,377642,377641,377636,377635,377634,377633,377632,377631,377625,377624,377623,377622,377621,377620,377619,377616,377613,376873,376872,376869,376868,376867,376866,376865,376654,376418,376417,376416,375890,375889,375888,375538,375537,375536,375535,375534,375533,375478,375477,375457,375456,374818,374817,374796,374750,374735,374731,374730,374729,374728,374727,374726,374725,374714,373964,373963,373089,373088,373087,373086,373085,373084,373081,371580,371579,371578,371328,371327,371326,370902,370901,370900,370899,370898,370897,370896,370895,370833,370832,370831,370554,370537,370506,370505,370504,370503,370502,370501,370449,370448,370447,370446,370445,370444,370443,370442,370371,370370,370369,370368,370367,370366,370364,370351,370350,370332,370331,370330,370329,370328,370314,370313,369453,369452,369204,369203,369202,369201,369200,369199,369198,369197,369168,369167,369163,369162,366049,365200,365199,365198,364583,364582,364581,364580,363672,363671,363670,363631,363308,363307,363306,363305,363303,363302,363301,363300,363299,363298,363297,363296,363295,362615,362611,362610,362609,362542,356176,356175,356139,356132,356131,355720,355719,355601,355600,355467,355466,354987,354986,354985,354984,354983,353474,353473,353472,353471,353470,353469,353468,353325,353324,353323,353317,353316,353261,353260,353259,353258,353249,353248,353247,353246,351811,351806,349727,349726,349725,349332,349331,349330,349329,349328,349327,349326,349325,349324,349323,349322,349321,349320,349319,349318,349317,349316,349315,349314,349313,349312,349311,349310,349309,349308,349307,349306,349305,349304,349303,349302,349301,349300,349299,349298,349297,349295,349294,349293,349292,349291,349290,349289,349288,349287,349286,349285,349284,349283,349282,349281,349280,349279,349278,349277,349276,349275,349274,349273,349272,349271,349270,349269,349268,349267,349173,349172,349171,349170,349169,349168,349167,349166,349165,349164,349163,349162,349161,349160,349159,349158,349157,349156,349155,349154,349118,349117,349098,349092,347162,347161,347160,347159,347158,347157,347156,347155,347154,347153,345852,345851,345810,345809,345808,345807,345806,345805,345804,345803,345802,345801,345738,344714,344713,344712,344711,344710,344709,344708,344707,344706,344705,344704,344703,344702,344701,344700,344699,344210,344209,344208,344207,344206,344205,344204,344203,344202,344201,344200,344199,344198,344197,344196,344195,344194,344193,344192,344191,344190,344189,344188,344187,344186,344185,344184,344183,344182,344181,344180,344179,344178,344177,344176,344175,341944,341943,341942,341889,339833,339823,339593,339591,339590,339583,339578,339577,339572,339552,339550,339103,339102,339101,339100,339076,339075,339074,339064,339043,339042,339041,339040,338295,337991,337990,337989,337988,337987,337986,337985,337594,336554,336553,336552,336549,336548,336540,336535,336534,336533,336526,336525,336523,335534,335533,335532,335531,335530,335529,335528,335527,335526,335525,335524,335516,335515,335513,335512,335511,335284,334206,334205,334177,333477,333476,333475,331195,331194,331193,331192,331094,331093,331092,331091,331089,331088,331087,330872,330871,330772,330771,330770,330082,330081,330080,330079,330078,326594,326593,326592,325383,325382,325173,325172,325171,325170,325169,325168,325167,325166,325165,325164,325163,325162,325161,325160,325159,325158,325157,325142,325141,325140,325139,325138,325129,325128,325127,325126,325125,325124,325123,325122,325121,325120,325003,325002,325001,325000,324999,324998,324997,324996,324995,324994,324993,324992,324991,324990,324989,324988,324987,324986,324985,324984,323411,323298,323249,323248,323184,323178,323169,323154,323117,323116,323115,322146,322145,322144,322143,322142,322141,322140,322139,322138,322137,322136,322135,322132,322131,322130,322129,322128,322127,322126,322099,322017,322016,322015,322014,322013,322012,322011,322010,322009,322008,322007,322006,322005,322004,322003,322002,322001,322000,321979,321978,321977,321976,321975,321974,321973,321972,321971,321970,321969,321968,321967,321966,321965,321964,321963,321962,321961,321960,321959,321938,321937,321936,321935,321934,321933,321932,321931,321930,321929,321928,321927,321926,321925,321924,321923,321922,321921,321920,321919,321918,321917,321916,321915,321914,321913,321912,321911,321910,321909,321908,321907,321906,321905,321904,321903,321902,321901,321900,321899,321898,321897,321896,321895,321894,321893,321892,321891,321890,321889,321888,321887,321886,321885,321884,321883,321882,321881,321880,321879,321878,321877,321876,321875,321874,321873,321872,321871,321870,321869,321868,321867,321866,321865,321864,321863,321862,321861,321860,321859,321858,321857,321856,321855,321854,321853,321852,321851,321850,321849,321848,321847,321846,321845,321844,321843,321842,321841,321840,321839,321838,321837,321836,321835,321834,321833,321832,321831,321830,321829,321828,321824,321823,321822,321821,321820,321819,321755,321754,321753,321752,321751,321750,321749,321748,321747,321746,321745,321744,321743,321742,321741,321740,321739,321738,321737,321736,321735,321734,321733,321732,321731,321730,321729,321728,321727,321726,321725,321724,321723,321722,321721,321720,321719,321718,321717,321716,321715,321714,321713,321712,321711,321710,321709,321708,321707,321706,321705,321704,321703,321702,321701,321700,321699,321698,321697,321696,321695,321694,321693,321692,321691,321690,321689,321688,321687,321686,321685,321684,321683,321682,321681,321680,321679,321678,321677,321676,321675,321674,321673,321672,321671,321670,321669,321668,321667,321666,321665,321664,321663,321662,321661,321660,321659,321658,321657,321656,321655,321654,321653,321652,321651,321650,321649,321648,321647,321646,321645,321644,321643,321642,321641,321640,321639,321638,321637,321636,321635,321634,321633,321632,321631,321630,321629,321628,321627,321626,321625,321624,321623,321622,321621,321620,321619,321618,321617,321616,321615,321614,321613,321612,321611,321610,321609,321608,321607,321606,321605,321604,321603,321602,321601,321600,321599,321598,321597,321596,321595,321594,321593,321592,321591,321590,321589,321588,321587,321586,321585,321584,321583,321582,321581,321580,321579,321578,321577,321576,321575,321574,321573,321333,321332,320359,320358,320357,320356,320355,320354,320353,320352,320351,320345,320344,320343,320341,320340,320336,320335,320334,320333,320332,320327,320326,320325,320324,320323,320322,320321,320320,320319,320318,320317,320316,320315,320314,320313,320312,320311,320083,318930,318929,318919,318869,315669,315668,315667,315666,314976,314830,314829,313755,313646,313645,313644,313594,313593,313592,313591,313412,313411,313375,313374,313373,313355,313354,313353,313352,312965,312964,312963,312962,312698,312584,312583,312582,312581,310213,310053,310052,310051,310050,310049,310048,310047,310046,310045,310038,310037,310036,310035,308424,308423,308422,308421,308420,308396,308395,308371,308370,308369,308368,308108,308107,308106,308105,308104,308103,308102,308101,308100,308099,308098,307970,307268,306709,306708,306707,306706,306705,306704,306683,306682,304229,304228,304227,304226,304222,304218,304217,304216,304204,304203,304202,300849,300848,300847,300835,300834,300833,300832,300831,300830,300829,300828,300827,300822,300821,300820,300819,300818,300817,300816,300810,300809,300804,300803,300802,300801,300785,300784,300783,300782,300132,300131,300130,299758,299756,299751,299750,297791,297790,297789,297788,297760,297759,297758,295924,295923,295922,295921,295911,295860,295859,295852,295851,295850,295849,295848,294440,294298,294297,294296,294295,291685,291684,291683,291600,288651,288650,288649,288648,288647,288645,287944,287943,287942,287694,287693,287692,287691,287690,287689,287688,287687,287686,287104,287096,287093,287092,287090,287089,287070,287065,287061,287056,287055,287054,287053,287052,287051,287050,287049,287048,287047,287046,287045,287024,287021,285378,285377,285376,285375,284795,284788,284786,284489,284488,284477,283960,283919,283918,283917,283916,283915,283914,283913,283912,283735,283734,283733,283732,283635,283630,283629,283590,283589,283588,283587,283586,283585,283584,283583,283582,283581,283580,283579,283578,283577,283576,283575,283574,283573,283572,276640,276639,276638,276637,276636,276635,276634,276633,273809,273808,273807,273806,273805,273804,273549,273548,273399,273398,273397,273392,273391,273390,271664,271663,271662,271661,271660,271659,271658,271657,271656,271655,271654,271653,271652,271651,271650,271649,271648,271647,271646,271645,271644,271643,271642,271641,271640,271639,271638,271637,271636,271635,271634,271633,271632,271631,271630,271629,271628,271627,271626,271625,271624,271623,271622,271621,271620,271619,271618,271617,271616,271615,271614,271613,271612,271611,271610,271609,271608,271607,271606,271605,271604,271603,271602,271218,269952,269951,267426,267420,267416,267414,267413,266745,266739,266735,266707,266444,266443,266442,266414,266413,266412,266411,266053,266052,266051,265993,265957,259502,259501,259500,259499,259498,256758,256756,256755,253099,253098,253001,253000,252999,252998,252997,252996,252995,252994,252993,252992,252991,252990,252989,252988,252987,252986,252985,252984,252983,252982,252981,252980,252979,252978,252977,252976,252952,252951,252930,252929,252928,252927,252919,252918,252917,252916,249534,249533,249532,249516,249515,249514,249508,249507,249506,249502,249501,249500,249499,249498,249497,249496,249495,249494,249493,249492,249491,249490,249489,249488,249487,249486,249485,249484,249483,249482,249481,249480,249479,249478,249477,249476,241213,241190,241189,241188,241187,241186,241185,241184,241183,241182,241181,241180,241179,241178,241177,241176,241173,241172,241171,241145,234683,234682,233864,233863,233862,233861,233860,233859,233683,233682,233681,233676,233675,233674,233673,233605,228270,228269,228268,228267,228266,228265,228264,228263,228262,228261,228260,226747,226746,226745,226744,226743,226267,226266,226265,226264,225955,225930,225555,225209,224147,224146,224145,224144,224022,224021,224020,224019,213318,213317,212208,211654,211611,210837,210836,210835,210755,210754,210738,210731,210728,205293,205292,205289,205288,205287,205286,204189,204188,204187,204186,204185,204184,204183,204182,204181,204180,204179,204178,204160,201499,201498,201497,201462,201461,201460,199208,199207,199206,199205,199204,199203,199202,199201,199200,199199,199198,199197,199141,199140,199139,199138,197743,197741,197740,197697,197696,195502,195501,195500,195499,195498,195497,195449,195448,195447,195446,195440,195439,195438,195437,195436,195435,195434,195424,195423,195422,195421,195258,195257,195256,195255,195244,195243,195242,195241,195236,195235,195222,195221,195220,195219,195218,195204,195203,195202,195201,195191,195190,195189,195188,195187,195186,195185,195184,195183,195182,195181,195180,195179,195178,195177,195176,195175,195174,195173,195172,195171,195170,195169,195168,195167,195166,195165,195164,195163,195162,195161,195160,195159,195158,195157,195156,195155,195154,195153,195152,195151,195150,195149,195148,195147,195146,195145,195144,195143,195142,195141,195140,195139,195138,195137,195136,195135,195134,195133,195132,195131,195130,195129,195128,195127,195126,195125,195124,195123,195122,195121,195120,188425,188424,188423,188184,177941,177939,177722,177721,176303,176302,176301,176300,176299,176295,176290,176288,176287,176286,176285,176284,176180,176179,176178,176177,176176,176175,176174,176173,176172,176113,176112,175911,175910,175909,175908,175797,175796,175795,175794,174848,174847,174846,174845,174844,174843,174839,174838,174837,174836,174835,174834,174833,174653,174652,174651,173277,173276,173275,173274,172930,172929,172801,172800,172799,172798,172797,172796,172795,170520,170519,170518,170517,170496,170495,170494,170493,170052,170051,170050,170049,170048,170047,170046,170045,170044,170043,170042,170041,170040,170039,170038,170037,169998,169997,169996,169995,169994,169993,169992,169991,169990,169989,169988,169987,169986,169985,169984,169983,169982,169981,169980,169979,169978,169977,169976,169975,169974,169973,169972,169971,169970,169969,169968,169967,169966,169965,169964,169963,169962,169961,169960,169959,169958,169957,169956,169955,169954,169944,169943,169942,169632,169631,169630,169629,169628,169627,169626,169625,169624,169623,169622,169621,169620,169619,169618,169617,169616,169615,169614,169613,169612,169570,169569,169568,169567,169566,169565,169564,169563,169562,169561,169560,169559,169558,169523,169522,169521,168500,168499,168498,168465,168464,168463,168458,168444,168443,168442,168441,168440,168439,168438,168437,168088,168087,168086,168085,168084,167678,167677,167676,167675,167674,167673,167672,167671,167670,167669,167668,167667,167666,167665,167660,167659,167658,167657,167656,167655,167654,167653,167652,167651,167650,167649,167648,167647,167646,167645,167644,167643,167642,167453,167360,167359,167358,167357,166834,166396,166395,166394,165935,165741,165599,165279,165278,165277,165276,165272,165230,165229,165228,165227,165226,165225,165224,165223,165222,165221,165220,165219,165218,165217,165216,165215,165214,165213,165212,165211,165210,165209,165208,165207,165206,165205,165204,165203,165202,165201,165200,165199,165198,165197,165196,165195,165194,165193,164729,164728,164727,164726,164725,164451,164450,164449,164448,164447,164446,164445,164444,164443,164442,164441,164440,163226,163190,163189,163173,163172,163171,163170,163073,163072,162993,162992,162991,162990,162989,162988,162987,162986,162985,162984,162983,162982,162981,162980,162979,162978,162977,162976,162971,162970,162969,162968,162967,162966,162965,162964,162963,162962,162961,162960,162959,162958,162615,162614,162613,162612,162594,162593,162592,162591,162590,162589,162498,162496,162344,162343,162339,162335,148633,148038,148037,148036,147910,147909,147880,147879,147878,147877,147876,147875,147873,147872,147871,147870,147869,147868,147867,147866,147858,147857,147856,147855,146891,146889,146888,146589,146588,146587,146586,146585,146584,146583,146582,146571,146570,146569,146567,146566,146565,146563,146562,146561,146546,146545,146544,145258,145257,145256,145255,145254,145253,145252,144550,144549,144548,142089,142088,142087,139321,139320,139319,139134,139133,131908,131907,131906,131905,131904,131903,131902,131901,131900,131899,131898,131897,131896,131895,131894,131893,131673,131672,131671,131670,131669,131598,131597,131596,131595,131594,131501,131278,131276,131275,131124,129674,129673,129643,129235,129234,129233,128770,128769,128391,128390,128389,128384,128383,128382,128381,128380,128379,128378,128377,128376,121008,121007,121006,121005,120967,120966,120965,120964,120583,120582,120581,120580,120579,120578,120577,120576,120553,120549,120548,120547,120546,120545,120544,120543,120542,120541,120540,120539,120538,120151,120150,120149,120148,120147,120146,120145,120144,120143,118858,118857,118856,118792,118153,118152,118151,118150,117658,117657,117656,113915,113914,113913,113880,113879,113651,113641,113640,113639,113628,113627,113626,113625,113624,113623,112356,112354,112353,111846,111845,111844,111843,111842,111841,111840,111839,111278,111277,111276,111275,108757,108452,108451,108450,107961,107960,107959,107958,107957,107956,107955,107954,107953,107952,107951,107950,107949,107948,107947,107946,107945,107944,107943,107942,107941,107940,107939,107938,107937,107936,107935,107934,107929,107928,107927,107926,107925,107924,107923,107922,107913,107912,107911,107910,107905,107904,107903,107902,107901,107900,107899,107898,107889,107888,107887,107886,107885,107884,107883,107882,107877,107876,107875,107874,107865,107864,107863,107862,107853,107852,107851,107850,107849,107848,107847,107846,101883,101882,101881,101880,101879,98268,98267,98266,98265,97213,97212,97211,97210,97194,97193,96432,96431,96430,96429,96428,96427,96426,96425,93308,93307,93306,93224,93223,93222,93221,93220,93219,88135,88134,88133,88132,88131,88130,88129,88128,88127,88126,88125,88124,88115,88114,88113,88112,88111,88110,88109,88108,88083,88082,88081,88076,88075,88057,88055,87702,87671,87532,87523,87522,87521,87520,87511,87510,87509,87448,87447,87446,87445,87444,87443,87442,87441,87440,87439,87438,87437,87426,87425,87424,87423,87422,87421,87420,87419,87418,87417,87416,87415,87414,87413,87412,87411,87410,87409,87408,87407,87406,87405,87404,87403,87402,87401,87400,87399,87398,87397,87396,87395,87394,87393,87392,87391,87390,87389,87388,87387,87386,87385,87384,87383,87382,87381,87378,87377,87376,87375,87374,87362,87361,87360,87359,87358,87357,87356,87355,87354,87353,87352,87351,87350,87349,87348,87347,87346,87345,87344,87343,87342,87341,87340,87339,87338,87337,87336,87335,87334,87333,87332,87331,87330,87329,87328,87327,87326,87325,87324,87323,87322,87321,87320,87319,87318,87317,87316,87315,87313,87312,87311,87310,87309,87302,87301,87300,87299,87298,87297,87296,87295,87294,87290,87289,87288,87191,87056,87055,87054,87037,87036,87033,87028,87027,87026,87025,87022,87021,87020,87019,87018,87017,87016,87015,87014,86998,86997,86996,86995,86994,86993,86992,86991,86974,86973,86964,86963,86946,86945,86944,86943,86907,86906,86905,86904,86903,86902,86901,86900,86899,86898,86897,86892,86881,86880,86879,86878,86877,86876,86875,86874,86873,86872,86871,86867,86866,86865,86864,86860,86859,86857,86721,86623,86622,86621,86306,86305,86083,86082,86081,86080,86079,84421,84420,84419,84369,84235,84234,84233,84229,84228,84227,84226,84225,84224,84220,84219,84218,84217,84216,84215,84204,84203,84202,84201,84200,84199,84198,84197,84196,84195,84194,84193,84192,84191,84190,84189,84188,84187,84186,84185,84184,84183,84182,84181,84180,84179,84178,84177,84176,84175,84174,84173,84172,84171,84170,84169,84168,84167,84166,84165,84164,84163,84162,84161,84160,84159,84158,84157,84156,84155,84154,84153,84152,84151,84150,84149,84148,84147,84146,84145,84144,84143,84142,84141,84140,84139,84138,84137,84136,84135,84134,84133,84132,84131,84130,84129,84128,84127,84126,84125,84124,84123,84122,84121,84120,84119,84118,84117,84116,84115,84114,84113,84112,84111,84110,84109,84108,84107,84106,84105,84104,84103,84102,84101,84100,84099,84098,84097,84096,84095,84094,84093,84092,84091,84090,84089,84088,84087,84086,84085,84084,84083,84082,84081,84080,84079,84078,84077,84076,84075,84074,84073,84072,84071,84070,84069,84068,84067,84066,84065,84064,84063,84062,84061,84060,84029,84027,84026,82130,82129,73315,73314,73313,73312,62827,62826,62815,62814,11196,2242];

        $start_time = $this->startTime;
        $end_time = $this->endTime;
        $province = $this->province;

        $target = [];
        //通讯BU 1
        $tx = PushDistrict::getPushByMallId($province,$start_time,$end_time,1);
        //母婴BU  3
        $baby = PushDistrict::getPushByMallId($province,$start_time,$end_time,3);
        //文体办公  7
        $liter = PushDistrict::getPushByMallId($province,$start_time,$end_time,7);
        $target['通讯BU'] = $tx;
        $target['母婴BU'] = $baby;
        $target['文体办公'] = $liter;
        //根据传过来的province分为江苏16和非江苏(只有江苏有小米，其他城市没有)
        if ($province == 16) {
            //小米代理商
            $xiaomi = PushDistrict::getPushByGoodsId($province,$start_time,$end_time,$su_xiaomi);  //$su_xiaomi
            //51优品
            $youp = PushDistrict::getPushByGoodsId($province,$start_time,$end_time,$su_youp);   //$su_youp
            $target['江苏小米代理商'] = $xiaomi;
            $target['江苏51优品'] = $youp;
        } else {
            //51优品
            $youp = PushDistrict::getPushByGoodsId($province,$start_time,$end_time,$other_youp);
            $target['51优品'] = $youp;
        }

        if (!empty($target)) {
            $this->actionResult['res'] = self::SUCCESS;
            $this->actionResult['msg'] = '获取数据成功';
            $this->actionResult['data'] = $target;
        } else {
            $this->actionResult['res'] = 100;
            $this->actionResult['msg'] = '获取数据失败';
        }
        return $this->actionResult;
    }
}
