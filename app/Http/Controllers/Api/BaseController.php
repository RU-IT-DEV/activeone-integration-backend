<?php


namespace App\Http\Controllers\Api;


use Illuminate\Http\Request;
use App\Http\Controllers\Controller as Controller;
use App\Models\AuditLogs;
use Illuminate\Support\Facades\Auth;

class BaseController extends Controller
{
    private $log = [
        'user_id' => null,
        'event' => null,
        'auditable_type' => null,
        'auditable_id' => null,
        'old_values' => null,
        'new_values' => null,
        'severity' => null,
        'summary' => null,
    ];
    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendResponse($result, $message)
    {
    	$response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];

        if (!is_null($this->log['event'])) AuditLogs::create(array_merge($this->log, [
            'summary' => $message,
            'new_values' => $result
        ]));

        return response()->json($response, 200);
    }


    /**
     * return error response.
     *
     * @return \Illuminate\Http\Response
     */
    public function sendError($error, $errorMessages = [], $code = 404)
    {
    	$response = [
            'success' => false,
            'message' => $error,
        ];

        if (!is_null($this->log['event'])) AuditLogs::create(array_merge($this->log, [
            'summary' => $error,
            'new_values' => $errorMessages            
        ]));

        if(!empty($errorMessages)){
            $response['data'] = $errorMessages;
        }


        return response()->json($response, $code);
    }

    public function hasAccess($user, string $resource) {
        $navigations = $user->role->navigations;
        
        $act = explode("-", $resource);
        if (count($act) > 1) {
            $navigation_name = $act[0];
            $ability = $act[1];
            
            $filtered_nav = array_filter(json_decode($navigations, true), function ($navigation) use ($navigation_name) {
                return $navigation['navigation_name'] == $navigation_name;
            });
            
            $filtered_nav_actions = array_values($filtered_nav)[0]['actions'];
            \Log::info($filtered_nav_actions);

            return in_array($ability, $filtered_nav_actions);
        } else {
            return false;
        }
    }

    /**
     * success log method.
     *
     * @param str action
     * @param str model
     * 
     */
    public function successLog($action, $model) {
        $this->log['event'] = $action;
        $this->log['auditable_type'] = $model;
        $this->log['auditable_id'] = 0;
        $this->log['severity'] = 0;
        $this->log['status'] = 'success';
        return $this;
    }

    /**
     * error log method.
     *
     * @param str action
     * @param str model
     * 
     */
    public function errorLog($action, $model) {
        $this->log['event'] = $action;
        $this->log['auditable_type'] = $model;
        $this->log['auditable_id'] = 0;
        $this->log['severity'] = 1;
        $this->log['status'] = 'fail';
        return $this;
    }

    public function saveViewLog($action, $auditable_type) {
        // find last log with same values and do not log if it is less than 30 mins ago
        $last_log = AuditLogs::where('user_id', Auth::user()->id)
            ->where('event', $action)
            ->where('auditable_type', $auditable_type)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($last_log && $last_log->created_at->diffInMinutes(now()) < 30) {
            return;
        }
        
        AuditLogs::create([
            'user_id' => Auth::user()->id,
            'event' => $action,
            'auditable_type' => $auditable_type,
            'auditable_id' => 0,
            'severity' => -1,
            'summary' => Auth::user()->name . " viewed {$auditable_type}."
        ]);
    }

    public function createLog ($details) {
        AuditLogs::create($details);
    }
}