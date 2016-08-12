<?php

namespace frontend\controllers;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use frontend\components\Controller2016;
use common\models\other\GroundBagCity;
use common\models\other\MallRnav;
use common\models\other\AdInfo;
use common\models\other\OtherMainMenu;
use common\models\other\OtherHandpick;
use common\models\goods\BaseGoods;
use common\models\goods\SupplierGoods;
use common\models\goods\Module;
use yii\data\Pagination;
use common\models\goods\Type;
use common\models\goods\Photo;
use yii\widgets\LinkPager;
use common\models\seas\SeasGoodsType;
use common\models\other\OtherRegion;
use common\models\goods\Depot;
use common\models\user\UserInvoice;
use common\models\order\OrderMinusGoods;
use common\models\markting\LadderGroup;
use common\models\order\OmsInfo;
use common\models\goods\SelfGoods;
use common\models\markting\CouponUser;
use common\models\seas\SeasUserAddress;
use common\models\markting\CouponInfo;
use common\models\markting\CouponSelect;
use common\models\goods\RecordSeries;
use yii\web\UploadedFile;
use yii\helpers\Json;
use common\models\seas\SeasOrderUserInfo;

class SeasGoodsController extends Controller2016 {
    /*
     * @title:index
     * @description:跨境专区首页
     * @author:lxzmy
     * @date:2016-5-27 10:25
     * return data
     * $type json
     */

