<?php

class bdPhoneSupport_Listener
{
    public static function criteria_user($rule, array $data, array $user, &$returnValue)
    {
        switch ($rule) {
            case 'bdPhoneSupport_primary':
                bdPhoneSupport_Integration::criteriaUser('primary', $data, $user, $returnValue);
                break;
        }
    }

    public static function container_public_params(array &$params, XenForo_Dependencies_Abstract $dependencies)
    {
        /** @noinspection PhpParamsInspection */
        bdPhoneSupport_Integration::showPrimaryVerifyNotice($params, $dependencies);
    }

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

    public static function load_class_XenForo_ControllerPublic_Misc($class, array &$extend)
    {
        if ($class === 'XenForo_ControllerPublic_Misc') {
            $extend[] = 'bdPhoneSupport_XenForo_ControllerPublic_Misc';
        }
    }
}