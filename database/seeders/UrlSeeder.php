<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('MYSQL_HOST') ?: 'mysql',
            getenv('MYSQL_DATABASE') ?: 'elephurl'
        );
        $pdo = new PDO($dsn, getenv('MYSQL_USER') ?: 'elephurl', getenv('MYSQL_PASSWORD') ?: 'elephurl_secret', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function getRedis(): Redis
{
    static $redis = null;
    if ($redis === null) {
        $redis = new Redis();
        $redis->connect(
            getenv('REDIS_HOST') ?: 'redis',
            (int)(getenv('REDIS_PORT') ?: 6379)
        );
    }
    return $redis;
}

$urls = [
    'https://github.com/laravel/framework/issues/48291',
    'https://stackoverflow.com/questions/12345678/how-to-parse-json-in-php',
    'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    'https://docs.google.com/document/d/1aBcDeFgHiJkLmNoPqRsTuVwXyZ/edit',
    'https://twitter.com/elikibet/status/1234567890123456789',
    'https://www.amazon.com/dp/B09V3KXJPB/ref=cm_sw_r_cp_api_i_dl_ABCDEF',
    'https://medium.com/@johndoe/understanding-microservices-architecture-2024-abc123',
    'https://www.nytimes.com/2024/03/15/technology/ai-regulation-europe.html',
    'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT',
    'https://www.linkedin.com/posts/techcompany_hiring-senior-developer-activity-123456',
    'https://reddit.com/r/programming/comments/abc123/what_language_should_i_learn_in_2024',
    'https://netflix.com/watch/81234567',
    'https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise',
    'https://www.figma.com/file/AbCdEfGhIjKl/Design-System-v2',
    'https://trello.com/b/AbCdEfGh/product-roadmap-q1-2024',
    'https://notion.so/workspace/Meeting-Notes-March-2024-abc123def456',
    'https://slack.com/app_redirect?channel=C0123ABCDEF',
    'https://calendly.com/johndoe/30min-consultation',
    'https://zoom.us/j/1234567890?pwd=ABCdefGHIjklMNO',
    'https://dropbox.com/s/abcdefghijk/quarterly-report.pdf',
    'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrS/view',
    'https://www.instagram.com/p/CaBcDeFgHiJ/',
    'https://tiktok.com/@username/video/7234567890123456789',
    'https://pinterest.com/pin/123456789012345678/',
    'https://www.twitch.tv/videos/1234567890',
    'https://vimeo.com/123456789',
    'https://soundcloud.com/artist-name/track-name-2024',
    'https://bandcamp.com/album/new-release-2024',
    'https://discord.gg/AbCdEfGh',
    'https://t.me/channelname/12345',
    'https://wa.me/1234567890?text=Hello',
    'https://maps.google.com/maps?q=40.7128,-74.0060',
    'https://airbnb.com/rooms/12345678',
    'https://booking.com/hotel/us/grand-plaza-new-york.html',
    'https://uber.com/ride/request?destination=JFK',
    'https://doordash.com/store/sushi-place-123456',
    'https://grubhub.com/restaurant/pizza-spot-new-york/12345',
    'https://yelp.com/biz/best-coffee-shop-san-francisco',
    'https://tripadvisor.com/Restaurant_Review-g60763-d123456-Reviews-Amazing_Restaurant-New_York.html',
    'https://etsy.com/listing/1234567890/handmade-ceramic-mug',
    'https://ebay.com/itm/123456789012?hash=item1234567890',
    'https://shopify.com/store-name/products/cool-product',
    'https://stripe.com/docs/api/payment_intents/create',
    'https://paypal.me/username/50',
    'https://venmo.com/u/username',
    'https://cashapp.com/$username',
    'https://gofundme.com/f/help-support-local-charity',
    'https://kickstarter.com/projects/creator/awesome-project-2024',
    'https://indiegogo.com/projects/innovative-gadget',
    'https://patreon.com/creator-name',
    'https://substack.com/@writer/p/interesting-article-about-tech',
    'https://ghost.io/blog/getting-started-guide/',
    'https://wordpress.com/post/myblog.com/12345',
    'https://wix.com/website/template/view/html/2345',
    'https://squarespace.com/templates/portfolio-design',
    'https://webflow.com/made-in-webflow/website/stunning-portfolio',
    'https://vercel.com/docs/deployments/overview',
    'https://netlify.com/blog/2024/03/new-features/',
    'https://heroku.com/apps/my-application/logs',
    'https://aws.amazon.com/s3/pricing/',
    'https://cloud.google.com/compute/docs/instances/create-start-instance',
    'https://azure.microsoft.com/en-us/services/kubernetes-service/',
    'https://digitalocean.com/community/tutorials/how-to-deploy-laravel',
    'https://linode.com/docs/guides/getting-started/',
    'https://vultr.com/products/cloud-compute/',
    'https://cloudflare.com/learning/ddos/what-is-a-ddos-attack/',
    'https://namecheap.com/domains/domain-name-search/',
    'https://godaddy.com/hosting/web-hosting',
    'https://bluehost.com/wordpress/wordpress-hosting',
    'https://siteground.com/tutorials/wordpress/',
    'https://hostinger.com/tutorials/how-to-make-a-website',
    'https://canva.com/design/DAFabcdefgh/edit',
    'https://adobe.com/products/photoshop.html',
    'https://sketch.com/s/12345678-abcd-efgh-ijkl-mnopqrstuvwx',
    'https://invisionapp.com/prototype/project-name-abc123',
    'https://miro.com/app/board/uXjVMabcdef=/',
    'https://lucidchart.com/documents/view/12345678-abcd-efgh',
    'https://drawio.com/blog/diagram-editor-features',
    'https://excalidraw.com/#json=abcdefghijklmnop',
    'https://codepen.io/username/pen/AbCdEf',
    'https://codesandbox.io/s/react-project-abc123',
    'https://replit.com/@username/project-name',
    'https://jsfiddle.net/abc123def/',
    'https://stackblitz.com/edit/angular-starter-project',
    'https://glitch.com/edit/#!/remix/hello-express',
    'https://observablehq.com/@username/data-visualization',
    'https://kaggle.com/competitions/titanic/overview',
    'https://huggingface.co/models?search=gpt',
    'https://openai.com/blog/chatgpt-plugins',
    'https://anthropic.com/research/claude-character',
    'https://deepmind.google/technologies/gemini/',
    'https://stability.ai/stable-diffusion',
    'https://midjourney.com/showcase/recent/',
    'https://runwayml.com/ai-tools/',
    'https://replicate.com/stability-ai/sdxl',
    'https://civitai.com/models/12345/realistic-style',
    'https://lexica.art/prompt/abc123def456',
    'https://labs.openai.com/s/AbCdEfGhIjKlMnOp',
    'https://chat.openai.com/share/abc123-def456-ghij789',
    'https://bard.google.com/chat/abc123def456',
];

$db = getDb();
$redis = getRedis();

echo "Seeding URLs...\n";

$stmt = $db->prepare('INSERT INTO urls (short_code, original_url, url_hash, click_count, created_at) VALUES (?, ?, ?, ?, ?)');

$chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

foreach ($urls as $i => $url) {
    $shortCode = '';
    for ($j = 0; $j < 7; $j++) {
        $shortCode .= $chars[random_int(0, strlen($chars) - 1)];
    }

    $urlHash = hash('sha256', $url);
    $clicks = random_int(0, 50) < 40 ? random_int(1, 500) : random_int(500, 5000);
    if (random_int(0, 100) < 10) {
        $clicks = random_int(5000, 25000);
    }
    if (random_int(0, 100) < 5) {
        $clicks = 0;
    }

    $daysAgo = random_int(1, 90);
    $createdAt = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days"));

    try {
        $stmt->execute([$shortCode, $url, $urlHash, $clicks, $createdAt]);
        $redis->setex("url:{$shortCode}", 86400, $url);
        $redis->set("clicks:{$shortCode}", $clicks);
        echo ".";
    } catch (PDOException $e) {
        echo "x";
    }
}

echo "\nDone! Seeded " . count($urls) . " URLs.\n";
