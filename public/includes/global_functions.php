<?php
require_once('credentials.php');
on_load();

function on_load() {
	session_start();

	$loggedin = session_is_logged_in();
	if(!isset($loggedin)) {
		session_logout_user();
	}

	if($_SESSION['last_csrf_cleanup'] + 86400 < time()) {
 		session_csrf_cleanup();
 	}
}

//votehaus Functions
function get_current_user_all_elections() {
	$uid = session_get_user_id();
	return get_users_all_elections($uid);
}

function get_users_all_elections($uid) {
	global $pdo;
	
	$stmt = $pdo->prepare("SELECT `election` FROM `access` WHERE `user`= ?");
	$stmt->bindParam(1, $uid);
	$stmt->execute();
	$elections = $stmt->fetchAll();
	return $elections;
}

function get_election_users_at_access($eid, $level) {
	global $pdo;
	
	$stmt = $pdo->prepare("SELECT `user`,`data` FROM `access` WHERE `election`= ? AND `level`= ? ORDER BY RAND()");
	$stmt->bindParam(1, $eid);
	$stmt->bindParam(2, $level);
	$stmt->execute();
	$users = $stmt->fetchAll();
	return $users;
}

function get_user_election_access($uid, $eid) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `level` FROM `access` WHERE `user`= ? AND `election`= ?");
	$stmt->bindParam(1, $uid);
	$stmt->bindParam(2, $eid);
	$stmt->execute();
	$access = $stmt->fetchAll();
	if($stmt->rowCount() == 1) {
		$level = $access[0]['level'];
		return $level; 
	} else {
		return false;
	}
}

function get_user_election_access_data($uid, $eid) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `data` FROM `access` WHERE `user`= ? AND `election`= ?");
	$stmt->bindParam(1, $uid);
	$stmt->bindParam(2, $eid);
	$stmt->execute();
	$access = $stmt->fetchAll();
	if($stmt->rowCount() == 1) {
		$data = $access[0]['data'];
		return $data; 
	} else {
		return false;
	}
}

function set_user_election_access($uid, $eid, $level, $data = "") {
	global $pdo;

	if($level != 0) {
		if(get_user_election_access($uid, $eid) == FALSE) {
			$stmt = $pdo->prepare("INSERT INTO `access` (`election`, `user`, `level`, `data`) VALUES (?, ?, ?, ?)");
			$stmt->bindParam(1, $eid);
			$stmt->bindParam(2, $uid);
			$stmt->bindParam(3, $level);
			$stmt->bindParam(4, $data);
			$stmt->execute();
		} else { 
			$stmt = $pdo->prepare("UPDATE `access` SET `level`= ?, `data` = ? WHERE `user`= ? AND `election`= ?");
			$stmt->bindParam(1, $level);
			$stmt->bindParam(2, $data);
			$stmt->bindParam(3, $uid);
			$stmt->bindParam(4, $eid);
			$stmt->execute();
			if (!$stmt) {
				error_log(print_r($stmt->errorInfo(), true));
			} else {
				log_auditable_action($uid, "set_elec_access", $eid);
			}
		}
	} else {
		$stmt = $pdo->prepare("DELETE FROM `access` WHERE `user`= ? AND `election`= ?");
		$stmt->bindParam(1, $uid);
		$stmt->bindParam(2, $eid);
		$stmt->execute();
		if (!$stmt) {
			error_log(print_r($stmt->errorInfo(), true));
		} else {
			log_auditable_action($uid, "del_elec_access", $eid);
		}
	}
}

function does_user_have_election_access($uid, $eid) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `level` FROM `access` WHERE `user`= ? AND `election`= ?");
	$stmt->bindParam(1, $uid);
	$stmt->bindParam(2, $eid);
	$stmt->execute();
	if($stmt->rowCount() == 1) {
		return true;
	} else {
		return false;
	}
}

