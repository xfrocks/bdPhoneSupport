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

    public static function verifyUserPhone($type, $userId, $codeText = '', &$errorPhraseKey = null)
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

        if ($codeText !== ''
            && !$verificationModel->verifyCode($user['user_id'], $phoneNumber, $codeText, $errorPhraseKey)
        ) {
            return false;
        }

        if (bdPhoneSupport_Option::get('oneUserPerPhone')) {
            /** @var bdPhoneSupport_Model_UserPhone $userPhoneModel */
            $userPhoneModel = $verificationModel->getModelFromCache('bdPhoneSupport_Model_UserPhone');
            if ($userPhoneModel->getUsersByPhoneNumber($phoneNumber, $user['user_id'])) {
                // new XenForo_Phrase('bdPhoneSupport_error_cannot_verify_someone_else')
                $errorPhraseKey = 'bdPhoneSupport_error_cannot_verify_someone_else';
                return false;
            }
        }

        return bdPhoneSupport_Helper_DataSource::setUserValue($type,
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED, $user,
            bdPhoneSupport_Helper_DataSource::DATA_VERIFIED_YES);
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

        /**
         * These checks are optional, the real checks are done in
         * @see bdPhoneSupport_Model_Verification::requestVerify
         *
         * We still perform checks here to avoid user confusion when
         * they see the notice but can't verify their phone...
         */
        $visitor = XenForo_Visitor::getInstance();
        if ($visitor['user_state'] !== 'valid') {
            return;
        }
        $codeMax = $visitor->hasPermission('general', 'bdPhoneSupport_codeMax');
        if ($codeMax === 0) {
            return;
        }
        $codePerDay = $visitor->hasPermission('general', 'bdPhoneSupport_codePerDay');
        if ($codePerDay === 0) {
            return;
        }

        // new XenForo_Phrase('bdPhoneSupport_notice_verify_primary')
        $paramKey = 'bdPhoneSupport_showPrimaryVerifyNotice';
        $containerParams[$paramKey] = true;
        $dependencies->notices[$paramKey] = 'bdPhoneSupport_notice_verify_primary';
    }

    public static function criteriaUser($type, array $data, array $user, &$returnValue)
    {
        switch ($data['status']) {
            case 'entered':
                $phoneNumber = self::getUserPhoneNumber($type, $user);
                if (!empty($phoneNumber)) {
                    $returnValue = true;
                }
                break;
            case 'verified':
                $verified = self::getUserVerified($type, $user);
                if ($verified === true) {
                    $returnValue = true;
                } elseif ($verified === null) {
                    // no verified data source, check for entered phone number instead
                    return self::criteriaUser($type, array('status' => 'entered'), $user, $returnValue);
                }
                break;
            case 'verified_once':
                $verified = self::getUserVerified('some', $user);
                if ($verified === true) {
                    $returnValue = true;
                } elseif ($verified === null) {
                    // no some-verified data source, check for entered phone number instead
                    return self::criteriaUser($type, array('status' => 'entered'), $user, $returnValue);
                }
                break;
        }
    }
}