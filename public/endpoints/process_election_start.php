<?php
require_once('../includes/config.php');

$errors	= array();
$data	= array();

if(isset($_POST['_csrf']) && session_csrf_check($_POST['_csrf'])) {
	if(isset($_POST['election_id']) && !empty($_POST['election_id'] && does_election_exist($_POST['election_id'])) && isset($_POST['start'])) {
		$election_id = $_POST['election_id'];
		$questions = get_questions_for_election($election_id); 
		foreach($questions as $question) {
			$questiondata = json_decode($question['data'], true);
			if($questiondata['type'] == "nominee_1") {
				$nominees = $questiondata['data']['nominees']; 
				foreach($nominees as $nominee) {
					if(get_user_id($nominee) == FALSE) {
						create_keyed_user_account($nominee);
					}
					set_user_election_access(get_user_id($nominee), $election_id, 100)
				}
			}
		}
		
		change_election_stage($election_id, 'ready');

		$data['success'] = true;
		$data['message'] = 'Success!';
	} else {
		$errors['name'] = 'Invalid election.';
	}
} else {
	$errors['req'] = 'Request is invalid.';
}

if(!empty($errors)) {
	$data['success'] = false;
	$data['errors']  = $errors;
}

echo json_encode($data);
