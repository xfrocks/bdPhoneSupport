<?php

class bdPhoneSupport_Helper_Http
{
    public static function makeRequest($uri, $params = null, $method = null, array $options = array())
    {
        $options += array(
            'username' => '',
            'password' => ''
        );

        if ($method === null && is_array($params)) {
            $method = 'POST';
        }

        $client = XenForo_Helper_Http::getClient($uri);

        if (!empty($params)) {
            if ($method === 'GET') {
                $client->setParameterGet($params);
            } elseif ($method === 'POST') {
                $client->setParameterPost($params);
            } else {
                $client->setRawData($params);
            }
        }

        if (!empty($options['username'])
            && !empty($options['password'])
        ) {
            $client->setAuth($options['username'], $options['password']);
        }

        $response = $client->request($method);
        $status = $response->getStatus();
        $headers = $response->getHeaders();
        $body = $response->getBody();

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__METHOD__, sprintf("%s %s %s -> %s\n\t%s\n\t%s",
                $method, $uri, json_encode($params),
                $status, json_encode($headers), $body));
        }

        return array(
            'uri' => $uri,
            'params' => $params,
            'options' => $options,

            'status' => $status,
            'headers' => $headers,
            'body' => $body
        );
    }
}