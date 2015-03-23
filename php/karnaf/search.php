<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession();
show_title("Search Tickets");
make_menus("Karnaf (HelpDesk)");
if(isset($_POST['max_results'])) {
?>
<table border="1" width="90%" bgcolor="White" style="border-collapse: collapse" bordercolor="#111111" cellpadding="0" cellspacing="0">
<tr class="Karnaf_L_Head">
<th>ID</th>
<th>Nick</th>
<td>Opened by</td>
<td>Assigned to</td>
<td>Priority</td>
<td>Open Date</td>
<td>Actions / Replies</td>
<td>Duration</td>
</tr>
<?
  $limit = 100;
  $next = 0;
  if(isset($_POST['max_results'])) {
    $limit = $_POST['max_results'];
    if(!is_numeric($limit)) safe_die("Invalid number for max_results!");
  }
  if(isset($_POST['next'])) {
    $next = $_POST['next'];
    if(!is_numeric($next)) safe_die("Invalid number for next!");
  }
  $argv = array();
  $qstr = "SELECT t.id,t.randcode,t.status,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,s.status_name,up.priority_name AS upriority,t.priority,sp.priority_name AS spriority,t.close_time 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON
sp.priority_id=t.priority) WHERE 1";
  if(isset($_POST['opened_by']) && !empty($_POST['opened_by'])) {
    $qstr .= " AND opened_by LIKE '%s'";
    array_push($argv, $_POST['opened_by']);
  }
  if(isset($_POST['oper']) && !empty($_POST['oper'])) {
    $qstr .= " AND t.rep_u='%s'";
    array_push($argv, $_POST['oper']);
  }
  if(isset($_POST['tid']) && !empty($_POST['tid'])) {
    $qstr .= " AND t.id=%d";
    array_push($argv, $_POST['tid']);
  }
  if(isset($_POST['unick']) && !empty($_POST['unick'])) {
    $qstr .= " AND unick LIKE '%s'";
    array_push($argv, $_POST['unick']);
  }
  if(isset($_POST['uname']) && !empty($_POST['uname'])) {
    $qstr .= " AND uname LIKE '%s'";
    array_push($argv, $_POST['uname']);
  }
  if(isset($_POST['uemail']) && !empty($_POST['uemail'])) {
    $qstr .= " AND uemail LIKE '%s'";
    array_push($argv, $_POST['uemail']);
  }
  if(isset($_POST['uip']) && !empty($_POST['uip'])) {
    $qstr .= " AND uip LIKE '%s'";
    array_push($argv, $_POST['uip']);
  }
  if(isset($_POST['rep_g']) && !empty($_POST['rep_g'])) {
    $qstr .= " AND t.rep_g='%s'";
    array_push($argv, $_POST['rep_g']);
  }
  if(isset($_POST['ext1']) && !empty($_POST['ext1'])) {
    $qstr .= " AND t.ext1 LIKE '%s'";
    array_push($argv, $_POST['ext1']);
  }
  if(isset($_POST['description']) && !empty($_POST['description'])) {
    $qstr .= " AND t.description LIKE '%s'";
    array_push($argv, $_POST['description']);
  }
  if(isset($_POST['reply']) && !empty($_POST['reply'])) {
    $qstr .= " AND t.id IN (SELECT tid FROM karnaf_replies WHERE reply LIKE '%s')";
    array_push($argv, $_POST['reply']);
  }
  if(isset($_POST['status']) && is_numeric($_POST['status'])) {
    $qstr .= " AND t.status=%d";
    array_push($argv, $_POST['status']);
  }
  if(isset($_POST['cat3']) && is_numeric($_POST['cat3'])) {
    $qstr .= " AND t.cat3_id=%d";
    array_push($argv, $_POST['cat3']);
  }
  if(isset($_POST['is_real']) && is_numeric($_POST['is_real'])) {
    $qstr .= " AND t.is_real=%d";
    array_push($argv, $_POST['is_real']);
  }
  if(isset($_POST['upriority']) && is_numeric($_POST['upriority'])) {
    $qstr .= " AND t.upriority=%d";
    array_push($argv, $_POST['upriority']);
  }
  if(isset($_POST['priority']) && is_numeric($_POST['priority'])) {
    $qstr .= " AND t.priority=%d";
    array_push($argv, $_POST['priority']);
  }
  if((!IsKarnafAdminSession()) || !isset($_POST['show_private']) || $_POST['show_private']!="on") {
    $qstr .= " AND t.is_private!=1";
  }
  if(!empty($_POST['search_template'])) {
    $time_start = time();
    if($_POST['search_template'] == "monthly") $time_start = $time_start - 2592000;
    else if($_POST['search_template'] == "weekly") $time_start = $time_start - 604800;
    else if($_POST['search_template'] == "24h") $time_start = $time_start - 86400;
    else if($_POST['search_template'] == "48h") $time_start = $time_start - 172800;
    $qstr .= " AND (t.open_time>=%d or t.close_time>%d)";
    array_push($argv, $time_start);
    array_push($argv, $time_start); /* Note: Yes, the line is repeated by design.. the second is for the close time */
  } else {
    if(!empty($_POST['time_start'])) {
      $time_start = datetounixtime($_POST['time_start']);
      $qstr .= " AND t.open_time>=%d";
      array_push($argv, $time_start);
    }
    if(!empty($_POST['time_end'])) {
      $time_end = datetounixtime($_POST['time_end']);
      $qstr .= " AND t.close_time!=0 AND close_time<=%d";
      array_push($argv, $time_end);
    }
  }
  $qstr .= " ORDER BY t.priority DESC,t.open_time LIMIT ".$next.",".($next+$limit+1);
  array_unshift($argv, $qstr);
  $query = squery_args($argv);
  $cnt = 0;
  while($result = sql_fetch_array($query)) {
    if(!IsGroupMember($result['rep_g']) && !IsKarnafAdminSession()) continue; /* Skip tickets for other teams */
    $cnt++;
    if($cnt > $limit) {
      echo "<tr><td colspan=\"9\" align=\"center\">*** There are more results... ***</td></tr>";
      break;
    }
    $query2 = squery("SELECT count(*) AS count FROM karnaf_actions WHERE tid=%d", $result['id']);
    if($result2 = sql_fetch_array($query2)) $action_cnt = (int)$result2['count'];
    else $action_cnt = 0;
    sql_free_result($query2);
    $query2 = squery("SELECT count(*) AS count FROM karnaf_replies WHERE tid=%d", $result['id']);
    if($result2 = sql_fetch_array($query2)) $reply_cnt = (int)$result2['count'];
    else $reply_cnt = 0;
    sql_free_result($query2);
    $status_style = "Karnaf_P_Normal"; // Lightgreen
    $priority = (int)$result['priority'];
    if($priority < 0) $status_style = "Karnaf_P_Low"; // LightBlue
    if($priority > 19) $status_style = "Karnaf_P_High"; // Red
    if($priority > 29) $status_style = "Karnaf_P_Critical";
    if($result['status'] == 0) $status_style = "Karnaf_P_Closed";
?>
<tr class="<?=$status_style?>">
<td><?=$result['id']?></td>
<td><?=$result['unick']?></td>
<td><?=$result['opened_by']?></td>
<td><?=$result['rep_g']?></td>
<td><?=$result['spriority']?></td>
<td><?=showtime($result['open_time'])?></td>
<td><?=$action_cnt+$reply_cnt?></td>
<td>
<?
    if($result['close_time']) echo do_duration($result['close_time'] - $result['open_time']);
    else echo do_duration(time() - $result['open_time']);
?>
</td>
<td><a href="view.php?id=<?=$result['id']?>">View Ticket</a> | <a href="edit.php?id=<?=$result['id']?>" !target="_blank">Edit Ticket</a></td>
</tr>
<?
  }
  if($cnt == 0) echo "<tr><td colspan=\"8\">No tickets were found using the search criteria you provided.</td></tr>";
  sql_free_result($query);
