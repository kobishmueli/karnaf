<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2016 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("karnaf_header.php");
CheckOperSession();
$id = trim($_GET['id']);
if(empty($id) || !is_numeric($id)) safe_die("Invalid Ticket ID!");
show_title("Ticket #".$id);
make_menus("Karnaf (HelpDesk)");
$query = squery("SELECT t.id,t.randcode,t.status,t.description,t.unick,t.ufullname,t.uemail,t.uphone,t.uip,t.rep_u,
t.rep_g,t.open_time,t.opened_by,t.is_real,t.is_private,t.email_upd,t.memo_upd,c1.name AS cat1_name,c2.name AS cat2_name,c3.name AS
cat3_name,s.status_name,up.priority_name AS upriority,sp.priority_name AS priority,g.private_actions,t.merged_to,t.cc,up.priority_id 
AS upriority_id, sp.priority_id,t.ext1,t.ext2,t.ext3,t.title 
FROM (karnaf_tickets AS t INNER JOIN karnaf_cat3 AS c3 ON c3.id=t.cat3_id INNER JOIN karnaf_cat2 AS c2 ON c2.id=c3.parent
INNER JOIN karnaf_cat1 AS c1 ON c1.id=c2.parent INNER JOIN karnaf_statuses AS s ON s.status_id=t.status INNER JOIN karnaf_priorities AS up ON
up.priority_id=t.upriority INNER JOIN karnaf_priorities AS sp ON sp.priority_id=t.priority LEFT JOIN groups AS g ON g.name=t.rep_g) WHERE t.id=%d", $id);
if(!($result = sql_fetch_array($query))) safe_die("Invalid Ticket ID!");
if(!IsGroupMember($result['rep_g']) && !IsKarnafAdminSession()) AccessDenied("Ticket is assigned to another team.");
$autoload = 1;
if(isset($_GET['reassign'])) $autoload = 5;
$autostatus = "";
if(isset($_POST['is_private']) && ($_POST['is_private'] == "on")) $is_private = 1;
else $is_private = 0;
if(isset($_POST['is_waiting']) && ($_POST['is_waiting'] == "on")) $is_waiting = 1;
else $is_waiting = 0;
$group = $result['rep_g'];
$unick = $result['unick'];
/* Edit ticket information */
if(isset($_POST['save']) && ($_POST['save'] == "2")) {
  squery("UPDATE karnaf_tickets SET status=%d,cat3_id=%d,upriority=%d,priority=%d,is_private=%d,lastupd_time=%d,title='%s',description='%s' WHERE id=%d",
         $_POST['status'], $_POST['cat3'], $_POST['upriority'], $_POST['priority'], $is_private, time(), $_POST['title'], $_POST['description'], $id);
  if($result['private_actions']) $is_private = 2;
  else $is_private = 0;
  if($result['status'] != $_POST['status']) {
    if($result['status'] == "0") squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'The ticket has been re-opened.','%s','%s',%d,1,%d)", $id, $nick, $group, (time()+1), $is_private);
    else if($_POST['status'] == "0") {
      squery("UPDATE karnaf_tickets SET close_time=%d,lastupd_time=%d WHERE id=%d", time(), time(), $id);
      squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'The ticket has been closed.','%s','%s',%d,1,%d)", $id, $nick, $group, (time()+1), $is_private);
    }
    else squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'Status changed','%s','%s',%d,1,%d)", $id, $nick, $group, time(), $is_private);
  }
  if($result['priority_id'] != $_POST['priority']) squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'System priority changed','%s','%s',%d,1,%d)", $id, $nick, $group, time(), $is_private);
  if($result['upriority_id'] != $_POST['upriority']) squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'User priority changed','%s','%s',%d,1,%d)", $id, $nick, $group, time(), $is_private);
  if($result['title'] != $_POST['title']) squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'Ticket title changed','%s','%s',%d,1,%d)", $id, $nick, $group, time(), $is_private);
  if($result['description'] != $_POST['description']) squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'Ticket description changed','%s','%s',%d,1,%d)", $id, $nick, $group, time(), $is_private);
  if(isset($_POST['ext1']) && ($result['ext1'] != $_POST['ext1'])) squery("UPDATE karnaf_tickets SET ext1='%s' WHERE id=%d", $_POST['ext1'], $id);
  if(isset($_POST['ext2']) && ($result['ext2'] != $_POST['ext2'])) squery("UPDATE karnaf_tickets SET ext2='%s' WHERE id=%d", $_POST['ext2'], $id);
  if(isset($_POST['ext3']) && ($result['ext3'] != $_POST['ext3'])) squery("UPDATE karnaf_tickets SET ext3='%s' WHERE id=%d", $_POST['ext3'], $id);
  if(!empty($_POST['merged_to']) && $result['merged_to'] != $_POST['merged_to']) {
    $merged_to = $_POST['merged_to'];
    $query2 = squery("SELECT id,status,uemail FROM karnaf_tickets WHERE id=%d AND status!=0 AND merged_to=0", $merged_to);
    if(($result2 = sql_fetch_array($query2))) {
      squery("UPDATE karnaf_tickets SET merged_to=%d,status=0,close_time=%d,lastupd_time=%d WHERE id=%d", $merged_to, time(), time(), $id);
      # Merge actions...
      $query3 = squery("SELECT is_private,a_type,action,a_time,a_by_u,a_by_g FROM karnaf_actions WHERE tid=%d", $id);
      while(($result3 = sql_fetch_array($query3))) {
        squery("INSERT INTO karnaf_actions(tid,is_private,a_type,action,a_time,a_by_u,a_by_g) VALUES(%d,%d,%d,'%s',%d,'%s','%s')",
               $merged_to, $result3['is_private'], $result3['a_type'], $result3['action'], $result3['a_time'], $result3['a_by_u'], $result3['a_by_g']);
      }
      sql_free_result($query3);
      # Merge replies...
      $query3 = squery("SELECT title,reply,r_time,r_by,r_from,ip,message_id FROM karnaf_replies WHERE tid=%d", $id);
      while(($result3 = sql_fetch_array($query3))) {
        squery("INSERT INTO karnaf_replies(tid,title,reply,r_time,r_by,r_from,ip,message_id) VALUES(%d,'%s','%s',%d,'%s','%s','%s','%s')",
               $merged_to, $result3['title'], $result3['reply'], $result3['r_time'], $result3['r_by'], $result3['r_from'], $result3['ip'], $result3['message_id']);
      }
      sql_free_result($query3);
      # Merge attachments...
      $query3 = squery("SELECT file_name,file_type,file_desc,file_path,file_size,lastupd_time FROM karnaf_files WHERE tid=%d", $id);
      while(($result3 = sql_fetch_array($query3))) {
        squery("INSERT INTO karnaf_files(tid,file_name,file_type,file_desc,file_path,file_size,lastupd_time) VALUES(%d,'%s','%s','%s','%s',%d,%d)",
               $merged_to, $result3['file_name'], $result3['file_type'], $result3['file_desc'], $result3['file_path'], $result3['file_size'], $result3['lastupd_time']);
      }
      sql_free_result($query3);
      squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,%d,'%s','%s',%d,5,%d)",
             $merged_to, $id, $nick, $group, (time()+1), $is_private);
      squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,%d,'%s','%s',%d,6,%d)",
             $id, $merged_to, $nick, $group, (time()+1), $is_private);
      if($result['uemail'] != $result2['uemail']) {
        squery("UPDATE karnaf_tickets SET cc='%s' WHERE id=%d", $result['uemail'], $merged_to);
      }
      $autostatus = "The ticket has been merged with Ticket #".$merged_to;
    }
    else $autostatus = "Error! Could not merge to Ticket #".$merged_to.". Please verify the ticket is open and is not merged into another ticket.";
    sql_free_result($query2);
  }
  else $autostatus = "The ticket has been updated.";
  $email_update_str = "Ticket information has been edited.";
  $autoload = 2;
}
/* Edit user information */
if(isset($_POST['save']) && ($_POST['save'] == "3")) {
  if(isset($_POST['is_real']) && ($_POST['is_real'] == "on")) $is_real = 1;
  else $is_real = 0;
  if(isset($_POST['email_upd']) && ($_POST['email_upd'] == "on")) $email_upd = 1;
  else $email_upd = 0;
  if(isset($_POST['memo_upd']) && ($_POST['memo_upd'] == "on")) $memo_upd = 1;
  else $memo_upd = 0;
  if(!isset($_POST['uphone'])) $_POST['uphone'] = "";
  squery("UPDATE karnaf_tickets SET unick='%s',ufullname='%s',uemail='%s',cc='%s',uip='%s',uphone='%s',is_real=%d,email_upd=%d,memo_upd=%d,lastupd_time=%d WHERE id=%d",
         $_POST['unick'], $_POST['ufullname'], $_POST['uemail'], $_POST['cc'], $_POST['uip'], $_POST['uphone'], $is_real, $email_upd, $memo_upd, time(), $id);
  squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'User information changed','%s','%s',%d,1,%d)", $id, $nick, $group, time(), $is_private);
  $autostatus = "The ticket has been updated.";
  $email_update_str = "User information has been edited.";
  $autoload = 3;
}
/* Add reply */
if(isset($_POST['reply_text'])) {
  if($is_private) $r_by = $group;
  else $r_by = $nick;
  if(empty($result['rep_u']) && isset($_POST['auto_assign']) && $_POST['auto_assign']=="on" && $group!=PSEUDO_GROUP) {
    /* Auto-assign is checked */
    if($result['private_actions'] || $is_private) $a_type = 4;
    else $a_type = 3;
    squery("UPDATE karnaf_tickets SET rep_u='%s',lastupd_time=%d WHERE id=%d", $nick, time(), $id);
    squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type) VALUES(%d,'%s','%s','%s',%d,%d)",
           $id, $nick, $nick, $group, (time()-1), $a_type);
  }
  if(!empty($_POST['reply_text'])) {
    $reply_text = str_replace("%OPERNICK%",$nick,$_POST['reply_text']);
    if(isset($a_fullname)) $reply_text = str_replace("%OPERFULLNAME%",$a_fullname,$_POST['reply_text']);
    if(isset($_POST['reply_to']) && !empty($_POST['reply_to']) && $_POST['reply_to']!=$result['uemail']) {
      if($_POST['reply_cc']!=$result['cc']) $reply_text = "CC: ".$_POST['reply_cc']."\r\n".$reply_text;
      $reply_text = "To: ".$_POST['reply_to']."\r\n".$reply_text;
      if(!defined("IRC_MODE") && isset($a_fullname) && !empty($a_fullname)) $body = "Message from ".$a_fullname.":\r\n";
      else $body = "Message from ".$nick.":\r\n";
      $body .= $reply_text."\r\n";
      $body .= "---\r\n";
      $body .= "*** Please make sure you keep the original subject when replying us by email ***";
      squery("INSERT INTO karnaf_replies(tid,reply,r_by,r_time,r_from,ip) VALUES(%d,'%s','%s',%d,'%s','%s')",
             $id, $reply_text, $nick, time(), $r_by, get_session_ip());
      $newsubject = "[".strtoupper($group)."] Ticket #".$result['id'];
      if(!empty($result['title'])) $newsubject .= " - ".$result['title'];
      send_mail($_POST['reply_to'], $newsubject, $body);
      send_mail($_POST['reply_cc'], $newsubject, $body);
      /* Don't update the user unless he was on the To or CC fields */
      $email_update_str = "";
    }
    else {
      squery("INSERT INTO karnaf_replies(tid,reply,r_by,r_time,r_from,ip) VALUES(%d,'%s','%s',%d,'%s','%s')",
             $id, $reply_text, $nick, time(), $r_by, get_session_ip());
      $email_update_str = "A new reply was sent to you.\r\nReply message:\r\n".$reply_text;
    }
    squery("UPDATE karnaf_tickets SET lastupd_time=%d,newuserreply=0 WHERE id=%d", time(), $id);
  }
  $autostatus = "The ticket has been updated.";
  if($result['private_actions']) $is_private = 2;
  else $is_private = 0;
  if($_POST['close'] == "1") {
    if($result['status'] == "0") {
      $autostatus = "The ticket is already closed.";
    }
    else {
      $autostatus = "The ticket has been closed.";
      if(isset($email_update_str) && strstr($email_update_str,"A new reply was sent to you.\r\nReply message:")) $email_update_str = str_replace("A new reply was sent to you.\r\nReply message:","The ticket has been closed:",$email_update_str);
      else $email_update_str = "The ticket has been closed.";
      squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'The ticket has been closed.','%s','%s',%d,1,%d)", 
             $id, $nick, $group, (time()+1), $is_private);
      squery("UPDATE karnaf_tickets SET status=0,close_time=%d,lastupd_time=%d WHERE id=%d", time(), time(), $id);
    }
  }
  if($_POST['reopen'] == "1") {
    if($result['status'] != "0") {
      $autostatus = "The ticket isn't closed.";
    }
    else {
      $autostatus = "The ticket has been re-opened.";
      $email_update_str = "The ticket has been re-opened.";
      squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'The ticket has been re-opened.','%s','%s',%d,1,%d)",
             $id, $nick, $group, (time()+1), $is_private);
      squery("UPDATE karnaf_tickets SET status=1,lastupd_time=%d WHERE id=%d", time(), $id);
    }
  }
  if($is_waiting) squery("UPDATE karnaf_tickets SET status=2,lastupd_time=%d WHERE id=%d AND status!=0", time(), $id);
  if(isset($_POST['short'])) $autoload = 8;
  else $autoload = 6;
}
/* Re-assign to... */
if(isset($_POST['assign_group'])) {
  if($_POST['assign_group'] != $result['rep_g']) {
    if($result['private_actions']) $is_private = 2;
    else $is_private = 0;
    squery("UPDATE karnaf_tickets SET rep_g='%s',rep_u='',lastupd_time=%d WHERE id=%d", $_POST['assign_group'], time(), $id);
    squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'%s','%s','%s',%d,2,%d)", 
           $id, $_POST['assign_group'], $nick, $group, (time()+1), $is_private);
    $autostatus = "The ticket has been re-assigned to ".$_POST['assign_group'].".";
    $email_update_str = "";
    $query2 = squery("SELECT assign_msg FROM groups WHERE name='%s'", $_POST['assign_group']);
    if(($result2 = sql_fetch_array($query2))) $email_update_str = $result2['assign_msg'];
    sql_free_result($query2);
    if($email_update_str == "default") $email_update_str = "The ticket has been re-assigned to another team (this means your ticket has been forwarded to another team who will deal with it and you need to wait for their reply).";
  }
  else if($_POST['assign_user'] != $result['rep_u']) {
    if(empty($_POST['assign_user'])) {
      squery("UPDATE karnaf_tickets SET rep_u='',lastupd_time=%d WHERE id=%d", time(), $id);
      squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type) VALUES(%d,'%s','%s','%s',%d,2)", 
             $id, $group, $nick, $group, (time()+1));
      $autostatus = "The ticket has been re-assigned to ".$group.".";
      $email_update_str = "The ticket has been re-assigned back to team.";
    }
    else {
     $a_type = 3;
      $query2 = squery("SELECT private_actions FROM groups WHERE name='%s'", $result['rep_g']);
      if(($result2 = sql_fetch_array($query2)) && $result2['private_actions']) $a_type = 4;
      sql_free_result($query2);
      squery("UPDATE karnaf_tickets SET rep_u='%s',lastupd_time=%d WHERE id=%d", $_POST['assign_user'], time(), $id);
      squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type) VALUES(%d,'%s','%s','%s',%d,%d)", 
             $id, $_POST['assign_user'], $nick, $group, (time()+1), $a_type);
      $autostatus = "The ticket has been re-assigned to ".$_POST['assign_user'].".";
      if(defined("IRC_MODE"))
       $email_update_str = "The ticket has been re-assigned to a staff member (this means your ticket has been forwarded to a staff member to deal with it and you need to wait for his/her reply).";
      else if($a_type != 4)
       $email_update_str = "The ticket has been re-assigned to ".$_POST['assign_user'];
      if($nick != $_POST['assign_user']) {
        send_memo($_POST['assign_user'], "Ticket #".$result['id']." has been assigned to you. For more information visit: ".KARNAF_URL."/edit.php?id=".$result['id']);
        $newsubject = "[".strtoupper($group)."] Ticket #".$result['id'];
        if(!empty($result['title'])) $newsubject .= " - ".$result['title'];
        $query2 = squery("SELECT email FROM users WHERE user='%s'", $_POST['assign_user']);
        if(($result2 = sql_fetch_array($query2))) send_mail($result2['email'], $newsubject, "Ticket #".$result['id']." has been assigned to you. For more information visit: ".KARNAF_URL."/edit.php?id=".$result['id']);
        sql_free_result($query2);
      }
    }
  }
  /* Remove waiting for user reply status from tickets that are re-assigned */
  squery("UPDATE karnaf_tickets SET status=1,lastupd_time=%d WHERE id=%d AND status=2", time(), $id);
  $autoload = 5;
}
/* Add action */
if(isset($_POST['action_text'])) {
  if(!$is_private && isset($_POST['team_action']) && ($_POST['team_action'] == "on")) $is_private = 2;
  if(!empty($_POST['action_text'])) {
    if(!empty($_POST['onbehalf_g']) && (IsGroupMember("dalnet-aob") || IsKarnafAdminSession()) && IsGroupMember($_POST['onbehalf_g'])) {
      /* Let SRAs/AOB/karnaf-admins add special actions */
      $group = $_POST['onbehalf_g'];
    }
    squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,is_private) VALUES(%d,'%s','%s','%s',%d,%d)", $id, $_POST['action_text'], 
           $nick, $group, time(), $is_private);
    squery("UPDATE karnaf_tickets SET last_note='%s',lastupd_time=%d,newuserreply=0 WHERE id=%d", $_POST['action_text']." (".$nick.")", time(), $id);
    $autostatus = "The ticket has been updated.";
    if($is_private != 1) $email_update_str = "A new action has been added to the ticket.\r\nAction message: ".$_POST['action_text'];
  }
  else $autostatus = "Nothing to update...";
  if($_POST['close'] == "1") {
    if($result['private_actions']) $is_private = 2;
    else $is_private = 0;
    if($result['status'] == "0") {
      $autostatus = "The ticket is already closed.";
    }
    else {
      $autostatus = "The ticket has been closed.";
      $email_update_str = "The ticket has been closed.";
      squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'The ticket has been closed.','%s','%s',%d,1,%d)", 
             $id, $nick, $group, (time()+1), $is_private);
      squery("UPDATE karnaf_tickets SET status=0,close_time=%d,lastupd_time=%d WHERE id=%d", time(), time(), $id);
    }
  }
  if($_POST['reopen'] == "1") {
    if($result['status'] != "0") {
      $autostatus = "The ticket isn't closed.";
    }
    else {
      $autostatus = "The ticket has been re-opened.";
      $email_update_str = "The ticket has been re-opened.";
      squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'The ticket has been re-opened.','%s','%s',%d,1,%d)",
             $id, $nick, $group, (time()+1), $is_private);
      squery("UPDATE karnaf_tickets SET status=1,lastupd_time=%d WHERE id=%d", time(), $id);
    }
  }
  $autoload = 4;
}
/* Send SMS */
if(isset($_POST['sms_account'])) {
  if(send_sms($_POST['sms_account'], $_POST['sms_to'], $_POST['sms_body'])) {
    squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'%s','%s','%s',%d,1,%d)", $id, "Sent SMS to ".$_POST['sms_to'],
           $nick, $group, time(), $is_private);
    squery("UPDATE karnaf_tickets SET lastupd_time=%d WHERE id=%d", time(), $id);
    $autostatus = "The SMS has been sent.";
    if($is_private != 1) $email_update_str = "Sent SMS to ".$_POST['sms_to'];
  }
  else $autostatus = "Error! Could not send SMS!";
  $autoload = 9;
}
if(isset($email_update_str) && !empty($email_update_str)) {
  if((!isset($is_private) || $is_private!="1") && (!isset($_POST['no_userupd']) || $_POST['no_userupd']!="on")) {
    if($result['memo_upd']=="1") send_memo($result['unick'], "Your ticket #".$result['id']." has been updated. For more information visit: ".KARNAF_URL."/view.php?id=".$result['id']."&code=".$result['randcode']);
    if($result['email_upd']=="1") {
      if(!defined("IRC_MODE") && isset($a_fullname) && !empty($a_fullname)) $body = "Your ticket #".$result['id']." has been updated by ".$a_fullname.":\r\n".$email_update_str."\r\n";
      else $body = "Your ticket #".$result['id']." has been updated:\r\n".$email_update_str."\r\n";
      $body .= "---\r\nFor more information visit: ".KARNAF_URL."/view.php?id=".$result['id']."&code=".$result['randcode'];
      $body .= "\r\n*** Please make sure you keep the original subject when replying us by email ***";
      $newsubject = "[".strtoupper($group)."] Ticket #".$result['id'];
      if(!empty($result['title'])) $newsubject .= " - ".$result['title'];
      send_mail($result['uemail'], $newsubject, $body);
      if(isset($_POST['reply_cc']) && $_POST['reply_cc']!=$result['cc']) send_mail($result['cc'], $newsubject, $body);
      else send_mail($result['cc'], $newsubject, $body);
    }
  }
}
?>
<script language="JavaScript" type="text/javascript">
var xmlhttp;
var ischanged = 1;
function load_page(id) {
    if (ischanged && document.getElementById('reply_text') && document.getElementById('reply_text').value != '') {
      if(!confirm('Are you sure you want to move to a different tab? your changes will *NOT* be saved.')) return;
    }
    if (id == 1) url = 'view.php?id=<?=$id?>&ajax=1';
    if (id == 2) url = 'edit_ticketinfo.php?id=<?=$id?>&ajax=1';
    if (id == 3) url = 'edit_userinfo.php?id=<?=$id?>&ajax=1';
    if (id == 4) url = 'edit_actions.php?id=<?=$id?>&ajax=1';
    if (id == 5) url = 'edit_reassign.php?id=<?=$id?>&ajax=1';
    if (id == 6) url = 'edit_replies.php?id=<?=$id?>&ajax=1';
    if (id == 7) url = 'check_user.php?tid=<?=$id?>&uuser=<?=$unick?>&ajax=1';
    if (id == 8) url = 'edit_replies.php?id=<?=$id?>&ajax=1&short=1';
    if (id == 9) url = 'edit_sms.php?id=<?=$id?>&ajax=1';
    url = url + "&rand=" + Math.random();
    xmlhttp=null;
    if (window.XMLHttpRequest) {// code for all new browsers
        xmlhttp=new XMLHttpRequest();
    }
    else if (window.ActiveXObject) {// code for IE5 and IE6
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    if (xmlhttp!=null) {
        document.getElementById('status').innerHTML = '';
        document.getElementById('Edit_Space').innerHTML = 'Loading page, please wait...';
        xmlhttp.onreadystatechange=do_change;
        xmlhttp.open("GET",url,true);
        xmlhttp.send(null);
    }
    else{
        alert("Your browser does not support XMLHTTP.");
    }
}

function load_template(id) {
    url = "karnaf_templates.php?id=" + id + "&rand=" + Math.random();
    xmlhttp=null;
    if (window.XMLHttpRequest) {// code for all new browsers
        xmlhttp=new XMLHttpRequest();
    }
    else if (window.ActiveXObject) {// code for IE5 and IE6
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    if (xmlhttp!=null) {
        xmlhttp.onreadystatechange=do_template_change;
        xmlhttp.open("GET",url,true);
        xmlhttp.send(null);
    }
    else{
        alert("Your browser does not support XMLHTTP.");
    }
}

function post_page(url, data) {
    url = url + "&rand=" + Math.random();
    xmlhttp=null;
    if (window.XMLHttpRequest) {// code for all new browsers
        xmlhttp=new XMLHttpRequest();
        if (xmlhttp.overrideMimeType) {
            xmlhttp.overrideMimeType('text/html');
        }
    }
    else if (window.ActiveXObject) {// code for IE5 and IE6
        xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
    }
    if (xmlhttp!=null) {
        document.getElementById('Edit_Space').innerHTML = 'Loading page, please wait...';
        xmlhttp.onreadystatechange=do_change;
        xmlhttp.open("POST",url,true);
        xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xmlhttp.setRequestHeader("Content-length", data.length);
        xmlhttp.setRequestHeader("Connection", "close");
        xmlhttp.send(data);
    }
    else {
        alert("Your browser does not support XMLHTTP.");
    }
}

function submit1_onclick() {
  ischanged = 0;
  document.form1.save.value = "1";
  document.form1.close.value = "0";
  document.form1.edit_button.disabled = true;
  document.form1.close_button.disabled = true;
//  post_page('edit_actions.php?id=<?=$id?>&ajax=1', "action_text=" + escape(encodeURI(document.getElementById("action_text").value)));
  document.form1.submit();
}

function submit2_onclick() {
  if (confirm('Are you sure you want to close this ticket?')) {
    ischanged = 0;
    document.form1.save.value = "0";
    document.form1.close.value = "1";
    document.form1.edit_button.disabled = true;
    document.form1.close_button.disabled = true;
    document.form1.submit();
  }
}

function submit3_onclick() {
  ischanged = 0;
  document.form1.save.value = "0";
  document.form1.reopen.value = "1";
  document.form1.edit_button.disabled = true;
  document.form1.close_button.disabled = true;
  document.form1.submit();
}

function do_change() {
    if (xmlhttp.readyState==4) {
        if (xmlhttp.status==200) {
            //alert(xmlhttp.responseText);
            document.getElementById('Edit_Space').innerHTML = xmlhttp.responseText;
            //alert("error status code: " + xmlhttp.status);
        }
        else {
            document.getElementById('Edit_Space').innerHTML = 'Problem retrieving XML data!<br>Error status code: ' + xmlhttp.status;
        }
        xmlhttp = null;
    }
}

function do_template_change() {
    if (xmlhttp.readyState==4) {
        if (xmlhttp.status==200) {
            //alert(xmlhttp.responseText);
            document.form1.reply_text.value = xmlhttp.responseText;
            //alert("error status code: " + xmlhttp.status);
        }
        else {
            document.form1.reply_text.value = 'Problem retrieving XML data!\r\nError status code: ' + xmlhttp.status;
        }
        xmlhttp = null;
    }
}

function setinfo(username,name,email,phone) {
  form1.unick.value = username;
  form1.ufullname.value = name;
  form1.uemail.value = email;
  form1.uphone.value = phone;
}

function open_search() {
  window.open("searchuser.php","searchwin","status=0,toolbar=0,location=0,scrollbars=1,width=500,height=200");
}

function do_closewarning() {
    if (ischanged && document.getElementById('reply_text') && document.getElementById('reply_text').value != '') {
      return "Your changes will *NOT* be saved.";
    }
}

window.onbeforeunload = do_closewarning;

<? if(isset($autoload)) { ?>
function auto_load() {
    load_page(<?=$autoload?>);
    document.getElementById('status').innerHTML = '<?=$autostatus?>';
}
window.onload = auto_load;
<? } ?>
</script>
<div id="status" class="status"></div><br>
<center>
<a href="mylist.php">My List</a> | 
<a href="list.php">Open Tickets</a> | 
<a href="list.php?oper=<?=$nick?>">My Tickets</a> | 
<a href="list.php?oper=">Non-Assigned Tickets</a> | 
<a href="list.php?group=<?=$result['rep_g']?>">Group (<?=$result['rep_g']?>) Tickets</a>
</center>
<center>
<input name="edit_view" type="button" value="View" onClick="javascript:load_page(1)">
<input name="edit_info" type="button" value="Edit Ticket Info" onClick="javascript:load_page(2)">
<input name="edit_user" type="button" value="Edit User Info" onClick="javascript:load_page(3)">
<input name="edit_actions" type="button" value="Actions" onClick="javascript:load_page(4)">
<input name="edit_reassign" type="button" value="Re-assign" onClick="javascript:load_page(5)">
<input name="edit_replies" type="button" value="Replies" onClick="javascript:load_page(6)">
<input name="new_reply" type="button" value="New Reply" onClick="javascript:load_page(8)">
<? if(!defined("IRC_MODE")) { ?>
<input name="check_user" type="button" value="Check User" onClick="javascript:load_page(7)">
<input name="send_sms" type="button" value="SMS" onClick="javascript:load_page(9)">
<? } ?>
</center>
<br><br>
<span id="Edit_Space">
Please click on one of the buttons...
</span>
<?
sql_free_result($query);
require_once("karnaf_footer.php");
?>
