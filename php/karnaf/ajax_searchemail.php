<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2018 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("../ktools.php");
check_auth();
CheckOperSession();

if(isset($_GET['term'])) {
  $term = "%".$_GET['term']."%";
  $query = squery("SELECT id,user,email,fullname FROM users WHERE user LIKE '%s' OR email LIKE '%s' OR fullname LIKE '%s'",
                  $term, $term, $term);
  while(($result = sql_fetch_array($query))) {
    $results[] = array('id' => $result['id'], "label" => $result['fullname']." - ".$result['email'], "value" => $result['email']);
  }
  sql_free_result($query);
}
echo json_encode($results);
require_once("karnaf_footer.php");
?>
