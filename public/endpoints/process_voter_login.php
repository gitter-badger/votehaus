<?php
require_once('../includes/config.php');

$errors	= array();
$data	= array();

if(isset($_POST['_csrf']) && session_csrf_check($_POST['_csrf'])) {
	$recaptcha = new \ReCaptcha\ReCaptcha($config['captcha']['priv'], new \ReCaptcha\RequestMethod\CurlPost());
	$resp = $recaptcha->verify($_POST['g-recaptcha-response']);
	if ($resp->isSuccess()) {
		if(isset($_POST['voter_token']) && !empty($_POST['voter_token']) && has_valid_voter_token($_POST['voter_token'])) {
			session_login_voter($_POST['voter_token']);
			
			$data['success'] = true;
			$data['message'] = 'Success!';
		} else {
			$errors['name'] = 'Voter token is invalid.';
			header("Location: /vote");
		}
	} else {
		$errors['captcha'] = 'Captcha is invalid.';
		header("Location: /vote");
	}
} else {
	$errors['req'] = 'Request is invalid.';
	header("Location: /vote");
}

if(!empty($errors)) {
    $data['success'] = false;
    $data['errors']  = $errors;
}

echo json_encode($data);
