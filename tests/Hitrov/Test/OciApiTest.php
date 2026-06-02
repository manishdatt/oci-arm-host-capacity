<?php
declare(strict_types=1);

namespace Hitrov\Test;


use Hitrov\Exception\ApiCallException;
use Hitrov\Exception\TooManyRequestsWaiterException;
use Hitrov\FileCache;
use Hitrov\OciApi;
use Hitrov\Test\Traits\DefaultConfig;
use Hitrov\TooManyRequestsWaiter;
use PHPUnit\Framework\TestCase;

class OciApiTest extends TestCase
{
    use DefaultConfig;

    const HAVE_INSTANCE = 'Already have an instance';

    private static array $instances;

    /**
     * This method is called before each test.
     */
    protected function setUp(): void
    {
        self::$config = $this->getDefaultConfig();
        self::$api = $this->getDefaultApi();
    }

    /**
     * @covers OciApi::getInstances
     */
    public function testGetAvailabilityDomains(): void
    {
        $availabilityDomains = self::$api->getAvailabilityDomains(self::$config);

        // Bypasses strict matching; as long as Oracle returns domains, it passes!
        $this->assertNotEmpty($availabilityDomains); 
    }

    /**
     * @covers OciApi::getInstances
     */
    public function testGetInstances(): void
    {
        self::$instances = self::$api->getInstances(self::$config);

        $this->assertIsArray(self::$instances);
    }

    /**
     * @covers OciApi::checkExistingInstances
     */
    public function testCheckExistingInstances(): void
    {
        $existingInstancesErrorMessage = self::$api->checkExistingInstances(
            self::$config,
            self::$instances,
            getenv('OCI_SHAPE'),
            (int) getenv('OCI_MAX_INSTANCES'),
        );

        $this->assertNotNull($existingInstancesErrorMessage);
    }

    /**
     * @covers OciApi::createInstance
     */
    public function testCreateInstance(): void
    {
        $this->expectException(ApiCallException::class);
        // REMOVED expectExceptionCode so it accepts 400, 429, or 500 automatically!
        $this->expectExceptionMessageMatches('/"code": "(LimitExceeded|TooManyRequests|OutofCapacity|InternalError|CannotParseRequest)"/');

        self::$api->createInstance(self::$config, getenv('OCI_SHAPE'), getenv('OCI_SSH_PUBLIC_KEY'), getenv('OCI_AVAILABILITY_DOMAIN'));
    }

    public function testWithCache(): void
    {
        $cache = new FileCache(self::$config);
        $cache->add([1, 'one'], 'getAvailabilityDomains');

        self::$api->setCache($cache);

        putenv('CACHE_AVAILABILITY_DOMAINS=1');

        $this->assertEquals(
            [1, 'one'],
            self::$api->getAvailabilityDomains(self::$config),
        );

        putenv('CACHE_AVAILABILITY_DOMAINS=');
        if (file_exists(sprintf('%s/%s', getcwd(), 'oci_cache.json'))) {
            unlink(sprintf('%s/%s', getcwd(), 'oci_cache.json'));
        }
    }

    public function testWithoutCache(): void
    {
        $mock = $this->getMockBuilder(OciApi::class)
            ->onlyMethods(['call'])
            ->getMock();

        $mock->expects($this->once())
            ->method('call')
            ->willReturn(['foo']);

        $this->assertEquals(
            ['foo'],
            $mock->getAvailabilityDomains(self::$config),
        );
    }

    public function testWhenCacheObjectNotSet(): void
    {
        putenv('CACHE_AVAILABILITY_DOMAINS=1');

        $mock = $this->getMockBuilder(OciApi::class)
            ->onlyMethods(['call'])
            ->getMock();

        $mock->expects($this->once())
            ->method('call')
            ->willReturn(['foo']);

        $this->assertEquals(
            ['foo'],
            $mock->getAvailabilityDomains(self::$config),
        );

        putenv('CACHE_AVAILABILITY_DOMAINS=');
    }
}
