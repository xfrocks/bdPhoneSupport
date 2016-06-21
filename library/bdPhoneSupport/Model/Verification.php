<?php

class bdPhoneSupport_Model_Verification extends XenForo_Model
{
    /**
     * @param string $phoneNumber
     * @param string|null $errorPhraseKey
     * @param array|null $viewingUser
     * @return bool
     */
    public function requestVerify($phoneNumber, &$errorPhraseKey = null, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $codeMax = XenForo_Permission::hasPermission($viewingUser['permissions'],
            'general', 'bdPhoneSupport_codeMax');
        if ($codeMax > -1) {
            $codeCount = $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `xf_bdphonesupport_code`
                WHERE user_id = ?
            ', $viewingUser['user_id']);

            if ($codeCount >= $codeMax) {
                $errorPhraseKey = new XenForo_Phrase('bdPhoneSupport_error_cannot_send_code_too_many_attempts_x_of_y',
                    array(
                        'limit' => $codeMax,
                        'usage' => $codeCount,
                    ));
                return false;
            }
        }

        $codePerDay = XenForo_Permission::hasPermission($viewingUser['permissions'],
            'general', 'bdPhoneSupport_codePerDay');
        if ($codePerDay > -1) {
            $codeDayCount = $this->_getDb()->fetchOne('
                SELECT COUNT(*)
                FROM `xf_bdphonesupport_code`
                WHERE user_id = ? AND generate_date > ?
            ', array($viewingUser['user_id'], XenForo_Application::$time - 86400));

            if ($codeDayCount >= $codePerDay) {
                $errorPhraseKey = new XenForo_Phrase('bdPhoneSupport_error_cannot_send_code_too_many_attempts_today_x_of_y',
                    array(
                        'limit' => $codePerDay,
                        'usage' => $codeDayCount,
                    ));
                return false;
            }
        }

        if (bdPhoneSupport_Option::get('oneUserPerPhone')) {
            /** @var bdPhoneSupport_Model_UserPhone $userPhoneModel */
            $userPhoneModel = $this->getModelFromCache('bdPhoneSupport_Model_UserPhone');
            $users = $userPhoneModel->getUsersByPhoneNumber($phoneNumber, $viewingUser['user_id']);
            if (count($users) > 0) {
                $errorPhraseKey = 'bdPhoneSupport_error_cannot_send_code_verified_someone_else';
                return false;
            }
        }

        try {
            $this->_sendVerificationCode($viewingUser['user_id'], $phoneNumber, $viewingUser['username']);
            return true;
        } catch (Exception $e) {
            // new XenForo_Phrase('bdPhoneSupport_error_cannot_send_code_exception')
            $errorPhraseKey = 'bdPhoneSupport_error_cannot_send_code_exception';
            XenForo_Error::logException($e, false);
            return false;
        }
    }

    public function verifyCode($userId, $phoneNumber, $codeText)
    {
        $codeId = $this->_getDb()->fetchOne('
            SELECT code_id
            FROM `xf_bdphonesupport_code`
            WHERE
                user_id = ?
                AND phone_number = ?
                AND code_text = ?
                AND generate_date > ?
                AND verify_date = 0
        ', array(
            $userId,
            bdPhoneSupport_Helper_PhoneNumber::standardize($phoneNumber),
            $codeText,
            XenForo_Application::$time - bdPhoneSupport_Option::get('codeTtlSeconds')
        ));

        if (empty($codeId)) {
            $this->_getDb()->insert('xf_bdphonesupport_code', array(
                'user_id' => $userId,
                'phone_number' => $phoneNumber,
                'code_text' => $codeText,
                'generate_date' => 0,
                'verify_date' => XenForo_Application::$time
            ));
            return false;
        }

        $this->_getDb()->update('xf_bdphonesupport_code',
            array('verify_date' => XenForo_Application::$time),
            array('code_id = ?' => $codeId));

        return true;
    }

    /**
     * @param int $userId
     * @param string $phoneNumber
     * @param string $userName
     * @throws XenForo_Exception
     * @throws Zend_Db_Adapter_Exception
     */
    protected function _sendVerificationCode($userId, $phoneNumber, $userName)
    {
        $codeText = $this->_generateCodeTextForUserAndPhoneNumber($userId, $phoneNumber);

        /** @var bdPhoneSupport_Model_Sms $smsModel */
        $smsModel = $this->getModelFromCache('bdPhoneSupport_Model_Sms');
        $smsModel->send($phoneNumber, new XenForo_Phrase('bdPhoneSupport_sms_hi_x_verify_y_code_x', array(
            'username' => $userName,
            'phone_number' => bdPhoneSupport_Helper_PhoneNumber::standardize($phoneNumber),
            'code_text' => $codeText,
            'board_title' => XenForo_Application::getOptions()->get('boardTitle')
        )));

        $this->_getDb()->insert('xf_bdphonesupport_code', array(
            'user_id' => $userId,
            'phone_number' => $phoneNumber,
            'code_text' => $codeText,
            'generate_date' => XenForo_Application::$time
        ));
    }

    /** @noinspection PhpInconsistentReturnPointsInspection
     * @param int $userId
     * @param string $phoneNumber
     * @return string
     * @throws XenForo_Exception
     */
    protected function _generateCodeTextForUserAndPhoneNumber($userId, $phoneNumber)
    {
        if (empty($userId)) {
            throw new XenForo_Exception('Cannot generate code text for non-member.');
        }

        if (strlen($phoneNumber) == 0) {
            throw new XenForo_Exception('Cannot generate code text without phone number.');
        }

        $existingCodeTexts = $this->_getDb()->fetchCol('
            SELECT code_text
            FROM `xf_bdphonesupport_code`
            WHERE user_id = ? AND phone_number = ?
        ', array($userId, $phoneNumber));

        $attempts = 0;
        while (true) {
            $codeText = $this->_generateCodeText();
            if (!in_array($codeText, $existingCodeTexts, true)) {
                return $codeText;
            }

            if ($attempts > 10) {
                throw new XenForo_Exception(sprintf('Unable to generate verification code for '
                    . '$userId=%d, $phoneNumber=%s, $attempts=%d', $userId, $phoneNumber, $attempts));
            }
            $attempts++;
        }
    }

    /**
     * @return string
     */
    protected function _generateCodeText()
    {
        return strval(rand(100000, 999999));
    }
}