<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2019 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

function do_upload($tid) {
  global $nick;

  if($_FILES['attachment-file']['size'] < 1) return "File size is too small!";

  $file_name = $_FILES['attachment-file']['name'];
  $file_ext = strtolower(substr($file_name,-4));
  if($file_ext!=".jpg" && $file_ext!=".png" && $file_ext!=".pdf" && $file_ext!=".log" && $file_ext!=".txt") return "You can only upload jpg/png/pdf/log/txt files!";
  $file_type = $_FILES['attachment-file']['type'];
  $file_size = $_FILES['attachment-file']['size'];
  $file_desc = "Attachment by ".$nick;
  if(!is_numeric($file_size)) safe_die("Error! Invalid number in file size!");
  $query = squery("INSERT INTO karnaf_files(tid,file_name,file_type,file_desc,file_size,lastupd_time) VALUES(%d,'%s','%s','%s',%d,%d)",
                  $tid, $file_name, $file_type, $file_desc, $file_size, time());
  if(!$query) return "SQL Error! Query failed on do_upload() function: ".mysql_error();
  $id = sql_insert_id();
  $fn = KARNAF_UPLOAD_PATH."/".$tid;
  if(!file_exists($fn)) {
    if(!mkdir($fn)) return "Can't create attachment directory!";
  }
  $fn .= "/".$id.$file_ext;
  if(!copy($_FILES['attachment-file']['tmp_name'],$fn)) return "Couldn't create attachment file!";
  return "";
}

if(isset($_GET['ajax']) && $_GET['ajax']=="1") {
  require_once("../ktools.php");
  check_auth();
}
else require_once("karnaf_header.php");
$id = $_GET['id'];
if(empty($id) || !is_numeric($id)) safe_die("Invalid Ticket ID!");
if(isset($_GET['code']) && !empty($_GET['code'])) $randcode = $_GET['code'];
else $randcode = 0;
if(!isset($_GET['ajax'])) show_title("Ticket #".$id);
if(IsKarnafOperSession()) $isoper = 1;
else $isoper = 0;
$isadmin = 0;
$query = squery("SELECT t.id,t.randcode,t.status,t.title,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.ulocation,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS 
cat3_name,s.status_name,up.priority_name AS upriority,sp.priority_name AS priority,c3.extra,t.ext1,t.ext2,t.ext3,t.merged_to,t.cc,
g.private_actions,t.lastupd_time,t.newuserreply 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent 
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON 
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON sp.priority_id=t.priority LEFT JOIN groups AS g ON g.name=t.rep_g) WHERE t.id=%d", $id);
if($result = sql_fetch_array($query)) {
  if(!$isoper && ($randcode != $result['randcode']) && (($nick != $result['unick']) || $nick=="Guest" || $a_regtime>(int)$result['open_time'])) AccessDenied("You must provide the ticket verification code to view this page.");
  if(isset($_POST['reply_text']) && !empty($_POST['reply_text']) && $result['status']!=0) {
    squery("INSERT INTO karnaf_replies(tid,reply,r_by,r_time,r_from,ip) VALUES(%d,'%s','%s',%d,'%s','%s')", $id, $_POST['reply_text'],
           $nick, time(), $nick, get_session_ip());
    if((int)$result['status'] == 2) {
      squery("UPDATE karnaf_tickets SET status=1,lastupd_time=%d,newuserreply=1 WHERE id=%d AND status=2", time(), $id);
      send_memo($result['rep_u'], "User has replied to ticket #".$result['id'].". For more information visit: ".KARNAF_URL."/edit.php?id=".$result['id']);
    }
    else squery("UPDATE karnaf_tickets SET lastupd_time=%d,newuserreply=1 WHERE id=%d", time(), $id);
    echo "<div class=\"status\">Your reply has been saved.</div><br>";
    $result['status'] = 1;
  }
  if(isset($_FILES['attachment-file']['name']) && !empty($_FILES['attachment-file']['name'])) {
    $error = do_upload($id);
    if($error == "") {
      if((int)$result['status'] == 2) {
        squery("UPDATE karnaf_tickets SET status=1,lastupd_time=%d WHERE id=%d AND status=2", time(), $id);
        send_memo($result['rep_u'], "User has added an attachment to ticket #".$result['id'].". For more information visit: ".KARNAF_URL."/edit.php?id=".$result['id']);
      }
      echo "<div class=\"status\">Your attachment has been saved.</div><br>";
    }
    else echo "<div class=\"status_err\">Error: ".$error."</div><br>";
  }
  if($isoper) {
    if(IsGroupMember($result['rep_g']) || IsKarnafAdminSession()) $isadmin = 1;
    if($result['is_private'] && !$isadmin) AccessDenied("Ticket is marked as private.");
    add_log("karnaf_view", $result['id']);
    if(isset($_GET['usermode'])) $isoper = $isadmin = 0;
    else make_menus("Karnaf (HelpDesk)");
    if(isset($_GET['ack']) && (int)$result['newuserreply']==1) {
      $query2 = squery("SELECT private_actions,flags FROM groups WHERE name='%s'", $result['rep_g']);
      if(($result2 = sql_fetch_array($query2))) {
        if($result2['private_actions']) $is_private = 2;
        else $is_private = 0;
        if((int)$result2['flags'] & GFLAG_CANACK_USERREPLY) {
          squery("UPDATE karnaf_tickets SET lastupd_time=%d,newuserreply=0 WHERE id=%d AND newuserreply=1", time(), $id);
          squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'User reply acknowledged','%s','%s',%d,1,%d)", $id,
                 $nick, $result['rep_g'], time(), $is_private);
          echo "<div class=\"status\">User reply has been acknowledged.</div><br>\r\n";
        }
        else echo "<div class=\"status_err\">Error: You don't have access to acknowledge this ticket.</div><br>\r\n";
      }
    }
    else if(isset($_GET['ack']) && (int)$result['newuserreply']==0) echo "<div class=\"status_err\">Error: There is nothing to acknowledge.</div><br>\r\n";
  }
  if($isoper && defined("IRC_MODE")) echo "<center>*** You are an IRC Operator and see things users don't ***</center><br>\r\n";
