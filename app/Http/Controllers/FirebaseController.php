<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FirebaseService;

class FirebaseController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function testFCM(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'title' => 'required',
            'body' => 'required',
        ]);

        $response = $this->firebase->sendNotification(
            $request->token,
            $request->title,
            $request->body
        );

        return response()->json($response);
    }
}
