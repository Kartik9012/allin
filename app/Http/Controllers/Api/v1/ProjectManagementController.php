<?php

namespace App\Http\Controllers\Api\v1;

use App\Exports\workHoursExport;
use App\Http\Controllers\Controller;
use App\Mail\WorkHoursMail;
use App\Models\Notes;
use App\Models\User;
use App\Models\WorkingHours;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;

class ProjectManagementController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/add-work-hours",
     *     summary="Add a new Reminder",
     *     tags={"Project Management"},
     *     description="Create a new message for Project Management.",
     *     operationId="addWorkHours",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Add Message Request",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"start_date_time","end_date_time","timezone"},
     *                 @OA\Property(
     *                     property="start_date_time",
     *                     type="string",
     *                     example="2024-05-17 19:15:00",
     *                     description="Enter Start Date Time"
     *                 ),
     *                 @OA\Property(
     *                     property="end_date_time",
     *                     type="string",
     *                     example="2024-05-17 21:15:00",
     *                     description="Enter End Date Time"
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="string",
     *                     example="",
     *                     description="Enter Work Summary"
     *                 ),
     *                 @OA\Property(
     *                     property="timezone",
     *                     type="string",
     *                     example="Asia/Kolkata",
     *                     description="Enter timezone"
     *                 ),
     *             )
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

    public function addWorkHours(Request $request)
    {
        try {
            $rules = [
                'start_date_time' => 'required|string',
                'end_date_time' => 'nullable|string',
                'timezone' => 'required|string',
            ];

            $message = [
                'start_date_time.required' => 'Start Date Time is required.',
                'start_date_time.string' => 'Start Date Time must be an String.',
                'end_date_time.required' => 'End Date Time is required.',
                'end_date_time.string' => 'End Date Time must be an String.',
                'timezone.required' => 'Timezone is required.',
                'timezone.string' => 'Timezone must be an String.',
            ];

            $validator = Validator::make($request->all(), $rules, $message);
            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => $validator->errors()->first(),
                    'data' => ""
                ]);
            }

            $startDateTime = Carbon::parse($request->start_date_time);
            $endDateTime = Carbon::parse($request->end_date_time);
            $totalMinutes = $startDateTime->diffInMinutes($endDateTime);
            $totalHours = floor($totalMinutes / 60);
            $remainingMinutes = $totalMinutes % 60;
            $totalWorkingTime = sprintf('%02dh%02dmin', $totalHours, $remainingMinutes);

            $workHours = new WorkingHours();
            $workHours->user_id = auth()->user()->id;
            $workHours->start_date_time = $request->start_date_time;
            $workHours->end_date_time = $request->end_date_time;
            $workHours->summary = $request->summary;
            $workHours->timezone = $request->timezone;
            $workHours->total_hours = $totalWorkingTime;
            $workHours->save();



            $data = [
                'status_code' => 200,
                'message' => "Work Hours Successfully Add!",
                'data' => []
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error([
                'method' => __METHOD__,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ],
                'created_at' => date("Y-m-d H:i:s")
            ]);
            return response()->json(['status_code' => 500, 'message' => 'Something went wrong']);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/work-hours",
     *     summary="Add a new Reminder",
     *     tags={"Project Management"},
     *     description="Create a new message for Project Management.",
     *     operationId="workHours",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         description="Enter Month Name",
     *         example="2024-06",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
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

    public function workHours(Request $request)
    {
        try {
            $rules = [
                'month' => 'nullable|string',
            ];
            $message = [
                'month.nullable' => 'Month is required',
                'month.string' => 'Month must be an String'
            ];
            $validator = Validator::make($request->all(), $rules, $message);
            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => $validator->errors()->first(),
                    'data' => ""
                ]);
            }
            $filterMonth = $request->filled('mon                                                                                                              th') ? Carbon::parse($request->month) : Carbon::now();
            $workHours = WorkingHours::where('user_id', auth()->user()->id)
                ->whereYear('start_date_time', $filterMonth->year)
                ->whereMonth('start_date_time', $filterMonth->month)
                ->orderByDesc('id')
                ->get(['id', 'start_date_time', 'end_date_time', 'total_hours', 'summary']);
            $format = $workHours->map(function ($workHour) {
                $workHour->start_date_time = Carbon::parse($workHour->start_date_time)->format('Y-m-d H:i:s');
                $workHour->end_date_time = Carbon::parse($workHour->end_date_time)->format('Y-m-d H:i:s');
                return $workHour;
            });
            $data = [
                'status_code' => 200,
                'message' => "Work Hours Successfully get!",
                'data' => [
                    'workHours' => $format
                ]
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            Log::error([
                'method' => __METHOD__,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ],
                'created_at' => date("Y-m-d H:i:s")
            ]);
            return response()->json(['status_code' => 500, 'message' => 'Something went wrong']);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/edit-work-hours-summary",
     *     summary="Edit a work hours",
     *     tags={"Project Management"},
     *     description="Create a new message for Project Management.",
     *     operationId="editWorkHoursSummary",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Enter work hour Id",
     *         example="1",
     *         required=true,
     *         @OA\Schema(
     *             type="number"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="summary",
     *         in="query",
     *         description="Enter work hour Summary",
     *         example="",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
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

    public function editWorkHoursSummary(Request $request)
    {
        try {
            $rules = [
                'id' => 'required|integer',
                'summary' => 'nullable|string',
            ];
            $message = [
                'id.required' => 'Id is required',
                'id.integer' => 'Id must be an Integer',
                'summary.string' => 'Summary must be an String'
            ];
            $validator = Validator::make($request->all(), $rules, $message);
            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => $validator->errors()->first(),
                    'data' => ""
                ]);
            }
            $workHour = WorkingHours::find($request->id);
            if ($workHour) {
                $workHour->summary = $request->summary;
                $workHour->save();
                $data = [
                    'status_code' => 200,
                    'message' => "Work Hours Successfully get!",
                    'data' => [
                        'workHours' => $workHour
                    ]
                ];
                return response()->json($data);
            } else {
                return response()->json([
                    'status_code' => 400,
                    'message' => "Work Hours Not Found!",
                    'data' => ""
                ]);
            }
        } catch (\Exception $e) {
            Log::error([
                'method' => __METHOD__,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ],
                'created_at' => date("Y-m-d H:i:s")
            ]);
            return response()->json(['status_code' => 500, 'message' => 'Something went wrong']);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/add-note",
     *     summary="Add a new Note",
     *     tags={"Project Management"},
     *     description="Create a new note for Project Management.",
     *     operationId="addNote",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="title",
     *         in="query",
     *         description="Enter Note Title",
     *         example="",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="description",
     *         in="query",
     *         description="Enter Note description",
     *         example="",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
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

    public function addNote(Request $request)
    {
        try {
            $rules = [
                'title' => 'required|string',
                'description' => 'nullable|string',
            ];
            $message = [
                'title.required' => 'Title is required',
                'title.string' => 'Title must be an String',
                'description.string' => 'Description must be an String'
            ];
            $validator = Validator::make($request->all(), $rules, $message);
            if ($validator->fails()) {
                return response()->json([
                    'status_code' => 400,
                    'message' => $validator->errors()->first(),
                    'data' => ""
                ]);
            }
            $note = new Notes();
            $note->user_id = auth()->user()->id;
            $note->title = $request->title;
            $note->description = $request->description;
            $note->save();
            $data = [
                'status_code' => 200,
                'message' => "Note Successfully Add!",
                'data' => [
                    'note' => $note
                ]
            ];
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error([
                'method' => __METHOD__,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ],
                'created_at' => date("Y-m-d H:i:s")
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/note",
     *     summary="new Note list",
     *     tags={"Project Management"},
     *     description="List of note for Project Management.",
     *     operationId="noteLost",
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

    public function notes(Request $request)
    {
        try {
            $data = [
                'status_code' => 200,
                'message' => "Note get Successfully!",
                'data' => [
                    'notes' => Notes::where('user_id', auth()->user()->id)->get()
                ]
            ];
            return response()->json($data);
        } catch (\Exception $e) {
            Log::error([
                'method' => __METHOD__,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ],
                'created_at' => date("Y-m-d H:i:s")
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/note-details",
     *     summary="Note Details",
     *     tags={"Project Management"},
     *     description="Details of note for Project Management.",
     *     operationId="noteDetails",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Enter Note Id",
     *         example="1",
     *         required=true,
     *         @OA\Schema(
     *             type="number"
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

    public function noteDetails(Request $request)
    {
        try {
            $rules = [
                'id' => 'required|integer|exists:notes,id',
            ];

            $message = [
                'id.required' => 'User ID is required.',
                'id.integer' => 'User ID must be an integer.',
                'id.exists' => 'The specified Note does not exist.',
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
            $data = [
                'status_code' => 200,
                'message' => "Note Successfully get!",
                'data' => [
                    'note' => Notes::where('id', $request->id)->first()
                ]
            ];
            return $this->sendJsonResponse($data);
        } catch (\Exception $e) {
            Log::error([
                'method' => __METHOD__,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ],
                'created_at' => date("Y-m-d H:i:s")
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/edit-note",
     *     summary="Edit Note",
     *     tags={"Project Management"},
     *     description="Edit note for Project Management.",
     *     operationId="editNotes",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Enter Note Id",
     *         example="1",
     *         required=true,
     *         @OA\Schema(
     *             type="number"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="title",
     *         in="query",
     *         description="Enter Note Title",
     *         example="",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="description",
     *         in="query",
     *         description="Enter Note Description",
     *         example="",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
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
    public function editNotes(Request $request)
    {
        try {
            $rules = [
                'id' => 'required|integer|exists:notes,id',
                'title' => 'required|string',
                'description' => 'nullable|string',
            ];
            $message = [
                'id.required' => 'Note ID is required.',
                'id.integer' => 'Note ID must be an integer.',
                'id.exists' => 'The specified Note does not exist.',
                'title.required' => 'Title is required.',
                'description.string' => 'Description must be a string.',
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
            $note = Notes::find($request->id);
            $note->title = $request->title;
            $note->description = $request->description;
            $note->save();
            $data = [
                'status_code' => 200,
                'message' => "Note Successfully Updated!",
                'data' => [
                    'note' => $note
                ]
            ];
            return $this->sendJsonResponse($data);
        } catch (\Exception $e) {
            Log::error([
                'method' => __METHOD__,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ],
                'created_at' => date("Y-m-d H:i:s")
            ]);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/delete-note",
     *     summary="Delete Note",
     *     tags={"Project Management"},
     *     description="Delete note for Project Management.",
     *     operationId="deleteNotes",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Enter Note Id",
     *         example="1",
     *         required=true,
     *         @OA\Schema(
     *             type="number"
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

    public function deleteNote(Request $request)
    {
        try {
            $rules = [
                'id' => 'required|integer|exists:notes,id',
            ];
            $message = [
                'id.required' => 'Note ID is required.',
                'id.integer' => 'Note ID must be an integer.',
                'id.exists' => 'The specified Note does not exist.',
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
            $note = Notes::find($request->id);
            $note->delete();
            $data = [
                'status_code' => 200,
                'message' => "Note Successfully Deleted!",
                'data' => [
                    'note' => $note
                ]
            ];
            return $this->sendJsonResponse($data);
        } catch (\Exception $e) {
            Log::error([
                'method' => __METHOD__,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ],
                'created_at' => date("Y-m-d H:i:s")
            ]);
            return $this->sendJsonResponse([
                'status_code' => 500,
                'message' => 'Internal Server Error',
                'data' => ''
            ]);
        }
    }


    /**
     * @OA\Post(
     *     path="/api/v1/send-work-hours-email",
     *     summary="Send Work Hours email",
     *     tags={"Project Management"},
     *     description="Send Email of work Hours.",
     *     operationId="sendWorkHoursEmail",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="query",
     *         description="Enter Comma Separated UserId",
     *         example="1,2,3",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="month",
     *         in="query",
     *         description="Enter Month Year",
     *         example="2024-06",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="summary",
     *         in="query",
     *         description="Enter Email Summary",
     *         example="Lorem Ipsum is simply dummy text of the printing and typesetting industry.",
     *         required=false,
     *         @OA\Schema(
     *             type="string"
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
    public function sendWorkHoursEmail(Request $request)
    {
        try {
            $rules = [
                'id' => 'required|string',
                'month' => 'required|string',
                'summary' => 'nullable|string',
            ];
            $message = [
                'id.required' => 'Id is required',
                'id.string' => 'Id must be an String',
                'month.required' => 'Month is required',
                'month.string' => 'Month must be an String',
                'summary.string' => 'Summary must be an String'
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
            $recipient = explode(',', $request->id);
            $filterMonth = Carbon::parse($request->month);
            $uniqueName = auth()->user()->account_id;
            $timestamp = Carbon::now()->timestamp;
            $fileName = "work_hours_{$uniqueName}_{$timestamp}_{$filterMonth->year}_{$filterMonth->month}.xlsx";

            $excelContent = Excel::raw(new WorkHoursExport($request), \Maatwebsite\Excel\Excel::XLSX);
            $tempFilePath = tempnam(sys_get_temp_dir(), $fileName);
            file_put_contents($tempFilePath, $excelContent);
            $email = [];
            foreach ($recipient as $single) {
                $user = User::where('id', $single)->first();
                if (!empty($user)) {
                    $email[] = $user;
                }
            }
            if (count($email) == 0) {
                $data = [
                    'status_code' => 400,
                    'message' => "No valid recipients found.",
                    'data' => []
                ];
                return $this->sendJsonResponse($data);
            }
            $month = $request->month;
            $summary = $request->summary;
            foreach ($email as $singleEmail) {
                if (!empty($singleEmail->email)) {
                    Mail::to($singleEmail->email)->send(new WorkHoursMail($tempFilePath, $month, $fileName,$summary));
                }
            }
            unlink($tempFilePath);
            $data = [
                'status_code' => 200,
                'message' => "Work Hours sent Successfully!",
                'data' => []
            ];
            return $this->sendJsonResponse($data);
        } catch (\Exception $e) {
            Log::error([
                'method' => __METHOD__,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'message' => $e->getMessage()
                ],
                'created_at' => date("Y-m-d H:i:s")
            ]);
            return response()->json(['status_code' => 500, 'message' => 'Something went wrong']);
        }
    }
}
