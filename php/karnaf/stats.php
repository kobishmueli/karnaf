<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession(80);
show_title("Karnaf - Stats");
make_menus("Karnaf (HelpDesk)");
$today = strtotime("today 00:00:00", time());
$today_end = strtotime("today 23:59:59", time());
$times = array(
                array("1", "Last Week", "Monday last week -1 day", "+604800"),
                array("2", "This Week", "Monday this week -1 day", "+604800"),
                array("3", "Last Month", "first day of last month", "last day of last month"),
                array("4", "This Month", "first day of this month", "last day of this month"),
                array("5", "Q1 2015", "first day of January 2015", "last day of March 2015"),
                array("6", "Q2 2015", "first day of April 2015", "last day of June 2015"),
                array("7", "Q3 2015", "first day of July 2015", "last day of September 2015"),
                array("8", "Q4 2015", "first day of October 2015", "last day of December 2015"),
                array("9", "Q1 2016", "first day of January 2016", "last day of March 2016"),
                array("10", "Q2 2016", "first day of April 2016", "last day of June 2016"),
                array("11", "Q3 2016", "first day of July 2016", "last day of September 2016"),
                array("12", "Q4 2016", "first day of October 2016", "last day of December 2016"),
              );
?>
<form name="form1" id="form1" method="get">
Report: 
<select name="report" onChange="form1.submit();">
<?
if(!isset($_GET['report'])) $_GET['report'] = $times[0][0];
foreach($times as $arr) {
  if($_GET['report'] == $arr[0]) {
    $report_name = $arr[1];
    $start_time = strtotime($arr[2], $today);
    if($arr[3][0] == "+") $end_time = $start_time + (int)$arr[3];
    else $end_time = strtotime($arr[3], $today_end);
    echo "<option value=\"".$arr[0]."\" selected>".$arr[1]."</option>";
  }
  else echo "<option value=\"".$arr[0]."\">".$arr[1]."</option>";
}
?>
</select>
</form>
<br>
<?
echo "<font size=\"+1\">Tickets that were opened or closed from ".showdate($start_time)." until ".showdate($end_time)." (".$report_name."):</font><br><br>\n";
$total = 0;
echo "<u>Teams:</u><br>\n";
$query = squery("SELECT t.id,t.rep_g,count(t.rep_g) AS c FROM karnaf_tickets AS t WHERE t.status!=5 AND (t.open_time>=%d OR t.close_time>=%d) AND (t.open_time<=%d OR t.close_time<=%d) GROUP BY rep_g ORDER BY id", $start_time, $start_time, $end_time, $end_time);
while($result = sql_fetch_array($query)) {
  $total += (int)$result['c'];
  echo $result['rep_g'].": ".$result['c']."<br>\n";
}
sql_free_result($query);
echo "<b>Total: ".$total."</b><br>\n";
echo "<br>\n";
echo "<u>Opers:</u><br>\n";
$query = squery("SELECT t.id,t.rep_u,count(t.rep_u) AS c FROM karnaf_tickets AS t WHERE t.status!=5 AND (t.open_time>=%d OR t.close_time>=%d) AND (t.open_time<=%d OR t.close_time<=%d) GROUP BY rep_u ORDER BY c DESC", $start_time, $start_time, $end_time, $end_time);
while($result = sql_fetch_array($query)) {
  if(empty($result['rep_u'])) $result['rep_u'] = "None";
  echo $result['rep_u'].": ".$result['c']."<br>\n";
}
sql_free_result($query);
echo "<br>\n";
echo "<u>Categories:</u><br>\n";
$query = squery("SELECT t.id,c3.name AS cat3,c2.name AS cat2,c1.name AS cat1,count(t.cat3_id) AS c FROM (karnaf_tickets AS t LEFT JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id
LEFT JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent LEFT JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent) WHERE (t.open_time>=%d OR t.close_time>=%d) AND (t.open_time<=%d OR t.close_time<=%d) GROUP BY
c1.priority,c1.name,c2.priority,c2.name,c3.priority,c3.name ORDER BY c DESC", $start_time, $start_time, $end_time, $end_time);
while($result = sql_fetch_array($query)) {
  echo $result['cat1']." - ".$result['cat2']." - ".$result['cat3'].": ".$result['c']."<br>\n";
}
sql_free_result($query);
require_once("karnaf_footer.php");
?>