?>
<table width="100%" class="view_ticket_table">
<tr>
<td valign="top" width="50%">
<table width="100%">
<tr class="Karnaf_Head2"><td colspan="2">User Information</td></tr>
<tr>
<td><?=USER_FIELD?>:</td>
<td><?=$result['unick']?></td>
</tr>
<tr>
<td>Identified:</td>
<td><?=showyesno($result['is_real'])?></td>
</tr>
<tr>
<td>Name:</td>
<td><?=$result['ufullname']?></td>
</tr>
<tr>
<td>E-Mail:</td>
<td><?=$result['uemail']?></td>
</tr>
<? if($result['cc']) { ?>
<tr>
<td>CC:</td>
<td><? foreach(explode(",", $result['cc']) as $curcc) echo str_replace("<","&lt;",$curcc)."<br>"; ?></td>
</tr>
<? } ?>
<? if(!defined("IRC_MODE")) { ?>
<tr>
<td>Location:</td>
<td><?=$result['ulocation']?></td>
</tr>
<tr>
<td>Phone:</td>
<td><?=$result['uphone']?></td>
</tr>
<? } ?>
<tr>
<td>IP:</td>
<td><?=$result['uip']?></td>
</tr>
<tr>
<td>Update by Mail:</td>
<td><?=showyesno($result['email_upd'])?></td>
</tr>
<tr>
<td>Update by Memo:</td>
<td><?=showyesno($result['memo_upd'])?></td>
</tr>
</table>
</td>
<td valign="top">
<table width="100%">
<tr class="Karnaf_Head2"><td colspan="2">Ticket Information</td></tr>
<tr>
<td>Status:</td>
<td><?=$result['status_name']?></td>
</tr>
<tr>
<td>Ticket Type:</td>
<td><?=$result['cat1_name']?></td>
</tr>
<tr>
<td>Ticket Category:</td>
<td><?=$result['cat2_name']?></td>
</tr>
<tr>
<td>Ticket Subject</td>
<td><?=$result['cat3_name']?></td>
</tr>
<?
  if($result['extra']) {
    $i = 0;
    foreach(explode(',',$result['extra']) as $row) {
      $i++;
?>
<tr>
<td><?=$row?>:</td>
<td>
<?
      custom_view_row_info($row, $result['ext'.$i], $isoper);
?>
</td>
</tr>
<?
    }
  }
