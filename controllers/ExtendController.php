<?php
namespace frontend\controllers;

use common\models\other\AdInfo;
use common\models\other\MallRnav;
use common\models\other\OtherMainMenu;
use common\models\user\UserMember;
use frontend\components\Controller2016;
use \common\models\baby\BabyFloorBase;
use common\models\goods\BaseGoods;

/**
 * @author sunkouping
 *本控制器 专门用于外面的项目
 *扩展项目
 *易达短信
 */
class ExtendController extends Controller2016{

    
    /**
     * @description:易达短信
     * @return: Ambigous <string, string>
     * @author: sunkouping
     * @date: 2016年1月5日上午10:25:51
     * @modified_date: 2016年1月5日上午10:25:51
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionYida(){
        $user_id = $user_id = \Yii::$app->user->id;
        $user_info = UserMember::find()->where(['id'=>$user_id])->asArray()->one();
        
        $yida_url = 'http://www.10690239.com/user.do?method=addUser51';
        
        $sname = $user_info['login_account'];
        $spwd  = md5($user_info['password']);
        $sphones = $user_info['phone'];
        $this->layout = '_blank';
        
        
        
        
        return $this->render('yida',[
            'user_info'=>$user_info,
        	'yida_url'=>$yida_url,
        		'sname'=>$sname,
        		'spwd'=>$spwd,
        		'sphones'=>$sphones,
        ]);
    }
    /**
     * @description:测试接口
     * @return: return_type
     * @author: sunkouping
     * @date: 2016年1月5日上午10:26:14
     * @modified_date: 2016年1月5日上午10:26:14
     * @modified_user: sunkouping
     * @review_user:
    */
    public function actionTest(){
    	exit(BaseGoods::getBrushCode(32793)['result']);
    }
    
}