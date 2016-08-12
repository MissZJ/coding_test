<?php
namespace frontend\controllers;

use Yii;
use yii\web\Controller;
/**
 * 电视墙专用控制器，用于页面调整
 * @package frontend\controllers
 */
class TvWallController extends Controller{
    public function actionIndex(){
        $this->redirect("http://excel.51dh.com.cn");
    }
    public function actionTop(){
        $this->redirect("http://excel.51dh.com.cn/tv-wall/top");
    }
}