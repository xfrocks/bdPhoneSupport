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

        /**
         * Please consider updating
         * @see bdPhoneSupport_Integration::showPrimaryVerifyNotice
         * if the below checks are changed / updated for best UX
         */
        if ($viewingUser['user_state'] !== 'valid') {
            $errorPhraseKey = new XenForo_Phrase('bdPhoneSupport_error_cannot_send_code_invalid_account');
            return false;
        }

        $codeMax = XenForo_Permission::hasPermission($viewingUser['permissions'],
            'general', 'bdPhoneSupport_codeMax');
        if ($codeMax === 0) {
            $errorPhraseKey = new XenForo_Phrase('bdPhoneSupport_error_cannot_send_code_invalid_account');
            return false;
        }
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
        if ($codePerDay === 0) {
            $errorPhraseKey = new XenForo_Phrase('bdPhoneSupport_error_cannot_send_code_invalid_account');
            return false;
        }
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
                // new XenForo_Phrase('bdPhoneSupport_error_cannot_send_code_verified_someone_else')
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

    public function verifyCode($userId, $phoneNumber, $codeText, &$errorPhraseKey = null)
    {
        $codeText = $this->_cleanCodeText($codeText);

        $code = null;
        $verifiableCodes = $this->getVerifiableCodes($userId, $phoneNumber);
        foreach ($verifiableCodes as $verifiableCode) {
            if (utf8_strtoupper($verifiableCode['code_text']) === utf8_strtoupper($codeText)) {
                $code = $verifiableCode;
            }
        }

        if (empty($code)) {
            $this->_getDb()->insert('xf_bdphonesupport_code', array(
                'user_id' => $userId,
                'phone_number' => $phoneNumber,
                'code_text' => $codeText,
                'generate_date' => 0,
                'verify_date' => XenForo_Application::$time
            ));
            // new XenForo_Phrase('bdPhoneSupport_error_cannot_verify_code_not_found')
            $errorPhraseKey = 'bdPhoneSupport_error_cannot_verify_code_not_found';
            return false;
        }

        $codeData = unserialize($code['data']);
        $codeData['verifyBacktrace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $codeData['verifySessionData'] = XenForo_Application::getSession()->getAll();

        $this->_getDb()->update('xf_bdphonesupport_code',
            array(
                'verify_date' => XenForo_Application::$time,
                'data' => serialize($codeData)
            ),
            array('code_id = ?' => $code['code_id']));

        return true;
    }

    public function getVerifiableCodes($userId, $phoneNumber)
    {
        $codes = $this->fetchAllKeyed('
            SELECT *
            FROM `xf_bdphonesupport_code`
            WHERE
                user_id = ?
                AND phone_number = ?
                AND generate_date > ?
                AND verify_date = 0
        ', 'code_id', array(
            $userId,
            bdPhoneSupport_Helper_PhoneNumber::standardize($phoneNumber),
            XenForo_Application::$time - bdPhoneSupport_Option::get('codeTtlSeconds')
        ));

        return $codes;
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
        $codeData = array(
            'generateBacktrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            'generateSessionData' => XenForo_Application::getSession()->getAll()
        );

        /** @var bdPhoneSupport_Model_Sms $smsModel */
        $smsModel = $this->getModelFromCache('bdPhoneSupport_Model_Sms');

        $codeData['smsResult'] = $smsModel->send($phoneNumber,
            new XenForo_Phrase('bdPhoneSupport_sms_hi_x_verify_y_code_x',
                array(
                    'username_censored' => $this->_censorForSms($userName),
                    'phone_number_censored' => $this->_censorForSms(
                        bdPhoneSupport_Helper_PhoneNumber::standardize($phoneNumber)),
                    'code_formatted' => $this->_formatCodeTextForSms($codeText),
                    'board_title' => XenForo_Application::getOptions()->get('boardTitle')
                )
            )
        );

        $this->_getDb()->insert('xf_bdphonesupport_code', array(
            'user_id' => $userId,
            'phone_number' => $phoneNumber,
            'code_text' => $codeText,
            'generate_date' => XenForo_Application::$time,
            'data' => serialize($codeData)
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

    /**
     * @param string $codeText
     * @return string
     */
    protected function _cleanCodeText($codeText)
    {
        return preg_replace('#[^0-9]#', '', $codeText);
    }

    /**
     * @param string $input
     * @return string
     */
    protected function _censorForSms($input)
    {
        static $censorLength = 3;

        $censored = $input;
        if (utf8_strlen($censored) > $censorLength) {
            $censored = utf8_substr($input, 0, -$censorLength) . str_repeat('x', $censorLength);
        } else {
            $censored = str_repeat('x', $censorLength);
        }

        return $censored;
    }

    /**
     * @param string $codeText
     * @return string
     */
    protected function _formatCodeTextForSms($codeText)
    {
        return trim(preg_replace('#[^ ]{3}#', '$0 ', $codeText));
    }
}