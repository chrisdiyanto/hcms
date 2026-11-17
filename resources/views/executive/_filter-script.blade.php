{{-- Pencarian di tabel rincian <details> (input [data-unit-filter], baris [data-unit-row data-unit-name]) --}}
<script>
    document.querySelectorAll('[data-unit-filter]').forEach(function (input) {
        input.addEventListener('input', function () {
            var box = input.closest('details');
            var query = input.value.trim().toLocaleLowerCase();
            var visible = 0;
            box.querySelectorAll('[data-unit-row]').forEach(function (row) {
                var match = row.dataset.unitName.includes(query);
                row.hidden = !match;
                if (match) visible++;
            });
            var empty = box.querySelector('.exec-filter-empty');
            if (empty) empty.style.display = visible === 0 ? 'table-row' : 'none';
        });
    });
</script>
