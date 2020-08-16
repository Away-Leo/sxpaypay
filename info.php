<?php

$a = array("website"=>"","web_logo"=>"/Upload/SystemConfig/5dc127ef1a2b1.png", "company"=>"","tel"=>"","wx_kf"=>"","wx_kf_qrcode"=>"","qq_kf"=>"","qq_kf_qrcode"=>"","bfb_1"=>"","bfb_2"=>"","bfb_3"=>"","charge"=>"","zx_bfb_1"=>0,"zx_bfb_2"=>0,"fx_bfb"=>0,"zd_tx"=>30);
 
//序列化数组
$s = serialize($a);
echo $s;
?>