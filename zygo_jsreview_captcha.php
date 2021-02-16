<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.zygo_jsreview_captcha
 *
 * @copyright   Copyright (C) 2015 Sherza, http://www.psytronica.ru, 
 * @author		Sherza, sherza.web@gmail.com 
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class PlgSystemZygo_jsreview_captcha extends JPlugin
{

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
	}

	public function onBeforeSaveReview(&$review){

		if($this->checkUser()) return;

		$app =JFactory::getApplication();
		$id = $app->input->get('product_id');

		if(!(isset($_SESSION['captcha_keystring_'.$id]) 
			&& $_SESSION['captcha_keystring_'.$id] === $app->input->get('keystring'))){
			$review->product_id=0;
			return;
		}

		if($this->params->get('remove_links')){

			$regex = '/\b(https?):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i';
			$review->review = preg_replace($regex, ' ', $review->review);
			$regex = '/\b(www)\.[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i';
			$review->review = preg_replace($regex, ' ', $review->review);

		}
	}

	private function checkUser()
	{

		$access = $this->params->get('access', 8);
		if(!is_array($access)) $access=array($access);

		$user = jFactory::getUser();

		return sizeof(array_intersect($user->groups, $access));

	}

	public function onAfterRoute()
	{
		$app =JFactory::getApplication();

		if(!$app->getName() == 'site') return;

		if($app->input->get('zygo_captcha')) $this->show_captcha();
		
		if( $app->input->get('option')=="com_jshopping" && 
			$app->input->get('product_id')){

			$script = "
			/* ==========================================================
			 Zygo Captcha Refresh
			----------------------------------------------------------- */ 

			function zygoCaptchaRefresh(){
				var captchaImg=document.getElementById('zygo_captcha_img');
				var pos = captchaImg.src.indexOf('new_num=');
				if(pos==-1){
					captchaImg.src+='&new_num=0';
				}else{
					captchaImg.src = captchaImg.src.substring(0, pos+8)+ 
									(parseInt(captchaImg.src.substring(pos+8))+1);
				}

			}";
			$doc = JFactory::getDocument();
			$doc->addScriptDeclaration($script);

			$style=$this->params->get('css', ".zygo_captcha_refresh{cursor:pointer;}".
				   ".zygo_captcha-input{margin-top:10px;}");

			if(trim($style)) $doc->addStyleDeclaration($style);
		}
	}


	function show_captcha(){

		include(dirname(__FILE__).'/kcaptcha/kcaptcha.php');

		$kparams = new stdClass();

		$kparams->alphabet = "0123456789abcdefghijklmnopqrstuvwxyz"; # do not change without changing font files!

		# symbols used to draw CAPTCHA
		//$allowed_symbols = "0123456789"; #digits
		//$allowed_symbols = "23456789abcdegkmnpqsuvxyz"; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)
		$kparams->allowed_symbols = "23456789abcdegikpqsvxyz"; #alphabet without similar symbols (o=0, 1=l, i=j, t=f)

		# folder with fonts
		$kparams->fontsdir = 'fonts';	

		# CAPTCHA string length
		$kparams->length = mt_rand($this->params->get('min_length', 5)
				,$this->params->get('max_length', 7)); # random 5 or 6 or 7
		//$length = 6;

		# CAPTCHA image size (you do not need to change it, this parameters is optimal)
		$kparams->width = $this->params->get('width', 160);
		$kparams->height = $this->params->get('height', 80);

		# symbol's vertical fluctuation amplitude
		$kparams->fluctuation_amplitude = 8;

		#noise
		//$white_noise_density=0; // no white noise
		$kparams->white_noise_density= 
			((int)($this->params->get('white_noise_density', 6)))? 
			1/((int)($this->params->get('white_noise_density', 6))) : 0;

		$kparams->black_noise_density= 
			((int)($this->params->get('black_noise_density', 30)))? 
			1/((int)($this->params->get('black_noise_density', 30))) : 0; 

		# increase safety by prevention of spaces between symbols
		$kparams->no_spaces = true;

		# show credits
		$kparams->show_credits = $this->params->get('show_credits', 1); # set to false to remove credits line. Credits adds 12 pixels to image height
		$kparams->credits = $this->params->get('credits', 'www.captcha.ru'); # if empty, HTTP_HOST will be shown

		# CAPTCHA image colors (RGB, 0-255)
		//$foreground_color = array(0, 0, 0);
		//$background_color = array(220, 230, 255);

		$rfb = $this->params->get('red_foreground_begin', 0);
		$rfe = $this->params->get('red_foreground_end', 80);

		$gfb = $this->params->get('green_foreground_begin', 0);
		$gfe = $this->params->get('green_foreground_end', 80);

		$bfb = $this->params->get('blue_foreground_begin', 0);
		$bfe = $this->params->get('blue_foreground_end', 80);	


		$rbb = $this->params->get('red_background_begin', 220);
		$rbe = $this->params->get('red_background_end', 255);

		$gbb = $this->params->get('green_background_begin', 220);
		$gbe = $this->params->get('green_background_end', 255);

		$bbb = $this->params->get('blue_background_begin', 220);
		$bbe = $this->params->get('blue_background_end', 255);				

		$kparams->foreground_color = array(mt_rand($rfb,$rfe), mt_rand($gfb,$gfe), mt_rand($bfb,$bfe));
		$kparams->background_color = array(mt_rand($rbb,$rbe), mt_rand($gbb,$gbe), mt_rand($bbb,$bbe));

		# JPEG quality of CAPTCHA image (bigger is better quality, but larger file size)
		$kparams->jpeg_quality = $this->params->get('jpeg_quality', 90);


		$captcha = new KCAPTCHA($kparams);

		if($_REQUEST[session_name()]){

			$app =JFactory::getApplication();
			$id = $app->input->get('product_id');

			$_SESSION['captcha_keystring_'.$id] = $captcha->getKeyString();
		}
		JApplication::close();
	}


	private function captchaContent(){

		$app =JFactory::getApplication();
		$id = $app->input->get('product_id');


		$html_default = '<div class="span3">%CAPTCHA_LABEL%</div>     
						<div class="span9">        
							<div id="zygo_captcha_wrapper">        
							  <div>%CAPTCHA_IMG%</div>         
							    <div class="zygo_captcha-input input-append">        
							    <input type="text" class="inputbox input-small" %CAPTCHA_INPUT_NAME%>         
							    <a class="btn zygo_captcha_refresh" %CAPTCHA_REFRESH%>        
							      <i class="icon-refresh"></i>
							      %CAPTCHA_REFRESH_LABEL%
							    </a>        
							  </div>        
							</div>      
						</div>';


		$html=$this->params->get('html', $html_default);

		$replaces = array(
			'%CAPTCHA_LABEL%'=>JText::_('PLG_SYSTEM_ZYGO_JSREVIEW_CAPTCHA_LABEL'),

			'%CAPTCHA_IMG%'=>'<img id="zygo_captcha_img" autocomplete="off" src="'.JURI::base().'index.php?zygo_captcha=1&product_id='.$id.'&'.session_name().'='.session_id().'">',

			'%CAPTCHA_INPUT_NAME%'=>'name="keystring"',

			'%CAPTCHA_REFRESH%'=>'onclick="zygoCaptchaRefresh()"',

			'%CAPTCHA_REFRESH_LABEL%'=>
				JText::_('PLG_SYSTEM_ZYGO_JSREVIEW_CAPTCHA_REFRESH')
		);

		return str_replace(array_keys($replaces), array_values($replaces), $html);

	}

	public function onAfterRender()
	{

		$app =JFactory::getApplication();
		if($app->getName() != 'site' || $app->input->get('option')!="com_jshopping" || !$app->input->get('product_id')) {
			return true;
		}

		if($this->checkUser()) return;

		$body = $app->getBody();

		$jLanguage = JFactory::getLanguage();
		$jLanguage->load('plg_system_zygo_jsreview_captcha', JPATH_ADMINISTRATOR, null, true, false);

		$tag = trim($this->params->get('tag'));

		if(!$tag){

			preg_match('@<form[^>]+name="add_review".+</form>@isU', $body, $out);

			if(isset($out[0]) && $out[0]){

				$out_arr = explode('<div class = "row-fluid">', $out[0]);

				$inserted= array($this->captchaContent().'</div>');
				array_splice( $out_arr, sizeof($out_arr)-1, 0, $inserted ); 

				$res = implode('<div class = "row-fluid">', $out_arr);

				$body= str_replace($out[0], $res, $body);
				$app->setBody($body);

			}

		}else if(strpos($body, $tag)!==FALSE){

			$body= str_replace($tag, $this->captchaContent(), $body);
			$app->setBody($body);

		}

	}

}
