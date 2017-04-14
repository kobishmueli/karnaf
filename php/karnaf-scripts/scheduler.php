<?
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2017 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################
/* This is a script to deal with scheduled Karnaf tasks */

require("../ktools.php");

$query = squery("SELECT MAX(CAST(version AS unsigned)) FROM karnaf_schema");
if($result = sql_fetch_array($query)) {
  $cur_version = (int)$result[0];
  if($cur_version < 1) {
    squery("alter table karnaf_tickets add `message_id` varchar(250) DEFAULT NULL after lastupd_time");
    squery("alter table karnaf_replies add `message_id` varchar(250) DEFAULT NULL after ip");
    squery("INSERT INTO karnaf_schema(version) VALUES(1)");
  }
  if($cur_version < 2) {
    squery("CREATE TABLE `karnaf_files` (
      `id` int(11) NOT NULL auto_increment,
      `tid` int(11) NOT NULL default '0',
      `file_name` varchar(250) NOT NULL,
      `file_type` varchar(50) NOT NULL,
      `file_desc` varchar(250) NOT NULL,
      `file_path` varchar(250) NOT NULL,
      `file_size` int(11) NOT NULL,
      `lastupd_time` int(11) default NULL,
      PRIMARY KEY  (`id`))");
    squery("INSERT INTO karnaf_schema(version) VALUES(2)");
  }
  if($cur_version < 3) {
    squery("alter table karnaf_tickets add `last_note` text DEFAULT NULL after message_id");
    squery("INSERT INTO karnaf_schema(version) VALUES(3)");
  }
  if($cur_version < 4) {
    squery("alter table groups add `autoforward` TEXT DEFAULT NULL after iskarnaf");
    squery("INSERT INTO karnaf_schema(version) VALUES(4)");
  }
  if($cur_version < 6) {
    squery("alter table karnaf_tickets add `title` varchar(250) DEFAULT NULL after status");
    squery("alter table karnaf_replies add `title` varchar(250) DEFAULT NULL after tid");
    squery("alter table groups add `assign_msg` TEXT DEFAULT NULL after autoforward");
    squery("INSERT INTO karnaf_schema(version) VALUES(6)");
  }
  if($cur_version < 7) {
    squery("CREATE TABLE `karnaf_mail_rules` (  
     `id` int(11) NOT NULL AUTO_INCREMENT,  
     `name` varchar(250) NOT NULL DEFAULT '',  
     `active` tinyint(1) NOT NULL DEFAULT '0',  
     `priority` int(11) NOT NULL DEFAULT '0',  
     `rcpt_pattern` varchar(250) NOT NULL DEFAULT '',  
     `to_pattern` varchar(250) NOT NULL DEFAULT '',  
     `cc_pattern` varchar(250) NOT NULL DEFAULT '',  
     `subject_pattern` varchar(250) NOT NULL DEFAULT '',  
     `body_pattern` varchar(250) NOT NULL DEFAULT '',  
     `stop_duplicates` tinyint(1) NOT NULL DEFAULT '0',  
     `break` tinyint(1) NOT NULL DEFAULT '0',  
     `set_priority` int(11) NOT NULL DEFAULT '0',  
     `set_group` varchar(30) DEFAULT NULL,  
     `set_extra` varchar(250) DEFAULT NULL,  
     `set_cat3` int(11) NOT NULL DEFAULT '0',  
     PRIMARY KEY (`id`))");
    squery("INSERT INTO karnaf_schema(version) VALUES(7)");
  }
  if($cur_version < 9) {
    squery("alter table karnaf_tickets add `ulocation` varchar(250) NOT NULL DEFAULT '' after uphone");
    squery("INSERT INTO karnaf_schema(version) VALUES(9)");
  }
  if($cur_version < 10) {
    squery("alter table karnaf_tickets add `newuserreply` tinyint(1) NOT NULL DEFAULT '0' after last_note");
    squery("alter table karnaf_tickets add `rep_cc` varchar(30) NOT NULL DEFAULT '' after rep_g");
    squery("INSERT INTO karnaf_schema(version) VALUES(10)");
  }
  if($cur_version < 11) {
    squery("CREATE TABLE `karnaf_filters` (
     `id` int(11) NOT NULL AUTO_INCREMENT,
     `name` varchar(30) NOT NULL DEFAULT '',
     `tooltip` varchar(255) NOT NULL DEFAULT '',
     `querystr` TEXT NOT NULL DEFAULT '',
     `priority` int(11) NOT NULL DEFAULT '0',
     PRIMARY KEY (`id`))");
    squery("INSERT INTO karnaf_schema(version) VALUES(11)");
  }
  if($cur_version < 12) {
    squery("alter table groups add `set_private` tinyint(1) NOT NULL DEFAULT '0' after assign_msg");
    squery("INSERT INTO karnaf_schema(version) VALUES(12)");
  }
  if($cur_version < 13) {
    squery("alter table karnaf_statuses add `ttl` varchar(250) NOT NULL DEFAULT '' after is_closed");
    squery("alter table karnaf_statuses add `ttl_status` int(11) NOT NULL DEFAULT '0' after ttl");
    squery("alter table karnaf_statuses add `priority` int(11) NOT NULL DEFAULT '0' after ttl_status");
    squery("INSERT INTO karnaf_schema(version) VALUES(13)");
  }
  if($cur_version < 14) {
    squery("alter table karnaf_cat3 add `keywords` TEXT NOT NULL DEFAULT '' after allowed_group");
    squery("INSERT INTO karnaf_schema(version) VALUES(14)");
  }
  if($cur_version < 15) {
    squery("CREATE TABLE `karnaf_watching` (
     `tid` int(11) NOT NULL,
     `user` varchar(30) NOT NULL DEFAULT '',
     `fullname` varchar(250) NOT NULL DEFAULT '',
     `timestamp` bigint(14) DEFAULT NULL,
     KEY `tid` (`tid`));");
    squery("INSERT INTO karnaf_schema(version) VALUES(15)");
  }
  if($cur_version < 16) {
    squery("alter table karnaf_tickets add `escalation` int(10) unsigned NOT NULL default '0' after newuserreply");
    squery("INSERT INTO karnaf_schema(version) VALUES(16)");
  }
}
sql_free_result($query);

