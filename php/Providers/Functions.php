<?php

namespace Providers;

use Prism\DB;

class Functions
{
	// Checks if team leader is not in another team
	public static function team_leader_check($email){
		$sql = "SELECT * FROM players WHERE email = '$email'";
		$result = mysqli_query(DB::connect(), $sql);
		$count = mysqli_num_rows($result);
		if ($count > "0"){
			trigger_error('A team leader cannot be in multiple teams');
			} else {
			return true;
		}
	}

	// Checks if team name is unique
	public static function team_name_check($team_name){
		$sql = "SELECT * FROM teams WHERE team_name = '$team_name'";
		$result = mysqli_query(DB::connect(), $sql);
		$count = mysqli_num_rows($result);
		if ($count > "0"){
			trigger_error('Team name must be unique, check the teams page to see taken names. <a href="teams.php">Click here.</a>');
		} else {
			return true;
		}
	}

	public static function fetchTeamInfo($team_id) {
		if($team_id === null){
			$sql = "SELECT * FROM teams";
		} else {
			$sql = "SELECT * FROM teams WHERE team_id = '$team_id'";
		}

		$result = mysqli_query(DB::connect(), $sql);
		$output = [];
		while($team = mysqli_fetch_array($result)){
			$team_id = $team['team_id'];
			$sql2 = "SELECT * FROM team_player WHERE team_id = '$team_id'";
			$result2 = mysqli_query(DB::connect(), $sql2);
			$team['team_count'] = mysqli_num_rows($result2);
			if ($team['team_count'] == null){
				$team['team_count'] = 0;
			}
			$sql3 = "SELECT * FROM schedule WHERE winner_id = '$team_id'";
			$res3 = mysqli_query(DB::connect(), $sql3);
			$team['wins'] = mysqli_num_rows($res3);

			$sql4 = "SELECT * FROM schedule WHERE played = 'Y' AND winner_id <> '$team_id' AND team1_id = '$team_id' UNION SELECT * FROM schedule WHERE played = 'Y' AND winner_id <> '$team_id' AND team2_id = '$team_id'";
			$res4 = mysqli_query(DB::connect(), $sql4);
			$team['loses'] = mysqli_num_rows($res4);
			$team['standing'] = $team['wins'] - $team['loses'];
			$output[] = $team;
		}
		return $output;
	}

	public static function fetchSchedule($team_id) {
		if($team_id === null){
			$sql = "SELECT * FROM schedule";
		} else {
			$sql = "SELECT * FROM schedule WHERE team1_id = '$team_id' OR team2_id = '$team_id'";
		}

		$result = mysqli_query(DB::connect(), $sql);
		$count = mysqli_num_rows($result);
		if($count > 0){
			$output = [];
			while($row = mysqli_fetch_array($result)){
				$team1 = $row['team1_id'];
				$team2 = $row['team2_id'];

				$sql2 = "SELECT team_name FROM teams WHERE team_id = '$team1'";
				$res2 = mysqli_query(DB::connect(), $sql2);
				$row2 = mysqli_fetch_array($res2);
				$row['team1_name'] = $row2['team_name'];

				$sql3 = "SELECT team_name FROM teams WHERE team_id = '$team2'";
				$res3 = mysqli_query(DB::connect(), $sql3);
				$row3 = mysqli_fetch_array($res3);
				$row['team2_name'] = $row3['team_name'];

				$winner = $row['winner_id'];
				if ($winner !== "TBD"){
					$sql4 = "SELECT team_name FROM teams WHERE team_id = '$winner'";
					$res4 = mysqli_query(DB::connect(), $sql4);
					$row4 = mysqli_fetch_array($res4);
					$row['winner_name'] = $row4['team_name'];
				} else {
					$row['winner_name'] = 'TBD';
				}

				if($team_id !== null || $row['played'] !== "N"){
					if($team_id === $winner){
						$row['result'] = 'W';
					} else {
						$row['result'] = 'L';
					}
				}

				if($row['played'] === "N"){
					$row['result'] = 'TBD';
				}
				$output[] = $row;
			}
			return $output;
		}
	}

