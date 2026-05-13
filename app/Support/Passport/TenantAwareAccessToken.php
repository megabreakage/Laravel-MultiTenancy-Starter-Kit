<?php

declare(strict_types=1);

namespace App\Support\Passport;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Laravel\Passport\Bridge\AccessToken;
use League\OAuth2\Server\CryptKeyInterface;

final class TenantAwareAccessToken extends AccessToken
{
    public ?string $tenantId = null;

    private CryptKeyInterface $storedPrivateKey;

    public function setPrivateKey(CryptKeyInterface $privateKey): void
    {
        $this->storedPrivateKey = $privateKey;
        parent::setPrivateKey($privateKey);
    }

    public function toString(): string
    {
        $keyContents = $this->storedPrivateKey->getKeyContents();

        $config = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($keyContents, $this->storedPrivateKey->getPassPhrase() ?? ''),
            InMemory::plainText('empty', 'empty')
        );

        $builder = $config->builder()
            ->permittedFor($this->getClient()->getIdentifier())
            ->identifiedBy($this->getIdentifier())
            ->issuedAt(new DateTimeImmutable())
            ->canOnlyBeUsedAfter(new DateTimeImmutable())
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo((string) ($this->getUserIdentifier() ?? $this->getClient()->getIdentifier()))
            ->withClaim('scopes', $this->getScopes());

        if ($this->tenantId !== null) {
            $builder = $builder->withClaim('tenant_id', $this->tenantId);
        }

        return $builder->getToken($config->signer(), $config->signingKey())->toString();
    }
}
