<?php

/*
 * This file is part of the overtrue/easy-sms.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace duan617\EasySms\Gateways;

use duan617\EasySms\Contracts\MessageInterface;
use duan617\EasySms\Contracts\PhoneNumberInterface;
use duan617\EasySms\Exceptions\GatewayErrorException;
use duan617\EasySms\Support\Config;
use duan617\EasySms\Traits\HasHttpRequest;

/**
 * Class SendcloudGateway.
 *
 * @see http://sendcloud.sohu.com/doc/sms/
 */
class SendcloudGateway extends Gateway
{
    use HasHttpRequest;

    const ENDPOINT_TEMPLATE = 'http://www.sendcloud.net/smsapi/%s';

    /**
     * Send a short message.
     *
     * @param \duan617\EasySms\Contracts\PhoneNumberInterface $to
     * @param \duan617\EasySms\Contracts\MessageInterface     $message
     * @param \duan617\EasySms\Support\Config                 $config
     *
     * @return array
     *
     * @throws \duan617\EasySms\Exceptions\GatewayErrorException
     */
    public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        $params = [
            'smsUser' => $config->get('sms_user'),
            'templateId' => $message->getTemplate($this),
            'msgType' => $to->getIDDCode() ? 2 : 0,
            'phone' => $to->getZeroPrefixedNumber(),
            'vars' => $this->formatTemplateVars($message->getData($this)),
        ];

        if ($config->get('timestamp', false)) {
            $params['timestamp'] = time() * 1000;
        }

        $params['signature'] = $this->sign($params, $config->get('sms_key'));

        $result = $this->post(sprintf(self::ENDPOINT_TEMPLATE, 'send'), $params);

        if (!$result['result']) {
            throw new GatewayErrorException($result['message'], $result['statusCode'], $result);
        }

        return $result;
    }

    /**
     * @param array $vars
     *
     * @return string
     */
    protected function formatTemplateVars(array $vars)
    {
        $formatted = [];

        foreach ($vars as $key => $value) {
            $formatted[sprintf('%%%s%%', trim($key, '%'))] = $value;
        }

        return json_encode($formatted, JSON_FORCE_OBJECT);
    }

    /**
     * @param array  $params
     * @param string $key
     *
     * @return string
     */
    protected function sign($params, $key)
    {
        ksort($params);

        return md5(sprintf('%s&%s&%s', $key, urldecode(http_build_query($params)), $key));
    }
}
