CREATE TABLE `group_members` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `group_id` int(10) unsigned NOT NULL DEFAULT '0',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `added_by` varchar(30) NOT NULL DEFAULT '',
  `added_time` int(11) unsigned NOT NULL DEFAULT '0',
  `reason` varchar(255) DEFAULT NULL,
  `level` tinyint(3) unsigned NOT NULL DEFAULT '0',
  UNIQUE KEY `id` (`id`),
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`)
);

CREATE TABLE `groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(30) NOT NULL DEFAULT '',
  `gdesc` varchar(255) NOT NULL DEFAULT '',
  `owner` int(11) NOT NULL DEFAULT '0',
  `flags` int(11) NOT NULL DEFAULT '0',
  `isteam` tinyint(1) NOT NULL DEFAULT '0',
  `private_actions` tinyint(1) NOT NULL DEFAULT '0',
  `iskarnaf` tinyint(1) NOT NULL DEFAULT '0',
  `autoforward` TEXT DEFAULT NULL,
  `assign_msg` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_actions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tid` int(11) NOT NULL DEFAULT '0',
  `is_private` tinyint(1) NOT NULL DEFAULT '0',
  `a_type` smallint(6) NOT NULL DEFAULT '0',
  `action` text NOT NULL,
  `a_time` bigint(20) NOT NULL DEFAULT '0',
  `a_by_u` text NOT NULL,
  `a_by_g` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tid` (`tid`)
);

CREATE TABLE `karnaf_cat1` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL DEFAULT '',
  `priority` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_cat2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL DEFAULT '',
  `priority` int(11) NOT NULL DEFAULT '0',
  `parent` int(11) NOT NULL DEFAULT '0',
  `allowed_group` text,
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_cat3` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL DEFAULT '',
  `priority` int(11) NOT NULL DEFAULT '0',
  `parent` int(11) NOT NULL DEFAULT '0',
  `default_priority` int(11) NOT NULL DEFAULT '0',
  `default_group` text NOT NULL,
  `extra` text,
  `allowed_group` text,
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_memo_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tonick` varchar(30) DEFAULT NULL,
  `memo` varchar(250) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_priorities` (
  `priority_id` int(11) NOT NULL DEFAULT '0',
  `priority_name` text NOT NULL
);

CREATE TABLE `karnaf_replies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tid` int(11) NOT NULL DEFAULT '0',
  `title` varchar(250) DEFAULT NULL,
  `reply` text NOT NULL,
  `r_time` bigint(20) NOT NULL DEFAULT '0',
  `r_by` varchar(50) NOT NULL DEFAULT '',
  `r_from` varchar(250) NOT NULL DEFAULT '',
  `ip` varchar(74) DEFAULT NULL,
  `message_id` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tid` (`tid`)
);

CREATE TABLE `karnaf_statuses` (
  `status_id` int(11) NOT NULL DEFAULT '0',
  `status_name` text NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`status_id`)
);

CREATE TABLE `karnaf_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL DEFAULT '0',
  `subject` varchar(250) NOT NULL DEFAULT '',
  `body` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `randcode` varchar(20) NOT NULL DEFAULT '',
  `status` int(11) NOT NULL DEFAULT '1',
  `title` varchar(250) DEFAULT NULL,
  `description` text NOT NULL,
  `cat3_id` int(11) NOT NULL DEFAULT '0',
  `unick` varchar(30) NOT NULL DEFAULT '',
  `ufullname` varchar(250) NOT NULL DEFAULT '',
  `uemail` varchar(250) NOT NULL DEFAULT '',
  `uphone` varchar(250) NOT NULL DEFAULT '',
  `ulocation` varchar(250) NOT NULL DEFAULT '',
  `uip` varchar(16) NOT NULL DEFAULT '',
  `upriority` int(11) NOT NULL DEFAULT '0',
  `priority` int(11) NOT NULL DEFAULT '0',
  `open_time` bigint(14) DEFAULT NULL,
  `close_time` bigint(14) DEFAULT NULL,
  `opened_by` text NOT NULL,
  `rep_u` varchar(30) NOT NULL DEFAULT '',
  `rep_g` varchar(30) NOT NULL DEFAULT '',
  `closed_by` varchar(30) NOT NULL DEFAULT '',
  `is_real` tinyint(1) NOT NULL DEFAULT '0',
  `is_private` tinyint(1) NOT NULL DEFAULT '0',
  `email_upd` tinyint(1) NOT NULL DEFAULT '0',
  `memo_upd` tinyint(1) NOT NULL DEFAULT '0',
  `ext1` varchar(250) DEFAULT NULL,
  `ext2` varchar(250) DEFAULT NULL,
  `ext3` varchar(250) DEFAULT NULL,
  `merged_to` int(11) NOT NULL DEFAULT '0',
  `cc` varchar(250) DEFAULT NULL,
  `lastupd_time` bigint(14) DEFAULT NULL,
  `message_id` varchar(250) DEFAULT NULL,
  `last_note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rep_u` (`rep_u`),
  KEY `status` (`status`),
  KEY `karnaf_tickets_unick` (`unick`),
  KEY `karnaf_tickets_uemail` (`uemail`),
  KEY `karnaf_tickets_rep_g` (`rep_g`),
  KEY `karnaf_tickets_ext1` (`ext1`),
  KEY `karnaf_tickets_merged_to` (`merged_to`)
);

CREATE TABLE `mail_queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mail_to` varchar(250) DEFAULT NULL,
  `mail_from` varchar(250) DEFAULT NULL,
  `mail_subject` varchar(250) DEFAULT NULL,
  `mail_body` text,
  PRIMARY KEY (`id`)
);

CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `user` varchar(30) NOT NULL,
  `pass` varchar(65) NOT NULL default '',
  `email` varchar(250) default NULL,
  `url` varchar(250) default NULL,
  `regtime` int(11) default NULL,
  `lasttime` int(11) default NULL,
  `lastseen_ip` varchar(15) NOT NULL default '',
  `operlev` tinyint(4) NOT NULL default '0',
  `flags` int(10) unsigned NOT NULL default '0',
  `options` int(10) unsigned NOT NULL default '0',
  `lasthost` varchar(255) default NULL,
  `timezone` int(11) default NULL,
  `avatar` varchar(30) default NULL,
  `signature` text,
  `newemail` varchar(250) default NULL,
  `fullname` varchar(250) default NULL,
  `fname` varchar(250) default NULL,
  `lname` varchar(250) default NULL,
  `department` varchar(250) default NULL,
  `team` varchar(250) default NULL,
  `title` varchar(250) default NULL,
  `phone` varchar(250) default NULL,
  `room` varchar(250) default NULL,
  `lastsync` int(11) default NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `user` (`user`)
);

CREATE TABLE `ws_logs` (
  `id` int(10) unsigned NOT NULL DEFAULT '0',
  `date` int(32) NOT NULL DEFAULT '0',
  `action` text,
  `user` varchar(255) DEFAULT NULL,
  `logtype` varchar(255) DEFAULT NULL,
  `ip` varchar(255) DEFAULT NULL
);

CREATE TABLE `karnaf_mail_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `host` varchar(250) NOT NULL DEFAULT '',
  `port` varchar(250) NOT NULL DEFAULT '',
  `user` varchar(250) NOT NULL DEFAULT '',
  `pass` varchar(250) NOT NULL DEFAULT '',
  `cat3_id` int(11) NOT NULL DEFAULT '0',
  `default_group` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_ldap_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `host` varchar(250) NOT NULL DEFAULT '',
  `user` varchar(250) NOT NULL DEFAULT '',
  `pass` varchar(250) NOT NULL DEFAULT '',
  `ou` text NOT NULL,
  `filter` text NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_files` (
  `id` int(11) NOT NULL auto_increment,
  `tid` int(11) NOT NULL default '0',
  `file_name` varchar(250) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_desc` varchar(250) NOT NULL,
  `file_path` varchar(250) NOT NULL,
  `file_size` int(11) NOT NULL,
  `lastupd_time` int(11) default NULL,
  PRIMARY KEY  (`id`)
);

