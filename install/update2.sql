CREATE TABLE IF NOT EXISTS `pre_storage` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` varchar(30) NOT NULL,
  `config` text NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `isdefault` tinyint(1) NOT NULL DEFAULT '0',
  `sort` int(11) NOT NULL DEFAULT '0',
  `addtime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `pre_file`
ADD COLUMN `storage_id` int(11) unsigned NOT NULL DEFAULT '0';

ALTER TABLE `pre_file`
ADD INDEX `storage_id` (`storage_id`);

REPLACE INTO `pre_config` VALUES ('upload_choose_storage', '0');
