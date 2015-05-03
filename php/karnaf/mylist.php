<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession();
show_title("My List");
make_menus("Karnaf (HelpDesk)");
if(isset($_GET['status'])) $status = $_GET['status'];
else $status = 1;
if(!$status) safe_die("Invalid status!");
if(isset($_POST['spams']) && is_array($_POST['spams'])) {
  foreach($_POST['spams'] as $spam) {
     $query = squery("SELECT status,rep_g FROM karnaf_tickets WHERE id=%d", $spam);
     if($result = sql_fetch_array($query)) {
       if(!IsGroupMember($result['rep_g']) && !IsKarnafAdminSession()) echo "Ticket #".$spam." is assigned to another team.<br>\n";
       else if($result['status'] == 0) echo "Ticket #".$spam." is already closed.<br>\n";
       else if($result['status'] == 5) echo "Ticket #".$spam." is already flagged as spam.<br>\n";
       else {
         squery("UPDATE karnaf_tickets SET status=5,lastupd_time=%d WHERE id=%d", time(), $spam);
         squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'The ticket has been flagged as spam.','%s','%s',%d,1,%d)",
                $spam, $nick, $result['rep_g'], time(), 0);
         echo "Ticket #".$spam." has been flagged as spam.<br>\n";
       }
     }
     else echo "Couldn't find ticket #".$spam."<br>\n";
     sql_free_result($query);
  }
  echo "<br>\n";
}
?>
<script language="JavaScript">
function showspan(spanname) {
  if (document.getElementById(spanname).style.display == "none") {
    document.getElementById(spanname).style.display="table-cell";
  }
  else {
    document.getElementById(spanname).style.display="none";
  }
}

function CheckAll()
{
  var nummod=0;
  for (var i=0;i<document.checks.elements.length;i++)
  {
    var e = document.checks.elements[i];
    if ((e.name != 'allbox') && (e.type=='checkbox')) {
      if(e.checked != document.checks.allbox.checked) {
        e.checked = document.checks.allbox.checked;
        nummod++;
        if(nummod == 999)
           break;
      }
    }
  }
  if(nummod >= 999)
     alert("Altered only the first " + nummod + " records.\nYou can only flag for up to 999 tickets at a time.");
}

var time = new Date().getTime();
$(document.body).bind("mousemove keypress", function(e) {
    time = new Date().getTime();
});

function refresh() {
    if(new Date().getTime() - time >= 60000)
        window.location.reload(true);
    else
        setTimeout(refresh, 10000);
}

