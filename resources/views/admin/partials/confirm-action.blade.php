{{--
    Generic destructive-action confirmation, with an optional mandatory reason.

    Include once per page, then trigger from any button:

      confirmDestructive('{{ route(...) }}', 'DELETE', {
          title: 'Delete this user?',
          message: 'They lose access immediately. This cannot be undone.',
          submitLabel: 'Delete user',
          tone: 'danger',            // danger | warning | primary
          reason: 'required',        // 'required' | 'optional' | 'none'
          reasonLabel: 'Why are you deleting this account?',
      })

    The reason is posted as `reason`, which the audit middleware already records
    with the actor, IP and route — so any form that gains this field becomes
    accountable without extra controller work.

    Named confirmDestructive rather than confirmAction on purpose: several pages
    already define their own window.confirmAction for simple submit-this-form
    prompts, and silently overriding it would break them.
--}}
<div class="modal fade" id="confirmActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="confirmActionForm" class="modal-content">
            @csrf
            <input type="hidden" name="_method" id="confirmActionMethod" value="POST">
            <div id="confirmActionExtras"></div>

            <div class="modal-header border-white-05">
                <h5 class="modal-title" id="confirmActionTitle">Confirm action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3" id="confirmActionMessage"></p>

                <div id="confirmActionReasonGroup" class="d-none">
                    <label class="form-label small fw-bold text-muted" for="confirmActionReason">
                        <span id="confirmActionReasonLabel">Reason</span>
                        <span class="text-danger" id="confirmActionReasonRequired">*</span>
                    </label>
                    <textarea name="reason" id="confirmActionReason" rows="3" maxlength="500"
                              class="form-control bg-dark-soft border-0 rounded-3"
                              placeholder="Recorded in the activity log with your name, IP and the time."></textarea>
                </div>
            </div>

            <div class="modal-footer border-white-05">
                <button type="button" class="btn btn-outline-dynamic rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger rounded-3" id="confirmActionSubmit">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('confirmActionModal');
    const modal = new bootstrap.Modal(modalElement);
    const form = document.getElementById('confirmActionForm');
    const methodInput = document.getElementById('confirmActionMethod');
    const extras = document.getElementById('confirmActionExtras');
    const title = document.getElementById('confirmActionTitle');
    const message = document.getElementById('confirmActionMessage');
    const submit = document.getElementById('confirmActionSubmit');
    const reasonGroup = document.getElementById('confirmActionReasonGroup');
    const reasonInput = document.getElementById('confirmActionReason');
    const reasonLabel = document.getElementById('confirmActionReasonLabel');
    const reasonRequiredMark = document.getElementById('confirmActionReasonRequired');

    window.confirmDestructive = function (url, method, options) {
        options = options || {};

        form.action = url;
        // Laravel reads _method for anything other than GET/POST.
        methodInput.value = (method || 'POST').toUpperCase();

        title.textContent = options.title || 'Confirm action';
        message.textContent = options.message || 'This cannot be undone.';
        submit.textContent = options.submitLabel || 'Confirm';
        submit.className = 'btn rounded-3 btn-' + (options.tone || 'danger');

        const reasonMode = options.reason || 'none';
        reasonGroup.classList.toggle('d-none', reasonMode === 'none');
        reasonInput.required = reasonMode === 'required';
        reasonInput.disabled = reasonMode === 'none';
        reasonInput.value = '';
        reasonLabel.textContent = options.reasonLabel || 'Reason';
        reasonRequiredMark.classList.toggle('d-none', reasonMode !== 'required');

        // Any additional hidden fields the caller needs sent along.
        extras.innerHTML = '';
        Object.entries(options.fields || {}).forEach(function ([name, value]) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            extras.appendChild(input);
        });

        modal.show();
    };

});
</script>
