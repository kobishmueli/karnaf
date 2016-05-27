<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2016 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("../ktools.php");
check_auth();
CheckOperSession();
$id = $_GET['id'];
if(empty($id) || !is_numeric($id)) safe_die("Invalid Ticket ID!");
$query = squery("SELECT t.id,t.randcode,t.status,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,c3.id AS cat3_id,s.status_name,t.upriority,t.priority,t.cc 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON sp.priority_id=t.priority) WHERE t.id=%d", $id);
if($result = sql_fetch_array($query)) {
?>
</script>
<form name="form1" id="form1" method="post">
<input type="hidden" name="save" id="save" value="3">
<input type="hidden" name="close" id="close" value="0">
<table width="100%">
<tr class="Karnaf_Head2"><td colspan="2">User Information</td></tr>
<tr>
<td><?=USER_FIELD?>:</td>
<td><input name="unick" type="text" value="<?=$result['unick']?>"><input type="button" value="Search" onClick="javascript:open_search()"></td>
</tr>
<tr>
<td>Identified:</td>
<td><input name="is_real" type="checkbox"<? if($result['is_real']) echo " CHECKED"; ?>>
</tr>
<tr>
<td>Name:</td>
<td><input name="ufullname" type="text" value="<?=$result['ufullname']?>"></td>
</tr>
<tr>
<td>E-Mail:</td>
<td><input name="uemail" type="text" value="<?=$result['uemail']?>"></td>
</tr>
<tr>
<td>CC:</td>
<td><input name="cc" type="text" value="<?=htmlspecialchars2($result['cc'])?>"></td>
</tr>
<? if(!defined("IRC_MODE")) { ?>
<tr>
<td>Phone:</td>
<td><input name="uphone" type="text" value="<?=$result['uphone']?>"></td>
</tr>
<? } ?>
<tr>
<td>IP:</td>
<td><input name="uip" type="text" value="<?=$result['uip']?>"></td>
</tr>
<tr>
<td>Update by Mail:</td>
<td><input name="email_upd" type="checkbox"<? if($result['email_upd']) echo " CHECKED"; ?>>
</tr>
<tr>
<td>Update by Memo:</td>
<td><input name="memo_upd" type="checkbox"<? if($result['memo_upd']) echo " CHECKED"; ?>>
</tr>
</table>
<input type="checkbox" name="no_userupd" id="no_userupd">&nbsp;Do not email/memo user about this update.
<br><br>
<center>
<input type="submit" name="edit_button" id="edit_button" value="Update Ticket">
</center>
</form>
<br><br>
Other open tickets for this user:<br>
<table border="1" bordercolor="Black">
<tr>
</tr>
<tr class="Karnaf_P_Head">
<td>ID</td>
<td><?=USER_FIELD?></td>
<td>E-Mail</td>
<td>Assigned to</td>
</tr>
<?
  $cnt = 0;
  $unick = $result['unick'];
  $uemail = $result['uemail'];
  if($unick == "Guest") $unick .= RandomNumber(5);
  if(empty($uemail)) $uemail = "Guest".RandomNumber(5)."@".MY_DOMAIN;
  $query2 = squery("SELECT id,status,unick,uemail,rep_g FROM karnaf_tickets WHERE id!=%d AND status!=0 AND (unick='%s' OR uemail='%s')",
                   $id, $unick, $uemail);
  while($result2 = sql_fetch_array($query2)) {
    $cnt++;
?>
<tr>
<td><a href="edit.php?id=<?=$result2['id']?>"><?=$result2['id']?></a></td>
<td><?=$result2['unick']?></td>
<td><?=$result2['uemail']?></td>
<td><?=$result2['rep_g']?></td>
</tr>
<?
  }
  if(!$cnt) echo "<tr><td colspan=\"4\" align=\"center\">*** None ***</td></tr>";
  sql_free_result($query2);
?>
</table>
<?
}
sql_free_result($query);
require_once("karnaf_footer.php");
?>
