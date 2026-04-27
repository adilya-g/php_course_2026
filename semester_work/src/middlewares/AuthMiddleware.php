<?php
namespace MyApp\Middlewares;

use MyApp\Entities\Request;
use MyApp\Services\AuthService;

class AuthMiddleware implements IMiddleware
{
    public AuthService $authService;
    public UserService $userService;

    function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function handle(Request $request, $next)
    {
        $authCode = $request->sessionData['code']
            ?? $request->params['code']
            ?? null;
        if(!is_null($authCode))
        {
            $this->authService->exchangeCode($authCode);
        }
        else if(isset($request->sessionData['refresh_token']))
        {
            $this->authService->validateToken();
        }
        else
        {
            $this->authService->authorize();
        }

        return $next($request);
    }
}