<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

if(isset($themepath)) {
  load_scripts();
  echo "<!-- Footer Here -->\n";
  load_template($themepath."/footer.html");
}
if(isset($connection)) mysql_close($connection);
?>

