<?php

namespace App\Services;

use App\Models\OrderLog;

class IntellicareLogService
{
    public function createTransactionCall($auditable_id, $log)
    {
        return OrderLog::create([
            'table' => 'order_intellicare_logs',
            'auditable_id' => $auditable_id,
            'action' => 'create',
            'status' => 1,
            'summary' => 'Intellicare create transaction has been called.',
            'value' => $log
        ]);
    }
    
    public function createTransactionError($auditable_id, $message)
    {
        return OrderLog::create([
            'table' => 'order_intellicare_logs',
            'auditable_id' => $auditable_id,
            'action' => 'create',
            'status' => -1,
            'summary' => $message,
            'value' => []
        ]);
    }
}

class IntellicareUploadPrescriptionService
{
    public function uploadPrescriptionCall($auditable_id, $log)
    {
        return OrderLog::create([
            'table' => 'order_prescriptions',
            'auditable_id' => $auditable_id,
            'action' => 'create',
            'status' => 1,
            'summary' => 'Intellicare upload prescription has been called.',
            'value' => $log
        ]);
    }
    
    public function uploadPrescriptionError($auditable_id, $message)
    {
        return OrderLog::create([
            'table' => 'order_prescriptions',
            'auditable_id' => $auditable_id,
            'action' => 'create',
            'status' => -1,
            'summary' => $message,
            'value' => []
        ]);
    }
}

class ShopifyOrderService
{
    public function createShopifyCall($auditable_id, $log)
    {
        return OrderLog::create([
            'table' => 'orders',
            'auditable_id' => $auditable_id,
            'action' => 'create',
            'status' => 1,
            'summary' => 'Shopify create order has been called.',
            'value' => $log
        ]);
    }
    
    public function createShopifyError($auditable_id, $message)
    {
        return OrderLog::create([
            'table' => 'orders',
            'auditable_id' => $auditable_id,
            'action' => 'create',
            'status' => -1,
            'summary' => $message,
            'value' => []
        ]);
    }
}

class OrderDetailService
{
    public function store($auditable_id, $log)
    {
        return OrderLog::create([
            'table' => 'order_details',
            'auditable_id' => $auditable_id,
            'action' => 'create',
            'status' => 1,
            'summary' => 'An item has been added to an order',
            'value' => $log
        ]);
    }

    public function update($auditable_id, $log)
    {
        return OrderLog::create([
            'table' => 'order_details',
            'auditable_id' => $auditable_id,
            'action' => 'update',
            'status' => 1,
            'summary' => 'An order item has been updated',
            'value' => $log
        ]);
    }

    public function delete($auditable_id, $log)
    {
        return OrderLog::create([
            'table' => 'order_details',
            'auditable_id' => $auditable_id,
            'action' => 'delete',
            'status' => 1,
            'summary' => 'An order item has been deleted',
            'value' => $log
        ]);
    }
}

class OrderLogService
{
    public $intellicare;
    public $orderDetails;
    public $prescription;
    public $shopify;

    public $arr_reject_reason_email_msg = [
        'DUPLICATE ORDER' => "This ordered was cancelled due to duplication.",
        'INCOMPLETE RX DETAILS' => "This order was cancelled due to incomplete prescirptipn details. Please review uploaded prescription, and reoder.",
        'MISMATCH: PRODUCT' => "This ordered was cancelled due to wrong generic name / brand name placed. Please double check order placed vs prescription, and reoder.",
        'MISMATCH: DOSAGE' => "This ordered was cancelled due to mismatch of dosage. Please double check order placed vs prescription, and reoder.",
        'MISMATCH: QUANTITY' => "This ordered was cancelled due to mismatch of quantity. Please double check order placed vs prescription, and reoder.",
        'NO/WRONG RX ATTACHED' => "This order was cancelled due to wrong attached prescription. Please review uploaded prescription, and reoder.",
        'RX > 30 DAYS DATE' => "This order exceeds the 30 day validity of prescription. Please secure a new prescription from your doctor.",
        'RX UTILIZED' => "This order was cancelled since prescription has been used to buy from us and full qty dispensed",
        'RX EXPIRED > 1 YEAR DATE' => "We cannot proceed with the order because prescription exceeds 1 year validity. Please secure a new prescription from your doctor.",
    ];

    public function __construct()
    {
        $this->intellicare = new IntellicareLogService();
        $this->orderDetails = new OrderDetailService();
        $this->prescription = new IntellicareUploadPrescriptionService();
        $this->shopify = new ShopifyOrderService();
    }

    /**
     * Create Order Create Log
     * 
     * @param int $auditable_id
     * @param array $log
     */
    public function store($auditable_id, $log)
    {
        OrderLog::create([
            'table' => 'orders',
            'auditable_id' => $auditable_id,
            'action' => 'create',
            'status' => 1,
            'summary' => 'An order has been created',
            'value' => $log
        ]);

        return $this;
    }

    public function update($auditable_id, $log)
    {
        OrderLog::create([
            'table' => 'orders',
            'auditable_id' => $auditable_id,
            'action' => 'update',
            'status' => 1,
            'summary' => 'An order has been updated',
            'value' => $log
        ]);

        return $this;
    }
    public function approve($auditable_id, $log)
    {
        OrderLog::create([
            'table' => 'orders',
            'auditable_id' => $auditable_id,
            'action' => 'approve',
            'status' => 1,
            'summary' => 'Order status has been updated to APPROVED.',
            'value' => $log
        ]);

        return $this;
    }

    public function reject($auditable_id, $log)
    {
        OrderLog::create([
            'table' => 'orders',
            'auditable_id' => $auditable_id,
            'action' => 'reject',
            'status' => 1,
            'summary' => 'Order status has been updated to REJECTED.',
            'value' => $log
        ]);

        return $this;
    }
}