squery("UPDATE karnaf_tickets SET cat3_id=95 WHERE status=1 AND cat3_id=1 AND description like 'Subject: New User Request Form - %%'");

/* Check for open tickets... (only once per two hours) */
$query = squery("SELECT id,rep_g,count(rep_g) FROM karnaf_tickets WHERE status=1 AND rep_u='' GROUP BY rep_g");
while($result = sql_fetch_array($query)) {
  $number = (int)$result[2];
  if($number == 1) echo "-".$result[1]."- ".$number." ticket is not assigned to anyone. ".KARNAF_URL."/list.php?group=".$result[1]."\n";
  else echo "-".$result[1]."- ".$number." tickets are not assigned to anyone. ".KARNAF_URL."/list.php?group=".$result[1]."\n";
}
sql_free_result($query);

/* TODO: Send memos... */
#$query = squery("SELECT id,tonick,memo FROM karnaf_memo_queue");
#while($result = sql_fetch_array($query)) {
#}
#sql_free_result($query);

/* Search tickets that are waiting for user reply for more than a week... */
$query = squery("SELECT id,rep_g,unick,uemail FROM karnaf_tickets WHERE status=2 AND lastupd_time<%d", time()-604800);
while($result = sql_fetch_array($query)) {
  $sender = $result['unick'];
  if($sender == "Guest" && !empty($result['uemail'])) $sender = $result['uemail'];
  echo "Ticket #".$result['id']." from ".$sender." is being automatically closed. ".KARNAF_URL."/view.php?id=".$result['id']."\n";
  squery("INSERT INTO karnaf_actions(tid,is_private,a_type,action,a_time,a_by_u,a_by_g) VALUES(%d,0,1,'%s',%d,'%s','%s')",
         $result['id'], "Ticket has been automatically closed due to being waiting for user reply for a week.", time(), "System", $result['rep_g']);
  squery("UPDATE karnaf_tickets SET close_time=%d,status=0 WHERE id=%d", time(), $result['id']);
}
sql_free_result($query);

/* Search for tickets that are *open* and waiting for an oper-reply for more than a week... */
$query = squery("SELECT id,rep_u,rep_g,unick,uemail,title FROM karnaf_tickets WHERE status=1 AND (lastupd_time<%d OR (open_time<%d AND lastupd_time is NULL AND rep_g='')) AND priority>=0 AND priority<20 AND escalation=0",
                time()-604800, time()-604800);
