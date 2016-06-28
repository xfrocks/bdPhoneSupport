<?php

class bdPhoneSupport_Helper_DataSource
{
    const OPTION_KEY_PHONE_NUMBER = '%1$sDataSource';
    const OPTION_KEY_VERIFIED = '%1$sVerifiedDataSource';

    const DATA_PHONE_NUMBER_MAX_LENGTH = 16;
    const DATA_PHONE_NUMBER_REGEX = '^\+?\d{6,15}$';
    const DATA_VERIFIED_YES = '1';
    const DATA_VERIFIED_NO = '0';

    public static function setUserValue($type, $optionKeyFormat, $userIdOrArray, $value)
    {
        $dataSource = bdPhoneSupport_Option::get(self::_buildOptionKey($type, $optionKeyFormat));
        if (empty($dataSource['type'])) {
            return null;
        }

        /** @var XenForo_DataWriter_User $userDw */
        $userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
        if (is_array($userIdOrArray)) {
            $userDw->setExistingData($userIdOrArray, true);
        } else {
            $userDw->setExistingData($userIdOrArray);
        }

        self::setUserValueDw($userDw, $optionKeyFormat, $dataSource, $value);

        if ($optionKeyFormat === self::OPTION_KEY_VERIFIED
            && $value === self::DATA_VERIFIED_YES
            && $type !== 'some'
        ) {
            $someDataSource = bdPhoneSupport_Option::get(self::_buildOptionKey('some', $optionKeyFormat));
            if (!empty($someDataSource['type'])) {
                self::setUserValueDw($userDw, $optionKeyFormat, $someDataSource, self::DATA_VERIFIED_YES);
            }
        }

        return $userDw->save();
    }

