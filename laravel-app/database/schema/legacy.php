<?php

// Exact schema-only DDL from the immutable export. No record data.
return [
    'ActivityLogs' => 'CREATE TABLE `ActivityLogs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` text NOT NULL,
  `timestamp` datetime NOT NULL,
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=216 DEFAULT CHARSET=latin1;',
    'ContractorServices' => 'CREATE TABLE `ContractorServices` (
  `user_id` int NOT NULL,
  `service_id` int NOT NULL,
  PRIMARY KEY (`user_id`,`service_id`),
  KEY `service_id` (`service_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;',
    'Files' => 'CREATE TABLE `Files` (
  `file_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `unique_file_id` varchar(32) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_data` longblob NOT NULL,
  `mime_type` varchar(50) NOT NULL,
  `upload_date` datetime NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `archived` tinyint(1) NOT NULL,
  PRIMARY KEY (`file_id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;',
    'Job_Media' => 'CREATE TABLE `Job_Media` (
  `media_id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_data` mediumblob NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `tag` enum(\'Before\',\'During\',\'After\',\'Other\') NOT NULL DEFAULT \'Other\',
  `upload_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` int NOT NULL,
  PRIMARY KEY (`media_id`),
  KEY `job_id` (`job_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=MyISAM AUTO_INCREMENT=11 DEFAULT CHARSET=latin1;',
    'Job_Notes' => 'CREATE TABLE `Job_Notes` (
  `note_id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `note_text` text NOT NULL,
  `created_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int NOT NULL,
  PRIMARY KEY (`note_id`),
  KEY `job_id` (`job_id`),
  KEY `created_by` (`created_by`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=latin1;',
    'Job_Services' => 'CREATE TABLE `Job_Services` (
  `job_service_id` int NOT NULL AUTO_INCREMENT,
  `job_id` int NOT NULL,
  `service_id` int NOT NULL,
  PRIMARY KEY (`job_service_id`),
  UNIQUE KEY `unique_job_service` (`job_id`,`service_id`)
) ENGINE=MyISAM AUTO_INCREMENT=74 DEFAULT CHARSET=latin1;',
    'Jobs' => 'CREATE TABLE `Jobs` (
  `job_id` int NOT NULL AUTO_INCREMENT,
  `entered_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `due_date` date DEFAULT NULL,
  `job_name` varchar(255) NOT NULL,
  `job_description` text,
  `is_accepted` tinyint NOT NULL DEFAULT \'0\',
  `accepted_datetime` datetime DEFAULT NULL,
  `terms` text,
  `contractor_id` int DEFAULT NULL,
  `is_completed` tinyint(1) NOT NULL DEFAULT \'0\',
  `completed_datetime` datetime DEFAULT NULL,
  `approved_price` decimal(10,2) DEFAULT NULL,
  `deposit_amount` decimal(10,2) DEFAULT NULL,
  `who_supplies_materials` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`job_id`),
  KEY `contractor_id` (`contractor_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=latin1;',
    'Notes' => 'CREATE TABLE `Notes` (
  `note_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `admin_id` int NOT NULL,
  `note_text` text NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`note_id`),
  KEY `user_id` (`user_id`),
  KEY `admin_id` (`admin_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;',
    'Services' => 'CREATE TABLE `Services` (
  `service_id` int NOT NULL AUTO_INCREMENT,
  `service_name` varchar(100) NOT NULL,
  PRIMARY KEY (`service_id`),
  UNIQUE KEY `service_name` (`service_name`)
) ENGINE=MyISAM AUTO_INCREMENT=44 DEFAULT CHARSET=latin1;',
    'Templates' => 'CREATE TABLE `Templates` (
  `template_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `html_content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`template_id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;',
    'Users' => 'CREATE TABLE `Users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `company_address` varchar(255) NOT NULL,
  `company_phone` varchar(50) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone1` varchar(50) DEFAULT NULL,
  `phone1_type` varchar(75) NOT NULL,
  `phone2` varchar(50) NOT NULL,
  `phone2_type` varchar(75) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_photo` longblob,
  `is_admin` tinyint(1) DEFAULT \'0\',
  `is_approved` tinyint(1) DEFAULT \'0\',
  `approved_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `priority` int NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `email_2` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=62 DEFAULT CHARSET=latin1;',
];
