<?php
/**
 * Created by PhpStorm.
 * User: wanmin
 * Date: 16/3/29
 * Time: 下午4:36
 */

namespace frontend\controllers;

use frontend\components\Controller2016;
use Yii;
use common\models\user\StoreMall;
use common\models\shopPrice\ShopPrice;


class PriceSummaryController extends Controller2016
{

    /**
     * 主页
    */
    public function actionIndex()
    {
        $user_info = $this->user_info;
        $user_id = $user_info['id'];
        $mall_info = storeMall::getMalls($user_id);
        $permit_mall =[];

        if($mall_info)
        {
            $permit_mall = $mall_info['yes_malls'];
        }
        //获取所有的允许的商城
        return $this->render('index',['permit_mall' => $permit_mall]);

    }


    /**
     * 获取报价单下载地址
     *
    */
    public function actionGetUrl()
    {
        $mall_id = f_post("mall_id");
        $city_id = $this->user_info['city'];
        $url = ShopPrice::getLink($city_id,$mall_id);
        echo json_encode($url);
    }



}