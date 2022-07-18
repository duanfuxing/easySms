<?php

/*
 * This file is part of the overtrue/easy-sms.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace duan617\EasySms\Tests\Gateways;

use duan617\EasySms\Exceptions\GatewayErrorException;
use duan617\EasySms\Gateways\LuosimaoGateway;
use duan617\EasySms\Message;
use duan617\EasySms\PhoneNumber;
use duan617\EasySms\Support\Config;
use duan617\EasySms\Tests\TestCase;

class LuosimaoGatewayTest extends TestCase
{
    public function testSend()
    {
        $config = [
            'api_key' => 'mock-api-key',
        ];
        $gateway = \Mockery::mock(LuosimaoGateway::class.'[post]', [$config])->shouldAllowMockingProtectedMethods();

        $gateway->shouldReceive('post')->with('https://sms-api.luosimao.com/v1/send.json', [
            'mobile' => 18188888888,
            'message' => '【overtrue】This is a test message.',
        ], [
            'Authorization' => 'Basic '.base64_encode('api:key-mock-api-key'),
        ])->andReturn([
            'error' => 0,
            'msg' => 'success',
        ], [
            'error' => 10000,
            'msg' => 'mock-err-msg',
        ])->times(2);

        $message = new Message(['content' => '【overtrue】This is a test message.']);
        $config = new Config($config);

        $this->assertSame([
            'error' => 0,
            'msg' => 'success',
        ], $gateway->send(new PhoneNumber(18188888888), $message, $config));

        $this->expectException(GatewayErrorException::class);
        $this->expectExceptionCode(10000);
        $this->expectExceptionMessage('mock-err-msg');

        $gateway->send(new PhoneNumber(18188888888), $message, $config);
    }
}
