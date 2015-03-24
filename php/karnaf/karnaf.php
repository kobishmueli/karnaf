<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require("../ktools.php");
check_auth();
?>
<html>
<head>
<title>Karnaf v<?=KARNAF_VERSION?></title>
</head>
<?
if(!$a_id) AccessDenied("");
else if(IsKarnafOperSession()) {
?>
<frameset border="0" cols="100,*">
<frame name="menu" src="menu.php" scrolling="no">
<frame name="main" src="mylist.php" scrolling="auto">
</frameset>
<? } else { ?>
<frameset border="0" cols="*">
<frame name="main" src="new.php" scrolling="auto">
</frameset>
<? } ?>
</html>
<? require_once("karnaf_footer.php"); ?>
