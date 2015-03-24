<?
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2015 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################
/* This is a script to delete the Karnaf database */

require("../ktools.php");

squery("DELETE FROM karnaf_tickets");
squery("DELETE FROM karnaf_replies");
squery("DELETE FROM karnaf_actions");
squery("DELETE FROM karnaf_files");
squery("ALTER TABLE karnaf_tickets AUTO_INCREMENT = 0");
squery("ALTER TABLE karnaf_replies AUTO_INCREMENT = 0");
squery("ALTER TABLE karnaf_actions AUTO_INCREMENT = 0");
squery("ALTER TABLE karnaf_files AUTO_INCREMENT = 0");

echo "Done.";

require_once("../contentpage_ftr.php");
?>
