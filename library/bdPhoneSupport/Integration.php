<?php

class bdPhoneSupport_Integration
{
    public static function setUserPhoneNumber($type, $userId, $phoneNumber)
    {
        return bdPhoneSupport_Helper_DataSource::setUserValue($type,
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_PHONE_NUMBER, $userId,
            bdPhoneSupport_Helper_PhoneNumber::standardize($phoneNumber)
        );
    }

    public static function verifyUserPhone($type, $userId, $codeText)
    {
        /** @var bdPhoneSupport_Model_Verification $verificationModel */
        $verificationModel = XenForo_Model::create('bdPhoneSupport_Model_Verification');

        if ($userId == XenForo_Visitor::getUserId()) {
            $user = XenForo_Visitor::getInstance()->toArray();
        } else {
            /** @var XenForo_Model_User $userModel */
            $userModel = $verificationModel->getModelFromCache('XenForo_Model_User');
            $user = $userModel->getFullUserById($userId);
            if (empty($user)) {
                throw new XenForo_Exception('Cannot verify user phone with invalid user ID.');
            }
        }

        $phoneNumber = self::getUserPhoneNumber($type, $user);
        if (!$verificationModel->verifyCode($user['user_id'], $phoneNumber, $codeText)) {
            return false;
        }

        if (bdPhoneSupport_Option::get('oneUserPerPhone')) {
            /** @var bdPhoneSupport_Model_UserPhone $userPhoneModel */
            $userPhoneModel = $verificationModel->getModelFromCache('bdPhoneSupport_Model_UserPhone');
            if ($userPhoneModel->getUsersByPhoneNumber($phoneNumber, $user['user_id'])) {
                return false;
            }
        }

        if (!bdPhoneSupport_Helper_DataSource::setUserValue(
            $type,
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED,
            $user,
            bdPhoneSupport_Helper_DataSource::DATA_VERIFIED_YES)
        ) {
            return false;
        }

        /** @var bdPhoneSupport_Model_UserPhone $userPhoneModel */
        $userPhoneModel = XenForo_Model::create('bdPhoneSupport_Model_UserPhone');
        if (!$userPhoneModel->addUserPhone($user['user_id'], $phoneNumber)) {
            return false;
        }

        return true;
    }

    public static function getUserPhoneNumber($type = 'primary', array $user = array())
    {
        if (!isset($user['user_id'])) {
            $user = XenForo_Visitor::getInstance()->toArray();
        }

        $phoneNumber = bdPhoneSupport_Helper_DataSource::getUserValue($type,
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_PHONE_NUMBER, $user);
        if (empty($phoneNumber)) {
            return $phoneNumber;
        }

        return bdPhoneSupport_Helper_PhoneNumber::standardize($phoneNumber);
    }

    public static function getUserVerified($type = 'primary', array $user = array())
    {
        if (!isset($user['user_id'])) {
            $user = XenForo_Visitor::getInstance()->toArray();
        }

        $verified = bdPhoneSupport_Helper_DataSource::getUserValue($type,
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED, $user);
        if ($verified === null) {
            return null;
        }

        return strval($verified) === bdPhoneSupport_Helper_DataSource::DATA_VERIFIED_YES;
    }

    public static function updateUserPhones(array $user = array())
    {
        if (!isset($user['user_id'])) {
            $user = XenForo_Visitor::getInstance()->toArray();
        }

        $phoneNumbers = array();

        $primaryPhoneNumber = self::getUserPhoneNumber('primary', $user);
        if (!empty($primaryPhoneNumber)) {
            $primaryVerified = self::getUserVerified('primary', $user);
            if ($primaryVerified !== false) {
                $phoneNumbers[] = $primaryPhoneNumber;
            }
        }

        /** @var bdPhoneSupport_Model_UserPhone $userPhoneModel */
        $userPhoneModel = XenForo_Model::create('bdPhoneSupport_Model_UserPhone');
        return $userPhoneModel->updateUserPhones($user['user_id'], $phoneNumbers);
    }

    public static function showPrimaryVerifyNotice(array &$containerParams, XenForo_Dependencies_Public $dependencies)
    {
        if (!bdPhoneSupport_Option::get('primaryVerifyNotice')) {
            return;
        }

        $phoneNumber = self::getUserPhoneNumber();
        if (empty($phoneNumber)) {
            return;
        }

        $verified = self::getUserVerified();
        if ($verified !== false) {
            return;
        }

        // new XenForo_Phrase('bdPhoneSupport_showPrimaryVerifyNotice')
        $paramKey = 'bdPhoneSupport_showPrimaryVerifyNotice';
        $containerParams[$paramKey] = true;
        $dependencies->notices[$paramKey] = 'bdPhoneSupport_notice_verify_primary';
    }
}