    public function actionIndex() {
        $seas_type = f_get('type_id', 2);   //  2保税  3直邮
        if($seas_type==2){
            $this->view->title = '51订货网-海外保税专区';
        }else if($seas_type==3){
            $this->view->title = '51订货网-海外直邮专区';
        }else{
            $this->view->title = '51订货网-海外购专区';
        }
        header("Content-Type: text/html; charset=UTF-8");
        $mall_id = f_get('mall_id', 3); //默认母婴
        $d = 1;
        $lm = f_post('lm');  //类目ID
        $brand_id = f_post('b_id');  //品牌ID
        $type_id = f_post('t_id');  //品类ID
        $s_type = f_post('seas_type'); //post 传过来
        $no_goods = "<div class='no_goods'><img src='/images/mf.gif'><div class='none_txt none_padding'><p><h5>抱歉,没有找到您所筛选的商品</h5></p></div></div>";
        $user_id = \Yii::$app->user->id;
        $user_info = f_c('frontend-' . $user_id . '-new_51dh3.0');
        //用户信息
        if (!$user_info) {
            $user_info = UserMember::find()->where(['id' => $user_id])->asArray()->one();
        }
        $city_id = $user_info['city'];
        //用户状态校验
        if ($user_info['user_status'] != 1) {
            $view = \Yii::$app->view->render('@common/widgets/alert/Alert.php', [
                'type' => 'warn',
                'message' => '帐号状态异常,即将退出登录',
                'is_next' => 'yes',
                'next_url' => '/site/logout',
            ]);
            return $view;
            exit;
        }
        $time = 3600;
        //广告
        if ($seas_type == 2) {
            $ad = f_c('Baby_unit_ad2_' . $city_id);
            if ($ad === false) {
                $ad = [];
                $ad['banner'] = AdInfo::getAd(147, $city_id);    //大banner图 2
                $ad['ad_top'] = AdInfo::getAd(98, $city_id, 1);   //顶部广告位
                f_c('Baby_unit_ad2_' . $city_id, $ad, $time);
            }
        } else {
            $ad = f_c('Baby_unit_ad3_' . $city_id);
            if ($ad === false) {
                $ad = [];
                $ad['banner'] = AdInfo::getAd(148, $city_id);    //大banner图 直邮 2
                $ad['ad_top'] = AdInfo::getAd(98, $city_id, 1);   //顶部广告位
                f_c('Baby_unit_ad3_' . $city_id, $ad, $time);
            }
        }

        //横向导航栏
        $menu_heng = f_c('Baby_unit_menuheng_' . $city_id);
        if ($menu_heng === false) {
            $menu_heng = OtherMainMenu::getMainMenu($mall_id, $city_id,8);
            f_c('Baby_unit_menuheng_' . $city_id, $menu_heng, $time);
        }
        //一周精选
        if ($seas_type == 2) {
            $one_week = f_c('Baby_unit_one_week2_' . $city_id);
        } else {
            $one_week = f_c('Baby_unit_one_week3_' . $city_id);
        }
        $arr = array();
        if (!$one_week) {
            if ($seas_type == 2) {
                $one_week = OtherHandpick::getWeekInfoForSeas(16,$city_id, $mall_id);   //16 指定一个目录下 保税
            } else {
                $one_week = OtherHandpick::getWeekInfoForSeas(35,$city_id, $mall_id);   //35 指定一个目录下 直邮
            }
            foreach ($one_week as $k => $v) {
                if ($v['goods_type'] == 1) {//基础商品
                    $base_id = $v['goods_id'];
                    $cover = BaseGoods::find()->select('cover_id')->where(['id' => $base_id])->scalar();
                    $photo = Photo::find()->where(['id' => $cover])->asArray()->one();
                    if ($photo) {
                        $one_week[$k]['img_url'] = $photo['img_url'];
                    } else {
                        $one_week[$k]['img_url'] = 'undefined.jpg';
                    }
                    $one_week[$k]['link_url'] = "/supplier-goods/index?id=" . $base_id;
                } else { //供应商商品
                    $goods_id = $v['goods_id'];
                    $cover = SupplierGoods::find()->select('cover_id')->where(['id' => $goods_id])->scalar();
                    $photo = Photo::find()->where(['id' => $cover])->asArray()->one();
                    if ($photo) {
                        $one_week[$k]['img_url'] = $photo['img_url'];
                    } else {
                        $one_week[$k]['img_url'] = 'undefined.jpg';
                    }
                    $one_week[$k]['link_url'] = "/site/detail?id=" . $goods_id;
                }
            }
            if ($seas_type == 2) {
                f_c('Baby_unit_one_week2_' . $city_id, $one_week, $time);
            } else {
                f_c('Baby_unit_one_week3_' . $city_id, $one_week, $time);
            }
        }
        //根据mall_id 获取类目名称 
            $leimu = Module::getLeiMuByMallId($mall_id, 1);
        //获取品类
        if ((isset($lm) && $lm > 0) || (isset($brand_id) && $brand_id > 0) || (isset($type_id) && $type_id > 0)) {
            if ($lm) {
                $cata_lm = $lm;  //筛选类目ID
                $lm = 't8.id=' . $cata_lm;
            } else {
                $lm = '1=' . $d;
            }
            if ($brand_id) {
                $cata_b = $brand_id;
                $brand_id = 't2.brand_id=' . $cata_b;
            } else {
                $cata_b = 0;
                $brand_id = '1=' . $d;
            }
            if ($type_id) {
                $cata_t = $type_id;
                $type_id = 't2.type_id=' . $cata_t;
            } else {
                $cata_t = 0;
                $type_id = '1=' . $d;
            }
            //根据类目 获取分类 and 品牌
            $typs = '';
            $brands = '';
            $brands_s = '';  //品类id下的品牌 替换之前的
            if ($cata_lm) {
                $typeInfo = SeasGoodsType::getTypeBrandByLm($cata_lm, $mall_id);   //根据类目获取品类、品牌
                if ($typeInfo) {
                    foreach ($typeInfo as $k => $v) {
                        $typs .="<a><span t_id=" . $v['id'] . ">" . $v['name'] . "</span></a>";
                        $fenlei_id[] = $v['id'];
                    }
                    if ($fenlei_id) {
                        $brandInfo = SeasGoodsType::getBrandByType($fenlei_id, $mall_id);  //根据品类获取品牌
                        if ($brandInfo) {
                            foreach ($brandInfo as $k => $v) {
                                $brands .="<a><span b_id=" . $v['id'] . ">" . $v['name'] . "</span></a>";
                            }
                        } else {
                            $brands = '';
                        }
                    } else {
                        $typs = '';
                    }
                } else {
                    $typs = '';
                }
            }
            //根据品类ID获取品牌
            if ($cata_t) {
                $brandInfo = SeasGoodsType::getBrandByType($cata_t, $mall_id);
                if ($brandInfo) {
                    foreach ($brandInfo as $k => $v) {
                        $brands_s .="<a><span b_id=" . $v['id'] . ">" . $v['name'] . "</span></a>";
                    }
                } else {
                    $brands_s = '';
                }
            }
            //商品列表信息
            $goodsInfo = SeasGoodsType::seasGoodsInfo($lm, $brand_id, $type_id, $mall_id, $city_id,$s_type);  //跨境商品
            $html = '';
            $res = [];
            if ($goodsInfo['goodsInfo']) {  //拼接
                foreach ($goodsInfo['goodsInfo'] as $k => $v) {
                    $html .="<li>";
                    $html .="<a href='/goods/detail?id=" . $v['id'] . "&&mall_id=" . $v['mall_id'] . "' target='_blank' class='goodsImg'>";
                    $html .="<img src='" . f_picByCoverId($v['cover_id']) . "@194h_194w_2eQ90'>";
                    $html .="</a>";
                    $html .="<div class='goodsInfo'>";
                    $html .="<a href='/goods/detail?id=" . $v['id'] . "&&mall_id=" . $v['mall_id'] . "' target='_blank'>" . $v['goods_name'] . "</a>";
                    $html .="<div class='mt-10'>";
                    $html .="<span class='cor-red fs-18 f-l'>¥" . $v['price'] . " </span>";
                    $html .="<span class='f-r cor-grey5 pt-5'>建议价:¥" . $v['price'] . "</span>";
                    $html .="</div>";
                    $html .="</div>";
                    $html .="</li>";
                }
            }
            //$page_web = LinkPager::widget(['pagination' => $goodsInfo['pages']]);   //前台分页
            $res['code'] = 1;
            $res['goodsInfo'] = $html ? $html : $no_goods;  //商品信息
            $res['typeInfo'] = $typs;   //品类信息
            $res['brandInfo'] = $brands;   //品牌信息
            $res['brands'] = $brands_s;
            $res['lm_id']=$goodsInfo['lm_id'];   //类目ID
            $res['b_id']=$goodsInfo['b_id'];   // 品牌ID
            $res['t_id']=$goodsInfo['t_id'];   // 分类 ID
            //$res['page'] = $page_web;
            $res['seas_type']=$s_type;  // 类型 2保税 3直邮
            return json_encode($res);
        } else {
            //全体清空筛选流程
            if (($lm != null && $lm == 0) && ($brand_id != null && $brand_id == 0) && ($type_id != null && $type_id == 0)) {
                $goodsInfo = SeasGoodsType::seasGoodsInfo('', '', '', $mall_id, $city_id,$s_type);
                //json 数据 goodsInfo
                $html = '';
                $res = [];
                if ($goodsInfo['goodsInfo']) {
                    foreach ($goodsInfo['goodsInfo'] as $k => $v) {
                        $html .="<li>";
                        $html .="<a href='/supplier-goods/index?id=" . $v['id'] . "&&mall_id=" . $v['mall_id'] . "' target='_blank' class='goodsImg'>";
                        $html .="<img src='" . f_picByCoverId($v['cover_id']) . "@194h_194w_2eQ90'>";
                        $html .="</a>";
                        $html .="<div class='goodsInfo'>";
                        $html .="<a href='/supplier-goods/index?id=" . $v['id'] . "&&mall_id=" . $v['mall_id'] . "' target='_blank'>" . $v['goods_name'] . "</a>";
                        $html .="<div class='mt-10'>";
                        $html .="<span class='cor-red fs-18 f-l'>¥" . $v['price'] . " </span>";
                        $html .="<span class='f-r cor-grey5 pt-5'>建议价:¥" . $v['price'] . "</span>";
                        $html .="</div>";
                        $html .="</div>";
                        $html .="</li>";
                    }
                }
                //$page_web = LinkPager::widget(['pagination' => $goodsInfo['pages']]);   //前台分页
                $res['code'] = 1;
                $res['goodsInfo'] = $html ? $html : $no_goods;  //商品信息
                //$res['page'] = $page_web;
                $res['seas_type']=$s_type;
                return json_encode($res);
            } else {
                //默认商品列表信息
                $goodsInfo = SeasGoodsType::seasGoodsInfo('', '', '', $mall_id, $city_id,$seas_type); //$seas_type 默认 url 传值
                //f_d($leimu);
                return $this->render('index', [
                            'ad' => $ad,
                            'user_info' => $user_info,
                            'menu_heng' => $menu_heng,
                            'one_week' => $one_week,
                            'leimu' => $leimu,
                            'goodsInfo' => $goodsInfo['goodsInfo'],
                            'page' => $goodsInfo['pages'],
                            'seas_type'=>$seas_type,
                ]);
            }
        }
    }

