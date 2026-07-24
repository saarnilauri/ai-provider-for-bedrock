<?php

declare(strict_types=1);

namespace AiProviderForBedrock\Authentication;

use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Psr7\Request as Psr7Request;
use WordPress\AiClient\Common\AbstractDataTransferObject;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use WordPress\AiClient\Providers\Http\DTO\Request;

/**
 * AWS Signature V4 request authentication for Amazon Bedrock.
 *
 * @since 1.0.0
 *
 * @phpstan-type BedrockAuthArrayShape array{
 *     accessKeyId: string,
 *     secretAccessKey?: string,
 *     region: string
 * }
 *
 * @extends AbstractDataTransferObject<BedrockAuthArrayShape>
 */
class BedrockRequestAuthentication extends AbstractDataTransferObject implements RequestAuthenticationInterface
{
    /**
     * AWS credentials used for signing requests.
     *
     * @since 1.0.0
     *
     * @var Credentials
     */
    private Credentials $credentials;

    /**
     * AWS region for the Bedrock service.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private string $region;

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Credentials $credentials AWS credentials.
     * @param string      $region      AWS region.
     */
    public function __construct(Credentials $credentials, string $region)
    {
        $this->credentials = $credentials;
        $this->region = $region;
    }

    /**
     * {@inheritDoc}
     *
     * Signs the request using AWS Signature V4.
     *
     * @since 1.0.0
     */
    public function authenticateRequest(Request $request): Request
    {
        $signer = new SignatureV4('bedrock', $this->region);

        $body = $request->getBody() ?? '';
        $psrRequest = new Psr7Request(
            (string) $request->getMethod(),
            $request->getUri(),
            $request->getHeaders(),
            $body
        );

        $signedPsrRequest = $signer->signRequest($psrRequest, $this->credentials);

        $signedHeaders = ['Authorization', 'X-Amz-Date', 'X-Amz-Content-Sha256'];
        foreach ($signedHeaders as $headerName) {
            $headerValue = $signedPsrRequest->getHeaderLine($headerName);
            if ($headerValue !== '') {
                /*
                 * Pass the value as a single-element array: string values are
                 * split on commas by the AI Client's HeadersCollection, which
                 * would break the comma-containing SigV4 Authorization header
                 * into multiple header lines that AWS rejects.
                 */
                $request = $request->withHeader($headerName, [$headerValue]);
            }
        }

        return $request;
    }

    /**
     * Creates the appropriate request authentication from plugin settings.
     *
     * Prefers a Bedrock API key (sent as a Bearer token) when one is configured,
     * and falls back to AWS Signature V4 with an IAM access key pair otherwise.
     *
     * @since 1.0.0
     *
     * @return RequestAuthenticationInterface
     *
     * @throws \RuntimeException If no credentials are configured.
     */
    public static function createFromSettings(): RequestAuthenticationInterface
    {
        $apiKey = \AiProviderForBedrock\get_bedrock_api_key();
        if ($apiKey !== '') {
            return new ApiKeyRequestAuthentication($apiKey);
        }

        return self::fromSettings();
    }

    /**
     * Creates a new instance from plugin settings.
     *
     * @since 1.0.0
     *
     * @return self
     *
     * @throws \RuntimeException If access key or secret key is empty.
     */
    public static function fromSettings(): self
    {
        $accessKeyId = \AiProviderForBedrock\get_bedrock_access_key_id();
        $secretAccessKey = \AiProviderForBedrock\get_bedrock_secret_access_key();
        $region = \AiProviderForBedrock\get_bedrock_region();

        if ($accessKeyId === '' || $secretAccessKey === '') {
            throw new \RuntimeException(
                'Bedrock credentials must be configured: either an API key, '
                . 'or an AWS access key ID and secret access key.'
            );
        }

        return new self(
            new Credentials($accessKeyId, $secretAccessKey),
            $region
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     *
     * @return BedrockAuthArrayShape
     */
    public function toArray(): array
    {
        return [
            'accessKeyId' => $this->credentials->getAccessKeyId(),
            'region' => $this->region,
            // Never expose secret key in serialization
        ];
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public static function fromArray(array $array): self
    {
        static::validateFromArrayData($array, ['accessKeyId', 'secretAccessKey', 'region']);

        return new self(
            new Credentials($array['accessKeyId'], $array['secretAccessKey']),
            $array['region']
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    public static function getJsonSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'accessKeyId' => ['type' => 'string', 'title' => 'AWS Access Key ID'],
                'secretAccessKey' => ['type' => 'string', 'title' => 'AWS Secret Access Key'],
                'region' => ['type' => 'string', 'title' => 'AWS Region'],
            ],
            'required' => ['accessKeyId', 'secretAccessKey', 'region'],
        ];
    }
}
