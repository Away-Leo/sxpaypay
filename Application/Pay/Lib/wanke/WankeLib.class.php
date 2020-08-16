<?php


namespace Pay\Lib\wanke;


class WankeLib{

    #TODO  接口地址
    private static $url = 'http://.open/pdd/';
    # TODO appId
    private static $appId = '202001141765583737';

    # 下单
    public static function setOrder($para){
        $url = $para['url'];

        $params = [
            'money' =>  $para['money'],
            'outTradeNo' =>  $para['outTradeNo'],
            'userAgent' =>  "AlipayClient",
            'appId' =>  $para['appId'],
            'notifyUrl' =>  $para['notifyUrl'],
            'channelType' =>  $para['channelType'],
        ];

        $sign = self::getSign($params,$para['key']);

        if (false === $sign)
            return array('code'=>2001,'msg'=>'签名失败');

        $params['sign'] = $sign;

        logwrite("万科请求参数为：".json_encode($params));
        $res = self::curl_request($url, $params);
        logwrite("万科请求结果为：".$res);
        if (!$res)
        return array('code'=>2001,'msg'=>'请求失败'.json_encode($res));

        $res = json_decode($res, true);
        # 验签
        if (is_array($res) && !empty($res) && isset($res['result']['url']) && !empty($res['result']['url'])) {
//            $resSign = $res['result']['sign'];
//            $veriResult = self::verify($res['result'], $resSign,$para['platformKey']);
//            logwrite("验证签名结果为:".$veriResult);
//            if ($veriResult)
                return array('code'=>'success','url'=>$res['result']['url']);
        }else{
            return array('code'=>2001,'msg'=>'请求失败'.json_encode($res));
        }
    }

    # 查询订单
    public function getOrder()
    {
        $url = self::$url . "searchOrder";

        $params = [
            'outTradeNo' =>  "1234567",
            'appId' =>  self::$appId,
        ];

        $sign = self::getSign($params);
        if (false === $sign)
            return "签名失败";

        $params['sign'] = $sign;

        $res = self::curl_request($url, $params);

        $res = json_decode($res, true);

        # 验签
        if (is_array($res) && isset($res['result']) && isset($res['result']['sign'])) {
            $verifyData = [];
            if (isset($res['result']['orderSn']) && $res['result']['orderSn']) $verifyData['orderSn'] = $res['result']['orderSn'];
            if (isset($res['result']['outTradeNo']) && $res['result']['outTradeNo']) $verifyData['outTradeNo'] = $res['result']['outTradeNo'];
            if (isset($res['result']['userAgent']) && $res['result']['userAgent']) $verifyData['userAgent'] = $res['result']['userAgent'];
            if (isset($res['result']['appId']) && $res['result']['appId']) $verifyData['appId'] = $res['result']['appId'];
            if (isset($res['result']['money']) && $res['result']['money']) $verifyData['money'] = $res['result']['money'];
            if (isset($res['result']['addTime']) && $res['result']['addTime']) $verifyData['addTime'] = $res['result']['addTime'];
            if (isset($res['result']['deliveryTime']) && $res['result']['deliveryTime']) $verifyData['deliveryTime'] = $res['result']['deliveryTime'];
            if (isset($res['result']['status']) && $res['result']['status']) $verifyData['status'] = $res['result']['status'];
            if (isset($res['result']['respMsg']) && $res['result']['respMsg']) $verifyData['respMsg'] = $res['result']['respMsg'];
            if (isset($res['result']['respCode']) && $res['result']['respCode']) $verifyData['respCode'] = $res['result']['respCode'];

            $res = self::verify($verifyData, $res['result']['sign']);

            if ($res)
                return json_encode(['code'=>2000,'msg'=>'操作成功', 'data'=>$res['result']]);
        }

        return json_encode(['code'=>2001, 'msg' => isset($res['meesage']) ? $res['meesage'] : '操作失败']);

    }

