<?php
namespace Pay\Lib\Sanhe;
use http\Exception\InvalidArgumentException;
use Think\Exception;

/*********
 * HTApi
 * 海天支付API调用类
 * 请求性函数的签名流程已封装在invoke函数中
 *********/
class HTApi{
	private $merchant;
	private $appkey;
	private $gateway;

	private $_lastRequest;

	public function __construct($merchant, $appkey, $gateway)
	{
		$this->merchant = $merchant;
		$this->appkey = $appkey;
		$this->gateway = $gateway;
	}
	/***********************************************************
	 * 签名验证
	 * @params array 要签名的数据，关联数组
	 * @sign string 要验证的签名结果
	 *
	 * @return boolean
	 ***********************************************************/
	public function checkSign($params, $sign)
	{
		// sign字段不加入签名，若包含则去除此字段
		if (isset($params['sign'])) {
			unset($params['sign']);
		}
		ksort($params);
		//urldecode 防止有中文时被编码，造成验签失败
		$signStr = urldecode(http_build_query($params)) . $this->appkey;
		return md5($signStr)  == $sign;
	}

	/***********************************************************
	 * 查询可用银行列表
	 *
	 * @return array(object) 可用银行列表 详情请参考API文档
	 ***********************************************************/
	public function getBanks()
	{
		//array 类型参数列表
		$paras = array();
		$paras['merchant'] = $this->merchant;

		$json = $this->invoke('getBanks', $paras);
		$obj = json_decode($json);
		if (!$obj) { //网络原因造成请求失败
			throw new Exception('请求失败');
		}

		if ($obj->error->code !== 200) { //API返回错误信息
			throw new Exception($obj->error->message);
		}

		return $obj->result;
	}
	/***********************************************************
	 * 查询可用金额列表
	 *
	 * @return array(int) 可用金额列表
	 ***********************************************************/
	public function getAmountList()
	{
		//array 类型参数列表
		$paras = array();
		$paras['merchant'] = $this->merchant;

		$json = $this->invoke('getAmountList', $paras);
		$obj = json_decode($json);
		if (!$obj) { //网络原因造成请求失败
			throw new Exception('请求失败');
		}

		if ($obj->error->code !== 200) { //API返回错误信息
			throw new Exception($obj->error->message);
		}

		return $obj->result;
	}
	/***********************************************************
	 * 创建订单
	 *
	 * @orderNo string 客户订单号
	 * @amount decimal(11,2) 订单金额
	 * @notify string 订单支付成功后的回调通知url
	 * @payType string 支付类型 (qr|h5|we|up|rp)
	 * @isManual int 是否补单 (0|1)
	 * @extra json string
	 *
	 * @return object
	 * object.img 为base64编码的png图片
	 * object.lin 为h5打开的链接，仅H5支付请求时包含此字段
	 ***********************************************************/
	public function createOrder($orderNo, $amount, $notify, $payType = 'qr', $ip = '', $browser = '', $os = '', $ua = '', $isManual = 0, $extra = '')
	{
		//array 类型参数列表
		$paras = array();
		$paras['merchant'] = $this->merchant;
		$paras['orderNo'] = $orderNo;
		$paras['amount'] = floatval(number_format($amount, 2, '.', ''));
		$paras['notify'] = $notify;
		$paras['payType'] = $payType;
		$paras['ip'] = $ip;
		$paras['browser'] = $browser;
		$paras['os'] = $os;
		$paras['ua'] = $ua;
		$paras['isManual'] = $isManual;
		$paras['extra'] = $extra;

		$json = $this->invoke('createOrder', $paras);
//		var_dump($json);

		$obj = json_decode($json);
		if (!$obj) { //网络原因造成请求失败
			throw new Exception('请求失败');
		}

		if ($obj->error->code !== 200) { //API返回错误信息
			throw new Exception($obj->error->message);
		}

		return $obj->result;
	}
	/***********************************************************
	 * @return array 返回上一次的请求和返回内容，供排错及log使用
	 * 建议操作：把请求内容和返回内容log到file或者db，以备检查
	 ***********************************************************/
	public function lastRequest()
	{
		return $this->_lastRequest;
	}
	/***********************************************************
	 * API调用帮助函数
	 *
	 * @access protected
	 * @param string $method 远程函数名
	 * @param array $paras 参数列表，关联数组
	 *
	 * @return string(valid json)
	 ***********************************************************/
	protected function invoke($method, $paras)
	{
		$request = new \stdClass();
		$request->method = $method;
		if ($paras == null) {
			$request->params = array();
		} elseif (is_array($paras)) {
			$request->params = $paras;
		} else {
			throw new InvalidArgumentException('$paras must be array or object');
		}
		// 签名流程
		{
			ksort($paras);
			//urldecode 防止有中文时被编码，造成验签失败
			$signStr = urldecode(http_build_query($paras)) . $this->appkey;
			$request->sign = md5($signStr);
		}
		$post = json_encode($request);
		$resp = $this->doHttpsPost($this->gateway, $post);

		$this->_lastRequest = array();
		$this->_lastRequest['request'] = $post;
		$this->_lastRequest['response'] = $resp;
		return $resp;
	}
	// post json data over https
	private function doHttpsPost($url, $json)
	{
		$curl = curl_init(); // 启动一个CURL会话

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 设置超时限制防止死循环
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $json);

		$headers = array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($json)
		);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 跳过证书检查
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);  // 从证书中检查SSL加密算法是否存在
		$json = curl_exec($curl);     //返回api的json对象
		//关闭URL请求

		curl_close($curl);
		return $json;
	}
}