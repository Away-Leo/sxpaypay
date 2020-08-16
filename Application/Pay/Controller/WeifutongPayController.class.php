<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;

use Pay\Lib\Xingye\WeiFuTong\WFTRequest;
class WeifutongPayController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入威富通支付。。。。。。。");
        logwrite("参数为:".json_encode($array));
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $parameter = array(
            'code' => 'Weifutong', // 通道名称
            'title' => '威富通',
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
        $notifyurl = $unlockdomain . 'Pay_WeifutongPay_notifyurl.html'; //异步通知
        $callbackurl = $unlockdomain . 'Pay_WeifutongPay_callbackurl.html'; //返回通知
        logwrite("notifyurl=".$notifyurl);
        $data = array(
            'service' => 'unified',
            'sign_type' =>'MD5',
            'mch_id' => $return['mch_id'],
            'out_trade_no' => $return['orderid'],
            'body' => '订单-'.$return['orderid'],
            'total_fee' => $return['amount']*100,
            'mch_create_ip' => '47.115.207.211',
            'notify_url' => $notifyurl,
            'nonce_str' => $return['orderid'],
            'key' => $return['signkey'],
            'url'=>$return['gateway']
        );
//        $data = array(
//            'pay_memberid' => $return['mch_id'],
//            'pay_bankcode' => intval($return['chennel_code']),
//            'pay_notifyurl' => $notifyurl,
//            'pay_amount' => $return['amount'],
//            'pay_orderid' => $return['orderid'],
//            'pay_md5sign' => '',
//            'pay_callbackurl' => $callbackurl,
//            'pay_type' => 'json',
//            'pay_applydate' => date('y-m-d HH:mm:ss',time())
//        );
        $wft=new WFTRequest();
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求参数为->" . json_encode($data));
        $result = $wft->submitOrderInfo($data);
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . json_encode($result));
        if(empty($result['code_url'])){
            $this->showmessage("请求支付链接失败",$result);
            exit();
        }
        logwrite("url->" . $result['code_url']);
        //******************存入临时表**********************
        $tempdata=[
            'url'=>$result['code_img_url'],
            'oid'=>$return['orderid'],
            'jtype'=>0,
            'oriurl'=>$result['code_url'],
            'amount'=>$return['amount']
        ];
        $order_jump=M('Orderjump');
        $add_result=$order_jump->add($tempdata);
        if($add_result){
            echo $this->buildResponse($this->_site . 'Pay_WeifutongPay_topay.html?omega='.$return['orderid'],$array);
        }else{
            $this->showmessage("请求支付链接失败");
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
            $this->display('SysWechatH5/PayXingye');
        }
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
        logwrite("进入威富通通知地址==========================================");
        $wft=new WFTRequest();
        $orderid=$wft->getOrderId();
        logwrite("获得的订单ID为：".$orderid);
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $orderid])->find(); //获取订单信息
        if($wft->checkSignAndResult($order_info['key'])){
                $this->EditMoney($orderid, '', 0);
                echo ("success");
                exit();
        }else {
            logwrite("威富通通知签名失败");
            echo ("false");
            exit();
        }

    }

}
