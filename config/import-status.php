<?php
function import_status_steps(): array
{
    return [
        'Vehicle Found',
        'Under Assessment',
        'Approved to Bid',
        'Auction Won',
        'Import Approval Required',
        'Import Approval Submitted',
        'Import Approved',
        'Shipping Booked',
        'In Transit',
        'Arrived at Port',
        'Customs Clearance',
        'Biosecurity',
        'Transport to Workshop',
        'Compliance',
        'Roadworthy / Registration',
        'Ready for Sale',
        'Sold',
        'Closed / Cancelled',
    ];
}

function import_legacy_status_map(): array
{
    return [
        'Draft' => 'Under Assessment',
        'Won' => 'Auction Won',
        'Shipped' => 'Shipping Booked',
        'Arrived' => 'Arrived at Port',
        'Closed' => 'Closed / Cancelled',
    ];
}

function normalise_import_status(?string $status): string
{
    $status = trim((string) $status);
    if ($status === '') {
        return 'Under Assessment';
    }

    return import_legacy_status_map()[$status] ?? $status;
}

function import_status_options_for_schema(bool $includeLegacy = false): array
{
    $statuses = import_status_steps();
    if ($includeLegacy) {
        $statuses = array_merge($statuses, array_keys(import_legacy_status_map()));
    }

    return array_values(array_unique($statuses));
}

function import_status_class(?string $status): string
{
    return 'status-' . trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(normalise_import_status($status))), '-');
}
?>