    /*
     * @title:seasGoodsSettlement
     * @description:保税专区商品结算页
     * @author:lxzmy
     * @date:2016-6-1 09:32
     * return $data
     */

    public function actionSettlement() {
        $this->view->title = '51订货网-海外购结算页';
        header("Content-Type: text/html; charset=UTF-8");
        $user_id = \Yii::$app->user->id;
        $user_info = f_c('frontend-' . $user_id . '-new_51dh3.0');
        //用户信息
        if (!$user_info) {
            $user_info = UserMember::find()->where(['id' => $user_id])->asArray()->one();
        }
        $city_id = $user_info['city'];
        //用户状态校验
        if ($user_info['user_status'] != 1) {
            $view = \Yii::$app->view->render('@common/widgets/alert/Alert.php', [
                'type' => 'warn',
                'message' => '帐号状态异常,即将退出登录',
                'is_next' => 'yes',
                'next_url' => '/site/logout',
            ]);
            return $view;
            exit;
        }

        $seas_address = array();
        $seas_address = SeasUserAddress::getSeasAddress2($user_id, isset($_GET['search']) ? $_GET['search'] : '');
        $seas_province = OtherRegion::getRegion(1);

        $time = 3600;
        //广告
        $ad = f_c('Baby_unit_ad_' . $city_id);
        if ($ad === false) {
            $ad = [];
            $ad['ad_top'] = AdInfo::getAd(98, $city_id, 1);   //顶部广告位
            f_c('Baby_unit_ad_' . $city_id, $ad, $time);
        }
        //收货地址
        $address = [];
        $address_moren = 0;
        $address = SeasUserAddress::find()->where(['user_id' => $user_id, 'status' => 0])->asArray()->all();  //消费者地址
        $address_chang = SeasUserAddress::find()->where(['user_id' => $user_id, 'status' => 1])->asArray()->all();  //常用者地址
        foreach ($address as $key => $value) {
            $address[$key]['details'] = OtherRegion::getRegionName($value['province'])['region_name'];
            $address[$key]['details'] .= OtherRegion::getRegionName($value['city'])['region_name'];
            $address[$key]['details'] .= OtherRegion::getRegionName($value['district'])['region_name'];
            $address[$key]['details'] .= $value['address'];
            if ($value['status'] == 1) {
                $address_moren = $value['id'];
            }
        }
        //支付方式
        $pay_type = [1, 4];  //在线支付
        $pay_way = \common\models\order\OrderPayWay::find()->where(['id' => $pay_type])->all();
        //报价页提交过来的直接购买商品 价格和数量
        $seas_gooods_id = 0;
        $seas_gooods_num = 0;
        $seas_gooods_id = f_post('seas_gooods_id');
        $seas_gooods_num = f_post('seas_gooods_num');
        if ($seas_gooods_id && $seas_gooods_num) {
            $data = [$seas_gooods_id => $seas_gooods_num];
        } else {
            //订单信息
            $data = f_post('data', []); //购物车内的主键一维数组 id=>num 
            $data = array_filter($data);  //值为空的数组 过滤掉
        }
        if (!$data) {
            return $this->goBack();
        }
        $goods = [];
        $ys_goods = []; //预售
        $flag = 0;

        $is_invoice = 0; //开票
        $invoice = [];
        $invoice_num = 0;
        $invoice_status = 0;
        //获取所有goods数组
        $goods_id_array = [];

        foreach ($data as $key => $value) {
            $goods_id_array[$key] = $value;
        }
        $yfb_open = 0;
        $user_yfb_open = (isset($this->user_info['yfb_open']) && $this->user_info['yfb_open']) ? 1 : 0;
        $preGoodsInfo = [];
        foreach ($data as $key_goods_id => $value) {
            $g = SupplierGoods::find()
                            ->select('goods_supplier_goods.*,b.is_invoice,t3.id as is_ys,t3.begin_time as ys_begin_time,t3.end_time as ys_end_time')
                            ->leftJoin('user_supplier as b', 'goods_supplier_goods.supplier_id=b.id')
                            ->leftJoin('goods_presell as t3', 'goods_supplier_goods.id=t3.goods_id')
                            ->where(['goods_supplier_goods.id' => $key_goods_id])
                            ->asArray()->one();
            $d = Depot::find()->where(['id' => $g['depot_id']])->asArray()->one();
            $g['num'] = $value;
            $g['goods_name'] = preg_replace("/\"/","&quot;",preg_replace("/'/",'&#039;',$g['goods_name']));
            //立减单价金额
            $minus_price = OrderMinusGoods::getMinusPrice($g['id'], $user_info['city']);
            //同一阶梯组的商品总数量
            $num_group = LadderGroup::getGroupNum($g['id'], $goods_id_array, $user_info['city']);

            $g['price'] = f_price($g['id'], $num_group, $user_info['city'], 1) - $minus_price;
            $g['minus_price'] = $minus_price;
            $g['depot_nature'] = $d['depot_nature'];
            $g['goods_id'] = $g['id'];
            //屏蔽POS机货到付款
            if ($g['type_id'] == 65) {
                $flag += 1;
            }

            //上下架 回收站
            if ($g['status'] == 0 || $g['enable'] != 1 || $g['is_deleted'] == 1) {
                return Yii::$app->view->render('@common/widgets/alert/Alert.php', [
                            'type' => 'warn',
                            'message' => '商品已下架,点击确定返回重新选购',
                            'is_next' => 'yes',
                            'next_url' => '/seas-goods/index',
                ]);
                exit;
            }

            //是否含有开发票的供应商商品
            if ($g['is_invoice'] == 2) {
                $is_invoice += 1;
                //获取用户发票信息
                $invoice = UserInvoice::find()->where(['user_id' => $user_id])->orderBy('status desc')->asArray()->all();
                $invoice_num = count($invoice);
                if ($invoice) {
                    foreach ($invoice as $vo) {
                        if ($vo['status'] == 2) {
                            $invoice_status = $vo['id'];
                        }
                    }
                }
            }
            unset($g['keywords']);
            unset($g['tips']);

            //如果是预售商品，此商品走预售拆单逻辑
            if ($g['is_ys'] && time() >= strtotime($g['ys_begin_time']) && time() <= strtotime($g['ys_end_time'])) {
                $ys_index = "ys_" . $g['id'];
                $ys_goods[$ys_index][] = $g;
            } else {
                $goods[] = $g;
            }


            //如果是预存宝商品，预存宝状态为1，渲染页支付方式去除预存宝支付
            if ($g['type_id'] == 217) {
                $yfb_open = 1;
            }

            $preGoodsInfo[] = [
                'goods_id' => $g['id'],
                'goods_num' => $g['num'],
                'goods_price' => $g['price']
            ];
        }

        $separateInfo = OmsInfo::separateDepot($goods);
        //f_d($separateInfo);
        $inDepot = isset($separateInfo['inDepot']) && is_array($separateInfo['inDepot']) ? $separateInfo['inDepot'] : [];
        $outDepot = isset($separateInfo['outDepot']) && is_array($separateInfo['outDepot']) ? $separateInfo['outDepot'] : [];
        //然后在仓的根据仓库depot_id,根据仓库拆单
        $inDepotArr = OmsInfo::depotById($inDepot);
        //然后揽件，根据supplier_id拆单
        $outDepotArr = OmsInfo::depotByGys($outDepot);
        $goods_data = [];
        //组装数据返回
        $order_data = array_merge($inDepotArr, $outDepotArr, $ys_goods);

        //拆单 一个商品一个订单
        foreach ($order_data as $key => $val) {
            //$depName = $key;
            foreach ($val as $k => $v) {
                $goods_data[$k][] = $v;
            }
        }
        $order_data = $goods_data;

        $order_temp = [];

        foreach ($order_data as $key => $value) {  //订单个数
            $k = 'order_num_' . $key;
            $order_temp[$k] = $value;
        }
        $order_data = $order_temp;
        //处理数据
        $total_goods_price = 0;
        $record_price = 0;
        $types = [];
        $typePrice = [];
        foreach ($order_data as $key => $value) {
            foreach ($value as $k => $v) {
                $order_data[$key][$k]['img_url'] = Photo::find()->select('img_url')->where(['id' => $v['cover_id']])->scalar();
                $types[] = $v['type_id'];
                if (!isset($typePrice[$v['type_id']]))
                    $typePrice[$v['type_id']] = 0.00;
                $typePrice[$v['type_id']] += $v['price'] * $v['num'];
                $total_goods_price += $v['price'] * $v['num'];
                //获取商品仓库 是否收取录串手续费
                $province = SelfGoods::getProvinceByGoodsId($v['goods_id']);  //为自备机并且返回省份
                if ($province) {
                    $series_price = RecordSeries::getSeriesByProvince($province);  //返回手续费接口
                    if ($series_price) {
                        $order_data[$key][$k]['series_price'] = $series_price * $v['num']; //列表使用
                        $record_price += $series_price * $v['num'];
                    } else {
                        $order_data[$key][$k]['series_price'] = '';
                    }
                } else {
                    $order_data[$key][$k]['series_price'] = '';
                }
            }
        }

        //运费接口
        //$yunfei_price = OmsInfo::getYunfei($types,$total_goods_price);
        $yunfei_price = OmsInfo::getNewYunfei($typePrice);

        //获取所有优惠券
        $coupon_youxiao = [];
        $coupon_wuxiao = [];
        $coupons = CouponUser::getAll($user_id);

        if ($coupons) {
            foreach ($order_data as $order_num => $od) {
                foreach ($coupons as $key => $value) {
                    $rs = CouponInfo::mayUse($od, $value['coupon_id']);
                    if ($rs['status'] == 1) {
                        $coupon_youxiao[$value['id']]['order_num'] = $order_num;
                        unset($coupons[$key]);
                    } else {
                        $coupons[$key]['msg'] = $rs['msg'];
                    }
                }
            }
        }
        foreach ($coupon_youxiao as $key => $value) {
            $u_info = CouponUser::find()->where(['id' => $key])->asArray()->one();
// 			f_d($u_info);
            $c_info = CouponInfo::find()->where(['id' => $u_info['coupon_id']])->asArray()->one();
            $coupon_youxiao[$key]['start_time'] = $u_info['start_time'];
            $coupon_youxiao[$key]['end_time'] = $u_info['end_time'];
            $coupon_youxiao[$key]['amount'] = $c_info['amount'];
            $coupon_youxiao[$key]['coupon_name'] = $c_info['coupon_name'];
            $coupon_youxiao[$key]['type'] = CouponSelect::getTypeName($c_info['type_id']);
        }

        $coupon_wuxiao = $coupons;
        foreach ($coupon_wuxiao as $key => $value) {
            $c_info = CouponInfo::find()->where(['id' => $value['coupon_id']])->asArray()->one();
            $coupon_wuxiao[$key]['coupon_name'] = $c_info['coupon_name'];
            $coupon_wuxiao[$key]['type'] = CouponSelect::getTypeName($c_info['type_id']);
            $coupon_wuxiao[$key]['amount'] = $c_info['amount'];
        }
        $this->layout = '_blank';
        return $this->render('settlement', [
                    'ad' => $ad,
                    'user_info' => $user_info,
                    'address' => $address,
                    'address_chang' => $address_chang,
                    'pay_way_info' => $pay_way,
                    'order_data' => $order_data,
                    'yunfei' => $yunfei_price,
                    'total_goods_price' => $total_goods_price,
                    'record_price' => $record_price,
                    'coupon_wuxiao' => $coupon_wuxiao,
                    'coupon_youxiao' => $coupon_youxiao,
                    'shop_car_ids' => [],
                    'user_info' => $this->user_info,
                    'is_invoice' => $is_invoice,
                    'invoice' => $invoice,
                    'invoice_num' => $invoice_num,
                    'invoice_status' => $invoice_status,
                    'goods_yfb_open' => $yfb_open,
                    'is_service' => $user_info['is_service'],
                    'all_address' => $seas_address['address'], //全部海外地址
                    'address_num' => $seas_address['count'],
                    'province' => $seas_province,
        ]);
    }

