<?php

namespace App\Http\Controllers;

use App\Models\Download;
use Illuminate\Support\Facades\Validator;

use App\Jobs\DownloadFileJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class DownloadController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->all();

        $rules = [
            //  'api_token'            => 'required|alpha_num|min:32|max:32',
            'source.url'        => 'required|url',
            'source.mediakey'   => ['required','alpha_num', 'min:32', 'max:32'],
            'source.created_at' => 'required',
            'target.*.label'    => 'required',
            'target.*.size'     => ['required', 'regex:/^(\d+)x(\d+)/'],
            'target.*.vbr'      => 'required|integer',
            'target.*.abr'      => 'required|integer',
            'target.*.format'   => ['required', Rule::in(['mp4','m4v'])]

        ];

        $validator = Validator::make($data, $rules);

        if (!$validator->fails())
        {
            $request->offsetUnset('api_token');
            $download = Download::create([
                'uid'       => Auth::guard('api')->user()->id,
                'payload'   => $request->json()->all()
            ]);

            DownloadFileJob::dispatch($download)->onQueue('download');

            return response()->json([
                'message' => 'File is queued for download',
                'status'  => 'success'
            ])->setStatusCode(200);
        }

        return response()->json([
            'message' => $validator->errors()->all(),
            'status'  => 'failed'
        ])->setStatusCode(400);
    }

    public function queue()
    {
        $payload = DB::table('jobs')->where('queue','=','download')->value('payload');

        if(isset($payload->data->command))
        {
            return response()->json($payload->data->command);
        }

        return response()->json(array('message' => 'not found'), 404);
    }
}
