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
 * Class QcloudGateway.
 *
 * @see https://cloud.tencent.com/document/product/382/13297
 */
class QcloudGateway extends Gateway
{
    use HasHttpRequest;

    const ENDPOINT_URL = 'https://yun.tim.qq.com/v5/';

    const ENDPOINT_METHOD = 'tlssmssvr/sendsms';

    const ENDPOINT_VERSION = 'v5';

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
        $data = $message->getData($this);

        $signName = !empty($data['sign_name']) ? $data['sign_name'] : $config->get('sign_name', '');

        unset($data['sign_name']);

        $msg = $message->getContent($this);
        if (!empty($msg) && '【' != mb_substr($msg, 0, 1) && !empty($signName)) {
            $msg = '【'.$signName.'】'.$msg;
        }

        $type = !empty($data['type']) ? $data['type'] : 0;
        $params = [
            'tel' => [
                'nationcode' => $to->getIDDCode() ?: 86,
                'mobile' => $to->getNumber(),
            ],
            'type' => $type,
            'msg' => $msg,
            'time' => time(),
            'extend' => '',
            'ext' => '',
        ];
        if (!is_null($message->getTemplate($this)) && is_array($data)) {
            unset($params['msg']);
            $params['params'] = array_values($data);
            $params['tpl_id'] = $message->getTemplate($this);
            $params['sign'] = $signName;
        }
        $random = substr(uniqid(), -10);

        $params['sig'] = $this->generateSign($params, $random);

        $url = self::ENDPOINT_URL.self::ENDPOINT_METHOD.'?sdkappid='.$config->get('sdk_app_id').'&random='.$random;

        $result = $this->request('post', $url, [
            'headers' => ['Accept' => 'application/json'],
            'json' => $params,
        ]);

        if (0 != $result['result']) {
            throw new GatewayErrorException($result['errmsg'], $result['result'], $result);
        }

        return $result;
    }

    /**
     * Generate Sign.
     *
     * @param array  $params
     * @param string $random
     *
     * @return string
     */
    protected function generateSign($params, $random)
    {
        ksort($params);

        return hash('sha256', sprintf(
            'appkey=%s&random=%s&time=%s&mobile=%s',
            $this->config->get('app_key'),
            $random,
            $params['time'],
            $params['tel']['mobile']
        ), false);
    }
}
