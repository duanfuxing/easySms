<?php

namespace duan617\EasySms\Gateways;

use Dotenv\Exception\InvalidCallbackException;
use duan617\EasySms\Contracts\MessageInterface;
use duan617\EasySms\Contracts\PhoneNumberInterface;
use duan617\EasySms\Exceptions\GatewayErrorException;
use duan617\EasySms\Support\Config;
use duan617\EasySms\Traits\HasHttpRequest;

/**
 * Class YunLiangGateway
 * @author Duan
 * @package duan617\EasySms\Gateways
 */
class YunLiangGateway extends Gateway
{
    use HasHttpRequest;

    const ENDPOINT_TEMPLATE = 'http://dx.yllmob.cn/verify/send';

    const ENDPOINT_ACTION = 'sendContent';

    const SUCCESS_CODE = 0000;

    /**
     * @param PhoneNumberInterface $to
     * @param MessageInterface $message
     * @param Config $config
     * @return array|\Psr\Http\Message\ResponseInterface|string
     * @throws GatewayErrorException
     */
    public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        $this->_checkParams($config);

        $data = $message->getData($this);
        $action = isset($data['action']) ? $data['action'] : self::ENDPOINT_ACTION;

        switch ($action) {
            case 'sendContent':
                $params = $this->buildSendContentParams($to, $message, $config);

                break;
            case 'verifyCode':
                $params = $this->buildVerifyCodeParams($to, $message, $config);

                break;
            case 'sendMarketContent':
                $params = $this->buildMarketContentParams($to, $message, $config);

                break;
            default:
                throw new GatewayErrorException(sprintf('action: %s not supported', $action), 0);
        }

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $result = $this->postJson(self::ENDPOINT_TEMPLATE, $params, $headers);

        $result = json_decode(trim($result, '"'));

        if (!isset($result->result) || $result->result != self::SUCCESS_CODE) {
            throw new GatewayErrorException($result->msg, $result['error'], $result);
        }

        return $result;
    }

    protected function _checkParams(Config $config)
    {
        if (empty($config->get('app_id')) || empty($config->get('app_secret'))) {
            throw new InvalidCallbackException('app_id or app_secret not empty');
        }
    }

    /**
     * @param PhoneNumberInterface $to
     * @param MessageInterface $message
     * @param Config $config
     * @return array|void
     * @throws GatewayErrorException
     * @todo 未实现
     */
    public function buildSendContentParams(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        throw new GatewayErrorException('当前方法未实现', 0);
        return;
        $data = $message->getData($this);

        if (!array_key_exists('code', $data)) {
            throw new GatewayErrorException('"code" cannot be empty', 0);
        }

        if (!array_key_exists('time', $data)) {
            throw new GatewayErrorException('"time" cannot be empty', 0);
        }

        if (empty($config->get('verifyCode_template'))) {
            throw new InvalidCallbackException('verifyCode_template not empty');
        }

        if (empty($message->getContent($this))) {
            throw new InvalidCallbackException('content not empty');
        }

        $timestamp = date('YmdHis');

        return [
            'tempid'    => 'NO',
            'to'        => $to->getNumber(),
            'sign'      => md5($config->get('app_id') . $timestamp . $config->get('app_secret')),
            'appid'     => $config->get('app_id'),
            'sid'       => time(),
            'type'      => 0,
            'data'      => [
                $config->get('verifyCode_template') . $this->buildEndpoint($message->getContent($this), $data['code'], $data['time'])
            ],
            'timestamp' => $timestamp,
            'url'       => ''
        ];
    }

    /**
     * @param $resource
     * @param $function
     *
     * @return string
     */
    protected function buildEndpoint($content, $code, $time)
    {
        return sprintf($content, $code, $time);
    }

    /**
     * @param PhoneNumberInterface $to
     * @param MessageInterface $message
     *
     * @return array
     *
     * @throws GatewayErrorException
     */
    public function buildVerifyCodeParams(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        $data = $message->getData($this);

        if (!array_key_exists('code', $data)) {
            throw new GatewayErrorException('"code" cannot be empty', 0);
        }

        if (!array_key_exists('time', $data)) {
            throw new GatewayErrorException('"time" cannot be empty', 0);
        }

        if (empty($config->get('verifyCode_template'))) {
            throw new InvalidCallbackException('verifyCode_template not empty');
        }

        if (empty($message->getContent($this))) {
            throw new InvalidCallbackException('content not empty');
        }

        $timestamp = date('YmdHis');

        return [
            'tempid'    => 'NO',
            'to'        => $to->getNumber(),
            'sign'      => md5($config->get('app_id') . $timestamp . $config->get('app_secret')),
            'appid'     => $config->get('app_id'),
            'sid'       => time(),
            'type'      => 0,
            'data'      => [
                $config->get('verifyCode_template') . $this->buildEndpoint($message->getContent($this), $data['code'], $data['time'])
            ],
            'timestamp' => $timestamp,
            'url'       => ''
        ];
    }

    /**
     * @param PhoneNumberInterface $to
     * @param MessageInterface $message
     * @param Config $config
     * @return array
     */
    public function buildMarketContentParams(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        if (empty($message->getContent($this))) {
            throw new InvalidCallbackException('content not empty');
        }

        $timestamp = date('YmdHis');

        return [
            'tempid'    => 'NO',
            'to'        => $to->getNumber(),
            'sign'      => md5($config->get('app_id') . $timestamp . $config->get('app_secret')),
            'appid'     => $config->get('app_id'),
            'sid'       => time(),
            'type'      => 0,
            'data'      => [
                $config->get('verifyCode_template') . $message->getContent($this)
            ],
            'timestamp' => $timestamp,
            'url'       => ''
        ];
    }

}
