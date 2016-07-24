<?
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2016 Kobi Shmueli. #
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
$query = squery("SELECT id,rep_g,unick,uemail FROM karnaf_tickets WHERE status=1 AND (lastupd_time<%d OR (open_time<%d AND lastupd_time is NULL AND rep_g='')) AND priority>=0 AND priority<20",
                time()-604800, time()-604800);
while($result = sql_fetch_array($query)) {
  $sender = $result['unick'];
  if($sender == "Guest" && !empty($result['uemail'])) $sender = $result['uemail'];
  echo "-".$result['rep_g']."- Ticket #".$result['id']." from ".$sender." is now getting higher priority. ".KARNAF_URL."/edit.php?id=".$result['id']."\n";
  squery("INSERT INTO karnaf_actions(tid,is_private,a_type,action,a_time,a_by_u,a_by_g) VALUES(%d,0,1,'%s',%d,'%s','%s')",
         $result['id'], "System priority increased to High", time(), "System", $result['rep_g']);
  squery("UPDATE karnaf_tickets SET priority=20 WHERE id=%d", $result['id']);
  #squery("INSERT INTO karnaf_memo_queue(tonick,memo) VALUES('%s','*Warning* Priority for ticket #%s has been increased to High. For more information visit: XXX/edit.php?id=%s')", $sender);
  #squery("INSERT INTO karnaf_actions(tid,is_private,a_type,action,a_time,a_by_u,a_by_g) VALUES(%d,0,1,'%s',%d,'%s','%s')", $result['id'],
  #       "Team leader was notified by MemoServ", time()+1, "System", $result['rep_g']);
}
sql_free_result($query);

/* Run custom post actions code if the scheduler_post_actions() function exists: */
if(function_exists("custom_scheduler_post_actions")) custom_scheduler_post_actions();

require_once("../contentpage_ftr.php");
?>
