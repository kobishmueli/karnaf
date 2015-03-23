<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession(80);
show_title("Karnaf - Stats");
make_menus("Karnaf (HelpDesk)");
# 1 week:
#$starttime = time() - 604800;
# 30 days:
$starttime = time() - (86400*30);
echo "<font size=\"+1\">Tickets that were opened or closed since ".showdate($starttime).":</font><br><br>\n";
echo "<u>Teams:</u><br>\n";
$query = squery("SELECT t.id,t.rep_g,count(t.rep_g) AS c FROM karnaf_tickets AS t WHERE t.status!=5 AND (t.open_time>=%d OR t.close_time>=%d) GROUP BY rep_g ORDER BY id", $starttime, $starttime);
while($result = sql_fetch_array($query)) {
  echo $result['rep_g'].": ".$result['c']."<br>\n";
}
sql_free_result($query);
echo "<br>\n";
echo "<u>Categories:</u><br>\n";
$query = squery("SELECT t.id,c3.name AS cat3,c2.name AS cat2,c1.name AS cat1,count(t.cat3_id) AS c FROM (karnaf_tickets AS t LEFT JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id
LEFT JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent LEFT JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent) WHERE t.open_time>=%d OR t.close_time>=%d GROUP BY
c1.priority,c1.name,c2.priority,c2.name,c3.priority,c3.name", $starttime, $starttime);
while($result = sql_fetch_array($query)) {
  echo $result['cat1']." - ".$result['cat2']." - ".$result['cat3'].": ".$result['c']."<br>\n";
}
sql_free_result($query);
require_once("karnaf_footer.php");
?>
