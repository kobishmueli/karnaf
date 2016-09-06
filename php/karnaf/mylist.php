<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2016 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession();
show_title("My List");
make_menus("Karnaf (HelpDesk)");
if(isset($_GET['status']) && !empty($_GET['status'])) $status = $_GET['status'];
else $status = 1;
if(!$status) safe_die("Invalid status!");
if(isset($_GET['filter'])) $filter = $_GET['filter'];
else $filter = 0;
if(isset($_POST['ids'])) {
  if(!is_array($_POST['ids'])) $_POST['ids'] = array($_POST['ids']);
  foreach($_POST['ids'] as $id) {
     $query = squery("SELECT id,status,rep_u,rep_g,memo_upd,email_upd,unick,uemail,cc,randcode FROM karnaf_tickets WHERE id=%d", $id);
     if($result = sql_fetch_array($query)) {
       if(!IsGroupMember($result['rep_g']) && !IsKarnafEditorSession()) echo "Ticket #".$id." is assigned to another team.<br>\n";
       if(isset($_POST['reassign']) && (int)$_POST['reassign']==1) {
         if(isset($_POST['reassign_oper']) && !empty($_POST['reassign_oper'])) $assign_user = $_POST['reassign_oper'];
         else $assign_user = $nick;
         if($result['status'] == 0) echo "Ticket #".$id." is closed, you must reopen it in order to re-assign it.<br>\n";
         else if($result['rep_u'] == $assign_user) echo "Ticket #".$id." is already assigned to ".$assign_user.".<br>\n";
         else {
           # Re-assign code (mostly from edit.php):
           $group = $result['rep_g'];
           $a_type = 3;
           $query2 = squery("SELECT private_actions FROM groups WHERE name='%s'", $result['rep_g']);
           if(($result2 = sql_fetch_array($query2)) && $result2['private_actions']) $a_type = 4;
           sql_free_result($query2);
           squery("UPDATE karnaf_tickets SET rep_u='%s',lastupd_time=%d WHERE id=%d", $assign_user, time(), $id);
           squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type) VALUES(%d,'%s','%s','%s',%d,%d)",
                 $id, $assign_user, $nick, $group, (time()+1), $a_type);
           $autostatus = "The ticket has been re-assigned to ".$assign_user.".";
           if(defined("IRC_MODE"))
            $email_update_str = "The ticket has been re-assigned to a staff member (this means your ticket has been forwarded to a staff member to deal with it and you need to wait for his/her reply).";
           else if($a_type != 4)
            $email_update_str = "The ticket has been re-assigned to ".$assign_user;
           if($nick != $assign_user) {
             send_memo($assign_user, "Ticket #".$result['id']." has been assigned to you. For more information visit: ".KARNAF_URL."/edit.php?id=".$result['id']);
             $newsubject = "[".strtoupper($group)."] Ticket #".$result['id'];
             $query2 = squery("SELECT email FROM users WHERE user='%s'", $assign_user);
             if(($result2 = sql_fetch_array($query2))) send_mail($result2['email'], $newsubject, "Ticket #".$result['id']." has been assigned to you. For more information visit: ".KARNAF_URL."/edit.php?id=".$result['id']);
             sql_free_result($query2);
           }
           # End of re-assign code.
           if($result['memo_upd']=="1") send_memo($result['unick'], "Your ticket #".$result['id']." has been updated. For more information visit: ".KARNAF_URL."/view.php?id=".$result['id']."&code=".$result['randcode']);
           if($result['email_upd']=="1") {
             $body = "Your ticket #".$result['id']." has been updated:\r\n".$email_update_str."\r\n";
             $body .= "---\r\nFor more information visit: ".KARNAF_URL."/view.php?id=".$result['id']."&code=".$result['randcode'];
             $body .= "\n*** Please make sure you keep the original subject when replying us by email ***";
             $newsubject = "[".strtoupper($group)."] Ticket #".$result['id'];
             send_mail($result['uemail'], $newsubject, $body);
             send_mail($result['cc'], $newsubject, $body);
           }
         }
       }
       else if(isset($_POST['flagspam']) && (int)$_POST['flagspam']==1) {
         if($result['status'] == 0) echo "Ticket #".$id." is already closed.<br>\n";
         else if($result['status'] == 5) echo "Ticket #".$id." is already flagged as spam.<br>\n";
         else {
           squery("UPDATE karnaf_tickets SET status=5,lastupd_time=%d WHERE id=%d", time(), $id);
           squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'The ticket has been flagged as spam.','%s','%s',%d,1,%d)",
                  $id, $nick, $result['rep_g'], time(), 0);
           echo "Ticket #".$id." has been flagged as spam.<br>\n";
         }
       }
     }
     else echo "Couldn't find ticket #".$id."<br>\n";
     sql_free_result($query);
  }
  echo "<br>\n";
}
?>
<script language="JavaScript">
function flagspam_onclick() {
  document.form1.method = "post";
  document.form1.flagspam.value = "1";
  document.form1.reassign.value = "0";
  document.form1.submit();
}

