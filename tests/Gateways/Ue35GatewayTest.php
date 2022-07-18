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
use duan617\EasySms\Gateways\Ue35Gateway;
use duan617\EasySms\Message;
use duan617\EasySms\PhoneNumber;
use duan617\EasySms\Support\Config;
use duan617\EasySms\Tests\TestCase;

class Ue35GatewayTest extends TestCase
{
    public function testSend()
    {
        $config = [
            'debug' => false,
            'is_sub_account' => false,
            'username' => 'mock-app-id',
            'userpwd' => '',
        ];
        $gateway = \Mockery::mock(Ue35Gateway::class.'[request]', [$config])->shouldAllowMockingProtectedMethods();

        $gateway->shouldReceive('request')->with(
            'get',
            \Mockery::on(function ($api) {
                return 0 === strpos($api, Ue35Gateway::getEndpointUri());
            }),
            \Mockery::on(function ($params) {
                return true;
            })
        )
        ->andReturn([
            'errorcode' => Ue35Gateway::SUCCESS_CODE,
        ], [
            'errorcode' => 100,
            'message' => 'error',
        ])->twice();

        $message = new Message(['content' => 'content']);
        $config = new Config($config);

        $this->assertSame([
             'errorcode' => Ue35Gateway::SUCCESS_CODE,
        ], $gateway->send(new PhoneNumber(18188888888), $message, $config));

        $this->expectException(GatewayErrorException::class);
        $this->expectExceptionCode(100);
        $this->expectExceptionMessage('error');

        $gateway->send(new PhoneNumber(18188888888), $message, $config);
    }
}
