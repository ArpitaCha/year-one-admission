<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use App\Models\SuperUser;
use App\Models\StudentChoice;
use App\Models\Token;

use App\Models\Trade;
use App\Models\District;
use App\Models\Board;
use App\Models\Role;
use App\Models\State;
use App\Models\Institute;
use App\Models\Eligibility;
use App\Models\AuthPermission;
use App\Models\AuthUrl;
use App\Models\AlotedAdmittedSeatMaster;
use App\Models\AlotedAdmittedPvtSeatMaster;
use App\Models\SpotSeatMaster;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\TradeResource;
use App\Http\Resources\EligibilityResource;
use App\Http\Resources\EligibilityBoardResource;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\DistrictResource;
use App\Http\Resources\InstituteResource;
use App\Http\Resources\AllotmentStudentResource;
use App\Http\Resources\SubdivisionResource;
use App\Http\Resources\StateResource;
use App\Http\Resources\InstAdminResource;
use App\Http\Resources\BlockResource;
use Illuminate\Support\Str;
use App\Models\Schedule;
use App\Models\Block;
use App\Models\Subdivision;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Mail;
use App\Mail\ChoiceLockedEmail;
use App\Mail\ChoiceEmail;

class AuthTokenMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, $requiredPermission = null)
    {
        if ($request->route('user_type')) {
            return $next($request);
        }
        $token = $request->header('token');
        if (!$token) {
            return response()->json([
                'error' => true,
                'message' => 'Token missing from request'
            ], 401);
        }

        $now    =   date('Y-m-d H:i:s');
        $token_check = Token::where('t_token', $token)
            ->where('t_expired_on', '>=', $now)
            ->first();

        if (!$token_check) {
            return response()->json([
                'error' => true,
                'message' => 'Invalid or expired token'
            ], 401);
        }

        $user_id = $token_check->t_user_id;

        $user_data = Student::where('s_id', $user_id)->first();
        if ($user_data) {
            $user_role_id = $user_data->u_role_id;
        } else {
            $admin_user = SuperUser::where('u_id', $user_id)->first();
            $user_role_id = $admin_user->u_role_id ?? null;
        }

        if (!$user_role_id) {
            return response()->json([
                'error' => true,
                'message' => 'User role not found'
            ], 403);
        }

        $role_url_access_id = AuthPermission::where('rp_role_id', $user_role_id)->pluck('rp_url_id');
        $urls = AuthUrl::where('url_visible', 1)
            ->whereIn('url_id', $role_url_access_id)
            ->pluck('url_name')
            ->toArray();

        if ($requiredPermission && !in_array($requiredPermission, $urls)) {
            return response()->json([
                'error' => true,
                'message' => "Oops! you don't have sufficient permission"
            ], 403);
        }

        // Attach useful user data into request so controller can access
        $request->merge([
            'auth_user_id' => $user_id,
            'auth_role_id' => $user_role_id,
            'auth_user' =>  $user_data ?? $admin_user,
        ]);

        return $next($request);
    }
}