	public static function fetchPlayerStats($team_id){
		$players = self::fetchPlayers($team_id);
		$output = [];
		foreach($players as $player){
			$player_id = $player['player_id'];
			$sql = "SELECT AVG(points) AS ppg, AVG(assists) AS apg, AVG(rebounds) AS rpg, AVG(blocks) AS bpg, AVG(turn_overs) AS topg, player_id FROM player_stats WHERE player_id = '$player_id'";
			$result = mysqli_query(DB::connect(), $sql);
			while($row = mysqli_fetch_array($result)){
				$row['player_name'] = $player['player_name'];
				$row['player_number'] = $player['player_number'];
				$row['age'] = $player['age'];
				$row['experience'] = $player['experience'];
				$output[] = $row;
			}
		}
		return $output;
	}

	public static function fetchPlayers($team_id){
		$sql = "SELECT player_id, player_number FROM team_player WHERE team_id = '$team_id'";

		$result = mysqli_query(DB::connect(), $sql);
		$output = [];
		while($row = mysqli_fetch_array($result)){
			$player_id = $row['player_id'];
			$sql2 = "SELECT first_name, last_name, age, experience FROM players WHERE player_id = '$player_id'";
			$result2 = mysqli_query(DB::connect(), $sql2);
			$row2 = mysqli_fetch_array($result2);
			$row['player_name'] = $row2['first_name'].' '.$row2['last_name'];
			$row['age'] = $row2['age'];
			$row['experience'] = $row2['experience'];
			$output[] = $row;
		}
		return $output;
	}

	public static function loginRequest($email, $password){

		$sql = "SELECT admin_id, admin_name, password FROM admin_users WHERE admin_email = '$email'";
		$result = mysqli_query(DB::connect(), $sql);
		if(mysqli_num_rows($result) === 1){
			$row = mysqli_fetch_array($result);
			$passwordVerify = password_verify($password, $row['password']);
			if($passwordVerify){
				$_SESSION['admin_id'] = $row['admin_id'];
				$_SESSION['admin_name'] = $row['admin_name'];
				$_SESSION['admin_email'] = $email;
				return 'success';
			} else {
				trigger_error('Password is incorrect');
			}
		} else {
			trigger_error('Email is in correct');
		}
	}

	public static function adminCreateTeam($team_name){

		if(!self::uniqueTeamName($team_name)){
			trigger_error('The team name you chose has been taken');
		}
		$sql = "INSERT INTO teams(team_name) VALUES('$team_name')";
		if(mysqli_query(DB::connect(), $sql)){
			return 'success';
		}
	}

	public static function adminUpdateTeam($team_id, $team_name) {

		if(!self::uniqueTeamName($team_name)){
			trigger_error('The team name you chose has been taken');
		}
		$sql = "UPDATE teams SET team_name = '$team_name' WHERE team_id = '$team_id'";
		if(mysqli_query(DB::connect(), $sql)){
			return 'success';
		}
	}

	public static function adminDeleteTeam($team_id){

		$sql = "DELETE FROM teams WHERE team_id = '$team_id'";
		if(mysqli_query(DB::connect(), $sql)){
			return self::adminDeleteTeamPlayerConnection($team_id);
		}
	}

	public static function adminDeleteTeamPlayerConnection($team_id){

		$sql = "DELETE FROM team_player WHERE team_id = '$team_id'";
		if(mysqli_query(DB::connect(), $sql)){
			return 'success';
		}
	}