?>
<tr>
<td>User Priority:</td>
<td><?=$result['upriority']?></td>
</tr>
<tr>
<td>System Priority:</td>
<td><?=$result['priority']?></td>
</tr>
<tr>
<td>Private Ticket:</td>
<td><?=showyesno($result['is_private'])?></td>
</tr>
<tr>
<td>Open Time:</td>
<td><?=showtime($result['open_time'])?></td>
</tr>
<tr>
<td>Opened By:</td>
<td><?=$result['opened_by']?></td>
</tr>
<tr>
<td>Assigned To:</td>
<td>
<?
if(!empty($result['rep_u']) && $result['private_actions'] && !$isadmin) echo "Oper (".$result['rep_g'].")";
else if(!empty($result['rep_u'])) echo $result['rep_u']." (".$result['rep_g'].")";
else echo $result['rep_g'];
?>
</td>
</tr>
<? if($result['merged_to']) { ?>
<tr>
<td>Merged To:</td>
<td>
<?
    if($isoper) echo "<a href=\"?id=".$result['merged_to']."\">".$result['merged_to']."</a>\n";
    else {
      $query2 = squery("SELECT randcode FROM karnaf_tickets WHERE id=%d", $result['merged_to']);
      if(($result2 = sql_fetch_array($query2))) {
        echo "<a href=\"?id=".$result['merged_to']."&code=".$result2['randcode']."\">".$result['merged_to']."</a>\n";
      }
      sql_free_result($query2);
    }
?>
</td>
</tr>
<? } ?>
<tr>
<td>Last Update Time:</td>
<td><?=$result['lastupd_time']==0?"Never":showtime($result['lastupd_time'])?></td>
</table>
</td>
</tr>
<? custom_view_more($result, $isoper); ?>
<? if($isoper) { ?>
<?
  $querystr = "SELECT id,status,unick,uemail,rep_g,priority FROM karnaf_tickets WHERE id!=%d AND (";
  $argv = array();
  $rows = 0;
  array_push($argv, $id);
  if(strtolower($result['unick']) != "guest") {
    if($rows) $querystr .= " OR";
    $rows++;
    $querystr .= " unick='%s'";
    array_push($argv, $result['unick']);
  }
  if(!empty($result['uemail'])) {
    if($rows) $querystr .= " OR";
    $rows++;
    $querystr .= " uemail='%s'";
    array_push($argv, $result['uemail']);
  }
  if(!empty($result['ext1'])) {
    if($rows) $querystr .= " OR";
    $rows++;
    $querystr .= " ext1='%s'";
    array_push($argv, $result['ext1']);
  }
  if($result['merged_to']) {
    if($rows) $querystr .= " OR";
    $rows++;
    $querystr .= " id=%d OR merged_to=%d";
    array_push($argv, $result['merged_to']);
    array_push($argv, $result['merged_to']);
  }
  if($rows) $querystr .= " OR";
  $querystr .= " merged_to=%d";
  array_push($argv, $id);
  $querystr .= ") ORDER BY status DESC,priority DESC,id";
  if(!isset($_GET['full'])) $querystr .= " LIMIT 10";
  array_unshift($argv, $querystr);
  $matches = 0;
  $query2 = squery_args($argv);
  while($result2 = sql_fetch_array($query2)) {
    if(!$matches) {
?>
<tr class="Karnaf_Head2"><td colspan="2">Possible Related Tickets</td></tr>
<tr><td colspan="2">
<table border="1" bordercolor="Black" width="100%" cellpadding="0" cellspacing="0">
<tr class="Karnaf_P_Head">
<td>ID</td>
<td><?=USER_FIELD?></td>
<td>E-Mail</td>
<td>Assigned to</td>
</tr>
<?
    }
    $matches++;
    $status_style = "Karnaf_P_Normal"; // Lightgreen
    $priority = (int)$result2['priority'];
    if($priority < 0) $status_style = "Karnaf_P_Low"; // LightBlue
    if($priority > 19) $status_style = "Karnaf_P_High"; // Red
    if($priority > 29) $status_style = "Karnaf_P_Critical";
    if($result2['status'] == 0) $status_style = "Karnaf_P_Closed";
?>
<tr class="<?=$status_style?>" style="cursor:pointer" onmouseover="this.style.backgroundColor='LightGreen'; this.style.color='Black'" onmouseout="this.style.backgroundColor=''; this.style.color=''" onclick=javascript:window.location.href="edit.php?id=<?=$result2['id']?>">
<td><?=$result2['id']?></td>
<td><?=$result2['unick']?></td>
<td><?=$result2['uemail']?></td>
<td><?=$result2['rep_g']?></td>
</tr>
<?
  }
  sql_free_result($query2);
  if($matches) {
?>
</table>
</td></tr>
<? } ?>
<? } ?>
<tr class="Karnaf_Head2"><td colspan="2">Ticket Description</td></tr>
<tr><td class="ticket_body" colspan="2">
<?
  if(!empty($result['title'])) {
    echo "Title: ";
    show_board_body($result['title']);
    echo "<hr>\n";
  }
  $description = $result['description'];
  $description = preg_replace("/(\*)?\[image\:\sironSource\](\*)?.*Thank\syou\./s", "*** Signature ***", $description);
