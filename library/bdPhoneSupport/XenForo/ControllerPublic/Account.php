<?php

class bdPhoneSupport_XenForo_ControllerPublic_Account extends XFCP_bdPhoneSupport_XenForo_ControllerPublic_Account
{
    public function actionPhones()
    {
        $primaryPhoneNumber = bdPhoneSupport_Integration::getUserPhoneNumber();
        $primaryVerified = bdPhoneSupport_Integration::getUserVerified();
        $from = $this->_input->filterSingle('from', XenForo_Input::STRING);
        $noRedirect = ($from === 'notice');

        if ($this->isConfirmedPost()) {
            $input = $this->_input->filter(array(
                'primary' => XenForo_Input::STRING,
                'primary_verify' => XenForo_Input::STRING,
                'request_verify' => array(XenForo_Input::STRING, 'array' => true),
            ));

            if ($primaryPhoneNumber !== null
                && $input['primary'] !== $primaryPhoneNumber
            ) {
                bdPhoneSupport_Integration::setUserPhoneNumber('primary',
                    XenForo_Visitor::getUserId(), $input['primary']);
                return $this->_bdPhoneSupport_responseRedirect(
                    $noRedirect,
                    XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
                    XenForo_Link::buildPublicLink('account/phones'),
                    new XenForo_Phrase('bdPhoneSupport_your_primary_phone_number_updated')
                );
            }

            if (!empty($input['primary_verify'])) {
                $this->assertNotFlooding('bdPhoneSupport_verify',
                    bdPhoneSupport_Option::get('codeFloodSeconds'));

                if (bdPhoneSupport_Integration::verifyUserPhone('primary',
                    XenForo_Visitor::getUserId(), $input['primary_verify'])
                ) {
                    return $this->_bdPhoneSupport_responseRedirect(
                        $noRedirect,
                        XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
                        XenForo_Link::buildPublicLink('account/phones'),
                        new XenForo_Phrase('bdPhoneSupport_your_primary_phone_number_verified')
                    );
                } else {
                    return $this->responseError(new XenForo_Phrase('bdPhoneSupport_error_cannot_verify'));
                }
            }

            foreach ($input['request_verify'] as $requestVerifyPhoneNumber) {
                /** @var bdPhoneSupport_Model_Verification $verificationModel */
                $verificationModel = $this->getModelFromCache('bdPhoneSupport_Model_Verification');
                if ($verificationModel->requestVerify($requestVerifyPhoneNumber, $errorPhraseKey)) {
                    return $this->responseMessage(new XenForo_Phrase('bdPhoneSupport_sent_code_phone_x', array(
                        'phone_number' => $requestVerifyPhoneNumber
                    )));
                } else {
                    throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
                }
            }

            return $this->_bdPhoneSupport_responseRedirect(
                $noRedirect,
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildPublicLink('account/phones'),
                null
            );
        }

        $viewParams = array(
            'primaryPhoneNumber' => $primaryPhoneNumber,
            'primaryVerified' => $primaryVerified,

            'from' => $from,
        );

        return $this->_getWrapper('bdPhoneSupport_Phones', 'account/phones', $this->responseView(
            'bdPhoneSupport_ViewPublic_Account_Phones',
            'bdPhoneSupport_account_phones',
            $viewParams
        ));
    }

    protected function _bdPhoneSupport_responseRedirect($noRedirect, $type, $target, $message)
    {
        if ($noRedirect) {
            return $this->responseMessage($message);
        }

        return $this->responseRedirect($type, $target, $message);
    }
}