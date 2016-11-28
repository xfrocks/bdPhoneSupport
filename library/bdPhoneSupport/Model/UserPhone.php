<?php

class bdPhoneSupport_Model_UserPhone extends XenForo_Model
{
    public function getUsersByPhoneNumber($phoneNumber, $notUserId = 0)
    {
        return $this->fetchAllKeyed('
            SELECT *
            FROM `xf_bdphonesupport_user_phone`
            WHERE phone_number = ?
                AND user_id <> ?
                AND verify_date > 0
                AND remove_date = 0
        ', 'user_id', array(
            bdPhoneSupport_Helper_PhoneNumber::standardize($phoneNumber),
            $notUserId
        ));
    }

    public function updateUserPhones($userId, array $phoneNumbers)
    {
        $userPhones = $this->_getDb()->fetchAll('
            SELECT *
            FROM `xf_bdphonesupport_user_phone`
            WHERE user_id = ?
                AND verify_date > 0
                AND remove_date = 0
        ', $userId);

        $phoneNumbers = array_map(array('bdPhoneSupport_Helper_PhoneNumber', 'standardize'), $phoneNumbers);
        $removedUserPhoneIds = array();
        $newPhoneNumbers = array();

        foreach ($userPhones as $userPhone) {
            $userPhoneNumberFound = false;
            foreach ($phoneNumbers as $phoneNumber) {
                if ($userPhone['phone_number'] === $phoneNumber) {
                    $userPhoneNumberFound = true;
                }
            }

            if (!$userPhoneNumberFound) {
                $removedUserPhoneIds[] = $userPhone['user_phone_id'];
            }
        }

        foreach ($phoneNumbers as $phoneNumber) {
            $phoneNumberFound = false;
            foreach ($userPhones as $userPhone) {
                if ($phoneNumber === $userPhone['phone_number']) {
                    $phoneNumberFound = true;
                }
            }

            if (!$phoneNumberFound) {
                $newPhoneNumbers[] = $phoneNumber;
            }
        }

        $db = $this->_getDb();

        XenForo_Db::beginTransaction($db);

        foreach ($removedUserPhoneIds as $needDeleteId) {
            $db->update('xf_bdphonesupport_user_phone',
                array('remove_date' => XenForo_Application::$time),
                array('user_phone_id = ?' => $needDeleteId));
        }

        foreach ($newPhoneNumbers as $newPhoneNumber) {
            $db->insert('xf_bdphonesupport_user_phone', array(
                'user_id' => $userId,
                'phone_number' => $newPhoneNumber,
                'verify_date' => XenForo_Application::$time
            ));
        }

        XenForo_Db::commit($db);

        return true;
    }
}