function get_user_email($uid) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `email` FROM `users` WHERE `id`= ?");
	$stmt->bindParam(1, $uid);
	$stmt->execute();
	$result = $stmt->fetchAll();
	if($stmt->rowCount() == 1) {
		$email = $result[0]['email'];
		return $email;
	} else {
		return false;
	}
}

function get_user_sysadmin_level($uid) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `system_admin` FROM `users` WHERE `id`= ?");
	$stmt->bindParam(1, $uid);
	$stmt->execute();
	$result = $stmt->fetchAll();
	if($stmt->rowCount() == 1) {
		$val = $result[0]['email'];
		return $val;
	} else {
		return false;
	}
}

function has_valid_voter_token($token) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `id` FROM `voters` WHERE `voter_token`= ?");
	$stmt->bindParam(1, $token);
	$stmt->execute();
	$result = $stmt->fetchAll();
	if($stmt->rowCount() == 1) {
		return true;
	} else {
		return false;
	}
}

function has_valid_reg_key($email, $key) {
	global $pdo;

	$regkey = "REGKEY=" . $key;
	$stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `email`= ? AND `password`= ?");
	$stmt->bindParam(1, $email);
	$stmt->bindParam(2, $regkey);
	$stmt->execute();
	$result = $stmt->fetchAll();
	if($stmt->rowCount() == 1) {
		return true;
	} else {
		return false;
	}
}

function create_keyed_user_account($email) {
	global $pdo;

	if(get_user_id($email) == FALSE) {
		$key = randString(32);
		$regkey = "REGKEY=" . $key;
		$stmt = $pdo->prepare("INSERT INTO `users` (`email`, `password`) VALUES (?, ?)");
		$stmt->bindParam(1, $email);
		$stmt->bindParam(2, $regkey);
		$stmt->execute();
		$result = $stmt->fetchAll();
		return $key;
	} else {
		return false;
	}
}

function reset_user_password($email, $passwordhash) {
	global $pdo;

	$stmt = $pdo->prepare("UPDATE `users` SET `password`= ? WHERE `email`= ?");
	$stmt->bindParam(1, $passwordhash);
	$stmt->bindParam(2, $email);
	$stmt->execute();
}

function change_election_stage($eid, $stage) {
	global $pdo;

	$stmt = $pdo->prepare("UPDATE `elections` SET `stage`= ? WHERE `id`= ?");
	$stmt->bindParam(1, $stage);
	$stmt->bindParam(2, $eid);
	$stmt->execute();
}

function get_election_stage($eid) {
	global $pdo;
	
	$stmt = $pdo->prepare("SELECT `stage` FROM `elections` WHERE `id`= ?");
	$stmt->bindParam(1, $eid);
	$stmt->execute();
	$row = $stmt->fetch(PDO::FETCH_NUM);

	$stage = $row[0];
	return $stage;
}

function get_voter_election_id() {
	global $pdo;
	
	$voter = session_get_voter_token();
	$stmt = $pdo->prepare("SELECT `election` FROM `voters` WHERE `voter_token`= ?");
	$stmt->bindParam(1, $voter);
	$stmt->execute();
	$row = $stmt->fetch(PDO::FETCH_NUM);

	$election_id = $row[0];
	return $election_id;
}

function get_user_id($email) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `email`= ?");
	$stmt->bindParam(1, $email);
	$stmt->execute();
	$result = $stmt->fetchAll();
	if($stmt->rowCount() == 1) {
		$id = $result[0]['id'];
		return $id;
	} else {
		return false;
	}
}

function get_election_admins($eid) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `user`, `level` FROM `access` WHERE `election` = ? AND `level` >= 254");
	$stmt->bindParam(1, $eid);
	$stmt->execute();
	$result = $stmt->fetchAll();
	return $result;
}

function does_election_exist($eid) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `id` FROM `elections` WHERE `id`= ?");
	$stmt->bindParam(1, $eid);
	$stmt->execute();
	if($stmt->rowCount() == 1) {
		return true;
	} else {
		return false;
	}
}