?>
</table>
<?
} else {
?>
You can search using any combination of the fields below:
<br>
<form name="form1" method="post">
<table>
<tr>
<td>Ticket ID:</td>
<td>
<input name="tid" size="30" type="text">
</td>
</tr>
<tr>
<td>Client <?=USER_FIELD?>:</td>
<td>
<input name="unick" size="30" type="text">
</td>
</tr>
<tr>
<td>Identified:</td>
<td>
<select name="is_real">
<option value="">---</option>
<option value="1">Yes</option>
<option value="0">No</option>
</select>
</td>
</tr>
<tr>
<td>Client name:</td>
<td>
<input name="uname" size="30" type="text">
</td>
</tr>
<tr>
<td>Client e-mail:</td>
<td>
<input name="uemail" size="30" type="text">
</td>
</tr>
<tr>
<td>Client IP:</td>
<td>
<input name="uip" size="30" type="text">
</td>
</tr>
<tr>
<td>Ticket Status:</td>
<td>
<select name="status">
<option value="">---</option>
<?
  $query2 = squery("SELECT status_id,status_name FROM karnaf_statuses ORDER BY status_id");
  while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['status_id']?>"><?=$result2['status_name']?></option>
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
<option value="">---</option>
<?
$query2 = squery("SELECT c3.name AS cat3_name,c3.id AS cat3_id,c2.name AS cat2_name,c1.name AS cat1_name FROM (karnaf_cat3 AS c3 INNER JOIN karnaf_cat2
AS c2 ON c3.parent=c2.id INNER JOIN karnaf_cat1 AS c1 ON c2.parent=c1.id) ORDER BY c1.priority,c1.name,c2.priority,c2.name,c3.priority,c3.name");
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['cat3_id']?>"><?=$result2['cat1_name']." - ".$result2['cat2_name']." - ".$result2['cat3_name']?></option>
<?
}
sql_free_result($query2);
?>
</select>
</td>
</tr>
<tr>
<td>User Priority:</td>
<td>
<select name="upriority">
<option value="">---</option>
<?
  $query2 = squery("SELECT priority_id,priority_name FROM karnaf_priorities ORDER BY priority_id");
  while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['priority_id']?>"><?=$result2['priority_name']?></option>
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
<option value="">---</option>
<?
  $query2 = squery("SELECT priority_id,priority_name FROM karnaf_priorities ORDER BY priority_id");
  while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['priority_id']?>"><?=$result2['priority_name']?></option>
