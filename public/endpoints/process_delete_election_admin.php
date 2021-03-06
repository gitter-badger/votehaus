<?php
require_once('../includes/config.php');

$errors	= array();
$data	= array();

if(isset($_POST['_csrf']) && session_csrf_check($_POST['_csrf']) && session_get_type() == "user") {
	if(isset($_POST['election_id_deleteadmin']) && !empty($_POST['election_id_deleteadmin']) && isset($_POST['admin_id_deleteadmin']) && !empty($_POST['admin_id_deleteadmin'])) {
		global $pdo;
		
		$admin_id = $_POST['admin_id_deleteadmin'];
		$election_id = $_POST['election_id_deleteadmin'];
		if(set_user_election_access($admin_id, $election_id, 0)) {
			$data['success'] = true;
			$data['message'] = 'Success!';
		} else {
			$errors['name'] = 'An error occurred.';
		}
	} else {
		$errors['name'] = 'Invalid name.';
	}
} else {
	$errors['req'] = 'Request is invalid.';
}

if(!empty($errors)) {
	$data['success'] = false;
	$data['errors']  = $errors;
}

echo json_encode($data);
