<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("../ktools.php");
setcookie(COOKIE_NICK, "Guest", time()+(3600*24*14), "/", MY_DOMAIN);
setcookie(COOKIE_KEY, "", time()+(3600*24*14), "/", MY_DOMAIN);
setcookie(COOKIE_NICK, "", time()-60000, "/", MY_DOMAIN);
setcookie(COOKIE_KEY, "", time()-60000, "/", MY_DOMAIN);
$nologin = 1;
include_once("karnaf_header.php");
?>
<script language="JavaScript" type="text/javascript">
if (top.location != location) {
  top.location.href = document.location.href;
}
</script>
<?
echo "Thank you!";
include_once("karnaf_footer.php");
?>
