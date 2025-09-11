<?php

namespace App\Exceptions;

use Exception;

class Handler extends Exception
{
    //
    public function render($request, Throwable $e)
{
    if ($request->expectsJson()) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], $this->isHttpException($e) ? $e->getStatusCode() : 500);
    }

    return parent::render($request, $e);
}

}
