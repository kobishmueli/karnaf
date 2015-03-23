<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require("../ktools.php");
check_auth();
?>
<select name="cat3" id="cat3" onChange="javascript:loadext(this.value);">
<option value="0">--Select--</option>
<?
  $cat2_id = $_GET['id'];
  $query = squery("SELECT id,name,allowed_group FROM karnaf_cat3 WHERE parent=%d ORDER BY priority,name", $cat2_id);
  while($result = sql_fetch_array($query)) {
    if(!empty($result['allowed_group']) && !IsGroupMember($result['allowed_group'])) continue;
?>
<option value="<?=$result['id']?>"><?=$result['name']?></option>
<?
  }
  sql_free_result($query);
?>
</select>
<?
require_once("karnaf_footer.php");
?>
