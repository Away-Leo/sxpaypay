<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;
class SysAlipayH5Controller extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入自营支付宝支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_SysAlipayH5_notifyurl.html'; //异步通知
        $callbackurl = $this->_site . 'Pay_SysAlipayH5_callbackurl.html'; //返回通知

        $parameter = array(
            'code' => 'SysAlipayH5', // 通道名称
            'title' => '官方自营支付宝H5',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => $orderid,
            'body' => $body,
            'channel' => $array
        );

        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $data = array(
            'payType' => '2',
            'notifyurl' => urlencode($notifyurl),
            'returnurl' => urlencode($callbackurl),
            'amount' => $return['amount'],
            'pforder' => $return['orderid'],
            'sign' => '',
            'mch_id' => $return['mch_id'],
            'signkey' => $return['signkey'],
            'appid' => $return['appid'],
            'appsecret' => $return['appsecret']
        );
        ksort($data);
        $signText = '';
        foreach ($data as $k => $v) {
            if ($k == "sign" || $v == "") continue;

            $signText .= $k . $v;
        }
        $signText .= 'UzBGGGyyGogGAGakMAxXADYyuMcAg7J7';
        logwrite("signText=" . $signText);
        $data['sign'] = md5($signText);

        $url = 'http://154.221.255.2/api/Pay/pay';
//        $url = 'http://karl-leo.imwork.net/common/test.json';
        logwrite("data->" . json_encode($data));
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        $result = $this->http_post_json($url, json_encode($data));
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . $result);
        $resultobj = json_decode($result, true);
        logwrite("url->" . $resultobj['url']);
        //******************存入临时表**********************
        $tempdata=[
            'oid'=>$return['orderid'],
            'url'=>$resultobj['url']
        ];
        $order_jump=M('Orderjump');
        $add_result=$order_jump->add($tempdata);
        if($add_result){
            echo $this->_site . 'Pay_SysAlipayH5_topay.html?omega='.$return['orderid'];
        }else{
            $this->showmessage("请求支付链接失败");
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


    //同步通知
    public function callbackurl()
    {
        $Order      = M("Order");
       
        $pay_status = $Order->where(['pay_orderid' => $_REQUEST["out_trade_no"]])->getField("pay_status");
        if ($pay_status != 0) {
            $this->EditMoney($_REQUEST["out_trade_no"], '', 1);
        } else {
            exit("error");
        }
    }

    //异步通知
    public function notifyurl()
    {
//        $arr=$_POST;
//        $alipaySevice = new \AlipayTradeService($config);
//        $alipaySevice->writeLog(var_export($_POST,true));
//        $result = $alipaySevice->check($arr);





        logwrite("进入支付宝回调-------------------》");
        $response  = $_POST;
        logwrite("全部参数为----------》".json_encode($response));
        $sign      = $response['sign'];
        $sign_type = $response['sign_type'];
        unset($response['sign']);
        unset($response['sign_type']);
        $publiKey =""; // 密钥
        $m_Order    = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $response["out_trade_no"]])->find(); //获取订单信息
        if($order_info){
            $channel_acount=M("channel_account");
            $channel_account_result=$channel_acount->where(['channel_id'=>$order_info['channel_id']])->find();
            $publiKey=$channel_account_result['appsecret'];
        }
        logwrite("publiKey------------->".$publiKey);
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
            logwrite("支付宝验证签名错误");
            exit('error:check sign Fail!');
        }

    }

}
