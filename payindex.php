<?php
error_reporting(0);
header("Content-type: text/html; charset=utf-8");
$pay_memberid = "200399771";   //商户后台API管理获取
$pay_orderid = 'E'.date("YmdHis").rand(100000,999999);    //订单号
$pay_amount = "0.01";    //交易金额
$pay_applydate = date("Y-m-d H:i:s");  //订单时间
$pay_notifyurl = "https://waimai.pingxundata.com/demo/server.php";   //服务端返回地址
$pay_callbackurl = "https://waimai.pingxundata.com/demo/page.php";  //页面跳转返回地址
$Md5key = "ojjdma0c406bp51ipcpk04925qx9jt0r";   //商户后台API管理获取
$tjurl = "http://pay1.pingxundata.com/Pay_SysWechatH5_Pay.html";   //提交地址
$pay_bankcode = "919"; //支付宝扫码  //商户后台通道费率页 获取银行编码
$native = array(
    "pay_memberid" => $pay_memberid,
    "pay_orderid" => $pay_orderid,
    "pay_amount" => $pay_amount,
    "pay_applydate" => $pay_applydate,
    "pay_bankcode" => $pay_bankcode,
    "pay_notifyurl" => urlencode($pay_notifyurl),
    "pay_callbackurl" =>urlencode($pay_callbackurl) ,
);
ksort($native);
$md5str = "";
foreach ($native as $key => $val) {
    $md5str = $md5str . $key . "=" . $val . "&";
}
//echo($md5str . "key=" . $Md5key);
$sign = strtoupper(md5($md5str . "key=" . $Md5key));
$native["pay_md5sign"] = $sign;
$native['pay_attach'] = "1234|456";
$native['pay_productname'] ='团购商品';


$orderid =$pay_orderid ;
        

$data = array(
    'payType' => '2',
    'notifyurl' =>urlencode($pay_notifyurl),
    'returnurl' => urlencode($pay_callbackurl),
    'amount' => '0.01',
    'pforder' => $orderid,
    'sign' => '',
);

ksort($data);

$signText = '';
foreach($data as $k => $v){
    if($k == "sign" || $v == "") continue;

    $signText .= $k.$v;
}
$signText .= 'UzBGGGyyGogGAGakMAxXADYyuMcAg7J7';
echo($signText.'<br>');
$data['sign'] = md5($signText);
echo json_encode($data);

?>