	public static function adminCreateSchedule($team1, $team2, $team1Result, $team2Result, $scheduleDate, $scheduleTime, $scheduleLocation){

		if($scheduleDate == null){
			$scheduleDate = "TBD";
		}
		if(is_numeric($team1Result) && is_numeric($team2Result)){
			$played = "Y";
			if($team1Result > $team2Result){
				$winner_id = $team1;
			} else {
				$winner_id = $team2;
			}
		} else {
			$team1Result = "TBD";
			$team2Result = "TBD";
			$played = "N";
			$winner_id = "TBD";
		}
		if($scheduleTime == null){
			$scheduleTime = "TBD";
		}
		if($scheduleLocation == null){
			$scheduleLocation = "TBD";
		}
		$sql = "INSERT INTO schedule(team1_id, team2_id, date, game_start, location, team1_result, team2_result, winner_id, played) VALUES('$team1', '$team2', '$scheduleDate', '$scheduleTime', '$scheduleLocation', '$team1Result', '$team2Result', '$winner_id', '$played')";
		if(mysqli_query(DB::connect(), $sql)){
			return 'success';
		}
	}

	public static function adminUpdateSchedule($game_id, $team1, $team2, $team1Result, $team2Result, $scheduleDate, $scheduleTime, $scheduleLocation) {

		if($scheduleDate == null){
			$scheduleDate = "TBD";
		}
		if(is_numeric($team1Result) && is_numeric($team2Result)){
			$played = "Y";
			if($team1Result > $team2Result){
				$winner_id = $team1;
			} else {
				$winner_id = $team2;
			}
		} else {
			$team1Result = "TBD";
			$team2Result = "TBD";
			$played = "N";
			$winner_id = null;
		}
		if($scheduleTime == null){
			$scheduleTime = "TBD";
		}
		if($scheduleLocation == null){
			$scheduleLocation = "TBD";
		}
		$sql = "UPDATE schedule SET team1_id = '$team1', team2_id = '$team2', date = '$scheduleDate', game_start = '$scheduleTime', location = '$scheduleLocation', team1_result = '$team1Result', team2_result = '$team2Result', winner_id = '$winner_id', played = '$played' WHERE game_id = '$game_id'";
		if(mysqli_query(DB::connect(), $sql)){
			return 'success';
		}
	}

	public static function adminDeleteSchedule($game_id){

		$sql = "DELETE FROM schedule WHERE game_id = '$game_id'";
		if(mysqli_query(DB::connect(), $sql)){
			return 'success';
		}
	}

	public static function adminCreatePlayer($player_first_name, $player_last_name, $email, $team_id, $player_number, $address, $phone_number, $age, $experience, $record_id = null) {

		if(!isset($_SESSION['record_id'])){
			if(!self::uniquePlayerNumber($team_id, $player_number, $record_id)){
				trigger_error('The player number you chose has been taken');
			}
			if(self::fetchTeamInfo($team_id)[0]['team_count'] >= 15){
				trigger_error('This team has already reached the limit of 15 players');
			}
		}
		$sql = "INSERT INTO players(first_name, last_name, email, address, phone_number, age, experience) VALUES('$player_first_name', '$player_last_name', '$email', '$address', '$phone_number', '$age', '$experience')";
		if(mysqli_query(DB::connect(), $sql)){
			$player_id = mysqli_insert_id(DB::connect());
			return self::createTeamPlayerConnection(mysqli_insert_id(DB::connect()), $player_number, $team_id);
		}
	}

	public static function uniquePlayerNumber($team_id, $player_number, $record_id = null){
		if(is_null($record_id)){
			$sql = "SELECT player_number FROM team_player WHERE team_id = '$team_id' AND player_number = '$player_number' UNION ALL SELECT player_number FROM unpaid_memberships WHERE team_id = '$team_id' AND player_number = '$player_number'";
		} else {
			$sql = "SELECT player_number FROM team_player WHERE team_id = '$team_id' AND player_number = '$player_number' UNION ALL SELECT player_number FROM unpaid_memberships WHERE team_id = '$team_id' AND player_number = '$player_number' AND record_id <> '$record_id'";
		}

		$count = mysqli_num_rows(mysqli_query(DB::connect(), $sql));
		if($count > 0){
			return false;
		} else {
			return true;
		}
	}