    /**
     * @description:增加消费者地址
     * @return: return_type
     * @author: changhaijun
     * @date: 2016年5月31日下午2:50:33
     * @review_user:
     */
    public function actionAddSeasAddress() {
        if (isset($_POST['receive_name']) && isset($_POST['province']) && isset($_POST['city']) && isset($_POST['district']) && isset($_POST['address']) && isset($_POST['phone']) && isset($_POST['idnum'])&& isset($_POST['idimg0'])&& isset($_POST['idimg1'])) {
            
            $params = array();
            $params['user_name'] = $_POST['receive_name'];
            $params['phone'] = $_POST['phone'];
            $params['identidy'] = $_POST['idnum'];
            $params['province'] = $_POST['province'];
            $params['city'] = $_POST['city'];
            $params['district'] = $_POST['district'];
            $params['address'] = $_POST['address'];
            $params['user_id'] = $this->user_info['id'];
            $params['idimg0'] = $_POST['idimg0'];
            $params['idimg1'] = $_POST['idimg1'];
            $params['add_time'] =date('Y-m-d H:i:s');
            $res = SeasUserAddress::addSeasAddress($params);
            if ($res) {
                $res = 1;
            } else {
                $res = 2;
            }
            return $res;
        }
    }
    
    /**
     * @Title: actionAjaxUploadPic
     * @Description: Ajax保存上传图片到服务器
     * @return: return_type
     * @author: wangmingcha
     * @modify:lxzmy
     */
    public function actionAjaxUploadPic(){
        $ajaxData = array();
        $fileKey = \Yii::$app->request->post('fileKey');
        try{
            $fileInstance = UploadedFile::getInstanceByName($fileKey);
            if (is_object($fileInstance)) {
                $file_size = $fileInstance->size/1024;
                if($file_size>1024*5){
                    f_d("图片大小不能超过1M");
                }

                $being_timestamp = 1206576000;
                $suffix_len = 3;
                $time = explode(' ', microtime());
                $id = ($time[1] - $being_timestamp) . sprintf('%06u', substr($time[0], 2, 6));
                if ($suffix_len > 0) {
                    $id .= substr(sprintf('%010u', mt_rand()), 0, $suffix_len);
                }

                $fileName = $id.".".$fileInstance->getExtension();
                $allFileName = \Yii::$app->basePath.'/uploads/'.$fileName;
                $fileInstance->saveAs($allFileName);
                $img_url = f_remote_upload($allFileName);
                //$img_url =$fileInstance->saveAs($allFileName);
                if($img_url){
                    $ajaxData['pic'] = $img_url;
                    //$ajaxData['pic'] = $allFileName;
                    $ajaxData['status'] = 'success';
                }
            }
        }catch (Exception $e){
            echo $e->getMessage();
        }
        echo Json::encode($ajaxData);
        \Yii::$app->end();
    }

