<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\User;

use Illuminate\Support\Facades\Storage;
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
        Log::debug("Entering " . __METHOD__);
        $data = $request->all();

        $rules = [
            'mediakey' => ['required', 'unique:downloads', 'alpha_num', 'min:32', 'max:32'],
            'source.url' => 'required|url',
            'source.created_at' => 'required',
            'target.start' => 'integer',
            'target.duration' => 'integer',
            'target.hls' => 'boolean',
            'target.format.*.label' => 'required',
            'target.format.*.size' => ['required', 'regex:/^(\d+)x(\d+)/'],
            'target.format.*.vbr' => 'required|integer',
            'target.format.*.abr' => 'required|integer',
            'target.format.*.extension' => ['required', Rule::in(['mp4', 'm4v'])],
            'target.format.*.default' => 'boolean'
        ];

        $url = User::where('id', '=', Auth::guard('api')->user()->id)->pluck('url')->first() . $data['source']['url'];
        $data['source']['url'] = $url;

        $request->merge($data);
        $validator = Validator::make($data, $rules);

        if (!$validator->fails()) {
            $request->offsetUnset('api_token');
            $download = Download::create([
                'user_id' => Auth::guard('api')->user()->id,
                'mediakey' => $request->json()->get('mediakey'),
                'processed' => Download::UNPROCESSED,
                'payload' => $request->json()->all()
            ]);

            $downloadJobId = DownloadFileJob::dispatch($download)->onQueue('download');
            Log::debug("Exiting " . __METHOD__);
            return response()->json([
                'message' => 'File is queued for download',
                'status' => 'success'
            ])->setStatusCode(200);
        }

        Log::debug("Exiting " . __METHOD__);
        return response()->json([
            'message' => $validator->errors()->all(),
            'status' => 'failed'
        ])->setStatusCode(400);
    }

    public static function deleteById($id)
    {
        Log::debug("Entering " . __METHOD__);
        $filenames = DB::table('downloads')->select('payload')->whereIn('id', explode(',', $id))->pluck('payload')->toArray();

        $files_to_delete = array();
        foreach ($filenames as $filename) {
            $filename = json_decode($filename);
            $files_to_delete[] = $filename->mediakey;
        }

        Storage::disk('uploaded')->delete($files_to_delete);
        Log::debug("Exiting " . __METHOD__);
    }

    public function queue()
    {
        Log::debug("Entering " . __METHOD__);
        $payload = DB::table('jobs')->where('queue', '=', 'download')->value('payload');

        if (isset($payload->data->command)) {
            Log::debug("Exiting " . __METHOD__);
            return response()->json($payload->data->command);
        }
        Log::debug("Exiting " . __METHOD__);
        return response()->json(array('message' => 'not found'), 404);
    }
}
