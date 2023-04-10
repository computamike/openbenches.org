<?php
if(!isset($_SESSION)) { session_start(); }
require_once ("config.php");
require_once ("mysql.php");
require_once ("functions.php");

$page_title = "- Merge";
include("header.php");

//	Is an admin using this?
[$user_provider, $user_providerID, $user_name] = get_user_details(true);

//	Hardcoded for @edent
if ("twitter" == $user_provider && 14054507 == $user_providerID)
{
	$originalID  = $_POST["originalID"];
	$duplicateID = $_POST["duplicateID"];
	if ($originalID != null && $duplicateID != null)
	{
		merge_benches($originalID, $duplicateID);
		echo "Redirected <a href='https://{$_SERVER['HTTP_HOST']}/bench/{$duplicateID}'>{$duplicateID}</a><br>";
	}
?>
	<form action="merge.php" method="post" autocomplete="off">
	Original:  <input type="text" name="originalID" autofocus><br>
	Duplicate: <input type="text" name="duplicateID"><br>
	<input type="submit" value="Merge Benches">
	</form>

<?php
} else {
	die();
}

include("footer.php");
