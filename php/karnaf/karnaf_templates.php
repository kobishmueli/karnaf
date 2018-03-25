<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2018 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require("../ktools.php");
check_auth();
$query = squery("SELECT g.name,t.body FROM (karnaf_templates AS t LEFT JOIN groups AS g ON g.id=t.group_id) WHERE t.id=%d", $_GET['id']);
while($result = sql_fetch_array($query)) {
  if((($result['name'] != PSEUDO_GROUP) || !IsKarnafOperSession()) && !IsGroupMember($result['name']) && !IsKarnafAdminSession()) continue;
  echo $result['body'];
}
sql_free_result($query);
require_once("karnaf_footer.php");
?>
