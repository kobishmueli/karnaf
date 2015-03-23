<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
if(!IsKarnafAdminSession()) AccessDenied();
$no_cache = 1;
show_title("View Logs");
if(isset($_POST['max_results'])) {
?>
<table border=1>
<tr>
<th>Time</th>
<th>IP</th>
<th>Nick</th>
<th>Action</th>
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
  $querystr = "SELECT id,date,action,user,logtype,ip FROM ws_logs WHERE 1 ";
  if(!empty($_POST['oper'])) {
    $querystr .= " AND user='%s'";
    array_push($argv, $_POST['oper']);
  }
  if(!empty($_POST['action'])) {
    $action = $_POST['action'];
    if($_POST['action_check'] == "like") $querystr .= " AND action LIKE '%s'";
    else if($_POST['action_check'] == "has") {
      $querystr .= " AND action LIKE '%s'";
      $action = "%".$_POST['action']."%";
    }
    else $querystr .= " AND action='%s'";
    array_push($argv, $action);
  }
  if(!empty($_POST['report_template'])) {
    $time_start = time();
    if($_POST['report_template'] == "monthly") $time_start = $time_start - 2592000;
    else if($_POST['report_template'] == "weekly") $time_start = $time_start - 604800;
    else if($_POST['report_template'] == "24h") $time_start = $time_start - 86400;
    else if($_POST['report_template'] == "48h") $time_start = $time_start - 172800;
    $querystr .= " AND date>=%d";
    array_push($argv, $time_start);
  } else {
    if(!empty($_POST['time_start'])) {
      $time_start = datetounixtime($_POST['time_start']);
      $querystr .= " AND date>=%d";
      array_push($argv, $time_start);
    }
    if(!empty($_POST['time_end'])) {
      $time_end = datetounixtime($_POST['time_end']);
      $querystr .= " AND date<=%d";
      array_push($argv, $time_end);
    }
  }
  if(!empty($_POST['logtype'])) {
    $querystr .= " AND logtype='%s'";
    array_push($argv, $_POST['logtype']);
  }
  if(!empty($_POST['ip'])) {
    $querystr .= " AND ip='%s'";
    array_push($argv, $_POST['ip']);
  }
  $querystr .= " ORDER BY date,id LIMIT ".$next.",".($next+$limit+1);
  array_unshift($argv, $querystr);
  $query = squery_args($argv);
  $i = 0;
  while($result = sql_fetch_array($query)) {
    $i++;
    if($i > $limit) {
      echo "<tr><td colspan=\"4\" align=\"center\">*** There are more results... ***</td></tr>";
      break;
    }
    $sdate = showtime($result['date']);
    if(isodd($i)) $class = "";
    else $class = "alt";
?>
<tr>
<td class=<?=$class?>><?=$sdate?></td>
<td class=<?=$class?>><?=$result['ip']?></td>
<td class=<?=$class?>><?=$result['user']?></td>
<td class=<?=$class?>><b><?=strtoupper($result['logtype'])?></b> -> <?=$result['action']?></td>
</tr>
<?
  }
  sql_free_result($query);
  if(!$i) echo "<tr><td colspan=\"4\" align=\"center\">*** No matches found. ***</td></tr>";
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
<td>Action:</td>
<td>
<input name="action" type="text">
<input name="action_check" type="radio" value="=" checked>Exact
<input name="action_check" type="radio" value="like">SQL Wild (% is a wildchar)
<input name="action_check" type="radio" value="has">Contains
</td>
</tr>
<tr>
<td>Log type:</td>
<td>
<select name="logtype">
<option value="">---</option>
<?
  $query = squery("SELECT logtype FROM ws_logs WHERE logtype NOT LIKE '%s' GROUP BY logtype ORDER BY logtype", "%.php?id=%");
  while($result = sql_fetch_array($query)) {
?>
<option value="<?=$result['logtype']?>"><?=$result['logtype']?></option>
<?
  }
  sql_free_result($query);
?>
</select>
</td>
</tr>
<tr>
<td>User:</td>
<td>
<script src="teamsearch.js"></script>
<input name="oper" size="30" onkeyup="showResult(this.value)" onfocus="showResult(this.value)" type="text" autocomplete="off">
<div id="livesearch"></div>
</td>
</tr>
<tr>
<td>IP:</td>
<td><input name="ip" type="text"></td>
</tr>
<tr>
<td>Report template:</td>
<td>
<select name="report_template">
<option value="">---</option>
<option value="24h">Logs from the last 24 hours</option>
<option value="48h">Logs from the last 48 hours</option>
<option value="weekly">Logs from the last week</option>
<option value="monthly">Logs from the last month</option>
</select>
</td>
</tr>
<tr>
<td>Start Date (DD/MM/YYYY):</td>
<td><input name="time_start" type="text"></td>
</tr>
<tr>
<td>End Date (DD/MM/YYYY):</td>
<td><input name="time_end" type="text"></td>
</tr>
<tr>
<td>Maximum results:</td>
<td><input name="max_results" type="text" value="100"></td>
</tr>
</table>
<input name="submit" type="submit" value="Search">
</form>
<? } ?>
<?php require_once("karnaf_footer.php"); ?>
