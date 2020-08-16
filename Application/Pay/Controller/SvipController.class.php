<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;
class SvipController extends PayController
{
    public function __construct()
    {
        parent::__construct();
    }

    //支付
    public function Pay($array)
    {
        logwrite("进入Svip支付。。。。。。。");
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');
        $parameter = array(
            'code' => 'Svip', // 通道名称
            'title' => 'Svip',
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
        $notifyurl = $unlockdomain . 'Pay_Svip_notifyurl.html'; //异步通知
        $callbackurl = $unlockdomain . 'Pay_Svip_callbackurl.html'; //返回通知
        logwrite("notifyurl=".$notifyurl);
//        $data = array(
//            'merchantNum' => $return['mch_id'],
//            'payType' => $return['chennel_code'],
//            'notifyUrl' => $notifyurl,
//            'returnUrl' => $notifyurl,
//            'amount' => intval($return['amount']),
//            'orderNo' => $return['orderid'],
//            'sign' => '',
//            'attch' => '订单'.$return['orderid'],
//            'ip' => '111.111.111.111'
//        );

        $params = array(
            'notify_url'	=> $notifyurl,
            'return_url'	=> $callbackurl,
            'user_account'	=>	$return['mch_id'],
            'out_trade_no'	=> $return['orderid'],
            'payment_type'	=> $return['chennel_code'],
            'total_fee'		=> $return['amount'],
            'trade_time'	=> date('Y-m-d H:i:s', time()),
            'body'			=> '订单'.$return['orderid'],
        );

        $params['sign'] = $this->_make_sign($params, $return['signkey']);

        $url = $return['gateway'];
//        $url = 'http://karl-leo.imwork.net/common/test.json';
        logwrite("开始请求支付地址------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("地址为->" . $url);
        logwrite("请求参数为->" . json_encode($params));
        $result = $this->httpRequest($url, $params);
        logwrite("求支付地址结束------------------------>" . date('Y-m-d h:i:s', time()));
        logwrite("请求结果为------------------------>" . $result);
        //******************存入临时表**********************
        $tempdata=[
            'oid'=>$return['orderid'],
            'jtype'=>1,
            'oriurl'=>json_encode($params),
            'url'=>$url
        ];
        $order_jump=M('Orderjump');
        $add_result=$order_jump->add($tempdata);
        if($add_result){
            echo $this->_site . 'Pay_Svip_topay.html?omega='.$return['orderid'].'&jtype=1';
        }else{
            $this->showmessage("请求支付链接失败");
        }
    }

    public function topay()
    {
        $oid=I("get.omega");
        $jtype=I("get.jtype");
        logwrite("omege--------------->".$oid);
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
                    'jtype'=>$temporder['jtype'],
                    'oriurl'=>json_decode($temporder['oriurl'])
                ]);
                $this->display('SysWechatH5/PayHtml');
            }
    }

    function _make_sign($data, $key)
    {

        //签名步骤一：按字典序排序参数
        ksort($data);
        //签名步骤二：使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串
        $string = $this->_to_url_params($data);
        //签名步骤三：在string后加入KEY
        $string = $string . "&key=".$key;
        //签名步骤四：MD5加密
        $string = md5($string);
        //签名步骤五：所有字符转为大写
        $result = strtoupper($string);

        return $result;
    }

    function _to_url_params($data)
    {
        $buff = "";
        foreach ($data as $k => $v)
        {
            if($k != "sign" && $v != "" && !is_array($v)){
                $buff .= $k . "=" . $v . "&";
            }
        }

        $buff = trim($buff, "&");
        return $buff;
    }

    //同步通知
    public function callbackurl()
    {
        $this->display('SysWechatH5/PaySuccess');
    }

    //异步通知
    public function notifyurl()
    {
        logwrite("进入Svip通知地址==========================================");
        $input = file_get_contents('php://input');
        $response=json_decode($input,true);
//        $response  = $_POST;
        $sign      = $response['sign'];
        logwrite("参数为：".json_encode($response));
        $m_Order = M("Order");
        $order_info = $m_Order->where(['pay_orderid' => $response['out_trade_no']])->find(); //获取订单信息

        if($this->_make_sign($response, $order_info['key'])==$sign){
            if(strtoupper($response['status'])=="SUCCESS"){
                $this->EditMoney($response['out_trade_no'], '', 0);
                exit("SUCCESS");
            }else{
                logwrite("未支付======================================");
                exit('error:check sign Fail!');
            }
        }else {
            logwrite("验证签名不通过======================================");
            exit('error:check sign Fail!');
        }

    }

}
