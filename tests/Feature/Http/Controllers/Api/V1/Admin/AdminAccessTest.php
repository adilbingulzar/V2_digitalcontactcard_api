<?php

namespace Tests\Feature\Http\Controllers\Api\V1\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function adminRoutes(): array
    {
        return [
            'dashboard' => ['get', 'api.v1.admin.dashboard'],
            'users' => ['get', 'api.v1.admin.users.index'],
            'payouts' => ['get', 'api.v1.admin.payouts.index'],
            'mark paid' => ['post', 'api.v1.admin.payouts.mark-paid'],
            'settings' => ['put', 'api.v1.admin.settings.update'],
            'ai settings' => ['get', 'api.v1.admin.ai-settings.show'],
            'ai models' => ['post', 'api.v1.admin.ai-settings.models'],
            'sms' => ['post', 'api.v1.admin.sms.store'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_only_admins_get_in(string $method, string $route): void
    {
        $this->json($method, route($route))->assertUnauthorized();

        Sanctum::actingAs(User::factory()->business()->create());
        $this->json($method, route($route))->assertForbidden();
    }

    public function test_admins_get_in(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson(route('api.v1.admin.dashboard'))->assertOk()->assertJsonStructure(['data' => ['users', 'tips', 'platform_fees', 'payouts']]);
    }
}
