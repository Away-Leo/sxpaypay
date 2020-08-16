<?php
namespace Pay\Lib\wanke;
class AlipaySubmit {

	function check($arr){
	    $result = $this->rsaCheckV1($arr);
	    return $result;
	}
	function rsaCheckV1($params) {
	    return $this->getSignContent($params);
	}
	function getSignContent($params) {
	    ksort($params);
	    $stringToBeSigned = "";
	    $i = 0;
	    foreach ($params as $k => $v) {
	        if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
	            // 转换成目标字符集
	            // $v = $this->characet($v, $this->postCharset);
	            if ($i == 0) {
	                $stringToBeSigned .= "$k" . "=" . "$v";
	            } else {
	                $stringToBeSigned .= "&" . "$k" . "=" . "$v";
	            }
	            $i++;
	        }
	    }
	    unset ($k, $v);
	    return $stringToBeSigned;
	}

	function checkEmpty($value) {
	    if (!isset($value))
	        return true;
	    if ($value === null)
	        return true;
	    if (trim($value) === "")
	        return true;

	    return false;
	}

	function buildRequestForm($params,$url){
	    ksort($params);
	    $stringToBeSigned = "<form id='requestForm' name='requestForm' action='".$url."' method='POST'>";
	    foreach ($params as $k => $v) {
	        if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
	            $stringToBeSigned .= "<input type='hidden' name='".$k."' value='".$v."' />";
	        }
	    }
	    $stringToBeSigned = $stringToBeSigned."<input type='submit' value='确定' style='display:none;'></form>";
	    $stringToBeSigned = $stringToBeSigned."<script>document.forms['requestForm'].submit();</script>";
	    unset ($k, $v);
	    return $stringToBeSigned;
	}
}