<?php

class bdPhoneSupport_Helper_PhoneNumber
{
    public static function standardize($phoneNumber)
    {
        if (substr($phoneNumber, 0, 2) === '00') {
            $phoneNumber = '+' . substr($phoneNumber, 2);
        }

        $defaultCountryCallCode = bdPhoneSupport_Option::get('defaultCountryCallCode');
        if (!empty($defaultCountryCallCode)) {
            if (substr($phoneNumber, 0, 1) !== '+') {
                $phoneNumber = $defaultCountryCallCode . ltrim($phoneNumber, '0');
            }

            if (substr($phoneNumber, strlen($defaultCountryCallCode), 1) === '0') {
                $phoneNumber = substr($phoneNumber, 0, strlen($defaultCountryCallCode))
                    . substr($phoneNumber, strlen($defaultCountryCallCode) + 1);
            }
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