<?php

use Codeception\Util\Fixtures;
use Grav\Common\Browser;
use Grav\Common\Grav;

/**
 * Class BrowserTest
 */
class BrowserTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav $grav */
    protected $grav;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
    }

    protected function tearDown(): void
    {
    }

    public function testGetBrowser(): void
    {
 /* Already covered by PhpUserAgent tests */
    }

    public function testGetPlatform(): void
    {
 /* Already covered by PhpUserAgent tests */
    }

    public function testGetLongVersion(): void
    {
 /* Already covered by PhpUserAgent tests */
    }

    public function testGetVersion(): void
    {
 /* Already covered by PhpUserAgent tests */
    }

    public function testIsHuman(): void
    {
        //Already Partially covered by PhpUserAgent tests

        //Make sure it recognizes the test as not human
        self::assertFalse($this->grav['browser']->isHuman());
    }

    /**
     * @param string $agent
     * @return bool
     */
    private function isHumanAgent(string $agent): bool
    {
        $previous = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $_SERVER['HTTP_USER_AGENT'] = $agent;
        try {
            return (new Browser())->isHuman();
        } finally {
            if ($previous === null) {
                unset($_SERVER['HTTP_USER_AGENT']);
            } else {
                $_SERVER['HTTP_USER_AGENT'] = $previous;
            }
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonHumanAgents(): array
    {
        return [
            // Link-preview and social fetchers
            'facebookexternalhit' => ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'],
            'facebookexternalhit mozilla' => ['Mozilla/5.0 (compatible; facebookexternalhit/1.1; +http://www.facebook.com/externalhit_uatext.php)'],
            'facebookcatalog' => ['facebookcatalog/1.0'],
            'meta-externalagent' => ['meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler)'],
            'meta-externalfetcher' => ['meta-externalfetcher/1.1'],
            'twitterbot' => ['Twitterbot/1.0'],
            'linkedinbot' => ['LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)'],
            'slackbot' => ['Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)'],
            'slack-imgproxy' => ['Slack-ImgProxy (+https://api.slack.com/robots)'],
            'discordbot' => ['Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)'],
            'whatsapp' => ['WhatsApp/2.23.20.0 A'],
            'telegrambot' => ['TelegramBot (like TwitterBot)'],
            'skypeuripreview' => ['Mozilla/5.0 (Windows NT 6.1; WOW64) SkypeUriPreview Preview/0.5'],
            'iframely' => ['Iframely/1.3.1 (+https://iframely.com/docs/about)'],
            'embedly' => ['Mozilla/5.0 (compatible; Embedly/0.2; +http://support.embed.ly/)'],
            'redditbot' => ['Mozilla/5.0 (compatible; redditbot/1.0; +http://www.reddit.com/feedback)'],
            'pinterest' => ['Pinterest/0.2 (+http://www.pinterest.com/bot.html)'],
            'vkshare' => ['Mozilla/5.0 (compatible; vkShare; +http://vk.com/dev/Share)'],
            'applebot' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)'],

            // AI fetchers
            'chatgpt-user' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot'],
            'oai-searchbot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot'],
            'gptbot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot'],
            'claudebot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'],
            'claude-user' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-User/1.0; +Claude-User@anthropic.com)'],
            'claude-searchbot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Claude-SearchBot/1.0; +Claude-SearchBot@anthropic.com)'],
            'anthropic-ai' => ['anthropic-ai'],
            'perplexitybot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)'],
            'perplexity-user' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Perplexity-User/1.0; +https://perplexity.ai/perplexity-user)'],
            'google-extended' => ['Mozilla/5.0 (compatible; Google-Extended)'],
            'bytespider' => ['Mozilla/5.0 (Linux; Android 5.0) AppleWebKit/537.36 (KHTML, like Gecko) Mobile Safari/537.36 (compatible; Bytespider; spider-feedback@bytedance.com)'],
            'ccbot' => ['CCBot/2.0 (https://commoncrawl.org/faq/)'],
            'cohere-ai' => ['cohere-ai'],
            'amazonbot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; Amazonbot/0.1; +https://developer.amazon.com/support/amazonbot) Chrome/119.0.6045.214 Safari/537.36'],
            'duckassistbot' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; DuckAssistBot/1.2; +http://duckduckgo.com/duckassistbot.html)'],
            'mistralai-user' => ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; MistralAI-User/1.0; +https://docs.mistral.ai/robots)'],

            // Uptime and monitoring
            'uptimerobot' => ['Mozilla/5.0 (compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)'],
            'pingdom' => ['Mozilla/5.0 (compatible; PingdomPageSpeed/1.0; +http://www.pingdom.com/)'],
            'pingdom tools' => ['Pingdom.com_bot_version_1.4_(http://www.pingdom.com/)'],
            'statuscake' => ['Mozilla/5.0 (compatible; StatusCake)'],
            'site24x7' => ['Site24x7'],
            'better uptime' => ['Better Uptime Bot Mozilla/5.0 (compatible; Better Uptime Bot; +https://betteruptime.com)'],
            'betterstack' => ['Mozilla/5.0 (compatible; BetterStack)'],
            'uptime-kuma' => ['Uptime-Kuma/1.23.11'],
            'freshping' => ['Mozilla/5.0 (compatible; FreshpingBot/1.0; +https://www.freshworks.com/website-monitoring/)'],
            'hetrixtools' => ['Mozilla/5.0 (compatible; HetrixTools Uptime Monitoring Bot. https://hetrixtools.com/uptime-monitoring-bot.html)'],
            'datadog agent' => ['Datadog Agent/7.50.0'],
            'datadog synthetics' => ['Mozilla/5.0 (X11; Linux x86_64) Chrome/118.0.0.0 Safari/537.36 DatadogSynthetics'],
            'newrelicpinger' => ['NewRelicPinger/1.0 (123456)'],
            'checkly' => ['Checkly/1.0 (https://www.checklyhq.com)'],
            'guardprobe' => ['GuardProbe/0.1 (+https://gravguard.com)'],
            'guardpinger' => ['GuardPinger/1.0'],
            'gravguard' => ['GravGuardBot/1.0 (+https://gravguard.com)'],

            // Generic tokens
            'spider' => ['Mozilla/5.0 (compatible; ExampleSpider/2.1)'],
            'crawler' => ['Mozilla/5.0 (compatible; ExampleCrawler/2.1)'],
            'scraper' => ['Mozilla/5.0 (compatible; my-scraper/0.3)'],
            'headlesschrome' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0.0.0 Safari/537.36'],
            'lighthouse' => ['Mozilla/5.0 (Linux; Android 11; moto g power (2022)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/109.0.0.0 Mobile Safari/537.36 Chrome-Lighthouse'],
            'preview' => ['Mozilla/5.0 LinkPreview/1.0'],
            'monitor' => ['Mozilla/5.0 SiteMonitor/3.1'],
            'uptime' => ['Mozilla/5.0 UptimeCheck/3.1'],
            'fetcher' => ['Mozilla/5.0 (compatible; FeedFetcher/2.0)'],
        ];
    }

    /**
     * @dataProvider nonHumanAgents
     */
    public function testIsHumanRejectsKnownNonHumanClients(string $agent): void
    {
        self::assertFalse($this->isHumanAgent($agent), $agent);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function humanAgents(): array
    {
        return [
            'chrome windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'],
            'chrome mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'],
            'safari mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15'],
            'safari ios' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1'],
            'firefox' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'],
            'firefox linux' => ['Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0'],
            'edge' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0'],
            'samsung internet' => ['Mozilla/5.0 (Linux; Android 13; SAMSUNG SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36'],
            'chrome android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36'],
            'facebook in-app ios' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/21B74 [FBAN/FBIOS;FBAV/440.0.0.30.113;FBBV/560000000;FBDV/iPhone14,5;FBMD/iPhone;FBSN/iOS;FBSV/17.1;FBSS/3;FBID/phone;FBLC/en_US;FBOP/5]'],
            'facebook in-app android' => ['Mozilla/5.0 (Linux; Android 13; Pixel 7 Build/TQ3A.230901.001; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/119.0.6045.193 Mobile Safari/537.36 [FB_IAB/FB4A;FBAV/440.0.0.33.113;]'],
            'instagram in-app' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/21B74 Instagram 310.0.0.37.109 (iPhone14,5; iOS 17_1; en_US; en-US; scale=3.00; 1170x2532; 540972892)'],
        ];
    }

    /**
     * @dataProvider humanAgents
     */
    public function testIsHumanKeepsRealBrowsersHuman(string $agent): void
    {
        self::assertTrue($this->isHumanAgent($agent), $agent);
    }

    public function testIsHumanKeepsExistingBotNameCheck(): void
    {
        self::assertFalse($this->isHumanAgent('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
        self::assertFalse($this->isHumanAgent('Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'));
    }
}
