<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession();
if(!isset($_GET['ajax'])) {
  show_title("Check User");
  make_menus("Karnaf (HelpDesk)");
}

function ldapTimeToUnixTime($ldapTime) {
  if($ldapTime == 0) return 0;
  // divide by 10.000.000 to get seconds from 100-nanosecond intervals
  $winInterval = round($ldapTime / 10000000);
  // substract seconds from 1601-01-01 -> 1970-01-01
  $unixTimestamp = ($winInterval - 11644473600);

  return $unixTimestamp;
}

function ldapTimeToNormalTime($ldapTime) {
  if($ldapTime == 0) return "Never";
  // divide by 10.000.000 to get seconds from 100-nanosecond intervals
  $winInterval = round($ldapTime / 10000000);
  // substract seconds from 1601-01-01 -> 1970-01-01
  $unixTimestamp = ($winInterval - 11644473600);

  return showtime($unixTimestamp);
}

if(isset($_GET['tid']) && is_numeric($_GET['tid'])) $tid = $_GET['tid'];
else $tid = "";
if(isset($_GET['uuser'])) $uuser = $_GET['uuser'];
if(isset($_POST['uuser'])) $uuser = $_POST['uuser'];
if(isset($uuser)) {
  add_log("karnaf_check_user", $uuser);
  $query = squery("SELECT type,host,user,pass,ou,filter FROM karnaf_ldap_accounts WHERE active=1");
  while($result = sql_fetch_array($query)) {
    $type = (int)$result['type'];
    if($type == 0) $ldapserver = "ldap://".$result['host'];
    else if($type == 1) $ldapserver = "ldaps://".$result['host'];
    else continue;
    $ldap = ldap_connect($ldapserver);
    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    if($bind = @ldap_bind($ldap, $result['user'], $result['pass'])) {
      $attr = array("company","mail","title","department","givenname","sn","telephonenumber","mobile","physicaldeliveryofficename","samaccountname","manager","distinguishedName","memberof","accountExpires","lastLogon","lockouttime","userAccountControl","pwdlastset");
      if($result = ldap_search($ldap, $result['ou'], "samaccountname=".$uuser, $attr)) {
        ldap_sort($ldap, $result, "sn");
        $entries = ldap_get_entries($ldap, $result);
        foreach($entries as $entry) {
          $found_user = $entry['samaccountname'][0];
          if(empty($found_user)) continue;
          if(isset($entry['accountexpires'][0])) {
            $found_expiration = ldapTimeToNormalTime($entry['accountexpires'][0]);
            $found_expiration_time = ldapTimeToUnixTime($entry['accountexpires'][0]);
          }
          else {
            $found_expiration = "N/A";
            $found_expiration_time = 0;
          }
          if(isset($entry['lockouttime'][0]) && (int)$entry['lockouttime'][0]!=0) $found_lockout = "Yes";
          else $found_lockout = "No";
          if(isset($entry['title'][0])) $found_title = $entry['title'][0];
          else $found_title = "";
          if(isset($entry['givenname'][0])) $found_fname = $entry['givenname'][0];
          else $found_fname = "";
          if(isset($entry['sn'][0])) $found_lname = $entry['sn'][0];
          else $found_lname = "";
          if(isset($entry['mobile'][0])) $found_mobile = $entry['mobile'][0];
          else $found_mobile = "";
          if(isset($entry['lastlogon'][0])) $found_lastlogin = ldapTimeToNormalTime($entry['lastlogon'][0]);
          else $found_lastlogin = "";
          if(isset($entry['pwdlastset'][0])) $found_lastpwdset = ldapTimeToNormalTime($entry['pwdlastset'][0]);
          else $found_lastpwdset = "N/A";
          if(isset($entry['useraccountcontrol'][0])) $found_flags = (int)$entry['useraccountcontrol'][0];
          else $found_flags = 0;
          if($found_flags & 0x2) $found_disabled = "Yes";
          else $found_disabled = "No";
          if(isset($entry['department'][0])) $found_department = $entry['department'][0];
          else $found_department = "N/A";
          if(isset($entry['company'][0])) $found_company = $entry['company'][0];
          else $found_company = "N/A";
          $found_groups = "";
          $found_all = 0;
          if(isset($entry['memberof'])) {
            foreach($entry['memberof'] as $grps) {
              if(substr($grps,0,3) == "CN=") {
                $pos = strpos($grps,",");
                if(!$pos) continue;
                $grp = substr($grps,3,$pos-3);
                $found_groups .= $grp."<br>\n";
                if($grp == "All Employees") $found_all = 1;
              }
            }
          }
          if($found_disabled == "Yes") echo "<div class=\"status_err\">Warning: The user is disabled!</div><br>";
          if($found_lockout == "Yes") echo "<div class=\"status_err\">Warning: The user is locked!</div><br>";
          if($found_expiration_time && ($found_expiration_time < time())) echo "<div class=\"status_err\">Warning: The user has expired!</div><br>";
          if(!$found_all) echo "<div class=\"status_err\">Warning: The user is not a member of the All Employees group!</div><br>";
?>
<table>
<tr>
<td>User:</td>
<td><?=$found_user?></td>
</tr>
<tr>
<td>First Name:</td>
<td><?=$found_fname?></td>
</tr>
<tr>
<td>Last Name:</td>
<td><?=$found_lname?></td>
</tr>
<tr>
<td>Title:</td>
<td><?=$found_title?></td>
</tr>
<tr>
<td>Team:</td>
<td><?=$found_department?></td>
</tr>
<tr>
<td>Department:</td>
<td><?=$found_company?></td>
</tr>
<tr>
<td>Mobile:</td>
<td><?=$found_mobile?></td>
</tr>
<tr>
<td>Last Login:</td>
<td><?=$found_lastlogin?></td>
</tr>
<tr>
<td>Last Password Set:</td>
<td><?=$found_lastpwdset?></td>
</tr>
<tr>
<td>Expiration:</td>
<td><?=$found_expiration?></td>
</tr>
<tr>
<td>Account Locked:</td>
<td><?=$found_lockout?></td>
</tr>
<tr>
<td>Account Disabled:</td>
<td><?=$found_disabled?></td>
</tr>
<tr>
<td valign="top">Groups:</td>
<td><?=$found_groups?></td>
</tr>
</table>
<?
        }
      }
    }
    ldap_unbind($ldap);
  }
  sql_free_result($query);
  if(!isset($found_user)) echo "<div class=\"status_err\">Error: couldn't find the user!</div><br>";
?>
<br><br>
<center><font size="+2">
<a href="check_user.php">Check Another User</a>
</font></center>
<?
} else {
?>
<script type="text/javascript">
function setinfo(username,name,email,phone) {
  form1.uuser.value = username;
}

function open_search() {
  window.open("searchuser.php","searchwin","status=0,toolbar=0,location=0,scrollbars=1,width=500,height=200");
}
</script>
<center>
<form name="form1" action="check_user.php" method="post">
<table>
<tr class="Karnaf_Head2"><td colspan="3">Check User</td></tr>
<tr>
<td>Username to check:</td>
<td>
<input name="uuser" size="30" type="text">
<? if(!isset($_GET['ajax'])) { ?>
<? } ?>
</td>
<td><img src="<?=MY_URL."/".$themepath?>search-icon.png" style="cursor:pointer" onClick="javascript:open_search()"></td>
</tr>
<!-- For future use:
<tr>
<td>Ticket ID:</td>
<td>
<input name="tid" size="30" type="text" value="<?=$tid?>">
</td>
</tr>
-->
<tr><td colspan="3" align="center">
<input name="submit" type="submit" value="Continue">
</td></tr>
</table>
</form>
</center>
<? } ?>
<?php require_once("karnaf_footer.php"); ?>
