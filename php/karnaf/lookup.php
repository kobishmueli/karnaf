<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession();
show_title("Ticket Lookup");
make_menus("Karnaf (HelpDesk)");
if(isset($_POST['tid'])) {
?>
<table border="1" width="90%" bgcolor="White" style="border-collapse: collapse" bordercolor="#111111" cellpadding="0" cellspacing="0">
<?
  $argv = array();
  $qstr = "SELECT t.id,t.randcode,t.status,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,s.status_name,up.priority_name AS upriority,t.priority,sp.priority_name AS spriority,t.close_time 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON
sp.priority_id=t.priority) WHERE t.id=%d";
  array_push($argv, $_POST['tid']);
  $qstr .= " ORDER BY t.priority DESC,t.open_time";
  array_unshift($argv, $qstr);
  $query = squery_args($argv);
  $cnt = 0;
  while($result = sql_fetch_array($query)) {
    $cnt++;
    if($result['randcode'] != $_POST['code']) {
      echo "<tr><td colspan=\"2\" align=\"center\">*** Incorrect code for ticket #".$result['id'].", please contact ".$result['rep_g']." ***</td></tr>";
      continue;
    }
    add_log("karnaf_lookup", $result['id']);
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
<tr class="Karnaf_Head2"><td colspan="2">Ticket #<?=$result['id']?></td></tr>
<tr><td>ID:</th><td><?=$result['id']?></td></tr>
<tr><td>User:</th><td><?=($result['unick']=="Guest"?$result['uemail']:$result['unick'])?></td><tr>
<tr><td>Opened by:</td><td><?=$result['opened_by']?></td></tr>
<tr class="<?=$status_style?>"><td>Status:</td><td><?=$result['status_name']?></td></tr>
<tr><td>Assigned to:</td><td>
<?
  echo $result['rep_g'];
  if(!empty($result['rep_u'])) echo " (".$result['rep_u'].")";
?>
</td></tr>
<tr><td>User Priority:</td><td><?=$result['upriority']?></td></tr>
<tr><td>System Priority:</td><td><?=$result['spriority']?></td></tr>
<tr><td>Open Date:</td><td><?=showtime($result['open_time'])?></td></tr>
<tr><td>Actions / Replies:</td><td><?=$action_cnt+$reply_cnt?></td></tr>
<tr><td>Duration:</td><td>
<?
    if($result['close_time']) echo do_duration($result['close_time'] - $result['open_time']);
    else echo do_duration(time() - $result['open_time']);
?>
</td></tr>
<tr><td colspan="2">For more information: <a href="view.php?id=<?=$result['id']?>&code=<?=$result['randcode']?>">click here</a></td></tr>
<?
  }
  if($cnt == 0) echo "<tr><td colspan=\"2\" align=\"center\">The ticket does not exist.</td></tr>";
  sql_free_result($query);
?>
</table>
<br><br>
<center><font size="+2">
<a href="lookup.php">Search another ticket</a>
</font></center>
<?
} else {
?>
Please write both the ticket ID and the ticket code:
<br>
<form name="form1" method="post">
<table>
<tr>
<td>Ticket ID:</td>
<td>
<input name="tid" size="30" type="text">
</td>
</tr>
<td>Verification Number:</td>
<td>
<input name="code" size="30" type="text">
</td>
</tr>
</table>
<input name="submit" type="submit" value="Lookup!">
</form>
<? } ?>
<?php require_once("karnaf_footer.php"); ?>
