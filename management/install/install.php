<?php
include_once 'CORALInstaller.php';
$installer = new CORALInstaller();

if ($installer->installed()) {
	header('Location: index.php');
	exit;
}

//this script runs entire installation process in 5 steps

//take "step" variable to determine which step the current is
$step = $_POST['step'];

//perform field validation(steps 3-5) and database connection tests (steps 3 and 4) and send back to previous step if not working
$errorMessage = array();
if ($step == "3"){
	//first, validate all fields are filled in
	$database_host = trim($_POST['database_host']);
	$database_username = trim($_POST['database_username']);
	$database_password = trim($_POST['database_password']);
	$database_name = trim($_POST['database_name']);

	if (!$database_host) $errorMessage[] = 'Host name is required';
	if (!$database_name) $errorMessage[] = 'Database name is required';
	if (!$database_username) $errorMessage[] = 'User name is required';
	if (!$database_password) $errorMessage[] = 'Password is required';

	//only continue to checking DB connections if there were no errors this far
	if (count($errorMessage) > 0){
		$step="2";
	}else{
		//first check connecting to host
		$link = new mysqli("$database_host", "$database_username", "$database_password");
		if ($link->connect_error) {
			$errorMessage[] = "Could not connect to the server '" . $database_host . "'<br />MySQL Error: " . $link->error;
		}else{

			//next check that the database exists
			$dbcheck = $link->select_db("$database_name");
			if (!$dbcheck) {
				$errorMessage[] = "Unable to access the database '" . $database_name . "'.  Please verify it has been created.<br />MySQL Error: " . $link->error;
			}else{
				//make sure the tables don't already exist - otherwise this script will overwrite all of the data!
				$query = "SELECT count(*) count FROM information_schema.`COLUMNS` WHERE table_schema = '" . $database_name . "' AND table_name='License'";

				//if License table exists, error out
				if (!$row = $link->query($query)->fetch_array()){
					$errorMessage[] = "Please verify your database user has access to select from the information_schema MySQL metadata database.";
				}else{
					if ($row['count'] > 0){
						$errorMessage[] = "The Management tables already exist.  If you intend to upgrade, please run upgrade.php instead.  If you would like to perform a fresh install you will need to manually drop all of the Management tables in this schema first.";
					}else{
						//passed db host, name check, can open/run file now
						//make sure SQL file exists
						$test_sql_file = "protected/test_create.sql";
						$sql_file = "protected/install.sql";

						if (!file_exists($test_sql_file)) {
							$errorMessage[] = "Could not open sql file: " . $test_sql_file . ".  If this file does not exist you must download new install files.";
						}else{
							//run the file - checking for errors at each SQL execution
							$f = fopen($test_sql_file,"r");
							$sqlFile = fread($f,filesize($test_sql_file));
							$sqlArray = explode(";",$sqlFile);

							//Process the sql file by statements
							foreach ($sqlArray as $stmt) {
								if (strlen(trim($stmt))>3){

									$result = $link->query($stmt);
									if (!$result){
										$errorMessage[] = $link->error . "<br /><br />For statement: " . $stmt;
										break;
									}
								}
							}

						}


						//once this check has passed we can run the entire ddl/dml script
						if (count($errorMessage) == 0){
							if (!file_exists($sql_file)) {
								$errorMessage[] = "Could not open sql file: " . $sql_file . ".  If this file does not exist you must download new install files.";
							}else{
								//run the file - checking for errors at each SQL execution
								$f = fopen($sql_file,"r");
								$sqlFile = fread($f,filesize($sql_file));
								$sqlArray = explode(';',$sqlFile);

								//Process the sql file by statements
								foreach ($sqlArray as $stmt) {
									if (strlen(trim($stmt))>3){

										$result = $link->query($stmt);
										if (!$result){
											$errorMessage[] = $link->error . "<br /><br />For statement: " . $stmt;
											break;
										}
									}
								}

							}
						}

					}
				}
			}
		}

	}

	if (count($errorMessage) > 0){
		$step="2";
	}

}else if ($step == "4"){

	//first, validate all fields are filled in
	$database_host = trim($_POST['database_host']);
	$database_username = trim($_POST['database_username']);
	$database_password = trim($_POST['database_password']);
	$database_name = trim($_POST['database_name']);
	$admin_login = trim($_POST['admin_login']);

	if (!$database_username) $errorMessage[] = 'User name is required';
	if (!$database_password) $errorMessage[] = 'Password is required';
	if (!$admin_login) $errorMessage[] = 'Admin user is required';

	//only continue to checking DB connections if there were no errors this far
	if (count($errorMessage) > 0){
		$step="3";
	}else{

		//first check connecting to host
		$link = new mysqli("$database_host", "$database_username", "$database_password");
		if ($link->connect_error) {
			$errorMessage[] = "Could not connect to the server '" . $database_host . "'<br />MySQL Error: " . $link->error;
		}else{

			//next check that the database exists
			$dbcheck = $link->select_db("$database_name");

			if (!$dbcheck) {
				$errorMessage[] = "Unable to access the database '" . $database_name . "'.  Please verify it has been created.<br />MySQL Error: " . $link->error;
			}else{
				//passed db host, name check, test that user can select from License database
				$result = $link->query("SELECT privilegeID FROM " . $database_name . ".Privilege WHERE shortName like '%admin%';");
				if (!$result){
					$errorMessage[] = "Unable to select from the Privilege table in database '" . $database_name . "' with user '" . $database_username . "'.  Error: " . $link->error;
				}else{
					while ($row = $result->fetch_array(MYSQLI_NUM)) {
						$privilegeID = $row[0];
					}

					//delete admin user if they exist, then set them back up
					$query = "DELETE FROM " . $database_name . ".User WHERE loginID = '" . $admin_login . "';";
					$link->query($query);
					$query = "INSERT INTO " . $database_name . ".User (loginID, privilegeID) values ('" . $admin_login . "', " . $privilegeID . ");";

					$link->query($query);
				}

			}
		}

	}

	if (count($errorMessage) > 0){
		$step="3";
	}


}else if ($step == "5"){

	//first, validate all required fields are filled in
	$remoteAuthVariableName = trim($_POST['remoteAuthVariableName']);
	$organizationsModule = $_POST['organizationsModule'];
	$usageModule = $_POST['usageModule'];
	$useTermsToolFunctionality = $_POST['useTermsToolFunctionality'];
	$organizationsDatabaseName = trim($_POST['organizationsDatabaseName']);
	$authModule = $_POST['authModule'];
	$authDatabaseName = trim($_POST['authDatabaseName']);


	$database_host = $_POST['database_host'];
	$database_name = $_POST['database_name'];
	$database_username = $_POST['database_username'];
	$database_password = trim($_POST['database_password']);

	if ((!$remoteAuthVariableName) && (!$authModule)){
		$errorMessage[] = 'Either the CORAL Authentication module must be used or you must enter the remote auth variable name';
	}else{
		//replace double quote with single quote since config writes with double quote
		$remoteAuthVariableName = str_replace('"', "'", $remoteAuthVariableName);

		//make sure variable name has matched number of ', otherwise it will bomb the program
		if((substr_count($remoteAuthVariableName, "'") % 2)!==0){
			$errorMessage[] = 'Make sure Remote Auth Variable Name has matched single or double quotes';
		}

	}
	if ((!$organizationsDatabaseName) && ($_POST['organizationsModule'])) $errorMessage[] = "If you are using the organizations module you must enter the organizations module database name.  It doesn't need to be created yet.";
	if ((!$authDatabaseName) && ($_POST['authModule'])) $errorMessage[] = "If you are using the authentication module you must enter the auth module database name.  It should be created already so that you can log in.";


	//make sure auth database and tables exist if auth is being used
	if (($authDatabaseName) && ($_POST['authModule'])){

		//first check connecting to host
		$link = new mysqli("$database_host", "$database_username", "$database_password");
		if (!$link) {
			$errorMessage[] = "Could not connect to the server '" . $database_host . "'<br />MySQL Error: " . $link->error;
		}else{

			//next check that the database exists
			$dbcheck = $link->select_db("$authDatabaseName");
			if (!$dbcheck) {
				$errorMessage[] = "Unable to access the auth database '" . $authDatabaseName . "'.  Please verify it has been created.<br />MySQL Error: " . $link->error;
			}else{
				//make sure the tables don't already exist - otherwise this script will overwrite all of the data!
				$query = "SELECT count(*) count FROM information_schema.`COLUMNS` WHERE table_schema = '" . $authDatabaseName . "' AND table_name='Session'";

				//if auth table exists, error out
				if (!$row = $link->query($query)->fetch_array()){
					$errorMessage[] = "Please verify your database user has access to select from the the auth tables and the information_schema MySQL metadata database.";
				}else{
					if ($row['count'] == 0){
						$errorMessage[] = "Please verify your auth database name is correct and the authentication module has been installed.";
					}
				}
			}
		}
	}




	//only continue to checking DB connections if there were no errors this far
	if (count($errorMessage) > 0){
		$step="4";
	}else{

		//write the config file
		$configFile = "../admin/configuration.ini";
		$fh = fopen($configFile, 'w');

		if (!$fh){
			$errorMessage[] = "Could not open file " . $configFile . ".  Please verify you can write to the /admin/ directory.";
		}else{
			if (!$organizationsModule) $organizationsModule = "N";
			if (!$authModule) $authModule = "N";
			if (!$usageModule) $usageModule = "N";
			if (!$resourcesModule) $resourcesModule = "N";
			if (!$useTermsToolFunctionality) $useTermsToolFunctionality = "N";

			//
			// By default this is a stand alone module.  Overriding these install settings.
			// End user will need to modify configuration file manually to enable these modules
			//

			$useTermsToolFunctionality = "N";
			$resourcesModule = "N";
			$usageModule = "N";
			$organizationsModule = "N";

			//
			//

			$iniData = array();
			$iniData[] = "# The Management module is not meant to tie into the other";
			$iniData[] = "# CORAL modules.  They only module that has been tested is the";
			$iniData[] = "# Auth module.  The Management was a clone of the Licensing module";
			$iniData[] = "# originally that was modified for a specific purpose.";
			$iniData[] = "# enable other modules at your own risk.";
			$iniData[] = "[settings]";
			$iniData[] = "organizationsModule=" . $organizationsModule;
			$iniData[] = "organizationsDatabaseName=" . $organizationsDatabaseName;
			$iniData[] = "authModule=" . $authModule;
			$iniData[] = "authDatabaseName=" . $authDatabaseName;
			$iniData[] = "usageModule=" . $usageModule;
			$iniData[] = "resourcesModule=" . $resourcesModule;
			$iniData[] = "resourcesDatabaseName" . $resourcesModuleDatabaseName;
			$iniData[] = "useTermsToolFunctionality=" . $useTermsToolFunctionality;
			$iniData[] = "remoteAuthVariableName=\"" . $remoteAuthVariableName . "\"";
			$iniData[] = "";
			$iniData[] = "[database]";
			$iniData[] = "type = \"mysql\"";
			$iniData[] = "host = \"" . $database_host . "\"";
			$iniData[] = "name = \"" . $database_name . "\"";
			$iniData[] = "username = \"" . $database_username . "\"";
			$iniData[] = "password = \"" . $database_password . "\"";

			fwrite($fh, implode("\n",$iniData));
			fclose($fh);
		}


	}

	if (count($errorMessage) > 0){
		$step="4";
	}


}


