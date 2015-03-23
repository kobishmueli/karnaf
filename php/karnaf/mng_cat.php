<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession();
if(!IsKarnafAdminSession()) AccessDenied();
show_title("Manage Karnaf Categories");
make_menus("Karnaf (HelpDesk)");
$table = "cat1";
$cat = "Type";
if(isset($_GET['table'])) {
  if($_GET['table'] == "cat2") {
    $table = "cat2";
    $cat = "Category";
    $ptable = "cat1";
  }
  else if($_GET['table'] == "cat3") {
    $table = "cat3";
    $cat = "Subject";
    $ptable = "cat2";
  }
}
if(isset($_GET['del'])) {
  squery("DELETE FROM karnaf_".$table." WHERE id=%d", $_GET['del']);
  add_log("Karnaf_".$cat, "DELETE #".$_GET['del']);
  echo "<div class=\"status\">The ".strtolower($cat)." has been deleted.</div><br>";
}
else if(isset($_POST['new'])) {
  if(isset($_POST['parent'])) {
    if($table == "cat3") squery("INSERT INTO karnaf_".$table."(name,priority,parent,default_group,default_priority,extra,allowed_group) VALUES('%s',%d,%d,'%s',%d,'%s','%s')",
                                $_POST['name'], $_POST['priority'], $_POST['parent'], $_POST['default_group'], $_POST['default_priority'], $_POST['extra'], $_POST['allowed_group']);
    else squery("INSERT INTO karnaf_".$table."(name,priority,parent,allowed_group) VALUES('%s',%d,%d,'%s')", $_POST['name'], $_POST['priority'], $_POST['parent'], $_POST['allowed_group']);
  }
  else squery("INSERT INTO karnaf_".$table."(name,priority) VALUES('%s',%d)", $_POST['name'], $_POST['priority']);
  add_log("Karnaf_".$cat, "INSERT ".$_POST['name']);
  echo "<div class=\"status\">The ".strtolower($cat)." has been added.</div><br>";
}
?>
<table border="1" bordercolor="000000">
<tr class="Karnaf_Head2">
<th>ID</th>
<th>Name</th>
<th>Priority</th>
<? if($table != "cat1") { ?>
<th>Parent</th>
<? } ?>
<? if($table == "cat3") { ?>
<th>Default Group</th>
<th>Default Priority</th>
<th>Extra rows</th>
<? } ?>
<? if($table != "cat1") { ?>
<th>Allowed Group</th>
<? } ?>
</tr>
<?
  if($table == "cat1") $query = squery("SELECT id,name,priority FROM karnaf_".$table." ORDER BY priority,name");
  else if($table == "cat2") $query = squery("SELECT c.id,c.name,c.priority,p.name AS pname,c.allowed_group FROM (karnaf_".$table." AS c INNER JOIN karnaf_".$ptable." AS p ON c.parent=p.id) ORDER BY c.priority,c.name");
  else $query = squery("SELECT c.id,c.name,c.priority,c.default_group,kp.priority_name,c.extra,concat(cat1.name,' - ',p.name) AS pname,c.allowed_group FROM (karnaf_".$table." AS c INNER JOIN karnaf_".$ptable." AS p ON c.parent=p.id INNER JOIN karnaf_priorities AS kp ON kp.priority_id=c.default_priority INNER JOIN karnaf_cat1 AS cat1 ON cat1.id=p.parent) ORDER BY c.priority,c.name");
  while($result = sql_fetch_array($query)) {
?>
<tr>
<td><?=$result['id']?></td>
<td><?=$result['name']?></td>
<td><?=$result['priority']?></td>
<? if($table != "cat1") { ?>
<td><?=$result['pname']?></td>
<? } ?>
<? if($table == "cat3") { ?>
<td><?=$result['default_group']?></td>
<td><?=$result['priority_name']?></td>
<td><?=$result['extra']?></td>
<? } ?>
<? if($table != "cat1") { ?>
<td><?=$result['allowed_group']?></td>
<? } ?>
<td><a href="?table=<?=$table?>&del=<?=$result['id']?>">Delete</a></td>
</tr>
<?
  }
  sql_free_result($query);
?>
</table>
<br><br>
<form name="form1" action="?table=<?=$table?>" method="post">
<input name="new" type="hidden" value="1">
<table border="1">
<tr class="Karnaf_Head2"><th colspan="2">Add new <?=strtolower($cat)?></th></tr>
<tr>
<td>Name:</td>
<td><input name="name" size="30" type="text"></td>
</tr>
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
<? if($table != "cat1") { ?>
<tr>
<td>Parent:</td>
<td>
<select name="parent">
<?
  if($table == "cat3")
   $query = squery("SELECT c2.id,concat(c1.name,' - ',c2.name) AS name,c2.priority FROM karnaf_cat2 AS c2 INNER JOIN karnaf_cat1 AS c1 ON c2.parent=c1.id ORDER BY priority,name");
  else
   $query = squery("SELECT id,name,priority FROM karnaf_".$ptable." ORDER BY priority,name");
  while($result = sql_fetch_array($query)) {
?>
<option value="<?=$result['id']?>"><?=$result['name']?></option>
<?
  }
  sql_free_result($query);
?>
</select>
</td>
</tr>
<? } ?>
<? if($table == "cat3") { ?>
<tr>
<td>Default Group:</td>
<td>
<select name="default_group">
<option value="">---</option>
<?
  $default_priority = 0;
  $query = squery("SELECT name FROM groups WHERE iskarnaf=1 ORDER BY name");
  while($result = sql_fetch_array($query)) {
?>
<option value="<?=$result['name']?>"><?=$result['name']?></option>
<?
  }
  sql_free_result($query);
?>
</select>
</td>
</tr>
<tr>
<td>Default Priority:</td>
<td>
<select name="default_priority">
<?
  $default_priority = 0;
  $query = squery("SELECT priority_id,priority_name FROM karnaf_priorities ORDER BY priority_id");
  while($result = sql_fetch_array($query)) {
?>
<option value="<?=$result['priority_id']?>"<? if($result['priority_id']==$default_priority) echo " SELECTED"; ?>><?=$result['priority_name']?></option>
<?
  }
  sql_free_result($query);
?>
</select>
</td>
</tr>
<tr>
<td>Extra Rows:</td>
<td><input name="extra" type="text"></td>
</tr>
<? } ?>
<? if($table != "cat1") { ?>
<tr>
<td>Allowed Group:</td>
<td>
<select name="allowed_group">
<option value="">---</option>
<?
  $default_priority = 0;
  $query = squery("SELECT name FROM groups ORDER BY name");
  while($result = sql_fetch_array($query)) {
?>
<option value="<?=$result['name']?>"><?=$result['name']?></option>
<?
  }
  sql_free_result($query);
?>
</select>
</td>
</tr>
<? } ?>
<tr>
<td colspan="2" align="center"><input name="submit" type="submit" value="Add"></td>
</tr>
</table>
</form>
<?
require_once("karnaf_footer.php");
?>
