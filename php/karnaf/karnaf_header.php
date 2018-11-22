<?php
##################################################################
# Karnaf HelpDesk System - Copyright (C) 2001-2018 Kobi Shmueli. #
# See the LICENSE file for more information.                     #
##################################################################

require_once("../ktools.php");
require_once("../contentpage_hdr.php");
?>
<style>
<? if(file_exists("karnaf.css")) echo file_get_contents("karnaf.css"); ?>
#maincontent a,a:link,a:visited {
  color: Blue;
}

#maincontent a:hover {
  background-color: Blue;
  color: White;
}

th {
  color: White;
  font-weight: bold;
  background-color: Black;
  text-align: center;
  padding: 3px 3px 3px 3px;
  font-family: "Arial";
}

td {
  padding-top: 5px;
  padding-bottom: 5px;
  padding-left: 2px;
}
.col1 {
  border: 2px solid black;
}
.col2 {
  background: rgb(237, 237, 237);
  border: 2px solid black;
}

.Karnaf_P_Normal,.Karnaf_P_Low,.Karnaf_P_High,.Karnaf_P_Critical,.Karnaf_P_Special,.Karnaf_P_Special2,.Karnaf_P_Special3,.Karnaf_P_Closed {
  border: 2px solid black;
}

table {
  border: none;
  border-collapse: separate;
  border-spacing: 2px 2px;
}

table.view_ticket_table {
  background: white;
  color: black;
}

.last_note {
  word-break: break-all;
}

#maincontent {
    padding-bottom: 50px;
}
.edit_ticket {
    color: white;
    font-size: 25px;
}
</style>
