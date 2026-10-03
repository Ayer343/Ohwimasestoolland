{{-- resources/views/developer/api/modals/log-details.blade.php --}}
<div id="logDetailsModal" class="modal hidden">
    <div class="modal-overlay"></div>
    <div class="modal-container max-w-4xl">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-eye mr-2" style="color: var(--info);"></i>
                    API Log Details
                </h3>
                <button type="button" class="modal-close" onclick="hideLogDetailsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body">
                <div id="logDetailsContent" class="space-y-4">
                    <!-- Content will be loaded via JavaScript -->
                    <div class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-2xl mb-4" style="color: var(--info);"></i>
                        <p style="color: var(--text-secondary);">Loading log details...</p>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="hideLogDetailsModal()">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
                <button type="button" class="btn btn-primary-outline" onclick="copyLogDetails()">
                    <i class="fas fa-copy mr-2"></i> Copy Details
                </button>
            </div>
        </div>
    </div>
</div>