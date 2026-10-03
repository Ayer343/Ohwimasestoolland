<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Support\Collection;

class TrashedTransfersExport implements FromCollection, WithHeadings, WithMapping
{
    protected $data;

    public function __construct(Collection $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        return $this->data;
    }

    public function headings(): array
    {
        return [
            'Transfer ID',
            'Document Reference',
            'Property Name',
            'Current Owner',
            'New Owner',
            'Status at Deletion',
            'Transfer Date',
            'Sale Amount',
            'Deleted By',
            'Deleted At',
            'Days in Trash',
            'Deletion Reason',
            'Document Type',
            'Reason for Transfer',
            'Admin Notes',
            'Rejection Reason'
        ];
    }

    public function map($row): array
    {
        return [
            $row['Transfer ID'] ?? 'N/A',
            $row['Document Reference'] ?? 'N/A',
            $row['Property Name'] ?? 'N/A',
            $row['Current Owner'] ?? 'N/A',
            $row['New Owner'] ?? 'N/A',
            $row['Status at Deletion'] ?? 'N/A',
            $row['Transfer Date'] ?? 'N/A',
            $row['Sale Amount'] ?? 'N/A',
            $row['Deleted By'] ?? 'N/A',
            $row['Deleted At'] ?? 'N/A',
            $row['Days in Trash'] ?? 'N/A',
            $row['Deletion Reason'] ?? 'N/A',
            $row['Document Type'] ?? 'N/A',
            $row['Reason for Transfer'] ?? 'N/A',
            $row['Admin Notes'] ?? 'N/A',
            $row['Rejection Reason'] ?? 'N/A'
        ];
    }
}