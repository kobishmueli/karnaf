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
<input type="hidden" name="save" id="save" value="0">
<input type="hidden" name="close" id="close" value="0">
<input type="hidden" name="reopen" id="reopen" value="0">
<table width="100%">
<tr class="Karnaf_Head2">
<td colspan="2" align="center">Send SMS</td>
</tr>
<tr>
<td>SMS Account:</td>
<td>
<select name="sms_account" id="sms_account">
<?
  $query2 = squery("SELECT id,from_number FROM karnaf_sms_accounts WHERE active=1");
  while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['id']?>"><?=$result2['from_number']?></option>
<?
  }
  sql_free_result($query2);
?>
</select>
</td>
</tr>
<tr>
<td>To:</td>
<td><input name="sms_to" type="text" size="50" value="<?=htmlspecialchars2($result['uphone'])?>"></td>
</tr>
<tr>
<td colspan="2">
<textarea rows="8" style="width:100%" name="sms_body" id="sms_body"></textarea><br>
</td>
</tr>
</table>
<br>
<center>
<input type=button name="edit_button" id="edit_button" value="Send SMS" onClick="javascript:submit1_onclick()">
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
