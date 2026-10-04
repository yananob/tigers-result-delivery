<?php

namespace Tests;

use App\AppConfig;
use PHPUnit\Framework\TestCase;

class AppConfigTest extends TestCase
{
    private $originalAppEnv;

    protected function setUp(): void
    {
        $this->originalAppEnv = getenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        if ($this->originalAppEnv !== false) {
            putenv("APP_ENV={$this->originalAppEnv}");
        } else {
            putenv('APP_ENV');
        }
    }

    public function testGetEnvironmentDefault(): void
    {
        putenv('APP_ENV');
        $this->assertEquals('development', AppConfig::getEnvironment());
        $this->assertTrue(AppConfig::isDevelopment());
        $this->assertFalse(AppConfig::isTest());
    }

    public function testGetEnvironmentProduction(): void
    {
        putenv('APP_ENV=production');
        $this->assertEquals('production', AppConfig::getEnvironment());
        $this->assertEquals('tigers-result-delivery', AppConfig::getFirestoreRootCollection());
        $this->assertEquals('bball', AppConfig::getLineDeliverTarget());
        $this->assertFalse(AppConfig::isDevelopment());
        $this->assertFalse(AppConfig::isTest());
    }

    public function testGetEnvironmentTest(): void
    {
        putenv('APP_ENV=test');
        $this->assertEquals('test', AppConfig::getEnvironment());
        $this->assertEquals('tigers-result-delivery-test', AppConfig::getFirestoreRootCollection());
        $this->assertEquals('nobu', AppConfig::getLineDeliverTarget());
        $this->assertTrue(AppConfig::isDevelopment());
        $this->assertTrue(AppConfig::isTest());
    }
}
