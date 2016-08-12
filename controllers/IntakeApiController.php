<?php
namespace frontend\controllers;
use common\models\stat\StatPvUvDetail;
use frontend\components\Controller2016;
use Yii;
use common\models\goods\GoodsIntakeInfo;
use common\models\goods\GoodsIntakeLog;
use common\models\user\Supplier;
use yii\helpers\Json;

class IntakeApiController extends Controller2016
{
	/**
	 * @inheritdoc
	 */
	public function behaviors() {
		return [];
	}
	
	/**
	 * @Title:beforeAction
	 * @Description:复写beforeAction
	 * @return:boolean || redirect
	 * @author:huaiyu.cui
	 * @date:2015-12-2 下午4:54:05
	 */
	public function beforeAction($action) {
		return true;
	}
	
	/**
	 * @return string
	 * @author jiangtao.ren
	 */
	public function actionIntakeDetail(){
		$out = [];
		if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
			$js_content = $_POST['content'];
			$time = $_POST['time'];
			$token = $_POST['token'];
			$date_time = strtotime($time);
			$content = json_decode($js_content);
			$token_str = md5($time).'66945330e992332fa0ff11d3ddbe8223';
			$token_strs = md5($token_str);
			if ($token_strs == $token) {
				$result = GoodsIntakeInfo::updateIntakeInfo($content);
				if (!empty($result)){
					$out['msg'] = '操作失败';
					$out['res'] = 100;
					foreach ($result as $key =>$value){
						if (isset($value['code'])){
							GoodsIntakeLog::SetIntakeLog($value['orderCode'], '入库失败', $value['code'].'入库失败');
						}else {
							GoodsIntakeLog::SetIntakeLog($value['orderCode'], '入库失败', '订单信息错误入库失败');
						}
					}
				}else {
					$out['msg'] = '操作成功';
					$out['res'] = 0;
				}
			} else {
				$out['res'] = 200;
				$out['msg'] = '密钥验证失败';
				GoodsIntakeLog::SetIntakeLog('11111', '密钥验证失败','密钥验证失败');
			}
		} else {
			$out['res'] = 300;
			$out['msg'] = '参数缺失';
			GoodsIntakeLog::SetIntakeLog('11111', '参数缺失', '参数缺失');
		}
		return json_encode($out);
	}
	
	/**
	 * @desc 关闭订单
	 * @return string
	 * @author jiangtao.ren
	 */
	public function actionCloseIntakeInfo(){
		$out = [];
		if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
			$js_content = $_POST['content'];
			$time = $_POST['time'];
			$token = $_POST['token'];
			$date_time = strtotime($time);
			$content = json_decode($js_content);
			$token_str = md5($time).'66945330e992332fa0ff11d3ddbe8223';
			$token_strs = md5($token_str);
			if ($token_strs == $token) {
				$result = GoodsIntakeInfo::closeIntakeOrder($content);
				if (!empty($result)){
					$out['res'] = 100;
					$out['msg'] = '关闭失败';
					foreach ($result as $key =>$value){
						GoodsIntakeLog::SetIntakeLog($value['orderCode'], '关闭订单'.$value['order_status'],'关闭订单失败');
					}
				}else {
					$out['res'] = 0;
					$out['msg'] = '关闭成功';
				}
			}else {
				$out['res'] = 200;
				$out['msg'] = '密钥验证失败';
			}
		}else {
			$out['res'] = 300;
			$out['msg'] = '参数缺少';
		}
		return json_encode($out);
	}
	
	/**
	 *@desc 同步供应商信息
	 *@author jiangtao.ren
	 */
	public function actionGetSupplierInfo(){
		$supplierInfo = $content = [];
		$token = $_POST['token'];
		$tokens = '66945330e992332fa0ff11d3ddbe8223';
		if (isset($token) && $token == $tokens){
			$supplierInfo = Supplier::getAllSupplierInfo();
			$content['supplier'] = $supplierInfo;
		}
		return json_encode($content);
	}

    /**
     * @desc 获取PU-UV点击统计数量
     */

    public function actionGetPvUvStatData(){
        $out = [];
        if (isset($_POST['content']) && isset($_POST['time']) && isset($_POST['token'])) {
            $time = $_POST['time'];
            $token = $_POST['token'];
            $content = Json::decode($_POST['content']);
            $token_str = md5($time).'66945330e992332fa0ff11d3ddbe8223';
            $token_strs = md5($token_str);
            if ($token_strs == $token) {
                if(isset($content['start_date']) && isset($content['end_date'])){
                    if(strtotime($content['start_date']) && strtotime($content['end_date'])){
                        $content['is_wsc'] = 0;
                        $pcData = StatPvUvDetail::getPvUvForApi($content);

                        $content['is_wsc'] = 1;
                        $wscData = StatPvUvDetail::getPvUvForApi($content);

                        $cacheData = [
                            'pc'=>$pcData,
                            'wsc'=>$wscData
                        ];
                        $out['data'] = $cacheData;
                        $out['res'] = 1;
                        $out['msg'] = 'success';
                    }else{
                        $out['res'] = 400;
                        $out['msg'] = '日期区间格式有误';
                    }
                }
            } else {
                $out['res'] = 200;
                $out['msg'] = '密钥验证失败';
            }
        } else {
            $out['res'] = 300;
            $out['msg'] = '参数缺失';
        }
        return json_encode($out);
    }
}