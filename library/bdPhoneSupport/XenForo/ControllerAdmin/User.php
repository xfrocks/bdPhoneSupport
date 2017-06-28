<?php

class bdPhoneSupport_XenForo_ControllerAdmin_User extends XFCP_bdPhoneSupport_XenForo_ControllerAdmin_User
{
    public function actionSave()
    {
        $GLOBALS['bdPhoneSupport_XenForo_ControllerAdmin_User::actionSave'] = $this;

        return parent::actionSave();
    }

    public function bdPhoneSupport_actionSave(XenForo_DataWriter_User $dw)
    {
        if (!$this->_input->inRequest('bdPhoneSupport_included')) {
            return;
        }

        $inputArray = $this->_input->filterSingle('bdPhoneSupport', XenForo_Input::ARRAY_SIMPLE);
        $inputObj = new XenForo_Input($inputArray);
        $input = $inputObj->filter(array(
            'primary' => XenForo_Input::STRING,
            'primary_verify' => XenForo_Input::BOOLEAN,
            'some_verify' => XenForo_Input::BOOLEAN,
        ));

        bdPhoneSupport_Helper_DataSource::setUserValue('primary',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_PHONE_NUMBER, $dw, $input['primary']);
        bdPhoneSupport_Helper_DataSource::setUserValue('primary',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED, $dw,
            $input['primary_verify'] ? bdPhoneSupport_Helper_DataSource::DATA_VERIFIED_YES
                : bdPhoneSupport_Helper_DataSource::DATA_VERIFIED_NO);
        bdPhoneSupport_Helper_DataSource::setUserValue('some',
            bdPhoneSupport_Helper_DataSource::OPTION_KEY_VERIFIED, $dw,
            $input['some_verify'] ? bdPhoneSupport_Helper_DataSource::DATA_VERIFIED_YES
                : bdPhoneSupport_Helper_DataSource::DATA_VERIFIED_NO);
    }

    protected function _getUserAddEditResponse(array $user)
    {
        $response = parent::_getUserAddEditResponse($user);

        if (!empty($user['user_id'])
            && $response instanceof XenForo_ControllerResponse_View
            && empty($response->params['bdPhoneSupport'])
        ) {
            $response->params['bdPhoneSupport'] = array();
            $paramsRef =& $response->params['bdPhoneSupport'];

            $paramsRef['primaryPhoneNumber'] = bdPhoneSupport_Helper_DataSource::getUserValue('primary',
                bdPhoneSupport_Helper_DataSource::OPTION_KEY_PHONE_NUMBER, $user);
            $paramsRef['primaryVerified'] = bdPhoneSupport_Integration::getUserVerified('primary', $user);
            $paramsRef['someVerified'] = bdPhoneSupport_Integration::getUserVerified('some', $user);
        }

        return $response;
    }
}