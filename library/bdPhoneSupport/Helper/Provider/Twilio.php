<?php

class bdPhoneSupport_Helper_Provider_Twilio
{
    const API_ROOT = 'https://api.twilio.com/2010-04-01';

    public static function computeSignature(array $config, $url, $data)
    {
        // https://www.twilio.com/docs/api/security
        ksort($data);
        foreach ($data as $key => $value) {
            $url = $url . $key . $value;
        }
        $hmac = hash_hmac('sha1', $url, $config['authToken'], true);
        return base64_encode($hmac);
    }

    public static function postMessage(array $config, $phoneNumber, $body)
    {
        $params = array(
            'To' => bdPhoneSupport_Helper_PhoneNumber::standardize($phoneNumber),
            'Body' => strval($body)
        );

        if (!empty($config['serviceSid'])) {
            $params['MessagingServiceSid'] = $config['serviceSid'];
        } elseif (!empty($config['senderId'])) {
            $params['From'] = $config['senderId'];
        } else {
            return false;
        }

        $statusCallbackLink = XenForo_Link::buildPublicLink('canonical:misc/twilio/status-callback');
        if (strpos($statusCallbackLink, 'localhost') === false) {
            $params['StatusCallback'] = $statusCallbackLink;
        }

        $twilioResponse = self::_getTwilioResponse($config,
            sprintf('Accounts/%s/Messages', $config['accountSid']), $params);

        if (empty($twilioResponse)
            || empty($twilioResponse->Message)
        ) {
            return false;
        }

        /** @noinspection PhpUndefinedFieldInspection */
        return array(
            'provider_id' => 'twilio_' . $twilioResponse->Message->Sid,
        );
    }

    protected static function _getTwilioResponse(
        array $config,
        $uri,
        $params = null,
        $method = null,
        array $options = array()
    ) {
        $options += array(
            'username' => $config['accountSid'],
            'password' => $config['authToken']
        );

        if (empty($options['username'])
            || empty($options['password'])
        ) {
            return false;
        }

        $result = bdPhoneSupport_Helper_Http::makeRequest(
            sprintf('%s/%s', self::API_ROOT, $uri), $params, $method, $options);

        if ($result['status'] < 200 && $result['status'] >= 300) {
            return false;
        }

        $document = Zend_Xml_Security::scan($result['body']);
        if (empty($document)
            || $document->getName() !== 'TwilioResponse'
        ) {
            return false;
        }

        return $document;
    }
}