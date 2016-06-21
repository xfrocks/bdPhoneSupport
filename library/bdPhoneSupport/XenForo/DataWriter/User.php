<?php

class bdPhoneSupport_XenForo_DataWriter_User extends XFCP_bdPhoneSupport_XenForo_DataWriter_User
{
    const OPTION_UPDATE_VERIFIED_ON_CHANGE = 'bdPhoneSupport_updateVerifiedOnChange';
    const OPTION_UPDATE_USER_PHONES = 'bdPhoneSupport_updateUserPhones';

    protected function _getFields()
    {
        $fields = parent::_getFields();

        bdPhoneSupport_Helper_DataSource::prepareUserFields('primary',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_PHONE_NUMBER, $fields);
        bdPhoneSupport_Helper_DataSource::prepareUserFields('primary',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED, $fields);

        return $fields;
    }

    protected function _getDefaultOptions()
    {
        $options = parent::_getDefaultOptions();

        $options[self::OPTION_UPDATE_VERIFIED_ON_CHANGE] = true;
        $options[self::OPTION_UPDATE_USER_PHONES] = true;

        return $options;
    }

    protected function _postSave()
    {
        parent::_postSave();

        if ($this->_bdPhoneSupport_hasPrimaryPhoneNumberChanged()) {
            $this->_bdPhoneSupport_triggerPrimaryVerification();
            $this->_bdPhoneSupport_removeUserPhones();
        }
    }

    protected function _bdPhoneSupport_hasPrimaryPhoneNumberChanged()
    {
        $primaryDataSource = bdPhoneSupport_Option::get('primaryDataSource');
        if (empty($primaryDataSource['type'])) {
            return false;
        }

        switch ($primaryDataSource['type']) {
            case 'db':
                $dbTable = $primaryDataSource['dbTable'];
                $dbColumn = $primaryDataSource['dbColumn'];
                if ($this->isChanged($dbColumn, $dbTable)) {
                    return true;
                }
                break;
            case 'userField':
                $userFieldId = $primaryDataSource['userFieldId'];
                if (isset($this->_updateCustomFields[$userFieldId])) {
                    return true;
                }
                break;
        }

        return false;
    }

    protected function _bdPhoneSupport_removeUserPhones()
    {
        if (!$this->getOption(self::OPTION_UPDATE_USER_PHONES)) {
            return false;
        }
        $this->setOption(self::OPTION_UPDATE_USER_PHONES, false);

        return bdPhoneSupport_Integration::updateUserPhones($this->getMergedData());
    }

    protected function _bdPhoneSupport_triggerPrimaryVerification()
    {
        if (!$this->getOption(self::OPTION_UPDATE_VERIFIED_ON_CHANGE)
            || $this->getOption(self::OPTION_ADMIN_EDIT)
        ) {
            return false;
        }
        $this->setOption(self::OPTION_UPDATE_VERIFIED_ON_CHANGE, false);

        $userData = $this->getMergedData();

        if (bdPhoneSupport_Helper_DataSource::setUserValue('primary',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED, $userData, 0)
        ) {
            if ($userData['user_id'] == XenForo_Visitor::getUserId()) {
                /** @var bdPhoneSupport_Model_Verification $verificationModel */
                $verificationModel = $this->getModelFromCache('bdPhoneSupport_Model_Verification');
                $phoneNumber = bdPhoneSupport_Integration::getUserPhoneNumber('primary', $userData);
                $verificationModel->requestVerify($phoneNumber);
            }
        }

        return true;
    }
}