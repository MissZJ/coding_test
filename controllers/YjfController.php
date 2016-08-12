<?php
/**
 * Created by PhpStorm.
 * User: leo
 * Date: 2016/6/30
 * Time: 17:38
 */

namespace frontend\controllers;


use common\models\user\UserMember;
use yii\web\Controller;

class YjfController extends Controller
{
    private  $key = 'm7XTamvGZWRUGlo';

    /**
     * @description: 生成密钥
     * @author: leo
     * @date ：2016年7月1日 09:28:21
     */
    private function genToken($login_account){
        return md5($login_account.'yf'.$this->key.date('Ymd').'fy');
    }

    /**
     * @description: 激活易极付
     * @author: leo
     * @date ：2016年7月1日 09:28:21
     */
    public function actionActiveYjf()
    {
        $res = [];
        $post = \Yii::$app->request->post('parameters');
        if(isset($post) && !empty($post)){
            $post_data = json_decode($post,true);
		    if(!empty($post_data['login_account']) && !empty($post_data['token'])){
                if($post_data['token'] == $this->genToken($post_data['login_account'])){
                    $user_data = UserMember::findOne(['login_account'=>$post_data['login_account']]);
                    if(!empty($user_data)){
                        $user_data->if_yjf = 1;
                        if($user_data->save()){
                            $res = ['code'=>'200','status'=>'success','msg'=>'易极付激活成功！'];
                        }else{
                            $res = ['code'=>'104','status'=>'error','msg'=>'数据库操作异常！'];
                        }
                    }else{
                        $res = ['code'=>'103','status'=>'error','msg'=>'用户数据未找到！'];
                    }
                }else{
                    $res = ['code'=>'102','status'=>'error','msg'=>'token验证不正确！'];
                }
            }else{
                $res = ['code'=>'101','status'=>'error','msg'=>'参数传递不正确！'];
            }
        }else{
            $res = ['code'=>'105','status'=>'error','msg'=>'http请求格式不正确！'];
        }


        return json_encode($res);
    }
}
