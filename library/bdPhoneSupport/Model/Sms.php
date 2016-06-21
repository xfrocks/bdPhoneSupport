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

        return $this->_twilio_send($phoneNumber, $text);
    }

    public function log(array $bulkSet)
    {
        $bulkSet += array(
            'action_date' => XenForo_Application::$time
        );

        $this->_getDb()->insert('xf_bdphonesupport_log', $bulkSet);
    }

    protected function _twilio_send($phoneNumber, $text)
    {
        return bdPhoneSupport_Helper_Provider_Twilio::postMessage(
            bdPhoneSupport_Option::get('twilio'), $phoneNumber, $text);
    }
}