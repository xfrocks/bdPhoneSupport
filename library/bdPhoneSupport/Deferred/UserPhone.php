<?php

class bdPhoneSupport_Deferred_UserPhone extends XenForo_Deferred_Abstract
{
    public function execute(array $deferred, array $data, $targetRunTime, &$status)
    {
        $data = array_merge(array(
            'position' => 0,
            'batch' => 500
        ), $data);
        $data['batch'] = max(1, $data['batch']);

        /* @var $userModel XenForo_Model_User */
        $userModel = XenForo_Model::create('XenForo_Model_User');

        $userIds = $userModel->getUserIdsInRange($data['position'], $data['batch']);
        if (sizeof($userIds) == 0) {
            return true;
        }

        $users = $userModel->getUsersByIds($userIds, array(
            XenForo_Model_User::FETCH_USER_FULL
        ));

        foreach ($userIds AS $userId) {
            $data['position'] = $userId;

            if (!isset($users[$userId])) {
                continue;
            }
            $userRef = $users[$userId];

            bdPhoneSupport_Integration::updateUserPhones($userRef);
        }

        $actionPhrase = new XenForo_Phrase('rebuilding');
        $typePhrase = new XenForo_Phrase('users');
        $status = sprintf('%s... %s (%s)', $actionPhrase, $typePhrase, XenForo_Locale::numberFormat($data['position']));

        return $data;
    }

    public function canCancel()
    {
        return true;
    }
}