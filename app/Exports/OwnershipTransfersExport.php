<?php

namespace App\Exports;

use App\Models\PropertyOwnershipTransfer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class OwnershipTransfersExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $transfers;
    protected $isTrashExport;

    public function __construct($transfers = null, $isTrashExport = false)
    {
        // If transfers are provided directly (for filtered exports)
        if ($transfers instanceof Collection) {
            $this->transfers = $transfers;
        } 
        // Otherwise fetch all transfers
        else {
            $this->transfers = PropertyOwnershipTransfer::with([
                'property', 
                'currentLandlord', 
                'newLandlord',
                'requestedBy',
                'approvedBy'
            ])->get();
        }
        
        $this->isTrashExport = $isTrashExport;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->transfers;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        if ($this->isTrashExport) {
            return [
                'Transfer ID',
                'Document Reference',
                'Property Name',
                'Property Registration',
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

        return [
            'Transfer ID',
            'Document Reference',
            'Property Name',
            'Property Registration',
            'Current Owner',
            'Current Owner Email',
            'Current Owner Phone',
            'New Owner',
            'New Owner Email',
            'New Owner Phone',
            'Status',
            'Transfer Date',
            'Sale Amount',
            'Document Type',
            'Requested By',
            'Requested Date',
            'Approved By',
            'Approved Date',
            'Completed By',
            'Completed Date',
            'Processing Time (Days)',
            'Reason for Transfer',
            'Admin Notes',
            'Rejection Reason',
            'Is Bulk Transfer',
            'Has Digital Signature',
            'Signature Verified'
        ];
    }

    /**
     * @param mixed $transfer
     * @return array
     */
    public function map($transfer): array
    {
        if ($this->isTrashExport) {
            return $this->mapTrashTransfer($transfer);
        }

        return $this->mapRegularTransfer($transfer);
    }

    /**
     * Map regular transfer data
     */
    protected function mapRegularTransfer($transfer): array
    {
        // Calculate processing time in days
        $processingDays = null;
        if ($transfer->created_at && $transfer->completed_at) {
            $processingDays = $transfer->created_at->diffInDays($transfer->completed_at);
        }

        return [
            $transfer->id,
            $transfer->document_reference,
            $transfer->property->property_name ?? 'N/A',
            $transfer->property->registration_pattern ?? 'N/A',
            $transfer->currentLandlord->name ?? 'N/A',
            $transfer->currentLandlord->email ?? 'N/A',
            $transfer->currentLandlord->phone ?? 'N/A',
            $transfer->newLandlord->name ?? $transfer->new_owner_name,
            $transfer->newLandlord->email ?? $transfer->new_owner_email,
            $transfer->newLandlord->phone ?? $transfer->new_owner_phone,
            $transfer->status_label,
            $transfer->transfer_date ? $transfer->transfer_date->format('Y-m-d') : 'N/A',
            $transfer->sale_amount ? 'GHS ' . number_format($transfer->sale_amount, 2) : 'N/A',
            $transfer->document_type_label,
            $transfer->requestedBy->name ?? 'N/A',
            $transfer->created_at ? $transfer->created_at->format('Y-m-d H:i:s') : 'N/A',
            $transfer->approvedBy->name ?? 'N/A',
            $transfer->approved_at ? $transfer->approved_at->format('Y-m-d H:i:s') : 'N/A',
            $transfer->completedBy->name ?? 'N/A',
            $transfer->completed_at ? $transfer->completed_at->format('Y-m-d H:i:s') : 'N/A',
            $processingDays ?? 'N/A',
            $transfer->reason_for_transfer ?? 'N/A',
            $transfer->admin_notes ?? 'N/A',
            $transfer->rejection_reason ?? 'N/A',
            $transfer->is_bulk_transfer ? 'Yes' : 'No',
            isset($transfer->metadata['digital_signature']) ? 'Yes' : 'No',
            ($transfer->metadata['digital_signature_verified'] ?? false) ? 'Yes' : 'No'
        ];
    }

    /**
     * Map trashed transfer data
     */
    protected function mapTrashTransfer($transfer): array
    {
        $softDeleted = $transfer->metadata['soft_deleted'] ?? [];
        
        return [
            $transfer->id,
            $transfer->document_reference,
            $transfer->property->property_name ?? 'N/A',
            $transfer->property->registration_pattern ?? 'N/A',
            $transfer->currentLandlord->name ?? 'N/A',
            $transfer->newLandlord->name ?? $transfer->new_owner_name,
            $softDeleted['status_at_deletion'] ?? $transfer->status_label,
            $transfer->transfer_date ? $transfer->transfer_date->format('Y-m-d') : 'N/A',
            $transfer->sale_amount ? 'GHS ' . number_format($transfer->sale_amount, 2) : 'N/A',
            $softDeleted['deleted_by_name'] ?? 'Unknown',
            $transfer->deleted_at ? $transfer->deleted_at->format('Y-m-d H:i:s') : 'N/A',
            $transfer->days_in_trash ?? 'N/A',
            $softDeleted['deleted_reason'] ?? 'N/A',
            $transfer->document_type_label,
            $transfer->reason_for_transfer ?? 'N/A',
            $transfer->admin_notes ?? 'N/A',
            $transfer->rejection_reason ?? 'N/A'
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text
            1 => ['font' => ['bold' => true, 'size' => 12]],
            
            // Style header background
            'A1:AA1' => [
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E2E8F0']
                ]
            ]
        ];
    }
}