setTimeout(refresh, 10000);
</script>
<form name="form1" id="form1" method="get">
<select name="status" onChange="form1.submit();">
<?
$query2 = squery("SELECT status_id,status_name FROM karnaf_statuses WHERE status_id!=0 AND status_id!=5 ORDER BY status_id");
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['status_id']?>"<? if($status == $result2['status_id']) echo " SELECTED"; ?>><?=$result2['status_name']?></option>
<?
}
sql_free_result($query2);
?>
</select>
<?
if(isset($_GET['showall'])) $showall = $_GET['showall'];
else $showall = "";
?>
<select name="showall" onChange="form1.submit();">
<option value="">---</option>
<option value="none"<? if(strtolower($showall) == "none") echo " SELECTED"; ?>>*** Not Assigned ***</option>
<option value="onlymy"<? if(strtolower($showall) == "onlymy") echo " SELECTED"; ?>>*** Only My Tickets ***</option>
</select>
</form>
<form name="checks" id="checks" method="post">
<br><br>
<table border="1" width="90%" bgcolor="White" style="border-collapse: collapse" bordercolor="#111111" cellpadding="1" cellspacing="1">
<tr class="Karnaf_L_Head">
<td><input name="allbox" type="checkbox" onClick="javascript:CheckAll()"></td>
<td>ID</td>
<td>User</td>
<td>Title</td>
<td>Assigned to</td>
<td>Priority</td>
<td>Open Date</td>
<td>Actions</td>
<td>Duration</td>
<td>Note</td>
</tr>
<?
$qstr = "SELECT t.id,t.randcode,t.status,t.title,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,s.status_name,up.priority_name AS upriority,t.priority,sp.priority_name AS spriority, t.last_note 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON
sp.priority_id=t.priority) WHERE t.status=%d";
$argv = array();
array_push($argv, $status);
$qstr .= " AND (rep_u='%s' or rep_u='')";
array_push($argv, $nick);
$qstr .= " ORDER BY t.priority DESC,t.open_time";
$cnt = 0;
array_unshift($argv, $qstr);
$query = squery_args($argv);
while($result = sql_fetch_array($query)) {
  if(!IsGroupMember($result['rep_g']) && (!defined("IRC_MODE") || !IsKarnafAdminSession())) continue; /* Skip tickets for other teams */
  if((strtolower($showall) == "none") && !empty($result['rep_u'])) continue;
  if((strtolower($showall) == "onlymy") && empty($result['rep_u'])) continue;
  $cnt++;
  $query2 = squery("SELECT count(*) AS count FROM karnaf_actions WHERE tid=%d", $result['id']);
  if($result2 = sql_fetch_array($query2)) $action_cnt = (int)$result2['count'];
  else $action_cnt = 0;
  sql_free_result($query2);
  $query2 = squery("SELECT count(*) AS count FROM karnaf_replies WHERE tid=%d", $result['id']);
  if($result2 = sql_fetch_array($query2)) $reply_cnt = (int)$result2['count'];
  else $reply_cnt = 0;
  sql_free_result($query2);
  $status_style = "Karnaf_P_Normal"; // Lightgreen
  if(isodd($cnt)) $curcol = "col2";
  else $curcol = "col1";
  $priority = (int)$result['priority'];
  if($priority < 0) $status_style = "Karnaf_P_Low"; // LightBlue
  if($priority > 19) $status_style = "Karnaf_P_High"; // Red
  if($priority > 29) $status_style = "Karnaf_P_Critical";
  $body = "";
  if(!empty($result['title'])) $body = "Title: ".$result['title']."\n\n";
  $body .= $result['description'];
  $body = preg_replace("/(\*)?\[image\:\sironSource\](\*)?.*Thank\syou\./s", "*** Signature ***", $body);
  $body = str_replace("\"","''",$body);
  $body = str_replace("<","&lt;",$body);
?>
<tr class="<?=$curcol?>" style="cursor:pointer" onmouseover="this.style.backgroundColor='LightGreen'; this.style.color='Black'" onmouseout="this.style.backgroundColor=''; this.style.color=''" onclick=javascript:showspan('tspan<?=$result['id']?>')>
<td class="<?=$status_style?>" align="center"><input name="spams[]" type="checkbox" value="<?=$result['id']?>"></td>
<td><span title="<?=$body?>" style="cursor:pointer"><?=$result['id']?></span></td>
<td>
<?
$userinfo = ($result['unick']=="Guest"?$result['uemail']:$result['unick']);
if(!defined("IRC_MODE") && !empty($result['ufullname'])) $userinfo = $result['ufullname'];
if(strlen($userinfo) > 30) $userinfo = substr($userinfo,0,30)."...";
echo $userinfo;
?>
</td>
<td><?=str_replace("<","&lt;",$result['title'])?></td>
<td>
<?
  if(!empty($result['rep_u'])) echo $result['rep_u'];
  else echo $result['rep_g'];
?>
</td>
<td><?=$result['spriority']?></td>
<td><?=showtime($result['open_time'])?></td>
<td><?=$action_cnt+$reply_cnt?></td>
<td><?=do_duration(time() - $result['open_time'])?></td>
<td><?=empty($result['last_note'])?"<center>N/A</center>":$result['last_note']?></td>
</tr>
<tr>
<td id="tspan<?=$result['id']?>" style="display:none" colspan="10" align="center">
<textarea style="width:98%" rows="10" readonly>
<?=$body?>
</textarea>
<br>
<center>
<a href="edit.php?id=<?=$result['id']?>">Edit</a> | 
<a href="view.php?id=<?=$result['id']?>">View</a> | 
<a href="edit.php?id=<?=$result['id']?>&reassign">Re-assign</a>
</center>
</td>
</tr>
<?
}
if(!$cnt) echo "<tr><td colspan=\"10\" align=\"center\">*** None ***</td></tr>";
?>
</table>
<br>
<center><input type="submit" value="Flag selected tickets as spam"></center>
</form>
<br>
Total: <?=$cnt?> ticket(s).
<?
sql_free_result($query);
require_once("karnaf_footer.php");
?>
