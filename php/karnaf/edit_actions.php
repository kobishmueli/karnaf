<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2016 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("../ktools.php");
check_auth();
CheckOperSession();
$isoper = 1;
$id = $_GET['id'];
if(empty($id) || !is_numeric($id)) safe_die("Invalid Ticket ID!");
$query = squery("SELECT t.id,t.randcode,t.status,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,s.status_name,up.priority_name AS upriority,sp.priority_name AS priority,g.private_actions 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON sp.priority_id=t.priority LEFT JOIN groups AS g ON g.name=t.rep_g) WHERE t.id=%d", $id);
if($result = sql_fetch_array($query)) {
  if(!IsGroupMember($result['rep_g']) && !IsKarnafEditorSession()) AccessDenied("Ticket is assigned to another team.");
  if($result['is_private'] && !IsGroupMember($result['rep_g']) && !IsKarnafAdminSession()) AccessDenied("Ticket is marked as private.");
?>
</script>
<form name="form1" id="form1" method="post">
<input type="hidden" name="save" id="save" value="0">
<input type="hidden" name="close" id="close" value="0">
<input type="hidden" name="reopen" id="reopen" value="0">
<table width="100%" class="view_ticket_table">
<tr class="Karnaf_Head2"><td colspan="2">Previous Actions</td></tr>
<tr><td colspan="2">
<table border="0" width="100%">
<tr class="Karnaf_P_Head">
<td>Assignee</td>
<td>Action</td>
<td>Group</td>
<td>Date</td>
</tr>
<?
  $query2 = squery("SELECT action,a_by_u,a_by_g,a_time,a_type,is_private FROM karnaf_actions WHERE tid=%d ORDER BY a_time", $id);
  $cnt = 0;
  while($result2 = sql_fetch_array($query2)) {
    $cnt++;
    $a_type = (int)$result2['a_type'];
    $is_private = (int)$result2['is_private'];
    if($a_type == 2) $action = "The ticket has been re-assigned to ".$result2['action'].".";
    else if($a_type == 3) $action = "The ticket has been re-assigned to ".$result2['action'].".";
    else if($a_type == 4 && $isoper) {
      $action = "The ticket has been privately re-assigned to ".$result2['action'].".";
      $is_private = 1;
    }
    else if($a_type == 4) $action = "The ticket has been re-assigned to Oper.";
    else if($a_type == 5) $action = "Merged from Ticket #".$result2['action'].".";
    else if($a_type == 6) $action = "Merged to Ticket #".$result2['action'].".";
    else $action = $result2['action'];
    if($is_private==1 && !$isoper) continue;
?>
<tr<? if ($is_private && $isoper) echo " bgcolor=lightblue"; ?>>
<td align="center"><?=($is_private==2 && !$isoper)?"Oper":$result2['a_by_u']?></td>
<? if($a_type == 0) { ?>
<td>
<?=show_board_body($action)?>
<?
    if($is_private == 1) echo "<br>(private action)\r\n";
    if($is_private == 2) echo "<br>(hidden oper nick)\r\n";
?>
</td>
<td align="center"><?=$result2['a_by_g']?></td>
<? } else { ?>
<td align="center">*** <?=$action?> ***</td>
<td align="center">---</td>
<? } ?>
<td align="center"><?=showtime($result2['a_time'])?></td>
</tr>
<?
  }
  if(!$cnt) echo "<tr><td colspan=\"4\" align=\"center\">*** None ***</td></tr>";
  sql_free_result($query2);
?>
</table>
</td></tr>
<tr class="Karnaf_Head2">
<td colspan="2" align="center">Add new action</td>
</tr>
<tr>
<td colspan="2">
<textarea rows="8" style="width:99%" name="action_text" id="action_text"></textarea><br>
<? if(IsGroupMember("dalnet-aob") || IsKarnafAdminSession()) { ?>
Action on behalf of:
<select name="onbehalf_g">
<option value="">---</option>
<?
  $query2 = squery("SELECT id,name,gdesc FROM groups ORDER BY name");
  while($result2 = sql_fetch_array($query2)) {
    if(!IsGroupMember($result2['name']) || ($result2['name']!=KARNAF_ADMINS_GROUP && $result2['name']!="dalnet-aob" && $result2['name']!="dalnet-sra" && $result2['name']!=$result['rep_g'])) continue;
?>
<option value="<?=$result2['name']?>"><?=$result2['name']?></option>
<?
  }
  sql_free_result($query2);
?>
</select>
<br>
<? } ?>
Admin CC: 
<select name="rep_cc">
<option value="" SELECTED>---------------</option>
<?
  $query2 = squery("SELECT id,name,gdesc FROM groups WHERE iskarnaf=1 ORDER BY name");
  while($result2 = sql_fetch_array($query2)) {
    if($result2['name'] == PSEUDO_GROUP) continue;
?>
<option value="<?=$result2['name']?>"><?=$result2['gdesc']?></option>
<?
  }
  sql_free_result($query2);
?>
<option value="">---------------</option>
<?
  $query3 = squery("SELECT DISTINCT(u.user),u.fullname FROM (group_members AS gm INNER JOIN users AS u ON gm.user_id=u.id) ".
                   "WHERE gm.group_id IN (SELECT id FROM groups WHERE iskarnaf=1) ORDER BY u.user");
  while($result3 = sql_fetch_array($query3)) {
    if(empty($result3['fullname'])) $result3['fullname'] = $result3['user'];
?>
<option value="<?=$result3['user']?>"><?=$result3['fullname']?></option>
<?
  }
  sql_free_result($query3);
?>
</select>
<br>
<input type="checkbox" name="is_private" id="is_private" CHECKED>&nbsp;Private action (hide it from users).
<br>
<input type="checkbox" name="team_action" id="team_action"<? if($result['private_actions']) echo " CHECKED"; ?>>&nbsp;Team reply (hide the oper's nick).
</td>
</tr>
</table>
<center>
<input type=button name="edit_button" id="edit_button" value="Update Ticket" onClick="javascript:submit1_onclick()">
<? if($result['status']==0) { ?>
<input type=button name="close_button" id="close_button" value="Reopen Ticket" onClick="javascript:submit3_onclick()">
<? } else { ?>
<input type=button name="close_button" id="close_button" value="Close Ticket" onClick="javascript:submit2_onclick()">
<? } ?>
</center>
</form>
<?
}
sql_free_result($query);
require_once("karnaf_footer.php");
?>
