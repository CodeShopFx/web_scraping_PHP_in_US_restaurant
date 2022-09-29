<?php
error_reporting(E_ALL);

require 'db.php';
$settings_file = 'settings.json';

$cards = array(
	'0' => 'Select',
	'4' => 'AMEX',
	'3' => 'MasterCard',
	'2' => 'Visa',
);

$range_index = array(
		0 => 150,1 => 120, 2 => 90, 3 => 60, 4 => 30, 5 => 15,
		6 => 0,
		7 => 15, 8 => 30, 9 => 60, 10 => 90, 11 => 120, 12 => 150);
$range_names = array( 0 => 'exactly', 15 => '15 min', 30 => '30 min', 60 => '1 hour', 90 => '1:30 hours', 120 => '2 hours', 150 => '2:30 hours');

	if (isset($_GET['edit'])) {
		$id = $_GET['edit'];
		if (filter_var($id, FILTER_VALIDATE_INT)) {
			userform($id);
		}
		exit;
	}


	if (isset($_GET['card_res'])) {
		$id = $_GET['card_res'];
		$type = $_GET['type'];
		if (filter_var($id, FILTER_VALIDATE_INT)) {
			$req = $dbh->prepare("UPDATE `users` SET cctype = 0, ccnum = '', ccexpyear = 0, ccexpmonth = 0, cccvc = '', cczip = '' WHERE `id` = ?;");
			$req->execute(array($id));
			header("Location: ./#$type");
		}
	}

	if (isset($_GET['delete'])) {
		$id = $_GET['delete'];
		$type = $_GET['type'];
		if (filter_var($id, FILTER_VALIDATE_INT)) {
			if (in_array($type, array('tasks', 'users'))) {
				$dbh->exec("DELETE FROM $type WHERE id=$id;");
				if ($type == 'users') {
					$dbh->exec("DELETE FROM tasks WHERE user_id=$id;");
				}
			}
			header("Location: ./#$type");
		}
	}

	if (isset($_POST['add_user'])) {

		if (isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['email']) && isset($_POST['phone'])) {
			$fname = $_POST['first_name'];
			$lname = $_POST['last_name'];
			$email = $_POST['email'];
			$phone = $_POST['phone'];
			$cc = array('cctype' => 0, 'ccnum' => '', 'ccexpmonth'=> '0', 'ccexpyear'=> '0', 'cccvc' => '', 'cczip' => '');

			if (isset($_POST['ccnum']) && $_POST['ccnum'] != '') {
				foreach ($cc as $key => &$val) {
					if (is_numeric($_POST[$key])) {
						$val = $_POST[$key];
					}
				}
				$insert = $dbh->prepare("INSERT INTO `users` (first_name, last_name, email, phone_number, cctype, ccnum, ccexpyear, ccexpmonth, cccvc, cczip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), last_name=VALUES(last_name), email=VALUES(email), phone_number=VALUES(phone_number), cctype=VALUES(cctype), ccnum=VALUES(ccnum), ccexpyear=VALUES(ccexpyear), ccexpmonth=VALUES(ccexpmonth), cccvc=VALUES(cccvc), cczip=VALUES(cczip)");
				$insert->execute(array($fname, $lname, $email, $phone, $cc['cctype'], $cc['ccnum'], $cc['ccexpyear'], $cc['ccexpmonth'], $cc['cccvc'], $cc['cczip']));
			} else {
				$insert = $dbh->prepare("INSERT INTO `users` (first_name, last_name, email, phone_number) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), last_name=VALUES(last_name), email=VALUES(email), phone_number=VALUES(phone_number)");
				$insert->execute(array($fname, $lname, $email, $phone));
			}
		}
	}
	if (isset($_POST['add_restaurant'])) {
		$id = $_POST['rid'];
		$name = $_POST['rname'];
		$addr = $_POST['raddr'];
		get_restaurant($id, $name, $addr, 'opentable.com');
	}

	if (isset($_POST['add_reserve_com_restaurant'])) {
		$url = $_POST['reserve_com_url'];
		$parts = explode('?', $url);
		$base_url = $parts[0];
		$parts = explode('/', $base_url);
		$identifier = end($parts);

		$result = getReserveRestaurantInfo($url);
		//print_r($result->data);die();
		$name = $result->data[0]->name;
		$address1 = $result->data[0]->address->line1;
		$city = $result->data[0]->address->city;
		$state = $result->data[0]->address->region;
		$zip = $result->data[0]->address->postal_code;
		$address = "$address1 $city, $state $zip";
		$id = time();
		get_restaurant($id, $name, $address, 'reserve.com', $identifier);
	}

	if (isset($_POST['remove_restaurant'])) {
		foreach ($_POST['restaurants'] as $num => $id)  {
			if (filter_var($id, FILTER_VALIDATE_INT)) {
					$dbh->exec("DELETE FROM restaurants WHERE id=$id;");
			}
		}
	}

	if (isset($_POST['add_reservation'])) {

		$rid = $_POST['restaurant'];
		$uid = $_POST['user'];
		$date = $_POST['date'];
		$time = $_POST['time'];

		$rangemin = (array_key_exists($_POST['rangemin'], $range_index)) ? $range_index[$_POST['rangemin']] : 0;
		$rangemax = (array_key_exists($_POST['rangemax'], $range_index)) ? $range_index[$_POST['rangemax']] : 0;

		$persons = $_POST['persons'];
		if ($rid && $date && $time && $uid && $persons) {
			$datetime = date('Y-m-d H:i', strtotime("$date $time"));
			$insert = $dbh->prepare("INSERT INTO `tasks` (`restaurant_id`, `user_id`, `datetime`, `rangemin`, `rangemax`, `persons`) VALUES (:rid, :uid, :datetime, :rangemin, :rangemax, :persons)");
			try {
				$insert->execute(array(
					':rid' => $rid,
					':uid' => $uid,
					':datetime' => $datetime,
					':rangemin' => $rangemin,
					':rangemax' => $rangemax,
					':persons' => $persons
				));
			} catch (Exception $e) {
				echo $e;
			}
		}

	}

	if (isset($_POST['settings'])) {
		$settings['deadline'] = $_POST['deadline'];
		$settings['uagent'] = $_POST['uagent'];
		$settings['timezone'] = $_POST['timezone'];
		if (isset($_POST['rate']) && $_POST['rate'] >= 5 && $_POST['rate'] <= 60) {
			$settings['rate'] = $_POST['rate'];
		}
		file_put_contents($settings_file, json_encode($settings));
	}

	$json = file_get_contents($settings_file);
	$settings = json_decode($json, true);

	if (!isset($settings['uagent'])) {
		$settings['uagent'] = "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)";
	}

	if (!isset($settings['rate'])) {
		$settings['rate'] = 60;
	}

	if (!isset($settings['timezone'])) {
		$settings['timezone'] = '';
	}


	
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8"/>
	
	<title>Restaurant Reservation</title>
	
	<link rel="stylesheet" type="text/css" href="static/style.css"/>
	<link rel="stylesheet" type="text/css" href="static/jquery-ui.css"/>
	<link rel="stylesheet" type="text/css" href="static/chosen.min.css">
	<link rel="stylesheet" type="text/css" href="static/jquery-ui-slider-pips.css">

	<script src="static/jquery-1.7.1.min.js" type="text/javascript"></script>
	<script src="static/jquery-ui.min.js" type="text/javascript"></script>

	<script src="static/jquery-ui-slider-pips.js" type="text/javascript"></script>
	<script src="static/jquery.hashchange.min.js" type="text/javascript"></script>
	<script src="static/jquery.easytabs.min.js" type="text/javascript"></script>
    <script src="static/chosen.jquery.min.js" type="text/javascript"></script>
	
	<script type="text/javascript">

	$(document).ready( function() {
		$('#tab-container').easytabs({animate: false});
		$('a.togglelink').on('click', function (e) {
		        e.preventDefault();
			$(this).next('#popform').toggle();
	   	});

		$("#datepick").datepicker({ dateFormat: "mm/dd/yy", minDate: 0  });
		$(".chosen-select").chosen({width: "400px", search_contains: true})
		$('.chosen-drop').css({"width": "600px"});
		$("#popform").toggle();

		$(document).on("click", ".edit-link",function(e){
			var id = this.id.split('-')[1];
			$(".ui-widget-overlay").live("click", function (){$("div:ui-dialog:visible").dialog("close");});
			$('#modal-dialog')
		        .load('index.php?edit=' + id)
				.dialog('open');
				return false;
			});

		$('#modal-dialog').dialog({
		    title: 'Edit',
		    autoOpen: false,
		    width: 400,
		    height: 500,
		    modal: true
		});

//		var range = ["-2:30 hours", "-2 hours", "-1:30 hours", "-1 hour", "-30 min", "exactly", "+30 min", "+1 hour", "+1:30 hours", "+2 hours", "+2:30 hours"];
		var range = ["-2:30 hours", "-2 hours", "-1:30 hours", "-1 hour", "-30 min", "-15 min", "exactly", "+15 min", "+30 min", "+1 hour", "+1:30 hours", "+2 hours", "+2:30 hours"];

		$("#slider").slider({ min: 0, max: 12, range:true, values: [6,6], change: function(event, ui) { 
				console.log(ui);
			$('input[name="rangemin"]').val(ui.values[0]);
			$('input[name="rangemax"]').val(ui.values[1]);
		}});        
		$("#slider").slider("pips" , { labels: range, rest: "label" });
	});

	</script>