<?
  }
  sql_free_result($query2);
?>
</select>
</td>
</tr>
<tr>
<td>Opened by:</td>
<td>
<input name="opened_by" size="30" type="text">
</td>
</tr>
<tr>
<td>Assigned to user:</td>
<td>
<!script src="/teamsearch.js"></script>
<input name="oper" size="30" !onkeyup="showResult(this.value)" !onfocus="showResult(this.value)" type="text" autocomplete="off">
<div id="livesearch"></div>
</td>
</tr>
<tr>
<td>Assigned to group:</td>
<td>
<select name="rep_g">
<option value="">---</option>
<?
  $query2 = squery("SELECT id,name,gdesc FROM groups WHERE iskarnaf=1 ORDER BY name");
  while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['name']?>"><?=$result2['gdesc']?></option>
<?
  }
  sql_free_result($query2);
?>
</select>
</td>
</tr>
<tr>
<td>Extended attribute (AKILL ID, etc):</td>
<td>
<input name="ext1" size="30" type="text">
</td>
</tr>
<tr>
<td>Description:</td>
<td>
<input name="description" size="30" type="text">
</td>
</tr>
<tr>
<td>Reply:</td>
<td>
<input name="reply" size="30" type="text">
</td>
</tr>
<tr>
<td>Search template:</td>
<td>
<select name="search_template">
<option value="">---</option>
<option value="24h">Tickets from the last 24 hours</option>
<option value="48h">Tickets from the last 48 hours</option>
<option value="weekly">Tickets from the last week</option>
<option value="monthly">Tickets from the last month</option>
</select>
</td>
</tr>
<tr>
<td>Open Date (DD/MM/YYYY):</td>
<td><input name="time_start" type="text"></td>
</tr>
<tr>
<td>Close Date (DD/MM/YYYY):</td>
<td><input name="time_end" type="text"></td>
</tr>
<? if(IsKarnafAdminSession()) { ?>
<tr>
<td>Show private tickets:</td>
<td><input name="show_private" type="checkbox"> (limited to <?=ADMINS_GROUP?>)</td>
</tr>
<? } ?>
<tr>
<td>Maximum results:</td>
<td><input name="max_results" type="text" value="100"></td>
</tr>
</table>
<input name="submit" type="submit" value="Search">
</form>
<? } ?>
<?php require_once("karnaf_footer.php"); ?>
