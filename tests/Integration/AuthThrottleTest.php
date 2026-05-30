<?php

declare(strict_types=1);

namespace Moncine\Tests\Integration;

use Moncine\LoginThrottle;
use Moncine\PasswordResetThrottle;
use Moncine\RegistrationThrottle;
use Moncine\Tests\Support\MoncineTestCase;

final class AuthThrottleTest extends MoncineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.50';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP']);
        LoginThrottle::resetForTests();
        RegistrationThrottle::resetForTests();
        PasswordResetThrottle::resetForTests();
    }

    public function testLoginThrottleBlocksByIpAfterFailuresWithFreshSession(): void
    {
        $email = 'victim@throttle.test';

        for ($i = 0; $i < 8; $i++) {
            LoginThrottle::recordFailure($email);
        }

        $this->assertTrue(LoginThrottle::isBlocked($email));

        // Nouvelle session : le plafond IP serveur doit toujours bloquer.
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $this->startSession();

        $this->assertTrue(LoginThrottle::isBlocked($email));
        $this->assertGreaterThan(0, LoginThrottle::secondsUntilUnblock($email));
    }

    public function testLoginSuccessClearsBucketButNotIpWideLockout(): void
    {
        $email = 'clear@throttle.test';

        for ($i = 0; $i < 3; $i++) {
            LoginThrottle::recordFailure($email);
        }

        LoginThrottle::clearOnSuccess($email);
        $this->assertFalse(LoginThrottle::isBlocked($email));
    }

    public function testRegistrationThrottlePersistsAcrossSessions(): void
    {
        $email = 'reg@throttle.test';

        for ($i = 0; $i < 5; $i++) {
            RegistrationThrottle::recordAttempt($email);
        }

        $_SESSION = [];
        $this->startSession();

        $this->assertTrue(RegistrationThrottle::isBlocked($email));
    }

    public function testPasswordResetThrottlePersistsAcrossSessions(): void
    {
        $email = 'reset@throttle.test';

        for ($i = 0; $i < 5; $i++) {
            PasswordResetThrottle::recordAttempt($email);
        }

        $_SESSION = [];
        $this->startSession();

        $this->assertTrue(PasswordResetThrottle::isBlocked($email));
    }
}