</head>
<body>
	<div id="main_container">
		<h1>Restaurant reservation</h1>
		<div id="tab-container" class="tab-container">
			<ul class="etabs">
				<li class="tab"><a href="#tasks">Reservations</a></li>
				<li class="tab"><a href="#users">Users</a></li>
				<li class="tab"><a href="#settings">Settings</a></li>
			</ul>

			<div id="tasks" class="tab-content">
				<table style="width: 100%;">
					<tr>
						<th>Restaurant</th>
						<th>Name</th>
						<th style="width: 3%;">Party size</th>
						<th style="width: 15%;">Date</th>
						<th style="width: 10%;">Range</th>
						<th style="width: 6%;">Status</th>
						<th style="width: 5%;"></th>
					</tr>

<?php
	$sth = $dbh->prepare("SELECT DATE_FORMAT(tasks.datetime, '%m/%d/%Y %l:%i %p') as tdate,restaurants.name as restaurant_name,restaurants.address as address,users.first_name as fname,users.last_name as lname,tasks.* FROM tasks,users,restaurants WHERE tasks.restaurant_id=restaurants.id AND tasks.user_id = users.id");
	$sth->execute();
	while ($user = $sth->fetch()) {
		if ($user['active'] == 1) {
			$status = 'Active';
		} else {
			$status = $user['status'];
		}
		$rangemin = ($user['rangemin'] > 0) ? '-'.$range_names[$user['rangemin']] : '';
		$rangemax = ($user['rangemax'] > 0) ? '+'.$range_names[$user['rangemax']] : '';
		$sep = ($rangemin && $rangemax) ? '<br>' : '';
		if (!$rangemin && !$rangemax) $sep = 'exactly';
		$range = $rangemin.$sep.$rangemax;
		echo '<tr><td>'.$user['restaurant_name'].' ['.$user['address'].']</td><td>' . $user['fname'] . ' '. $user['lname'] .'</td><td style="text-align:center">' . $user['persons'] . '</td><td style="text-align:center">'.$user['tdate'].'</td><td style="text-align:center">' . $range . '</td><td style="text-align:center">' . $status . '</td><td style="text-align:center">[<a href="?type=tasks&delete='.$user['id'].'" style="color: red;">delete</a>]</tr>';
	}
