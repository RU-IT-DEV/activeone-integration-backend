<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcessJobsController extends BaseController
{
    public function queue_work(Request $request)
    {
        // ✅ Ensure request is from Cloud Tasks
        abort_unless(
            $request->hasHeader('X-CloudTasks-TaskName'),
            403,
            'Forbidden'
        );

        DB::reconnect();

        $raw = $request->getContent();

        $data = json_decode(base64_decode($raw), true);

        if (!is_array($data)) {
            abort(400, 'Invalid Cloud Tasks payload');
        }

        $jobClass = $data['job_class'] ?? null;
        $payload  = $data['payload'] ?? null;
        logger()->info("ProcessJobsController: ", [$payload]);
        /*
         |--------------------------------------------------------------------------
         | NEW CLOUD TASKS PATH
         |--------------------------------------------------------------------------
         | Executes a single job sent via Cloud Tasks payload.
         */
        if (!is_null($jobClass)) {

            if (!class_exists($jobClass)) {
                abort(400, 'Invalid job class');
            }

            if (isset($payload['shopify_order_name'])) {
                $payload = Order::with(['lineItems', 'shippingAddress', 'billingAddress', 'intellicareLog'])
                    ->findOrFail($payload['id']);
            }

            $job = new $jobClass($payload);

            dispatch_sync($job);

            return response()->json([
                'status' => 'ok',
                'processed' => 1,
            ]);
        }

        /*
         |--------------------------------------------------------------------------
         | LEGACY LARAVEL QUEUE DRAIN PATH (TEMPORARY)
         |--------------------------------------------------------------------------
         | Drains old jobs from the `jobs` table only during migration.
         */
        try {
            $processed = 0;

            DB::transaction(function () use (&$processed) {

                $jobs = DB::table('jobs')
                    ->whereNull('reserved_at')
                    ->limit(50)
                    ->lockForUpdate()
                    ->get();

                foreach ($jobs as $jobRow) {

                    $payload = json_decode($jobRow->payload, true);

                    // Legacy DB queue payloads only
                    if (!isset($payload['data']['command'])) {
                        continue;
                    }

                    $command = unserialize($payload['data']['command']);

                    dispatch_sync($command);

                    DB::table('jobs')->where('id', $jobRow->id)->delete();
                    $processed++;
                }
            });

            return response()->json([
                'status' => 'ok',
                'processed' => $processed,
            ]);

        } catch (\Throwable $e) {
            logger()->error('ProcessJobsController failed', [
                'exception' => $e,
            ]);

            return $this->errorLog('queue:work', 'Job')
                ->sendError($e->getMessage(), [], 400);
        }
    }
}