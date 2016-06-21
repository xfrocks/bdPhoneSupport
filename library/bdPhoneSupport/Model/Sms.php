<?php

class bdPhoneSupport_Model_Sms extends XenForo_Model
{
    public function send($phoneNumber, $text)
    {
        if (empty($phoneNumber)) {
            throw new XenForo_Exception('Cannot send SMS without phone number.');
        }

        XenForo_Helper_File::log(__CLASS__, sprintf('%s($phoneNumber=%s, $text=%s)',
            __METHOD__, $phoneNumber, $text));

        return true;
    }
}