function reassign_onclick() {
  document.form1.method = "post";
  document.form1.reassign.value = "1";
  document.form1.flagspam.value = "0";
  document.form1.submit();
}

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
<input type="hidden" name="flagspam" id="flagspam" value="0">
<input type="hidden" name="reassign" id="reassign" value="0">
<select name="filter" onChange="form1.submit();">
<option value="">---</option>
<?
$query2 = squery("SELECT id,name FROM karnaf_filters ORDER BY priority");
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['id']?>"<? if($filter == $result2['id']) echo " SELECTED"; ?>><?=$result2['name']?></option>
<?
}
sql_free_result($query2);
?>
</select>
<select name="status" onChange="form1.submit();">
<? if($filter != 0) { $status = 9999; echo "<option value=\"\" SELECTED>---</option>\n"; } ?>
<option value="999"<? if($status == 999) echo " SELECTED"; ?>>Opened - All non-closed tickets</option>
<?
$query2 = squery("SELECT status_id,status_name FROM karnaf_statuses WHERE status_id!=0 AND status_id!=5 ORDER BY priority,status_name");
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
<form name="checks" id="checks" method="post">
<br><br>
<table>
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
$qstr = "SELECT t.id,t.randcode,t.status,t.title,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.ulocation,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,s.status_name,up.priority_name AS upriority,t.priority,sp.priority_name AS spriority, t.last_note,t.newuserreply,t.rep_cc 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON
sp.priority_id=t.priority) WHERE ";
$argv = array();
if($filter != 0) {
  $query2 = squery("SELECT id,name,querystr FROM karnaf_filters WHERE id=%d", $filter);
  if($result2 = sql_fetch_array($query2)) $qstr .= " ".$result2['querystr'];
  sql_free_result($query2);
}
else if($status == 999) {
  $qstr .= " (t.status!=0 and t.status!=5)";
}
else {
  $qstr .= " t.status=%d";
  array_push($argv, $status);
}
$qstr .= " AND (rep_u='%s' or rep_u='' or rep_cc='%s')";
array_push($argv, $nick);
array_push($argv, $nick);
$qstr .= " ORDER BY t.priority DESC,t.open_time";
$cnt = 0;
array_unshift($argv, $qstr);
$query = squery_args($argv);
while($result = sql_fetch_array($query)) {
  if($a_user != $result['rep_u'] && !IsGroupMember($result['rep_g']) && ($a_user != $result['rep_cc']) && !IsGroupMember($result['rep_cc']) && (strtolower($result['rep_g'])!=PSEUDO_GROUP || !IsKarnafEditorSession()) && (!defined("IRC_MODE") || !IsKarnafEditorSession())) continue; /* Skip tickets for other teams */
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
  custom_list_item($result);
  $body = "";
  if(!empty($result['title'])) $body = "Title: ".$result['title']."\n\n";
  $body .= $result['description'];
  $body = preg_replace("/(\*)?\[image\:\sironSource\](\*)?.*Thank\syou\./s", "*** Signature ***", $body);
  $body = str_replace("\"","''",$body);
  $body = str_replace("<","&lt;",$body);
?>
<tr class="<?=$curcol?>" style="cursor:pointer" onmouseover="this.style.backgroundColor='LightGreen'; this.style.color='Black'" onmouseout="this.style.backgroundColor=''; this.style.color=''" onclick=javascript:showspan('tspan<?=$result['id']?>')>
<td class="<?=$status_style?>" align="center"><input name="ids[]" type="checkbox" value="<?=$result['id']?>"></td>
<td><span title="<?=$body?>" style="cursor:pointer">
<? if((int)$result['newuserreply'] == 1) echo "<b>"; ?>
<?=$result['id']?>
<? if((int)$result['newuserreply'] == 1) echo "</b>"; ?>
</span></td>
<td>
<?
$userinfo = ($result['unick']=="Guest"?$result['uemail']:$result['unick']);
if(!defined("IRC_MODE") && !empty($result['ufullname'])) $userinfo = $result['ufullname'];
if(strlen($userinfo) > 30) $userinfo = substr($userinfo,0,30)."...";
echo $userinfo;
?>
</td>
<td><?=str_replace("<","&lt;",$result['title'])?></td>
<?
  if($result['rep_u'] == $nick) echo "<td style=\"border: 1px solid black; background-color: green;\">".$result['rep_u']."</td>\n";
  else if(!empty($result['rep_u'])) echo "<td><span title=\"".$result['rep_g']."\" style=\"cursor:pointer\">".$result['rep_u']."</span></td>\n";
  else if(IsGroupMember($result['rep_g'])) echo "<td class=\"karnaf_my_team\">".$result['rep_g']."</td>\n";
  else echo "<td class=\"karnaf_not_my_team\">".$result['rep_g']."</td>\n";
?>
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
<center>
<input type="button" value="Flag selected tickets as spam" onClick="javascript:flagspam_onclick()">
<input type="button" value="Re-assign selected tickets to:" onClick="javascript:reassign_onclick()">
<select name="reassign_oper">
<option value=""><?=$nick?></option>
<?
$query2 = squery("SELECT DISTINCT(rep_u) FROM karnaf_tickets WHERE status!=0 AND rep_u!='' and rep_u!='%s' ORDER BY rep_u", $nick);
while($result2 = sql_fetch_array($query2)) {
?>
<option value="<?=$result2['rep_u']?>"><?=$result2['rep_u']?></option>
<?
}
sql_free_result($query2);
?>
</select>
</center>
</form>
<br>
Total: <?=$cnt?> ticket(s).
<?
sql_free_result($query);
require_once("karnaf_footer.php");
?>
