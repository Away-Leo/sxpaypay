<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;

use Pay\Lib\Xingye\WeiFuTong\Utils;
use Pay\Lib\Xingye\WeiFuTong\payclasses\ClientResponseHandler;
use Pay\Lib\Xingye\WeiFuTong\payclasses\PayHttpClient;
use Pay\Lib\Xingye\WeiFuTong\payclasses\RequestHandler;

class OtherController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入其他支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $pay_type = I('request.pay_type');
        $parameter = array(
            'code' => 'Other', // 通道名称
            'title' => '花花',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body' => $body,
            'channel' => $array
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $unlockdomain=$return['unlockdomain'];
        if($unlockdomain==null||$unlockdomain==""){
            $unlockdomain=$this->_site;
        }
//        $notifyurl = $unlockdomain . 'Pay_Xingye_notifyurl.html'; //异步通知
        $notifyurl = 'http://pay.yanwujing.cn/Pay_Other_notifyurl.html'; //异步通知
        logwrite("notifyurl=".$notifyurl);
        $return_array = [
            'pay_memberid' => $return['mch_id'], //測試
            'pay_orderid' => $return['orderid'],
            'pay_applydate' => date('Y-m-d H:i:s'),
            'pay_bankcode' => '911',
            'pay_notifyurl' => $notifyurl,
            'pay_callbackurl' => $notifyurl,   //页面跳转通知(必填)，但用不到
            'pay_amount' => $return['amount'],
        ];

        $return_array['pay_type'] = 'json';
        $return_array['pay_md5sign'] = '';
        ksort($return_array);
        $signText = '';
        foreach ($return_array as $k => $v) {
            if ($v == ""||$k=="pay_md5sign") continue;

            $signText .= $k .'='.$v.'&';
        }
        $signText .= 'key='.$return['signkey'];
        logwrite("signText=" . $signText);
        $return_array['pay_md5sign'] = strtoupper(md5($signText));
        $url = 'https://juhe.osat.asia/Pay_Index.html';
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("参数为------------------------>" . json_encode($return_array));
        $result = $this->httpRequest($url,$return_array);
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . $result);
        $resultobj = json_decode($result, true);
        if(!empty($resultobj['url'])){
            logwrite("url->" . $resultobj['url']);
            //******************存入临时表**********************
            $tempdata=[
                'oid'=>$return['orderid'],
                'url'=>$resultobj['url'],
                'amount'=>$return['amount']
            ];
            $order_jump=M('Orderjump');
            $add_result=$order_jump->add($tempdata);
            if($add_result){
                echo $this->_site . 'Pay_Other_topay.html?omega='.$return['orderid'];
            }else{
                $this->showmessage("请求支付链接失败");
            }
        }else{
            $this->showmessage("请求支付链接失败",$resultobj);
        }
    }
    public function topay()
    {
        $oid=I("get.omega");
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            $device='IOS';
        }else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')){
            $device='Android';
        }else{
            $device='other';
        }
        logwrite("omege--------------->".$oid."=device=".$device);
        $clientip=getIp();
        M('Order')->where(['pay_orderid' => $oid])->save(['device' => $device,'client_ip'=>$clientip]);
        $temporder=M('Orderjump')->where(['oid'=>$oid])->find();
        logwrite("temporder===============".json_encode($temporder));
        if($temporder){
            $this->assign([
                'jump_url' => $temporder['url'],
                'amount' => $temporder['amount'],
            ]);
            $this->display('SysWechatH5/Pay');
        }
    }

    public function topaybank()
    {
//        $oid=I("get.omega");
//        logwrite("omege--------------->".$oid);
//        $temporder=M('Orderjump')->where(['oid'=>$oid])->find();
//        logwrite("temporder===============".json_encode($temporder));
//        if($temporder){
//            $this->assign([
//                'jump_url' => $temporder['url']
//            ]);
            $this->display('SysWechatH5/PayBank');
//        }
    }

    //同步通知
    public function callbackurl()
    {
//        $Order      = M("Order");
//
//        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["out_trade_no"]])->getField("pay_status");
//        if ($pay_status != 0) {
//            $this->EditMoney($_REQUEST["out_trade_no"], '', 1);
//        } else {
//            exit("error");
//        }
    }

    //异步通知
    public function notifyurl()
    {
        logwrite("进入其他通知地址==========================================");
        $response=$_POST;
        logwrite("所有回调参数为：".json_encode($response));
        $sign=$response['sign'];
        $orderId=$response['orderid'];
        //获取订单信息
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $orderId])->find(); //获取订单信息
        if($order_info){
            $singkey=$order_info['key'];
            ksort($response);
            logwrite("此订单KEY为：".$singkey);
            logwrite("收到的参数：".json_encode($response));
            $signData = '';
            foreach ($response as $key => $val) {
                if ($key == "sign" || $val == "") continue;
                $signData .= $key . '=' . $val . "&";
            }
            $signData.="key=".$singkey;
            if(strtoupper(md5($signData))==$sign){
                logwrite("签名通过！！！！！！！！！！！！！！！！！！！！");
                $state=$response['returncode'];
                if($state=='00'){
                    $this->EditMoney($orderId, '', 0);
                    echo ("OK");
                    exit();
                }
            }else{
                logwrite("验证签名不通过======================================");
                echo "FALSE";
                exit();
            }
        }else{
            logwrite("通知未查询到该订单======================================");
            echo "FALSE";
            exit();
        }
    }

}