    /**
     * @Title: actionDeletePic
     * @Description: 删除图片时触发逻辑，目前为空,日后如有需要再补充
     * @return: return_type
     * @author: wangmingcha
     * @modify:lxzmy
     */
    public function actionDeletePic(){
        //删除静态图片逻辑 @todo
        echo Json::encode([]);
        \Yii::$app->end();
    }
    
    /*
     * @title: 限购跨境订单金额每人一天不超过两千元
     * @description: checkOrderPrice
     * @author:lxzmy
     * @date:2016-6-27 15:21
     */
    public function actionCheckOrderPrice(){
        $res =0;
        $now_begin = date("Y-m-d 00:00:00");  //当天 开始
        $now_end = date('Y-m-d 23:59:59');   // 当天 结束
        $seas_addres = f_post('seas_addres');
        $total_ptice = f_post('total_ptice',0); //购买金额
        if($seas_addres){
            $arr = SeasUserAddress::find()->where(['id'=>$seas_addres])->asArray()->one();
            if($arr){
                //判断是否有身份证照片 没有不让购买
                if(!$arr['idimg0']&&!$arr['idimg1']){
                    $res=4;
                    return $res;  //没有身份证照片 则返回
                }
                //每个身份证当日数据库购买金额
                $map = SeasOrderUserInfo::find()
                        ->where(['seas_address_id'=>$arr['id'],'identidy'=>$arr['identidy']])
                        ->andWhere(['between','add_time',$now_begin,$now_end])->sum('order_price');
                if($map+$total_ptice>SeasOrderUserInfo::$seas_price){
                    $res=2; //每人每日限购不能超过2000 
                }else{
                    $res=1; // 正常购买
                }
            }else{
                $res=3;
            }
        }else{
            $res =0;
        }
        return $res;
    }
    
    
    /*
     * @title:海外购地址设置为默认收货地址
     * @desc:setDefaultSeasAddress
     * @author:lxzmy
     * @date:2016-7-13 11:12
     */
    public function actionSetDefaultSeasAddress(){
        $res = 0;
        $address_id = f_post('address_id',0);
        $user_id = \Yii::$app->user->id;
        $arr = SeasUserAddress::find()->where(['id'=>$address_id,'user_id'=>$user_id])->asArray()->one();
        if($arr['is_default']==0){
            $address_info = SeasUserAddress::find()->where(['user_id'=>$user_id])->asArray()->all();
            if($address_info){
                SeasUserAddress::updateAll(['is_default'=>0],['user_id'=>$user_id]); // 全部更新为0
//                if($bool){
                     $map = SeasUserAddress::updateAll(['is_default'=>1],['id'=>$address_id,'user_id'=>$user_id]);
                     if($map){
                         $res = 1;
                     }else{
                         $res = 3; //设置失败
                     }
//                }else{
//                    $res = 4; //更新默认失败
//                }
            }else{
                $res=2;  //没有海外地址
            }
        }else{
            $res=5; //已经是默认地址
        }
        return $res;
    }

}
