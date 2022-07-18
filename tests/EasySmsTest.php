<?php

/*
 * This file is part of the overtrue/easy-sms.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace duan617\EasySms\Tests;

use duan617\EasySms\Contracts\GatewayInterface;
use duan617\EasySms\Contracts\MessageInterface;
use duan617\EasySms\Contracts\PhoneNumberInterface;
use duan617\EasySms\EasySms;
use duan617\EasySms\Exceptions\InvalidArgumentException;
use duan617\EasySms\Gateways\AliyunGateway;
use duan617\EasySms\Message;
use duan617\EasySms\Messenger;
use duan617\EasySms\PhoneNumber;
use duan617\EasySms\Support\Config;
use RuntimeException;

class EasySmsTest extends TestCase
{
    public function testGateway()
    {
        $easySms = new EasySms([]);

        $this->assertInstanceOf(GatewayInterface::class, $easySms->gateway('error-log'));

        // invalid gateway
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class "duan617\EasySms\Gateways\NotExistsGatewayNameGateway" is a invalid easy-sms gateway.');

        $easySms->gateway('NotExistsGatewayName');
    }

    public function testGatewayNameConflicts()
    {
        $easySms = \Mockery::mock(EasySms::class.'[makeGateway]', [['default' => DummyGatewayForTest::class]]);

        $this->expectExceptionMessage('Class "duan617\EasySms\Tests\DummyGatewayNotImplementsGatewayInterface" is a invalid easy-sms gateway.');
        $easySms->makeGateway(DummyGatewayNotImplementsGatewayInterface::class, []);
    }

    public function testExtend()
    {
        $easySms = new EasySms([]);
        $easySms->extend('foo', function () {
            return new DummyGatewayForTest();
        });

        $this->assertInstanceOf(DummyGatewayForTest::class, $easySms->gateway('foo'));
    }

    public function testSend()
    {
        $messenger = \Mockery::mock(Messenger::class);
        $messenger->allows()->send(\Mockery::on(function ($number) {
            return $number instanceof PhoneNumber && !empty($number->getNumber());
        }), \Mockery::on(function ($message) {
            return $message instanceof MessageInterface && !empty($message->getContent());
        }), [])->andReturn('send-result');

        $easySms = \Mockery::mock(EasySms::class.'[getMessenger]', [['default' => DummyGatewayForTest::class]]);
        $easySms->shouldReceive('getMessenger')->andReturn($messenger);

        // simple
        $this->assertSame('send-result', $easySms->send('18888888888', ['content' => 'hello']));

        // message object
        $message = new Message(['content' => 'hello']);
        $this->assertSame('send-result', $easySms->send('18888888888', $message, []));

        // phone number object
        $number = new PhoneNumber('18888888888', 35);
        $message = new Message(['content' => 'hello']);
        $messenger = \Mockery::mock(Messenger::class);
        $messenger->expects()->send($number, $message, [])->andReturn('mock-result');
        $easySms = \Mockery::mock(EasySms::class.'[getMessenger]', [['default' => DummyGatewayForTest::class]]);
        $easySms->shouldReceive('getMessenger')->andReturn($messenger);
        $this->assertSame('mock-result', $easySms->send($number, $message));
    }

    public function testFormatMessage()
    {
        $easySms = \Mockery::mock(EasySms::class.'[formatMessage]', [[]])->makePartial()->shouldAllowMockingProtectedMethods();

        // text
        $message = $easySms->formatMessage('文本');

        $this->assertSame('文本', $message->getContent());
        $this->assertSame('文本', $message->getTemplate());

        // callback
        $message = $easySms->formatMessage([
            'content' => function () {
                return 'content';
            },
            'template' => function () {
                return 'template';
            },
            'data' => function () {
                return ['foo' => 'bar'];
            },
        ]);

        $this->assertSame('content', $message->getContent());
        $this->assertSame('template', $message->getTemplate());
        $this->assertSame(['foo' => 'bar'], $message->getData());

        $func = function () {
            return ['a' => 'b'];
        };

        $this->assertSame(['a' => 'b'], $message->setData($func)->getData());
        $this->assertSame(['c' => 'd'], $message->setData(['c' => 'd'])->getData());
    }

    public function testGetMessenger()
    {
        $easySms = new EasySms([]);

        $this->assertInstanceOf(Messenger::class, $easySms->getMessenger());
    }

    public function testFormatGateways()
    {
        $config = [
            'gateways' => [
                'foo' => [
                    'a' => 'b',
                ],
                'bar' => [
                    'c' => 'd',
                ],
            ],
        ];

        $easySms = \Mockery::mock(EasySms::class.'[formatMessage]', [$config])->makePartial()->shouldAllowMockingProtectedMethods();

        // gateway names
        $gateways = $easySms->formatGateways(['foo', 'bar']);

        $this->assertCount(2, $gateways);
        $this->arrayHasKey('foo', $gateways);
        $this->arrayHasKey('bar', $gateways);
        $this->assertSame('b', $gateways['foo']->get('a'));
        $this->assertSame('d', $gateways['bar']->get('c'));

        // gateway names && override config
        $gateways = $easySms->formatGateways(['foo', 'bar' => ['c' => 'e']]);

        $this->assertCount(2, $gateways);
        $this->arrayHasKey('foo', $gateways);
        $this->arrayHasKey('bar', $gateways);
        $this->assertSame('b', $gateways['foo']->get('a'));
        $this->assertSame('e', $gateways['bar']->get('c'));

        // gateway names && append config
        $gateways = $easySms->formatGateways(['foo' => ['f' => 'g'], 'bar' => ['c' => 'e']]);

        $this->assertCount(2, $gateways);
        $this->arrayHasKey('foo', $gateways);
        $this->arrayHasKey('bar', $gateways);
        $this->assertSame('b', $gateways['foo']->get('a'));
        $this->assertSame('g', $gateways['foo']->get('f'));
        $this->assertSame('e', $gateways['bar']->get('c'));
    }

    public function testCreateGatewayWithDefaultTimeout()
    {
        $easySms = new EasySms([
            'timeout' => 10.0,
        ]);

        $gateway = $easySms->gateway('aliyun');

        $this->assertSame(10.0, $gateway->getTimeout());

        $gateway->setTimeout(9.0);

        $this->assertSame(9.0, $gateway->getTimeout());
    }
}

class DummyGatewayNotImplementsGatewayInterface
{
}

class DummyGatewayForTest implements GatewayInterface
{
    public function getName()
    {
        return 'name';
    }

    public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        return 'send-result';
    }
}

class DummyInvalidGatewayForTest
{
    // nothing
}
