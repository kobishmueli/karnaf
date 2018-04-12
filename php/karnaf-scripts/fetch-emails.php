<?
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2018 Kobi Shmueli. #
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
  $fp = fopen("/tmp/karnaf-fetch-emails.lock", "a");
  if (!$fp || !flock($fp,LOCK_EX|LOCK_NB,$wb) || $wb) {
    safe_die("Error: Failed to acquire lock /tmp/karnaf-fetch-emails.lock!");
  }
}

/* Cache all the mail rules... */
$mail_rules = array();
$query = squery("SELECT name,priority,rcpt_pattern,to_pattern,cc_pattern,subject_pattern,body_pattern,stop_duplicates,break,set_priority,set_group,set_extra,set_cat3 FROM karnaf_mail_rules WHERE active=1 ORDER BY priority");
while($result = sql_fetch_array($query)) {
  $mail_rules[] = $result;
}
sql_free_result($query);

$query = squery("SELECT type,host,port,user,pass,cat3_id,default_group FROM karnaf_mail_accounts WHERE active=1");
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
      if(isset($overview->subject)) $m_subject = $overview->subject;
      else $m_subject = "";
      $m_msgid = $overview->message_id;
      if($overview->seen == 0) $m_new = 1;
      else $m_new = 0;
      if(isset($overview->in_reply_to)) $m_in_reply_to = $overview->in_reply_to;
      else $m_in_reply_to = "";
      if(isset($overview->references)) $m_references = $overview->references;
      else $m_references = "";
      echo "#{$m_id} ({$overview->date}) - From: {$m_from} {$m_msgid}\n";
      $headers = imap_fetchheader($mbox, $m_id);
      $header_info = imap_headerinfo($mbox, $m_id);
      $uname = "";
      $reply_to = "";
      $to = $header_info->toaddress;
      if(isset($header_info->ccaddress)) $cc = $header_info->ccaddress;
      else $cc = "";
      $tid = 0;
      $debug_body = "";
      $debug_only = 0;
      $maybespam = 0;
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
        if(preg_match("/^X-Priority: (.*)/", $header, $matches)) {
          if($matches[1] == "1") $upriority = 20; /* high priority */
          else if($matches[1] == "5") $upriority = -1; /* low priority */
          continue;
        }
        if(preg_match("/^X-Autoreply: (.*)/", $header, $matches)) $maybespam = 1;
        if(preg_match("/^X-Autorespond: (.*)/", $header, $matches)) $maybespam = 1;
        if(preg_match("/^auto-submitted: auto-generated/", $header, $matches)) $maybespam = 1;
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
                $debug_body .= "Found attachment by filename: ".$attachments[$i]['filename']."\n";
              }
            }
          }
          if($structure->parts[$i]->ifparameters) {
            foreach($structure->parts[$i]->parameters as $object) {
              if(strtolower($object->attribute) == "name") {
                $attachments[$i]['filename'] = $object->value;
                $debug_body .= "Found attachment by name: ".$attachments[$i]['filename']."\n";
              }
            }
          }
          if(($structure->parts[$i]->type == 5) && (!isset($attachments[$i]['filename']))) {
            $debug_body .= "Possible error: Found attachment by type with no name!\n";
          }
          if(isset($attachments[$i]['filename'])) {
            $attachments[$i]['data'] = imap_fetchbody($mbox, $m_id, $i+1);
            if($structure->parts[$i]->encoding == 3) $attachments[$i]['data'] = base64_decode($attachments[$i]['data']);
            if($structure->parts[$i]->encoding == 4) $attachments[$i]['data'] = quoted_printable_decode($attachments[$i]['data']);
          }
          if(isset($structure->parts[$i]->parts)) {
            for($y = 0; $y < count($structure->parts[$i]->parts); $y++) {
              if($structure->parts[$i]->parts[$y]->ifdparameters) {
                foreach($structure->parts[$i]->parts[$y]->dparameters as $object) {
                  if(strtolower($object->attribute) == "filename") {
                    $attachments[($i+$y*2)]['filename'] = $object->value;
                    $debug_body .= "Found sub-attachment by filename: ".$attachments[($i+$y*2)]['filename']."\n";
                  }
                }
              }
              if($structure->parts[$i]->parts[$y]->ifparameters) {
                foreach($structure->parts[$i]->parts[$y]->parameters as $object) {
                  if(strtolower($object->attribute) == "name") {
                    $attachments[($i+$y*2)]['filename'] = $object->value;
                    $debug_body .= "Found sub-attachment by name: ".$attachments[($i+$y*2)]['filename']."\n";
                  }
                }
              }
              if(isset($attachments[($i+$y*2)]['filename'])) {
                $attachments[($i+$y*2)]['data'] = imap_fetchbody($mbox, $m_id, ($i+1).".".($y));
                $debug_body .= "Adding sub-attachment ".($i+1).".".($y)." (size=".strlen($attachments[($i+$y*2)]['data']).")\n";
                if($structure->parts[$i]->parts[$y]->encoding == 3) $attachments[($i+$y*2)]['data'] = base64_decode($attachments[($i+$y*2)]['data']);
                if($structure->parts[$i]->parts[$y]->encoding == 4) $attachments[($i+$y*2)]['data'] = quoted_printable_decode($attachments[($i+$y*2)]['data']);
              }
            }
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
      if(strstr($m_subject, "=?WINDOWS-1252?") || strstr($m_subject, "=?GB2312?") || strstr($m_subject, "=?ISO-2022-JP?")) {
        $debug_body .= "M_Subject before imap_mime_header_decode=".$m_subject."\n";
        $arr = imap_mime_header_decode($m_subject);
        $m_subject = "";
        foreach($arr as $obj) {
          $m_subject .= $obj->text;
        }
        $debug_body .= "M_Subject after imap_mime_header_decode=".$m_subject."\n";
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
      if($tid == 0) {
        /* Let's try to find the ID from other ticket systems... */
        $stid = "";
        if(preg_match("/^.*(\[#[A-Z]{3}-\d{3}-\d+\]).*/", $m_subject, $matches)) $stid = $matches[1];
        #Amazon:
        if(preg_match("/^.*(case: \d+).*/", $m_subject, $matches)) $stid = $matches[1];
        if(preg_match("/^.*(\[Case \d+\]).*/", $m_subject, $matches)) $stid = $matches[1];
        if(preg_match("/^.*(Report \[\d+\]).*/", $m_subject, $matches)) $stid = $matches[1];
        if(preg_match("/^.*(Report \[\d+-\d\]).*/", $m_subject, $matches)) $stid = $matches[1];
        #10gbps.io:
        if(preg_match("/^.*(\[Support #\d+\]).*/", $m_subject, $matches)) $stid = $matches[1];
        #Purchasing Requests:
        if(preg_match("/^.*(PR #\d+).*/", $m_subject, $matches)) $stid = $matches[1];
        #Parallels:
        if(preg_match("/^.*(\[Parallels #\d+\]).*/", $m_subject, $matches)) $stid = $matches[1];
        if($stid != "") {
          $query2 = squery("SELECT id FROM karnaf_tickets WHERE status!=0 AND title LIKE '%s' ORDER BY id LIMIT 1", "%".$stid."%");
          if($result2 = sql_fetch_array($query2)) $tid = (int)$result2['id'];
          sql_free_result($query2);
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
      if(strstr($m_from,MY_EMAIL)) { echo "Deleting #".$m_id."...\n"; imap_delete($mbox, $m_id); continue; }
      if(strstr($m_from,"noreply")) continue;
      if(strstr($m_from,"no-reply")) { echo "Deleting #".$m_id."...\n"; imap_delete($mbox, $m_id); continue; }
      if(stristr($m_from,"users@dal.net")) continue;
      if(stristr($m_from,"Mailer-Daemon@")) { echo "Deleting #".$m_id."...\n"; imap_delete($mbox, $m_id); continue; }
      if(stristr($m_from,"newsletter@")) continue;
      if(stristr($m_from,"suggestions@DAL.NET")) continue;
      if(stristr($m_from,"helpdesk@dal.net")) continue;
      if(stristr($m_from,"cybercafe@dal.net")) continue;
      if(stristr($m_from,"operator@dal.net")) continue;
      if(stristr($m_from,"faq@dal.net")) continue;
      if(stristr($m_from,"operhelp@dal.net")) continue;
      if(stristr($m_from,"dalhelp@dal.net")) continue;
      if(stristr($m_from,"sabuse@dal.net")) continue;
      if(stristr($m_from,"nobody@google.com")) continue;
      if(stristr($m_from,"matthias.koch@melia.com")) continue;
      if(stristr($m_from,"@abbyy.com")) continue;
      if(stristr($m_from,"@moonfroglabs.com")) continue;
      if(stristr($m_from,"@lfb.org")) continue;
      if(stristr($m_from,"support@yesware.zendesk.com")) continue;
      if(stristr($m_from,"discounts@")) continue;
      if(strstr($m_subject,"Automatic reply:")) continue;
      if(strstr($m_subject,"Auto: ")) continue;
      if(strstr($m_subject,"Auto Reply:")) continue;
      if(strstr($m_subject,"Auto Reply :")) continue;
      /* Set default values for new tickets here before the mail rules... */
      $priority = 0;
      if(!isset($upriority)) $upriority = 0;
      /* If the user priority is *lower* than the system priority, we'll use the user priority */
      if($priority > $upriority) $priority = $upriority;
      if(!empty($result['default_group'])) $rep_g = $result['default_group'];
      else $rep_g = KARNAF_DEFAULT_GROUP;
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
        $query2 = squery("SELECT t.id,t.status,t.rep_u,t.uphone,t.rep_g,t.title,o.email AS oemail,t.merged_to,t.close_time FROM (karnaf_tickets AS t LEFT JOIN users as o ON t.rep_u=o.user) WHERE t.id=%d", $tid);
        if($result2 = sql_fetch_array($query2)) {
          if(!empty($result2['merged_to'])) {
            $tid = (int)$result2['merged_to'];
            sql_free_result($query2);
            $query2 = squery("SELECT t.id,t.status,t.rep_u,t.uphone,t.rep_g,t.title,o.email AS oemail,t.merged_to,t.close_time FROM (karnaf_tickets AS t LEFT JOIN users as o ON t.rep_u=o.user) WHERE t.id=%d", $tid);
            if(!($result2 = sql_fetch_array($query2))) $tid = 0;
          }
          if($tid && (int)$result2['status']==0 && (time() - (int)$result2['close_time']) >= 60*60*24*60) {
            /* If the ticket is closed for more than 60 days, we'll create a new ticket... */
            $tid = 0;
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
              squery("UPDATE karnaf_tickets SET status=1,lastupd_time=%d,newuserreply=1 WHERE id=%d", time(), $tid);
              send_memo($result2['rep_u'], "User has replied to ticket #".$result2['id'].". For more information visit: ".KARNAF_URL."/edit.php?id=".$result2['id']);
            }
            else squery("UPDATE karnaf_tickets SET lastupd_time=%d,newuserreply=1 WHERE id=%d", time(), $tid);
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
        if(strstr($m_subject,"We know you're busy.")) $status = 4;
        if(stristr($m_from,"info@")) $status = 4;
        if($maybespam == 1) $status = 4;
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

        if($rep_g==KARNAF_DEFAULT_GROUP && ($uteam=="US" || $uteam=="USA" || strstr($uteam,"US-") || strstr($uteam,"US -"))) $rep_g = "karnaf-it-usa";

        squery("INSERT INTO karnaf_tickets(randcode,status,title,description,cat3_id,unick,ufullname,uemail,uphone,ulocation,uip,upriority,priority,open_time,opened_by,rep_u,rep_g,is_real,is_private,email_upd,memo_upd,message_id,ext1,cc) VALUES('%s',%d,'%s','%s','%d','%s','%s','%s','%s','%s','%s',%d,%d,%d,'%s','%s','%s',%d,%d,%d,%d,'%s','%s','%s')",
           $randstr,$status,$m_subject,$m_body,$cat3_id,$unick,$uname,$reply_to,$uphone,$uteam,$uip,$upriority,$priority,time(),"(EMAIL)",$rep_u,
           $rep_g,0,0,1,0,$m_msgid,$extra,$cc);
        $tid = sql_insert_id();
        $reply = "Your ticket has been opened and we will take care of it as soon as possible.\r\n\r\n";
        $reply .= "Your Ticket ID: ".$tid."\r\nYour Verification Number: ".$randstr."\r\nThe ticket has been assigned to: ".$rep_g."\r\n";
        $reply .= "To view the ticket status: ".KARNAF_URL."/view.php?id=".$tid."&code=".$randstr."\r\n";

        /* Run custom code to change/remove the reply text if the custom_fetch_emails_reply() function exists: */
        if(function_exists("custom_fetch_emails_reply")) $reply = custom_fetch_emails_reply($tid, $reply, $rep_g, $randstr, $status, $m_subject, $m_from);

        if($status != 4 && $reply!="") {
          $reply_subject = "Re: [".strtoupper($rep_g)."] Ticket #".$tid;
          if(!empty($m_subject)) $reply_subject .= " - ".$m_subject;
          karnaf_email($reply_to, $reply_subject, $reply);
        }
        $query2 = squery("SELECT autoforward,set_private FROM groups WHERE name='%s'", $rep_g);
        if($result2 = sql_fetch_array($query2)) {
          if(!empty($result2['autoforward'])) {
            /* Automatically forward new tickets to the team... */
            $text = "New ticket from: ".$unick."\r\n\r\n";
            if($status == 4) $text .= "*** THIS EMAIL HAS BEEN MARKED AS SPAM ***\r\n\r\n";
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
          if((int)$result2['set_private'] == 1) squery("UPDATE karnaf_tickets SET is_private=1 WHERE id=%d", $tid);
        }
        sql_free_result($query2);
      }
      if(defined("KARNAF_DEBUG") && KARNAF_DEBUG==1 && !empty($debug_body)) {
        # Note to self: Yes, tid can be 0 here...
        squery("INSERT INTO karnaf_debug(tid,body) VALUES(%d,'%s')", $tid, $debug_body);
      }
      if($tid && isset($attachments) && count($attachments)>0) {
        /* We have attachment(s), let's add them to the ticket... */
        $debug_body .= "Found attachments: ".count($attachments)."\n";
        foreach($attachments as $attachment) {
          if(!isset($attachment['filename'])) continue; /* Skip empty attachments */
          $file_name = $attachment['filename'];
          if(strstr($file_name, "=?UTF-8?")) $file_name = imap_utf8($file_name);
          $file_desc = "Attachment by ".$uname;
          $file_size = mb_strlen($attachment['data']);
          $file_ext = strtolower(substr($file_name,-4));
          if($file_ext[0] != ".") $file_ext = strtolower(substr($file_name,-5));
          if($file_ext == ".jpg") $file_type = "image/jpeg";
          else if($file_ext == ".png") $file_type = "image/png";
          else if($file_ext == ".gif") $file_type = "image/gif";
          else $file_type = "application/octet-stream";
          if($file_ext!=".jpg" && $file_ext!=".png" && $file_ext!=".pdf" && $file_ext!=".log" && $file_ext!=".txt" && $file_ext!=".xls" &&
             $file_ext!=".xlsx" && $file_ext!=".doc" && $file_ext!=".docx" && $file_ext!=".xml" && $file_ext!=".gif") {
            echo "Skipping invalid attachment ".$file_ext." for ".$file_name."\r\n";
            continue; /* Skip invalid file extensions */
          }
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
fclose($fp);
unlink("/tmp/karnaf-fetch-emails.lock");
require_once("../contentpage_ftr.php");
?>
