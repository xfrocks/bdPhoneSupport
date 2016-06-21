<?php

class bdPhoneSupport_Listener
{
    public static function load_class_XenForo_DataWriter_User($class, array &$extend)
    {
        if ($class === 'XenForo_DataWriter_User') {
            $extend[] = 'bdPhoneSupport_XenForo_DataWriter_User';
        }
    }

    public static function load_class_XenForo_ControllerPublic_Account($class, array &$extend)
    {
        if ($class === 'XenForo_ControllerPublic_Account') {
            $extend[] = 'bdPhoneSupport_XenForo_ControllerPublic_Account';
        }
    }

    public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes += bdPhoneSupport_FileSums::getHashes();
    }
}