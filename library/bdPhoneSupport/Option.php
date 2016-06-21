<?php

class bdPhoneSupport_Option
{
    public static function get($key, $subKey = null)
    {
        if (is_array($subKey)) {
            if (count($subKey) === 1) {
                $subKey = reset($subKey);
            } else {
                $subKey = null;
            }
        }

        $xenOptions = XenForo_Application::getOptions();

        return $xenOptions->get('bdPhoneSupport_' . $key, $subKey);
    }
}