    # 回调
    public static function notify($publicKey){
        # TODO 记日志 自己处理
//        file_put_contents( 'notify_log_'.date('Ymd') . '.log', date('Y-m-d H:i:s') . '-收到回调-' . json_encode($_POST) . PHP_EOL, FILE_APPEND );

        # 验签
        if (isset($_POST['sign']) && (2 === intval($_POST['status']) || 10 === intval($_POST['status']) || 11 === intval($_POST['status']))) {
            $resSign = $_POST['sign'];
            unset($_POST['sign']);
            # 验签
            $res = self::verify($_POST, $resSign,$publicKey);

            if (!$res) {
//                # TODO 验签失败 写日志 失败逻辑 自己处理
//                file_put_contents('notify_log_'.date('Ymd') . '.log','验签失败 data ：【'.json_encode($_POST).'】'. PHP_EOL, FILE_APPEND );
//                echo '验签失败';
//                exit;
                return false;

            } else {
//                # TODO 验签成功 业务逻辑写在这里
//                file_put_contents('notify_log_'.date('Ymd') . '.log','回调成功 data ：【'.json_encode($_POST).'】'. PHP_EOL, FILE_APPEND );
//                echo 'success';
//                exit;
                return true;
            }
        }
    }


    # 加签
    private static function getSign($params,$privateKey)
    {
        if (!$params || !is_array($params))
            return false;

        $params['charset'] = "utf-8";
        ksort($params);
        $privateKeyHeader = "-----BEGIN RSA PRIVATE KEY-----\n";
        # TODO 私钥
        $privateKeyContent = $privateKey;
        $privateKeyContent = wordwrap($privateKeyContent, 64, "\n", true);
        $privateKeyEnd = "\n-----END RSA PRIVATE KEY-----";

        $privateKey = $privateKeyHeader . $privateKeyContent . $privateKeyEnd;


        $keyStr = '';
        foreach ($params as $key => $value) {
            if (empty($keyStr))
                $keyStr = $key . '=' . $value;
            else
                $keyStr .= '&' . $key . '=' . $value;
        }

        if (!$keyStr)
            return false;

        try {
            $key = openssl_get_privatekey($privateKey);
            openssl_sign($keyStr, $signature, $key, "SHA256");
            openssl_free_key($key);
            $sign = base64_encode($signature);
            return $sign ? $sign : false;
        } catch (\Exception $e) {

            return false;
        }
    }

    //验签
    private static function verify($params, $returnSign,$publicKey){

        if (!$params || !is_array($params))
            return false;

        $params['charset'] = "utf-8";
        ksort($params);
        $publicKeyHeader = "-----BEGIN PUBLIC KEY-----\n";
        # TODO 公钥
        $publicKeyContent = $publicKey;
        $publicKeyContent = wordwrap($publicKeyContent, 64, "\n", true);
        $publicKeyEnd = "\n-----END PUBLIC KEY-----";

        $publicKey = $publicKeyHeader . $publicKeyContent . $publicKeyEnd;

        $keyStr = '';
        foreach ($params as $key => $value) {
            if($key=='sign'||$value==""||$key=='charset')continue;
            if (empty($keyStr))
                $keyStr = $key . '=' . $value;
            else
                $keyStr .= '&' . $key . '=' . $value;
        }

        if (!$keyStr)
            return false;

        try {
            logwrite("开始验证签名=".$returnSign."\n".$keyStr);
            logwrite("publicKey=".$publicKey);
            $res    = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($publicKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
            $result = (bool) openssl_verify($keyStr, base64_decode($returnSign), $res, OPENSSL_ALGO_SHA256);
            logwrite("验证签名结果为=".$result);
            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    //参数1：访问的URL，参数2：post数据
    public static function curl_request($url,$post=[]){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_POSTFIELDS, $post); // Post提交的数据包
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回

        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_error($curl);
        }
        curl_close($curl);
        return $data;
    }

}