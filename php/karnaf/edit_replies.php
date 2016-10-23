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
$query = squery("SELECT t.id,t.randcode,t.status,t.title,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,s.status_name,up.priority_name AS upriority,sp.priority_name AS priority,g.private_actions,t.cc 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON sp.priority_id=t.priority LEFT JOIN groups AS g ON g.name=t.rep_g) WHERE t.id=%d", $id);
if($result = sql_fetch_array($query)) {
  if(!IsGroupMember($result['rep_g']) && !IsKarnafEditorSession()) AccessDenied("Ticket is assigned to another team.");
  if($result['is_private'] && !IsGroupMember($result['rep_g']) && !IsKarnafAdminSession()) AccessDenied("Ticket is marked as private.");
?>
<form name="form1" id="form1" method="post">
<input type="hidden" name="save" id="save" value="6">
<input type="hidden" name="close" id="close" value="0">
<input type="hidden" name="reopen" id="reopen" value="0">
<? if(isset($_GET['short'])) { ?>
<input type="hidden" name="short" id="short" value="1">
<? } ?>
<table width="100%">
<? if(isset($_GET['short'])) { ?>
<tr><td colspan="2">
<table border="1" width="100%" cellpadding="0" cellspacing="0">
<tr class="Karnaf_Head2"><td colspan="2">Ticket Description</td></tr>
<tr><td class="ticket_body" colspan="2">
<?
  if(!empty($result['title'])) {
    echo "Title: ";
    show_board_body($result['title']);
    echo "<hr>\n";
  }
  $description = $result['description'];
  $description = preg_replace("/(\*)?\[image\:\sironSource\](\*)?.*Thank\syou\./s", "*** Signature ***", $description);
?>
<?=show_board_body($description)?>
</td></tr>
</table>
</td></tr>
<? } else { ?>
<tr><td colspan="2">
<table border="1" width="100%" cellpadding="0" cellspacing="0">
<tr class="Karnaf_Head2"><td colspan="2">Replies</td></tr>
<?
  $query2 = squery("SELECT reply,r_time,r_from,r_by,ip FROM karnaf_replies WHERE tid=%d ORDER BY r_time", $id);
  $cnt = 0;
  while($result2 = sql_fetch_array($query2)) {
    $cnt++;
    $reply = preg_replace("/(\*)?\[image\:\sironSource\](\*)?.*Thank\syou\./s", "*** Signature ***", $result2['reply']);
?>
<tr class="Karnaf_P_Head"><td colspan="2">Reply #<?=$cnt?> from <?=$result2['r_from']?> [<?=USER_FIELD?>: <?=$result2['r_by']?> / IP: <?=IsKarnafAdminSession()?$result2['ip']:"HIDDEN"?>] at <?=showtime($result2['r_time'])?></td></tr>
<tr>
<td class="ticket_replies" colspan="2"><?=show_board_body($reply)?></td>
</tr>
<?
  }
  if(!$cnt) echo "<tr><td colspan=\"2\" align=\"center\">*** None ***</td></tr>\r\n";
  sql_free_result($query2);
?>
</table>
</td></tr>
<? } ?>
<tr class="Karnaf_Head2">
<td colspan="2" align="center">Add new reply</td>
</tr>
<tr>
<td width="1%">To:</td>
<td><input name="reply_to" type="text" size="50" value="<?=htmlspecialchars2($result['uemail'])?>"></td>
</tr>
<tr>
<td width="1%">CC:</td>
<td><input name="reply_cc" type="text" size="50" value="<?=htmlspecialchars2($result['cc'])?>"></td>
</tr>
<tr>
<td colspan="2">
<textarea rows="8" style="width:100%" name="reply_text" id="reply_text"></textarea><br>
<input type="checkbox" name="is_private" id="is_private" <? if($result['private_actions']) echo " CHECKED"; ?>>&nbsp;Team reply (hide the oper's nick).
<br>
<input type="checkbox" name="is_waiting" id="is_waiting" CHECKED>&nbsp;Hold the ticket until the user reply.
<br>
<input type="checkbox" name="auto_assign" id="auto_assign" <? if(empty($result['rep_u'])) echo " CHECKED"; ?>>&nbsp;Automatically assign the ticket to me if it's not assigned to anyone.
<br>
Template: 
<select name="template" onChange="javascript:load_template(this.value);">
<option value="0">---</option>
<?
  $query2 = squery("SELECT id,subject FROM karnaf_templates WHERE group_id=(SELECT id FROM groups WHERE name='%s') OR group_id=(SELECT id FROM groups WHERE name='%s') ORDER BY subject", $result['rep_g'], PSEUDO_GROUP);
  while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['id']?>"><?=$result2['subject']?></option>
<?
  }
  sql_free_result($query2);
?>
</select>
</td>
</tr>
</table>
<br>
<center>
<? if($result['status']==0) { ?>
<input type=button name="edit_button" id="edit_button" value="Update Ticket" onClick="javascript:submit3_onclick()">
<input type=button name="close_button" id="close_button" value="Reopen Ticket" onClick="javascript:submit3_onclick()">
<? } else { ?>
<input type=button name="edit_button" id="edit_button" value="Update Ticket" onClick="javascript:submit1_onclick()">
<input type=button name="close_button" id="close_button" value="Close Ticket" onClick="javascript:submit2_onclick()">
<? } ?>
</center>
</form>
<?
}
sql_free_result($query);
require_once("karnaf_footer.php");
?>
