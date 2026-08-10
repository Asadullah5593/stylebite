{{--
    Searchable multi-select built from plain checkboxes.

    A native <select multiple> is a small scroll box that needs Ctrl/Cmd-click
    and has no search, which is unusable past a handful of options. Checkboxes
    submit identically without any JavaScript, so filtering and the counter are
    pure enhancement — the form still works if the script never runs.

    Expects:
      $name        e.g. 'user_ids[]'
      $options     [['value' => 1, 'label' => 'Asad', 'sublabel' => 'a@b.com'], ...]
      $selected    array of already-selected values
      $placeholder search box placeholder
      $emptyText   shown when there is nothing to pick
      $note        optional footnote (e.g. a truncation warning)
--}}
@php
    $pickerId = 'picker-'.md5($name.uniqid());
    $selected = array_map('strval', (array) ($selected ?? []));
@endphp

<div class="checkbox-picker" data-picker="{{ $pickerId }}">
    @if (count($options) === 0)
        <div class="bg-dark-soft rounded-3 p-3 text-muted small">{{ $emptyText ?? 'Nothing available to choose.' }}</div>
    @else
        <div class="position-relative mb-2">
            <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-muted small"></i>
            <input type="text" class="form-control form-control-sm ps-5 bg-dark-soft border-0 rounded-3 picker-search"
                   placeholder="{{ $placeholder ?? 'Search…' }}" autocomplete="off">
        </div>

        <div class="bg-dark-soft rounded-3 border border-white-05 picker-list" style="max-height: 190px; overflow-y: auto;">
            @foreach ($options as $option)
                <label class="d-flex align-items-center gap-2 px-3 py-2 mb-0 picker-row" style="cursor: pointer;"
                       data-search="{{ Str::lower($option['label'].' '.($option['sublabel'] ?? '')) }}">
                    <input class="form-check-input mt-0 picker-box" type="checkbox" name="{{ $name }}"
                           value="{{ $option['value'] }}"
                           @checked(in_array((string) $option['value'], $selected, true))>
                    <span class="small">
                        <span class="fw-semibold">{{ $option['label'] }}</span>
                        @if (! empty($option['sublabel']))
                            <span class="text-muted extra-small d-block">{{ $option['sublabel'] }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
            <div class="px-3 py-3 text-muted small picker-empty d-none">No matches.</div>
        </div>

        <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
            <span class="text-muted extra-small picker-count">0 selected</span>
            <span class="d-flex gap-2">
                <a href="#" class="text-decoration-none extra-small picker-all">Select all shown</a>
                <a href="#" class="text-decoration-none extra-small picker-none">Clear</a>
            </span>
        </div>

        @if (! empty($note))
            <div class="form-text text-muted extra-small">{{ $note }}</div>
        @endif
    @endif
</div>

@once
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.checkbox-picker').forEach(function (picker) {
            const search = picker.querySelector('.picker-search');
            const rows = Array.from(picker.querySelectorAll('.picker-row'));
            const boxes = Array.from(picker.querySelectorAll('.picker-box'));
            const count = picker.querySelector('.picker-count');
            const empty = picker.querySelector('.picker-empty');

            if (!rows.length) {
                return;
            }

            function refreshCount() {
                const n = boxes.filter(b => b.checked).length;
                count.textContent = n + ' selected';
            }

            function visibleRows() {
                return rows.filter(r => !r.classList.contains('d-none'));
            }

            search?.addEventListener('input', function () {
                const term = search.value.trim().toLowerCase();

                rows.forEach(function (row) {
                    const match = term === '' || row.dataset.search.includes(term);
                    row.classList.toggle('d-none', !match);
                });

                empty?.classList.toggle('d-none', visibleRows().length > 0);
            });

            boxes.forEach(b => b.addEventListener('change', refreshCount));

            picker.querySelector('.picker-all')?.addEventListener('click', function (event) {
                event.preventDefault();
                visibleRows().forEach(function (row) {
                    const box = row.querySelector('.picker-box');
                    if (box) box.checked = true;
                });
                refreshCount();
            });

            picker.querySelector('.picker-none')?.addEventListener('click', function (event) {
                event.preventDefault();
                boxes.forEach(b => { b.checked = false; });
                refreshCount();
            });

            refreshCount();
        });
    });
    </script>
@endonce
