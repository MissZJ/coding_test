<?php
namespace frontend\controllers;
use frontend\components\Controller2016;
use Yii;
use common\models\other\OtherRegion;
use common\models\user\Pusher;
use yii\db\Query;
use common\models\user\ServiceFaq;


class CustomerServiceFaqController extends Controller2016
{
  
    /**
     *
     * @Title: actionList
     * @Description: 异步显示分页列表
     * @return: return_type
     * @author: zend.wang
     * @date: 2015-9-22下午2:49:50
     */
    public function actionList(){
        if(\Yii::$app->request->isAjax){
    
            $cid =  intval(f_get('cid',0));
            $page = intval(f_get('page',0));

            if(empty($cid) || empty($page)){
                $returnVal = ['status'=>102,'msg'=>'输入参数异常'];
            }
            $data = ServiceFaq::getFaqList($cid,$this->user_info['city'],$page);
            if(empty($data['list'])){
                $returnVal = ['status'=>101,'msg'=>'该分类暂无数据'];
            }
            else {
                $returnVal = ['status'=>100,'msg'=>'','data'=>$data];
            }
            return json_encode($returnVal);
        }
    }
    
    
    /**
     * 
     * @Title: actionGetCustomerService
     * @Description: 在线客服
     * @return: string
     * @author: yulong.wang
     * @date: 2015-12-30下午4:00:47
     */
    public function actionGetCustomerService() {
        $userId = \Yii::$app->user->id;
        $key = 'frontend-'.$userId.'-new_51dh3.0';
        $userInfo = f_c($key);
        $zeroTime = strtotime(date('Y-m-d 00:00:00',time()));
        $sixTime = strtotime(date('Y-m-d 06:00:00',time()));
        $elevenTime = strtotime(date('Y-m-d 11:00:00',time()));
        $thirteenTime = strtotime(date('Y-m-d 13:00:00',time()));
        $nineteenTime = strtotime(date('Y-m-d 19:00:00',time()));
        $twentyFourTime = strtotime(date('Y-m-d 23:59:59',time()));
        
        $remark = '老板，您今天在我要订货网订货了吗?';
        if (time() >= $zeroTime && time() < $sixTime) {
            $remark = '老板，夜间早点休息!';
        } elseif (time() >= $sixTime && time() < $elevenTime) {
            $remark = '老板，上午好,新的一天开始了!';
        } elseif (time() >= $elevenTime && time() < $thirteenTime) {
            $remark = '老板，中午好,别忘记吃午饭(⊙o⊙)哦!';
        } elseif (time() >= $thirteenTime && time() < $nineteenTime) {
            $remark = '老板，下午好,别忘记18点订货(⊙o⊙)哦!';
        }
        
        
        $pushMessage = Pusher::getExclusiveServicePusher($userInfo['city'],$userInfo['district']);
        if (!empty($pushMessage)) {
            $pusher = '<span style="color:#757576">您的区域经理是:'.mb_substr($pushMessage['pusher'],0,18).'-<font style="color:white;background:#FF7E20">'.$pushMessage['phone'].'</font></span>';
        } else {
            $pusher = '<span style="color:#757576">您所在的区域暂无区域经理</span>';
        }
        
        $data = [];
        $faqCategoryList = (new Query())->select(['id','title'])->from('user_service_faq_category')->where(['status'=>1])->orderBy('display_order desc,create_time desc')->limit(8)->all();
        foreach($faqCategoryList as $faq_category){
            $faq_list = ServiceFaq::getFaqList($faq_category['id'],$userInfo['city'],1);
            $faq_list['category'] = $faq_category['title'];
            $data[] = $faq_list;
        }
        
        if (!empty($data)) {
            $response =['status'=>1,'remark'=>$remark,'pusher'=>$pusher,'faq'=>$data];
        } else {
            $response =['status'=>0,'remark'=>$remark,'pusher'=>$pusher,'faq'=>$data];
        }
        return json_encode($response);
    }
    
    
    /**
     *
     * @Title: actionChat
     * @Description: 在线客服聊天
     * @return: return_type
     * @author: zend.wang
     * @date: 2015-9-9下午3:49:50
     */
    public function actionChat() {

        $id = intval(f_get('id',0));

        $meiqia_params=[['unitid'=>'55f7ad564eae35753300000a','specifyGr'=>1], ['unitid'=>'55dfc5534eae351a2b000017','specifyGr'=>1]];

        $pname = OtherRegion::getRegionNameById($this->user_info['province']);
        $cname = OtherRegion::getRegionNameById($this->user_info['city']);
        $dname= OtherRegion::getRegionNameById($this->user_info['district']);

        $this->user_info['address_full'] ="{$pname}{$cname}{$dname}{$this->user_info['address']}";

        return $this->renderPartial('chat',[
            'user_info'=>$this->user_info,
            'unitid' => $meiqia_params[$id]['unitid'],
            'specifyGr'=>$meiqia_params[$id]['specifyGr']
        ]);

    }

    /**
     * 美洽在线客服2016版本
     * @author: shihan.kang
     * @time: 2016年3月2日09:18:03
     */
    public function actionMeChat2016() {
        $id = intval(f_get('id',0));
        $mechat_params = [['entId'=>8280,'groupId'=>'73cfd5e988037b2e0e9b742623ed0294'],['entId'=>24102,'groupId'=>'1dda4790c7bc6eea1eaa6de7a580adad']];

        $pname = OtherRegion::getRegionNameById($this->user_info['province']);
        $cname = OtherRegion::getRegionNameById($this->user_info['city']);
        $dname= OtherRegion::getRegionNameById($this->user_info['district']);

        $this->user_info['address_full'] ="{$pname}{$cname}{$dname}{$this->user_info['address']}";
        return $this->renderPartial('chat2016',[
            'user_info'=>$this->user_info,
            'mechat_params' => $mechat_params[$id],
        ]);

    }

}
