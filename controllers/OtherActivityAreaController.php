<?php

namespace frontend\controllers;

use frontend\components\Controller2016;
use common\models\other\OtherActivityArea;
class OtherActivityAreaController extends Controller2016
{
    /**
      * @Title:actionIndex
      * @Description:活动专区首页
      * @return:[]
      * @author:huaiyu.cui
      * @date:2015-11-21 下午2:10:14
     */
    public function actionIndex()
    {
        //获取参数
        $params = [
            'page' => f_get('page',1)
        ];
        
        //获取活动专区信息
        $data = OtherActivityArea::getInfo($params);

        return $this->render('index',[
            'activityInfo' => $data['activityInfo'],
            'params' => $params,
            'total_page' => $data['count'],
        ]);
    }

}
