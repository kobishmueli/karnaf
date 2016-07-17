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
cat3_name,s.status_name,up.priority_name AS upriority,sp.priority_name AS priority
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON sp.priority_id=t.priority) WHERE t.id=%d", $id);
if($result = sql_fetch_array($query)) {
  if(!IsGroupMember($result['rep_g']) && !IsKarnafEditorSession()) AccessDenied("Ticket is assigned to another team.");
  if($result['is_private'] && !IsGroupMember($result['rep_g']) && !IsKarnafAdminSession()) AccessDenied("Ticket is marked as private.");
  if(isset($_POST['action_text'])) {
    $group = $result['rep_g'];
    squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time) VALUES(%d,'%s','%s','%s',%d)", $id, $_POST['action_text'], $nick, $group, time());
    echo "<div class=\"status\">The ticket has been updated.</div><br>";
  }
?>
<form name="form1" id="form1" method="post">
<table width="100%">
<tr class="Karnaf_Head2">
<td colspan="2" align="center">Re-assign ticket</td>
</tr>
<tr>
<td>Re-assign to group:</td>
<td>
<select name="assign_group">
<?
  $query2 = squery("SELECT id,name,gdesc FROM groups WHERE iskarnaf=1 ORDER BY name");
  while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['name']?>"<? if(($result2['name'] == $result['rep_g'])) echo " SELECTED"; ?>><?=$result2['gdesc']?></option>
<?
  }
  sql_free_result($query2);
?>
</select>
</td>
</tr>
<tr>
<td colspan="2" align="center">--- OR ---</td>
</tr>
<tr>
<td>Re-assign to user:</td>
<td>
<select name="assign_user">
<option value="">---------------</option>
<?
  $query2 = squery("SELECT id,name,gdesc FROM groups WHERE iskarnaf=1 AND name='%s'", $result['rep_g']);
  if($result2 = sql_fetch_array($query2)) {
    $query3 = squery("SELECT u.user,u.fullname FROM (group_members AS gm INNER JOIN users AS u ON gm.user_id=u.id) WHERE gm.group_id=%d ORDER BY u.user", $result2['id']);
    while($result3 = sql_fetch_array($query3)) {
      if($result3['user'] == $result['rep_u']) $selected = 1;
      if(defined("IRC_MODE") || empty($result3['fullname'])) $result3['fullname'] = $result3['user'];
?>
<option value="<?=$result3['user']?>"<? if($result3['user'] == $result['rep_u']) echo " SELECTED"; ?>><?=$result2['gdesc']?>\<?=$result3['fullname']?></option>
<?
    }
    sql_free_result($query3);
  }
  sql_free_result($query2);
?>
</select>
</td>
</tr>
</table>
<br>
<center>
<input type="submit" name="edit_button" id="edit_button" value="Update Ticket">
</center>
</form>
<?
}
sql_free_result($query);
require_once("karnaf_footer.php");
?>
