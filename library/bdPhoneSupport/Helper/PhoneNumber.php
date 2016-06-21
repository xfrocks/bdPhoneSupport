<?php

class bdPhoneSupport_Helper_PhoneNumber
{
    public static function standardize($phoneNumber)
    {
        if (substr($phoneNumber, 0, 2) === '00') {
            $phoneNumber = '+' . substr($phoneNumber, 2);
        }

        if (substr($phoneNumber, 0, 1) !== '+') {
            $phoneNumber = bdPhoneSupport_Option::get('defaultCountryCallCode') . ltrim($phoneNumber, '0');
        }

        return $phoneNumber;
    }

    public static function validateOptionCountryCallingCode(&$ccc, XenForo_DataWriter $dw, $fieldName)
    {
        if (empty($ccc)) {
            return true;
        }

        if (preg_match('#^\+\d{1,4}$#', $ccc)) {
            return true;
        }

        if (preg_match('#^00(?<ccc>\d{1,4})$#', $ccc, $matches)) {
            $ccc = '+' . $matches['ccc'];
            return true;
        }

        $dw->error(new XenForo_Phrase('bdPhoneSupport_error_country_calling_code_invalid', array(
            'field_name' => $fieldName,
            'ccc' => $ccc
        )), 'option_value');
        return true;
    }
}