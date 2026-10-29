<?php

namespace Tuna\TunaGateway\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Serialize\Serializer\Json;

class TunaDebugLogger
{
    private const CONFIG_PATH = 'payment/tuna_payment/options/enable_debug_logging';
    private const LOG_FILE = 'tuna_debug.log';
    private const REDACTED_VALUE = '[REDACTED]';
    private const SENSITIVE_KEYS = [
        'accesstoken',
        'address',
        'appkey',
        'apptoken',
        'authenticationinformation',
        'authorization',
        'authorizationcode',
        'bankaccount',
        'barcode',
        'billingaddress',
        'bin',
        'cardbin',
        'cardexpirationdate',
        'cardexpiry',
        'buyername',
        'cardnumber',
        'cardholdername',
        'cardholderfirstname',
        'cardholderlastname',
        'cardtoken',
        'customerid',
        'cvv',
        'cvc',
        'devicedatacollectionurl',
        'digitableline',
        'document',
        'documentnumber',
        'email',
        'emailaddress',
        'expirationmonth',
        'expirationyear',
        'expirationdate',
        'expiry',
        'expirymonth',
        'expiryyear',
        'firstname',
        'birthdate',
        'birthyear',
        'dateofbirth',
        'id',
        'ipaddress',
        'jwt',
        'last4',
        'lastname',
        'maskednumber',
        'name',
        'numbermasked',
        'pan',
        'panmasked',
        'pares',
        'paresponse',
        'paymentkey',
        'paymentmethodidentifier',
        'paymenttoken',
        'paymentaccount',
        'originalresponse',
        'phone',
        'phonenumber',
        'parequest',
        'piximage',
        'postcode',
        'primaryaccountnumber',
        'qrcontent',
        'qrcode',
        'qrimage',
        'refreshtoken',
        'sessionid',
        'street',
        'telephone',
        'tokensession',
        'token',
        'transactionid',
        'url',
        'uri',
        'wallettoken',
        'mobilenumber',
        'buyerdocument',
        'taxnumber',
        'nationalid',
        'securitycode',
        'ssn',
        'cryptogram',
        'pixkey',
        'iban',
        'postalcode',
        'zipcode',
        'xtunaapptoken',
        'xtunaaccount',
        'account',
        'partneraccount',
        'referenceid',
        'signature',
    ];

    private $scopeConfig;
    private $filesystem;
    private $json;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Filesystem $filesystem,
        Json $json
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->filesystem = $filesystem;
        $this->json = $json;
    }

    public function log(string $event, array $context = []): void
    {
        try {
            if (!$this->scopeConfig->isSetFlag(self::CONFIG_PATH)) {
                return;
            }

            $record = [
                'timestamp' => gmdate('c'),
                'event' => $event,
                'context' => $this->sanitizeValue('', $context),
            ];
            $this->filesystem->getDirectoryWrite(DirectoryList::LOG)->writeFile(
                self::LOG_FILE,
                $this->json->serialize($record) . PHP_EOL,
                'a',
                true
            );
        } catch (\Throwable $exception) {
            // Debug logging must never interrupt payment or webhook processing.
        }
    }

    private function sanitizeValue(string $key, $value)
    {
        $normalizedKey = strtolower((string)preg_replace('/[^a-zA-Z0-9]/', '', $key));
        if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
            return self::REDACTED_VALUE;
        }

        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $childKey => $childValue) {
                $sanitized[$childKey] = $this->sanitizeValue((string)$childKey, $childValue);
            }
            return $sanitized;
        }

        if (is_object($value)) {
            return $this->sanitizeValue($key, get_object_vars($value));
        }

        if (is_string($value)) {
            $trimmedValue = ltrim($value);
            if ($trimmedValue !== '' && ($trimmedValue[0] === '{' || $trimmedValue[0] === '[')) {
                try {
                    $decodedValue = $this->json->unserialize($value);
                    if (is_array($decodedValue)) {
                        return $this->sanitizeValue('', $decodedValue);
                    }
                } catch (\InvalidArgumentException $exception) {
                    return $this->summarizeRawPayload($value);
                }
            }

            if ($normalizedKey === 'body') {
                return $this->summarizeRawPayload($value);
            }

            if (strlen($value) > 8192) {
                return substr($value, 0, 8192) . '[TRUNCATED]';
            }
        }

        return $value;
    }

    private function summarizeRawPayload(string $payload): array
    {
        return [
            'payloadBytes' => strlen($payload),
            'payload' => '[unparsed payload omitted]',
        ];
    }
}