	public static function uniqueTeamName($team_name){
		$result = DB::select("SELECT team_name FROM teams WHERE team_name = '$team_name' UNION ALL SELECT team_name FROM unpaid_memberships WHERE team_name = '$team_name'");
		$count = sizeof($result);
		if($count > 0){
			return false;
		} else {
			return true;
		}
	}

	public static function createTeamPlayerConnection($player_id, $player_number, $team_id){

		if(DB::insert("INSERT INTO team_player(player_id, team_id, player_number) VALUES('$player_id', '$team_id', '$player_number')")){
			return 'success';
		}
	}

	public static function adminReadPlayers(){
		$result = DB::select("SELECT * FROM players");
		$output = [];
		foreach($result as $row){
			$player_team = self::fetchPlayerTeam($row['player_id']);
			$row['team_id'] = $player_team['team_id'];
			$row['player_number'] = $player_team['player_number'];
			$row['team_name'] = self::fetchPlayerTeamName($row['team_id'])['team_name'];
			$output[] = $row;
		}
		return $output;
	}

	public static function fetchPlayerTeam($player_id){
		return DB::selectOne("SELECT team_id, player_number FROM team_player WHERE player_id = '$player_id'");
	}

	public static function fetchPlayerTeamName($team_id){
		return DB::selectOne("SELECT team_name FROM teams WHERE team_id = '$team_id'");
	}

	public static function adminUpdatePlayer($player_id, $player_first_name, $player_last_name, $email, $team_id, $player_number, $address, $phone_number, $age, $experience){
		if(self::fetchTeamInfo($team_id)[0]['team_count'] >= 15){
			trigger_error('This team has already reached the limit of 15 players');
		}
		if(
			DB::update(
				"UPDATE players SET first_name = '$player_first_name', last_name = '$player_last_name', email = '$email', address = '$address', phone_number = '$phone_number', age = '$age', experience = '$experience' WHERE player_id = '$player_id'"
				)
		){
			return self::changePlayerTeam($player_id, $team_id, $player_number);
		}
	}

	public static function changePlayerTeam($player_id, $team_id, $player_number){
		if(DB::update("UPDATE team_player SET team_id = '$team_id', player_number = '$player_number' WHERE player_id = '$player_id'")){
			return 'success';
		}
	}

	public static function adminDeletePlayer($player_id){

		$sql = DB::query("DELETE FROM players WHERE player_id = '$player_id'");
		$sql2 = DB::query("DELETE FROM team_player WHERE player_id = '$player_id'");
		if($sql && $sql2){
			return 'success';
		}
	}

	public static function adminCreateStat($game_id, $player_id, $points, $assists, $rebounds, $blocks, $turnovers){

		$sql = "INSERT INTO player_stats(player_id, game_id, points, assists, rebounds, blocks, turn_overs) VALUES( '$player_id', '$game_id', '$points', '$assists', '$rebounds', '$blocks', '$turnovers')";
		if(DB::insert($sql)){
			return 'success';
		}
	}

	public static function createUnpaidOrder($action, $first_name, $last_name, $team_id, $team_name, $email, $player_number, $address, $phone_number, $age, $experience){
		$timestamp = DB::timestamp();
		if(!self::uniquePlayerNumber($team_id, $player_number)){
			trigger_error('The player number you chose has been taken');
		}
		$sql = "INSERT INTO unpaid_memberships(first_name, last_name, email, team_id, order_type, player_number, address, phone_number, age, experience, timestamp) VALUES('$first_name', '$last_name', '$email', '$team_id', '$action', '$player_number', '$address', '$phone_number', '$age', '$experience', '$timestamp')";
		if(DB::insert($sql)){
			$_SESSION['record_id'] = mysqli_insert_id(DB::connect());
			return 'success';
		}
	}

