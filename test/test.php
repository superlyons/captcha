<?php
include_once("captcha.php");

$img=new captcha();
$captcha = $img->run('bulidCode.php', ['onClick'=>"refCaptcha(this);"]);

$capcode=$_POST['capcode'];
if(!empty($capcode)){
	$v=$img->validate($capcode,false);
	$msg= !$v ? "验证码输入错误! ,".$capcode : "验证码正确! ,".$capcode;
}


?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<form method="post">
<?=$msg?>
<input type="text" name="capcode"/><?=$captcha?>

</form>
<script>
function refCaptcha(e){
	var time=new Date().getTime();
	e.src="bulidCode.php?<?=captcha::REFRESH_GET_VAR?>=1&t="+time;
}
</script>