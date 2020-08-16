<?php
namespace Pay\Lib\Sanhe;
$ua = $_SERVER['HTTP_USER_AGENT'];

class Util
{
	public static function ip()
	{
		if (getenv("HTTP_CLIENT_IP")) {
			$ip = getenv("HTTP_CLIENT_IP");
		} elseif (getenv("HTTP_X_FORWARDED_FOR")) {
			$ip = getenv("HTTP_X_FORWARDED_FOR");
		} elseif (getenv("REMOTE_ADDR")) {
			$ip = getenv("REMOTE_ADDR");
		} else $ip = "Unknow";

		return $ip;
	}

	// 获取浏览器信息
	public static function getBrowser($agent)
	{
		$regs = [];
		if (preg_match('/MSIE\s([^\s|;]+)/i', $agent, $regs)) {
			$outputer = 'IE浏览器';
		} else if (preg_match('/FireFox\/([^\s]+)/i', $agent, $regs)) {
			$str1 = explode('Firefox/', $regs[0]);
			$FireFox_vern = explode('.', $str1[1]);
			$outputer = '火狐浏览器 ' . $FireFox_vern[0];
		} else if (preg_match('/Maxthon([\d]*)\/([^\s]+)/i', $agent, $regs)) {
			$str1 = explode('Maxthon/', $agent);
			$Maxthon_vern = explode('.', $str1[1]);
			$outputer = '傲游浏览器 ' . $Maxthon_vern[0];
		} else if (preg_match('#SE 2([a-zA-Z0-9.]+)#i', $agent, $regs)) {
			$outputer = '搜狗浏览器';
		} else if (preg_match('#360([a-zA-Z0-9.]+)#i', $agent, $regs)) {
			$outputer = '360浏览器';
		} else if (preg_match('/Edge([\d]*)\/([^\s]+)/i', $agent, $regs)) {
			$str1 = explode('Edge/', $regs[0]);
			$Edge_vern = explode('.', $str1[1]);
			$outputer = 'Edge ' . $Edge_vern[0];
		} else if (preg_match('/UC/i', $agent)) {
			$str1 = explode('rowser/',  $agent);
			$UCBrowser_vern = explode('.', $str1[1]);
			$outputer = 'UC浏览器 ' . $UCBrowser_vern[0];
		} else if (preg_match('/MicroMesseng/i', $agent, $regs)) {
			$outputer = '微信内嵌浏览器';
		} else if (preg_match('/WeiBo/i', $agent, $regs)) {
			$outputer = '微博内嵌浏览器';
		} else if (preg_match('/QQ/i', $agent, $regs) || preg_match('/QQBrowser\/([^\s]+)/i', $agent, $regs)) {
			$str1 = explode('rowser/',  $agent);
			$QQ_vern = explode('.', $str1[1]);
			$outputer = 'QQ浏览器 ' . $QQ_vern[0];
		} else if (preg_match('/BIDU/i', $agent, $regs)) {
			$outputer = '百度浏览器';
		} else if (preg_match('/LBBROWSER/i', $agent, $regs)) {
			$outputer = '猎豹浏览器';
		} else if (preg_match('/TheWorld/i', $agent, $regs)) {
			$outputer = '世界之窗浏览器';
		} else if (preg_match('/XiaoMi/i', $agent, $regs)) {
			$outputer = '小米浏览器';
		} else if (preg_match('/UBrowser/i', $agent, $regs)) {
			$str1 = explode('rowser/',  $agent);
			$UCBrowser_vern = explode('.', $str1[1]);
			$outputer = 'UC浏览器 ' . $UCBrowser_vern[0];
		} else if (preg_match('/mailapp/i', $agent, $regs)) {
			$outputer = 'email内嵌浏览器';
		} else if (preg_match('/2345Explorer/i', $agent, $regs)) {
			$outputer = '2345浏览器';
		} else if (preg_match('/Sleipnir/i', $agent, $regs)) {
			$outputer = '神马浏览器';
		} else if (preg_match('/YaBrowser/i', $agent, $regs)) {
			$outputer = 'Yandex浏览器';
		} else if (preg_match('/Opera[\s|\/]([^\s]+)/i', $agent, $regs)) {
			$outputer = 'Opera浏览器';
		} else if (preg_match('/MZBrowser/i', $agent, $regs)) {
			$outputer = '魅族浏览器';
		} else if (preg_match('/VivoBrowser/i', $agent, $regs)) {
			$outputer = 'vivo浏览器';
		} else if (preg_match('/Quark/i', $agent, $regs)) {
			$outputer = '夸克浏览器';
		} else if (preg_match('/mixia/i', $agent, $regs)) {
			$outputer = '米侠浏览器';
		} else if (preg_match('/fusion/i', $agent, $regs)) {
			$outputer = '客户端';
		} else if (preg_match('/CoolMarket/i', $agent, $regs)) {
			$outputer = '基安内置浏览器';
		} else if (preg_match('/Thunder/i', $agent, $regs)) {
			$outputer = '迅雷内置浏览器';
		} else if (preg_match('/Chrome([\d]*)\/([^\s]+)/i', $agent, $regs)) {
			$str1 = explode('Chrome/', $agent);
			$chrome_vern = explode('.', $str1[1]);
			$outputer = 'Chrome ' . $chrome_vern[0];
		} else if (preg_match('/safari\/([^\s]+)/i', $agent, $regs)) {
			$str1 = explode('Version/',  $agent);
			$safari_vern = explode('.', $str1[1]);
			$outputer = 'Safari ' . $safari_vern[0];
		} else {
			$outputer = 'unknown';
		}
		return $outputer;
	}
	// 获取操作系统信息
	public static function getOs($agent)
	{
		$os = false;

		if (preg_match('/win/i', $agent)) {
			if (preg_match('/nt 6.0/i', $agent)) {
				$os = 'windows Vista';
			} else if (preg_match('/nt 6.1/i', $agent)) {
				$os = 'windows 7';
			} else if (preg_match('/nt 6.2/i', $agent)) {
				$os = 'windows 8';
			} else if (preg_match('/nt 6.3/i', $agent)) {
				$os = 'windows 8.1';
			} else if (preg_match('/nt 5.1/i', $agent)) {
				$os = 'windows XP';
			} else if (preg_match('/nt 10.0/i', $agent)) {
				$os = 'windows 10';
			} else {
				$os = 'windows';
			}
		} else if (preg_match('/android/i', $agent)) {
			if (preg_match('/android 9/i', $agent)) {
				$os = 'android P';
			} else if (preg_match('/android 8/i', $agent)) {
				$os = 'android O';
			} else if (preg_match('/android 7/i', $agent)) {
				$os = 'android N';
			} else if (preg_match('/android 6/i', $agent)) {
				$os = 'android M';
			} else if (preg_match('/android 5/i', $agent)) {
				$os = 'android L';
			} else {
				$os = 'android';
			}
		} else if (preg_match('/ubuntu/i', $agent)) {
			$os = 'linux';
		} else if (preg_match('/linux/i', $agent)) {
			$os = 'linux';
		} else if (preg_match('/iPhone/i', $agent)) {
			$os = 'ios';
		} else if (preg_match('/mac/i', $agent)) {
			$os = 'macos';
		} else if (preg_match('/fusion/i', $agent)) {
			$os = 'android';
		} else {
			$os = 'unknown';
		}
		return $os;
	}
}