?>
			</table>
		<br><br><a href="#" class="togglelink">Add reservation</a>
				<div id="popform">
				<br><br><br><h2>Add reservation</h2>

			<form name="add_reservation" method="POST" style="width: 90%;margin: 0 auto;">
				
					<p>
					<div class="sites">

<strong>Restaurant:</strong><br>
<select name="restaurant" class="chosen-select">

<?php
	$sth = $dbh->prepare("SELECT * FROM restaurants ORDER BY name");
	$sth->execute();
	$rest_list = '';
	while ($rest = $sth->fetch()) {
		$name = $rest['name'] . ' ['. $rest['address'].']';
		$id = $rest['id'];
		$rest_list.='<option value="'.$id.'">'.$name.'</option>';
	}
	echo $rest_list;
?>
</select>
<br><br>
<strong>Date:</strong><br>
<input id="datepick" type="text" name="date" autocomplete="off">
<select name="time">
	<option value="12:00 AM">12:00 AM</option>
	<option value="12:30 AM">12:30 AM</option>
	<option value="1:00 AM">1:00 AM</option>
	<option value="1:30 AM">1:30 AM</option>
	<option value="2:00 AM">2:00 AM</option>
	<option value="2:30 AM">2:30 AM</option>
	<option value="3:00 AM">3:00 AM</option>
	<option value="3:30 AM">3:30 AM</option>
	<option value="4:00 AM">4:00 AM</option>
	<option value="4:30 AM">4:30 AM</option>
	<option value="5:00 AM">5:00 AM</option>
	<option value="5:30 AM">5:30 AM</option>
	<option value="6:00 AM">6:00 AM</option>
	<option value="6:30 AM">6:30 AM</option>
	<option value="7:00 AM">7:00 AM</option>
	<option value="7:30 AM">7:30 AM</option>
	<option value="8:00 AM">8:00 AM</option>
	<option value="8:30 AM">8:30 AM</option>
	<option value="9:00 AM">9:00 AM</option>
	<option value="9:30 AM">9:30 AM</option>
	<option value="10:00 AM">10:00 AM</option>
	<option value="10:30 AM">10:30 AM</option>
	<option value="11:00 AM">11:00 AM</option>
	<option value="11:30 AM">11:30 AM</option>
	<option value="12:00 PM">12:00 PM</option>
	<option value="12:30 PM">12:30 PM</option>
	<option value="1:00 PM">1:00 PM</option>
	<option value="1:30 PM">1:30 PM</option>
	<option value="2:00 PM">2:00 PM</option>
	<option value="2:30 PM">2:30 PM</option>
	<option value="3:00 PM">3:00 PM</option>
	<option value="3:30 PM">3:30 PM</option>
	<option value="4:00 PM">4:00 PM</option>
	<option value="4:30 PM">4:30 PM</option>
	<option value="5:00 PM">5:00 PM</option>
	<option value="5:30 PM">5:30 PM</option>
	<option value="6:00 PM">6:00 PM</option>
	<option value="6:30 PM">6:30 PM</option>
	<option value="7:00 PM">7:00 PM</option>
	<option value="7:30 PM">7:30 PM</option>
	<option value="8:00 PM">8:00 PM</option>
	<option value="8:30 PM">8:30 PM</option>
	<option value="9:00 PM">9:00 PM</option>
	<option value="9:30 PM">9:30 PM</option>
	<option value="10:00 PM">10:00 PM</option>
	<option value="10:30 PM">10:30 PM</option>
	<option value="11:00 PM">11:00 PM</option>
	<option value="11:30 PM">11:30 PM</option>
