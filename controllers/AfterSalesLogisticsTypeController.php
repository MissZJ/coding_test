<?php

namespace frontend\controllers;

use Yii;
use frontend\components\Controller2016;
use yii\filters\VerbFilter;
use yii\db\Query;

/**
 * AfterSalesLogisticsTypeController implements the CRUD actions for AfterSalesLogisticsType model.
 */
class AfterSalesLogisticsTypeController extends Controller2016
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                ],
            ],
        ];
    }
    
    /**
     * Lists all AfterSalesLogisticsType models.
     * @return mixed
     */
    public function actionIndex()
    {
        if(isset($_GET['type'])){
            $this->layout = false;
            $user_info = $this->user_info;
            $data = (new Query())->select('t1.*')->from('after_sales_logistics_type as t1')
            ->leftJoin('after_sales_logistics_type_city as t2','t1.id = t2.logistics_type_id')
            ->where(['t1.status'=>1,'t2.province'=>$user_info['province'],'t2.city'=>$user_info['city']])
            ->orderBy('t1.sort asc')
            ->one();

            return $this->render($_GET['type'],[
              'data' => $data
            ]);
        }else{
            return $this->redirect('http://www.51dh.com.cn');
        }
    }
}
