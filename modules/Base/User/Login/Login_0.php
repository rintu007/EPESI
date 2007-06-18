<?php
/**
 * Login class.
 * 
 * This class provides for basic login functionality, saves passwords to database and enables password recvery.
 * 
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @licence SPL
 * @package epesi-base-extra
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * This class provides for basic login functionality, saves passwords to database and enables password recvery.
 * @package epesi-base-extra
 * @subpackage user-login
 */
class Base_User_Login extends Module {
	protected $lang;
	
	public function construct() {
		$this->fast_process();
	}
	
	public function body($arg) {
		$this->lang = $this->pack_module('Base/Lang');

		//if logged
		if(Acl::is_user()) {
			if($this->get_unique_href_variable('logout')) {
				Acl::set_user();
				Base_UserCommon::set_my_user_id();
				location(array());
			} else {
				print($this->lang->t('Logged as %s.',Acl::get_user()).' <a '.$this->create_unique_href(array('logout'=>1)).'>'.$this->lang->t('Logout').'</a>');
			}
			return;	
		}
		
		if($this->is_back())
		    $this->unset_module_variable('mail_recover_pass');
		    
		//if recover pass
		if($this->get_module_variable_or_unique_href_variable('mail_recover_pass')=='1') {
			$this->recover_pass();
			return;
		}
		
		//else just login form
		$form = & $this->init_module('Libs/QuickForm');
		$form->addElement('header', 'login_header', $this->lang->t('Login'));
		$form->addElement('text', 'username', $this->lang->t('Username'),array('id'=>'username'));
		$form->addElement('password', 'password', $this->lang->t('Password'));
		$form->addElement('static', 'recover_password', null, '<a '.$this->create_unique_href(array('mail_recover_pass'=>1)).'>'.$this->lang->t('Recover password').'</a>');
		$form->addElement('submit', 'submit_button', $this->lang->ht('Login'), array('class'=>'submit'));
		
		// register and add a rule to check if a username and password is ok
		$form->registerRule('check_login', 'callback', 'submit_login', 'Base_User_Login');
		$form->addRule('username', $this->lang->t('Login or password incorrect'), 'check_login', $form);
		
		$form->addRule('username', $this->lang->t('Field required'), 'required');
		$form->addRule('password', $this->lang->t('Field required'), 'required');

		if($form->validate()) {
			$user = $form->exportValue('username');
			Acl::set_user($user); //tag who is logged
			
			Base_UserCommon::set_my_user_id(Base_UserCommon::get_user_id($user));
			
			location(array());
		} else {
			$theme =  & $this->pack_module('Base/Theme');
			$theme->assign_form('form', $form);
			$theme->display();

			eval_js("focus_by_id('username')");
		}
	}
	
	public static function submit_login($username, $form) {
		return Base_User_LoginCommon::check_login($username, $form->exportValue('password'));
	}
	
	public function recover_pass() {
		$form = & $this->init_module('Libs/QuickForm');
		
		$form->addElement('header', null, $this->lang->t('Recover password'));
		$form->addElement('hidden', $this->create_unique_key('mail_recover_pass'), '1');
		$form->addElement('text', 'username', $this->lang->t('Username'));
		$form->addElement('text', 'mail', $this->lang->t('e-mail'));
		$ok_b = & HTML_QuickForm::createElement('submit', 'submit_button', $this->lang->ht('OK'));
		$cancel_b = & HTML_QuickForm::createElement('button', 'cancel_button', $this->lang->ht('Cancel'), $this->create_back_href());
		$form->addGroup(array($ok_b,$cancel_b),'buttons');
		
		// require a username
		$form->addRule('username', $this->lang->t('A username must be between 3 and 32 chars'), 'rangelength', array(3,32));
		// register and add a rule to check if a username and password is ok
		$form->registerRule('check_username', 'callback', 'check_username_mail_valid', 'Base_User_Login');
		$form->addRule('username', $this->lang->t('Username or e-mail invalid'), 'check_username', $form);
		$form->addRule('username', $this->lang->t('Field required'), 'required');
		//require valid e-mail address
		$form->addRule('mail', $this->lang->t('Field required'), 'required');
		$form->addRule('mail', $this->lang->t('This isn\'t valid e-mail address'), 'email');
		
		if($form->validate()) {
			if($form->process(array(&$this, 'submit_recover')))
				print($this->lang->t('Mail with password sent.').' <a '.$this->create_back_href().'>'.$this->lang->t('Login').'</a>');
		} else $form->display();
	}

	public static function check_username_mail_valid($username, $form) {
		$mail = $form->getElement('mail')->getValue();
		$ret = DB::Execute('SELECT null FROM user_password p JOIN user_login u ON u.id=p.user_login_id WHERE u.login=%s AND p.mail=%s AND u.active=1',array($username, $mail));
		return $ret->FetchRow()!==false;
	}
	
	public function submit_recover($data) {
		$mail = $data['mail'];
		$username = $data['username'];
		$pass = generate_password();
		
		$user_id = Base_UserCommon::get_user_id($username);
		if($user_id===false) {
			print('No such user!');
			return false;
		}
		
		if(!DB::Execute('UPDATE user_password SET password=%s WHERE user_login_id=%d', array(md5($pass), $user_id))) {
			print($this->lang->t('Unable to update password for user %s.',$username));
			return false;
		}
		
		if(!$this->send_mail_with_password($username, $pass, $mail)) {
			print($this->lang->t('Unable to send e-mail with password. Mail module configuration invalid. Notify about it your administrator.'));
			return false;
		}
		
		return true;
	}
}
?>