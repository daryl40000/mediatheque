<?php

declare(strict_types=1);

namespace Moncine\Tests\Unit;

use Moncine\Exception\ValidationException;
use Moncine\Tests\Support\MoncineTestCase;
use Moncine\UtilisateurRepository;
use Moncine\Validator\UserAccountValidator;
use Moncine\Validator\Validator;

final class ValidatorTest extends MoncineTestCase
{
    public function testRequiredFailsOnEmpty(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Champ obligatoire.');
        Validator::of('  ')->trim()->required()->orThrow();
    }

    public function testEmailNormalizesAndAcceptsValid(): void
    {
        $email = Validator::of('  Alice@Example.COM  ')->trim()->lower()->email()->orThrow();
        $this->assertSame('alice@example.com', $email);
    }

    public function testEmailRejectsInvalid(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Adresse e-mail invalide.');
        Validator::of('pas-un-email')->trim()->email()->orThrow();
    }

    public function testMinLengthUsesUtf8Characters(): void
    {
        // « été » = 3 caractères UTF-8
        $this->assertSame('été', Validator::of('été')->minLength(3)->orThrow());

        $this->expectException(ValidationException::class);
        Validator::of('é')->minLength(2, 'Trop court.')->orThrow();
    }

    public function testResultReturnsMessageWithoutThrowing(): void
    {
        $result = Validator::of('')->required('Manquant.')->result();
        $this->assertSame('Manquant.', $result);
        $this->assertTrue(Validator::of('ok')->required()->result() === true);
    }

    public function testChainStopsAtFirstError(): void
    {
        $v = Validator::of('')->required('Vide.')->email('Email.');
        $this->assertSame('Vide.', $v->errorMessage());
    }

    public function testUserAccountValidatorEmailMessageUnchanged(): void
    {
        $normalized = null;
        $this->assertSame('Adresse e-mail invalide.', UserAccountValidator::checkEmail('x', $normalized));
        $this->assertNull($normalized);

        $this->assertTrue(UserAccountValidator::checkEmail('  a@b.co  ', $normalized) === true);
        $this->assertSame('a@b.co', $normalized);
    }

    public function testUserAccountValidatorPasswordMessageUnchanged(): void
    {
        $msg = UtilisateurRepository::passwordValidationMessage();
        $this->assertSame($msg, UserAccountValidator::checkPasswordLength('short'));
        $this->assertTrue(UserAccountValidator::checkPasswordLength('TestPass123!') === true);
    }
}
