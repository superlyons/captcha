<?
include_once("captcha.php");
$img = new lyons\lib\captcha();
$str = $img->run('bulidCode.php', ['onClick'=>"refCaptcha(this);"]);
echo $str;

$answer = trim(fgets(STDIN));
?>