</select>
<br>

<strong>Time range:</strong><br>
<input type="hidden" name="rangemin"><br>
<div style="width:100%" id="slider"></div>
<input type="hidden" name="rangemax"><br>
<br><br>
<strong>User:</strong><br>
<select name="user" style="width:155px">

<?php
	$sth = $dbh->prepare("SELECT * FROM users");
	$sth->execute();
	while ($user = $sth->fetch()) {
		$name = $user['first_name'] . ' '. $user['last_name'];
		$id = $user['id'];
		echo '<option value="'.$id.'">'.$name.'</option>';
	}
?>
</select><br>
<strong>Party size:</strong><br>
<select name="persons" style="width:155px">
<?php
	foreach (range(1, 20) as $num) {
		$add = ($num == 1) ? 'person' : 'people';
		echo "<option value='$num'>$num $add</option>\n";
	}
?>
</select><br><br>
			<input type="submit" name="add_reservation" value="Add" class="button submit" />
			<input type="reset" name="clear" value="Clear" class="button reset" />
			</div>
			</p>

			</form>
			</div>

		<br><br><a href="#" class="togglelink">Manage restaurants</a>
		<div id="popform" style="display:none">
				<br><br><br><h2>Add restaurant</h2>
			<form name="add_restaurant" method="POST" style="width: 40%;margin: 0 auto;">
					<p>
					<div class="sites">
<strong>Opentable ID:</strong><br />
<input type="text" name="rid"><br />
<strong>Name:</strong><br />
<input type="text" name="rname"><br />
<strong>Address:</strong><br />
<input type="text" name="raddr"><br />
<input type="submit" name="add_restaurant" value="Add" class="button submit" />
</div>
</form>

<br><br><br><h2>Add (Reserve.com) Restaurant</h2>
<form name="add_reserve_restaurant" method="POST" style="width: 40%;margin: 0 auto;">
	<div class="sites">
	<strong>Reserve.com URL:</strong><br />
	<input type="text" name="reserve_com_url"><br />
	<input type="submit" name="add_reserve_com_restaurant" value="Add" class="button submit" />
	</div>
</form>


				<br><br><br><h2>Remove restaurants</h2>
		<form name="remove_restaurant" method="POST" style="width: 40%;margin: 0 auto;">
		<p><div class="sites">
        <select size="8" name="restaurants[]" multiple="multiple">
		<? echo $rest_list; ?>
        </select>

