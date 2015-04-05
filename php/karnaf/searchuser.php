<?
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

include("../ktools.php");
check_auth();
CheckOperSession();
?>
<html>
<head>
<meta HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=windows-1255">
<link rel="stylesheet" type="text/css" href="default.css">
<meta HTTP-EQUIV="Expires" CONTENT="0">
<title>karnaf</title>
<script language="JavaScript">
function setinfo(username,name,email,phone) {
  window.opener.setinfo(username,name,email,phone);
  window.close();
}
</script>
</head>
<body>
<div dir="ltr">
<? if(isset($_POST['s_username']) or isset($_POST['s_fname']) or isset($_POST['s_lname']) or isset($_POST['s_location'])) { ?>
<table border=1>
<tr class="Karnaf_L_Head">
<td>Username</td>
<td>Full Name</td>
<td>E-Mail</td>
<td>Department</td>
<td>Phone</td>
</tr>
<?
  $next = 0;
  $limit = 100;
  $argv = array();
  $querystr = "SELECT user,email,fullname,phone,department FROM users WHERE 1 ";
  if(!empty($_POST['s_username'])) {
    $querystr .= " AND user LIKE '%s'";
    array_push($argv, "%".$_POST['s_username']."%");
  }
  if(!empty($_POST['s_name'])) {
    $querystr .= " AND fullname LIKE '%s'";
    array_push($argv, "%".$_POST['s_name']."%");
  }
  if(!empty($_POST['s_location'])) {
    $querystr .= " AND department LIKE '%s'";
    array_push($argv, "%".$_POST['s_location']."%");
  }
  $querystr .= " ORDER BY user LIMIT ".$next.",".($next+$limit+1);
  array_unshift($argv, $querystr);
  $query = squery_args($argv);
  $cnt = 0;
  while($result = sql_fetch_array($query)) {
    $cnt++;
?>
<tr>
<td>
<a
href="javascript:setinfo('<?=$result['user']?>','<?=$result['fullname']?>','<?=$result['email']?>','<?=$result['phone']?>')">
<?=$result['user']?>
</a>
</td>
<td><?=$result['fullname']?></td>
<td><?=$result['email']?></td>
<td><?=$result['department']?></td>
<td><?=$result['phone']?></td>
</tr>
<?
  }
  if(!$cnt) echo "<tr><td colspan=\"5\" align=\"center\">*** No matches found ***</td></tr>";
  sql_free_result($query);
?>
</table>
<br>
<center><a href="javascript:history.back()">Go back</a></center>
<? } else { ?>
<form method="post">
<center>
<table width="100%" border=0>
<tr>
<td>Username:</td>
<td><input name="s_username" type="text"></td>
</tr>
<tr>
<td>Name:</td>
<td><input name="s_name" type="text"></td>
</tr>
<tr>
<td>Location:</td>
<td><input name="s_location" type="text"></td>
</tr>
</table>
<br>
<input type="submit" value="Search">
</center>
</form>
<? } ?>
</div>
<? include("karnaf_footer.php"); ?>
</body>
</html>

