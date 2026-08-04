<?php

namespace App\Helper;

use Carbon\Carbon;
use Google\Cloud\Tasks\V2\OidcToken;
use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\Task;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Protobuf\Timestamp;
use ReflectionClass;

class CloudTasksHelper
{
    public static function dispatchJob(object $job, ?Carbon $runAt = null): void
    {
        $client = new CloudTasksClient();

        $projectId = config('services.cloud_task.project_id');
        $location  = 'asia-southeast1';
        $queue     = config('services.cloud_task.shopify.queue');
        $endpoint  = config('services.cloud_task.tasks_endpoint');

        $parent = $client->queueName($projectId, $location, $queue);

        // ✅ Extract constructor args
        $reflection = new ReflectionClass($job);
        $payload = [];

        if ($constructor = $reflection->getConstructor()) {
            foreach ($constructor->getParameters() as $param) {
                $payload[$param->getName()] = $job->{$param->getName()};
            }
        }

        // ✅ Build HttpRequest
        $httpRequest = new HttpRequest([
            'http_method' => HttpMethod::POST,
            'url' => $endpoint,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'oidc_token' => new OidcToken([
                'service_account_email' => config('services.cloud_task.cloud_tasks_sa'),
            ]),
            'body' => base64_encode(json_encode([
                'job_class' => get_class($job),
                'payload'   => $payload,
            ])),
        ]);

        // ✅ Build Task data correctly
        $taskData = [
            'http_request' => $httpRequest,
        ];

        if ($runAt) {
            $taskData['schedule_time'] = new Timestamp([
                'seconds' => $runAt->getTimestamp(),
            ]);
        }

        // ✅ Create ONE task
        $task = new Task($taskData);
        $client->createTask($parent, $task);
    }
}