<br><center><input type="submit" name="remove_restaurant" value="Remove" class="button reset" /></center>
</div>
</form>

</div>
</div>
		
			<div id="users" class="tab-content">
				<table style="width: 100%;">
					<tr>
						<th>Name</th>
						<th>Email</th>
						<th>Phone</th>
						<th>Credit Card</th>
						<th style="width: 100px;"></th>
					</tr>

<?php
	$sth = $dbh->prepare("SELECT * FROM users");
	$sth->execute();
	while ($user = $sth->fetch()) {
		if (($user['cctype'] > 0) && isset($user['ccnum'])) {
			$card = $cards[$user['cctype']];
		} else {
			$card = 'None';
		}
		echo '<tr><td>' . $user['first_name'] . ' '. $user['last_name'] .'</td><td>'.$user['email'].'</td><td>' . $user['phone_number'] . '</td><td>' . $card . '</td><td style="text-align:center">[<a href="#" id="edit-'.$user['id'].'" class="edit-link" style="color: orange;">edit</a>]&nbsp;[<a href="?type=users&delete='.$user['id'].'" style="color: red;">delete</a>]</tr>';
	}
?>
				</table>
				<br><br><a href="#" class="togglelink">Add users</a>
				<div id="popform" style="display: none">
				<br><br><br><h2>Add new user</h2>
<?
userform();
function userform($id = '') {
global $cards, $dbh;
		$user = array();
		if ($id) {
			$sth = $dbh->prepare("SELECT * FROM users WHERE id=?;");
			$sth->execute(array($id));
			$user = $sth->fetch();
		}

?>
			<div id="modal-dialog"></div>
			<form name="add_user" method="POST">
				
					<p>
					<div class="sites">
						<strong>First name:</strong><br><input type="text" name="first_name" id="tags" value="<? echo cval($user, 'first_name'); ?>" /><br>
						<strong>Last name:</strong><br><input type="text" name="last_name" id="tags" value="<? echo cval($user, 'last_name'); ?>" /><br>
						<strong>Email:</strong><br><input type="text" name="email" id="tags" value="<? echo cval($user, 'email'); ?>" /><br>
						<strong>Phone:</strong><br><input type="text" name="phone" id="tags" value="<? echo cval($user, 'phone_number'); ?>" /><br><br><br>
<?
if (isset($user['ccnum']) && $user['ccnum'] != '') {
	$num = substr($user['ccnum'], -4);
	$cctype = $user['cctype'];
	if (isset($cards[$cctype])) $num = $cards[$cctype].' '.$num;
	echo '<strong>Credit Card #: </strong>'.$num.'&nbsp;&nbsp;[<a href="?type=users&card_res='.$user['id'].'" style="color: red;">reset</a>]<br>';
} else {
?>

<strong>Credit Card Type:</strong><br>
<select name="cctype">
<?
	foreach ($cards as $num => $name) {
		$add = (isset($user['cctype']) && $user['cctype'] == $num) ? 'selected' : '';
		echo "<option value='$num'$add>$name</option>\n";
	}
?>
</select><br>
<strong>Credit Card #:</strong><br><input type="text" name="ccnum" id="tags" value="<? echo cval($user, 'ccnum'); ?>" /><br>
<strong>Expiration Date:</strong><br>
&nbsp;<span>Month</span>
	<select name="ccexpmonth">
<?
	foreach (range(1, 12) as $num) {
		$add = (isset($user['ccexpmonth']) && $user['ccexpmonth'] == $num) ? 'selected' : '';
		echo "<option value='$num'$add>$num</option>\n";
	}
?>
</select>&nbsp;<span>Year</span>
	<select name="ccexpyear">
<?	$year = date("Y");
	foreach (range($year, $year+9) as $num) {
		$add = (isset($user['ccexpyear']) &&  $user['ccexpyear'] == $num) ? 'selected' : '';
		echo "<option value='$num'$add>$num</option>\n";
	}
?>
</select><br>
<strong>CVC:</strong><br><input type="text" name="cccvc" id="tags" value="<? echo cval($user, 'cccvc'); ?>" /><br><br>
<strong>Zip:</strong><br><input type="text" name="cczip" id="zip" value="<? echo cval($user, 'cczip'); ?>" /><br>
<? } ?>
<br><br><br><br>
		<input type="submit" name="add_user" value="Submit" class="button submit" />
		</div>
		</p>
		</form>
<?
}
?>
			</div>
			</div>
		
			<div id="settings" class="tab-content">

			<br><br><h2>Backend settings</h2>
			<br>
			<form name="add_user" method="POST">
				
					<p>
					<div class="sites">
						<strong>Cancel deadline (minutes):</strong><br><input type="text" name="deadline" id="tags" value="<? echo intval($settings['deadline']); ?>" /><br>
						<br>
						<strong>Polling rate (seconds, 5-60):</strong><br><input type="text" name="rate" id="tags" value="<? echo intval($settings['rate']); ?>" /><br>
						<br>
						<strong>Server time:</strong><br><input type="text" name="timezone" id="tags" value="<? echo $settings['timezone']; ?>" /><br>
						<br>
						<strong>Browser useragent:</strong><br><input type="text" name="uagent" id="tags" style="width:100%" value="<? echo $settings['uagent']; ?>" /><br>
						<br>
					<input type="submit" name="settings" value="Save" class="button submit" />

					</div>


				</p>

			</form>

			</div>
		</div>
		
	</div>
