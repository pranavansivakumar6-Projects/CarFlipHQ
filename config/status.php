<?php
function car_status_steps(): array
{
    return ['Bought','Waiting for Parts','Under Repair','RWC Pending','Ready for Sale','Listed','Sold'];
}

function infer_car_status(array $car, array $parts, array $tasks, array $listings): string
{
    if (($car['status'] ?? '') === 'Sold' || (float) ($car['actual_sale_price'] ?? 0) > 0 || !empty($car['sold_date'])) {
        return 'Sold';
    }

    foreach ($listings as $listing) {
        if (in_array($listing['status'] ?? '', ['Listed','Offer Received','Deposit Taken'], true)) {
            return 'Listed';
        }
        if (($listing['status'] ?? '') === 'Sold') {
            return 'Sold';
        }
    }

    $waitingParts = false;
    $arrivedParts = false;
    foreach ($parts as $part) {
        $partStatus = $part['status'] ?? '';
        if (in_array($partStatus, ['Needed','Ordered'], true)) {
            $waitingParts = true;
        }
        if ($partStatus === 'Arrived') {
            $arrivedParts = true;
        }
    }

    $openRepairTasks = 0;
    $hasRwcTask = false;
    $hasRwcDone = false;
    foreach ($tasks as $task) {
        $title = strtolower(($task['task_title'] ?? '') . ' ' . ($task['description'] ?? ''));
        $isRwc = str_contains($title, 'rwc') || str_contains($title, 'roadworthy') || str_contains($title, 'road worthy');
        $taskDone = ($task['status'] ?? '') === 'Done';

        if ($isRwc) {
            $hasRwcTask = true;
            if ($taskDone) {
                $hasRwcDone = true;
            }
        }

        if (!$taskDone && !$isRwc) {
            $openRepairTasks++;
        }
    }

    if ($hasRwcDone) {
        return 'Ready for Sale';
    }

    if ($waitingParts) {
        return 'Waiting for Parts';
    }

    if ($openRepairTasks > 0 || ($arrivedParts && count($tasks) === 0)) {
        return 'Under Repair';
    }

    if ($hasRwcTask && !$hasRwcDone) {
        return 'RWC Pending';
    }

    if (count($tasks) > 0 || count($parts) > 0) {
        return 'Ready for Sale';
    }

    return 'Bought';
}
?>
