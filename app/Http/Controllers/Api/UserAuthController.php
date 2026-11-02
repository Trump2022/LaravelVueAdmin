<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;


class UserAuthController extends Controller
{
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create($data);
        $guard = Auth::guard('user-api');
        $token = $guard->login($user);
        $user->sendEmailVerificationNotification();

        return $this->tokenResponse($user, $token, '注册成功', 201);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = Auth::guard('user-api');
        $token = $guard->attempt($credentials);

        if (! $token) {
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误。'],
            ]);
        }

        return $this->tokenResponse($guard->user(), $token, '登录成功');
    }

    /**
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        Auth::guard('user-api')->logout();

        return response()->json(['message' => '已退出登录。']);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->userData($request->user('user-api')),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user('user-api');
        $user->name = $data['name'];
        $user->save();

        return response()->json([
            'message' => '个人信息已更新。',
            'user' => $this->userData($user),
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user('user-api');

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['当前密码不正确。'],
            ]);
        }

        $user->password = $data['password'];
        $user->save();

        return response()->json(['message' => '密码已更新。']);
    }

    /**
     * @param User $user
     * @param string $token
     * @param string $message
     * @param int $status
     * @return JsonResponse
     */
    private function tokenResponse(User $user, string $token, string $message, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('user-api')->getTTL() * 60,
            'user' => $this->userData($user),
        ], $status);
    }

    /**
     * @param User $user
     * @return array
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
        ];
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user('user-api');

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => '邮箱已经验证。']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => '验证邮件已发送。'], 202);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function sendPasswordResetLink(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        Password::broker('users')->sendResetLink($data);

        // 对存在和不存在的邮箱返回相同内容，避免泄露账号是否注册。
        return response()->json([
            'message' => '如果该邮箱已注册，重置邮件将发送。',
        ], 202);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('users')->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => '密码已重置，请使用新密码登录。',
        ]);
    }

    /**
     * @param string $id
     * @param string $hash
     * @return RedirectResponse
     */
    public function verifyEmail(string $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect('/web/account?verified=1');
    }
}
