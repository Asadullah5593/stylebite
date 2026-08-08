{{--
    Shared ban / suspend / activate modal. Single instance per page:
      lifecycleAction(url, action, label)  — one user (PATCH url)
      lifecycleBulkAction(action, ids)     — many users (PATCH bulk route)
    Presets post duration_hours (server computes the end time); the custom
    picker posts an explicit suspended_until instead.
--}}
<div class="modal fade" id="lifecycleActionModal" tabindex="-1" aria-hidden="true" data-bulk-url="{{ route('admin.users.bulk_lifecycle') }}">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="lifecycleActionForm" class="modal-content">
            @csrf
            @method('PATCH')
            <input type="hidden" name="action" id="lifecycleActionInput">
            <div id="lifecycleBulkIdsContainer" class="d-none"></div>

            <div class="modal-header border-white-05">
                <h5 class="modal-title" id="lifecycleActionTitle">Confirm action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small mb-3" id="lifecycleActionText"></p>

                <label class="form-label small fw-bold text-muted">
                    Reason <span class="text-danger" id="lifecycleReasonRequired">*</span>
                </label>
                <textarea name="reason" id="lifecycleReasonInput" rows="3" maxlength="500"
                    class="form-control bg-dark-soft border-0 rounded-3"
                    placeholder="Why is this action being taken? Stored in the moderation log and shown to the user when they try to log in."></textarea>

                <div id="lifecycleDurationGroup" class="mt-3 d-none">
                    <label class="form-label small fw-bold text-muted">Suspension length</label>
                    <select id="lifecycleDurationSelect" class="form-select bg-dark-soft border-0 rounded-3">
                        <option value="24">24 hours</option>
                        <option value="72">3 days</option>
                        <option value="168" selected>7 days</option>
                        <option value="720">30 days</option>
                        <option value="custom">Custom end time…</option>
                    </select>
                    <input type="hidden" name="duration_hours" id="lifecycleDurationHours" value="168">
                    <input type="datetime-local" name="suspended_until" id="lifecycleUntilInput"
                        class="form-control bg-dark-soft border-0 rounded-3 mt-2 d-none" disabled>
                    <div class="form-text text-muted extra-small d-none" id="lifecycleUntilHint">
                        Interpreted as server time ({{ config('app.timezone', 'UTC') }}).
                    </div>
                </div>
            </div>

            <div class="modal-footer border-white-05">
                <button type="button" class="btn btn-outline-dynamic rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn bg-primary-gradient text-white rounded-3 border-0" id="lifecycleSubmitButton">Confirm</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('lifecycleActionModal');
    const modal = new bootstrap.Modal(modalElement);
    const form = document.getElementById('lifecycleActionForm');
    const actionInput = document.getElementById('lifecycleActionInput');
    const title = document.getElementById('lifecycleActionTitle');
    const text = document.getElementById('lifecycleActionText');
    const reasonInput = document.getElementById('lifecycleReasonInput');
    const reasonRequired = document.getElementById('lifecycleReasonRequired');
    const durationGroup = document.getElementById('lifecycleDurationGroup');
    const durationSelect = document.getElementById('lifecycleDurationSelect');
    const durationHours = document.getElementById('lifecycleDurationHours');
    const untilInput = document.getElementById('lifecycleUntilInput');
    const untilHint = document.getElementById('lifecycleUntilHint');
    const bulkIdsContainer = document.getElementById('lifecycleBulkIdsContainer');
    const submitButton = document.getElementById('lifecycleSubmitButton');

    const COPY = {
        activate: {
            title: 'Activate',
            text: 'Lifts any ban or suspension and restores access immediately. Reason is optional.',
            button: 'Activate',
            buttonClass: 'btn btn-success rounded-3 border-0',
            reasonRequired: false,
            duration: false,
        },
        suspend: {
            title: 'Suspend',
            text: 'Temporarily locks the account and signs the user out everywhere. Access returns automatically when the suspension ends.',
            button: 'Suspend',
            buttonClass: 'btn btn-warning rounded-3 border-0',
            reasonRequired: true,
            duration: true,
        },
        ban: {
            title: 'Ban',
            text: 'Permanently locks the account and signs the user out everywhere. Only an admin can lift a ban.',
            button: 'Ban',
            buttonClass: 'btn btn-danger rounded-3 border-0',
            reasonRequired: true,
            duration: false,
        },
    };

    function configure(action, label) {
        const copy = COPY[action];

        actionInput.value = action;
        title.textContent = copy.title + ' ' + label;
        text.textContent = copy.text;
        submitButton.textContent = copy.button;
        submitButton.className = copy.buttonClass;
        reasonInput.value = '';
        reasonInput.required = copy.reasonRequired;
        reasonRequired.classList.toggle('d-none', !copy.reasonRequired);
        durationGroup.classList.toggle('d-none', !copy.duration);
        durationSelect.value = '168';
        syncDurationInputs();
        modal.show();
    }

    function syncDurationInputs() {
        const custom = durationSelect.value === 'custom';

        durationHours.disabled = custom;
        durationHours.value = custom ? '' : durationSelect.value;
        untilInput.disabled = !custom;
        untilInput.required = custom && !durationGroup.classList.contains('d-none');
        untilInput.classList.toggle('d-none', !custom);
        untilHint.classList.toggle('d-none', !custom);

        if (custom) {
            const min = new Date(Date.now() + 10 * 60 * 1000);
            min.setSeconds(0, 0);
            untilInput.min = min.toISOString().slice(0, 16);
        }
    }

    durationSelect.addEventListener('change', syncDurationInputs);

    window.lifecycleAction = function (url, action, label) {
        form.action = url;
        bulkIdsContainer.innerHTML = '';
        configure(action, label);
    };

    window.lifecycleBulkAction = function (action, ids) {
        form.action = modalElement.dataset.bulkUrl;
        bulkIdsContainer.innerHTML = '';

        ids.forEach(function (id) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';
            input.value = id;
            bulkIdsContainer.appendChild(input);
        });

        configure(action, ids.length + ' selected user' + (ids.length === 1 ? '' : 's'));
    };
});
</script>
