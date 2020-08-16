<?php
ini_set('display_errors',1);
error_reporting(-1);
date_default_timezone_set('Asia/Shanghai');

function nextRandomOrderNo(){
    $t = date("YmdHis");
    $r = rand(100000,999999);
    return $t.$r;
}

function now(){
    $mt = microtime(true) * 1000;
    $mts = round($mt);
    return strval($mts);
}
function doSignWithMd5($principal, $credential){
    return md5($principal . $credential);
}
function doSignWithRsa($principal, $credential){
    openssl_sign($principal,$sign,$credential,OPENSSL_ALGO_SHA256);
    $sign = base64_encode($sign);
    $sign = str_replace('+','*',$sign);
    $sign = str_replace('/','-',$sign);
    $sign = str_replace('=','.',$sign);
    return $sign;
}
// 以下商户信息为测试商户演示作用，对接请自行替换
// 商户密钥
$apiKey = 'cc91590edb3bbb0fea9472fe06161610';
// 商户密钥安全码
$apiSecurity = '2eaf2f79460209bec95ade373a819810';
// 商户私钥文件，用于RSA签名
$privateKey = file_get_contents('./keys/private_key.pem');
// 订单号。根据业务逻辑生成的业务订单号
$orderNo = nextRandomOrderNo();
// 当前时间戳，精确到毫秒
$timestamp = now();
$order = [
    'timestamp' => $timestamp,
    'apiKey' => $apiKey,
    // 应用ID。由商户自定义
    'appId' => '123465',
    // 商户订单号，同一个应用ID下不能有重复订单号
    'outOrderNo' => $orderNo,
    // 订单标题
    'subject' => '测试订单',
    // 订单金额（单位：分）
    'amount' => '900',
    // 签名类型，支持（md5, hmac_md5, rsa）
    'signType' => 'rsa',
    'channel'=>'upacp_qr',
    'notifyUrl' => 'http://localhost/notify.php',
];
ksort($order);
$principal = http_build_query($order);
$principal = urldecode($principal);
$sign = '';
if ($order['signType'] == 'md5'){
    $sign = doSignWithMd5($principal, $apiSecurity);
}else if($order['signType'] == 'rsa'){
    $sign = doSignWithRsa($principal, $privateKey);
}
$order['sign'] = $sign;
$qs = http_build_query($order);
$apiHost = 'http://api.pay.leyyx.cn';
$api = $apiHost . '/api/v1/invoices';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_AUTOREFERER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");  
curl_setopt($ch, CURLOPT_POSTFIELDS, $qs);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$r = curl_exec($ch);
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$rJsonObj = json_decode($r);
if ($httpStatus != 200){
    exit('<script>alert("服务器错误");</script>');
}
$orderNo = $rJsonObj->orderNo;
$credential = $rJsonObj->credential;
if (empty($credential)){
    exit('<script>alert("服务器错误");</script>');
}
$resultUrl = $credential->resultUrl;
$resultBody = $credential->resultBody;
if(!empty($resultUrl)){
    header('Location: ' . $resultUrl);
    exit();
}
if(empty($resultBody)){
    exit('<script>alert("服务器错误");</script>');
}
echo '下单成功';
?>