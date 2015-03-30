<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
if(isset($_GET['team'])) $team = $_GET['team'];
else $team = "none";
if(!IsKarnafAdminSession() && !IsGroupMember($team)) AccessDenied();
show_title("Karnaf - Team Stats");
make_menus("Karnaf (HelpDesk)");
# 1 week:
#$starttime = time() - 604800;
# 30 days:
#$starttime = time() - (86400*30);
if(isset($_GET['months'])) $months = (int)$_GET['months'];
else $months = 1;
$starttime = time() - (86400*30*$months);
$replies = 0;
$ureplies = 0;
?>
Statistics between <?=showdate($starttime)?> to <?=showdate(time())?><br><br>
<table border="1">
<tr>
<th><?=USER_FIELD?></th>
<th>Replies to unique tickets</th>
<th>Total Replies</th>
</tr>
<?
$query = squery("SELECT u.user FROM group_members AS gm INNER JOIN users AS u ON u.id=gm.user_id WHERE gm.group_id=(SELECT id FROM groups WHERE name='%s')", $team);
while($result = sql_fetch_array($query)) {
  echo "<tr>\n";
  echo "<td>".$result['user']."</td>\n";
  $query2 = squery("SELECT COUNT(DISTINCT(r.tid)) FROM (karnaf_replies AS r INNER JOIN karnaf_tickets AS t ON t.id=r.tid) WHERE r.r_time>%d AND r.r_by='%s' AND t.rep_g='%s'",
                   $starttime, $result['user'], $team);
  if($result2 = sql_fetch_array($query2)) {
    $ureplies += (int)$result2[0];
    echo "<td align=\"center\">".$result2[0]."</td>\n";
  }
  sql_free_result($query2);
  $query2 = squery("SELECT COUNT(r.tid) FROM (karnaf_replies AS r INNER JOIN karnaf_tickets AS t ON t.id=r.tid) WHERE r.r_time>%d AND r.r_by='%s' AND t.rep_g='%s'",
                   $starttime, $result['user'], $team);
  if($result2 = sql_fetch_array($query2)) {
    $replies += (int)$result2[0];
    echo "<td align=\"center\">".$result2[0]."</td>\n";
  }
  sql_free_result($query2);
  echo "</tr>\n";
}
sql_free_result($query);
?>
<tr>
<td><b>Total</b></td>
<td align="center"><b><?=$ureplies?></b></td>
<td align="center"><b><?=$replies?></b></td>
</tr>
</table>
<?
require_once("karnaf_footer.php");
?>
