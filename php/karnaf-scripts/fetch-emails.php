<?
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################
/* This is a script to fetch emails from multiple mail accounts into Karnaf */

require("../ktools.php");

function karnaf_email($mail_to, $mail_subject, $mail_body) {
  /* Was only used for debugging:
      $mail_to = "kobi@macron.co.il";
  */
  mail($mail_to, $mail_subject, $mail_body,
       "From: ".MY_EMAIL."\r\n" .
       "Reply-To: ".MY_EMAIL);
}

if(!isset($argv[1]) || $argv[1]!="force") {
  if(file_exists("/tmp/karnaf-fetch-emails.lock")) {
    safe_die("Error: lock file exists!");
  }
}

$fp = fopen("/tmp/karnaf-fetch-emails.lock", "w");
fwrite($fp, "locked");
fclose($fp);

$query = squery("SELECT type,host,port,user,pass,cat3_id FROM karnaf_mail_accounts WHERE active=1");
while($result = sql_fetch_array($query)) {
  echo "Checking ".$result['host'].":".$result['port']."...\n";
  $type = (int)$result['type'];
  # Types:
  # 0 = POP3
  # 1 = IMAP
  # 2 = POP3/SSL
  # 3 = IMAP/SSL
  if($type == 0) $authhost = "{".$result['host'].":".$result['port']."/pop3}";
  else if($type == 1) $authhost = "{".$result['host'].":".$result['port']."/imap}";
  else if($type == 2) $authhost = "{".$result['host'].":".$result['port']."/pop3/ssl}";
  else if($type == 3) $authhost = "{".$result['host'].":".$result['port']."/imap/ssl}";
  else {
    echo "Error: unknown mail account type. Skipping...\n";
    continue;
  }
  if($mbox = imap_open($authhost."INBOX",$result['user'],$result['pass'])) {
    $MC = imap_check($mbox);
    $iresult = imap_fetch_overview($mbox,"1:{$MC->Nmsgs}",0);
    foreach ($iresult as $overview) {
      $m_id = $overview->msgno;
      $m_from = $overview->from;
      $m_subject = $overview->subject;
      $m_msgid = $overview->message_id;
      if($overview->seen == 0) $m_new = 1;
      else $m_new = 0;
      if(isset($overview->in_reply_to)) $m_in_reply_to = $overview->in_reply_to;
      else $m_in_reply_to = "";
      if(isset($overview->references)) $m_references = $overview->references;
      else $m_references = "";
      echo "#{$m_id} ({$overview->date}) - From: {$m_from} {$m_msgid}\n";
      $headers = imap_fetchheader($mbox, $m_id);
      $uname = "";
      $reply_to = "";
      $to = "";
      $cc = "";
      $tid = 0;
      foreach(explode("\n", $headers) as $header) {
        if(preg_match("/^Subject: (.*(#(\d+)).*)/", $header, $matches)) {
          $subject = $matches[1];
          if(isset($matches[3])) $tid = $matches[3];
        }
        else if(preg_match("/^Subject: (.*)/", $header, $matches)) {
          $subject = $matches[1];
        }
        if(preg_match("/^From: (.*(<(.*)>))/", $header, $matches)) {
          $uname = $matches[1];
          if(isset($matches[3])) $reply_to = $matches[3];
        }
        else if(preg_match("/^From: (.*)/", $header, $matches)) {
          $uname = $matches[1];
        }
        if(preg_match("/^Reply-to: (.*(<(.*)>))/", $header, $matches)) {
          $reply_to = $matches[1];
          if(isset($matches[3])) $reply_to = $matches[3];
        }
        else if(preg_match("/^Reply-to: (.*)/", $header, $matches)) {
          $reply_to = $matches[1];
        }
        if(preg_match("/^To: (.*)/", $header, $matches)) {
          $to = $matches[1];
          continue;
        }
        if(preg_match("/^CC: (.*)/", $header, $matches)) {
          $cc = $matches[1];
          continue;
        }
        if(preg_match("/^X-Priority: (.*)/", $header, $matches)) {
          if($matches[1] == "1") $upriority = 20; /* high priority */
          else if($matches[1] == "5") $upriority = -1; /* low priority */
          continue;
        }
      }
      $structure = imap_fetchstructure($mbox, $m_id);
      if($structure->type == 1) {
        # multi-part email
        $m_body = imap_fetchbody($mbox, $m_id, "1.1");
        if($m_body == "") $m_body = imap_fetchbody($mbox, $m_id, "1");
        $m_body = quoted_printable_decode($m_body);
        #For debugging:
        #$m_body = "Type: multi-part\n".$m_body;
      }
      else {
        # not multi-part email
        $m_body = imap_body($mbox, $m_id);
        #For debugging:
        #$m_body = "Type: not-multi-part\n".$m_body;
      }
      $attachments = array();
      if(isset($structure->parts)) {
        for($i = 0; $i < count($structure->parts); $i++) {
          $attachments[$i] = array();
          if($structure->parts[$i]->ifdparameters) {
            foreach($structure->parts[$i]->dparameters as $object) {
              if(strtolower($object->attribute) == "filename") {
                $attachments[$i]['filename'] = $object->value;
              }
            }
          }
          if($structure->parts[$i]->ifparameters) {
            foreach($structure->parts[$i]->parameters as $object) {
              if(strtolower($object->attribute) == "name") {
                $attachments[$i]['filename'] = $object->value;
              }
            }
          }
          if(isset($attachments[$i]['filename'])) {
            $attachments[$i]['data'] = imap_fetchbody($mbox, $m_id, $i+1);
            if($structure->parts[$i]->encoding == 3) $attachments[$i]['data'] = base64_decode($attachments[$i]['data']);
            if($structure->parts[$i]->encoding == 4) $attachments[$i]['data'] = quoted_printable_decode($attachments[$i]['data']);
          }
        }
      }
      if(substr($m_body,0,1) == "\n") $m_body = substr($m_body,1);
      if(!empty($m_subject)) $m_body = "Subject: ".$m_subject."\n".$m_body;
      if(strstr($m_body,"<DEFANGED_DIV>")) $m_body = strip_tags($m_body);
      if(preg_match("/^(.*(<(.*)>))/", $m_from, $matches)) {
        $uname = $matches[1];
        if(isset($matches[3])) $reply_to = $matches[3];
      }
      else if(preg_match("/^(.*)/", $m_from, $matches)) {
        $uname = $matches[1];
      }
      if(strstr($m_from,"noreply")) continue;
      if(strstr($m_from,"no-reply")) continue;
      if(stristr($m_from,"users@dal.net")) continue;
      if(stristr($m_from,"Mailer-Daemon@")) continue;
      if(stristr($m_from,"suggestions@DAL.NET")) continue;
      if(stristr($m_from,"helpdesk@dal.net")) continue;
      if(stristr($m_from,"cybercafe@dal.net")) continue;
      if(stristr($m_from,"operator@dal.net")) continue;
      if(stristr($m_from,"faq@dal.net")) continue;
      if(stristr($m_from,"operhelp@dal.net")) continue;
      if(stristr($m_from,"dalhelp@dal.net")) continue;
      if(stristr($m_from,"sabuse@dal.net")) continue;
      if(!$tid && $m_in_reply_to!="") {
        /* If we didn't find the ticket ID so far, let's try to find its email's reply-to message id... */
        $query2 = squery("SELECT id FROM karnaf_tickets WHERE status!=0 AND message_id='%s'", $m_in_reply_to);
        if($result2 = sql_fetch_array($query2)) {
          $tid = (int)$result2['id'];
        }
        sql_free_result($query2);
      }
      if(!$tid && $m_in_reply_to!="") {
        /* If we (still) didn't find the ticket ID, let's try to find the reply-to message id in previous replies */
        $query2 = squery("SELECT tid FROM karnaf_replies WHERE message_id='%s'", $m_in_reply_to);
        if($result2 = sql_fetch_array($query2)) {
          $tid = (int)$result2['tid'];
        }
        sql_free_result($query2);
      }
      if(!$tid && $m_references!="") {
        /* If we (still) didn't find the ticket ID, let's try to find one of the references in tickets... */
        foreach(explode(" ", $m_references) as $m_ref) {
          $query2 = squery("SELECT id FROM karnaf_tickets WHERE status!=0 AND message_id='%s'", $m_ref);
          if($result2 = sql_fetch_array($query2)) {
            $tid = (int)$result2['id'];
          }
          sql_free_result($query2);
        }
      }
      if(!$tid && $m_references!="") {
        /* If we (still) didn't find the ticket ID, let's try to find one of the references in replies... */
        foreach(explode(" ", $m_references) as $m_ref) {
          $query2 = squery("SELECT tid FROM karnaf_replies WHERE message_id='%s'", $m_ref);
          if($result2 = sql_fetch_array($query2)) {
            $tid = (int)$result2['tid'];
          }
          sql_free_result($query2);
        }
      }
      if(!$tid) {
        /* This really shouldn't happen but let's try to find the message id too... */
        $query2 = squery("SELECT id FROM karnaf_tickets WHERE status!=0 AND message_id='%s'", $m_msgid);
        if($result2 = sql_fetch_array($query2)) {
          $tid = (int)$result2['id'];
        }
        sql_free_result($query2);
      }
      if($tid) {
        /* --- Ticket exists --- */
        /* Let's just verify the ticket really exists and isn't closed... */
        $query2 = squery("SELECT id,status,rep_u FROM karnaf_tickets WHERE id=%d", $tid);
        if($result2 = sql_fetch_array($query2)) {
          if((int)$result2['status'] == 0) {
            karnaf_email($reply_to, "Ticket #".$tid, "We are sorry, the ticket is already closed and you can't add new replies to it.");
          }
          else {
            squery("INSERT INTO karnaf_replies(tid,reply,r_by,r_time,r_from,ip,message_id) VALUES(%d,'%s','%s',%d,'%s','%s','%s')",
                   $tid, $m_body, "Guest", time(), $uname, "(EMAIL)", $m_msgid);
            if((int)$result2['status'] == 2) {
              squery("UPDATE karnaf_tickets SET status=1,lastupd_time=%d WHERE id=%d", time(), $tid);
              send_memo($result['rep_u'], "User has replied to ticket #".$result2['id'].". For more information visit: ".KARNAF_URL."/edit.php?id=".$result2['id']);
            }
            else squery("UPDATE karnaf_tickets SET lastupd_time=%d WHERE id=%d", time(), $tid);
          }
        }
        else $tid = 0; /* We tried to add a reply to a non-existing ticket, let's create a new ticket instead... */
        sql_free_result($query2);
      }
      if(!$tid) {
        /* --- New ticket --- */
        $randstr = RandomNumber(10);
        $unick = "Guest";
        $uphone = "";
        $uip = "";
        $priority = 0;
        if(!isset($upriority)) $upriority = 0;
        /* If the user priority is *lower* than the system priority, we'll use the user priority */
        if($priority > $upriority) $priority = $upriority;
        $rep_u = "";
        $status = 1;
        $rep_g = KARNAF_DEFAULT_GROUP;
        $cat3_id = $result['cat3_id'];
        /* Spam checks */
        if(strstr($m_subject,"[SPAM]")) $status = 4;
        /* End of spam checks */
        if($rep_g == KARNAF_DEFAULT_GROUP) {
          if(!empty($cc)) $m_body = "CC: ".$cc."\n".$m_body;
          if(!empty($to)) $m_body = "To: ".$to."\n".$m_body;
        }
        squery("INSERT INTO karnaf_tickets(randcode,status,description,cat3_id,unick,ufullname,uemail,uphone,uip,upriority,priority,open_time,opened_by,rep_u,rep_g,is_real,is_private,email_upd,memo_upd,message_id) VALUES('%s',%d,'%s','%d','%s','%s','%s','%s','%s',%d,%d,%d,'%s','%s','%s',%d,%d,%d,%d,'%s')",
           $randstr,$status,$m_body,$cat3_id,$unick,$uname,$reply_to,$uphone,$uip,$upriority,$priority,time(),"(EMAIL)",$rep_u,
           $rep_g,0,0,1,0,$m_msgid);
        $tid = sql_insert_id();
        $reply = "Your ticket has been opened and we will take care of it as soon as possible.\r\n\r\n";
        $reply .= "Your Ticket ID: ".$tid."\r\nYour Verification Number: ".$randstr."\r\nThe ticket has been assigned to: ".$rep_g."\r\n";
        $reply .= "To view the ticket status: ".KARNAF_URL."/view.php?id=".$tid."&code=".$randstr."\r\n";
        if($status != 4) karnaf_email($reply_to, "Ticket #".$tid, $reply);
        $query2 = squery("SELECT autoforward FROM groups WHERE name='%s'", $rep_g);
        if($result2 = sql_fetch_array($query2)) {
          if(!empty($result2['autoforward'])) {
            /* Automatically forward new tickets to the team... */
            $text = "New ticket from: ".$unick."\r\n\r\n";
            $text .= "To edit the ticket: ".KARNAF_URL."/edit.php?id=".$tid."\r\n";
            $text = "Body: ".$m_body."\r\n";
            $text .= "To edit the ticket: ".KARNAF_URL."/edit.php?id=".$tid."\r\n";
            karnaf_email($result2['autoforward'], "[".$rep_g."] New Ticket #".$tid, $text);
          }
        }
        sql_free_result($query2);
      }
      if($tid && isset($attachments) && count($attachments)>0) {
        /* We have attachment(s), let's add them to the ticket... */
        foreach($attachments as $attachment) {
          if(!isset($attachment['filename'])) continue; /* Skip empty attachments */
          $file_name = $attachment['filename'];
          $file_desc = "Attachment by ".$uname;
          $file_size = mb_strlen($attachment['data']);
          $file_ext = strtolower(substr($file_name,-4));
          if($file_ext == ".jpg") $file_type = "image/jpeg";
          else if($file_ext == ".png") $file_type = "image/png";
          else if($file_ext == ".gif") $file_type = "image/gif";
          else $file_type = "application/octet-stream";
          if($file_ext!=".jpg" && $file_ext!=".png" && $file_ext!=".pdf" && $file_ext!=".log" && $file_ext!=".txt") continue; /* Skip invalid file extensions */
          squery("INSERT INTO karnaf_files(tid,file_name,file_type,file_desc,file_size,lastupd_time) VALUES(%d,'%s','%s','%s',%d,%d)",
                 $tid, $file_name, $file_type, $file_desc, $file_size, time());
          $id = sql_insert_id();
          $fn = KARNAF_UPLOAD_PATH."/".$tid;
          if(!file_exists($fn)) {
            if(!mkdir($fn)) continue; /* Error: can't make directory! */ 
          }
          $fn .= "/".$id.$file_ext;
          if(($file = fopen($fn, "wb"))) {
            fwrite($file, $attachment['data']);
            fclose($file);
          }
        }
      }
      imap_delete($mbox, $m_id);
    }
    imap_close($mbox);
    echo "Done.\n";
  }
  else echo "Couldn't open mailbox! Error: ".imap_last_error()."\n";
}
sql_free_result($query);
unlink("/tmp/karnaf-fetch-emails.lock");
require_once("../contentpage_ftr.php");
?>
