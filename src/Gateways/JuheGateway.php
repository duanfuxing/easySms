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
 * Class JuheGateway.
 *
 * @see https://www.juhe.cn/docs/api/id/54
 */
class JuheGateway extends Gateway
{
    use HasHttpRequest;

    const ENDPOINT_URL = 'http://v.juhe.cn/sms/send';

    const ENDPOINT_FORMAT = 'json';

    /**
     * @param \duan617\EasySms\Contracts\PhoneNumberInterface $to
     * @param \duan617\EasySms\Contracts\MessageInterface     $message
     * @param \duan617\EasySms\Support\Config                 $config
     *
     * @return array
     *
     * @throws \duan617\EasySms\Exceptions\GatewayErrorException ;
     */
    public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        $params = [
            'mobile' => $to->getNumber(),
            'tpl_id' => $message->getTemplate($this),
            'tpl_value' => $this->formatTemplateVars($message->getData($this)),
            'dtype' => self::ENDPOINT_FORMAT,
            'key' => $config->get('app_key'),
        ];

        $result = $this->get(self::ENDPOINT_URL, $params);

        if ($result['error_code']) {
            throw new GatewayErrorException($result['reason'], $result['error_code'], $result);
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
            $formatted[sprintf('#%s#', trim($key, '#'))] = $value;
        }

        return http_build_query($formatted);
    }
}
