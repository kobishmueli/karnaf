<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2016 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

define("KARNAF_ADMINS_GROUP", "karnaf-admins");   // Members of the group have full access to the karnaf system
define("KARNAF_EDITORS_GROUP", "karnaf-editors"); // Members of the group can see tickets of other groups as long as they're not private
define("KARNAF_OPERS_GROUP", "karnaf-opers");     // Members of the group can manage their own team's tickets
define("KARNAF_AOB_GROUP", "dalnet-aob");         // Members of the group can add special actions to tickets
define("DB_HOST", "localhost");
define("DB_USER", "karnaf");
define("DB_PASS", "changeme");
define("DB_DB",   "karnaf");
define("MY_URL", "http://helpdesk.nonstop.co.il");
define("KARNAF_URL", "http://helpdesk.nonstop.co.il/karnaf");
define("MY_DOMAIN", "helpdesk.nonstop.co.il");
define("MY_EMAIL", "helpdesk@nonstop.co.il");
define("COOKIE_NICK", "i_user");
define("COOKIE_KEY", "i_key");
define("COOKIE_HASH", "XXXX");
define("KARNAF_DEFAULT_GROUP", "karnaf-helpdesk");
define("KARNAF_UPLOAD_PATH", "/var/www/karnaf/upload");
define("KARNAF_DEBUG", 0);
define("PSEUDO_GROUP", "karnaf-it-all");
?>