</body>
</html>
<?php
function get_restaurant($id, $name, $address, $platform='', $identifier='') {
global $dbh, $settings;

	if (preg_match('/[&?](?:r|rid|RestaurantIDs)=(\d+)/', $id, $matches))  {
		$id = $matches[1];
	}

	if (!is_numeric($id)) return;

#	if ($platform == 'opentable.com') {
#		$ch = curl_init();
#		curl_setopt($ch, CURLOPT_USERAGENT, $settings['uagent']);
#		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
#		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
#		curl_setopt($ch, CURLOPT_COOKIEFILE, '');
#		curl_setopt($ch, CURLOPT_URL, "http://www.opentable.com/restaurant/profile/$id?rid=$id");
#		curl_exec($ch);
#
#
#		$redirectUrl = curl_getinfo($ch)['redirect_url'];
#		while($redirectUrl) {
#
#			$url = preg_replace('|^https?://www.opentable.com//www.opentable.com|', 'https://www.opentable.com', $redirectUrl);
#			$ch = curl_init();
#			curl_setopt($ch, CURLOPT_USERAGENT, $settings['uagent']);
#			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
#			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
#			curl_setopt($ch, CURLOPT_COOKIEFILE, '');
#			curl_setopt($ch, CURLOPT_URL, $url);
#			$content = curl_exec($ch);
#			$redirectUrl = curl_getinfo($ch)['redirect_url'];
#		}
#
#		//$name = (preg_match('/<[^>]*itemprop="name"[^>]*>\s*(.*?)\s*<\//s', $content, $matches)) ? $matches[1] : '';
#		$name = (preg_match('/<h1[^>]*itemprop="name"[^>]*>\s*(.*?)\s*<\//s', $content, $matches)) ? $matches[1] : '';
#		$address = (preg_match('/<[^>]*itemprop="streetAddress"[^>]*>\s*(.*?)\s*<\//s', $content, $matches)) ? $matches[1] : '';
#		$address = preg_replace('/\s*<br[^>]*>\s*/s', '; ', $address);
#	}

	if ($name && $address) {
		$insert = $dbh->prepare("INSERT INTO `restaurants` (id,name,address,platform,identifier) VALUES (:id, :name, :addr, :platform, :identifier) ON DUPLICATE KEY UPDATE id=id;");
		try {
			$insert->execute(array(
					':id' => $id,
					':name' => $name,
					':addr' => $address,
					':platform' => $platform,
					':identifier' => $identifier,
					));
		} catch (Exception $e) {
			echo $e;
		}

	} else {
		echo 'not found';
	}
}

function cval($arr, $key) {
	return (isset($arr[$key])) ? $arr[$key] : '';
}

function getReserveRestaurantInfo($public_url) {
	$datetime = new DateTime('tomorrow');
        $date = $datetime->modify('next month')->format('Y-m-d');
	//$date = $datetime->format('Y-m-d');

	$time = 1700;
	$party_size = 2;

	$parts = explode('?', $public_url);
	$url = $parts[0];
	$parts = explode('/', $url);
	$slug = end($parts);

        $url = 'https://api.reserve.com/api/v1/public/venues/search';

        $data = array(
                'party_size'  => $party_size,
                'platform'    => 'WEB',
                'start_date'  => $date,
                'end_date'    => $date,
                'time'        => $time,
                'time_radius' => 120,
                'search_type' => 'SLUG',
                'slugs'   => [$slug]
        );
        $data_string = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );

        $result = curl_exec($ch);
        $result_json = json_decode($result);
        return $result_json;
}


?>
