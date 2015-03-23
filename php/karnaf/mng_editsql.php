<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
$no_cache = 1;
show_title("Edit SQL Tables");
$sql_table = "none";
if(isset($_GET['table'])) $sql_table = $_GET['table'];
$allowed_tables = array(
                    array("karnaf_priorities","priority_id",array("priority_id","priority_name")),
                    array("karnaf_statuses","status_id",array("status_id","status_name")),
                    array("karnaf_cat1","id",array("name","priority")),
                    array("karnaf_cat2","id",array(
                      "name",
                      "priority",
                      array("parent","sqlselect","select id,name from karnaf_cat1"),
                      array("allowed_group","sqlselect","select '' AS id,'---' AS name union select id,name FROM groups ORDER BY name"),
                    )),
                    array("karnaf_cat3","id",array(
                      "name",
                      "priority",
                      array("parent","sqlselect","select cat2.id,concat(cat1.name,' - ',cat2.name) from (karnaf_cat2 AS cat2 LEFT JOIN karnaf_cat1 AS cat1 ON cat1.id=cat2.parent)"),
                      array("default_priority","sqlselect","select priority_id,priority_name FROM karnaf_priorities"),
                      array("default_group","sqlselect","select '','---' union select id,name FROM groups WHERE iskarnaf=1"),
                      "extra",
                      array("allowed_group","sqlselect","select '' AS id,'---' AS name union select id,name FROM groups ORDER BY name"),
                    )),
                    array("karnaf_mail_accounts","id",array(
                          array("active","sqlselect","select 1,'Yes' union select 0,'No'"),
                          array("type","sqlselect","select 0,'POP3' union select 1,'IMAP' union select 2,'POP3/SSL' union select 3,'IMAP/SSL'"),
                          "host",
                          "port",
                          "user",
                          array("pass","password"),
                          array("cat3_id","sqlselect","select cat3.id,concat(cat1.name,' - ',cat2.name,' - ',cat3.name) from (karnaf_cat3 AS cat3 LEFT JOIN karnaf_cat2 AS cat2 ON cat3.parent=cat2.id LEFT JOIN karnaf_cat1 AS cat1 ON cat1.id=cat2.parent)"),
                          array("default_group","sqlselect","select '','---' union select id,name FROM groups WHERE iskarnaf=1"),
                    )),
                    array("users", "id", array("user", "pass", "email")),
);
$sql_rows = 0;
foreach($allowed_tables as $x) {
  if($sql_table == $x[0]) {
    $sql_table = $x[0];
    $sql_id = $x[1];
    $sql_rows = $x[2];
  }
}
if(!IsKarnafAdminSession() && !($sql_table == "test-table" && IsGroupMember("test-group"))) AccessDenied();
if($sql_table == "none") {
  echo "Choose a table to edit:<br>\n";
  foreach($allowed_tables as $table) {
?>
<a href="mng_editsql.php?table=<?=$table[0]?>"><?=$table[0]?></a><br>
<?
  }
  safe_die("none");
}
if($sql_rows == 0) safe_die("The table doesn't exist.");

