{{-- ============ ADMINISTRATIVE TOOLS MODAL ============ --}}
@if($isAuthorized ?? false)
<div id="administrativeToolsModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-start justify-center z-50 hidden overflow-y-auto" style="padding-top: 2rem; padding-bottom: 2rem;">
    <div class="admin-tools-modal relative mx-auto my-auto" 
         style="background-color: var(--card-bg); 
                border: 1px solid var(--border-color);
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                animation: modalSlideUp 0.3s ease-out;
                width: 90%;
                max-width: 1200px;
                max-height: calc(100vh - 4rem);
                display: flex;
                flex-direction: column;
                border-radius: 16px;
                overflow: hidden;">
        
        <div class="modal-header flex justify-between items-center p-6 border-b flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--card-bg);">
            <h3 class="text-xl font-semibold" style="color: var(--text-primary);">
                <i class="fas fa-tools mr-3" style="color: var(--primary);"></i>
                Administrative Tools
            </h3>
            <button id="closeAdminToolsModal" class="p-2 rounded-full transition-colors duration-200"
                    style="color: var(--text-secondary);">
                <i class="fas fa-times text-lg"></i>
            </button>
        </div>
        
        <div class="modal-body p-6 overflow-y-auto" style="max-height: calc(100vh - 12rem); scroll-behavior: smooth;">
            <p class="text-sm mb-6" style="color: var(--text-secondary);">
                Select an administrative module to manage system functions.
            </p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <a href="{{ route('admin.security-posts.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-map-marker-alt" style="color: var(--primary);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Security Posts</h4>
                        <p class="admin-tools-description">Manage guard stations and checkpoints</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="{{ route('admin.security-shifts.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-clock" style="color: var(--success);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Security Shifts</h4>
                        <p class="admin-tools-description">Define and manage shift patterns</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="{{ route('admin.security-schedules.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-calendar-alt" style="color: var(--info);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Security Schedules</h4>
                        <p class="admin-tools-description">Schedule personnel to posts</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="{{ route('admin.supervisor-assignments.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-user-shield" style="color: var(--warning);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Supervisor Assignments</h4>
                        <p class="admin-tools-description">Assign supervisors to posts</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
                
                <a href="{{ route('admin.ownership-transfers.index') }}" class="admin-tools-card group"
                   onclick="closeAdminToolsModal()">
                    <div class="admin-tools-icon-container"><i class="fas fa-exchange-alt" style="color: var(--danger);"></i></div>
                    <div class="admin-tools-content">
                        <h4 class="admin-tools-title">Ownership Transfers</h4>
                        <p class="admin-tools-description">Manage property transfers</p>
                    </div>
                    <div class="admin-tools-arrow"><i class="fas fa-chevron-right"></i></div>
                </a>
            </div>
        </div>
        
        <div class="modal-footer p-6 border-t flex justify-end flex-shrink-0"
             style="border-color: var(--border-color); background-color: var(--bg-secondary);">
            <button id="cancelAdminToolsModal" class="px-4 py-2 text-sm font-medium"
                    style="color: var(--text-secondary);">Close</button>
        </div>
    </div>
</div>
@endif