<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\userDeviceToken;
use App\Models\UserOtp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class UserController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/check-mobile-exists",
     *     summary="Check Mobile Exists",
     *     tags={"User"},
     *     description="Check Mobile Exists",
     *     operationId="checkMobileExists",
     *     @OA\Parameter(
     *         name="country_code",
     *         in="query",
     *         example="+91",
     *         description="Enter Country Code",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="mobile",
     *         in="query",
     *         example="9876543210",
     *         description="Enter Mobile Number",
     *         required=true,
     *         @OA\Schema(
     *             type="number",
     *         )
     *     ),
     *      @OA\Response(
     *         response=200,
     *         description="json schema",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invalid Request"
     *     ),
     * )
     */

    public function checkMobileExists(Request $request)
    {
        try {
            $rules = [
                'country_code' => 'required|string|max:255|regex:/^\+\d{1,3}$/',
                'mobile' => 'required|string|max:10|regex:/^\d{10,}$/',
            ];

            $message = [
                'country_code.required' => 'Country code is required.',
                'country_code.string' => 'Country code must be a string.',
                'country_code.max' => 'Country code must not exceed 255 characters.',
                'country_code.regex' => 'Invalid country code format. It should start with "+" followed by one to three digits.',
                'mobile.required' => 'Mobile number is required.',
                'mobile.string' => 'Mobile number must be a string.',
                'mobile.max' => 'Mobile number must not exceed 10 characters.',
                'mobile.regex' => 'Invalid mobile number format. It should be numeric and at least 10 digits long.',
            ];

            $validator = Validator::make($request->all(), $rules, $message);
            if ($validator->fails()) {
                $data = [
                    'status_code' => 400,
                    'message' => $validator->errors()->first(),
                    'data' => ""
                ];
                return $this->sendJsonResponse($data);
            }
            $user = new User();
            $userData = $user->getUserDetailsUsingMobile($request->country_code, $request->mobile);
            if (empty($userData)) {
                $data = [
                    'status_code' => 200,
                    'message' => "User Not Found!",
                    'data' => ""
                ];
            } else {
                $data = [
                    'status_code' => 400,
                    'message' => 'User Already Exists',
                    'data' => ""
                ];
            }
            return $this->sendJsonResponse($data);
        } catch (\Exception $e) {
            Log::error(
                [
                    'method' => __METHOD__,
                    'error' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'message' => $e->getMessage()
                    ],
                    'created_at' => date("Y-m-d H:i:s")
                ]
            );
            return $this->sendJsonResponse(array('status_code' => 500, 'message' => 'Something went wrong'));
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/user-registration",
     *     summary="User Registration",
     *     tags={"User"},
     *     description="User Registration",
     *     operationId="userRegistration",
     *     @OA\RequestBody(
     *         required=true,
     *         description="User Registration Request",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"first_name", "last_name", "country_code", "mobile", "otp", "device_token"},
     *                 @OA\Property(
     *                     property="first_name",
     *                     type="string",
     *                     example="Test",
     *                     description="Enter First Name"
     *                 ),
     *                 @OA\Property(
     *                     property="last_name",
     *                     type="string",
     *                     example="User",
     *                     description="Enter Last Name"
     *                 ),
     *                 @OA\Property(
     *                     property="country_code",
     *                     type="string",
     *                     example="+91",
     *                     description="Enter Country Code"
     *                 ),
     *                 @OA\Property(
     *                     property="mobile",
     *                     type="number",
     *                     example="9876543210",
     *                     description="Enter Mobile Number"
     *                 ),
     *                 @OA\Property(
     *                     property="otp",
     *                     type="number",
     *                     example="123456",
     *                     description="Enter OTP"
     *                 ),
     *                 @OA\Property(
     *                     property="device_token",
     *                     type="string",
     *                     example="",
     *                     description="Enter Device Token"
     *                 ),
     *                 @OA\Property(
     *                     property="profile",
     *                     type="file",
     *                     description="Profile Image"
     *                 ),
     *                 @OA\Property(
     *                     property="cover_image",
     *                     type="file",
     *                     description="Cover Image"
     *                 ),
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="json schema",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invalid Request"
     *     ),
     * )
     */


    public function userRegistration(Request $request)
    {
        try {
            $rules = [
                'first_name' => 'required|string|regex:/^[a-zA-Z\s]+$/|max:255',
                'last_name' => 'nullable|string|regex:/^[a-zA-Z\s]+$/|max:255',
                'country_code' => 'required|string|max:255|regex:/^\+\d{1,3}$/',
                'mobile' => 'required|string|max:10|regex:/^\d{10,}$/',
                'otp' => 'required|numeric|min:100000|max:999999',
                'profile' => 'nullable|image|mimes:jpeg,jpg,png,webp,svg|max:2048',
                'cover_image' => 'nullable|image|mimes:jpeg,jpg,png,webp,svg|max:2048',
                'device_token' => 'required|string'
            ];

            $message = [
                'first_name.required' => 'First name is required.',
                'first_name.string' => 'First name must be a string.',
                'first_name.regex' => 'First name must contain only alphabets and spaces.',
                'first_name.max' => 'First name must not exceed 255 characters.',
                'last_name.string' => 'Last name must be a string.',
                'last_name.regex' => 'Last name must contain only alphabets and spaces.',
                'last_name.max' => 'Last name must not exceed 255 characters.',
                'country_code.required' => 'Country code is required.',
                'country_code.string' => 'Country code must be a string.',
                'country_code.max' => 'Country code must not exceed 255 characters.',
                'country_code.regex' => 'Invalid country code format. It should start with "+" followed by one to three digits.',
                'mobile.required' => 'Mobile number is required.',
                'mobile.string' => 'Mobile number must be a string.',
                'mobile.max' => 'Mobile number must not exceed 10 characters.',
                'mobile.regex' => 'Invalid mobile number format. It should be numeric and at least 10 digits long.',
                'otp.required' => 'OTP is required.',
                'otp.numeric' => 'OTP must be a numeric value.',
                'otp.min' => 'OTP must be at least 6 digits long.',
                'otp.max' => 'OTP must be at most 6 digits long.',
                'profile.image' => 'Profile image must be an image file.',
                'profile.mimes' => 'Profile image must be a JPEG, JPG, PNG,svg, or WebP file.',
                'profile.max' => 'Profile image size must not exceed 2MB.',
                'cover_image.image' => 'Cover image must be an image file.',
                'cover_image.mimes' => 'Cover image must be a JPEG, JPG, PNG,svg, or WebP file.',
                'cover_image.max' => 'Cover image size must not exceed 2MB.',
                'device_token.required' => 'Device token is required.'
            ];

            $validator = Validator::make($request->all(), $rules, $message);
            if ($validator->fails()) {
                $data = [
                    'status_code' => 400,
                    'message' => $validator->errors()->first(),
                    'data' => ""
                ];
                return $this->sendJsonResponse($data);
            }
            $user = new User();
            $userData = $user->getUserDetailsUsingMobile($request->country_code, $request->mobile);
            if (!empty($userData)) {
                $data = [
                    'status_code' => 400,
                    'message' => 'User Already Exists',
                    'data' => ""
                ];
                return $this->sendJsonResponse($data);
            }
            $otpVerification = UserOtp::where([
                'country_code' => $request->country_code,
                'mobile' => $request->mobile,
                'otp' => $request->otp,
                'status' => 'Active'
            ])->first();
            if (!$otpVerification) {
                $data = [
                    'status_code' => 400,
                    'message' => 'Invalid OTP',
                    'data' => ""
                ];
                return $this->sendJsonResponse($data);
            }

            $profileImageName = NULL;
            if ($request->hasFile('profile')) {
                $profileImage = $request->file('profile');
                $profileImageName = imageUpload($profileImage, 'user-profile');
                if ($profileImageName == 'upload_failed') {
                    $data = [
                        'status_code' => 400,
                        'message' => 'profile Upload faild',
                        'data' => ""
                    ];
                    return $this->sendJsonResponse($data);
                }
            }
            $coverImageName = NULL;
            if ($request->hasFile('cover_image')) {
                $coverImage = $request->file('cover_image');
                $coverImageName = imageUpload($coverImage, 'user-profile-cover-image');
                if ($coverImageName == 'upload_failed') {
                    $data = [
                        'status_code' => 400,
                        'message' => 'Cover Image Upload faild',
                        'data' => ""
                    ];
                    return $this->sendJsonResponse($data);
                }
            }

            $user->first_name = $request->first_name;
            $user->last_name = $request->last_name;
            $user->country_code = $request->country_code;
            $user->mobile = $request->mobile;
            $user->profile = $profileImageName;
            $user->cover_image = $coverImageName;
            $user->role = "User";
            $user->status = "Active";
            $user->save();
            $token = JWTAuth::fromUser($user);
            $otpVerification->status = 'Inactive';
            $otpVerification->save();

            $userDeviceToken  = new userDeviceToken();
            $userDeviceToken->user_id = $user->id;
            $userDeviceToken->token = $request->device_token;
            $userDeviceToken->save();

            $authData['userDetails'] = $user;
            $authData['token'] = $token;
            $authData['token_type'] = 'bearer';
            $authData['expires_in'] = JWTAuth::factory()->getTTL() * 60 * 24;
            $data = [
                'status_code' => 200,
                'message' => "User Registered Successfully.",
                'data' => $authData
            ];
            return $this->sendJsonResponse($data);
        } catch (\Exception $e) {
            Log::error(
                [
                    'method' => __METHOD__,
                    'error' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'message' => $e->getMessage()
                    ],
                    'created_at' => date("Y-m-d H:i:s")
                ]
            );
            return $this->sendJsonResponse(array('status_code' => 500, 'message' => 'Something went wrong'));
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/logout",
     *     summary="Logout",
     *     tags={"Authentication"},
     *     description="Logout",
     *     operationId="logout",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="device_token",
     *         in="query",
     *         example="",
     *         description="Enter Device Token",
     *         required=true,
     *         @OA\Schema(
     *             type="string",
     *         )
     *     ),
     *      @OA\Response(
     *         response=200,
     *         description="json schema",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invalid Request"
     *     ),
     * )
     */

    public function logout(Request $request)
    {
        try {
            $rules = [
                'device_token' => 'required'
            ];

            $message = [
                'device_token.required' => 'Device token is required.',
            ];

            $validator = Validator::make($request->all(), $rules, $message);
            if ($validator->fails()) {
                $data = [
                    'status_code' => 400,
                    'message' => $validator->errors()->first(),
                    'data' => ""
                ];
                return $this->sendJsonResponse($data);
            }
            userDeviceToken::where('user_id',auth()->user()->id)->where('token',$request->device_token)->forceDelete();

            $data = [
                'status_code' => 200,
                'message' => "Logout Successfully.",
                'data' => ""
            ];
            return $this->sendJsonResponse($data);
        } catch (\Exception $e) {
            Log::error(
                [
                    'method' => __METHOD__,
                    'error' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'message' => $e->getMessage()
                    ],
                    'created_at' => date("Y-m-d H:i:s")
                ]
            );
            return $this->sendJsonResponse(array('status_code' => 500, 'message' => 'Something went wrong'));
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/users-mobile-numbers",
     *     summary="User Mobile Numbers",
     *     tags={"User"},
     *     description="User Mobile Numbers",
     *     operationId="userMobileNumbers",
     *     security={{"bearerAuth":{}}},
     *      @OA\Response(
     *         response=200,
     *         description="json schema",
     *         @OA\MediaType(
     *             mediaType="application/json",
     *         ),
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Invalid Request"
     *     ),
     * )
     */

     public function userMobileNumbers(Request $request)
    {
        try {
            $mobileNumbers = User::where('role','User')->where('status','Active')->get(['country_code','mobile']);

            $data = [
                'status_code' => 200,
                'message' => "Get Data Successfully.",
                'data' => [
                    'mobileNumbers' => $mobileNumbers
                ]
            ];
            return $this->sendJsonResponse($data);
        } catch (\Exception $e) {
            Log::error(
                [
                    'method' => __METHOD__,
                    'error' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'message' => $e->getMessage()
                    ],
                    'created_at' => date("Y-m-d H:i:s")
                ]
            );
            return $this->sendJsonResponse(array('status_code' => 500, 'message' => 'Something went wrong'));
        }
    }

}