?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>CORAL Installation</title>
<link rel="stylesheet" href="css/style.css" type="text/css" />
</head>
<body>
<center>
<table style='width:700px;'>
<tr>
<td style='vertical-align:top;'>
<div style="text-align:left;">


<?php if(!$step){ ?>

	<h3>Welcome to a new CORAL Management installation!</h3>
	This installation will:
	<ul>
		<li>Check that you are running PHP 5</li>
		<li>Connect to MySQL and create the CORAL Management tables</li>
		<li>Test the database connection the CORAL Management application will use </li>
		<li>Set up the config file with settings you choose</li>
	</ul>

	<br />
	To get started you should:
	<ul>
		<li>Create a MySQL Schema for CORAL Management Module - recommended name is coral_management_prod.  Each CORAL module has separate user permissions and requires a separate schema.</li>
		<li>Know your host, username and password for MySQL with permissions to create tables</li>
		<li>It is recommended for security to have a different username and password for CORAL with only select, insert, update and delete privileges to CORAL schemas</li>
		<li>If you are using the CORAL Authentication module, you will need to have it installed and your admin user set up before you can log into Management</li>
		<li>If you are not using CORAL Authentication, the server variable name to access your school's auth system via PHP - for example $HTTP_SERVER_VARS['REMOTE_USER'] or $SERVER['AUTH_USER']</li>
<!--		<li>Know what other systems you will be using operating with - you will be asked whether you are using the Terms Tool Add-On or the Organizations Module.  If you are using the Organizations module you will need to provide the name of the database/schema used for Organizations for inter-operability.  Recommended name is coral_organizations_prod.  For more information about inter-operability refer to the user guide.</li> -->
		<li>Verify that your /admin/ directory is writable by server during the installation process (chmod 777).  After installation you should chmod it back.</li>
	</ul>


	<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
	<input type='hidden' name='step' value='1'>
	<input type="submit" value="Continue" name="submit">
	</form>


<?php
//first step - check system info and verify php 5
} else if ($step == '1') {
	ob_start();
	phpinfo(-1);
	$phpinfo = array('phpinfo' => array());
	if(preg_match_all('#(?:<h2>(?:<a name=".*?">)?(.*?)(?:</a>)?</h2>)|(?:<tr(?: class=".*?")?><t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>)?)?</tr>)#s', ob_get_clean(), $matches, PREG_SET_ORDER))
		foreach($matches as $match){
			if(strlen($match[1]))
				$phpinfo[$match[1]] = array();
			elseif(isset($match[3]))
				$phpinfo[end(array_keys($phpinfo))][$match[2]] = isset($match[4]) ? array($match[3], $match[4]) : $match[3];
			else
				$phpinfo[end(array_keys($phpinfo))][] = $match[2];
		}
?>

	<h3>Getting system info and verifying php version</h3>
	<ul>
	<li>System: <?php echo $phpinfo['phpinfo']['System'];?></li>
	<li>PHP version: <?php echo phpversion();?></li>
	<li>Server API: <?php echo $phpinfo['phpinfo']['Server API'];?></li>
	</ul>

	<br />

	<?php
	if (phpversion() >= 5){
	?>
		<form action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
		<input type='hidden' name='step' value='2'>
		<input type="submit" value="Continue" name="submit">
		</form>
	<?php
	}else{
		echo "<span style='font-size=115%;color:red;'>PHP 5 is not installed on this server!  Installation will not continue.</font>";
	}

//second step - ask for DB info to run DDL
} else if ($step == '2') {

	if (!$database_host) $database_host='localhost';
	if (!$database_name) $database_name='coral_management_prod';
	?>
		<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>">
		<h3>MySQL info with permissions to create tables</h3>
		<?php
			if (count($errorMessage) > 0){
				echo "<span style='color:red'><b>The following errors occurred:</b><br /><ul>";
				foreach ($errorMessage as $err)
					echo "<li>" . $err . "</li>";
				echo "</ul></span>";
			}
		?>
		<table width="100%" border="0" cellspacing="0" cellpadding="2">
		<tr>
			<tr>
				<td>&nbsp;Database Host</td>
				<td>
					<input type="text" name="database_host" value='<?php echo $database_host?>' size="30">
				</td>
			</tr>
			<tr>
				<td>&nbsp;Database Schema Name</td>
				<td>
					<input type="text" name="database_name" size="30" value="<?php echo $database_name?>">
				</td>
			</tr>
			<tr>
				<td>&nbsp;Database Username</td>
				<td>
					<input type="text" name="database_username" size="30" value="<?php echo $database_username?>">
				</td>
			</tr>
			<tr>
				<td>&nbsp;Database Password</td>
				<td>
					<input type="password" name="database_password" size="30" value="<?php echo $database_password?>">
				</td>
			</tr>
			<tr>
				<td colspan=2>&nbsp;</td>
			</tr>
			<tr>
				<td align='left'>&nbsp;</td>
				<td align='left'>
				<input type='hidden' name='step' value='3'>
				<input type="submit" value="Continue" name="submit">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="button" value="Cancel" onclick="document.location.href='install.php'">
				</td>
			</tr>

		</table>
		</form>
<?php
//third step - ask for DB info to log in from CORAL
} else if ($step == '3') {

	?>
		<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>">
		<h3>MySQL user for CORAL web application - with select, insert, update, delete privileges to CORAL schemas</h3>
		*It's recommended but not required that this user is different than the one used on the prior step
		<?php
			if (count($errorMessage) > 0){
				echo "<br /><span style='color:red'><b>The following errors occurred:</b><br /><ul>";
				foreach ($errorMessage as $err)
					echo "<li>" . $err . "</li>";
				echo "</ul></span>";
			}
		?>
		<input type="hidden" name="database_host" value='<?php echo $database_host?>'>
		<input type="hidden" name="database_name" value="<?php echo $database_name?>">

		<table width="100%" border="0" cellspacing="0" cellpadding="2">
		<tr>
			<tr>
				<td>&nbsp;Database Username</td>
				<td>
					<input type="text" name="database_username" size="30" value="<?php echo $database_username?>">
				</td>
			</tr>
			<tr>
				<td>&nbsp;Database Password</td>
				<td>
					<input type="password" name="database_password" size="30" value="<?php echo $database_password?>">
				</td>
			</tr>

			<tr>
				<td colspan="2"><br />&nbsp;Additionally, since user privileges are driven through the web, we will need to set up the first admin account to administer other users.  <br />Please enter either your CORAL Authentication Login ID or your externally authenticated Login ID below.</td>
			</tr>
			<tr>
				<td>&nbsp;Your Login ID</td>
				<td>
					<input type="text" name="admin_login" size="30" value="<?php echo $admin_login?>">
				</td>
			</tr>
			<tr>
				<td colspan=2>&nbsp;</td>
			</tr>
			<tr>
				<td align='left'>&nbsp;</td>
				<td align='left'>
				<input type='hidden' name='step' value='4'>
				<input type="submit" value="Continue" name="submit">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="button" value="Cancel" onclick="document.location.href='install.php'">
				</td>
			</tr>

		</table>
		</form>
<?php
//fourth step - ask for other settings in configuration.ini
} else if ($step == '4') {
	if (!$remoteAuthVariableName) $remoteAuthVariableName = "_SERVER['REMOTE_USER']";
	if ($_POST['organizationsModule']) $organizationsChecked = "checked";
	if ($_POST['authModule']) $authChecked = "checked";
	if ($_POST['usageModule']) $usageChecked = "checked";
	if ($_POST['resourcesModule']) $resourcesChecked = "checked";
	if ($_POST['useTermsToolFunctionality']) $useTermsToolFunctionalityChecked = "checked";

	?>
		<form method="post" action="<?php echo $_SERVER['PHP_SELF']?>">
		<h3>Inter-operability and other config settings</h3>
		<?php
			if (count($errorMessage) > 0){
				echo "<span style='color:red'><b>The following errors occurred:</b><br /><ul>";
				foreach ($errorMessage as $err)
					echo "<li>" . $err . "</li>";
				echo "</ul></span>";
			}
		?>
		<input type="hidden" name="database_host" value='<?php echo $database_host?>'>
		<input type="hidden" name="database_name" value="<?php echo $database_name?>">
		<input type="hidden" name="database_username" value='<?php echo $database_username?>'>
		<input type="hidden" name="database_password" value="<?php echo $database_password?>">

		<table width="100%" border="0" cellspacing="0" cellpadding="2">
		<tr>
<!--
			<tr>
				<td>&nbsp;Are you going to use the Terms Tool Add-On?</td>
				<td>
					<input type="checkbox" name="useTermsToolFunctionality" value="Y" <?php echo $useTermsToolFunctionalityChecked?>>
				</td>
			</tr>
-->
			<tr>
				<td>&nbsp;Are you using the authentication module?</td>
				<td>
					<input type="checkbox" name="authModule" value="Y" <?php echo $authChecked?>>
				</td>
			</tr>
			<tr>
				<td>&nbsp;If so, enter authentication database schema name</td>
				<td>
					<input type="text" name="authDatabaseName" size="30" value="<?php echo $authDatabaseName?>">
				</td>
			</tr>
<!--
			<tr>
				<td>&nbsp;Are you using the organizations module?</td>
				<td>
					<input type="checkbox" name="organizationsModule" value="Y" <?php echo $organizationsChecked?>>
				</td>
			</tr>
			<tr>

				<td>&nbsp;If so, enter organizations database schema name</td>
				<td>
					<input type="text" name="organizationsDatabaseName" size="30" value="<?php echo $organizationsDatabaseName?>">
				</td>
			</tr>
-->
<!--
			<tr>
				<td>&nbsp;Are you using the resources module?</td>
				<td>
					<input type="checkbox" name="resourcesModule" value="Y" <?php echo $resourcesChecked?>>
				</td>
			</tr>
			<tr>
				<td>&nbsp;Are you using the usage module?</td>
				<td>
					<input type="checkbox" name="usageModule" value="Y" <?php echo $usageChecked?>>
				</td>
			</tr>
-->
			<tr>
				<td>&nbsp;Remote Auth Variable Name (required if not using the CORAL Authentication Module)</td>
				<td>
					<input type="text" name="remoteAuthVariableName" size="30" value="<?php echo $remoteAuthVariableName?>">
				</td>
			</tr>
			<tr>
				<td colspan=2>&nbsp;</td>
			</tr>
			<tr>
				<td align='left'>&nbsp;</td>
				<td align='left'>
				<input type='hidden' name='step' value='5'>
				<input type="submit" value="Continue" name="submit">
				&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
				<input type="button" value="Cancel" onclick="document.location.href='install.php'">
				</td>
			</tr>

		</table>
		</form>
<?php
}else if ($step == '5'){ ?>
	<h3>CORAL Management installation is now complete!</h3>
	It is recommended you now:
	<ul>
		<li>Set up your .htaccess file</li>
		<li>Set up your users on the <a href='../admin.php'>admin screen</a>.</li>
	</ul>

<?php
}
?>

</td>
</tr>
</table>
<br />
</center>


</body>
</html>
