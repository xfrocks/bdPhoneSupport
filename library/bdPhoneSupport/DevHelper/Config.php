<?php

class bdPhoneSupport_DevHelper_Config extends DevHelper_Config_Base
{
    protected $_dataClasses = array(
        'code' => array(
            'name' => 'code',
            'camelCase' => 'Code',
            'camelCasePlural' => 'Codes',
            'camelCaseWSpace' => 'Code',
            'camelCasePluralWSpace' => 'Codes',
            'fields' => array(
                'code_id' => array('name' => 'code_id', 'type' => 'uint', 'autoIncrement' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'phone_number' => array('name' => 'phone_number', 'type' => 'string', 'length' => 16, 'required' => true),
                'code_text' => array('name' => 'code_text', 'type' => 'string', 'length' => '255', 'required' => true),
                'generate_date' => array('name' => 'generate_date', 'type' => 'uint', 'required' => true),
                'verify_date' => array('name' => 'verify_date', 'type' => 'uint', 'required' => true, 'default' => 0),
                'data' => array('name' => 'data', 'type' => 'serialized'),
            ),
            'phrases' => array(),
            'title_field' => 'code_text',
            'primaryKey' => array('code_id'),
            'indeces' => array(
                'user_id_phone_number' => array(
                    'name' => 'user_id_phone_number',
                    'fields' => array('user_id', 'phone_number'),
                    'type' => 'NORMAL',
                ),
            ),
            'files' => array('data_writer' => false, 'model' => false, 'route_prefix_admin' => false, 'controller_admin' => false),
        ),
        'user_phone' => array(
            'name' => 'user_phone',
            'camelCase' => 'UserPhone',
            'camelCasePlural' => 'UserPhones',
            'camelCaseWSpace' => 'User Phone',
            'camelCasePluralWSpace' => 'User Phones',
            'fields' => array(
                'user_phone_id' => array('name' => 'user_phone_id', 'type' => 'uint', 'autoIncrement' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'phone_number' => array('name' => 'phone_number', 'type' => 'string', 'length' => 16, 'required' => true),
                'verify_date' => array('name' => 'verify_date', 'type' => 'uint', 'required' => true),
                'remove_date' => array('name' => 'remove_date', 'type' => 'uint', 'required' => true, 'default' => 0),
            ),
            'phrases' => array(),
            'title_field' => 'phone_number',
            'primaryKey' => array('user_phone_id'),
            'indeces' => array(
                'user_id' => array('name' => 'user_id', 'fields' => array('user_id'), 'type' => 'NORMAL'),
                'phone_number' => array('name' => 'phone_number', 'fields' => array('phone_number'), 'type' => 'NORMAL'),
            ),
            'files' => array('data_writer' => false, 'model' => false, 'route_prefix_admin' => false, 'controller_admin' => false),
        ),
        'log' => array(
            'name' => 'log',
            'camelCase' => 'Log',
            'camelCasePlural' => 'Logs',
            'camelCaseWSpace' => 'Log',
            'camelCasePluralWSpace' => 'Logs',
            'fields' => array(
                'log_id' => array('name' => 'log_id', 'type' => 'uint', 'autoIncrement' => true),
                'provider_id' => array('name' => 'provider_id', 'type' => 'string', 'length' => '255', 'required' => true),
                'action' => array('name' => 'action', 'type' => 'string', 'length' => '255', 'required' => true),
                'action_date' => array('name' => 'action_date', 'type' => 'uint', 'required' => true),
                'data' => array('name' => 'data', 'type' => 'serialized'),
            ),
            'phrases' => array(),
            'title_field' => 'provider_id',
            'primaryKey' => array('log_id'),
            'indeces' => array(
                'provider_id' => array('name' => 'provider_id', 'fields' => array('provider_id'), 'type' => 'NORMAL'),
            ),
            'files' => array('data_writer' => false, 'model' => false, 'route_prefix_admin' => false, 'controller_admin' => false),
        ),
    );
    protected $_dataPatches = array();
    protected $_exportPath = '/Users/sondh/XenForo/bdPhoneSupport';
    protected $_exportIncludes = array();
    protected $_exportExcludes = array();
    protected $_exportAddOns = array();
    protected $_exportStyles = array();
    protected $_options = array();

    /**
     * Return false to trigger the upgrade!
     **/
    protected function _upgrade()
    {
        return true; // remove this line to trigger update

        /*
        $this->addDataClass(
            'name_here',
            array( // fields
                'field_here' => array(
                    'type' => 'type_here',
                    // 'length' => 'length_here',
                    // 'required' => true,
                    // 'allowedValues' => array('value_1', 'value_2'),
                    // 'default' => 0,
                    // 'autoIncrement' => true,
                ),
                // other fields go here
            ),
            array('primary_key_1', 'primary_key_2'), // or 'primary_key', both are okie
            array( // indeces
                array(
                    'fields' => array('field_1', 'field_2'),
                    'type' => 'NORMAL', // UNIQUE or FULLTEXT
                ),
            ),
        );
        */
    }
}