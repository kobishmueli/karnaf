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
cat3_name,c3.id AS cat3_id,s.status_name,t.upriority,t.priority,t.merged_to,c3.extra,t.ext1,t.ext2,t.ext3 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON sp.priority_id=t.priority) WHERE t.id=%d", $id);
if($result = sql_fetch_array($query)) {
  if(!IsGroupMember($result['rep_g']) && !IsKarnafEditorSession()) AccessDenied("Ticket is assigned to another team.");
  if($result['is_private'] && !IsGroupMember($result['rep_g']) && !IsKarnafAdminSession()) AccessDenied("Ticket is marked as private.");
?>
</script>
<form name="form1" id="form1" method="post">
<input type="hidden" name="save" id="save" value="2">
<input type="hidden" name="close" id="close" value="0">
<table width="100%">
<tr class="Karnaf_Head2"><td colspan="2">Ticket Information</td></tr>
<td>Status:</td>
<td>
<select name="status">
<?
$query2 = squery("SELECT status_id,status_name FROM karnaf_statuses ORDER BY priority,status_name");
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['status_id']?>"<? if($result['status'] == $result2['status_id']) echo " SELECTED"; ?>><?=$result2['status_name']?></option>
<?
}
sql_free_result($query2);
?>
</select>
</td>
</tr>
<tr>
<td>Ticket Subject:</td>
<td>
<select name="cat3">
<?
$query2 = squery("SELECT c3.name AS cat3_name,c3.id AS cat3_id,c2.name AS cat2_name,c1.name AS cat1_name FROM (karnaf_cat3 AS c3 INNER JOIN karnaf_cat2 AS c2 ON c3.parent=c2.id INNER JOIN karnaf_cat1 AS c1 ON c2.parent=c1.id) ORDER BY c1.priority,c1.name,c2.priority,c2.name,c3.priority,c3.name");
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['cat3_id']?>"<? if($result['cat3_id'] == $result2['cat3_id']) echo " SELECTED"; ?>><?=$result2['cat1_name']." - ".$result2['cat2_name']." - ".$result2['cat3_name']?></option>
<?
}
sql_free_result($query2);
?>
</select>
</td>
</tr>
<?
  if($result['extra']) {
    $i = 0;
    foreach(split(',',$result['extra']) as $row) {
      $i++;
?>
<tr>
<td><?=$row?>:</td>
<td>
<?
      echo "<input name=\"ext".$i."\" value=\"".$result['ext'.$i]."\">";
    }
?>
</tr>
<?
  }
  else echo "<tr><td>Ext1:</td><td><input name=\"ext1\" value=\"".$result['ext1']."\"></td></tr>";
?>
<tr>
<td>User Priority:</td>
<td>
<select name="upriority">
<?
$query2 = squery("SELECT priority_id,priority_name FROM karnaf_priorities ORDER BY priority_id");
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['priority_id']?>"<? if($result2['priority_id']==$result['upriority']) echo " SELECTED"; ?>><?=$result2['priority_name']?></option>
<?
}
sql_free_result($query2);
?>
</select>
</td>
</tr>
<tr>
<td>System Priority:</td>
<td>
<select name="priority">
<?
$query2 = squery("SELECT priority_id,priority_name FROM karnaf_priorities ORDER BY priority_id");
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['priority_id']?>"<? if($result2['priority_id']==$result['priority']) echo " SELECTED"; ?>><?=$result2['priority_name']?></option>
<?
}
sql_free_result($query2);
?>
</select>
</td>
</tr>
<tr>
<td>Private Ticket:</td>
<td><input name="is_private" type="checkbox"<? if($result['is_private']) echo " CHECKED"; ?>>
</td>
</tr>
<tr>
<td>Merge to another ticket:</td>
<td><input name="merged_to" type="text" value="<? if($result['merged_to']) echo $result['merged_to']; ?>"></td>
</tr>
<tr>
<td>Title:</td>
<td><input name="title" type="text" value="<?=htmlspecialchars2($result['title'])?>" style="width:99%"></td>
</tr>
<tr>
<td>Description:</td>
</tr>
<tr>
<td colspan="2">
<textarea name="description" style="width:98%" rows="10">
<?=$result['description']?>
</textarea>
</td>
</tr>
</table>
<input type="checkbox" name="no_userupd" id="no_userupd">&nbsp;Do not email/memo user about this update.
<br><br>
<center>
<input type="submit" name="edit_button" id="edit_button" value="Update Ticket">
</center>
</form>
<?
}
sql_free_result($query);
require_once("karnaf_footer.php");
?>
