<?
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

/* This is a script to import tickets from a SysAid database into Karnaf */

require("../ktools.php");

function remove_before_slashes($text) {
  if(($pos = strpos($text,'\\')) !== false) {
    $text = substr($text,$pos+1);
  }
  return $text;
}

/* Let's cache the users + emails */
$cached_users = array();
$query = squery("SELECT user_name,email_address,phone,display_name FROM sysaid.sysaid_user");
while($result = sql_fetch_array($query)) {
  $cached_users[$result['user_name']] = array($result['user_name'],$result['email_address'],$result['phone'],$result['display_name']);
}
sql_free_result($query);

/* Let's check cat1 categories */
$query = squery("SELECT DISTINCT(TRIM(problem_type)) AS cat FROM sysaid.problem_type");
while($result = sql_fetch_array($query)) {
  $cat = $result['cat'];
  if($cat == "Basic Software") continue; /* Skipping annoying default sysaid category... */
  if(preg_match("/\d+\s-\s([a-zA-Z0-9\s\(\)]+)$/", $cat, $matches)) $cat = $matches[1];
  $found = 0;
  $query2 = squery("SELECT name FROM karnaf_cat1 WHERE name='%s'", $cat);
  if($result2 = sql_fetch_array($query2)) $found = 1;
  sql_free_result($query2);
  if(!$found) {
    echo "New cat1 category: ".$cat."\n";
    $priority = 10;
    if($cat == "Other") $priority = 20;
    squery("INSERT INTO karnaf_cat1(name,priority) VALUES('%s',%d)", $cat, $priority);
  }
}
sql_free_result($query);

/* Let's check cat2 categories */
$query = squery("SELECT TRIM(problem_sub_type) AS cat,TRIM(problem_type) AS pcat FROM sysaid.problem_type");
while($result = sql_fetch_array($query)) {
  $cat = $result['cat'];
  if(preg_match("/\d+\s-\s([a-zA-Z0-9\s\(\)]+)$/", $cat, $matches)) $cat = $matches[1];
  $pcat = $result['pcat'];
  if(preg_match("/\d+\s-\s([a-zA-Z0-9\s\(\)]+)$/", $pcat, $matches)) $pcat = $matches[1];
  if($pcat == "Basic Software" && $cat == "Patch Approval") continue; /* Skipping annoying default sysaid category... */
  $found = 0;
  $query2 = squery("SELECT name FROM karnaf_cat2 WHERE name='%s' AND parent=(SELECT id FROM karnaf_cat1 WHERE name='%s')", $cat, $pcat);
  if($result2 = sql_fetch_array($query2)) $found = 1;
  sql_free_result($query2);
  if(!$found) {
    echo "New cat2 category: ".$cat." (parent: ".$pcat.")\n";
    $priority = 10;
    if($cat == "Other") $priority = 20;
    squery("INSERT INTO karnaf_cat2(name,priority,parent) VALUES('%s',%d,(SELECT id FROM karnaf_cat1 WHERE name='%s'))", $cat, $priority, $pcat);
  }
}
sql_free_result($query);

/* Let's check cat3 categories */
$query = squery("SELECT TRIM(third_level_category) AS cat,TRIM(problem_sub_type) AS pcat,TRIM(problem_type) AS ppcat FROM sysaid.problem_type");
while($result = sql_fetch_array($query)) {
  $cat = $result['cat'];
  if(preg_match("/\d+\s-\s([a-zA-Z0-9\s\(\)]+)$/", $cat, $matches)) $cat = $matches[1];
  $pcat = $result['pcat'];
  if(preg_match("/\d+\s-\s([a-zA-Z0-9\s\(\)]+)$/", $pcat, $matches)) $pcat = $matches[1];
  $ppcat = $result['ppcat'];
  if(preg_match("/\d+\s-\s([a-zA-Z0-9\s\(\)]+)$/", $ppcat, $matches)) $ppcat = $matches[1];
  if($ppcat == "Basic Software" && $pcat == "Patch Approval") continue; /* Skipping annoying default sysaid category... */
  $found = 0;
  $query2 = squery("SELECT name FROM karnaf_cat3 WHERE name='%s' AND parent=(SELECT id FROM karnaf_cat2 WHERE name='%s' AND parent=(SELECT id FROM karnaf_cat1 WHERE name='%s'))", $cat, $pcat, $ppcat);
  if($result2 = sql_fetch_array($query2)) $found = 1;
  sql_free_result($query2);
  if(!$found) {
    echo "New cat3 category: ".$cat." (parent: ".$ppcat." - ".$pcat.")\n";
    $priority = 10;
    if($cat == "Other") $priority = 20;
    squery("INSERT INTO karnaf_cat3(name,priority,parent) VALUES('%s',%d,(SELECT id FROM karnaf_cat2 WHERE name='%s' AND parent=(SELECT id FROM karnaf_cat1 WHERE name='%s')))", $cat, $priority, $pcat, $ppcat);
  }
}
sql_free_result($query);