	public static function adminReadUnpaid() {
		$result = DB::select("SELECT * FROM unpaid_memberships");
		$output = [];
		foreach($result as $row){
			if($row['order_type'] === "joinTeam"){
				$row['team_name'] = self::fetchPlayerTeamName($row['team_id'])['team_name'];
			}
			$output[] = $row;
		}
		return $output;
	}

	public static function adminDeleteUnpaid($record_id){

		$sql = "DELETE FROM unpaid_memberships WHERE record_id = '$record_id' AND paid = 'false'";
		if(DB::query($sql)){
			return 'success';
		}
	}

	public static function createTeamFromUnpaid($team_name){

		$sql = DB::insert("INSERT INTO teams(team_name) VALUES('$team_name')");
		if($sql){
			return $sql;
		}
	}

	public static function adminPaidUnpaid($record_id){

	  $sql = "UPDATE unpaid_memberships SET paid = 'true' WHERE record_id = '$record_id'";
	  if(mysqli_query(DB::connect(), $sql)){
	    return 'success';
	  }
	}

	public static function getRecord($record_id){
	  return DB::selectOne("SELECT * FROM unpaid_memberships WHERE record_id = '$record_id'");
	}

	public static function processUnpaid($record_id){
		$record = self::getRecord($record_id);
		$first_name = $record['first_name'];
		$last_name = $record['last_name'];
		$team_name = $record['team_name'];
		$email = $record['email'];
		$player_number = $record['player_number'];
		$address = $record['address'];
		$phone_number = $record['phone_number'];
		$age = $record['age'];
		$experience = $record['experience'];
		if($record['paid'] === "true"){
			trigger_error('Already paid');
		}
		if($record['order_type'] === "createTeam"){
		  $team_id = self::createTeamFromUnpaid($team_name);
		} else {
		  $team_id = $record['team_id'];
		}
		$createPlayer = self::adminCreatePlayer($first_name, $last_name, $email, $team_id, $player_number, $address, $phone_number, $age, $experience, $record_id);
		if($createPlayer['status'] === "success"){
			if(self::adminPaidUnpaid($record['record_id'])['status'] === "success"){
				if(isset($_SESSION['record_id']) || !isset($_SESSION['admin_id'])){
					unset($_SESSION['record_id']);
				}
				return 'success';
			}
		} else {
			return $createPlayer;
		}
	}

	public static function contactEmail($name, $email, $message, $phone_number){
		$to = "info@balltillifall.ca";
		$body = "<strong>From: </strong>".$name." &lt; <a href='mailto:".$email."'>".$email."</a> &gt; <br/>  &lt; ".$phone_number."</a> &gt; <br/> <strong>Message: </strong>".$message;
		$subject = "Contact form submission";
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
		$headers .= 'From: <'.$email.'>' . "\r\n";
		$headers .= 'X-Mailer: PHP/' . phpversion();
		if ($name === null || $message === null) {
			trigger_error('Name and message cannot be empty');
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)){
			trigger_error('Email must be valid');
		}
		if(mail($to,$subject,$body,$headers)){
			return 'success';
		}
	}

	public static function getAdmins()
	{
		return DB::select("SELECT admin_id, admin_name, admin_email FROM admin_users");
	}

	public static function createAdmin($admin_name, $admin_email, $password)
	{
		$password = password_hash($password, PASSWORD_DEFAULT);
		$sql = "INSERT INTO admin_users SET admin_name = '$admin_name', admin_email = '$admin_email', password = '$password'";
		$query = DB::insert($sql);
		if($query){
			return 'success';
		}
	}

	public static function updateAdmin($admin_id, $admin_name, $admin_email, $password)
	{
		$password = password_hash($password, PASSWORD_DEFAULT);
		$query = DB::query("UPDATE admin_users SET admin_name = '$admin_name', admin_email = '$admin_email', password = '$password' WHERE admin_id = '$admin_id'");
		return $query;
	}

	public static function deleteAdmin($admin_id)
	{
		return DB::query("DELETE FROM admin_users WHERE admin_id = '$admin_id'");
	}
}
