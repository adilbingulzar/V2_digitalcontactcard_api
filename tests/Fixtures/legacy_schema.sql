/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `key` varchar(40) NOT NULL,
  `level` int NOT NULL,
  `ignore_limits` tinyint(1) NOT NULL DEFAULT '0',
  `is_private_key` tinyint(1) NOT NULL DEFAULT '0',
  `ip_addresses` text,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_agreement` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bizid` int NOT NULL,
  `userid` int NOT NULL,
  `header` text NOT NULL,
  `content` text NOT NULL,
  `percentage` text NOT NULL COMMENT 'level and percent number in json',
  `is_default` int NOT NULL DEFAULT '0',
  `is_spot` int NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_app_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fees` float NOT NULL,
  `fees_percent` float NOT NULL,
  `support_url` text NOT NULL,
  `support_email` text NOT NULL,
  `term_conditions` text NOT NULL,
  `business_subscription` int NOT NULL DEFAULT '0',
  `user_subscription` int NOT NULL DEFAULT '0',
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_business_roll` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bizid` int NOT NULL,
  `roll_name` varchar(150) NOT NULL,
  `level` int NOT NULL,
  `tip` int NOT NULL COMMENT 'eligible for pay tips ? 0 = false 1 = true',
  `parent_id` int NOT NULL,
  `status` int NOT NULL,
  `modified` int NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cl_bank_account` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_name` varchar(175) NOT NULL,
  `account_type` int NOT NULL DEFAULT '1' COMMENT 'current / personal',
  `swift_code` text NOT NULL,
  `account_number` text NOT NULL,
  `modified` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cl_business` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bname` varchar(175) NOT NULL,
  `bemail` varchar(175) NOT NULL,
  `baddress` text NOT NULL,
  `owner_id` int NOT NULL,
  `bizcode` text NOT NULL,
  `tiqs_vendor_id` text NOT NULL,
  `default_agrid` int NOT NULL DEFAULT '0',
  `status` int NOT NULL DEFAULT '1',
  `modified` int NOT NULL,
  `created` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cl_employee` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bizid` int NOT NULL,
  `empid` int NOT NULL,
  `request_from` varchar(12) NOT NULL COMMENT 'request from business/user',
  `roll` varchar(25) NOT NULL,
  `live` int NOT NULL DEFAULT '0',
  `last_live` int NOT NULL,
  `agreement_id` int NOT NULL DEFAULT '0',
  `reason` text NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cl_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `user_status` text NOT NULL,
  `user_image` text NOT NULL,
  `user_uri` varchar(25) NOT NULL,
  `created_on` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cl_spot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `spotname` text NOT NULL,
  `spoturi` text NOT NULL,
  `bizid` int NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `multispot` int NOT NULL,
  `modify` int NOT NULL,
  `created` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_cl_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fullname` varchar(75) NOT NULL,
  `bdate` text NOT NULL,
  `email` varchar(75) NOT NULL,
  `phone` varchar(25) NOT NULL,
  `country` varchar(10) NOT NULL,
  `countrycode` varchar(5) NOT NULL,
  `address` text NOT NULL,
  `password` varchar(255) NOT NULL,
  `roll` int NOT NULL DEFAULT '1',
  `playerid` text NOT NULL,
  `status` int NOT NULL DEFAULT '0',
  `subscription` int NOT NULL DEFAULT '0',
  `tip_account` int NOT NULL DEFAULT '0',
  `type` int NOT NULL DEFAULT '1',
  `created` int NOT NULL,
  `modified` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_confirm` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL DEFAULT '0',
  `authkey` text NOT NULL,
  `code` int NOT NULL,
  `for_key` varchar(15) NOT NULL DEFAULT '',
  `valid` int DEFAULT '0',
  `status` int NOT NULL,
  `time` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_email_template` (
  `id` int NOT NULL AUTO_INCREMENT,
  `temp_name` varchar(100) NOT NULL,
  `temp_subject` text NOT NULL,
  `temp_file` text NOT NULL,
  `temp_type` varchar(100) NOT NULL,
  `temp_unlayer` text NOT NULL,
  `temp_attach` text NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_fees_collection` (
  `id` int NOT NULL AUTO_INCREMENT,
  `from_user` int NOT NULL,
  `process_id` int NOT NULL,
  `token_key` text NOT NULL,
  `tip_amount` float NOT NULL,
  `fees_amount` float NOT NULL,
  `tax_amount` float NOT NULL,
  `fees_type` int NOT NULL DEFAULT '0',
  `ondate` text NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_fees_payout` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `w_amount` int NOT NULL,
  `payout_fees` int NOT NULL,
  `process_id` text NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_fees_user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `type` text NOT NULL,
  `option_for` text NOT NULL,
  `fees_price` float NOT NULL,
  `fees_percent` float NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_keys` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `key` varchar(40) NOT NULL,
  `level` int NOT NULL,
  `ignore_limits` tinyint(1) NOT NULL DEFAULT '0',
  `is_private_key` tinyint(1) NOT NULL DEFAULT '0',
  `ip_addresses` text,
  `date_created` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_language` (
  `id` int NOT NULL AUTO_INCREMENT,
  `langID` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `key` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `language` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'english',
  `text` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `controller` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `languagenumber` int NOT NULL,
  `vendorid` int NOT NULL,
  `createdAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`,`langID`) USING BTREE,
  KEY `language_ix` (`language`) USING BTREE,
  KEY `controller_ix` (`controller`) USING BTREE,
  KEY `langID` (`langID`) USING BTREE,
  KEY `Key` (`key`),
  KEY `text` (`text`(256))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci CHECKSUM=1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `bizid` int NOT NULL,
  `to_notify` text NOT NULL,
  `title` text NOT NULL,
  `message` text NOT NULL,
  `noti_link` text NOT NULL,
  `noti_type` text NOT NULL,
  `noti_icon` text NOT NULL,
  `is_read` int NOT NULL DEFAULT '0',
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_payment_methods` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `method` text NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_raiting` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `raiting` int NOT NULL,
  `buyer_email` varchar(125) NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_referral` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `r_userid` int NOT NULL,
  `r_amount` float NOT NULL,
  `status` int NOT NULL,
  `process` int NOT NULL DEFAULT '0',
  `process_id` text NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_review` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `raiting` int NOT NULL,
  `review` text NOT NULL,
  `buyer_email` varchar(125) NOT NULL,
  `tip` varchar(5) NOT NULL DEFAULT 'false',
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_setting_notification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `for_notify` varchar(55) NOT NULL,
  `for_sub` varchar(55) NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `bizid` int NOT NULL DEFAULT '0',
  `type` varchar(30) NOT NULL,
  `value` varchar(30) NOT NULL,
  `subvalue` varchar(30) NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_settings_profile` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int DEFAULT NULL,
  `profile_type` int NOT NULL DEFAULT '0' COMMENT 'spot/user',
  `page_header` text NOT NULL,
  `page_title` text NOT NULL,
  `theme` int DEFAULT '0',
  `back_image` text,
  `back_color` varchar(150) DEFAULT NULL,
  `bg_color` varchar(150) NOT NULL,
  `text_color` varchar(150) DEFAULT NULL,
  `emoji_color` varchar(150) DEFAULT NULL,
  `emoji_type` int NOT NULL DEFAULT '0',
  `button_color` text,
  `font_size` varchar(255) DEFAULT NULL,
  `emailbox` int DEFAULT '0',
  `reviewbox` int DEFAULT '0',
  `payampunt1` int DEFAULT NULL,
  `payampunt2` int DEFAULT NULL,
  `payampunt3` int DEFAULT NULL,
  `review_style` int NOT NULL DEFAULT '1',
  `sticky_review` int NOT NULL DEFAULT '1',
  `success_header` text NOT NULL,
  `success_message` text NOT NULL,
  `defaultpage` int NOT NULL,
  `status` int DEFAULT NULL,
  `time` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_settings_qr` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `header` text NOT NULL,
  `title` text NOT NULL,
  `qrcode` text NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_shift_hours` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empid` int NOT NULL,
  `bizid` int NOT NULL,
  `days` text NOT NULL,
  `hours` text NOT NULL,
  `foruse` text NOT NULL,
  `userid` int NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_shift_hours_spot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `spotid` int NOT NULL,
  `bizid` int NOT NULL,
  `days` text NOT NULL,
  `hours` text NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_spot_employee` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bizid` int NOT NULL,
  `spotid` int NOT NULL,
  `userid` int NOT NULL,
  `live` int NOT NULL,
  `roll` int NOT NULL,
  `agreement` int NOT NULL,
  `status` int NOT NULL,
  `modify` int NOT NULL,
  `created` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_subscription` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `amount` float NOT NULL,
  `orderId` text NOT NULL,
  `valid_from` text NOT NULL,
  `valid_till` text NOT NULL,
  `subscription_plan` varchar(25) NOT NULL,
  `active` int NOT NULL DEFAULT '0',
  `modify_by` int NOT NULL DEFAULT '0' COMMENT '0=user,1=admin',
  `process_id` text NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_tips_spot` (
  `id` int NOT NULL AUTO_INCREMENT,
  `spotid` int NOT NULL,
  `bizid` int NOT NULL DEFAULT '0',
  `client_id` int NOT NULL,
  `review_name` text NOT NULL,
  `buyer_email` varchar(125) NOT NULL,
  `orderId` varchar(34) NOT NULL,
  `trans_type` varchar(45) NOT NULL,
  `trans_amount` float NOT NULL,
  `trans_currency` varchar(11) NOT NULL,
  `trans_time` text NOT NULL,
  `tip_rating` float NOT NULL DEFAULT '3',
  `service_review` text NOT NULL,
  `sticky_review` int NOT NULL DEFAULT '0',
  `token_key` text NOT NULL,
  `user_ip` text NOT NULL,
  `status` int NOT NULL,
  `process` int NOT NULL DEFAULT '0',
  `process_id` varchar(60) NOT NULL DEFAULT '0',
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_touchtip` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `from_user` int NOT NULL COMMENT 'sharing tip of user id',
  `bizid` int NOT NULL DEFAULT '0',
  `review_name` text NOT NULL,
  `buyer_email` varchar(125) NOT NULL,
  `orderId` varchar(34) NOT NULL,
  `trans_type` varchar(45) NOT NULL,
  `trans_amount` float NOT NULL,
  `trans_currency` varchar(11) NOT NULL,
  `trans_time` text NOT NULL,
  `tip_rating` float NOT NULL DEFAULT '3',
  `service_review` text NOT NULL,
  `sticky_review` int NOT NULL DEFAULT '0',
  `token_key` text NOT NULL,
  `user_ip` text NOT NULL,
  `status` int NOT NULL,
  `process` int NOT NULL DEFAULT '0',
  `process_id` varchar(60) NOT NULL DEFAULT '0',
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_vcards` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `business_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `business_logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `business_title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `business_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `business_add` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `blatitude` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `blongitude` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `solinks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `googler_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ambassador_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `appontment_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `theme` int NOT NULL DEFAULT '1',
  `banner` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `is_bizname` int NOT NULL DEFAULT '0',
  `is_blocation` int NOT NULL DEFAULT '0',
  `is_bizours` int NOT NULL DEFAULT '0',
  `is_review` int NOT NULL DEFAULT '0',
  `is_bizicon` int NOT NULL DEFAULT '0',
  `is_emailicon` int NOT NULL DEFAULT '0',
  `is_phoneicon` int NOT NULL DEFAULT '0',
  `is_addressicon` int NOT NULL DEFAULT '0',
  `googlewallet_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ioswallet_link` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_vcards_theme` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int NOT NULL,
  `pbgcolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sbgcolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ptextcolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `stextcolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pbtncolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sbtncolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `pbtntextcolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `sbtntextcolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `brdcolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `rtncolor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `status` int NOT NULL,
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_visitor_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `page_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `log_type` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `ip_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `activity` int NOT NULL,
  `time` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_widget_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bizid` int NOT NULL,
  `width` int NOT NULL,
  `height` int NOT NULL,
  `scrollable` int NOT NULL DEFAULT '0',
  `theme` int NOT NULL DEFAULT '0',
  `framesize` text NOT NULL,
  `for_widget` text NOT NULL,
  `status` int NOT NULL DEFAULT '1',
  `time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tbl_withdrawal` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `w_type` int NOT NULL DEFAULT '0',
  `w_amount` float NOT NULL DEFAULT '0',
  `w_fees` float NOT NULL DEFAULT '0',
  `w_status` int NOT NULL,
  `process_id` varchar(60) NOT NULL DEFAULT '0',
  `w_date` text NOT NULL,
  `w_time` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;
