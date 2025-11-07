<?php

namespace App\Http\Controllers;

use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FirebaseTestController extends Controller
{
    protected $firebaseService;

    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Test Firestore connection
     * GET: /api/firebase/test-firestore
     */
    public function testFirestore()
    {
        try {
            // Test write
            $testData = [
                'id' => 'test-' . time(),
                'message' => 'Test notification from Laravel',
                'created_at' => now()->toIso8601String(),
                'status' => 'test',
            ];

            $success = $this->firebaseService->pushToFirestore($testData);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Firestore connection successful!',
                    'data' => $testData,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to write to Firestore',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Firestore Test Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error testing Firestore: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test FCM notification
     * POST: /api/firebase/test-fcm
     * Body: {
     *   "fcm_tokens": ["token1", "token2"],
     *   "title": "Test Notification",
     *   "body": "This is a test message"
     * }
     */
    public function testFCM(Request $request)
    {
        try {
            $validated = $request->validate([
                'fcm_tokens' => 'required|array|min:1',
                'fcm_tokens.*' => 'required|string',
                'title' => 'required|string|max:200',
                'body' => 'required|string|max:500',
            ]);

            $success = $this->firebaseService->sendFCM(
                $validated['fcm_tokens'],
                $validated['title'],
                $validated['body'],
                ['type' => 'test']
            );

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'FCM notification sent successfully!',
                    'data' => [
                        'tokens_count' => count($validated['fcm_tokens']),
                        'title' => $validated['title'],
                        'body' => $validated['body'],
                    ],
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send FCM notification',
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('FCM Test Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error sending FCM: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test reading from Firestore
     * GET: /api/firebase/test-read?doc_id=test-notification-id
     */
    public function testRead(Request $request)
    {
        try {
            $docId = $request->query('doc_id', 'test-' . (time() - 10));

            $data = $this->firebaseService->getNotification($docId);

            if ($data) {
                return response()->json([
                    'success' => true,
                    'message' => 'Data retrieved successfully!',
                    'data' => $data,
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Document not found in Firestore',
                    'doc_id' => $docId,
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('Firestore Read Test Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error reading from Firestore: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete test: Save notification and send FCM
     * POST: /api/firebase/test-complete
     * Body: {
     *   "fcm_tokens": ["token1"],
     *   "title": "Complete Test",
     *   "body": "Testing full workflow",
     *   "user_id": 1
     * }
     */
    public function testComplete(Request $request)
    {
        try {
            $validated = $request->validate([
                'fcm_tokens' => 'required|array|min:1',
                'fcm_tokens.*' => 'required|string',
                'title' => 'required|string',
                'body' => 'required|string',
                'user_id' => 'nullable|integer',
            ]);

            $notificationId = 'notif-' . time();

            // Step 1: Save to Firestore
            $notificationData = [
                'id' => $notificationId,
                'user_id' => $validated['user_id'] ?? null,
                'title' => $validated['title'],
                'body' => $validated['body'],
                'created_at' => now()->toIso8601String(),
                'status' => 'sent',
            ];

            $firestore_success = $this->firebaseService->pushToFirestore($notificationData);

            // Step 2: Send FCM
            $fcm_success = $this->firebaseService->sendFCM(
                $validated['fcm_tokens'],
                $validated['title'],
                $validated['body'],
                ['notification_id' => $notificationId]
            );

            return response()->json([
                'success' => $firestore_success && $fcm_success,
                'message' => 'Complete test finished',
                'steps' => [
                    'firestore_saved' => $firestore_success,
                    'fcm_sent' => $fcm_success,
                ],
                'notification_id' => $notificationId,
                'data' => $notificationData,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Complete Test Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error in complete test: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check
     * GET: /api/firebase/health
     */
    public function health()
    {
        return response()->json([
            'success' => true,
            'message' => 'Firebase service is running',
            'timestamp' => now(),
        ]);
    }
}