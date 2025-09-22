<?php
/**
 * Class UserController
 *
 * Handles user-related operations such as registration, authentication,
 * sending welcome emails, account activation, and managing user portfolios.
 *
 * @package App\Http\Controllers\user
 */
namespace App\Http\Controllers\user;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    /**
     * Register a new user with email and password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signup(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        // Check if user already exists
        if (DB::table('users')->where('email', $request->input('email'))->exists()) {
            return response()->json([
                'message' => 'User with this email already exists.'
            ], 409);
        }

        $userId = DB::table('users')->insertGetId([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = DB::table('users')->where('id', $userId)->first();        

        // Send welcome email
        //$this->sendWelcomeEmail($user->email, $user->name);

        // Register user in WordPress
        try {
            $wpResponse = \Illuminate\Support\Facades\Http::post('https://accounts.tradeengine.io/wp-json/user/v1/register', [
                'username' =>  $request->input('name'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'user_id' => $userId,
            ]);
            $wpResult = $wpResponse->json();

            DB::table('user_external_subscriptions')->insert(
            [
                'user_id' => $wpResult['laravel_id'],
                'wordpress_id' => $wpResult['wordpress_id'],
                'plan_name' => $wpResult['membership_name'],
                'subscription_status' => 'active',
                'start_date' => $wpResult['start_date'],
                'end_date' => $wpResult['end_date'],
                'payment_gateway' => null,
                'raw_payload' => null
            ]
        );

        } catch (\Exception $e) {
            // Optionally, you can delete the user if WP registration fails
            // DB::table('users')->where('id', $userId)->delete();
            return response()->json([
                'message' => 'User registered locally, but failed to register in WordPress.',
                'user' => $user,
                'wp_error' => $e->getMessage(),
            ], 201);
        }

        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'wordpress' => $wpResult ?? null,
        ], 201);
    }


    /**
     * Send a welcome email to the user after registration with an activation link.
     *
     * Generates a unique activation token, stores it in the users table,
     * and sends an email containing the activation link to the specified user.
     *
     * @param string $email The email address of the user.
     * @param string $name  The name of the user.
     * @return void
     */
    private function sendWelcomeEmail($email, $name)
    {

        // Generate a simple activation token
        $token = bin2hex(random_bytes(32));
        $activationLink = url("/v1/user/activate?token=$token&email=" . urlencode($email));

        // Store the token in the users table for later verification
        DB::table('users')
        ->where('email', $email)
        ->update(['verify_token' => $token]);

        $subject = 'Welcome to TradeEngine.io! Activate Your Account';
        $message = "Hi $name,\n\nThank you for registering at TradeEngine.io. Please activate your account by clicking the link below:\n\n$activationLink\n\nBest regards,\nThe TradeEngine Team";

        $sendEmail = \Mail::raw($message, function ($mail) use ($email, $subject) {
            $mail->from('no-reply@tradeengine.io', 'TradeEngine')    // ← MUST match a domain you control
                ->to($email)
                ->subject($subject);
        });
    }

    /**
     * Activate a user's account using the provided token and email.
     *
     * Verifies the activation token for the given email, and if valid,
     * updates the user's email_verified_at field and clears the activation token.
     *
     * @param \Illuminate\Http\Request $request The HTTP request containing 'email' and 'token' query parameters.
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate(Request $request)
    {
        $email = $request->query('email');
        $token = $request->query('token'); // In production, verify this token

        // Update email_verified_at for the user
        $updated = DB::table('users')
            ->where('email', $email)
            ->where('verify_token', $token) // Ensure the token matches
            ->whereNull('email_verified_at') // Only update if not already verified
            ->update(['email_verified_at' => now()]);

        if ($updated) {
            return response()->json(['message' => 'Account activated successfully.']);
        } else {
            return response()->json(['message' => 'Activation failed.'], 400);
        }
    }

    /**
     * Sign in a user with email and password.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signin(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = DB::table('users')->where('email', $request->input('email'))->first();

        if (!$user || !Hash::check($request->input('password'), $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.'
            ], 401);
        }

        // check if user is verified
        /**if (is_null($user->email_verified_at)) {
            return response()->json([
                'message' => 'Please verify your email before signing in.'
            ], 403);
        }**/

        // Generate a random 40-character alphanumeric token
        $token = $this->generateRandomToken(40);
        $createdAt = now();
        $expireAt = now()->addDay();

        // Get user's other details
        $userData = null;
        if (DB::table('user_external_subscriptions')->where('user_id', $user->id)->exists()) {
            $userData = DB::table('user_external_subscriptions')->where('user_id', $user->id)->first();
            // ...do something with $userData
        }

        // Save token to user_token table
        DB::table('user_token')->insert([
            'user_id' => $user->id,
            'token' => $token,
            'created_at' => $createdAt,
            'expire_at' => $expireAt,
        ]);

        return response()->json([
            'message' => 'Signin successful',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_level' => $user->user_level ?? 'user',
                'plan' => $userData->plan_name ?? null
            ],
            'token' => $token,
            'expire_at' => $expireAt,
        ]);
    }

    /**
     * Generate a secure random token string.
     *
     * Creates a cryptographically secure random token, typically used for
     * account activation, password reset, or other verification processes.
     *
     * @param int $length The length of the generated token in bytes. Default is 32.
     * @return string The generated random token as a hexadecimal string.
     */
    private function generateRandomToken($length = 32)
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Register or sign in a user using Google OAuth.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signupWithGoogle(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        // Verify the token with Google
        $googleResponse = file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token=' . $request->input('id_token'));
        $googleUser = json_decode($googleResponse, true);

        if (!isset($googleUser['email'])) {
            return response()->json(['message' => 'Invalid Google token.'], 401);
        }

        $user = DB::table('users')->where('email', $googleUser['email'])->first();

        if ($user) {
            return response()->json([
                'message' => 'User already exists',
                'user' => $user
            ], 200);
        }

        $userId = DB::table('users')->insertGetId([
            'name' => $googleUser['name'] ?? '',
            'email' => $googleUser['email'],
            'password' => '', // No password for Google signup
            'created_at' => now(),
            'updated_at' => now(),
            'google_id' => $googleUser['sub'],
        ]);

        $user = DB::table('users')->where('id', $userId)->first();

        return response()->json([
            'message' => 'User registered with Google successfully',
            'user' => $user
        ], 201);
    }

    /**
     * Delete a user by email.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        $user = DB::table('users')->where('email', $request->input('email'))->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        DB::table('users')->where('email', $request->input('email'))->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }

    /**
     * Logout a user by deleting their token from the user_token table.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $deleted = DB::table('user_token')->where('user_id', $request->input('user_id'))->delete();

        if ($deleted) {
            return response()->json(['message' => 'Logout successful.']);
        } else {
            return response()->json(['message' => 'Invalid token or already logged out.'], 400);
        }
    }

    /**
     * Store or update a user's external subscription from WordPress.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveExternalSubscription(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'wordpress_id' => 'required|integer',
            'plan_name' => 'required|string',
            'subscription_status' => 'required|string',
            'ai_token' => 'integer|nullable',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'payment_gateway' => 'string',
            'raw_payload' => 'string',
        ]);

        DB::table('user_external_subscriptions')->updateOrInsert(
            ['user_id' => $request->input('user_id')],
            [
                'wordpress_id' => $request->input('wordpress_id'),
                'plan_name' => $request->input('plan_name'),
                'subscription_status' => $request->input('subscription_status'),
                'ai_token' => $request->input('ai_token') ?? 0,
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'payment_gateway' => $request->input('payment_gateway') ?? null,
                'raw_payload' => $request->input('raw_payload') ?? null
            ]
        );

        return response()->json(['message' => 'External subscription saved successfully.']);
    }

    /**
     * Resend the verification link to the user's email.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resendVerificationLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = DB::table('users')->where('email', $request->input('email'))->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Generate a new activation token
        $token = bin2hex(random_bytes(32));
        DB::table('users')
            ->where('email', $user->email)
            ->update(['activation_token' => $token]);

        $activationLink = url("/v1/user/activate?token=$token&email=" . urlencode($user->email));

        $subject = 'Resend: Activate Your Account';
        $message = "Hi {$user->name},\n\nPlease activate your account by clicking the link below:\n\n$activationLink\n\nBest regards,\nThe TradeEngine Team";

        \Mail::raw($message, function ($mail) use ($user, $subject) {
            $mail->to($user->email)
                ->subject($subject)
                ->from('no-reply@tradeengine.io', 'TradeEngine');
        });

        return response()->json(['message' => 'Verification link resent.']);
    }

    /**
     * Retrieve a user's external subscription data from the user_external_subscriptions table.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExternalSubscription(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $subscription = DB::table('user_external_subscriptions')
            ->where('user_id', $request->input('user_id'))
            ->first();

        if (!$subscription) {
            return response()->json(['message' => 'No external subscription found for this user.'], 404);
        }

        return response()->json(['subscription' => $subscription]);
    }

    /**
     * Update the ai_token on the user_external_subscriptions table by user_id.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateExternalSubscription(Request $request)
    {
        // Validate incoming request
        $request->validate([
            'user_id'  => 'required|integer|exists:users,id',
            'ai_token' => 'required|integer',
        ]);

        $userId     = $request->input('user_id');
        $increment  = $request->input('ai_token');

        // 1) Fetch current ai_token
        $subscription = DB::table('user_external_subscriptions')
            ->where('user_id', $userId)
            ->first(['ai_token']);

        if (! $subscription) {
            return response()->json([
                'message' => 'No external subscription found for this user.'
            ], 404);
        }

        $oldToken = (int) $subscription->ai_token;
        $newTotal = $oldToken + $increment;

        // 2) Update with the sum
        DB::table('user_external_subscriptions')
            ->where('user_id', $userId)
            ->update(['ai_token' => $newTotal]);

        // 3) Return both old, increment, and new total
        return response()->json([
            'message'        => 'ai_token incremented successfully.',
            'user_id'        => $userId,
            'old_ai_token'   => $oldToken,
            'added_ai_token' => $increment,
            'new_ai_token'   => $newTotal,
        ]);
    }

    /**
     * Check the AI token balance of a user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkAiToken(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $aiToken = DB::table('user_external_subscriptions')
            ->where('user_id', $request->input('user_id'))
            ->value('ai_token');

        if ($aiToken === null) {
            return response()->json(['message' => 'No AI token record found for this user.'], 404);
        }

        return response()->json(['ai_token' => $aiToken]);
    }

    /**
     * Check and deduct AI token for a user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function useAiToken(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $userId = $request->input('user_id');
        $aiToken = DB::table('user_external_subscriptions')
            ->where('user_id', $userId)
            ->value('ai_token');

        if (is_null($aiToken)) {
            return response()->json(['error' => 'No AI token record found for this user.'], 404);
        }

        if ($aiToken <= 0) {
            return response()->json(['error' => 'Insufficient AI tokens'], 400);
        }

        DB::table('user_external_subscriptions')
            ->where('user_id', $userId)
            ->update(['ai_token' => $aiToken - 1]);

        return response()->json([
            'message' => 'AI token deducted successfully.',
            'remaining_ai_token' => $aiToken - 1
        ]);
    }
}