CREATE TABLE `karnaf_mail_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(250) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `priority` int(11) NOT NULL DEFAULT '0',
  `rcpt_pattern` varchar(250) NOT NULL DEFAULT '',
  `to_pattern` varchar(250) NOT NULL DEFAULT '',
  `cc_pattern` varchar(250) NOT NULL DEFAULT '',
  `subject_pattern` varchar(250) NOT NULL DEFAULT '',
  `body_pattern` varchar(250) NOT NULL DEFAULT '',
  `stop_duplicates` tinyint(1) NOT NULL DEFAULT '0',
  `break` tinyint(1) NOT NULL DEFAULT '0',
  `set_priority` int(11) NOT NULL DEFAULT '0',
  `set_group` varchar(30) DEFAULT NULL,
  `set_extra` varchar(250) DEFAULT NULL,
  `set_cat3` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_debug` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tid` int(11) NOT NULL DEFAULT '0',
  `body` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tid` (`tid`)
);

CREATE TABLE `karnaf_sms_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `account_id` varchar(250) NOT NULL DEFAULT '',
  `account_token` varchar(250) NOT NULL DEFAULT '',
  `from_number` varchar(250) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
);

CREATE TABLE `karnaf_schema` (
  `version` varchar(30) NOT NULL DEFAULT ''
);

INSERT INTO `karnaf_schema` VALUES ('1');
INSERT INTO `karnaf_schema` VALUES ('2');
INSERT INTO `karnaf_schema` VALUES ('3');
INSERT INTO `karnaf_schema` VALUES ('4');
INSERT INTO `karnaf_schema` VALUES ('5');
INSERT INTO `karnaf_schema` VALUES ('6');
INSERT INTO `karnaf_schema` VALUES ('7');
INSERT INTO `karnaf_schema` VALUES ('8');
INSERT INTO `karnaf_schema` VALUES ('9');

INSERT INTO `karnaf_priorities` VALUES (-1,'Low');
INSERT INTO `karnaf_priorities` VALUES (0,'Normal');
INSERT INTO `karnaf_priorities` VALUES (10,'Above Normal');
INSERT INTO `karnaf_priorities` VALUES (20,'High');
INSERT INTO `karnaf_priorities` VALUES (30,'Critical');

INSERT INTO `karnaf_statuses` VALUES (0,'Closed',1);
INSERT INTO `karnaf_statuses` VALUES (1,'Opened',0);
INSERT INTO `karnaf_statuses` VALUES (2,'Opened - Waiting for user reply',0);
INSERT INTO `karnaf_statuses` VALUES (3,'Held',0);
INSERT INTO `karnaf_statuses` VALUES (4,'Held - Possible spam',0);
INSERT INTO `karnaf_statuses` VALUES (5,'Spam',1);

INSERT INTO `users` VALUES (1,'admin','098f6bcd4621d373cade4e832627b4f6','admin@nonstop.co.il','',1426820239,1427066785,'',80,0,0,'127.0.0.1',0,'','','','System Administrator');
