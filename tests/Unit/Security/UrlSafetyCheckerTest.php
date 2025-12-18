<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Security\UrlSafetyChecker;
use Redis;

class UrlSafetyCheckerTest extends TestCase
{
    private UrlSafetyChecker $checker;
    private Redis $redis;

    protected function setUp(): void
    {
        $this->checker = new UrlSafetyChecker();
        $this->redis = new Redis();
        $this->redis->connect(
            getenv('REDIS_HOST') ?: 'redis',
            (int)(getenv('REDIS_PORT') ?: 6379)
        );
        $this->redis->setOption(Redis::OPT_PREFIX, 'test:');
    }

    protected function tearDown(): void
    {
        $keys = $this->redis->keys('*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }

    #[Test]
    public function checkUrl_allowsValidUrl(): void
    {
        $result = $this->checker->checkUrl('https://example.com/page');

        $this->assertTrue($result['safe']);
        $this->assertArrayNotHasKey('error', $result);
    }

    #[Test]
    public function checkUrl_allowsHttpUrl(): void
    {
        $result = $this->checker->checkUrl('http://example.com/page');

        $this->assertTrue($result['safe']);
    }

    #[Test]
    public function checkUrl_rejectsInvalidUrl(): void
    {
        $result = $this->checker->checkUrl('not-a-valid-url');

        $this->assertFalse($result['safe']);
        $this->assertEquals('invalid_url', $result['reason']);
    }

    #[Test]
    #[DataProvider('urlShortenerProvider')]
    public function checkUrl_blocksUrlShorteners(string $url): void
    {
        $result = $this->checker->checkUrl($url);

        $this->assertFalse($result['safe']);
        $this->assertEquals('shortener_chain', $result['reason']);
        $this->assertStringContainsString('shortener', strtolower($result['message']));
    }

    public static function urlShortenerProvider(): array
    {
        return [
            'bit.ly' => ['https://bit.ly/abc123'],
            'tinyurl.com' => ['https://tinyurl.com/xyz'],
            't.co' => ['https://t.co/abcd'],
            'goo.gl' => ['https://goo.gl/maps/xyz'],
            'ow.ly' => ['https://ow.ly/abc'],
            'is.gd' => ['https://is.gd/short'],
            'buff.ly' => ['https://buff.ly/2xyz'],
            'cutt.ly' => ['https://cutt.ly/abc'],
            'rebrand.ly' => ['https://rebrand.ly/xyz'],
            'short.io' => ['https://short.io/abc'],
        ];
    }

    #[Test]
    #[DataProvider('phishingDomainProvider')]
    public function checkUrl_blocksPhishingDomains(string $url): void
    {
        $result = $this->checker->checkUrl($url);

        $this->assertFalse($result['safe']);
        $this->assertEquals('blocked_domain', $result['reason']);
    }

    public static function phishingDomainProvider(): array
    {
        return [
            'paypal-login' => ['https://paypal-login.com/verify'],
            'login-paypal' => ['https://login-paypal.com/secure'],
            'secure-bank' => ['https://secure-bank-verify.com'],
            'signin-amazon' => ['https://signin-amazon.com'],
            'account-verify' => ['https://account-verify-now.com'],
            'banking-secure' => ['https://banking-secure.com'],
            'update-account' => ['https://update-account.com'],
            'confirm-identity' => ['https://confirm-identity.com'],
        ];
    }

    #[Test]
    #[DataProvider('blockedTldProvider')]
    public function checkUrl_blocksAbusiveTlds(string $url): void
    {
        $result = $this->checker->checkUrl($url);

        $this->assertFalse($result['safe']);
        $this->assertEquals('blocked_tld', $result['reason']);
    }

    public static function blockedTldProvider(): array
    {
        return [
            '.tk' => ['https://freesite.tk/page'],
            '.ml' => ['https://malware.ml/download'],
            '.ga' => ['https://spam.ga/click'],
            '.cf' => ['https://phishing.cf/login'],
            '.gq' => ['https://scam.gq/verify'],
        ];
    }

    #[Test]
    #[DataProvider('privateIpProvider')]
    public function checkUrl_blocksPrivateIps(string $url): void
    {
        $result = $this->checker->checkUrl($url);

        $this->assertFalse($result['safe']);
        $this->assertContains($result['reason'], ['private_ip', 'suspicious_pattern']);
    }

    public static function privateIpProvider(): array
    {
        return [
            'localhost' => ['http://localhost/admin'],
            '127.0.0.1' => ['http://127.0.0.1/secret'],
            '192.168.x.x' => ['http://192.168.1.1/router'],
            '10.x.x.x' => ['http://10.0.0.1/internal'],
            '172.16.x.x' => ['http://172.16.0.1/private'],
            '.local' => ['http://myserver.local/api'],
            '.localhost' => ['http://dev.localhost/test'],
        ];
    }

    #[Test]
    public function checkUrl_blocksIpAddressUrls(): void
    {
        $result = $this->checker->checkUrl('http://8.8.8.8/malware');

        $this->assertFalse($result['safe']);
        $this->assertEquals('suspicious_pattern', $result['reason']);
    }

    #[Test]
    public function checkUrl_blocksDataUrls(): void
    {
        $result = $this->checker->checkUrl('data:text/html,<script>alert(1)</script>');

        $this->assertFalse($result['safe']);
    }

    #[Test]
    public function checkUrl_blocksJavascriptUrls(): void
    {
        $result = $this->checker->checkUrl('javascript:alert(1)');

        $this->assertFalse($result['safe']);
    }

    #[Test]
    public function checkUrl_blocksCredentialsInUrl(): void
    {
        $result = $this->checker->checkUrl('https://user:pass@example.com/page');

        $this->assertFalse($result['safe']);
        $this->assertEquals('suspicious_pattern', $result['reason']);
    }

    #[Test]
    public function checkUrl_blocksExcessiveSubdomains(): void
    {
        $result = $this->checker->checkUrl('https://a.b.c.d.e.example.com/page');

        $this->assertFalse($result['safe']);
        $this->assertEquals('suspicious_pattern', $result['reason']);
    }

    #[Test]
    public function checkUrl_blocksVeryLongUrls(): void
    {
        $longPath = str_repeat('a', 2100);
        $result = $this->checker->checkUrl("https://example.com/{$longPath}");

        $this->assertFalse($result['safe']);
        $this->assertEquals('suspicious_pattern', $result['reason']);
    }

    #[Test]
    public function checkUrl_blocksInvalidScheme(): void
    {
        $result = $this->checker->checkUrl('ftp://example.com/file.txt');

        $this->assertFalse($result['safe']);
        $this->assertEquals('invalid_scheme', $result['reason']);
        $this->assertStringContainsString('http', strtolower($result['message']));
    }

    #[Test]
    #[DataProvider('executableExtensionProvider')]
    public function checkUrl_warnsAboutExecutables(string $url): void
    {
        $result = $this->checker->checkUrl($url);

        // Executables are medium severity (warning, not block)
        $this->assertTrue($result['safe']);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertNotEmpty($result['warnings']);
        $this->assertEquals('suspicious_extension', $result['warnings'][0]['type']);
    }

    public static function executableExtensionProvider(): array
    {
        return [
            '.exe' => ['https://example.com/download/file.exe'],
            '.msi' => ['https://example.com/install.msi'],
            '.bat' => ['https://example.com/script.bat'],
            '.cmd' => ['https://example.com/run.cmd'],
            '.ps1' => ['https://example.com/powershell.ps1'],
            '.vbs' => ['https://example.com/script.vbs'],
            '.jar' => ['https://example.com/app.jar'],
            '.scr' => ['https://example.com/screensaver.scr'],
        ];
    }

    #[Test]
    public function checkUrl_allowsNormalFileExtensions(): void
    {
        $extensions = ['.html', '.php', '.js', '.css', '.jpg', '.png', '.pdf', '.txt'];

        foreach ($extensions as $ext) {
            $result = $this->checker->checkUrl("https://example.com/file{$ext}");
            $this->assertTrue($result['safe'], "Extension {$ext} should be allowed");
        }
    }

    #[Test]
    public function blockDomain_addsToDynamicBlocklist(): void
    {
        $this->checker->blockDomain($this->redis, 'spam-domain.com', 3600);

        $isBlocked = $this->checker->isDynamicallyBlocked($this->redis, 'spam-domain.com');
        $this->assertTrue($isBlocked);
    }

    #[Test]
    public function isDynamicallyBlocked_returnsFalseForUnblockedDomain(): void
    {
        $isBlocked = $this->checker->isDynamicallyBlocked($this->redis, 'not-blocked.com');
        $this->assertFalse($isBlocked);
    }

    #[Test]
    public function blockDomain_caseInsensitive(): void
    {
        $this->checker->blockDomain($this->redis, 'UPPERCASE.COM');

        $this->assertTrue($this->checker->isDynamicallyBlocked($this->redis, 'uppercase.com'));
        $this->assertTrue($this->checker->isDynamicallyBlocked($this->redis, 'UPPERCASE.COM'));
    }

    #[Test]
    public function checkUrl_handlesUrlsWithQueryStrings(): void
    {
        $result = $this->checker->checkUrl('https://example.com/search?q=test&page=1');

        $this->assertTrue($result['safe']);
    }

    #[Test]
    public function checkUrl_handlesUrlsWithFragments(): void
    {
        $result = $this->checker->checkUrl('https://example.com/page#section');

        $this->assertTrue($result['safe']);
    }

    #[Test]
    public function checkUrl_handlesUrlsWithPorts(): void
    {
        $result = $this->checker->checkUrl('https://example.com:8443/api');

        $this->assertTrue($result['safe']);
    }

    #[Test]
    public function checkUrl_handlesInternationalDomains(): void
    {
        // Non-IDN international domain should work
        $result = $this->checker->checkUrl('https://example.co.uk/page');

        $this->assertTrue($result['safe']);
    }

    #[Test]
    public function checkUrl_detectsHomographAttacks(): void
    {
        // Mixed Cyrillic and Latin (homograph attack)
        $result = $this->checker->checkUrl('https://pаypal.com/login'); // 'а' is Cyrillic

        $this->assertFalse($result['safe']);
        $this->assertEquals('suspicious_pattern', $result['reason']);
    }

    #[Test]
    public function checkUrl_multipleIssuesReported(): void
    {
        // URL that would trigger multiple checks
        $result = $this->checker->checkUrl('https://paypal-login.tk/file.exe');

        $this->assertFalse($result['safe']);
        $this->assertArrayHasKey('issues', $result);
        $this->assertGreaterThan(1, count($result['issues']));
    }

    #[Test]
    public function checkUrl_returnsUserFriendlyMessages(): void
    {
        $urls = [
            'https://bit.ly/x' => 'shortener',
            'https://paypal-login.com' => 'not allowed',
            'http://localhost/' => 'private',
            'ftp://example.com' => 'http',
        ];

        foreach ($urls as $url => $expectedWord) {
            $result = $this->checker->checkUrl($url);
            $this->assertFalse($result['safe']);
            $this->assertStringContainsString(
                $expectedWord,
                strtolower($result['message']),
                "Message for {$url} should contain '{$expectedWord}'"
            );
        }
    }
}
