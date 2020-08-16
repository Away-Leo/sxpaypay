<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;

class DarouPayController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        $orderid     = I("request.pay_orderid");
        $body        = I('request.pay_productname');
        $notifyurl   = $this->_site . 'Pay_DarouPay_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_DarouPay_callbackurl.html'; //返回通知

        $parameter = array(
            'code'         => 'DarouPay', // 通道名称
            'title'        => '大肉',
            'exchange'     => 1, // 金额比例
            'gateway'      => '',
            'orderid'      => '',
            'out_trade_id' => $orderid,
            'body'         => $body,
            'channel'      => $array,
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $params=[
            'mch_id'=>$return['mch_id'],
            'apiKey'=>$return['signkey'],
            'signType'=>'md5',
            'sign'=>'',
            'appId'=>$return['appid'],
            'outOrderNo'=>$return['orderid'],
            'amount'=>$return['amount']*100,
            'channel'=>'upacp_qr',
            'subject'=>'订单-'.$return['orderid'],
            'notifyUrl'=>$notifyurl
        ];
        ksort($params);
        $signText = '';
        foreach ($params as $k => $v) {
            if ($k == "sign" || $v == "") continue;

            $signText .= $k .'='.$v.'&';
        }
        $signText=$signText.$return['appsecret'];
        logwrite("signText=" . $signText);
        $params['sign'] = md5($signText);
        $url='http://api.pay.leyyx.cn/api/v1/invoices';
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time())."参数为：".json_encode($params));
        $result=$this->httpRequest($url,$params);
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . $result);
        $resultObj=json_decode($result,true);
        if($resultObj&&$resultObj['credential']!=''){
            $tempdata=[
                'oid'=>$return['orderid'],
                'url'=>$resultObj['credential']
            ];
            $order_jump=M('Orderjump');
            $add_result=$order_jump->add($tempdata);
            if($add_result){
                echo $this->_site . 'Pay_LvbeiUnionPay_topay.html?omega='.$return['orderid'];
            }else{
                $this->showmessage("请求支付链接失败");
            }
        }else{
            $this->showmessage("请求支付链接失败",$result);
        }
    }



    function getUnixTimestamp ()
    {
        list($s1, $s2) = explode(' ', microtime());
        return (float)sprintf('%.0f',(floatval($s1) + floatval($s2)) * 1000);
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

    //异步通知
    public function notifyurl()
    {
        $response  = $_POST;
        $publiKey = getKey($response["outOrderNo"]); // 密钥

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
            exit('error:check sign Fail!');
        }

    }

}
