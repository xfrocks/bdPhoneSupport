<?php

class bdPhoneSupport_Installer
{
    /* Start auto-generated lines of code. Change made will be overwriten... */

    protected static $_tables = array(
        'code' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdphonesupport_code` (
                `code_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`phone_number` VARCHAR(16) NOT NULL
                ,`code_text` VARCHAR(255) NOT NULL
                ,`generate_date` INT(10) UNSIGNED NOT NULL
                ,`verify_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                ,`data` MEDIUMBLOB
                , PRIMARY KEY (`code_id`)
                ,INDEX `user_id_phone_number` (`user_id`,`phone_number`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdphonesupport_code`',
        ),
        'user_phone' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdphonesupport_user_phone` (
                `user_phone_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`user_id` INT(10) UNSIGNED NOT NULL
                ,`phone_number` VARCHAR(16) NOT NULL
                ,`verify_date` INT(10) UNSIGNED NOT NULL
                ,`remove_date` INT(10) UNSIGNED NOT NULL DEFAULT \'0\'
                , PRIMARY KEY (`user_phone_id`)
                ,INDEX `user_id` (`user_id`)
                ,INDEX `phone_number` (`phone_number`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdphonesupport_user_phone`',
        ),
        'log' => array(
            'createQuery' => 'CREATE TABLE IF NOT EXISTS `xf_bdphonesupport_log` (
                `log_id` INT(10) UNSIGNED AUTO_INCREMENT
                ,`provider_id` VARCHAR(255) NOT NULL
                ,`action` VARCHAR(255) NOT NULL
                ,`action_date` INT(10) UNSIGNED NOT NULL
                ,`data` MEDIUMBLOB
                , PRIMARY KEY (`log_id`)
                ,INDEX `provider_id` (`provider_id`)
            ) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;',
            'dropQuery' => 'DROP TABLE IF EXISTS `xf_bdphonesupport_log`',
        ),
    );
    protected static $_patches = array();

    public static function install($existingAddOn, $addOnData)
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_tables as $table) {
            $db->query($table['createQuery']);
        }

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (empty($existed)) {
                $db->query($patch['addQuery']);
            }
        }

        self::installCustomized($existingAddOn, $addOnData);
    }

    public static function uninstall()
    {
        $db = XenForo_Application::get('db');

        foreach (self::$_patches as $patch) {
            $tableExisted = $db->fetchOne($patch['tableCheckQuery']);
            if (empty($tableExisted)) {
                continue;
            }

            $existed = $db->fetchOne($patch['checkQuery']);
            if (!empty($existed)) {
                $db->query($patch['dropQuery']);
            }
        }

        foreach (self::$_tables as $table) {
            $db->query($table['dropQuery']);
        }

        self::uninstallCustomized();
    }

    /* End auto-generated lines of code. Feel free to make changes below */

    public static function installCustomized($existingAddOn, $addOnData)
    {
        $db = XenForo_Application::getDb();

        $existingVersionId = 0;
        if (!empty($existingAddOn)) {
            $existingVersionId = $existingAddOn['version_id'];
        }

        if ($existingVersionId <= 1000000) {
            $db->query("
				INSERT IGNORE INTO xf_permission_entry
					(user_group_id, user_id, permission_group_id, permission_id,               permission_value, permission_value_int)
				VALUES
				    (2,             0,       'general',           'bdPhoneSupport_codeMax',    'use_int',        -1),
				    (2,             0,       'general',           'bdPhoneSupport_codePerDay', 'use_int',        3)
			");
        }
    }

    public static function uninstallCustomized()
    {
        // customized uninstall script goes here
    }

}