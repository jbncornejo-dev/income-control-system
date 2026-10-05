<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureUserHasRole;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    private function requestFor(?User $user): Request
    {
        $request = Request::create('/any');
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    private function userWithRole(string $role): User
    {
        $user = new User();
        $user->setRelation('rol', new Rol(['nombre_rol' => $role]));

        return $user;
    }

    private function next(): callable
    {
        return fn () => response('ok');
    }

    public function test_role_middleware_lets_an_allowed_role_through(): void
    {
        $response = (new EnsureUserHasRole())->handle(
            $this->requestFor($this->userWithRole('docente')),
            $this->next(),
            'administrador',
            'docente'
        );

        $this->assertSame('ok', $response->getContent());
    }

    public function test_role_middleware_blocks_other_roles_with_403(): void
    {
        try {
            (new EnsureUserHasRole())->handle(
                $this->requestFor($this->userWithRole('docente')),
                $this->next(),
                'administrador'
            );
            $this->fail('Expected a 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_role_middleware_blocks_guests_and_users_without_role(): void
    {
        foreach ([null, new User()] as $user) {
            try {
                (new EnsureUserHasRole())->handle($this->requestFor($user), $this->next(), 'administrador');
                $this->fail('Expected a 403');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_password_middleware_redirects_students_with_a_temporary_password(): void
    {
        $user = new User(['id_estudiante' => 1, 'debe_cambiar_password' => true]);

        $response = (new EnsurePasswordChanged())->handle($this->requestFor($user), $this->next());

        $this->assertTrue($response->isRedirect(route('cambiar-password.show')));
    }

    public function test_password_middleware_lets_everyone_else_through(): void
    {
        $users = [
            null,
            new User(['id_estudiante' => 1, 'debe_cambiar_password' => false]),
            new User(['id_estudiante' => null, 'debe_cambiar_password' => true]),
        ];

        foreach ($users as $user) {
            $response = (new EnsurePasswordChanged())->handle($this->requestFor($user), $this->next());
            $this->assertSame('ok', $response->getContent());
        }
    }
}