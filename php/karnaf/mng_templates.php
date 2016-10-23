<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2016 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession();
if(!IsGroupMember("dalnet-leads") && !IsKarnafAdminSession()) AccessDenied();
show_title("Manage Karnaf Templates");
make_menus("Karnaf (HelpDesk)");
if(isset($_GET['del'])) {
  squery("DELETE FROM karnaf_templates WHERE id=%d", $_GET['del']);
  add_log("Karnaf_Templates", "DELETE #".$_GET['del']);
  echo "<div class=\"status\">The template has been deleted.</div><br>";
}
else if(isset($_POST['id']) && !empty($_POST['id'])) {
  squery("UPDATE karnaf_templates SET group_id=%d,subject='%s',body='%s' WHERE id=%d", $_POST['group'], $_POST['subject'], $_POST['body'], $_POST['id']);
  add_log("Karnaf_Templates", "UPDATE #".$_POST['id']);
  echo "<div class=\"status\">The template has been updated.</div><br>";
}
else if(isset($_POST['new'])) {
  squery("INSERT INTO karnaf_templates(group_id,subject,body) VALUES(%d,'%s','%s')", $_POST['group'], $_POST['subject'], $_POST['body']);
  add_log("Karnaf_Templates", "INSERT ".$_POST['subject']);
  echo "<div class=\"status\">The template has been added.</div><br>";
}
$r_cmd = "Add";
$r_body = "";
$r_subject = "";
$r_group = "";
$r_id = "";
?>
<table border="1" bordercolor="000000">
<tr class="Karnaf_Head2">
<th>ID</th>
<th>Group</th>
<th>Subject</th>
</tr>
<?
  $query = squery("SELECT t.id,g.name,t.subject,t.body FROM (karnaf_templates AS t INNER JOIN groups AS g ON g.id=t.group_id) ORDER BY subject");
  while($result = sql_fetch_array($query)) {
    if(isset($_GET['edit']) && $result['id']==$_GET['edit']) {
      $r_cmd = "Edit";
      $r_body = $result['body'];
      $r_subject = $result['subject'];
      $r_group = $result['name'];
      $r_id = $result['id'];
    }
?>
<tr>
<td><?=$result['id']?></td>
<td><?=$result['name']?></td>
<td><span title="<?=str_replace("\"","''",$result['body'])?>" style="cursor:pointer"><?=$result['subject']?></span></td>
<td><a href="?edit=<?=$result['id']?>">Edit</a> | <a href="?del=<?=$result['id']?>">Delete</a></td>
</tr>
<?
  }
  sql_free_result($query);
?>
</table>
<? if($r_cmd == "Edit") { ?>
<a href="mng_templates.php">Add new template</a>
<? } ?>
<br><br>
<form name="form1" method="post">
<input name="new" type="hidden" value="1">
<input name="id" type="hidden" value="<?=$r_id?>">
<table border="1">
<tr class="Karnaf_Head2"><th colspan="2"><?=$r_cmd?> template</th></tr>
<tr>
<td>Subject:</td>
<td><input name="subject" size="30" type="text" value="<?=$r_subject?>"></td>
</tr>
<tr>
<td>Body:</td>
<td><textarea rows="8" cols="78" name="body" id="body"><?=$r_body?></textarea></td>
</tr>
<tr>
<td>Group:</td>
<td>
<select name="group">
<?
  $query = squery("SELECT id,name FROM groups WHERE iskarnaf=1 ORDER BY name");
  while($result = sql_fetch_array($query)) {
?>
<option value="<?=$result['id']?>"<? if($result['name'] == $r_group) echo " SELECTED"; ?>><?=$result['name']?></option>
<?
  }
  sql_free_result($query);
?>
</select>
</td>
</tr>
<!--
<tr>
<td>Priority:</td>
<td>
<select name="priority">
<?
  $priority = 10;
  for($i = 0; $i <= 20; $i++) {
?>
<option value="<?=$i?>"<? if($i == $priority) echo " SELECTED"; ?>><?=$i?></option>
<?
  }
?>
</select>
</td>
</tr>
-->
<tr>
<td colspan="2" align="center"><input name="submit" type="submit" value="<?=$r_cmd?>"></td>
</tr>
</table>
</form>
<?
require_once("karnaf_footer.php");
?>