    public static function setUserValueDw(XenForo_DataWriter_User $userDw, $optionKeyFormat, $dataSource, $value)
    {
        switch ($dataSource['type']) {
            case 'db':
                $userDw->set($dataSource['dbColumn'], $value, $dataSource['dbTable']);
                break;
            case 'userField':
                if ($optionKeyFormat === self::OPTION_KEY_VERIFIED) {
                    $userDw->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);
                }
                $userDw->setCustomFields(array($dataSource['userFieldId'] => $value));
                break;
        }
    }

    public static function getUserValue($type, $optionKeyFormat, $userIdOrArray)
    {
        $user = array();
        if (is_numeric($userIdOrArray)) {
            $userId = intval($userIdOrArray);
        } elseif (is_array($userIdOrArray)
            && isset($userIdOrArray['user_id'])
        ) {
            $userId = $userIdOrArray['user_id'];
            $user = $userIdOrArray;
        } else {
            throw new XenForo_Exception('Cannot get user value without user ID.');
        }

        $dataSource = bdPhoneSupport_Option::get(self::_buildOptionKey($type, $optionKeyFormat));
        if (empty($dataSource['type'])) {
            return null;
        }

        switch ($dataSource['type']) {
            case 'db':
                if (isset($user[$dataSource['dbColumn']])) {
                    return $user[$dataSource['dbColumn']];
                } else {
                    /** @var XenForo_Model_User $userModel */
                    $userModel = XenForo_Model::create('XenForo_Model_User');
                    $user = $userModel->getFullUserById($userId);
                    if (isset($user[$dataSource['dbColumn']])) {
                        return $user[$dataSource['dbColumn']];
                    }
                }
                break;
            case 'userField':
                if (isset($user['customFields'])) {
                    $customFields = $user['customFields'];
                } else {
                    /** @var XenForo_Model_UserField $userFieldModel */
                    $userFieldModel = XenForo_Model::create('XenForo_Model_UserField');
                    $customFields = $userFieldModel->getUserFieldValues($user['user_id']);
                }

                if (isset($customFields[$dataSource['userFieldId']])) {
                    return $customFields[$dataSource['userFieldId']];
                }
                break;
        }

        return '';
    }

    public static function prepareUserFields($type, $optionKeyFormat, array &$fields)
    {
        $dataSource = bdPhoneSupport_Option::get(self::_buildOptionKey($type, $optionKeyFormat));
        if (!empty($dataSource['type'])
            && $dataSource['type'] === 'db'
            && isset($fields[$dataSource['dbTable']])
            && !isset($fields[$dataSource['dbTable']][$dataSource['dbColumn']])
        ) {
            $fieldDefinition = array('type' => XenForo_DataWriter::TYPE_STRING);

            switch ($optionKeyFormat) {
                case self::OPTION_KEY_PHONE_NUMBER:
                    $fieldDefinition['default'] = '';
                    $fieldDefinition['maxLength'] = self::DATA_PHONE_NUMBER_MAX_LENGTH;
                    $fieldDefinition['verification'] = array(__CLASS__, 'prepareUserFields_phoneNumberVerification');
                    break;
                case self::OPTION_KEY_VERIFIED:
                    $fieldDefinition['default'] = self::DATA_VERIFIED_NO;
                    $fieldDefinition['allowedValues'] = array(self::DATA_VERIFIED_YES, self::DATA_VERIFIED_NO);
                    break;
            }

            $fields[$dataSource['dbTable']][$dataSource['dbColumn']] = $fieldDefinition;
        }
    }

    public static function prepareUserFields_phoneNumberVerification(
        &$phoneNumber,
        XenForo_DataWriter $dw,
        $fieldName
    ) {
        if (empty($phoneNumber)) {
            return true;
        }

        if (!preg_match(sprintf('#%s#', self::DATA_PHONE_NUMBER_REGEX), $phoneNumber)) {
            $dw->error(new XenForo_Phrase('bdPhoneSupport_error_phone_number_x_invalid', array(
                'phone_number' => $phoneNumber
            )), $fieldName);
        }

        return true;
    }

    public static function validateOption(array &$dataSource, XenForo_DataWriter $dw, $fieldName)
    {
        if (empty($dataSource['type'])) {
            return true;
        }

        switch ($dw->get('option_id')) {
            case 'bdPhoneSupport_primaryDataSource';
                // https://www.itu.int/rec/T-REC-E.164/en
                $config = array(
                    'dbColumnSchema' => 'VARCHAR(' . self::DATA_PHONE_NUMBER_MAX_LENGTH . ') NOT NULL DEFAULT \'\'',
                    'userFieldBulkSet' => array(
                        'display_group' => 'contact',
                        'field_type' => 'textbox',
                        'match_type' => 'regex',
                        'match_regex' => self::DATA_PHONE_NUMBER_REGEX,
                        'max_length' => self::DATA_PHONE_NUMBER_MAX_LENGTH
                    )
                );
                break;
            case 'bdPhoneSupport_primaryVerifiedDataSource':
            case 'bdPhoneSupport_someVerifiedDataSource':
                $config = array(
                    'dbColumnSchema' => 'TINYINT(3) UNSIGNED NOT NULL DEFAULT \'0\'',
                    'userFieldBulkSet' => array(
                        'display_group' => 'contact',
                        'field_type' => 'radio',
                        'user_editable' => 'never'
                    ),
                    'userFieldChoices' => array(
                        self::DATA_VERIFIED_YES => new XenForo_Phrase('yes'),
                        self::DATA_VERIFIED_NO => new XenForo_Phrase('no')
                    )
                );
                break;
            default:
                // unrecognized option id?!
                return true;
        }

        switch ($dataSource['type']) {
            case 'db':
                self::_validateOption_typeDb($dataSource, $dw, $fieldName, $config);
                break;
            case 'userField':
                self::_validateOption_typeUserField($dataSource, $dw, $fieldName, $config);
                break;
            default:
                $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_type_unknown', array(
                    'field_name' => $fieldName,
                    'type' => $dataSource['type']
                )), 'option_value');
                break;
        }

        return true;
    }

    protected static function _validateOption_typeDb(
        array &$dataSource,
        XenForo_DataWriter $dw,
        $fieldName,
        array $config
    ) {
        $db = XenForo_Application::getDb();

        if (empty($dataSource['dbTable'])) {
            $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_db_table_missing', array(
                'field_name' => $fieldName
            )), 'option_value');
            return false;
        }

        $tables = $db->fetchCol('SHOW TABLES LIKE ' . $db->quote($dataSource['dbTable']));
        if (empty($tables)) {
            $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_db_table_not_found', array(
                'field_name' => $fieldName,
                'table' => $dataSource['dbTable']
            )), 'option_value');
            return false;
        }

        if (empty($dataSource['dbColumn'])) {
            $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_db_column_missing', array(
                'field_name' => $fieldName
            )), 'option_value');
            return false;
        }
        if (substr($dataSource['dbColumn'], -1) === '!') {
            if (!preg_match('#^(?<dbColumn>[a-z0-9_]+)!$#i', $dataSource['dbColumn'], $matches)) {
                $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_db_column_invalid', array(
                    'field_name' => $fieldName,
                    'column' => $dataSource['dbColumn']
                )), 'option_value');
                return false;
            }

            $dataSource['dbColumn'] = $matches['dbColumn'];

            $db->query(sprintf('ALTER TABLE %s ADD COLUMN %s %s;', $dataSource['dbTable'],
                $dataSource['dbColumn'], $config['dbColumnSchema']));
        }

        $tableColumns = $db->fetchPairs('SHOW COLUMNS FROM ' . $dataSource['dbTable']);
        if (empty($tableColumns['user_id'])) {
            $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_db_table_no_user_id', array(
                'field_name' => $fieldName,
                'table' => $dataSource['dbTable']
            )), 'option_value');
        }

        if (empty($tableColumns[$dataSource['dbColumn']])) {
            $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_db_column_not_found', array(
                'field_name' => $fieldName,
                'table' => $dataSource['dbTable'],
                'column' => $dataSource['dbColumn']
            )), 'option_value');
            return false;
        }

        return true;
    }

    protected static function _validateOption_typeUserField(
        array &$dataSource,
        XenForo_DataWriter $dw,
        $fieldName,
        array $config
    ) {
        /** @var XenForo_Model_UserField $userFieldModel */
        $userFieldModel = XenForo_Model::create('XenForo_Model_UserField');

        if (empty($dataSource['userFieldId'])) {
            $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_user_field_id_missing', array(
                'field_name' => $fieldName
            )), 'option_value');
            return false;
        }

        if (substr($dataSource['userFieldId'], -1) === '!') {
            if (!preg_match('#^(?<userFieldId>[a-z0-9_]+)!$#i', $dataSource['userFieldId'], $matches)) {
                $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_user_field_id_invalid', array(
                    'field_name' => $fieldName,
                    'user_field_id' => $dataSource['userFieldId']
                )), 'option_value');
                return false;
            }

            $dataSource['userFieldId'] = $matches['userFieldId'];

            /** @var XenForo_DataWriter_UserField $userFieldDw */
            $userFieldDw = XenForo_DataWriter::create('XenForo_DataWriter_UserField');
            $userFieldDw->set('field_id', $dataSource['userFieldId']);
            $userFieldDw->bulkSet($config['userFieldBulkSet']);
            if (isset($config['userFieldChoices'])) {
                $userFieldDw->setFieldChoices($config['userFieldChoices']);
            }

            /** @var XenForo_Model_Option $optionModel */
            $optionModel = $userFieldModel->getModelFromCache('XenForo_Model_Option');
            $titlePhraseName = $optionModel->getOptionTitlePhraseName($dw->get('option_id'));
            $userFieldDw->setExtraData(XenForo_DataWriter_UserField::DATA_TITLE,
                strval(new XenForo_Phrase($titlePhraseName)));
            $userFieldDw->setExtraData(XenForo_DataWriter_UserField::DATA_DESCRIPTION, '');

            $userFieldDw->save();
        }

        $field = $userFieldModel->getUserFieldById($dataSource['userFieldId']);
        if (empty($field)) {
            $dw->error(new XenForo_Phrase('bdPhoneSupport_error_data_source_user_field_id_not_found', array(
                'field_name' => $fieldName,
                'user_field_id' => $dataSource['userFieldId']
            )), 'option_value');
            return false;
        }

        return true;
    }

    protected static function _buildOptionKey($type, $optionKeyFormat)
    {
        return sprintf($optionKeyFormat, $type, ucwords($type));
    }
}