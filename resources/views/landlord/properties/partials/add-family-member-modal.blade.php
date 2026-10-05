{{-- resources/views/landlord/properties/partials/add-family-member-modal.blade.php --}}

<div class="modal fade" id="addFamilyMemberModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('landlord.family-links.store') }}"
              method="POST"
              class="modal-content"
              id="familyLinkForm">
            @csrf

            <input type="hidden" name="property_id" value="{{ $property->id }}">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>
                    Link a Family Member to {{ $property->property_name }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                {{-- ── Two-stage workflow explainer ── --}}
                <div class="alert alert-info small mb-3">
                    <div class="fw-semibold mb-1">
                        <i class="fas fa-info-circle me-1"></i>
                        How linking works
                    </div>
                    <ol class="mb-0 ps-3">
                        <li>You submit a proposal below.</li>
                        <li>You confirm the proposal yourself (safety step).</li>
                        <li>An administrator reviews and approves it.</li>
                        <li>
                            <strong>Only after admin approval</strong>
                            will your family member receive an email/SMS invitation to set their password.
                        </li>
                    </ol>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text"
                               name="proposed_name"
                               class="form-control"
                               required
                               maxlength="255"
                               value="{{ old('proposed_name') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Relationship <span class="text-danger">*</span>
                        </label>
                        <select name="relationship"
                                class="form-select"
                                required
                                id="relationshipSelect">
                            <option value="">Select…</option>
                            @foreach(config('property_family_links.relationship_options', []) as $rel)
                                <option value="{{ $rel }}" {{ old('relationship') === $rel ? 'selected' : '' }}>
                                    {{ ucfirst($rel) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12 d-none" id="relationshipOtherWrap">
                        <label class="form-label">Specify Relationship</label>
                        <input type="text"
                               name="relationship_other"
                               class="form-control"
                               maxlength="100"
                               value="{{ old('relationship_other') }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text"
                               name="proposed_phone"
                               class="form-control"
                               maxlength="20"
                               value="{{ old('proposed_phone') }}">
                        <small class="text-muted">Provide phone or email.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email"
                               name="proposed_email"
                               class="form-control"
                               maxlength="255"
                               value="{{ old('proposed_email') }}">
                    </div>

                    <div class="col-12">
                        <label class="form-label d-block">Permissions</label>
                        <div class="row g-2">
                            @foreach(config('property_family_links.permissions', []) as $key => $label)
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               name="permissions[]"
                                               value="{{ $key }}"
                                               class="form-check-input"
                                               id="perm-{{ $key }}"
                                               {{ in_array($key, old('permissions', config('property_family_links.default_permissions', []))) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="perm-{{ $key }}">
                                            {{ $label }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notes for Administrator</label>
                        <textarea name="landlord_notes"
                                  class="form-control"
                                  rows="3"
                                  maxlength="1000"
                                  placeholder="Any context the admin should know when reviewing...">{{ old('landlord_notes') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="submitFamilyLinkBtn">
                    <i class="fas fa-paper-plane me-1"></i> Submit Proposal
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    'use strict';

    const sel = document.getElementById('relationshipSelect');
    const wrap = document.getElementById('relationshipOtherWrap');

    if (sel && wrap) {
        // Restore visibility on load if "other" was previously selected
        if (sel.value === 'other') wrap.classList.remove('d-none');

        sel.addEventListener('change', function () {
            wrap.classList.toggle('d-none', this.value !== 'other');
        });
    }

    // ── AJAX submit + in-modal feedback ──
    const form = document.getElementById('familyLinkForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            // If Bootstrap's modal JS is missing, allow a native submit
            if (typeof bootstrap === 'undefined') return;

            e.preventDefault();

            const submitBtn = document.getElementById('submitFamilyLinkBtn');
            const originalHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
            }

            const formData = new FormData(form);

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(r => r.json().then(data => ({ status: r.status, data })))
            .then(({ status, data }) => {
                if (data.success) {
                    // Close modal, refresh so the panel updates
                    try {
                        const modalEl = document.getElementById('addFamilyMemberModal');
                        const modal   = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    } catch (_) {}
                    window.location.reload();
                } else {
                    // Show validation errors or generic message
                    let msg = data.message || 'Failed to submit proposal.';
                    if (data.errors) {
                        const first = Object.values(data.errors)[0];
                        if (Array.isArray(first) && first[0]) msg = first[0];
                    }
                    alert(msg); // simple fallback
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                    }
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
            });
        });
    }
})();
</script>