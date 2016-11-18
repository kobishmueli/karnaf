<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2016 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("../ktools.php");
check_auth();

$id = trim($_GET['id']);
if(empty($id) || !is_numeric($id)) safe_die("Invalid Ticket ID!");

$query = squery("SELECT t.id,t.randcode,t.status,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,s.status_name,up.priority_name AS upriority,sp.priority_name AS priority,g.private_actions,t.merged_to,t.cc,up.priority_id
AS upriority_id, sp.priority_id,t.ext1,t.ext2,t.ext3,t.title
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON sp.priority_id=t.priority LEFT JOIN groups AS g ON g.name=t.rep_g) WHERE t.id=%d", $id);
if(!($result = sql_fetch_array($query))) safe_die("Invalid Ticket ID!");
if(!IsGroupMember($result['rep_g']) && !IsKarnafEditorSession()) AccessDenied("Ticket is assigned to another team.");
if($result['is_private'] && !IsGroupMember($result['rep_g']) && !IsKarnafAdminSession()) AccessDenied("Ticket is marked as private.");

$foundme = 0;
$cnt = 0;
$res = "";
$query2 = squery("SELECT user,fullname FROM karnaf_watching WHERE tid=%d AND timestamp>=%d", $id, (time()-60));
while(($result2 = sql_fetch_array($query2))) {
  if($result2['user'] == $a_user) {
    $foundme++;
    continue;
  }
  $cnt++;
  if($res != "") $res .= ", ";
  $res .= $result2['fullname'];
}
sql_free_result($query2);
if($foundme) squery("UPDATE karnaf_watching SET timestamp=%d WHERE tid=%d AND user='%s'", time(), $id, $a_user);
else squery("INSERT INTO karnaf_watching(tid,user,fullname,timestamp) VALUES(%d,'%s','%s',%d)", $id, $a_user, $a_fullname, time());

if($cnt == 1) echo "The following operator is also editing this ticket: ".$res.".\r\n";
else if($cnt > 0) echo "The following operators are also editing this ticket: ".$res.".\r\n";

sql_free_result($query);
require_once("karnaf_footer.php");
?>
