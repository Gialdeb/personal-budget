<?php

use App\Models\TrackedItem;
use App\Models\User;
use App\Models\WebsiteIdentity;
use App\Services\TrackedItems\WebsiteLogoService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

function verifiedTrackedItemLogoUser(): User
{
    return User::factory()->create(['email_verified_at' => now()]);
}

test('supplier domains are normalized to safe https urls', function () {
    $service = app(WebsiteLogoService::class);

    expect($service->normalizeUrl(' amazon.it/offerte?campaign=private#today '))
        ->toBe('https://amazon.it/offerte')
        ->and($service->normalizeUrl('http://www.amazon.it'))
        ->toBe('https://www.amazon.it/');

    foreach (['https://127.0.0.1', 'https://localhost', 'https://user:pass@example.com', 'ftp://example.com'] as $url) {
        expect(fn () => $service->normalizeUrl($url))->toThrow(ValidationException::class);
    }
});

test('large source logos are resized and optimized before they are cached', function () {
    $source = imagecreatetruecolor(1024, 1024);
    imagefill($source, 0, 0, imagecolorallocate($source, 22, 101, 52));
    ob_start();
    imagepng($source, null, 0);
    $sourceBytes = (string) ob_get_clean();
    imagedestroy($source);

    expect(strlen($sourceBytes))->toBeGreaterThan(1_048_576);

    $service = app(WebsiteLogoService::class);
    $optimizedLogo = (fn (): ?array => $this->optimizeLogo($sourceBytes, 'image/png'))
        ->call($service);

    expect($optimizedLogo)->not->toBeNull()
        ->and(strlen($optimizedLogo['bytes']))->toBeLessThanOrEqual(524_288)
        ->and($optimizedLogo['mime_type'])->toBeIn(['image/png', 'image/webp']);

    $dimensions = getimagesizefromstring($optimizedLogo['bytes']);

    expect($dimensions[0])->toBeLessThanOrEqual(512)
        ->and($dimensions[1])->toBeLessThanOrEqual(256);
});

test('logo fetch retries the next public address when the first resolved address fails', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        if ($attempts === 1) {
            throw new ConnectionException('Primary address unavailable');
        }

        return Http::response('<html></html>', 200);
    });

    $service = new class extends WebsiteLogoService
    {
        public function fetch(string $url): int
        {
            return $this->request($url)->status();
        }

        protected function publicAddressesForHost(string $host): array
        {
            return ['2001:db8::10', '203.0.113.10'];
        }
    };

    expect($service->fetch('https://amazon.it/'))->toBe(200);
    expect($attempts)->toBe(2);
});

test('logo fetch retries the next public address after a transient cdn response', function () {
    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        $attempts++;

        return $attempts === 1
            ? Http::response('Service unavailable', 503)
            : Http::response('<html></html>', 200);
    });

    $service = new class extends WebsiteLogoService
    {
        public function fetch(string $url): int
        {
            return $this->request($url)->status();
        }

        protected function publicAddressesForHost(string $host): array
        {
            return ['2001:db8::10', '203.0.113.10'];
        }
    };

    expect($service->fetch('https://amazon.it/'))->toBe(200);
    expect($attempts)->toBe(2);
});

test('logo preview returns a locally cached website identity', function () {
    Storage::fake('public');
    Storage::disk('public')->put('tracked-item-logos/amazon.png', 'logo-bytes');

    $user = verifiedTrackedItemLogoUser();
    $identity = WebsiteIdentity::factory()->create([
        'domain' => 'amazon.it',
        'canonical_url' => 'https://amazon.it',
        'logo_path' => 'tracked-item-logos/amazon.png',
        'logo_mime_type' => 'image/png',
        'status' => 'ready',
    ]);

    $service = $this->mock(WebsiteLogoService::class);
    $service->shouldReceive('normalizeUrl')->once()->with('amazon.it')->andReturn('https://amazon.it/');
    $service->shouldReceive('resolve')->once()->with('amazon.it')->andReturn($identity);

    $this->actingAs($user)
        ->postJson(route('tracked-items.logo-preview'), ['website_url' => 'amazon.it'])
        ->assertOk()
        ->assertJson([
            'website_url' => 'https://amazon.it/',
            'domain' => 'amazon.it',
            'logo_url' => route('tracked-item-logos.show', $identity->uuid),
            'status' => 'ready',
        ]);
});

test('different users reuse the same cached website identity', function () {
    $firstUser = verifiedTrackedItemLogoUser();
    $secondUser = verifiedTrackedItemLogoUser();
    $identity = WebsiteIdentity::factory()->create([
        'domain' => 'amazon.it',
        'canonical_url' => 'https://amazon.it',
    ]);

    $service = $this->mock(WebsiteLogoService::class);
    $service->shouldReceive('normalizeUrl')->twice()->andReturn('https://amazon.it/');
    $service->shouldReceive('resolve')->twice()->andReturn($identity);

    foreach ([[$firstUser, 'Amazon personale'], [$secondUser, 'Amazon famiglia']] as [$user, $name]) {
        $this->actingAs($user)
            ->post(route('tracked-items.store'), [
                'name' => $name,
                'slug' => str($name)->slug()->toString(),
                'website_url' => 'amazon.it',
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tracked-items.edit'));
    }

    expect(TrackedItem::query()->where('website_identity_id', $identity->id)->count())->toBe(2)
        ->and(WebsiteIdentity::query()->where('domain', 'amazon.it')->count())->toBe(1);
});

test('tracked items payload exposes website and local logo url', function () {
    Storage::fake('public');
    Storage::disk('public')->put('tracked-item-logos/supplier.png', 'logo-bytes');

    $user = verifiedTrackedItemLogoUser();
    $identity = WebsiteIdentity::factory()->create([
        'logo_path' => 'tracked-item-logos/supplier.png',
        'logo_mime_type' => 'image/png',
        'status' => 'ready',
    ]);
    $trackedItem = TrackedItem::query()->create([
        'user_id' => $user->id,
        'website_identity_id' => $identity->id,
        'name' => 'Fornitore',
        'slug' => 'fornitore',
        'website_url' => $identity->canonical_url,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('tracked-items.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/TrackedItems')
            ->where('trackedItems.flat.0.uuid', $trackedItem->uuid)
            ->where('trackedItems.flat.0.website_url', $identity->canonical_url)
            ->where('trackedItems.flat.0.logo_url', route('tracked-item-logos.show', $identity->uuid)));
});

test('cached logo endpoint serves only the known local file with immutable headers', function () {
    Storage::fake('public');
    Storage::disk('public')->put('tracked-item-logos/supplier.png', 'logo-bytes');

    $user = verifiedTrackedItemLogoUser();
    $identity = WebsiteIdentity::factory()->create([
        'logo_path' => 'tracked-item-logos/supplier.png',
        'logo_mime_type' => 'image/png',
        'status' => 'ready',
    ]);

    $this->actingAs($user)
        ->get(route('tracked-item-logos.show', $identity->uuid))
        ->assertOk()
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertStreamedContent('logo-bytes');
});