function get_election_name($eid) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `name` FROM `elections` WHERE `id`= ?");
	$stmt->bindParam(1, $eid);
	$stmt->execute();
	$result = $stmt->fetch(PDO::FETCH_NUM)[0];
	return $result;
}

function get_questions_for_election($id) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `id`, `order`, `data` FROM `questions` WHERE `election`= ? ORDER BY `order` ASC");
	$stmt->bindParam(1, $id);
	$stmt->execute();
	$questions = $stmt->fetchAll();
	return $questions;
}

function get_voters_for_election($id) {
	global $pdo;

	$stmt = $pdo->prepare("SELECT `id`, `email` FROM `voters` WHERE `election`= ?");
	$stmt->bindParam(1, $id);
	$stmt->execute();
	$voters = $stmt->fetchAll();
	return $voters;
}

function log_auditable_action($user, $action, $details = NULL) {
	global $pdo;

	$stmt = $pdo->prepare("INSERT INTO `audit` (`user`, `action`, `details`) VALUES (?, ?, ?)");
	$stmt->bindParam(1, $user);
	$stmt->bindParam(2, $action);
	$stmt->bindParam(3, $details);
	$stmt->execute();
}

function record_ballot($eid, $ballot) {
	global $pdo;

	$ballot_id = randString(16);
	
	$stmt = $pdo->prepare("INSERT INTO `ballots` (`id`) VALUES (?)");
	$stmt->bindParam(1, $ballot_id);
	$stmt->execute();
	
	$qid = 0;
	
	$stmt = $pdo->prepare("INSERT INTO `votes` (`ballot`, `election`, `question`, `data`) VALUES (?, ?, ?, ?)");
	$stmt->bindParam(1, $ballot_id);
	$stmt->bindParam(2, $eid);
	$stmt->bindParam(3, $qid);
	$stmt->bindParam(4, $ballot);
	$stmt->execute();
	
	$token = session_get_voter_token();
	
	$stmt = $pdo->prepare("UPDATE `voters` SET `has_voted`= 1 WHERE `voter_token`= ?");
	$stmt->bindParam(1, $token);
	$stmt->execute();
}

function has_voter_voted() {
	global $pdo;

	$token = session_get_voter_token();
	
	$stmt = $pdo->prepare("SELECT `id` FROM `voters` WHERE `voter_token`= ? AND `has_voted` = 0");
	$stmt->bindParam(1, $token);
	$stmt->execute();
	if($stmt->rowCount() == 1) {
		return false;
	} else {
		return true;
	}
}

function render_question($questiondata, $eid) {
	global $pdo;
	
	if($questiondata['type'] == "nominee_1") {
		?>
		<div class="row">
			<!-- Nominees -->
			<h4>Nominees</h4>
			<table class="table">
				<tr>
					<th>Email</th>
					<th style="width: 40px">Action</th>
				</tr>
				<?php $nominees = $questiondata['data']['nominees']; 
				foreach($nominees as $nominee) { ?>
				<tr>
					<td><?php echo $nominee; ?></td>
					<td>
						<form name="deletenomineebox" id="deletenomineebox" action="endpoints/process_delete_election_nominee.php" method="post">
							<?php csrf_render_html(); ?>
							<input type="hidden" name="election_id_deleteadmin" value="<?php echo $eid; ?>" />
							<input type="hidden" name="admin_id_deleteadmin" value="<?php echo $nominee; ?>" />
							<button type="submit" class="btn btn-block btn-danger btn-xs"><i class="fa fa-minus-square"></i></button>
						</form>
					</td>
				</tr>
				<?php } ?>
			</table>
		</div>
		<div class="row">
			<!-- Acceptance Notice -->
			<h4>Nominee Acceptance Notice</h4>
			<form action="endpoints/process_election_notice_modify.php" method="post">
				<textarea id="noticeeditor" name="noticeeditor" rows="10" cols="80">
					<?php echo $questiondata['data']['acceptance_notice']; ?>
				</textarea>
				<button type="submit" class="btn btn-block btn-primary"><i class="fa fa-save"></i> Save</button>
			</form>
		</div>
		<div class="row">
			<!-- Voter Guide -->
			<h4>Voter Guide Questions</h4>
			<table class="table">
				<tr>
					<th>Question</th>
					<th style="width: 80px">Char Limit</th>
				</tr>
				<?php $voter_guide = $questiondata['data']['voter_guide']; 
				foreach($voter_guide as $question) { ?>
				<tr>
					<td><?php echo $question['question']; ?></td>
					<td><?php echo $question['limit']; ?></td>
				</tr>
				<?php } ?>
			</table>
		</div>
		<?php 
	} else {
		?>
		<div class="alert alert-danger">
			<h4><i class="icon fa fa-ban"></i>Invalid Question Type</h4>
			The question type "<?php echo $questiondata['type']; ?>" does not exist.
		</div>
		<?php 
	}
}

