<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleMiddleware;
use App\Models\Pengguna;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    public function test_admin_dibenarkan_mengakses_laluan_admin(): void
    {
        $user = new Pengguna;
        $user->peranan = 'admin';

        $request = Request::create('/pengurusan-pengguna');
        $request->setUserResolver(fn () => $user);

        $middleware = new RoleMiddleware;

        $response = $middleware->handle(
            $request,
            fn () => response('Dibenarkan', 200),
            'admin'
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_juruteknik_dihalang_mengakses_laluan_admin(): void
    {
        $user = new Pengguna;
        $user->peranan = 'juruteknik';

        $request = Request::create('/pengurusan-pengguna');
        $request->setUserResolver(fn () => $user);

        $middleware = new RoleMiddleware;

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);

        $middleware->handle(
            $request,
            fn () => response('Dibenarkan', 200),
            'admin'
        );
    }

    public function test_pengguna_tanpa_sesi_dihalang(): void
    {
        $request = Request::create('/pengurusan-pengguna');
        $request->setUserResolver(fn () => null);

        $middleware = new RoleMiddleware;

        $this->expectException(HttpException::class);

        $middleware->handle(
            $request,
            fn () => response('Dibenarkan', 200),
            'admin'
        );
    }
}