while($result = sql_fetch_array($query)) {
  $sender = $result['unick'];
  if($sender == "Guest" && !empty($result['uemail'])) $sender = $result['uemail'];
  echo "-".$result['rep_g']."- Ticket #".$result['id']." from ".$sender." is being escalated. ".KARNAF_URL."/edit.php?id=".$result['id']."\n";
  squery("INSERT INTO karnaf_actions(tid,is_private,a_type,action,a_time,a_by_u,a_by_g) VALUES(%d,0,1,'%s',%d,'%s','%s')",
         $result['id'], "Ticket has been escalated due to inactivity", time(), "System", $result['rep_g']);
  squery("UPDATE karnaf_tickets SET escalation=1 WHERE id=%d", $result['id']);
  #squery("INSERT INTO karnaf_memo_queue(tonick,memo) VALUES('%s','*Warning* Priority for ticket #%s has been increased to High. For more information visit: XXX/edit.php?id=%s')", $sender);
  #squery("INSERT INTO karnaf_actions(tid,is_private,a_type,action,a_time,a_by_u,a_by_g) VALUES(%d,0,1,'%s',%d,'%s','%s')", $result['id'],
  #       "Team leader was notified by MemoServ", time()+1, "System", $result['rep_g']);
  $newsubject = "Escalated: [".strtoupper($result['rep_g'])."] Ticket #".$result['id'];
  if(!empty($result['title'])) $newsubject .= " - ".$result['title'];
  $body = "Ticket #".$result['id']." has been escalated due to inactivity.\r\n";
  $body .= "Operator: ".$result['rep_u']."\r\n";
  $body .= "Group: ".$result['rep_g']."\r\n";
  $body .= "\r\nFor more information visit: ".KARNAF_URL."/edit.php?id=".$result['id']."\r\n";
  $cc = "";
  $query2 = squery("SELECT email,phone FROM users WHERE id IN (SELECT user_id FROM group_members WHERE group_id=(SELECT id FROM groups WHERE name='%s'))", KARNAF_ESCALATION_GROUP);
  while($result2 = sql_fetch_array($query2)) {
    if(!empty($cc)) $cc .= ", ";
    $cc .= $result2['email'];
    #For future use (maybe): send SMS message too.
  }
  sql_free_result($query2);
  send_mail($cc, $newsubject, $body);
}
sql_free_result($query);

/* Search for statuses with ttl enabled */
$query = squery("SELECT s.status_id,s.status_name,s.ttl,s.ttl_status,n.status_name AS newstatus FROM (karnaf_statuses AS s LEFT JOIN karnaf_statuses AS n ON n.status_id=s.ttl_status) WHERE s.ttl!=''");
while($result = sql_fetch_array($query)) {
  $ttl = (int)$result['ttl'] * 60; /* Convert minutes into seconds */
  $query2 = squery("SELECT id,rep_g FROM karnaf_tickets WHERE status=%d AND lastupd_time<%d", $result['status_id'], time() - $ttl);
  while($result2 = sql_fetch_array($query2)) {
    squery("INSERT INTO karnaf_actions(tid,is_private,a_type,action,a_time,a_by_u,a_by_g) VALUES(%d,0,1,'%s',%d,'%s','%s')",
           $result2['id'], "Status automatically changed from ".$result['status_name']." to ".$result['newstatus']." after ".$ttl." minutes",
           time(), "System", $result2['rep_g']);
    squery("UPDATE karnaf_tickets SET status=%d,lastupd_time=%d WHERE id=%d", $result['ttl_status'], time(), $result2['id']);
  }
  sql_free_result($query2);
}
sql_free_result($query);

/* Set categories for open tickets according to the keywords (this should really be moved to php/karnaf-scripts/fetch-emails.php once it becomes stable) -Kobi. */
$query = squery("SELECT id,title,description,cat3_id FROM karnaf_tickets WHERE status IN (SELECT status_id FROM karnaf_statuses WHERE is_closed=0) AND cat3_id IN (SELECT cat3_id FROM karnaf_mail_accounts WHERE active=1) AND opened_by!='(API)'");
while($result = sql_fetch_array($query)) {
  $new_cat3 = find_karnaf_cat_by_keyword($result['title'], $result['description']);
  if(($new_cat3 != 0) && (int)$new_cat3!=(int)$result['cat3_id']) {
    squery("UPDATE karnaf_tickets SET cat3_id=%d WHERE id=%d", $new_cat3, $result['id']);
    if(defined("KARNAF_DEBUG") && KARNAF_DEBUG==1) squery("INSERT INTO karnaf_debug(tid,body) VALUES(%d,'%s')",
                                                          $result['id'], "Category changed from ".$result['cat3_id']." to ".$new_cat3);
    echo "Ticket #".$result['id']." - Category changed from ".$result['cat3_id']." to ".$new_cat3."\n";
  }
}
sql_free_result($query);

/* Delete karnaf_watching rows that are older than 5 minutes */
squery("DELETE FROM karnaf_watching WHERE timestamp<%d", (time()-60*5));

/* Run custom post actions code if the scheduler_post_actions() function exists: */
if(function_exists("custom_scheduler_post_actions")) custom_scheduler_post_actions();

require_once("../contentpage_ftr.php");
?>
