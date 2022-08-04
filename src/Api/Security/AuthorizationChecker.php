<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
declare(strict_types=1);

namespace PrestaShop\Module\Mbo\Api\Security;

use Configuration;
use Doctrine\Common\Cache\CacheProvider;
use GuzzleHttp\Exception\GuzzleException;
use PrestaShop\Module\Mbo\Api\Exception\RetrieveNewKeyException;
use PrestaShop\Module\Mbo\Api\Exception\UnauthorizedException;
use PrestaShop\Module\Mbo\Distribution\Client;

class AuthorizationChecker
{
    /**
     * @var CacheProvider
     */
    private $cacheProvider;

    /**
     * @var Client
     */
    private $distributionClient;

    /**
     * @var string
     */
    private $keyVersionCacheIndex;

    /**
     * @var string
     */
    private $keyCacheIndex;

    public function __construct(CacheProvider $cacheProvider, Client $distributionClient)
    {
        $this->cacheProvider = $cacheProvider;
        $this->distributionClient = $distributionClient;

        $shopUuid = Configuration::get('PS_MBO_SHOP_ADMIN_UUID');
        $this->keyVersionCacheIndex = 'api_key_version_' . $shopUuid;
        $this->keyCacheIndex = 'api_key_' . $shopUuid;
    }

    public function verify(string $keyVersion, string $signature, string $message)
    {
        $storedKeyVersion = null;
        if ($this->cacheProvider->contains($this->keyVersionCacheIndex)) {
            $storedKeyVersion = $this->cacheProvider->fetch($this->keyVersionCacheIndex);
        }

        if (
            null === $storedKeyVersion ||
            $storedKeyVersion !== $keyVersion
        ) {
            // Ask for a new key and store keyVersion and Key
            $this->retrieveNewKey();
        }

        $key = $this->cacheProvider->fetch($this->keyCacheIndex);

        $verified = openssl_verify($message, base64_decode($signature), $key, OPENSSL_ALGO_SHA256);

        if (1 !== $verified) {
            throw new UnauthorizedException('Caller authorization failed');
        }
    }

    /**
     * @throws RetrieveNewKeyException
     */
    private function retrieveNewKey()
    {
        try {
            $response = $this->distributionClient->retrieveNewKey();

            $key = $response->key;
            $keyVersion = $response->version;

            $this->cacheProvider->save($this->keyVersionCacheIndex, $keyVersion, 0);
            $this->cacheProvider->save($this->keyCacheIndex, $key, 0);
        } catch (\Exception|GuzzleException $e) {
            throw new RetrieveNewKeyException('Unable to retrieve signing key');
        }
    }
}