?>
<?=show_board_body($description)?>
</td></tr>
<?
  $query2 = squery("SELECT id,file_name,file_desc,file_size FROM karnaf_files WHERE tid=%d ORDER BY id", $id);
  $cnt = 0;
  while($result2 = sql_fetch_array($query2)) {
    $cnt++;
    if($cnt == 1) {
?>
<tr class="Karnaf_Head2"><td colspan="2">Attachments</td></tr>
<tr><td colspan="2">
<table border="1" width="100%" cellpadding="0" cellspacing="0">
<tr class="Karnaf_P_Head">
<td>File</td>
<td>Size</td>
<td width="80%">Description</td>
</tr>
<?
    }
?>
<tr>
<td><a href="download.php?id=<?=$id?>&download=<?=$result2['id']?>" target="_blank"><?=$result2['file_name']?></a></td>
<td><?=coolsize($result2['file_size'])?></td>
<td><?=$result2['file_desc']?></td>
</tr>
<?
  }
  #if(!$cnt) echo "<tr><td colspan=\"3\" align=\"center\">*** None ***</td></tr>";
  sql_free_result($query2);
  if($cnt) {
?>
</table>
</td></tr>
<? } ?>
<tr class="Karnaf_Head2"><td colspan="2">Actions</td></tr>
<tr><td colspan="2">
<table border="1" width="100%" cellpadding="0" cellspacing="0">
<tr class="Karnaf_P_Head">
<td>Assignee</td>
<td>Action</td>
<td>Group</td>
<td>Date</td>
</tr>
<?
  $query2 = squery("SELECT action,a_by_u,a_by_g,a_time,a_type,is_private FROM karnaf_actions WHERE tid=%d ORDER BY a_time", $id);
  $cnt = 0;
  while($result2 = sql_fetch_array($query2)) {
    $cnt++;
    $a_type = (int)$result2['a_type'];
    $is_private = (int)$result2['is_private'];
    if($a_type == 2) $action = "The ticket has been re-assigned to ".$result2['action'].".";
    else if($a_type == 3) $action = "The ticket has been re-assigned to ".$result2['action'].".";
    else if($a_type == 4 && $isoper) {
      $action = "The ticket has been privately re-assigned to ".$result2['action'].".";
      $is_private = 1;
    }
    else if($a_type == 4) $action = "The ticket has been re-assigned to Oper.";
    else if($a_type == 5) {
      if($isoper) $action = "Merged from <a href=\"?id=".$result2['action']."\">Ticket #".$result2['action']."</a>.";
      else {
        $query3 = squery("SELECT randcode FROM karnaf_tickets WHERE id=%d", $result2['action']);
        if(($result3 = sql_fetch_array($query3))) {
          $action = "Merged from <a href=\"?id=".$result2['action']."&code=".$result3['randcode']."\">Ticket #".$result2['action']."</a>.";
        }
        sql_free_result($query3);
      }
    }
    else if($a_type == 6) {
      if($isoper) $action = "Merged to <a href=\"?id=".$result2['action']."\">Ticket #".$result2['action']."</a>.";
      else {
        $query3 = squery("SELECT randcode FROM karnaf_tickets WHERE id=%d", $result2['action']);
        if(($result3 = sql_fetch_array($query3))) {
          $action = "Merged to <a href=\"?id=".$result2['action']."&code=".$result3['randcode']."\">Ticket #".$result2['action']."</a>.";
        }
        sql_free_result($query3);
      }
    }
    else if($a_type == 7) {
      /* Special system action... */
      $result2['a_by_u'] = "System";
      $result2['a_by_g'] = "---";
      $a_type = 0;
      $action = str_replace("<br>","\r\n",$result2['action']);
    }
    else $action = $result2['action'];
    if($is_private==1 && !$isoper) continue;
?>
<tr<? if ($is_private && $isoper) echo " bgcolor=lightblue"; ?>>
<td align="center"><?=(($a_type==4 || $is_private==2) && !$isoper)?"---":$result2['a_by_u']?></td>
<? if($a_type == 0) { ?>
<td>
<?=show_board_body($action)?>
<?
    if($is_private == 1) echo "<br>(private action)\r\n";
    if($is_private == 2) echo "<br>(hidden oper nick)\r\n";
?>
</td>
<td align="center"><?=$result2['a_by_g']?></td>
<? } else { ?>
<td align="center">*** <?=$action?> ***</td>
<td align="center">---</td>
<? } ?>
<td align="center"><?=showtime($result2['a_time'])?></td>
</tr>
<?
  }
  if(!$cnt) echo "<tr><td colspan=\"4\" align=\"center\">*** None ***</td></tr>";
  sql_free_result($query2);
