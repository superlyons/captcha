<?php
defined('CAPTCHA_ROOT') or define('CAPTCHA_ROOT', dirname(__FILE__));

class captcha{

	public $bulidCaptchaUrl;
	public $template = "<div id='captcha'>{image}</div>";
	public $imageOptions=[];

	public static function checkRequirements()
	{
	    if (extension_loaded('imagick')) {
	        $imagick = new \Imagick();
	        $imagickFormats = $imagick->queryFormats('PNG');
	        if (in_array('PNG', $imagickFormats)) {
	            return 'imagick';
	        }
	    }
	    if (extension_loaded('gd')) {
	        $gdInfo = gd_info();
	        if (!empty($gdInfo['FreeType Support'])) {
	            return 'gd';
	        }
	    }
	    return false;
	}

	public function run($bulidCaptchaUrl, $options=[], $tmp=null){
		$tmp= isset($tmp) ? $tmp : $this->template;
		$options = isset($options) ? $options : $this->imageOptions;
		$options["src"] = $bulidCaptchaUrl;
		$html="<img".$this->renderTagAttributes($options)."/>";
		return strtr($this->template, [
	            '{image}' => $html,
	        ]);
	}

	public function renderTagAttributes($attributes){
		$html="";
		foreach($attributes as $name => $value){
			if ($value !== null) {
				$html.=" $name = \"".$value."\"";
			}
		}
		return $html;
	}


	const REFRESH_GET_VAR = 'refresh';
	public $testLimit = 3;
	public $width = 120;
	public $height = 50;
	public $padding = 2;
	public $backColor = 0xFFFFFF;
	public $foreColor = 0x2040A0;
	public $transparent = false;
	public $minLength = 6;
	public $maxLength = 7;
	public $offset = -2;
	public $fontFile = "";
	public $fixedVerifyCode;
	public $sessionKey="__captcha";

	public function __construct(){
		$this->fontFile = CAPTCHA_ROOT.'\SpicyRice.ttf';
	}

	public function validate($input, $caseSensitive=false)
  {
      $code = $this->getVerifyCode();
      $valid = $caseSensitive ? ($input === $code) : strcasecmp($input, $code) === 0;
      @session_start();
      $name = $this->sessionKey.'count';
      $_SESSION[$name] = $_SESSION[$name] + 1;
      if ($valid || $_SESSION[$name] > $this->testLimit && $this->testLimit > 0) {
          $this->getVerifyCode(true);
      }
      return $valid;
  }

	public function outputimg(){
		if($_GET[self::REFRESH_GET_VAR]!==null){
			$this->getVerifyCode(true);
		}
		$this->setHttpHeaders();
		echo $this->renderImage($this->getVerifyCode());
		
	}

	public function getVerifyCode($regenerate = false)
  {
      if ($this->fixedVerifyCode !== null) {
          return $this->fixedVerifyCode;
      }
      @session_start();
      $name=$this->sessionKey;
      if ($_SESSION[$name] === null || $regenerate) {
          $_SESSION[$name] = $this->generateVerifyCode();
          $_SESSION[$name . 'count'] = 1;
      }

      return $_SESSION[$name];
  }
  protected function generateVerifyCode()
  {
      if ($this->minLength > $this->maxLength) {
          $this->maxLength = $this->minLength;
      }
      if ($this->minLength < 3) {
          $this->minLength = 3;
      }
      if ($this->maxLength > 20) {
          $this->maxLength = 20;
      }
      $length = mt_rand($this->minLength, $this->maxLength);

      $letters = 'bcdfghjklmnpqrstvwxyz';
      $vowels = 'aeiou';
      $code = '';
      for ($i = 0; $i < $length; ++$i) {
          //$i能整除2 并且 0-10的随机数>2 或 $1不能整除2 并且 0-1的随机数为10
          if ($i % 2 && mt_rand(0, 10) > 2 || !($i % 2) && mt_rand(0, 10) > 9) {
              $code .= $vowels[mt_rand(0, 4)];
          } else {
              $code .= $letters[mt_rand(0, 20)];
          }
      }

      return $code;
  }

	protected function setHttpHeaders()
	{
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		Header('Content-Transfer-Encoding: binary');
		header("Content-type: image/png");
	}

	protected function renderImage($code)
	{
	    if (Captcha::checkRequirements() === 'gd') {
	        return $this->renderImageByGD($code);
	    } else {
	        return $this->renderImageByImagick($code);
	    }
	}


	protected function renderImageByGD($code)
	{
		$image = imagecreatetruecolor($this->width, $this->height);

		$backColor = imagecolorallocate(
			$image,
			(int) ($this->backColor % 0x1000000 / 0x10000),
			(int) ($this->backColor % 0x10000 / 0x100),
			$this->backColor % 0x100
		);
		imagefilledrectangle($image, 0, 0, $this->width, $this->height, $backColor);
		imagecolordeallocate($image, $backColor);

		if ($this->transparent) {
			imagecolortransparent($image, $backColor);
		}

		$foreColor = imagecolorallocate(
			$image,
			(int) ($this->foreColor % 0x1000000 / 0x10000),
			(int) ($this->foreColor % 0x10000 / 0x100),
			$this->foreColor % 0x100
		);

		$length = strlen($code);
		$box = imagettfbbox(30, 0, $this->fontFile, $code);
		$w = $box[4] - $box[0] + $this->offset * ($length - 1);
		$h = $box[1] - $box[5];
		$scale = min(($this->width - $this->padding * 2) / $w, ($this->height - $this->padding * 2) / $h);
		$x = 10;
		$y = round($this->height * 27 / 40);
		for ($i = 0; $i < $length; ++$i) {
			$fontSize = (int) (rand(26, 32) * $scale * 0.8);
			$angle = rand(-10, 10);
			$letter = $code[$i];
			$box = imagettftext($image, $fontSize, $angle, $x, $y, $foreColor, $this->fontFile, $letter);
			$x = $box[2] + $this->offset;
		}

		imagecolordeallocate($image, $foreColor);

		ob_start();
		imagepng($image);
		imagedestroy($image);

		return ob_get_clean();
	}

	protected function renderImageByImagick($code)
	{
		$backColor = $this->transparent ? new \ImagickPixel('transparent') : new \ImagickPixel('#' . dechex($this->backColor));
		$foreColor = new \ImagickPixel('#' . dechex($this->foreColor));

		$image = new \Imagick();
		$image->newImage($this->width, $this->height, $backColor);

		$draw = new \ImagickDraw();
		$draw->setFont($this->fontFile);
		$draw->setFontSize(30);
		$fontMetrics = $image->queryFontMetrics($draw, $code);

		$length = strlen($code);
		$w = (int) ($fontMetrics['textWidth']) - 8 + $this->offset * ($length - 1);
		$h = (int) ($fontMetrics['textHeight']) - 8;
		$scale = min(($this->width - $this->padding * 2) / $w, ($this->height - $this->padding * 2) / $h);
		$x = 10;
		$y = round($this->height * 27 / 40);
		for ($i = 0; $i < $length; ++$i) {
			$draw = new \ImagickDraw();
			$draw->setFont($this->fontFile);
			$draw->setFontSize((int) (rand(26, 32) * $scale * 0.8));
			$draw->setFillColor($foreColor);
			$image->annotateImage($draw, $x, $y, rand(-10, 10), $code[$i]);
			$fontMetrics = $image->queryFontMetrics($draw, $code[$i]);
			$x += (int) ($fontMetrics['textWidth']) + $this->offset;
		}

		$image->setImageFormat('png');
		return $image->getImageBlob();
	}

}

?>