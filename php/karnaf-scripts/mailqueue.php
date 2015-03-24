<?
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################
/* This is a script to send all the emails on the queue */

$override_magicquotes = 1;
require("../ktools.php");

function karnaf_email($mail_to, $mail_subject, $mail_body) {
  /* Was only used for debugging:
      $mail_to = "kobi@macron.co.il";
  */
  mail($mail_to, $mail_subject, $mail_body,
       "From: ".MY_EMAIL."\r\n" .
       "Reply-To: ".MY_EMAIL);
}

$query = squery("SELECT id,mail_to,mail_from,mail_subject,mail_body FROM mail_queue ORDER BY id");
while($result = sql_fetch_array($query)) {
  echo "Sending #".$result['id']." to ".$result['mail_to']."...\n";
  karnaf_email($result['mail_to'], $result['mail_subject'], $result['mail_body']);
  squery("DELETE FROM mail_queue WHERE id=%d", $result['id']);
}
sql_free_result($query);
echo "Done.\n";

require_once("../contentpage_ftr.php");
?>
