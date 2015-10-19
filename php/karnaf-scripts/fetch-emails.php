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

/* Cache all the mail rules... */
$mail_rules = array();
$query = squery("SELECT name,priority,rcpt_pattern,to_pattern,cc_pattern,subject_pattern,body_pattern,stop_duplicates,break,set_priority,set_group,set_extra,set_cat3 FROM karnaf_mail_rules WHERE active=1 ORDER BY priority");
while($result = sql_fetch_array($query)) {
  $mail_rules[] = $result;
}
sql_free_result($query);

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
    if($MC->Nmsgs == 0) continue; /* Skip empty mailboxes... */
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
      $debug_body = "";
      $debug_only = 0;
      foreach(explode("\n", $headers) as $header) {
        if(preg_match("/^Subject: (.*(#([\d,]+)).*)/", $header, $matches)) {
          $subject = $matches[1];
          if(isset($matches[3])) $tid = str_replace(",","",$matches[3]);
        }
        else if(preg_match("/^Subject: (.*(#(\d+)).*)/", $header, $matches)) {
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
        if(preg_match("/^[Cc]{2}: (.*)/", $header, $matches)) {
          $cc = $matches[1];
          continue;
        }
        if(preg_match("/^X-Priority: (.*)/", $header, $matches)) {
          if($matches[1] == "1") $upriority = 20; /* high priority */
          else if($matches[1] == "5") $upriority = -1; /* low priority */
          continue;
        }
        if(preg_match("/^X-Karnaf-Debug: 1/", $header, $matches)) {
          $debug_only = 1;
          continue;
        }
      }
      $structure = imap_fetchstructure($mbox, $m_id);
      $debug_body .= "structure->type=".$structure->type."\n";
      $debug_body .= "structure->encoding=".$structure->encoding."\n";
      if($structure->ifdescription) $debug_body .= "structure->description=".$structure->description."\n";
      if($structure->ifdisposition) $debug_body .= "structure->disposition=".$structure->disposition."\n";
      if($structure->type == 1) {
        # multi-part email
        $m_body = imap_fetchbody($mbox, $m_id, "1.1");
        if($m_body == "") {
          $m_body = imap_fetchbody($mbox, $m_id, "1");
          $debug_body .= "After imap_fetchbody 1 (encoding=".$structure->parts[1]->encoding.")\n";
          if($structure->parts[0]->encoding == 3 || $structure->parts[1]->encoding == 3) $m_body = base64_decode($m_body);
          else if($structure->parts[0]->encoding == 4 || $structure->parts[1]->encoding == 4) $m_body = quoted_printable_decode($m_body);
        }
        else if(isset($structure->parts[0]->parts[0])) {
          $debug_body .= "structure->parts[0]->parts[0]->encoding=".$structure->parts[0]->parts[0]->encoding."\n";
          if($structure->parts[0]->parts[0]->encoding == 3) $m_body = base64_decode($m_body);
          if($structure->parts[0]->parts[0]->encoding == 4) $m_body = quoted_printable_decode($m_body);
        }
        $debug_body .= "Body=".$m_body."\n";
        #Remove double spacing:
        $m_body = str_replace("\r\n\r\n","\r\n",$m_body);
        #For debugging:
        $debug_body .= "Type: multi-part\n";
      }
      else {
        # not multi-part email
        $m_body = imap_body($mbox, $m_id);
        if($structure->encoding == 3) $m_body = base64_decode($m_body);
        if($structure->encoding == 4) $m_body = quoted_printable_decode($m_body);
        #For debugging:
        $debug_body .= "Type: not-multi-part\n";
        $debug_body .= "Body=".$m_body."\n";
      }
      $attachments = array();
      if(isset($structure->parts)) {
        for($i = 0; $i < count($structure->parts); $i++) {
          $debug_body .= "parts[".$i."].encoding=".$structure->parts[$i]->encoding."\n";
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
      if(strstr($subject, "=?UTF-8?")) {
        $debug_body .= "Subject before imap_utf8(1)=".$subject."\n";
        $subject = imap_utf8($subject);
        $debug_body .= "Subject after imap_utf8(1)=".$subject."\n";
        if($tid == 0) {
          /* Let's try to catch the ticket ID again... */
          if(preg_match("/^.*(#([\d,]+)).*/", $subject, $matches)) {
            if(isset($matches[2])) $tid = str_replace(",","",$matches[2]);
          }
          else if(preg_match("/^.*(#(\d+).*)/", $subject, $matches)) {
            if(isset($matches[2])) $tid = $matches[2];
          }
        }
      }
      if(strstr($m_subject, "=?UTF-8?")) {
        $debug_body .= "M_Subject before imap_utf8(2)=".$m_subject."\n";
        $m_subject = imap_utf8($m_subject);
        $debug_body .= "M_Subject after imap_utf8(2)=".$m_subject."\n";
        if($tid == 0) {
          /* Let's try to catch the ticket ID again... */
          if(preg_match("/^.*(#([\d,]+)).*/", $m_subject, $matches)) {
            if(isset($matches[2])) $tid = str_replace(",","",$matches[2]);
          }
          else if(preg_match("/^.*(#(\d+).*)/", $m_subject, $matches)) {
            if(isset($matches[2])) $tid = $matches[2];
          }
        }
      }
      if(substr($m_body,0,1) == "\n") $m_body = substr($m_body,1);
      if(strstr($m_body,"<DEFANGED_DIV>")) $m_body = strip_tags($m_body);
      if(strstr($m_body,"<head>") && strstr($m_body,"<body") && strstr($m_body,"</body>") && strstr($m_body,"</html>")) {
        $m_body = strip_tags($m_body);
        $debug_body .= "Body after strip_tags=".$m_body."\n";
      }
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
      /* Set default values for new tickets here before the mail rules... */
      $priority = 0;
      if(!isset($upriority)) $upriority = 0;
      /* If the user priority is *lower* than the system priority, we'll use the user priority */
      if($priority > $upriority) $priority = $upriority;
      $rep_g = KARNAF_DEFAULT_GROUP;
      $cat3_id = $result['cat3_id'];
      $extra = "";
      foreach($mail_rules as $mail_rule) {
        $special_vars = array();
        if(!empty($mail_rule['rcpt_pattern']) && !preg_match("/".$mail_rule['rcpt_pattern']."/", $to, $matches) && !preg_match("/".$mail_rule['rcpt_pattern']."/", $cc, $matches)) continue;
        foreach($matches as $key => $value) {
          $special_vars['RCPT'.$key] = $value;
        }
        if(!empty($mail_rule['to_pattern']) && !preg_match("/".$mail_rule['to_pattern']."/", $to, $matches)) continue;
        foreach($matches as $key => $value) {
          $special_vars['TO'.$key] = $value;
        }
        if(!empty($mail_rule['cc_pattern']) && !preg_match("/".$mail_rule['to_pattern']."/", $cc, $matches)) continue;
        foreach($matches as $key => $value) {
          $special_vars['CC'.$key] = $value;
        }
        if(!empty($mail_rule['subject_pattern']) && !preg_match("/".$mail_rule['subject_pattern']."/", $subject, $matches)) continue;
        foreach($matches as $key => $value) {
          $special_vars['SUBJECT'.$key] = $value;
        }
        if(!empty($mail_rule['body_pattern']) && !preg_match("/".$mail_rule['body_pattern']."/", $m_body, $matches)) continue;
        foreach($matches as $key => $value) {
          $special_vars['BODY'.$key] = $value;
        }
        /* If we're still here, we got a match... */
        if(!empty($mail_rule['set_group'])) $rep_g = $mail_rule['set_group'];
        if(!empty($mail_rule['set_priority'])) $priority = (int)$mail_rule['set_priority'];
        if(!empty($mail_rule['set_extra'])) {
          $extra = $mail_rule['set_extra'];
          foreach($special_vars as $key => $value) {
            $extra = str_replace("%".$key."%", $value, $extra);
          }
        }
        if(!empty($mail_rule['set_cat3'])) $cat3_id = $mail_rule['set_cat3'];
        if((int)$mail_rule['stop_duplicates'] == 1) {
          $query2 = squery("SELECT tid FROM karnaf_replies WHERE status!=0 AND title='%s'", $subject);
          if($result2 = sql_fetch_array($query2)) {
            $tid = -999;
          }
          sql_free_result($query2);
        }
        if((int)$mail_rule['break'] == 1) break;
      }
      if($tid == -999) {
        /* Special case to skip duplicates... */
        imap_delete($mbox, $m_id);
        continue;
      }
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
      if($debug_only == 1) {
        echo "*** Debugging TID=".$tid." ***\n";
        echo $debug_body;
        echo "*** End of debugging ***\n";
        continue;
      }
      if($tid) {
        /* --- Ticket exists --- */
        /* Let's just verify the ticket really exists and isn't closed... */
        $query2 = squery("SELECT t.id,t.status,t.rep_u,t.uphone,t.rep_g,t.title,o.email AS oemail,t.merged_to FROM (karnaf_tickets AS t LEFT JOIN users as o ON t.rep_u=o.user) WHERE t.id=%d", $tid);
        if($result2 = sql_fetch_array($query2)) {
          if(!empty($result2['merged_to'])) {
            $tid = (int)$result2['merged_to'];
            sql_free_result($query2);
            $query2 = squery("SELECT t.id,t.status,t.rep_u,t.uphone,t.rep_g,t.title,o.email AS oemail,t.merged_to FROM (karnaf_tickets AS t LEFT JOIN users as o ON t.rep_u=o.user) WHERE t.id=%d", $tid);
            if(!($result2 = sql_fetch_array($query2))) $tid = 0;
          }
          if($tid) {
            if((int)$result2['status'] == 0) {
              squery("INSERT INTO karnaf_actions(tid,action,a_by_u,a_by_g,a_time,a_type,is_private) VALUES(%d,'The ticket has been re-opened.','%s','%s',%d,1,%d)",
                     $tid, "System", $result2['rep_g'], time(), 0);
               squery("UPDATE karnaf_tickets SET status=1,lastupd_time=%d WHERE id=%d", time(), $tid);
            }
            squery("INSERT INTO karnaf_replies(tid,title,reply,r_by,r_time,r_from,ip,message_id) VALUES(%d,'%s','%s','%s',%d,'%s','%s','%s')",
                   $tid, $m_subject, $m_body, "Guest", time(), $uname, "(EMAIL)", $m_msgid);
            if((int)$result2['status'] == 2) {
              squery("UPDATE karnaf_tickets SET status=1,lastupd_time=%d WHERE id=%d", time(), $tid);
              send_memo($result2['rep_u'], "User has replied to ticket #".$result2['id'].". For more information visit: ".KARNAF_URL."/edit.php?id=".$result2['id']);
            }
            else squery("UPDATE karnaf_tickets SET lastupd_time=%d WHERE id=%d", time(), $tid);
            $text = "New reply from: ".$uname."\r\n\r\n";
            $text .= "To edit the ticket: ".KARNAF_URL."/edit.php?id=".$tid."\r\n";
            $text .= "---------------------------------------------------------------------------------------------\r\n";
            $text .= "Sender: ".$uname." <".$reply_to.">\r\n";
            if(!empty($result2['uphone'])) $text .= "Phone: ".$result2['uphone']."\r\n";
            if(!empty($m_subject)) {
              $text .= "---------------------------------------------------------------------------------------------\r\n";
              $text .= "Title: ".$m_subject."\r\n";
            }
            $text .= "---------------------------------------------------------------------------------------------\r\n";
            $text .= "Body: ".$m_body."\r\n";
            $text .= "---------------------------------------------------------------------------------------------\r\n";
            $text .= "To edit the ticket: ".KARNAF_URL."/edit.php?id=".$tid."\r\n";
            $newsubject = "Re: [".strtoupper($result2['rep_g'])."] Ticket #".$tid;
            if(!empty($result2['title'])) $newsubject .= " - ".$result2['title'];
            if(empty($result2['rep_u'])) {
              $query3 = squery("SELECT autoforward FROM groups WHERE name='%s'", $result2['rep_g']);
              if($result3 = sql_fetch_array($query2)) {
                if(!empty($result3['autoforward'])) {
                  /* Automatically forward new replies to the team... */
                  karnaf_email($result3['autoforward'], $newsubject, $text);
                }
              }
              sql_free_result($query3);
            }
            else if(!empty($result2['oemail'])) {
              /* Automatically forward new replies to the operator... */
              karnaf_email($result2['oemail'], $newsubject, $text);
            }
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
        $udepartment = "";
        $uteam = "";
        $uroom = "";
        $uip = "";
        $rep_u = "";
        $status = 1;
        /* Spam checks */
        if(strstr($m_subject,"[SPAM]")) $status = 4;
        /* End of spam checks */
        if(($rep_g == KARNAF_DEFAULT_GROUP) && defined("IRC_MODE")) {
          if(!empty($cc)) $m_body = "CC: ".$cc."\n".$m_body;
          if(!empty($to)) $m_body = "To: ".$to."\n".$m_body;
        }

        /* Let's try to find the sender on our user database... */
        if(!empty($reply_to)) {
          $query2 = squery("SELECT user,fullname,phone,department,team,room FROM users WHERE email='%s'", $reply_to);
          if($result2 = sql_fetch_array($query2)) {
            $unick = $result2['user'];
            $uname = $result2['fullname'];
            $uphone = $result2['phone'];
            $udepartment = $result2['department'];
            $uteam = $result2['team'];
            $uroom = $result2['room'];
            if(empty($uteam) && !empty($udepartment)) $uteam = $udepartment;
          }
          sql_free_result($query2);
        }

        squery("INSERT INTO karnaf_tickets(randcode,status,title,description,cat3_id,unick,ufullname,uemail,uphone,ulocation,uip,upriority,priority,open_time,opened_by,rep_u,rep_g,is_real,is_private,email_upd,memo_upd,message_id,ext1,cc) VALUES('%s',%d,'%s','%s','%d','%s','%s','%s','%s','%s','%s',%d,%d,%d,'%s','%s','%s',%d,%d,%d,%d,'%s','%s','%s')",
           $randstr,$status,$m_subject,$m_body,$cat3_id,$unick,$uname,$reply_to,$uphone,$uteam,$uip,$upriority,$priority,time(),"(EMAIL)",$rep_u,
           $rep_g,0,0,1,0,$m_msgid,$extra,$cc);
        $tid = sql_insert_id();
        $reply = "Your ticket has been opened and we will take care of it as soon as possible.\r\n\r\n";
        $reply .= "Your Ticket ID: ".$tid."\r\nYour Verification Number: ".$randstr."\r\nThe ticket has been assigned to: ".$rep_g."\r\n";
        $reply .= "To view the ticket status: ".KARNAF_URL."/view.php?id=".$tid."&code=".$randstr."\r\n";
        if($status != 4) {
          $reply_subject = "Ticket #".$tid;
          if(!empty($m_subject)) $reply_subject .= " - ".$m_subject;
          karnaf_email($reply_to, $reply_subject, $reply);
        }
        $query2 = squery("SELECT autoforward FROM groups WHERE name='%s'", $rep_g);
        if($result2 = sql_fetch_array($query2)) {
          if(!empty($result2['autoforward'])) {
            /* Automatically forward new tickets to the team... */
            $text = "New ticket from: ".$unick."\r\n\r\n";
            $text .= "To edit the ticket: ".KARNAF_URL."/edit.php?id=".$tid."\r\n";
            $text .= "---------------------------------------------------------------------------------------------\r\n";
            $text .= "Sender: ".$uname." <".$reply_to.">\r\n";
            if(!empty($uphone)) $text .= "Phone: ".$uphone."\r\n";
            if(!empty($uteam) && $uteam!=$udepartment) $text .= "Team: ".$uteam."\r\n";
            if(!empty($udepartment)) $text .= "Department: ".$udepartment."\r\n";
            if(!empty($uroom)) $text .= "Room: ".$uroom."\r\n";
            if(!empty($m_subject)) $text .= "Title: ".$m_subject."\r\n";
            $text .= "---------------------------------------------------------------------------------------------\r\n";
            $text .= "Body: ".$m_body."\r\n";
            $text .= "---------------------------------------------------------------------------------------------\r\n";
            $text .= "To edit the ticket: ".KARNAF_URL."/edit.php?id=".$tid."\r\n";
            $newsubject = "[".strtoupper($rep_g)."] New Ticket #".$tid;
            if(!empty($m_subject)) $newsubject .= " - ".$m_subject;
            karnaf_email($result2['autoforward'], $newsubject, $text);
          }
        }
        sql_free_result($query2);
      }
      if(defined("KARNAF_DEBUG") && KARNAF_DEBUG==1 && !empty($debug_body)) {
        # Note to self: Yes, tid can be 0 here...
        squery("INSERT INTO karnaf_debug(tid,body) VALUES(%d,'%s')", $tid, $debug_body);
      }
      if($tid && isset($attachments) && count($attachments)>0) {
        /* We have attachment(s), let's add them to the ticket... */
        foreach($attachments as $attachment) {
          if(!isset($attachment['filename'])) continue; /* Skip empty attachments */
          $file_name = $attachment['filename'];
          $file_desc = "Attachment by ".$uname;
          $file_size = mb_strlen($attachment['data']);
          $file_ext = strtolower(substr($file_name,-4));
          if($file_ext[0] != ".") $file_ext = strtolower(substr($file_name,-5));
          if($file_ext == ".jpg") $file_type = "image/jpeg";
          else if($file_ext == ".png") $file_type = "image/png";
          else if($file_ext == ".gif") $file_type = "image/gif";
          else $file_type = "application/octet-stream";
          if($file_ext!=".jpg" && $file_ext!=".png" && $file_ext!=".pdf" && $file_ext!=".log" && $file_ext!=".txt" && $file_ext!=".xls" && $file_ext!=".xlsx") continue; /* Skip invalid file extensions */
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
