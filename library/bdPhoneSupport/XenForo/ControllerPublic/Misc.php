<?php

class bdPhoneSupport_XenForo_ControllerPublic_Misc extends XFCP_bdPhoneSupport_XenForo_ControllerPublic_Misc
{
    public function actionTwilioStatusCallback()
    {
        $this->_assertPostOnly();

        $config = bdPhoneSupport_Option::get('twilio');
        $requestPaths = XenForo_Application::get('requestPaths');
        $url = $requestPaths['fullUri'];
        if (XenForo_Application::debugMode()) {
            $urlOverwrite = $this->_input->filterSingle('_url', XenForo_Input::STRING);
            if (!empty($urlOverwrite)) {
                $url = $urlOverwrite;
            }
        }
        $computedSignature = bdPhoneSupport_Helper_Provider_Twilio::computeSignature($config, $url, $_POST);

        if (empty($_SERVER['HTTP_X_TWILIO_SIGNATURE'])) {
            die('No signature');
        }
        $signature = $_SERVER['HTTP_X_TWILIO_SIGNATURE'];

        if ($signature != $computedSignature) {
            die('Invalid signature');
        }

        $providerId = $this->_input->filterSingle('MessageSid', XenForo_Input::STRING);
        $action = $this->_input->filterSingle('MessageStatus', XenForo_Input::STRING);

        /** @var bdPhoneSupport_Model_Sms $smsModel */
        $smsModel = $this->getModelFromCache('bdPhoneSupport_Model_Sms');
        $smsModel->log(array(
            'provider_id' => 'twilio_' . $providerId,
            'action' => $action,
            'data' => serialize($_POST)
        ));

        die('Okie');
    }
}