/* Let's cache the categories... */
$cats = array();
$query = squery("SELECT cat3.id,CONCAT(cat1.name,' - ',cat2.name,' - ',cat3.name) AS cat FROM (karnaf_cat3 AS cat3 INNER JOIN karnaf_cat2 AS cat2 ON cat2.id=cat3.parent INNER JOIN karnaf_cat1 AS cat1 ON cat1.id=cat2.parent)");
while($result = sql_fetch_array($query)) {
  echo "Cached category: ".$result['cat']."\n";
  $cats[$result['cat']] = (int)$result['id'];
}
sql_free_result($query);

$query = squery("SELECT id,title,description,problem_type,problem_sub_type,status,responsibility,request_user,submit_user,assigned_group,
unix_timestamp(insert_time) AS insert_time,full_name,solution,unix_timestamp(close_time) AS close_time,
TRIM(problem_type) AS cat1,TRIM(problem_sub_type) AS cat2,TRIM(third_level_category) AS cat3,cc,
unix_timestamp(update_time) AS update_time,TRIM(notes) AS notes,TRIM(cust_notes) AS cust_notes FROM sysaid.service_req ORDER BY id");
while($result = sql_fetch_array($query)) {
  $tid = (int)$result['id'];
  if($tid == 6) continue; /* Special case... */
  $cat_name = $result['problem_type']." - ".$result['problem_sub_type'];
  $rep_u = remove_before_slashes($result['responsibility']);
  if($rep_u == "none") $rep_u = "";
  $rep_g = $result['assigned_group'];
  if($rep_g == "none" || empty($rep_g)) $rep_g = KARNAF_DEFAULT_GROUP;
  $uuser = remove_before_slashes($result['request_user']);
  $opened_by = remove_before_slashes($result['submit_user']);
  $open_time = $result['insert_time'];
  $title = trim($result['title']);
  $description = $result['description'];
  $uname = $result['full_name'];
  echo "Ticket: ".$tid."\n";
  $uemail = $uuser;
  if(!strstr($uemail,"@")) $uemail .= "@".MY_DOMAIN;
  $uphone = "";
  $oldcc = trim($result['cc']);
  $cc = "";
  foreach(explode(",", $oldcc) as $curcc) {
    $curcc = trim($curcc);
    if(isset($cached_users[$curcc])) $curcc = $cached_users[$curcc][1];
    if(!empty($cc)) $cc .= ",";
    $cc .= $curcc;
  }
  $old_status = (int)$result['status'];
  if(isset($cached_users[$result['request_user']])) {
    $uemail = $cached_users[$result['request_user']][1];
    $uphone = trim($cached_users[$result['request_user']][2]);
    if(!empty($cached_users[$result['request_user']][3])) $uname = trim($cached_users[$result['request_user']][3]);
  }
  else if(isset($cached_users[$result['submit_user']])) {
    $uemail = $cached_users[$result['submit_user']][1];
    $uphone = trim($cached_users[$result['submit_user']][2]);
    if(!empty($cached_users[$result['submit_user']][3])) $uname = trim($cached_users[$result['submit_user']][3]);
  }
  if(0) {
    /* For debugging: */
    echo "Title: ".$result['title']."\n";
    echo "Status: ".$result['status']."\n";
    echo "Category: ".$cat_name."\n";
    echo "uuser: ".$uuser."\n";
    echo "rep_u: ".$rep_u."\n";
    echo "rep_g: ".$rep_g."\n";
    echo "Opened by: ".$opened_by."\n";
    echo "Opened at: ".$open_time."\n";
    echo "----------------------------\n";
  }
  $found = 0;
  $query2 = squery("SELECT id FROM karnaf_tickets WHERE id=%d", $tid);
  if($result2 = sql_fetch_array($query2)) $found = 1;
  sql_free_result($query2);
  if($found) {
    #echo "Skipping ticket #".$tid."...\n";
    #continue;
    echo "Ticket #".$tid." already exists, deleting the old ticket...\n";
    squery("DELETE FROM karnaf_tickets WHERE id=%d", $tid);
    squery("DELETE FROM karnaf_actions WHERE tid=%d", $tid);
    squery("DELETE FROM karnaf_replies WHERE tid=%d", $tid);
    squery("DELETE FROM karnaf_files WHERE tid=%d", $tid);
    if(defined("KARNAF_UPLOAD_PATH") && KARNAF_UPLOAD_PATH!="" && file_exists(KARNAF_UPLOAD_PATH."/".$tid)) system("find ".KARNAF_UPLOAD_PATH."/".$tid." -type f -delete");
  }
  if(empty($result['submit_user'])) {
    echo "Skipping ticket #".$tid." (no submit user)...\n";
    continue;
  }
  /* Creating karnaf ticket... */
  $randstr = RandomNumber(10);
  $uip = "";
  $priority = 0;
  if(!isset($upriority)) $upriority = 0;
  /* If the user priority is *lower* than the system priority, we'll use the user priority */
  if($priority > $upriority) $priority = $upriority;
  $status = 1;
  if($old_status == 3 || $old_status == 4) $status = 0; /* Closed */
  if($old_status == 7) $status = 5; /* Deleted = mark as spam */
  if($old_status == 0) $status = 5; /* Not sure what is status 0 so let's mark as spam for now... */
  if($old_status == 5) $status = 3; /* Pending --> Held*/
  if($old_status == 8) $status = 2; /* Waiting for user reply (special) */
  $cat3_id = 1; /* Default category */
  $cat1 = $result['cat1'];
  $cat2 = $result['cat2'];
  $cat3 = $result['cat3'];
  if(preg_match("/\d+\s-\s([a-zA-Z0-9\s\(\)]+)$/", $cat1, $matches)) $cat1 = $matches[1];
  if(preg_match("/\d+\s-\s([a-zA-Z0-9\s\(\)]+)$/", $cat2, $matches)) $cat2 = $matches[1];
  if(preg_match("/\d+\s-\s([a-zA-Z0-9\s\(\)]+)$/", $cat3, $matches)) $cat3 = $matches[1];
  $curcat = $cat1." - ".$cat2." - ".$cat3;
  if(isset($cats[$curcat])) $cat3_id = $cats[$curcat];
  squery("INSERT INTO karnaf_tickets(id,randcode,status,title,description,cat3_id,unick,ufullname,uemail,uphone,uip,upriority,priority,open_time,opened_by,rep_u,rep_g,is_real,is_private,email_upd,memo_upd,message_id,cc,lastupd_time) VALUES(%d,'%s',%d,'%s','%s','%d','%s','%s','%s','%s','%s',%d,%d,%d,'%s','%s','%s',%d,%d,%d,%d,'%s','%s',%d)",
         $tid,$randstr,$status,$title,$description,$cat3_id,$uuser,$uname,$uemail,$uphone,$uip,$upriority,$priority,$open_time,
         $opened_by." (IMPORTED)",$rep_u,
         $rep_g,0,0,1,0,"",$cc,$result['update_time']);
  echo "Ticket #".$tid." has been imported.\n";

  /* Let's add the notes if they exist.. */
  if(!empty($result['notes'])) {
    echo "Adding notes to ticket #".$tid."...\n";
    $body = $result['notes'];
    $msg_user = "(IMPORTED)";
    $msg_time = 0;
    squery("INSERT INTO karnaf_replies(tid,title,reply,r_by,r_time,r_from,ip) VALUES(%d,'%s','%s','%s',%d,'%s','%s')", $tid,
           $title, $body,
           "Guest", $msg_time, $msg_user, "(IMPORTED)");
  }

  /* Let's add the custom notes if they exist.. */
  if(!empty($result['cust_notes'])) {
    echo "Adding custom notes to ticket #".$tid."...\n";
    $body = "Custom notes: ".$result['cust_notes'];
    $msg_user = "(IMPORTED)";
    $msg_time = 0;
    squery("INSERT INTO karnaf_replies(tid,title,reply,r_by,r_time,r_from,ip) VALUES(%d,'%s','%s','%s',%d,'%s','%s')", $tid,
           "Custom Notes", $body,
           "Guest", $msg_time, $msg_user, "(IMPORTED)");
  }

  /* Let's check replies too... */
  $query2 = squery("SELECT UNIX_TIMESTAMP(msg_time) AS msg_time,from_user,subject,msg_body,to_user,cc_user FROM sysaid.service_req_msg WHERE id=%d ORDER BY msg_time", $tid);
  while($result2 = sql_fetch_array($query2)) {
    echo "Adding reply to ticket #".$tid."...\n";
    $body = $result2['msg_body'];
    $body = str_ireplace("<br>","\n",$body);
    $body = strip_tags($body);
    $msg_user = remove_before_slashes($result2['from_user']);
    $msg_time = $result2['msg_time'];
    $title = trim($result2['subject']);
    if($body == $description && $msg_user == $uuser) continue; /* Skip the first reply if it's exactly the same as the ticket description */
    if(!empty($result2['cc_user'])) $body = "CC: ".str_replace(",",", ",$result2['cc_user'])."\n".$body;
    if(!empty($result2['to_user'])) $body = "To: ".str_replace(",",", ",$result2['to_user'])."\n".$body;
    squery("INSERT INTO karnaf_replies(tid,title,reply,r_by,r_time,r_from,ip) VALUES(%d,'%s','%s','%s',%d,'%s','%s')", $tid,
           $title, $body,
           "Guest", $msg_time, $msg_user, "(IMPORTED)");
  }
  sql_free_result($query2);

  /* Check actions */
  $query2 = squery("SELECT UNIX_TIMESTAMP(log_time) AS log_time,user_name,log_description FROM sysaid.service_req_log WHERE service_req_id=%d", $tid);
  while($result2 = sql_fetch_array($query2)) {
    $msg_user = remove_before_slashes($result2['user_name']);
    if($msg_user == $uuser) continue; /* Skip ticket opener actions */
    $body = $result2['log_description'];
    $body = str_ireplace("<br>","\n",$body);
    $body = strip_tags($body);
    if(substr($body,0,1) == "\n") $body = substr($body,1);
    echo "Adding action to ticket #".$tid."...\n";
    squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'%s','%s','%s',%d,0,%d)",
           $tid, $body, $msg_user, "karnaf-imported", $result2['log_time'], 0);
  }
  sql_free_result($query2);

  /* Now let's add the solution if one exists.. */
  if(!empty($result['solution'])) {
    echo "Adding solution to ticket #".$tid."...\n";
    $body = "Solution: ".$result['solution'];
    $msg_user = "(IMPORTED)";
    $msg_time = (int)$result['close_time'];
    if($msg_time != 0) {
      squery("INSERT INTO karnaf_replies(tid,title,reply,r_by,r_time,r_from,ip) VALUES(%d,'%s','%s','%s',%d,'%s','%s')", $tid,
             "Solution", $body,
             "Guest", $msg_time, $msg_user, "(IMPORTED)");
    }
  }

  /* Add the attachments */
  if(defined("KARNAF_UPLOAD_PATH") && KARNAF_UPLOAD_PATH!="") {
    $query2 = squery("SELECT UNIX_TIMESTAMP(file_date) AS file_date,file_name,file_content FROM sysaid.service_req_files WHERE id=%d", $tid);
    while($result2 = sql_fetch_array($query2)) {
      $file_name = $result2['file_name'];
      $file_ext = strtolower(substr($file_name,-4));
      $data = $result2['file_content'];
      if($file_ext == ".jpg") $file_type = "image/jpeg";
      else if($file_ext == ".png") $file_type = "image/png";
      else if($file_ext == ".gif") $file_type = "image/gif";
      else $file_type = "application/octet-stream";
      $file_desc = "Imported from SysAid";
      $file_size = 0;
      squery("INSERT INTO karnaf_files(tid,file_name,file_type,file_desc,file_size,lastupd_time) VALUES(%d,'%s','%s','%s',%d,%d)",
             $tid, $file_name, $file_type, $file_desc, $file_size, $result2['file_date']);
      $id = sql_insert_id();
      $fn = KARNAF_UPLOAD_PATH."/".$tid;
      if(!file_exists($fn)) {
        if(!mkdir($fn)) return "Can't create attachment directory!";
      }
      $fn .= "/".$id.$file_ext;
      if(($file = fopen($fn, "wb"))) {
        fwrite($file, $result2['file_content']);
        fclose($file);
      }
      /* Let's check the file's size and update the entry... */
      squery("UPDATE karnaf_files SET file_size=%d WHERE id=%d", filesize($fn), $id);
    }
    sql_free_result($query2);
  }
}
sql_free_result($query);
echo "Done.\n";
require_once("../contentpage_ftr.php");
?>