function tabulate_alernative_vote($eid) {
	global $pdo;
	
	$stmt = $pdo->prepare("SELECT `data` FROM `votes` WHERE `election`= ? AND `question` = 0");
	$stmt->bindParam(1, $eid);
	$stmt->execute();
	$ballots = $stmt->fetchAll();
	foreach($ballots as $num => $ballot) {
		//do stuff
	}
}

//Session Functions
function randString($length, $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789') {
	$str = '';
	$count = strlen($charset);
	while ($length--) {
		$str .= $charset[mt_rand(0, $count-1)];
	}
	return $str;
}

function session_is_logged_in() {
	if(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true) {
		return true;
	} else {
		return false;
	}
}

function session_get_type() {
	if(isset($_SESSION['uid'])) {
		return 'user';
	} else {
		return 'voter';
	}
}

function session_login_user($uid) {
	$_SESSION['uid'] = $uid;
	$_SESSION['logged_in'] = true;
	$_SESSION['login_time'] = time();
	session_csrf_cleanup();
}

function session_get_user_id() {
	if(isset($_SESSION['uid'])) {
		return $_SESSION['uid'];
	} else {
		return false;
	}
}

function session_logout_user() {
	session_unset();
	session_destroy();
	session_start();
	$_SESSION['logged_in'] = false;
	$_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
	session_csrf_cleanup();
}

function session_login_voter($token) {
	$_SESSION['voter'] = $token;
	$_SESSION['logged_in'] = true;
	$_SESSION['login_time'] = time();
	session_csrf_cleanup();
}

function session_get_voter_token() {
	if(isset($_SESSION['voter'])) {
		return $_SESSION['voter'];
	} else {
		return false;
	}
}

function session_logout_voter() {
	session_unset();
	session_destroy();
	session_start();
	$_SESSION['logged_in'] = false;
	$_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
	session_csrf_cleanup();
}

function session_set_value($name, $value) {
	$_SESSION['vars'][$name] = $value;
}

function session_delete_value($name) {
	unset($_SESSION['vars'][$name]);
}

function session_get_value($name) {
	return $_SESSION['vars'][$name];
}

function session_csrf_add() {
	$value = randString(16);
	$_SESSION['csrf'][$value] = time() + 86400; //1 day expiry
	return $value;
}

function session_csrf_check($value) {
	if(!empty($_SESSION['csrf'][$value]) && time() < $_SESSION['csrf'][$value]) {
		unset($_SESSION['csrf'][$value]);
		return true;
	} else {
		return false;
	}
}

function session_csrf_cleanup() {
	if(isset($_SESSION['csrf']) && sizeof($_SESSION['csrf']) >= 1) {
		foreach($_SESSION['csrf'] as $str => $time) {
			if($time < time()) {
				unset($_SESSION['csrf'][$str]);
			}
		}
	}
	$_SESSION['last_csrf_cleanup'] = time();
}

function csrf_render_html($fieldname = "_csrf") {
	echo csrf_get_html($fieldname);
}

function csrf_get_html($fieldname = "_csrf") {
	return '<input type="hidden" name="' . $fieldname . '" value="' . session_csrf_add() . '" />';
}
