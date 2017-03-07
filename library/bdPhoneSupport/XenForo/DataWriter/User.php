<?php

class bdPhoneSupport_XenForo_DataWriter_User extends XFCP_bdPhoneSupport_XenForo_DataWriter_User
{
    const OPTION_UPDATE_VERIFIED_ON_CHANGE = 'bdPhoneSupport_updateVerifiedOnChange';
    const OPTION_UPDATE_USER_PHONES = 'bdPhoneSupport_updateUserPhones';

    public function bdPhoneSupport_isCustomFieldUpdated($fieldId)
    {
        return isset($this->_updateCustomFields[$fieldId]);
    }

    public function bdPhoneSupport_setCustomField($fieldId, $fieldValue)
    {
        $fieldValues = array();
        if (is_array($this->_updateCustomFields)) {
            $fieldValues = $this->_updateCustomFields;
        }
        $fieldValues[$fieldId] = $fieldValue;

        return $this->setCustomFields($fieldValues);
    }

    protected function _getFields()
    {
        $fields = parent::_getFields();

        bdPhoneSupport_Helper_DataSource::prepareUserFields('primary',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_PHONE_NUMBER, $fields);
        bdPhoneSupport_Helper_DataSource::prepareUserFields('primary',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED, $fields);
        bdPhoneSupport_Helper_DataSource::prepareUserFields('some',
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

    protected function _preSave()
    {
        parent::_preSave();

        if (bdPhoneSupport_Helper_DataSource::isChangeDw($this,
            'primary', bdPhoneSupport_Helper_DataSource::OPTION_KEY_PHONE_NUMBER)
        ) {
            $this->_bdPhoneSupport_markPrimaryUnverified();
        }
    }

    protected function _postSave()
    {
        parent::_postSave();

        $primaryPhoneNumberChanged = bdPhoneSupport_Helper_DataSource::isChangeDw($this,
            'primary', bdPhoneSupport_Helper_DataSource::OPTION_KEY_PHONE_NUMBER);
        $primaryVerifiedChanged = bdPhoneSupport_Helper_DataSource::isChangeDw($this,
            'primary', bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED);
        if ($primaryPhoneNumberChanged || $primaryVerifiedChanged) {
            $this->_bdPhoneSupport_triggerPrimaryVerification();
            $this->_bdPhoneSupport_updateUserPhones();
        }
    }

    protected function _postSaveAfterTransaction()
    {
        parent::_postSaveAfterTransaction();

        if ($this->isUpdate()
            && $this->isChanged('user_state')
            && $this->get('user_state') === 'valid'
        ) {
            // automatically trigger verification on user state change to `valid`
            // usually this happens if user successfully verifies via email
            $this->_bdPhoneSupport_triggerPrimaryVerification();
        }
    }

    protected function _bdPhoneSupport_markPrimaryUnverified()
    {
        if (!$this->getOption(self::OPTION_UPDATE_VERIFIED_ON_CHANGE)
            || $this->getOption(self::OPTION_ADMIN_EDIT)
        ) {
            return false;
        }
        $this->setOption(self::OPTION_UPDATE_VERIFIED_ON_CHANGE, false);

        return bdPhoneSupport_Helper_DataSource::setUserValue('primary',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED, $this, 0);
    }

    protected function _bdPhoneSupport_triggerPrimaryVerification()
    {
        $userData = $this->getMergedData();
        $phoneNumber = bdPhoneSupport_Integration::getUserPhoneNumber('primary', $userData);
        if (empty($phoneNumber)) {
            return false;
        }

        $verified = bdPhoneSupport_Helper_DataSource::getUserValue('primary',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED, $this->getMergedData());
        if (!empty($verified)) {
            return false;
        }

        /** @var bdPhoneSupport_Model_Verification $verificationModel */
        $verificationModel = $this->getModelFromCache('bdPhoneSupport_Model_Verification');
        return $verificationModel->requestVerify($phoneNumber, $null, $userData);
    }

    protected function _bdPhoneSupport_updateUserPhones()
    {
        if (!$this->getOption(self::OPTION_UPDATE_USER_PHONES)) {
            return false;
        }
        $this->setOption(self::OPTION_UPDATE_USER_PHONES, false);

        return bdPhoneSupport_Integration::updateUserPhones($this->getMergedData());
    }
}