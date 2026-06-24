<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController as BaseController;
use Illuminate\Http\Request;
use App\Models\AuditLogs;
use Illuminate\Support\Carbon;

class AuditLogsController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $result = AuditLogs::where('severity', '>', -1)->where('created_at', '>', Carbon::now()->subHours(3))->get()->load(['user' => function ($query) {
            $query->select('name', 'id', 'role_id');
        }, 'user.role' => function ($query) {
            $query->select('name', 'id');
        }]);

        $create_response_data = []; $modules = [];
        foreach ($result as $key => $auditlog) {
            $module = str_replace("App\\Models\\", "", $auditlog->auditable_type);
            if (!in_array($module, $modules)) {
                $modules[] = $module;
            }

            $temp_log = [
                'id' => $auditlog->id,
                'user' => $auditlog->user ? $auditlog->user->name :'System',
                'role' => $auditlog->user ? $auditlog->user->role->name :'',
                'action' => $auditlog->event,
                'module' => $module,
                'summary' => $auditlog->summary,
                'severity' => $auditlog->severity,
                'data' => [
                    'old_values' => $auditlog->old_values,
                    'new_values' => $auditlog->new_values
                ],
                'timestamp' => $auditlog->created_at
            ];
            array_push($create_response_data, $temp_log);
        }

        $this->saveViewLog('view', 'Logs');
        return $this->sendResponse(['table' => $create_response_data, 'modules' => $modules], "Logs successfully retrieved.");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(AuditLogs $log)
    {
        $module = str_replace("App\\Models\\", "", $log->auditable_type);
        // start
        $carbon_date = new Carbon($log->created_at);
        $startDay = $carbon_date->startOfDay();
        // end
        $carbon_date = new Carbon($log->created_at);
        $endDay = $carbon_date->addDay()->startOfDay();
        
        $logs = AuditLogs::where(function ($query) use ($log, $module) {
                $query->where('auditable_type', $log->auditable_type)
                ->orWhere('auditable_type', $module);
            })->whereBetween('created_at', [$startDay, $endDay])
            ->where('user_id', $log->user_id)
            ->orderBy('id', 'DESC');
        
        // $logs = AuditLogs::whereBetween('created_at', [$startDay, $endDay])
        //     ->where('session_id', $log->session_id)
        //     ->orderBy('id', 'DESC');

        \Log::info($logs->toRawSql());
        
        $this->saveViewLog('view', "Logs\\{$log->id}");
        if (!is_null($log->user))
            $title = "{$module} logs of {$log->user->name} on {$log->created_at}";
        else
            $title = "{$module} logs on {$log->created_at}";

        return $this->sendResponse(['data' => $logs->get(), 'title' => $title], 'Success');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