$rows_data = array();
if(isset($_POST['submit'])) {
  if(isset($_GET['editrow'])) {
    foreach($sql_rows as $row) {
      if(is_array($row)) $row = $row[0];
      $query = squery(0, "UPDATE ".$sql_table." SET ".$row."='%s' WHERE ".$sql_id."=%d", $_POST["es-".$row], $_GET['editrow']);
    }
    add_log($sql_table, "UPDATE ".$sql_id."=".$_GET['editrow']);
    echo "<div class=\"status_ok\">The row has been updated.</div><br>";
  }
  else {
    if(is_array($sql_rows[0])) $row = $sql_rows[0][0];
    else $row = $sql_rows[0];
    $query = squery(0, "INSERT INTO ".$sql_table."(".$row.") VALUES('%s')", $_POST["es-".$row]);
    $id = sql_insert_id();
    foreach($sql_rows as $row) {
      if(is_array($row)) $row = $row[0];
      $query = squery(0, "UPDATE ".$sql_table." SET ".$row."='%s' WHERE ".$sql_id."=%d", $_POST["es-".$row], $id);
    }
    add_log($sql_table, "INSERT ".$sql_id."=".$id);
    echo "<div class=\"status_ok\">The row has been added.</div><br>";
  }
}
if(!isset($_POST['submit']) && (isset($_GET['editrow']) || isset($_GET['addrow']))) {
  if(isset($_GET['editrow'])) {
    $query = squery(0, "SELECT ".$sql_id.",".merge_array($sql_rows)." FROM $sql_table WHERE ".$sql_id."='%d'", $_GET['editrow']);
    if($query) {
      $result = sql_fetch_array($query);
      foreach($sql_rows as $row) {
        if(is_array($row)) $row = $row[0];
        $rows_data[$row] = $result[$row];
      }
      sql_free_result($query);
    }
  }
?>
<form method="post">
<table>
<?
  foreach($sql_rows as $row) {
    if(is_array($row) && $row[1]=="textarea") {
      echo '<tr><td>'.$row[0].'</td><td><td>';
      echo '<textarea name="es-'.$row[0].'" rows="20" cols="60" dir="ltr">';
      if(isset($rows_data[$row[0]])) echo htmlspecialchars2($rows_data[$row[0]]);
      echo '</textarea>';
      echo '</td></td></tr>';
    }
    else if(is_array($row) && $row[1]=="sqlselect") {
      echo '<tr><td>'.$row[0].'</td><td><td>';
      echo '<select name="es-'.$row[0].'">';
      $query = squery(0, $row[2]);
      while($result = sql_fetch_array($query)) {
        if(isset($rows_data[$row[0]]) && $result[0] == $rows_data[$row[0]])
          echo '<option value="'.$result[0].'" SELECTED>'.$result[1].'</option>';
        else
          echo '<option value="'.$result[0].'">'.$result[1].'</option>';
      }
      sql_free_result($query);
      echo '</select>';
      echo '</td></td></tr>';
    }
    else if(is_array($row) && $row[1]=="password") {
      if(isset($rows_data[$row[0]]))
        echo '<tr><td>'.$row[0].'</td><td><td><input name="es-'.$row[0].'" type="password" size="79" value="'.htmlspecialchars2($rows_data[$row[0]]).'"></td></td></tr>';
      else
        echo '<tr><td>'.$row[0].'</td><td><td><input name="es-'.$row[0].'" type="password" size="79"></td></td></tr>';
    }
    else if(is_array($row)) safe_die("EDITSQL Internal error!"); /* This shouldn't happen! */
    else if(isset($rows_data[$row]))
      echo '<tr><td>'.$row.'</td><td><td><input name="es-'.$row.'" type="textbox" size="79" value="'.htmlspecialchars2($rows_data[$row]).'"></td></td></tr>';
    else
      echo '<tr><td>'.$row.'</td><td><td><input name="es-'.$row.'" type="textbox" size="79"></td></td></tr>';
  }
?>
<tr>
<td colspan="3" align="center">
<input name="submit" type="submit" value="Update">
</td>
</tr>
</table>
</form>
<?
} else {
  if(isset($_GET['delrow'])) {
    $query = squery(0, "DELETE FROM $sql_table WHERE ".$sql_id."='%d'", $_GET['delrow']);
    add_log($sql_table, "DELETE ".$sql_id."=".$_GET['delrow']);
    echo "<div class=\"status_ok\">The row has been deleted.</div><br>";
  }
?>
<table>
<tr>
<?
  foreach($sql_rows as $row) {
    if(is_array($row)) {
      if($row[1] == "sqlselect") {
        $query2 = squery(0, $row[2]);
        while($result2 = sql_fetch_array($query2)) {
          $selects[$row[0]][] = array($result2[0], $result2[1]);
        }
        sql_free_result($query2);
      }
      $row = $row[0];
    }
    echo "<th>$row</th>";
  }
?>
</tr>
<?
  $curcol = "col2";
  $cnt = 0;
  $query = squery(0, "SELECT ".$sql_id.",".merge_array($sql_rows)." FROM $sql_table ORDER BY ".$sql_id);
  if($query) {
    while($result = sql_fetch_array($query)) {
      $cnt++;
      if($curcol=="col1") $curcol = "col2";
      else $curcol = "col1";
?>
<tr>
<?
  foreach($sql_rows as $row) {
    if(is_array($row)) {
      if($row[1] == "password") $text = "*";
      else if($row[1] == "sqlselect") {
        $text = "";
        if(isset($selects[$row[0]])) {
          foreach($selects[$row[0]] as $arr) {
            if($result[$row[0]] == $arr[0]) $text = $arr[1];
          }
        }
        if($text == "") $text = $result[$row[0]];
      }
      else $text = $result[$row[0]];
    }
    else $text = $result[$row];
    if(strlen($text) > 35) $text = substr($text,0,35)."...";
    echo "<td class=\"".$curcol."\">".$text."</td>";
  }
?>
<td class="<?=$curcol?>"><a href="?table=<?=$sql_table?>&editrow=<?=$result[$sql_id]?>">Edit</a> | <a href="?table=<?=$sql_table?>&delrow=<?=$result[$sql_id]?>">Delete</a></td>
</tr>
<?
    }
    if(!$cnt) echo "<tr><td colspan=\"10\" align=\"center\">*** No entries ***</td></tr>";
  }
  sql_free_result($query);
?>
</table>
<a href="?table=<?=$sql_table?>&addrow=1">Add new row</a>
<? } ?>
<?php require_once("karnaf_footer.php"); ?>
