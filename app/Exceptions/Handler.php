<?php

namespace App\Exceptions;

use Exception;

class Handler extends Exception
{
    //
public function render($request, Throwable $exception)
{
    if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
    return parent::render($request, $exception);
}

} 
