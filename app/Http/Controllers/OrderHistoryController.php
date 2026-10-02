<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\BaseController;
use App\Models\Order;
use App\Models\OrderLog;
use Illuminate\Http\Request;

class OrderHistoryController extends BaseController
{
    public function show(Request $request, Order $order)
    {
        $order->load(['lineItems', 'intellicareLog']);

        $order_history = OrderLog::where('auditable_id', $order->id)
            ->where('table', 'orders')
            ->orderBy('id', 'DESC')
            ->get()
            ->each(function ($log) {
                $log->user;
            });

        $order_details_ids = $order->lineItems()->select('id')->get()->pluck('id')->toArray();
        $order_details_history = OrderLog::whereIn('auditable_id', $order_details_ids)
            ->where('table', 'order_details')
            ->orderBy('id', 'DESC')
            ->get()
            ->each(function ($log) {
                $log->user;
            });

        $intellicareLog_ids = $order->intellicareLog()->select('id')->get()->pluck('id')->toArray();
        $order_intLog_history = OrderLog::whereIn('auditable_id', $intellicareLog_ids)
            ->where('table', 'order_intellicare_logs')
            ->orderBy('id', 'DESC')
            ->get()
            ->each(function ($log) {
                $log->user;
            });

        $all_order_history = $order_history->merge($order_details_history)->merge($order_intLog_history)->sortByDesc('id')->values();

        return $this->sendResponse($all_order_history, "Success.");

    }
}
