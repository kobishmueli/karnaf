<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2019 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require("../ktools.php");
$cat3_id = $_GET['id'];
$extra = "";
$query = squery("SELECT id,name,extra FROM karnaf_cat3 WHERE id=%d", $cat3_id);
if($result = sql_fetch_array($query)) $extra = $result['extra'];
sql_free_result($query);
if(!empty($extra)) {
?>
<table width="100%" border="1">
<tr class="Karnaf_Head2"><td colspan="2">Extra Information</td></tr>
<?
  $i = 0;
  foreach(explode(',',$extra) as $row) {
  $i++;
?>
<tr>
<td><?=$row?>:</td>
<td><input name="ext<?=$i?>" type="text" size="50"></td>
</tr>
<?
  }
?>
</table>
<?
}
require_once("karnaf_footer.php");
?>