?>
</table>
</td></tr>
<tr>
<td colspan="2">
<table border="1" width="100%" cellpadding="0" cellspacing="0">
<tr class="Karnaf_Head2"><td colspan="2">Replies</td></tr>
<?
  $query2 = squery("SELECT title,reply,r_time,r_from FROM karnaf_replies WHERE tid=%d ORDER BY r_time", $id);
  $cnt = 0;
  while($result2 = sql_fetch_array($query2)) {
    $cnt++;
?>
<tr class="Karnaf_P_Head"><td colspan="2">Reply #<?=$cnt?> from <?=$result2['r_from']?> at <?=showtime($result2['r_time'])?></td></tr>
<tr>
<td class="ticket_replies" colspan="2">
<?
    if(!empty($result2['title'])) {
      echo "Title: ";
      show_board_body($result2['title']);
      echo "<hr>\n";
    }
?>
<?=show_board_body(preg_replace("/(\*)?\[image\:\sironSource\](\*)?.*Thank\syou\./s", "*** Signature ***", $result2['reply']))?>
</td>
</tr>
<?
  }
  if(!$cnt) echo "<tr><td colspan=\"2\" align=\"center\">*** None ***</td></tr>\r\n";
  sql_free_result($query2);
?>
</table>
</td>
</tr>
<? if(!isset($_GET['ajax']) && (!$isoper || $result['unick']==$nick) && $result['status']!=0) { ?>
<tr class="Karnaf_Head2">
<td colspan="2" align="center">
Add new reply
<? if($isoper) echo " (as a user)"; ?>
</td>
</tr>
<tr>
<td colspan="2">
<form name="form1" id="form1" method="post" enctype="multipart/form-data">
<textarea rows="8" style="width:100%" name="reply_text" id="reply_text"></textarea><br>
<? if(defined("KARNAF_UPLOAD_PATH") && KARNAF_UPLOAD_PATH!="") { ?>
Add attachment: <input type="file" style="width:100%" name="attachment-file" id="attachment-file">
<? } ?>
<center><input name="submit" type="submit" value="Submit!"></center>
</form>
</td>
</tr>
<? } ?>
</table>
<? if(!isset($_GET['ajax']) && (in_array($result['rep_g'], $a_groups) || IsKarnafEditorSession())) { ?>
<br>
<center><a href="edit.php?id=<?=$id?>" class="edit_ticket">Edit this ticket</a></center>
<?
  }
}
else safe_die("Invalid Ticket ID!");
sql_free_result($query);
require_once("karnaf_footer.php");
?>
