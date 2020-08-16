<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;
class XingyeController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入兴业银行支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $pay_type = I('request.pay_type');
        $parameter = array(
            'code' => 'Xingye', // 通道名称
            'title' => '兴业支付',
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
        $notifyurl = $unlockdomain . 'Pay_Xingye_notifyurl.html'; //异步通知
        logwrite("notifyurl=".$notifyurl);
        $data = array(
            'account' => '15863148099',
            'payMoney' => $return['amount'],
            'body' => $return['orderid'],
            'lowOrderId' => $return['orderid'],
            'notifyUrl' => $notifyurl,
            'sign' => '',
        );
        ksort($data);
        $signText = '';
        foreach ($data as $k => $v) {
            if ($k == "sign" || $v == "") continue;

            $signText .= $k .'='.$v.'&';
        }
        $signText .= 'key=4f8341b8176472dcbb38518689d198a9';
        logwrite("signText=" . $signText);
        $data['sign'] = strtoupper(md5($signText));
        $request_data=["data"=>json_encode($data)];
//        $url = 'http://154.221.255.2/api/Pay/pay';
        $url = 'http://127.0.0.1:9999/getPayUrl';
//        $url = 'http://tgpay.833006.net/tgPosp/services/payApi/allQrcodePay';
//        $url = 'https://tgpay.833006.net/tgPosp/services/payApi/unifiedorder';
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        $result = $this->httpRequest($url, $request_data);
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . $result);
        $resultobj = json_decode($result, true);
        if($resultobj['status']=='100'||$resultobj['status']==100){
            logwrite("url->" . $resultobj['codeUrl']);
            //******************存入临时表**********************
            $tempdata=[
                'oid'=>$return['orderid'],
                'url'=>$resultobj['codeUrl'],
                'jtype'=>0
            ];
            $order_jump=M('Orderjump');
            $add_result=$order_jump->add($tempdata);
            if($add_result){
                echo $this->_site . 'Pay_Xingye_topay.html?omega='.$return['orderid'];
            }else{
                $this->showmessage("请求支付链接失败");
            }
        }
    }

    public function topay()
    {
        $oid=I("get.omega");
        logwrite("omege--------------->".$oid);
        $temporder=M('Orderjump')->where(['oid'=>$oid])->find();
        logwrite("temporder===============".json_encode($temporder));
        if($temporder){
            $this->assign([
                'jump_url' => $temporder['url']
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
        logwrite("进入自营支付宝通知地址==========================================");
        $response  = $_POST;
        $sign      = $response['sign'];
        $sign_type = $response['sign_type'];
        unset($response['sign']);
        unset($response['sign_type']);
        $publiKey = getSecret($response["out_trade_no"]); // 密钥

        ksort($response);
        $signData = '';
        foreach ($response as $key => $val) {
            $signData .= $key . '=' . $val . "&";
        }
        $signData = trim($signData, '&');
        //$checkResult = $aop->verify($signData,$sign,$publiKey,$sign_type);
        $res    = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($publiKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
        $result = (bool) openssl_verify($signData, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);

        if ($result) {
            if ($response['trade_status'] == 'TRADE_SUCCESS' || $response['trade_status'] == 'TRADE_FINISHED') {
                $this->EditMoney($response['out_trade_no'], '', 0);
                exit("success");
            }
        } else {
            logwrite("支付宝验证签名不通过======================================");
            exit('error:check sign Fail!');
        }

    }

}
